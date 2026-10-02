<?php

namespace App\Http\Controllers;

use App\Models\PendingSale;
use App\Models\Product;
use App\Models\ProductReplacement;
use App\Models\SalesTransaction;
use App\Models\StoreHub;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;

class HubController extends Controller
{
    use AuthorizesRequests;

    public function dashboard($id)
    {
        $user = auth()->user();
        if ($user && ! $user->canAccessHub((int) $id)) {
            abort(403, 'Unauthorized access to this store hub dashboard.');
        }

        $hub = StoreHub::findOrFail($id);

        $pendingSalesCount = PendingSale::where('store_hub_id', $id)
            ->where('status', 'pending')
            ->count()
            + ProductReplacement::where('status', 'pending')->whereHas('transaction', fn ($query) => $query->where('store_hub_id', $id))->count();

        $totalProducts = Product::where('store_hub_id', $id)->count();
        $totalSales = SalesTransaction::where('store_hub_id', $id)->sum('grand_total');

        return view('hubs.dashboard', compact('hub', 'totalProducts', 'totalSales', 'pendingSalesCount'));
    }

    public function index(Request $request)
    {
        $user = auth()->user();

        $hubsQuery = StoreHub::where('status', 'active');
        if ($user && ! in_array($user->role, ['admin', 'inventory_staff'], true)) {
            $hubsQuery->whereIn('id', $user->accessibleStoreHubIds());
        }

        $filters = $request->validate([
            'hub_id' => ['nullable', 'integer'],
            'date' => ['nullable', 'date_format:Y-m-d'],
            'from' => ['nullable', 'date_format:Y-m-d'],
            'to' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:from'],
            'channel' => ['nullable', 'in:shopee,lazada,tiktok,online,wholesale,walk_in'],
        ]);
        $endDateInput = $filters['to'] ?? $filters['date'] ?? now()->toDateString();
        if (isset($filters['from']) && $filters['from'] > $endDateInput) {
            throw ValidationException::withMessages([
                'from' => 'The from date must be on or before the to date.',
            ]);
        }
        $dashboardHubs = $hubsQuery->orderBy('name')->get();
        $hubId = $filters['hub_id'] ?? null;
        if (! $request->has('hub_id') && $user && $user->role !== 'admin') {
            $designatedHubId = (int) $user->hub_id;
            if ($dashboardHubs->contains('id', $designatedHubId)) {
                $hubId = $designatedHubId;
            }
        }
        if ($hubId) {
            abort_unless($dashboardHubs->contains('id', (int) $hubId), 403);
        } elseif ($dashboardHubs->count() === 1) {
            $hubId = (int) $dashboardHubs->first()->id;
        }
        $scopeIds = $hubId ? [(int) $hubId] : $dashboardHubs->modelKeys();
        $asOf = Carbon::parse($filters['to'] ?? $filters['date'] ?? now()->toDateString())->startOfDay();
        $fromDate = Carbon::parse($filters['from'] ?? $asOf->copy()->startOfMonth())->startOfDay();
        $periods = [
            'Daily' => $asOf->copy(), 'Weekly' => $asOf->copy()->startOfWeek(),
            'Monthly' => $asOf->copy()->startOfMonth(), 'Quarterly' => $asOf->copy()->startOfQuarter(),
            'Yearly' => $asOf->copy()->startOfYear(),
        ];
        $salesQuery = SalesTransaction::whereIn('store_hub_id', $scopeIds)
            ->whereIn('status', ['completed', 'confirmed'])
            ->where(function ($query) {
                $query->whereRaw("LOWER(REPLACE(REPLACE(channel_type, '-', '_'), ' ', '_')) <> 'wholesale'")
                    ->orWhere('payment_status', 'paid')
                    ->orWhere(function ($query) {
                        $query->where('payment_status', 'partial')->where('amount_paid', '>', 0);
                    });
            });
        $assignedChannels = $user?->role === 'sales_marketing_staff'
            ? collect($user->sales_channels ?? [])->map(fn ($channel) => strtolower(str_replace(['-', ' '], '_', (string) $channel)))->values()
            : collect();
        $dashboardChannelOptions = $assignedChannels->filter(fn ($channel) => in_array($channel, ['shopee', 'lazada', 'tiktok', 'online', 'wholesale', 'walk_in'], true))->values();
        $dashboardChannel = $filters['channel'] ?? null;
        if ($dashboardChannel && $user?->role === 'sales_marketing_staff' && ! $assignedChannels->contains($dashboardChannel)) {
            abort(403, 'You are not assigned to view this sales channel.');
        }
        if (! $dashboardChannel && $dashboardChannelOptions->isNotEmpty()) {
            $dashboardChannel = $dashboardChannelOptions->first();
        }
        if ($user?->role === 'sales_marketing_staff') {
            $salesQuery->whereIn('channel_type', $assignedChannels->all());
        }
        $marketplaceComparisonQuery = clone $salesQuery;
        if ($dashboardChannel) {
            $salesQuery->whereRaw("LOWER(REPLACE(REPLACE(channel_type, '-', '_'), ' ', '_')) = ?", [$dashboardChannel]);
        }
        $channelComparisonKeys = ['shopee', 'lazada', 'tiktok', 'online', 'wholesale', 'walk_in'];
        $marketplaceComparisonChannels = $dashboardHubs->whereIn('id', $scopeIds)->contains('is_head_office', true)
            ? ($dashboardChannel
                ? collect([$dashboardChannel])->filter(fn ($channel) => in_array($channel, $channelComparisonKeys, true))
                : ($user?->role === 'sales_marketing_staff'
                    ? $dashboardChannelOptions->filter(fn ($channel) => in_array($channel, $channelComparisonKeys, true))->values()
                    : collect($channelComparisonKeys)))
            : collect();
        $normalizeChannel = static function ($channel): string {
            $normalized = strtolower(str_replace(['-', ' '], '_', (string) $channel));

            return $normalized === 'walkin' ? 'walk_in' : $normalized;
        };
        $reportedAmount = static function (SalesTransaction $sale) use ($normalizeChannel): float {
            $channel = $normalizeChannel($sale->channel_type);
            $resolvedTotal = (float) ($sale->grand_total ?: $sale->total_amount ?: 0);

            if ($channel === 'tiktok') {
                return $sale->effectiveTikTokPayout();
            }
            if ($channel === 'wholesale' && $sale->payment_status === 'partial') {
                $resolvedTotal = min((float) ($sale->amount_paid ?? 0), $resolvedTotal);
            }

            return $sale->netOrderTotal($resolvedTotal);
        };
        $dashboardRelations = ['items.inventoryReturns', 'items.replacements.inventoryReturns', 'inventoryReturns'];
        $salesTotals = collect($periods)->map(fn ($start) => (float) (clone $salesQuery)
            ->whereBetween('order_date', [$start->greaterThan($fromDate) ? $start : $fromDate, $asOf->copy()->endOfDay()])
            ->with($dashboardRelations)->get()->sum($reportedAmount));
        $monthlyQuery = (clone $salesQuery)->whereBetween('order_date', [$fromDate, $asOf->copy()->endOfDay()]);
        $monthlyTransactions = (clone $monthlyQuery)->with($dashboardRelations)->get();
        $marketplaceSalesComparison = collect();
        $marketplaceCustomerComparison = collect();
        foreach ($marketplaceComparisonChannels as $comparisonChannel) {
            $lastYearFrom = $fromDate->copy()->subYear();
            $lastYearAsOf = $asOf->copy()->subYear();
            $channelQuery = fn () => (clone $marketplaceComparisonQuery)
                ->whereRaw("LOWER(REPLACE(REPLACE(channel_type, '-', '_'), ' ', '_')) = ?", [$comparisonChannel]);
            $lastYearSales = $channelQuery()
                ->whereBetween('order_date', [$lastYearFrom, $lastYearAsOf->copy()->endOfDay()])
                ->with($dashboardRelations)->get();
            $thisYearSales = $channelQuery()
                ->whereBetween('order_date', [$fromDate, $asOf->copy()->endOfDay()])
                ->with($dashboardRelations)->get();
            $marketplaceSalesComparison[$comparisonChannel] = collect([
                'last_year' => round((float) $lastYearSales->sum($reportedAmount), 2),
                'this_year' => round((float) $thisYearSales->sum($reportedAmount), 2),
            ]);
            $marketplaceCustomerComparison[$comparisonChannel] = collect([
                'last_year' => $lastYearSales->map(fn ($sale) => $sale->customerIdentityKey())->filter()->unique()->count(),
                'this_year' => $thisYearSales->map(fn ($sale) => $sale->customerIdentityKey())->filter()->unique()->count(),
            ]);
        }
        $customerHistory = (clone $salesQuery)
            ->where('order_date', '<', $asOf->copy()->addDay()->toDateString())
            ->get(['id', 'store_hub_id', 'channel_type', 'customer_name', 'contact_number', 'order_date'])
            ->groupBy(fn ($sale) => $normalizeChannel($sale->channel_type));
        $monthlySales = $monthlyTransactions
            ->reject(fn ($sale) => $normalizeChannel($sale->channel_type) === 'tiktok'
                && ($sale->sales_after_transaction_fee === null || $sale->isTikTokFullyReturned()))
            ->each(function ($sale) use ($reportedAmount) {
                $sale->setAttribute('transaction_count', 1);
                $sale->setAttribute('reported_total', $reportedAmount($sale));
            })->values();
        if ($user?->role === 'sales_marketing_staff') {
            $monthlySales = $monthlySales->filter(function ($sale) use ($assignedChannels) {
                $channel = strtolower(str_replace(['-', ' '], '_', (string) $sale->channel_type));

                return $assignedChannels->contains($channel);
            })->values();
        }
        $scopedHubs = $dashboardHubs->whereIn('id', $scopeIds);
        $storeSalesOverview = $dashboardHubs->whereIn('id', $scopeIds)
            ->map(function ($hub) use ($monthlySales) {
                $hubSales = $monthlySales->where('store_hub_id', $hub->id);

                return [
                    'id' => $hub->id,
                    'name' => $hub->name,
                    'mode' => $hub->is_head_office ? 'Head Office' : 'Branch',
                    'total' => round((float) $hubSales->sum('reported_total'), 2),
                    'transactions' => (int) $hubSales->sum('transaction_count'),
                ];
            })
            ->sortByDesc('total')
            ->values();
        $channelDefinitions = [
            'shopee' => ['label' => 'Shopee', 'icon' => 'fa-bag-shopping', 'color' => 'warning'],
            'lazada' => ['label' => 'Lazada', 'icon' => 'fa-store', 'color' => 'info'],
            'tiktok' => ['label' => 'TikTok', 'icon' => 'fa-music', 'color' => 'dark'],
            'online' => ['label' => 'Online', 'icon' => 'fa-globe', 'color' => 'primary'],
            'wholesale' => ['label' => 'Wholesale', 'icon' => 'fa-building', 'color' => 'success'],
            'walk_in' => ['label' => 'Walk-In', 'icon' => 'fa-cash-register', 'color' => 'secondary'],
        ];
        $availableChannels = $scopedHubs->contains('is_head_office', true)
            ? ['shopee', 'lazada', 'tiktok', 'online', 'wholesale', 'walk_in']
            : ['walk_in'];
        if ($user?->role === 'sales_marketing_staff') {
            $availableChannels = array_values(array_intersect($availableChannels, $assignedChannels->all()));
        }
        if ($dashboardChannel) {
            $availableChannels = array_values(array_intersect($availableChannels, [$dashboardChannel]));
        }
        $isTikTokDashboard = $availableChannels === ['tiktok'];
        $tiktokTransactions = $monthlyTransactions
            ->filter(fn ($sale) => $normalizeChannel($sale->channel_type) === 'tiktok')
            ->map(function ($sale) {
                $sale->setAttribute('fully_returned', $sale->isTikTokFullyReturned());

                return $sale;
            });
        $tiktokOverview = [
            'orders' => $tiktokTransactions->count(),
            'payout_entered' => $tiktokTransactions->filter(fn ($sale) => ! $sale->fully_returned && $sale->sales_after_transaction_fee !== null)->count(),
            'recalculated' => $tiktokTransactions->filter(fn ($sale) => ! $sale->fully_returned && $sale->tiktok_recalculated_payout_entered)->count(),
            'awaiting' => $tiktokTransactions->filter(fn ($sale) => ! $sale->fully_returned && $sale->sales_after_transaction_fee === null)->count(),
            'fully_returned' => $tiktokTransactions->where('fully_returned', true)->count(),
            'final_payout' => $tiktokTransactions->sum(fn ($sale) => $sale->effectiveTikTokPayout()),
        ];
        $channelSummaries = collect($availableChannels)->map(function ($channel) use ($channelDefinitions, $monthlySales, $customerHistory, $fromDate, $asOf) {
            $channelSales = $monthlySales->filter(function ($sale) use ($channel) {
                $value = strtolower(str_replace(['-', ' '], '_', (string) $sale->channel_type));
                $value = $value === 'walkin' ? 'walk_in' : $value;

                return $value === $channel;
            });
            $firstCustomerOrders = [];
            foreach ($customerHistory->get($channel, collect()) as $sale) {
                $identity = $sale->customerIdentityKey();
                if ($identity === null) {
                    continue;
                }
                $firstCustomerOrders[$identity] = min(
                    $firstCustomerOrders[$identity] ?? $sale->order_date->toDateString(),
                    $sale->order_date->toDateString()
                );
            }
            $periodStart = $fromDate->toDateString();
            $monthEnd = $asOf->toDateString();

            return array_merge($channelDefinitions[$channel], [
                'key' => $channel,
                'transactions' => $channelSales->sum('transaction_count'),
                'total' => $channelSales->sum('reported_total'),
                'refund_total' => $channelSales->sum(fn ($sale) => max(
                    (float) $sale->items->sum(fn ($item) => $item->refundCostAmount()),
                    (float) $sale->inventoryReturns->whereNull('transaction_item_id')->sum('refund_amount')
                )),
                'total_customers' => count($firstCustomerOrders),
                'new_customers' => collect($firstCustomerOrders)->filter(fn ($firstOrder) => $firstOrder >= $periodStart && $firstOrder <= $monthEnd)->count(),
                'completed_transactions' => $channel === 'wholesale'
                    ? $channelSales->filter(fn ($sale) => $sale->payment_status === 'paid' && $sale->delivery_status === 'delivered')->sum('transaction_count')
                    : $channelSales->sum('transaction_count'),
                'partial_transactions' => $channel === 'wholesale'
                    ? $channelSales->where('payment_status', 'partial')->sum('transaction_count')
                    : 0,
                'open_transactions' => $channel === 'wholesale'
                    ? $channelSales->reject(fn ($sale) => $sale->payment_status === 'paid' && $sale->delivery_status === 'delivered')->sum('transaction_count')
                    : 0,
            ]);
        });
        $marketplacePaymentMaps = [
            'shopee' => ['COD' => 'COD', 'SPAYLATER' => 'SPayLater', 'MIXEDCARD' => 'Mixedcard', 'CREDITDEBITCARD' => 'Credit/Debit Card', 'GCASH' => 'GCASH', 'SHOPEEPAYBALANCE' => 'ShopeePay Balance', 'ONLINEOFFLINEPAYMENT' => 'Online/Offline Payment', 'QRPH' => 'QRph'],
            'lazada' => ['COD' => 'COD', 'PAYLATER' => 'PayLater', 'MIXEDCARD' => 'Mixedcard', 'CREDITDEBITCARD' => 'Credit/Debit Card', 'GCASH' => 'GCASH', 'ONLINEOFFLINEPAYMENT' => 'Online/Offline Payment', 'QRPH' => 'QRph'],
        ];
        $marketplacePaymentMap = $marketplacePaymentMaps[$dashboardChannel] ?? [];
        $branchPaymentScope = $scopedHubs->count() === 1 && ! $scopedHubs->first()->is_head_office;
        $paymentColumns = $branchPaymentScope
            ? ['Cash', 'GCash', 'PayMaya', 'QRPH', 'BPI', 'BDO', 'Metrobank', 'Other']
            : ($marketplacePaymentMap
            ? array_values(array_unique([...array_values($marketplacePaymentMap), 'Other']))
            : ['Cash', 'GCash', 'Maya', 'BDO', 'BPI', 'Dated Check', 'Post-Dated Check', 'Other']);
        $matrix = $dashboardHubs->whereIn('id', $scopeIds)->map(function ($hub) use ($monthlySales, $paymentColumns, $marketplacePaymentMap, $branchPaymentScope) {
            $amounts = array_fill_keys($paymentColumns, 0);
            $counts = array_fill_keys($paymentColumns, 0);
            foreach ($monthlySales->where('store_hub_id', $hub->id) as $sale) {
                if (strtolower(str_replace(['-', ' '], '_', (string) $sale->channel_type)) === 'tiktok') {
                    continue;
                }
                $method = strtoupper(str_replace([' ', '_', '-'], '', (string) ($sale->mode_of_payment ?: $sale->custom_mop)));
                $bank = strtoupper(trim((string) ($sale->bank_name ?: $sale->custom_bank_name)));
                $key = $marketplacePaymentMap[$method] ?? match (true) {
                    $method === 'CASH' => 'Cash', $method === 'GCASH' => 'GCash',
                    in_array($method, ['MAYA', 'PAYMAYA'], true) => $branchPaymentScope ? 'PayMaya' : 'Maya',
                    $branchPaymentScope && $method === 'QRPH' => 'QRPH',
                    $branchPaymentScope && $method === 'METROBANK' => 'Metrobank',
                    $method === 'DATEDCHECK' => 'Dated Check',
                    $method === 'POSTDATEDCHECK' => 'Post-Dated Check',
                    $method === 'BDO' || $bank === 'BDO' => 'BDO',
                    $method === 'BPI' || $bank === 'BPI' => 'BPI', default => 'Other',
                };
                if (! array_key_exists($key, $amounts)) {
                    $key = 'Other';
                }
                $amounts[$key] += (float) $sale->reported_total;
                $counts[$key]++;
            }

            return ['name' => $hub->name, 'amounts' => $amounts, 'counts' => $counts];
        });
        $channelPaymentOverview = null;
        if (
            in_array($dashboardChannel, ['online', 'walk_in', 'wholesale'], true)
            && $scopedHubs->contains('is_head_office', true)
        ) {
            $amounts = array_fill_keys($paymentColumns, 0.0);
            $counts = array_fill_keys($paymentColumns, 0);
            foreach ($matrix as $hubPayment) {
                foreach ($paymentColumns as $method) {
                    $amounts[$method] += (float) $hubPayment['amounts'][$method];
                    $counts[$method] += (int) $hubPayment['counts'][$method];
                }
            }
            $channelPaymentOverview = [
                'label' => match ($dashboardChannel) {
                    'online' => 'Online Sales Overview',
                    'walk_in' => 'Walk-In Sales Overview',
                    'wholesale' => 'Wholesale Sales Overview',
                },
                'amounts' => $amounts,
                'counts' => $counts,
                'total' => array_sum($amounts),
                'transactions' => array_sum($counts),
            ];
        }
        $marketplaceOverviewChannels = $dashboardChannel
            ? collect([$dashboardChannel])->filter(fn ($channel) => isset($marketplacePaymentMaps[$channel]))
            : $dashboardChannelOptions->filter(fn ($channel) => isset($marketplacePaymentMaps[$channel]));
        $marketplaceOverviews = $marketplaceOverviewChannels->map(function ($channel) use ($marketplacePaymentMaps, $monthlySales, $dashboardHubs, $scopeIds) {
            $methodMap = $marketplacePaymentMaps[$channel];
            $columns = array_values(array_unique([...array_values($methodMap), 'Other']));
            $rows = $dashboardHubs->whereIn('id', $scopeIds)->map(function ($hub) use ($monthlySales, $channel, $methodMap, $columns) {
                $amounts = array_fill_keys($columns, 0);
                $counts = array_fill_keys($columns, 0);
                foreach ($monthlySales->where('store_hub_id', $hub->id) as $sale) {
                    $saleChannel = strtolower(str_replace(['-', ' '], '_', (string) $sale->channel_type));
                    if ($saleChannel !== $channel) {
                        continue;
                    }
                    $method = strtoupper(str_replace([' ', '_', '-'], '', (string) ($sale->mode_of_payment ?: $sale->custom_mop)));
                    $key = $methodMap[$method] ?? 'Other';
                    $amounts[$key] += (float) $sale->reported_total;
                    $counts[$key]++;
                }

                return ['name' => $hub->name, 'amounts' => $amounts, 'counts' => $counts];
            });

            return ['channel' => $channel, 'rows' => $rows, 'total' => $rows->sum(fn ($row) => array_sum($row['amounts']))];
        })->values();
        $soldUnits = collect();
        foreach ($monthlySales as $sale) {
            $isTikTok = $normalizeChannel($sale->channel_type) === 'tiktok';
            foreach ($sale->items as $item) {
                if (! $isTikTok) {
                    $soldUnits[$item->product_id] = ($soldUnits[$item->product_id] ?? 0) + (int) $item->quantity;
                    continue;
                }

                $approvedReplacements = $item->replacements->where('status', 'approved');
                $returnedQuantity = max((int) ($item->returned_quantity ?? 0), (int) $item->inventoryReturns->sum('quantity'));
                $originalRemaining = max(0, (int) $item->quantity - $returnedQuantity - (int) $approvedReplacements->sum('quantity'));
                $soldUnits[$item->product_id] = ($soldUnits[$item->product_id] ?? 0) + $originalRemaining;
                foreach ($approvedReplacements as $replacement) {
                    $replacementRemaining = max(0,
                        (int) ($replacement->replacement_quantity ?: $replacement->quantity)
                        - (int) $replacement->inventoryReturns->sum('quantity')
                    );
                    $soldUnits[$replacement->replacement_product_id] = ($soldUnits[$replacement->replacement_product_id] ?? 0) + $replacementRemaining;
                }
            }
        }
        $topUnits = $soldUnits->sortDesc()->take(10);
        $topNames = Product::whereIn('id', $topUnits->keys())->get()->keyBy('id');
        $topProducts = $topUnits->map(fn ($units, $productId) => [
            'name' => $topNames->get($productId)?->name ?? 'Unavailable product',
            'units' => (int) $units,
        ])->values();
        $slowProducts = Product::whereIn('store_hub_id', $scopeIds)
            ->where('status', 'active')
            ->where('stock', '>', 0)
            ->when(in_array($dashboardChannel, ['shopee', 'lazada'], true), function ($query) use ($dashboardChannel, $soldUnits) {
                $query->where(function ($query) use ($dashboardChannel, $soldUnits) {
                    $query->whereIn('products.id', $soldUnits->keys())
                        ->orWhereHas('stockAllocation', fn ($allocation) => $allocation->where($dashboardChannel, '>', 0));
                });
            })
            ->get()
            ->map(fn ($product) => [
                'id' => $product->id,
                'name' => $product->name ?: $product->item_id,
                'stock' => (int) $product->stock,
                'units' => (int) ($soldUnits[$product->id] ?? 0),
            ])
            ->sortBy(fn ($product) => [$product['units'], $product['id']])
            ->values();
        $slowProducts = (new LengthAwarePaginator(
            $slowProducts->forPage(LengthAwarePaginator::resolveCurrentPage('slow_page'), 10)->values(),
            $slowProducts->count(),
            10,
            LengthAwarePaginator::resolveCurrentPage('slow_page'),
            ['path' => $request->url(), 'pageName' => 'slow_page']
        ))->withQueryString();
        $alertsQuery = Product::with('storeHub')
            ->whereIn('store_hub_id', $scopeIds)
            ->where('status', 'active')
            ->whereBetween('stock', [0, 30]);
        $alerts = $alertsQuery
            ->orderBy('stock')
            ->orderByCatalog()
            ->paginate(10, ['*'], 'alerts_page')
            ->withQueryString();
        $alertCount = $alerts->total();
        $pendingVerificationCount = $user?->role === 'admin'
            ? PendingSale::whereIn('store_hub_id', $scopeIds)->where('status', 'pending')->count()
                + ProductReplacement::where('status', 'pending')->whereHas('transaction', fn ($query) => $query->whereIn('store_hub_id', $scopeIds))->count()
            : 0;
        $scopeName = $hubId ? $dashboardHubs->firstWhere('id', $hubId)->name : 'All accessible stores';
        $scopedDashboardHubs = $dashboardHubs->whereIn('id', $scopeIds);
        $isAllStoresAdminDashboard = $user?->role === 'admin' && $hubId === null;
        $isBranchDashboard = $scopedDashboardHubs->count() === 1
            && ! $scopedDashboardHubs->first()->is_head_office;

        return view('dashboard', compact('dashboardHubs', 'hubId', 'fromDate', 'asOf', 'salesTotals', 'paymentColumns', 'matrix', 'channelPaymentOverview', 'topProducts', 'slowProducts', 'alerts', 'alertCount', 'scopeName', 'channelSummaries', 'pendingVerificationCount', 'isTikTokDashboard', 'tiktokOverview', 'dashboardChannel', 'dashboardChannelOptions', 'marketplaceOverviews', 'marketplaceSalesComparison', 'marketplaceCustomerComparison', 'isBranchDashboard', 'isAllStoresAdminDashboard', 'storeSalesOverview'));
    }

    public function destroy($id)
    {
        $this->authorize('full-access');

        $hub = StoreHub::findOrFail($id);
        $hub->delete();

        return redirect()->back()->with('success', 'Hub deleted successfully.');
    }
}
