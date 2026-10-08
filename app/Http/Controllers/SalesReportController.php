<?php

namespace App\Http\Controllers;

use App\Support\KeywordSearch;
use App\Models\SalesTransaction;
use App\Models\SalesPaymentRecord;
use App\Models\StoreHub;
use App\Models\InventoryTransaction;
use App\Models\Product;
use App\Models\ProductReplacement;
use App\Models\ProductStockAllocation;
use App\Models\User;
use App\Notifications\InventoryWorkflowNotification;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Illuminate\Support\Str;

class SalesReportController extends Controller
{
    private const REPORT_CHANNELS = ['online', 'wholesale', 'walk_in'];

    public function tiktokReturns(Request $request, int $hub)
    {
        return $this->marketplaceReturnsForChannel($request, $hub, 'tiktok');
    }

    public function marketplaceReturns(Request $request, int $hub)
    {
        $filters = $request->validate([
            'channel' => ['required', 'in:shopee,lazada'],
        ]);

        return $this->marketplaceReturnsForChannel($request, $hub, $filters['channel']);
    }

    private function marketplaceReturnsForChannel(Request $request, int $hub, string $channel)
    {
        $user = auth()->user();
        $storeHub = StoreHub::findOrFail($hub);

        abort_unless($user && $user->canAccessHub($storeHub->id), 403);
        abort_unless(
            $user->role === 'admin'
                || (in_array($user->role, ['sales_associate', 'sales_marketing_staff'], true)
                    && $user->hasSalesChannel($channel)),
            403
        );
        abort_unless($storeHub->is_head_office, 404);

        $filters = $request->validate([
            'date_from' => ['nullable', 'date'],
            'date_to' => ['nullable', 'date', 'after_or_equal:date_from'],
        ]);

        $transactions = SalesTransaction::query()
            ->where('store_hub_id', $storeHub->id)
            ->whereRaw("LOWER(REPLACE(REPLACE(channel_type, '-', '_'), ' ', '_')) = ?", [$channel])
            ->whereHas('items', fn ($items) => $items->where(function ($returnedItems) {
                $returnedItems->whereHas('inventoryReturns', fn ($returns) => $returns->where('quantity', '>', 0))
                    ->orWhere(fn ($legacyReturns) => $legacyReturns
                        ->where('return_status', 'received')
                        ->where('returned_quantity', '>', 0));
            }))
            ->with([
                'items.product',
                'items.inventoryReturns',
                'items.replacements.inventoryReturns',
                'items.replacements.replacementProduct',
            ])
            ->when($filters['date_from'] ?? null, fn ($query, $date) => $query->where('order_date', '>=', $date))
            ->when($filters['date_to'] ?? null, fn ($query, $date) => $query->where('order_date', '<', Carbon::parse($date)->addDay()->toDateString()))
            ->orderByDesc('order_date')
            ->orderByDesc('id')
            ->paginate(20)
            ->withQueryString();

        $channelLabel = $channel === 'tiktok' ? 'TikTok' : ucfirst($channel);

        return view('hubs.tiktok-returns', [
            'hub' => $storeHub,
            'transactions' => $transactions,
            'channel' => $channelLabel,
            'channelKey' => $channel,
            'dateFrom' => $filters['date_from'] ?? null,
            'dateTo' => $filters['date_to'] ?? null,
        ]);
    }

    public function fullyBookedReturns(Request $request, int $hub)
    {
        $user = auth()->user();
        $storeHub = StoreHub::findOrFail($hub);

        abort_unless($user && $user->canAccessHub($storeHub->id), 403);
        abort_unless(
            $user->role === 'admin'
                || ($user->role === 'sales_marketing_staff' && $user->hasSalesChannel('fully_booked')),
            403
        );

        $filters = $request->validate([
            'date_from' => ['nullable', 'date'],
            'date_to' => ['nullable', 'date', 'after_or_equal:date_from'],
        ]);

        $transactions = SalesTransaction::query()
            ->where('store_hub_id', $storeHub->id)
            ->whereRaw('LOWER(REPLACE(REPLACE(channel_type, \'-\', \'_\'), \' \', \'_\')) = ?', ['fully_booked'])
            ->whereHas('inventoryReturns', fn ($query) => $query
                ->when($filters['date_from'] ?? null, fn ($returns, $date) => $returns->where('occurred_on', '>=', $date))
                ->when($filters['date_to'] ?? null, fn ($returns, $date) => $returns->where('occurred_on', '<=', $date)))
            ->with([
                'inventoryReturns.product',
                'inventoryReturns.transactionItem.product',
                'inventoryReturns.productReplacement.replacementProduct',
            ])
            ->orderByDesc('order_date')
            ->orderByDesc('id')
            ->paginate(20)
            ->withQueryString();

        return view('hubs.fully-booked-returns', [
            'hub' => $storeHub,
            'transactions' => $transactions,
            'dateFrom' => $filters['date_from'] ?? null,
            'dateTo' => $filters['date_to'] ?? null,
        ]);
    }

    public function report(Request $request, int $hub)
    {
        $user = auth()->user();
        if ($user && ! $user->canAccessHub($hub)) {
            abort(403, 'Unauthorized access to this store hub report.');
        }

        $validated = $request->validate([
            'channel' => ['nullable', 'string'],
            'date_from' => ['nullable', 'date'],
            'date_to' => ['nullable', 'date', 'after_or_equal:date_from'],
            'transaction_state' => ['nullable', 'in:all,open,completed'],
            'search' => ['nullable', 'string', 'max:255'],
        ]);

        $hub = StoreHub::findOrFail($hub);
        if ($user && ! $hub->is_head_office && ! in_array($user->role, ['admin', 'inventory_staff'], true)
            && (int) $user->store_hub_id !== (int) $hub->id) {
            abort(403, 'Sales staff can only view the report for their designated store branch.');
        }
        $channel = $this->normalizeChannel($validated['channel'] ?? null);
        abort_if($channel === 'tiktok', 404);
        if (! $hub->is_head_office) {
            $channel = 'walk_in';
        } elseif (in_array($user?->role, ['sales_associate', 'sales_marketing_staff'], true)) {
            $userChannels = collect($user->sales_channels ?? [])
                ->map(fn ($assignedChannel) => $this->normalizeChannel($assignedChannel))
                ->values()
                ->all();
            $assignedChannels = array_values(array_intersect(self::REPORT_CHANNELS, $userChannels));

            if ($channel === 'all') {
                $channel = $this->normalizeChannel($assignedChannels[0] ?? null);
            }

            if ($channel === 'all' || ! in_array($channel, $assignedChannels, true)) {
                abort(403, 'You are not assigned to view this sales channel.');
            }
        }
        $dateFrom = $validated['date_from'] ?? null;
        $dateTo = $validated['date_to'] ?? null;
        $search = trim($validated['search'] ?? '');
        $transactionState = $channel === 'wholesale' ? ($validated['transaction_state'] ?? 'all') : 'all';

        $query = SalesTransaction::query()
            ->where('store_hub_id', $hub->id)
            ->with([
                'items.product',
                'items.inventoryReturns',
                'items.replacements.originalProduct',
                'items.replacements.replacementProduct',
                'items.replacements.inventoryReturns',
                'items.replacements.creator',
                'inventoryReturns',
                'paymentRecords.recorder',
            ]);

        if ($channel !== 'all') {
            $query->where(function ($builder) use ($channel) {
                $builder->whereRaw('LOWER(channel_type) = ?', [$channel])
                    ->orWhereRaw('LOWER(channel_type) = ?', [str_replace('_', '-', $channel)])
                    ->orWhereRaw('LOWER(channel_type) = ?', [str_replace('_', ' ', $channel)]);
            });
        } else {
            $query->whereRaw("LOWER(REPLACE(REPLACE(channel_type, '-', '_'), ' ', '_')) IN ('online', 'wholesale', 'walk_in')");
        }

        $reportDateColumn = in_array($channel, ['shopee', 'lazada'], true)
            ? 'date_of_arrangement'
            : 'order_date';
        if ($dateFrom) {
            $query->where($reportDateColumn, '>=', $dateFrom);
        }
        if ($dateTo) {
            $query->where($reportDateColumn, '<', Carbon::parse($dateTo)->addDay()->toDateString());
        }
        if ($search !== '') {
            $query->where(fn ($builder) => KeywordSearch::apply($builder, $search, ['customer_name', 'order_number', 'note']));
        }
        $stateCounts = ['all' => 0, 'open' => 0, 'completed' => 0];
        if ($channel === 'wholesale') {
            $completedScope = fn ($builder) => $builder
                ->where('payment_status', 'paid')
                ->where('delivery_status', 'delivered');
            $stateCounts['all'] = (clone $query)->count();
            $stateCounts['completed'] = (clone $query)->where($completedScope)->count();
            $stateCounts['open'] = $stateCounts['all'] - $stateCounts['completed'];

            if ($transactionState === 'completed') {
                $query->where($completedScope);
            } elseif ($transactionState === 'open') {
                $query->where(function ($builder) {
                    $builder->whereNull('payment_status')
                        ->orWhere('payment_status', '<>', 'paid')
                        ->orWhereNull('delivery_status')
                        ->orWhere('delivery_status', '<>', 'delivered');
                });
            }
        }

        $allTransactions = (clone $query)->orderBy('order_date')->get();
        $transactions = (clone $query)
            ->orderByDesc('order_date')
            ->orderByDesc('id')
            ->paginate(20)
            ->withQueryString();
        $wholesaleReturns = $channel === 'wholesale'
            ? InventoryTransaction::query()
                ->where('store_hub_id', $hub->id)
                ->where('type', 'return')
                ->whereRaw('LOWER(channel) = ?', ['wholesale'])
                ->whereNotNull('reference')
                ->when($dateFrom, fn ($builder) => $builder->where('occurred_on', '>=', $dateFrom))
                ->when($dateTo, fn ($builder) => $builder->where('occurred_on', '<', Carbon::parse($dateTo)->addDay()->toDateString()))
                ->get()
                ->groupBy(fn ($return) => trim($return->reference).'|'.$return->product_id)
            : collect();

        $resolveTotal = fn (SalesTransaction $transaction): float => $transaction->netOrderTotal(
            $this->resolveReportedTotal($transaction)
        );
        $currentGross = function (SalesTransaction $transaction): float {
            $gross = (float) $transaction->items->sum(fn ($item) => (float) $item->unit_price * ((int) $item->quantity - $item->returnedQuantity()));
            $gross -= (float) $transaction->replacements->where('status', 'approved')->sum(fn ($replacement) => (float) $replacement->original_unit_price * (int) $replacement->quantity);
            $gross += (float) $transaction->replacements->where('status', 'approved')->sum(fn ($replacement) => (float) $replacement->replacement_unit_price * (int) ($replacement->replacement_quantity ?: $replacement->quantity));

            return max(0, round($gross, 2));
        };
        $currentNet = $resolveTotal;
        $grossSales = $allTransactions->sum($currentGross);
        $returnRefunds = $returnRecords = InventoryTransaction::query()
            ->where('store_hub_id', $hub->id)
            ->where('type', 'return')
            ->when($channel !== 'all', fn ($builder) => $builder->whereRaw('LOWER(channel) = ?', [$channel]), fn ($builder) => $builder->whereRaw("LOWER(REPLACE(REPLACE(channel, '-', '_'), ' ', '_')) IN ('online', 'wholesale', 'walk_in')"))
            ->when($dateFrom, fn ($builder) => $builder->where('occurred_on', '>=', $dateFrom))
            ->when($dateTo, fn ($builder) => $builder->where('occurred_on', '<', Carbon::parse($dateTo)->addDay()->toDateString()))
            ->get();
        $returnRefundTotal = (float) $returnRefunds->sum('refund_amount');
        $matchedReturnIds = collect();
        $refundTotal = (float) $allTransactions->sum(function ($transaction) use ($returnRefunds, $matchedReturnIds) {
            $itemIds = $transaction->items->modelKeys();
            $linkedReturns = $returnRefunds->filter(function ($return) use ($transaction, $itemIds) {
                if ((int) $return->sales_transaction_id === (int) $transaction->id
                    || ($return->transaction_item_id && in_array((int) $return->transaction_item_id, $itemIds, true))) {
                    return true;
                }

                return ! $return->sales_transaction_id
                    && ! $return->transaction_item_id
                    && trim((string) $return->reference) !== ''
                    && trim((string) $return->reference) === trim((string) $transaction->order_number);
            });
            foreach ($linkedReturns as $return) {
                $matchedReturnIds->push($return->id);
            }
            $itemRefunds = $transaction->items->sum(fn ($item) => $item->refundCostAmount());

            return max($itemRefunds, (float) $linkedReturns->sum('refund_amount'));
        });
        $refundTotal += (float) $returnRefunds
            ->reject(fn ($return) => $matchedReturnIds->contains($return->id))
            ->sum('refund_amount');
        $totalSales = $allTransactions->sum($currentNet);
        $wholesaleCollectedSales = $channel === 'wholesale'
            ? $allTransactions->sum(function ($transaction) {
                if (! in_array(strtolower((string) $transaction->payment_status), ['paid', 'partial'], true)) {
                    return 0;
                }

                $orderTotal = (float) ($transaction->grand_total ?: $transaction->total_amount ?: $transaction->items->sum('line_total'));

                return min($orderTotal, max(0, (float) ($transaction->amount_paid ?? 0)));
            })
            : 0;
        $totalTransactions = $allTransactions->count();
        $totalPurchasedItems = $allTransactions->sum(fn ($transaction) => $transaction->items->sum(
            fn ($item) => max(0, (int) $item->quantity - $item->returnedQuantity())
        ));
        $customerHistoryQuery = SalesTransaction::query()
            ->where('store_hub_id', $hub->id)
            ->when($channel !== 'all', function ($builder) use ($channel) {
                $builder->where(function ($builder) use ($channel) {
                    $builder->whereRaw('LOWER(channel_type) = ?', [$channel])
                        ->orWhereRaw('LOWER(channel_type) = ?', [str_replace('_', '-', $channel)])
                        ->orWhereRaw('LOWER(channel_type) = ?', [str_replace('_', ' ', $channel)]);
                });
            }, function ($builder) {
                $builder->whereRaw("LOWER(REPLACE(REPLACE(channel_type, '-', '_'), ' ', '_')) IN ('online', 'wholesale', 'walk_in')");
            })
            ->when($dateTo, function ($builder) use ($reportDateColumn, $dateTo) {
                if ($reportDateColumn === 'date_of_arrangement') {
                    $exclusiveEnd = Carbon::parse($dateTo)->addDay()->toDateString();
                    $builder->where(function ($dateQuery) use ($exclusiveEnd) {
                        $dateQuery->where('date_of_arrangement', '<', $exclusiveEnd)
                            ->orWhere(function ($fallbackQuery) use ($exclusiveEnd) {
                                $fallbackQuery->whereNull('date_of_arrangement')
                                    ->where('order_date', '<', $exclusiveEnd);
                            });
                    });
                } else {
                    $builder->where($reportDateColumn, '<', Carbon::parse($dateTo)->addDay()->toDateString());
                }
            })
            ->orderByRaw('COALESCE('.$reportDateColumn.', order_date)')
            ->orderBy('id')
            ->get(['id', 'channel_type', 'customer_name', 'contact_number', 'order_date', 'date_of_arrangement']);
        $firstCustomerOrders = [];
        foreach ($customerHistoryQuery as $sale) {
            $identity = $sale->customerIdentityKey();
            if ($identity === null) {
                continue;
            }
            $key = $this->normalizeChannel($sale->channel_type).'|'.$identity;
            $firstCustomerOrders[$key] ??= [
                'date' => optional($sale->{$reportDateColumn})->toDateString() ?: $sale->order_date->toDateString(),
                'sale_id' => (int) $sale->id,
            ];
        }
        $reportCustomers = $allTransactions->map(function ($sale) {
            $identity = $sale->customerIdentityKey();

            return $identity === null ? null : $this->normalizeChannel($sale->channel_type).'|'.$identity;
        })->filter()->unique();
        $newCustomerStart = $dateFrom ?: '0001-01-01';
        $newCustomerEnd = $dateTo ?: now()->toDateString();
        $isNewCustomerOrder = static function ($identity) use ($firstCustomerOrders, $newCustomerStart, $newCustomerEnd): bool {
            $firstOrder = $firstCustomerOrders[$identity] ?? null;

            return $firstOrder !== null && $firstOrder['date'] !== null
                && $firstOrder['date'] >= $newCustomerStart
                && $firstOrder['date'] <= $newCustomerEnd;
        };
        $customerMetrics = [
            'total' => $reportCustomers->count(),
            'new' => $reportCustomers->filter($isNewCustomerOrder)->count(),
        ];
        $newCustomerSaleIds = $allTransactions->filter(function ($sale) use ($isNewCustomerOrder, $firstCustomerOrders) {
            $identity = $sale->customerIdentityKey();
            if ($identity === null) {
                return false;
            }
            $key = $this->normalizeChannel($sale->channel_type).'|'.$identity;
            $firstOrder = $firstCustomerOrders[$key] ?? null;

            return $firstOrder !== null
                && $firstOrder['sale_id'] === (int) $sale->id
                && $isNewCustomerOrder($key);
        })->modelKeys();
        $replacementCount = in_array($channel, ['wholesale', 'online'], true)
            ? $allTransactions->sum(fn ($transaction) => $transaction->replacements->count())
            : 0;
        $returnCount = in_array($channel, ['wholesale', 'online'], true)
            ? InventoryTransaction::query()
                ->where('store_hub_id', $hub->id)
                ->where('type', 'return')
                ->whereRaw('LOWER(channel) = ?', [$channel])
                ->when($dateFrom, fn ($builder) => $builder->where('occurred_on', '>=', $dateFrom))
                ->when($dateTo, fn ($builder) => $builder->where('occurred_on', '<', Carbon::parse($dateTo)->addDay()->toDateString()))
                ->count()
            : 0;
        $metrics = [
            'gross_sales' => $grossSales,
            'discounts' => $allTransactions->sum(fn ($transaction) => max(0, $currentGross($transaction) - $currentNet($transaction))),
            'total_sales' => $totalSales,
            'shipping_fees' => $allTransactions->sum('shipping_fee_amount'),
            'shipping_service_fees' => $allTransactions->sum('shipping_service_fee'),
            'refund_shipping_fees' => $allTransactions->sum('refund_shipping_fee'),
            'actual_platform_payout' => $allTransactions->sum('sales_after_transaction_fee'),
            'net_platform_payout' => $allTransactions->sum(fn ($transaction) => $transaction->netTikTokPayout() ?? 0),
            'customer_refund_amount' => $allTransactions->sum(fn ($transaction) => $transaction->completedCustomerRefunds()),
            'pending_payouts' => $allTransactions->whereNull('sales_after_transaction_fee')->count(),
            'proof_amount' => $allTransactions->sum('proof_amount'),
            'difference' => $allTransactions->sum(function ($transaction) use ($resolveTotal) {
                if ($transaction->proof_amount === null) {
                    return 0;
                }

                $saleAmount = (float) ($transaction->sub_total ?: $resolveTotal($transaction));

                return (float) $transaction->proof_amount
                    - $saleAmount;
            }),
            'replacement_count' => $replacementCount,
            'return_count' => $returnCount,
            'return_quantity' => $returnRecords->sum('quantity'),
            'return_refund_total' => $returnRefundTotal,
            'refund_total' => $refundTotal,
        ];
        if (in_array($channel, ['shopee', 'lazada'], true)) {
            $metrics['discounts'] = $allTransactions->sum(function ($transaction) {
                $itemDiscounts = $transaction->items->sum(function ($item) {
                    $replacedQuantity = (int) $item->replacements
                        ->where('status', 'approved')
                        ->sum('quantity');
                    $discountableQuantity = max(0, (int) $item->quantity - $item->returnedQuantity() - $replacedQuantity);

                    return $discountableQuantity * (float) $item->unit_price
                        * ((float) $item->discount_percentage / 100);
                });
                $replacementDiscounts = $transaction->replacements
                    ->where('status', 'approved')
                    ->sum(fn ($replacement) => (int) ($replacement->replacement_quantity ?: $replacement->quantity)
                        * (float) $replacement->replacement_unit_price
                        * ((float) ($replacement->replacement_discount_percentage ?? 0) / 100));

                return round($itemDiscounts + $replacementDiscounts, 2);
            });
        }
        if ($channel === 'walk_in') {
            $metrics['replacement_count'] = $allTransactions->sum(fn ($transaction) => $transaction->replacements->count());
            $metrics['return_count'] = $returnRecords->count();
        }
        if ($channel === 'tiktok') {
            $effectiveTikTokPayout = function (SalesTransaction $transaction): float {
                if ($this->tiktokOrderFullyReturned($transaction)) {
                    return 0.0;
                }

                return $transaction->tiktok_recalculated_payout_entered
                    && $transaction->tiktok_recalculated_payout !== null
                        ? (float) $transaction->tiktok_recalculated_payout
                        : (float) ($transaction->netTikTokPayout() ?? 0);
            };
            $latestItemTotal = static function ($item): float {
                $remainingQuantity = max(0, (int) $item->quantity - $item->returnedQuantity());
                $remainingTotal = $remainingQuantity * (float) $item->unit_price
                    * (1 - ((float) $item->discount_percentage / 100));
                $replacementTotal = $item->replacements
                    ->where('status', 'approved')
                    ->sum(fn ($replacement) => (float) $replacement->replacement_unit_price
                        * (1 - ((float) ($replacement->replacement_discount_percentage ?? 0) / 100))
                        * (int) ($replacement->replacement_quantity ?: $replacement->quantity));

                return round($remainingTotal + $replacementTotal, 2);
            };
            $metrics['gross_sales'] = $allTransactions->sum(fn ($transaction) => $transaction->items->sum(
                fn ($item) => (float) $item->unit_price * ((int) $item->quantity - $item->returnedQuantity())
            ));
            $metrics['total_sales'] = $allTransactions->sum(fn ($transaction) => $transaction->items->sum($latestItemTotal));
            $metrics['shipping_service_fees'] = $allTransactions->sum(fn ($transaction) => $transaction->items->sum(
                fn ($item) => round($latestItemTotal($item) * 0.05, 2)
            ));
            $metrics['replacement_count'] = $allTransactions->sum(fn ($transaction) => $transaction->replacements
                ->where('status', 'approved')->count());
            $metrics['refund_count'] = $allTransactions->sum(fn ($transaction) => $transaction->items
                ->sum(fn ($item) => $item->inventoryReturns->filter(fn ($return) => (float) $return->refund_amount > 0)->count()));
            $metrics['net_platform_payout'] = $allTransactions->sum($effectiveTikTokPayout);
        }
        $replacementProducts = $channel === 'tiktok'
            ? Product::where('store_hub_id', $hub->id)->where('status', 'active')->orderByCatalog()->get()
            : collect();

        $paymentBreakdown = $channel === 'walk_in'
            ? $this->walkInPaymentBreakdown($allTransactions, ! $hub->is_head_office)
            : $allTransactions
                ->groupBy(function ($transaction) {
                    $paymentMethod = strtoupper($transaction->mode_of_payment ?: 'UNSPECIFIED');
                    $bankName = strtoupper($transaction->bank_name ?: $transaction->custom_bank_name ?: '');

                    return $bankName ? $paymentMethod.' / '.$bankName : $paymentMethod;
                })
                ->map(fn ($group, $paymentMethod) => (object) [
                    'payment_method' => $paymentMethod,
                    'transaction_count' => $group->count(),
                    'total' => $group->sum($resolveTotal),
                ])
                ->sortKeys();

        $onlinePaymentSales = collect([
            'GCASH' => 0.0,
            'PAYMAYA' => 0.0,
            'BDO' => 0.0,
            'BPI' => 0.0,
            'DATED CHECK' => 0.0,
            'POST-DATED CHECK' => 0.0,
            'OTHERS' => 0.0,
        ]);
        $onlinePaymentCounts = $onlinePaymentSales->map(fn () => 0);
        $onlineBankSales = collect();
        $onlinePaymentDisplay = collect();
        $onlinePaymentDisplayCounts = collect();
        if ($channel === 'online') {
            $allTransactions->each(function ($transaction) use ($resolveTotal, $onlinePaymentSales, $onlinePaymentCounts, $onlineBankSales, $onlinePaymentDisplay, $onlinePaymentDisplayCounts) {
                $method = strtoupper(str_replace(['-', '_'], ' ', trim((string) ($transaction->mode_of_payment ?: 'OTHERS'))));
                $bank = strtoupper(trim((string) ($transaction->bank_name ?: $transaction->custom_bank_name ?: '')));
                $category = match (true) {
                    str_contains($method, 'POST DATED CHECK') => 'POST-DATED CHECK',
                    str_contains($method, 'DATED CHECK') => 'DATED CHECK',
                    str_contains($method, 'GCASH') => 'GCASH',
                    str_contains($method, 'PAYMAYA'), str_contains($method, 'MAYA') => 'PAYMAYA',
                    $method === 'BDO' => 'BDO',
                    $method === 'BPI' => 'BPI',
                    $method === 'OTHERS' => 'OTHERS',
                    str_contains($method, 'BANK') && $bank !== '' => str_replace('_', ' ', $bank),
                    default => $method,
                };
                $displayCategory = in_array($category, ['DATED CHECK', 'POST-DATED CHECK'], true) && $bank !== ''
                    ? $category.' / '.$bank
                    : $category;
                $onlinePaymentSales->put($category, (float) $onlinePaymentSales->get($category, 0) + $resolveTotal($transaction));
                $onlinePaymentCounts->put($category, (int) $onlinePaymentCounts->get($category, 0) + 1);
                $onlinePaymentDisplay->put($displayCategory, (float) $onlinePaymentDisplay->get($displayCategory, 0) + $resolveTotal($transaction));
                $onlinePaymentDisplayCounts->put($displayCategory, (int) $onlinePaymentDisplayCounts->get($displayCategory, 0) + 1);

                if ($bank !== '') {
                    $onlineBankSales->put($bank, (float) $onlineBankSales->get($bank, 0) + $resolveTotal($transaction));
                }
            });
        }

        $monthlySales = $allTransactions
            ->groupBy(fn ($transaction) => optional($transaction->order_date)->format('Y-m'))
            ->map(fn ($group, $month) => (object) [
                'month' => $month,
                'transaction_count' => $group->count(),
                'total' => $group->sum($resolveTotal),
            ])
            ->sortKeys();

        $deliveryStatuses = $allTransactions
            ->groupBy(fn ($transaction) => ucfirst(str_replace('_', ' ', strtolower($transaction->delivery_status ?: 'unspecified'))))
            ->map(fn ($group, $status) => (object) [
                'status' => $status,
                'transaction_count' => $group->count(),
                'total' => $group->sum($resolveTotal),
            ])
            ->sortKeys();

        $salesByChannel = SalesTransaction::where('store_hub_id', $hub->id)
            ->whereRaw("LOWER(REPLACE(REPLACE(channel_type, '-', '_'), ' ', '_')) IN ('online', 'wholesale', 'walk_in')")
            ->when($dateFrom, fn ($builder) => $builder->where('order_date', '>=', $dateFrom))
            ->when($dateTo, fn ($builder) => $builder->where('order_date', '<', Carbon::parse($dateTo)->addDay()->toDateString()))
            ->with('items')
            ->get()
            ->groupBy(fn ($transaction) => $this->normalizeChannel($transaction->channel_type))
            ->map(function ($group, $channelName) use ($resolveTotal) {
                return (object) [
                    'channel_type' => $channelName,
                    'transaction_count' => $group->count(),
                    'total' => $group->sum($resolveTotal),
                ];
            })
            ->values();

        return view('hubs.report', compact(
            'hub',
            'channel',
            'dateFrom',
            'dateTo',
            'search',
            'transactions',
            'allTransactions',
            'totalSales',
            'wholesaleCollectedSales',
            'totalTransactions',
            'totalPurchasedItems',
            'metrics',
            'paymentBreakdown',
            'onlinePaymentSales',
            'onlinePaymentCounts',
            'onlineBankSales',
            'onlinePaymentDisplay',
            'onlinePaymentDisplayCounts',
            'monthlySales',
            'deliveryStatuses',
            'salesByChannel',
            'transactionState',
            'stateCounts',
            'wholesaleReturns',
            'replacementProducts',
            'customerMetrics',
            'newCustomerSaleIds',
        ));
    }

    public function updateTikTokFields(Request $request, int $hub, int $transaction)
    {
        $user = auth()->user();
        $storeHub = StoreHub::findOrFail($hub);

        if (! $user->canAccessHub($hub)) {
            abort(403, 'Unauthorized access to this store hub report.');
        }

        if (! $storeHub->is_head_office && ! in_array($user->role, ['admin', 'inventory_staff'], true)
            && (int) $user->store_hub_id !== (int) $storeHub->id) {
            abort(403, 'Sales staff can only update their designated store branch report.');
        }

        if ($user->role !== 'admin'
            && (! in_array($user->role, ['sales_associate', 'sales_marketing_staff'], true)
                || ! $user->hasSalesChannel('tiktok'))) {
            abort(403, 'You are not assigned to manage TikTok sales reports.');
        }

        $validated = $request->validate([
            'refund_shipping_fee' => ['nullable', 'numeric', 'min:0'],
            'sales_after_transaction_fee' => ['nullable', 'numeric'],
            'payout_includes_refunds' => ['sometimes', 'boolean'],
            'drop_off_date' => ['nullable', 'date'],
            'courier' => ['nullable', 'string', 'max:255'],
            'note' => ['nullable', 'string', 'max:2000'],
        ]);

        $salesTransaction = SalesTransaction::whereKey($transaction)
            ->where('store_hub_id', $storeHub->id)
            ->whereRaw('LOWER(channel_type) = ?', ['tiktok'])
            ->firstOrFail();

        if ($this->tiktokOrderFullyReturned($salesTransaction)) {
            throw ValidationException::withMessages([
                'sales_after_transaction_fee' => 'Payout and delivery details are locked because every item in this TikTok order has been returned.',
            ]);
        }

        $hasReturn = $salesTransaction->items()
            ->where(function ($query) {
                $query->where('return_status', '!=', 'none')
                    ->orWhereHas('inventoryReturns');
            })
            ->exists();

        $salesTransaction->update([
            'refund_shipping_fee' => array_key_exists('refund_shipping_fee', $validated)
                ? $validated['refund_shipping_fee']
                : $salesTransaction->refund_shipping_fee,
            'sales_after_transaction_fee' => $hasReturn
                ? $salesTransaction->sales_after_transaction_fee
                : ($validated['sales_after_transaction_fee'] ?? null),
            'tiktok_recalculated_payout' => $hasReturn
                ? ($validated['sales_after_transaction_fee'] ?? null)
                : $salesTransaction->tiktok_recalculated_payout,
            'payout_includes_refunds' => $validated['payout_includes_refunds'] ?? $salesTransaction->payout_includes_refunds,
            'drop_off_date' => $validated['drop_off_date'] ?? null,
            'courier' => $validated['courier'] ?? null,
            'note' => $validated['note'] ?? null,
            'tiktok_recalculated_payout_entered' => $hasReturn
                && array_key_exists('sales_after_transaction_fee', $validated)
                && $validated['sales_after_transaction_fee'] !== null,
        ]);

        return back()->with('success', 'TikTok report details updated.');
    }

    public function updateTikTokReturn(Request $request, int $hub, int $transaction, int $item)
    {
        $user = auth()->user();
        $storeHub = StoreHub::findOrFail($hub);

        if (! $user->canAccessHub($hub)) {
            abort(403, 'Unauthorized access to this store hub report.');
        }

        if (! $storeHub->is_head_office && ! in_array($user->role, ['admin', 'inventory_staff'], true)
            && (int) $user->store_hub_id !== (int) $storeHub->id) {
            abort(403, 'Sales staff can only update their designated store branch report.');
        }

        if ($user->role !== 'admin'
            && (! in_array($user->role, ['inventory_staff', 'sales_associate', 'sales_marketing_staff'], true)
                || ($user->role !== 'inventory_staff' && ! $user->hasSalesChannel('tiktok')))) {
            abort(403, 'You are not assigned to manage TikTok returns.');
        }

        $validated = $request->validate([
            'return_status' => ['required', 'in:none,requested,received,rejected,refund_only'],
            'return_condition' => ['nullable', 'required_if:return_status,received', 'in:good,damaged'],
            'refund_status' => ['required', 'in:none,pending,completed,rejected'],
            'returned_quantity' => ['required', 'integer', 'min:0'],
            'customer_refund_amount' => ['nullable', 'numeric', 'min:0'],
            'return_reason' => ['nullable', 'string', 'max:2000'],
        ]);

        $replacement = DB::transaction(function () use ($validated, $storeHub, $transaction, $item) {
            $salesTransaction = SalesTransaction::whereKey($transaction)
                ->where('store_hub_id', $storeHub->id)
                ->whereRaw('LOWER(channel_type) = ?', ['tiktok'])
                ->firstOrFail();
            $transactionItem = $salesTransaction->items()->whereKey($item)->lockForUpdate()->firstOrFail();

            $quantity = (int) $validated['returned_quantity'];
            $status = $validated['return_status'];
            $condition = $status === 'received' ? $validated['return_condition'] : null;
            $refund = (float) ($validated['customer_refund_amount'] ?? 0);
            $discountedUnitPrice = round(
                (float) $transactionItem->unit_price
                * (1 - ((float) ($transactionItem->discount_percentage ?? 0) / 100)),
                2
            );
            $refundCap = round($discountedUnitPrice * $quantity, 2);
            $fail = fn ($field, $message) => throw ValidationException::withMessages([$field => $message]);
            if ($quantity > (int) $transactionItem->quantity) {
                $fail('returned_quantity', 'Returned quantity cannot exceed the sold quantity.');
            }
            if (in_array($status, ['requested', 'received']) && $quantity < 1) {
                $fail('returned_quantity', 'Enter the quantity being returned.');
            }
            if (in_array($status, ['none', 'refund_only']) && $quantity !== 0) {
                $fail('returned_quantity', 'Use zero quantity when no item is being returned.');
            }
            if ($status === 'received' && $quantity > 0 && $refund > $refundCap) {
                $fail('customer_refund_amount', 'The item refund cannot exceed the item total after discount.');
            }
            if ($status === 'received' && $quantity > 0) {
                $refund = $refundCap;
            } elseif ($refund > (float) $transactionItem->line_total) {
                $fail('customer_refund_amount', 'The item refund cannot exceed the item total after discount.');
            }
            if (in_array($validated['refund_status'], ['pending', 'completed']) && ($refund <= 0 || in_array($status, ['none', 'rejected']))) {
                $fail('refund_status', 'Select a return or refund-only request and enter a positive refund amount.');
            }
            if (in_array($validated['refund_status'], ['none', 'rejected']) && $refund != 0) {
                $fail('customer_refund_amount', 'Use zero refund amount for no refund or a rejected refund.');
            }
            if ($transactionItem->return_status === 'received' && ($status !== 'received'
                || $quantity !== (int) $transactionItem->returned_quantity || $condition !== $transactionItem->return_condition)) {
                $fail('returned_quantity', 'Received quantity and condition are locked because the inventory movement is already recorded. You can still update the refund.');
            }

            $wasReceived = $transactionItem->return_status === 'received';
            $willBeReceived = $validated['return_status'] === 'received';
            if (! $wasReceived && $willBeReceived && $quantity > 0) {
                $product = $transactionItem->product()->lockForUpdate()->firstOrFail();
                $product->increment('stock', $quantity);
                if ($condition === 'good') {
                    ProductStockAllocation::firstOrCreate(['product_id' => $product->id])
                        ->increment('tiktok', $quantity);
                }

                InventoryTransaction::create([
                    'type' => 'return',
                    'reference' => $salesTransaction->order_number,
                    'store_hub_id' => $storeHub->id,
                    'product_id' => $product->id,
                    'sales_transaction_id' => $salesTransaction->id,
                    'transaction_item_id' => $transactionItem->id,
                    'channel' => 'tiktok',
                    'source' => 'TikTok customer return',
                    'condition' => $condition,
                    'quantity' => $quantity,
                    'occurred_on' => now()->toDateString(),
                    'notes' => $validated['return_reason'] ?? 'TikTok customer return received.',
                    'created_by' => auth()->id(),
                ]);
            }

            $transactionItem->update([
                'returned_quantity' => $quantity,
                'return_status' => $validated['return_status'],
                'return_condition' => $condition,
                'refund_status' => $validated['refund_status'],
                'customer_refund_amount' => $refund,
                'returned_at' => $willBeReceived ? ($transactionItem->returned_at ?? now()) : null,
                'return_reason' => $validated['return_reason'] ?? null,
            ]);
            $transactionItem->transaction()->update([
                'tiktok_recalculated_payout_entered' => false,
            ]);
        });

        return back()->with('success', 'TikTok return details updated.');
    }

    public function wholesaleReport(int $hub)
    {
        StoreHub::findOrFail($hub);

        return redirect()->route('hub.report', ['hub' => $hub, 'channel' => 'wholesale']);
    }

    public function replacementAttachment(int $hub, ProductReplacement $replacement, string $type, int $index)
    {
        $storeHub = StoreHub::findOrFail($hub);
        abort_unless(auth()->user()?->canAccessHub($storeHub->id), 403);

        $sale = $replacement->transaction;
        abort_unless($sale && (int) $sale->store_hub_id === (int) $storeHub->id, 404);

        $path = match ($type) {
            'payment-proof' => $replacement->exchange_payment_proofs[$index] ?? null,
            'replacement-slip' => $index === 0 ? $replacement->replacement_order_slip : null,
            default => null,
        };
        abort_unless(is_string($path) && $path !== '', 404);

        $disk = Storage::disk('public');
        abort_unless($disk->exists($path), 404, 'Replacement attachment is no longer available.');

        return $disk->response(
            $path,
            basename($path),
            ['Content-Type' => $disk->mimeType($path) ?: 'application/octet-stream'],
            'inline'
        );
    }

    public function replaceWholesaleItem(Request $request, int $hub, int $transaction, int $item)
    {
        $storeHub = StoreHub::findOrFail($hub);
        abort_unless(auth()->user()?->canAccessHub($storeHub->id), 403);

        $allowedPaymentMethods = $storeHub->is_head_office
            ? ['CASH', 'GCASH', 'PAYMAYA', 'QRPH', 'BPI', 'BDO', 'METROBANK', 'BANK_TRANSFER', 'DATED_CHECK', 'POST_DATED_CHECK', 'COD', 'OTHERS']
            : ['CASH', 'GCASH', 'PAYMAYA', 'QRPH', 'BPI', 'BDO', 'METROBANK', 'BANK_TRANSFER', 'OTHERS'];

        $validated = $request->validate([
            'replacement_product_id' => ['required', 'integer', 'exists:products,id'],
            'quantity' => ['required', 'integer', 'min:1'],
            'replacement_quantity' => ['required', 'integer', 'min:1'],
            'replacement_discount_percentage' => ['nullable', 'numeric', 'between:0,100'],
            'replacement_shipping_fee_type' => ['nullable', 'in:Free,Custom Amount'],
            'replacement_shipping_fee_amount' => ['nullable', 'required_if:replacement_shipping_fee_type,Custom Amount', 'numeric', 'min:0', 'max:99999999.99'],
            'additional_items' => ['nullable', 'array', 'max:9'],
            'additional_items.*.product_id' => ['required', 'integer', 'exists:products,id'],
            'additional_items.*.quantity' => ['required', 'integer', 'min:1'],
            'additional_items.*.discount_percentage' => ['nullable', 'numeric', 'between:0,100'],
            'exchange_payment_amount' => ['nullable', 'numeric', 'min:0'],
            'exchange_payment_method' => ['nullable', 'string', 'max:100', 'in:'.implode(',', $allowedPaymentMethods)],
            'exchange_custom_mop' => ['required_if:exchange_payment_method,OTHERS', 'nullable', 'string', 'max:100'],
            'exchange_bank_name' => ['required_if:exchange_payment_method,BANK_TRANSFER,DATED_CHECK,POST_DATED_CHECK', 'nullable', 'string', 'max:100'],
            'exchange_custom_bank_name' => ['required_if:exchange_bank_name,OTHERS', 'nullable', 'string', 'max:100'],
            'exchange_check_number' => ['required_if:exchange_payment_method,DATED_CHECK,POST_DATED_CHECK', 'nullable', 'string', 'max:255'],
            'exchange_check_date' => ['required_if:exchange_payment_method,DATED_CHECK,POST_DATED_CHECK', 'nullable', 'date'],
            'exchange_payment_reference' => ['nullable', 'string', 'max:255'],
            'exchange_payment_proofs' => ['nullable', 'array', 'max:4'],
            'exchange_payment_proofs.*' => ['file', 'mimes:jpeg,png,jpg,webp,pdf', 'max:2048'],
            'replacement_order_slip' => ['nullable', 'file', 'mimes:jpeg,png,jpg,webp,pdf', 'max:2048'],
            'reason' => ['nullable', 'string', 'max:2000'],
        ]);

        $replacement = DB::transaction(function () use ($validated, $storeHub, $transaction, $item, $request) {
            $sale = SalesTransaction::whereKey($transaction)
                ->where('store_hub_id', $storeHub->id)
                ->lockForUpdate()
                ->firstOrFail();

            $channel = $this->normalizeChannel($sale->channel_type);
            if (! in_array($channel, ['wholesale', 'tiktok', 'online', 'walk_in'], true)) {
                abort(404);
            }
            if (auth()->user()?->role === 'sales_associate'
                && ($channel !== 'walk_in' || $storeHub->is_head_office)) {
                abort(403);
            }
            if (! in_array($sale->status, ['confirmed', 'completed'], true)) {
                throw ValidationException::withMessages([
                    'quantity' => 'Only verified sales transactions can have product replacements.',
                ]);
            }

            $transactionItem = $sale->items()->whereKey($item)->lockForUpdate()->firstOrFail();
            $requestedLines = collect([[
                'product_id' => (int) $validated['replacement_product_id'],
                'quantity' => (int) $validated['replacement_quantity'],
                'discount_percentage' => (float) ($validated['replacement_discount_percentage'] ?? 0),
            ]])->concat(collect($validated['additional_items'] ?? [])->map(fn ($line) => [
                'product_id' => (int) $line['product_id'],
                'quantity' => (int) $line['quantity'],
                'discount_percentage' => (float) ($line['discount_percentage'] ?? 0),
            ]))->values();
            if ($requestedLines->contains(fn ($line) => $line['product_id'] === (int) $transactionItem->product_id)) {
                throw ValidationException::withMessages([
                    'replacement_product_id' => 'Choose different products from the returned item.',
                ]);
            }

            $alreadyReplaced = (int) ProductReplacement::where('transaction_item_id', $transactionItem->id)
                ->whereIn('status', ['pending', 'approved'])
                ->sum('quantity');
            $quantity = (int) $validated['quantity'];
            if ($quantity > ((int) $transactionItem->quantity - $alreadyReplaced)) {
                throw ValidationException::withMessages([
                    'quantity' => 'Replacement quantity exceeds the remaining quantity from the original sale.',
                ]);
            }
            $requiresReceivedReturn = in_array($channel, ['online', 'wholesale'], true)
                || ($channel === 'walk_in' && $storeHub->is_head_office);
            if ($requiresReceivedReturn && $quantity > max(0, $transactionItem->returnedQuantity() - $alreadyReplaced)) {
                throw ValidationException::withMessages([
                    'quantity' => $transactionItem->returnedQuantity() < 1
                        ? 'Inventory must record the original item as returned before a replacement can be requested.'
                        : 'Replacement quantity exceeds the quantity of returned items that has not already been replaced.',
                ]);
            }

            $originalProduct = Product::whereKey($transactionItem->product_id)->where('store_hub_id', $storeHub->id)->first();
            $replacementProducts = Product::whereIn('id', $requestedLines->pluck('product_id')->unique())
                ->where('store_hub_id', $storeHub->id)->get()->keyBy('id');
            if (! $originalProduct || $replacementProducts->count() !== $requestedLines->pluck('product_id')->unique()->count()) {
                throw ValidationException::withMessages([
                    'replacement_product_id' => 'Every replacement product must belong to this store hub.',
                ]);
            }
            if ($replacementProducts->contains(fn ($product) => $product->status !== 'active')) {
                throw ValidationException::withMessages([
                    'replacement_product_id' => 'Every replacement product must be active.',
                ]);
            }
            $originalUnitPrice = round((float) $transactionItem->unit_price * (1 - ((float) ($transactionItem->discount_percentage ?? 0) / 100)), 2);
            $isPartialWholesale = $channel === 'wholesale' && $sale->payment_status === 'partial';
            $pricedLines = $requestedLines->map(function ($line) use ($replacementProducts, $channel) {
                $product = $replacementProducts->get($line['product_id']);
                $unitPrice = (float) ($channel === 'wholesale'
                    ? ($product->wholesale_price ?? $product->sales_price ?? 0)
                    : ($product->sales_price ?? 0));
                $line['unit_price'] = $unitPrice;
                $line['total'] = round($unitPrice * (1 - ($line['discount_percentage'] / 100)) * $line['quantity'], 2);
                return $line;
            });
            $supportsReplacementShipping = in_array($channel, ['online', 'wholesale'], true)
                || ($channel === 'walk_in' && $storeHub->is_head_office);
            $replacementShippingFeeType = $supportsReplacementShipping
                ? (string) ($validated['replacement_shipping_fee_type'] ?? 'Free')
                : null;
            $replacementShippingFee = $supportsReplacementShipping && $replacementShippingFeeType === 'Custom Amount'
                ? round((float) ($validated['replacement_shipping_fee_amount'] ?? 0), 2)
                : 0.0;
            if ($supportsReplacementShipping && $replacementShippingFeeType === 'Custom Amount'
                && ! array_key_exists('replacement_shipping_fee_amount', $validated)) {
                throw ValidationException::withMessages([
                    'replacement_shipping_fee_amount' => 'Enter the replacement delivery fee or choose Free delivery.',
                ]);
            }
            $replacementProductsTotal = round((float) $pricedLines->sum('total'), 2);
            $exchangeTotal = round($replacementProductsTotal + $replacementShippingFee, 2);
            $returnedItemValue = round($originalUnitPrice * $quantity, 2);
            $newSubTotal = max(0, (float) $sale->sub_total - $returnedItemValue + $replacementProductsTotal);
            $newShippingFee = round((float) $sale->shipping_fee_amount + $replacementShippingFee, 2);
            $discount = $newSubTotal * ((float) $sale->additional_discount_percentage / 100);
            $withholding = $newSubTotal * ((float) $sale->withholding_tax / 100);
            $revisedOrderTotal = max(0, round($newSubTotal - $discount - $withholding + $newShippingFee, 2));
            $existingPaid = max(0, round((float) $sale->amount_paid, 2));
            $credit = $isPartialWholesale
                ? max(0, round($exchangeTotal - max(0, $revisedOrderTotal - $existingPaid), 2))
                : $returnedItemValue;
            $usesExchangeCredit = $credit > 0;
            $allowsLowerValueExchange = $channel === 'online'
                || ($channel === 'walk_in' && $storeHub->is_head_office)
                || $isPartialWholesale;
            if (! $allowsLowerValueExchange && $exchangeTotal + 0.0001 < $credit) {
                throw ValidationException::withMessages([
                    'replacement_product_id' => 'The replacement basket must equal or exceed the exchange credit of ₱'.number_format($credit, 2).'. Add another product or increase a quantity.',
                ]);
            }

            // A partial Wholesale payment is applied to the revised order. It is never
            // charged again, and a Free replacement delivery option contributes zero.
            $additionalPaymentDue = $isPartialWholesale
                ? max(0, round($revisedOrderTotal - $existingPaid, 2))
                : max(0, round($exchangeTotal - $credit, 2));
            $paymentAmount = round((float) ($validated['exchange_payment_amount'] ?? 0), 2);
            $paymentMethod = strtoupper(trim((string) ($validated['exchange_payment_method'] ?? '')));
            if (abs($paymentAmount - $additionalPaymentDue) > 0.0001) {
                throw ValidationException::withMessages([
                    'exchange_payment_amount' => 'Enter the exact additional payment of ₱'.number_format($additionalPaymentDue, 2).'.',
                ]);
            }
            if ($additionalPaymentDue > 0 && $paymentMethod === '') {
                throw ValidationException::withMessages(['exchange_payment_method' => 'Select the mode of payment for the additional amount.']);
            }
            if ($additionalPaymentDue > 0 && $paymentMethod !== 'CASH' && ! $request->hasFile('exchange_payment_proofs')) {
                throw ValidationException::withMessages(['exchange_payment_proofs' => 'Upload proof for a non-cash additional payment.']);
            }
            $paymentProofs = array_map(
                fn ($file) => $file->store('exchange_payment_proofs', 'public'),
                $request->file('exchange_payment_proofs', [])
            );
            $replacementOrderSlip = $request->file('replacement_order_slip')?->store('replacement_order_slips', 'public');

            $exchangeReference = (string) Str::uuid();
            $created = $pricedLines->map(function ($line, $index) use ($sale, $transactionItem, $originalProduct, $quantity, $originalUnitPrice, $usesExchangeCredit, $credit, $exchangeTotal, $additionalPaymentDue, $replacementShippingFeeType, $replacementShippingFee, $paymentAmount, $paymentMethod, $paymentProofs, $replacementOrderSlip, $exchangeReference, $validated) {
                return ProductReplacement::create([
                    'exchange_reference' => $exchangeReference,
                    'transaction_id' => $sale->id,
                    'transaction_item_id' => $transactionItem->id,
                    'original_product_id' => $originalProduct->id,
                    'replacement_product_id' => $line['product_id'],
                    'quantity' => $index === 0 ? $quantity : 0,
                    'replacement_quantity' => $line['quantity'],
                    'original_unit_price' => $originalUnitPrice,
                    'replacement_unit_price' => $line['unit_price'],
                    'replacement_discount_percentage' => $line['discount_percentage'],
                    'exchange_credit' => $credit,
                    'uses_exchange_credit' => $usesExchangeCredit,
                    'exchange_total' => $exchangeTotal,
                    'additional_payment_due' => $additionalPaymentDue,
                    'replacement_shipping_fee_type' => $index === 0 ? $replacementShippingFeeType : null,
                    'replacement_shipping_fee_amount' => $index === 0 ? $replacementShippingFee : 0,
                    'exchange_payment_amount' => $index === 0 ? $paymentAmount : 0,
                    'exchange_payment_method' => $index === 0 ? ($paymentMethod ?: null) : null,
                    'exchange_custom_mop' => $index === 0 ? ($validated['exchange_custom_mop'] ?? null) : null,
                    'exchange_bank_name' => $index === 0 ? ($validated['exchange_bank_name'] ?? null) : null,
                    'exchange_custom_bank_name' => $index === 0 ? ($validated['exchange_custom_bank_name'] ?? null) : null,
                    'exchange_check_number' => $index === 0 ? ($validated['exchange_check_number'] ?? null) : null,
                    'exchange_check_date' => $index === 0 ? ($validated['exchange_check_date'] ?? null) : null,
                    'exchange_payment_reference' => $index === 0 ? ($validated['exchange_payment_reference'] ?? null) : null,
                    'exchange_payment_proofs' => $index === 0 ? $paymentProofs : null,
                    'replacement_order_slip' => $index === 0 ? $replacementOrderSlip : null,
                    'reason' => $validated['reason'] ?? null,
                    'status' => 'pending',
                    'created_by' => auth()->id(),
                ]);
            });

            return $created->first();
        });

        $channel = $this->normalizeChannel($replacement->transaction?->channel_type);
        $channelLabel = $channel === 'walk_in' ? 'Walk-In' : ucfirst($channel);

        User::whereIn('role', ['admin', 'inventory_staff'])
            ->where('id', '<>', auth()->id())
            ->get()
            ->each(fn (User $recipient) => $recipient->notify(
                new InventoryWorkflowNotification(
                    'replacement_request',
                    sprintf(
                        '%s submitted a replacement request for order %s. Inventory verification is required.',
                        $replacement->creator?->name ?? auth()->user()->name,
                        $replacement->transaction?->order_number ?? $transaction
                    ),
                    (int) $storeHub->id,
                    route('hub.sales.pending', ['hubId' => $storeHub->id]),
                    $channelLabel.' replacement verification needed',
                    $channel
                )
            ));

        return back()->with('success', 'Replacement request submitted for inventory verification. No stock or order total has changed yet.');
    }

    public function recordWalkInReplacementPayment(Request $request, int $hub, int $transaction)
    {
        $storeHub = StoreHub::findOrFail($hub);
        abort_unless(auth()->user()?->canAccessHub($storeHub->id), 403);

        $validated = $request->validate([
            'amount' => ['required', 'numeric', 'gt:0'],
            'mode_of_payment' => ['required', 'string', 'max:100'],
            'custom_mop' => ['required_if:mode_of_payment,OTHERS', 'nullable', 'string', 'max:100'],
            'bank_name' => ['nullable', 'string', 'max:100'],
            'custom_bank_name' => ['nullable', 'string', 'max:100'],
            'check_number' => ['nullable', 'string', 'max:255'],
            'check_date' => ['nullable', 'date'],
            'walkin_payment_proofs' => ['required_unless:mode_of_payment,CASH', 'nullable', 'array', 'min:1', 'max:4'],
            'walkin_payment_proofs.*' => ['file', 'mimes:jpeg,png,jpg,webp,pdf', 'max:2048'],
        ]);

        $proofs = array_map(fn ($file) => $file->store('proofs_of_payment', 'public'), $request->file('walkin_payment_proofs', []));
        DB::transaction(function () use ($validated, $proofs, $storeHub, $transaction) {
            $sale = SalesTransaction::whereKey($transaction)
                ->where('store_hub_id', $storeHub->id)
                ->lockForUpdate()
                ->firstOrFail();
            if ($this->normalizeChannel($sale->channel_type) !== 'walk_in') {
                abort(404);
            }

            $total = (float) ($sale->grand_total ?: $sale->total_amount ?: $sale->sub_total);
            $paid = (float) ($sale->amount_paid ?? 0);
            $amount = round((float) $validated['amount'], 2);
            if ($amount > max(0, round($total - $paid, 2))) {
                throw ValidationException::withMessages(['amount' => 'The payment cannot be greater than the remaining balance.']);
            }

            $newPaid = round($paid + $amount, 2);
            SalesPaymentRecord::create([
                'sales_transaction_id' => $sale->id,
                'amount' => $amount,
                'mode_of_payment' => $validated['mode_of_payment'],
                'custom_mop' => $validated['custom_mop'] ?? null,
                'bank_name' => $validated['bank_name'] ?? null,
                'custom_bank_name' => $validated['custom_bank_name'] ?? null,
                'check_number' => $validated['check_number'] ?? null,
                'check_date' => $validated['check_date'] ?? null,
                'walkin_payment_proofs' => $proofs,
                'recorded_by' => auth()->id(),
            ]);
            $sale->update([
                'amount_paid' => $newPaid,
                'payment_status' => $newPaid + 0.0001 >= $total ? 'paid' : 'partial',
            ]);
        });

        return back()->with('success', 'Walk-In replacement payment recorded.');
    }

    public function approveWholesaleReplacement(int $replacement)
    {
        $record = DB::transaction(function () use ($replacement) {
            $record = ProductReplacement::whereKey($replacement)->lockForUpdate()->firstOrFail();
            if ($record->status !== 'pending') {
                throw ValidationException::withMessages(['replacement' => 'This replacement request has already been reviewed.']);
            }
            $records = $record->exchange_reference
                ? ProductReplacement::where('exchange_reference', $record->exchange_reference)->lockForUpdate()->get()
                : collect([$record]);
            if ($records->contains(fn ($line) => $line->status !== 'pending')) {
                throw ValidationException::withMessages(['replacement' => 'This exchange request has already been reviewed.']);
            }
            $sale = SalesTransaction::whereKey($record->transaction_id)->lockForUpdate()->firstOrFail();
            abort_unless(auth()->user()?->canAccessHub((int) $sale->store_hub_id), 403);

            $productIds = $records->pluck('replacement_product_id')->push($record->original_product_id)->unique()->sort()->values();
            $products = Product::whereIn('id', $productIds)->where('store_hub_id', $sale->store_hub_id)
                ->lockForUpdate()->get()->keyBy('id');
            $original = $products->get($record->original_product_id);
            if (! $original || $records->contains(fn ($line) => ! $products->has($line->replacement_product_id))) {
                throw ValidationException::withMessages(['replacement' => 'A product in this request is no longer available in this hub.']);
            }
            $channel = $this->normalizeChannel($sale->channel_type);
            $requestedByProduct = $records->groupBy('replacement_product_id')->map(
                fn ($lines) => $lines->sum(fn ($line) => (int) ($line->replacement_quantity ?: $line->quantity))
            );
            foreach ($requestedByProduct as $productId => $requestedQuantity) {
                $replacementProduct = $products->get((int) $productId);
                $channelAvailable = match ($channel) {
                    'walk_in' => $replacementProduct->unallocatedStock(),
                    'online' => $replacementProduct->channelAvailableStock($channel, false),
                    default => $replacementProduct->channelAvailableStock($channel),
                };
                if ((int) $replacementProduct->stock < $requestedQuantity || $channelAvailable < $requestedQuantity) {
                    throw ValidationException::withMessages([
                        'replacement' => "Not enough {$channel} stock for {$replacementProduct->name}. Available: {$channelAvailable}; requested: {$requestedQuantity}.",
                    ]);
                }
            }

            $returnedQuantity = (int) $records->sum('quantity');
            $transactionItem = $record->transaction_item_id
                ? $sale->items()->whereKey($record->transaction_item_id)->lockForUpdate()->first()
                : null;
            $previouslyApprovedQuantity = $transactionItem
                ? (int) ProductReplacement::where('transaction_item_id', $transactionItem->id)
                    ->where('status', 'approved')
                    ->sum('quantity')
                : 0;
            $receivedReturnQuantity = $transactionItem?->returnedQuantity() ?? 0;
            if (! $transactionItem || $receivedReturnQuantity < $previouslyApprovedQuantity + $returnedQuantity) {
                throw ValidationException::withMessages([
                    'replacement' => 'Inventory must record the original item as received before approving or releasing its replacement.',
                ]);
            }

            $previouslyReturnedQuantity = $record->transaction_item_id
                ? (int) InventoryTransaction::query()
                    ->where('transaction_item_id', $record->transaction_item_id)
                    ->where('type', 'return')
                    ->whereNull('product_replacement_id')
                    ->sum('quantity')
                : 0;
            $additionalReturnQuantity = max(0, $returnedQuantity - $previouslyReturnedQuantity);
            if ($additionalReturnQuantity > 0) {
                $original->increment('stock', $additionalReturnQuantity);
                if ($channel !== 'walk_in') {
                    ProductStockAllocation::where('product_id', $original->id)
                        ->lockForUpdate()
                        ->first()?->increment($channel, $additionalReturnQuantity);
                }
            }
            foreach ($requestedByProduct as $productId => $requestedQuantity) {
                $replacementProduct = $products->get((int) $productId);
                $replacementProduct->decrement('stock', $requestedQuantity);
                if ($channel !== 'walk_in') {
                    ProductStockAllocation::where('product_id', $replacementProduct->id)->lockForUpdate()->first()?->decrement($channel, $requestedQuantity);
                }
            }
            $returnedItemValue = round((float) $record->original_unit_price * $returnedQuantity, 2);
            $charge = round((float) $records->sum(fn ($line) => (float) $line->replacement_unit_price
                * (1 - ((float) ($line->replacement_discount_percentage ?? 0) / 100))
                * (int) ($line->replacement_quantity ?: $line->quantity)), 2);
            $supportsReplacementShipping = in_array($channel, ['online', 'wholesale'], true)
                || ($channel === 'walk_in' && (bool) $sale->storeHub()->value('is_head_office'));
            $replacementShippingFee = $supportsReplacementShipping
                ? (float) ($records->firstWhere('replacement_shipping_fee_amount', '>', 0)?->replacement_shipping_fee_amount ?? 0)
                : 0.0;
            $originalOrderTotal = round(
                (float) ($sale->grand_total ?: $sale->total_amount ?: $sale->sub_total),
                2
            );
            $newSubTotal = max(0, (float) $sale->sub_total - $returnedItemValue + $charge);
            $newShippingFee = round((float) $sale->shipping_fee_amount + $replacementShippingFee, 2);
            $discount = $newSubTotal * ((float) $sale->additional_discount_percentage / 100);
            $withholding = $newSubTotal * ((float) $sale->withholding_tax / 100);
            $calculatedGrandTotal = max(0, round($newSubTotal - $discount - $withholding + $newShippingFee, 2));
            // A partial Wholesale order must use its revised total so its existing
            // payment is credited once, rather than being collected a second time.
            $newGrandTotal = $channel === 'wholesale' && $sale->payment_status === 'partial'
                ? $calculatedGrandTotal
                : max($originalOrderTotal, $calculatedGrandTotal);
            $amountPaid = (float) $sale->amount_paid;
            $exchangePayment = round((float) ($record->exchange_payment_amount ?? 0), 2);
            if ($exchangePayment > 0) {
                SalesPaymentRecord::create([
                    'sales_transaction_id' => $sale->id,
                    'amount' => $exchangePayment,
                    'mode_of_payment' => $record->exchange_payment_method,
                    'custom_mop' => $record->exchange_custom_mop ?: $record->exchange_payment_reference,
                    'bank_name' => $record->exchange_bank_name,
                    'custom_bank_name' => $record->exchange_custom_bank_name,
                    'check_number' => $record->exchange_check_number,
                    'check_date' => $record->exchange_check_date,
                    'walkin_payment_proofs' => $record->exchange_payment_proofs,
                    'status' => 'verified',
                    'recorded_by' => auth()->id(),
                ]);
                $amountPaid = round($amountPaid + $exchangePayment, 2);
            }

            $paymentStatus = in_array($channel, ['online', 'tiktok'], true)
                ? $sale->payment_status
                : ($amountPaid <= 0 ? 'unpaid' : ($amountPaid + 0.0001 >= $newGrandTotal ? 'paid' : 'partial'));
            $sale->update([
                'sub_total' => $newSubTotal,
                'total_amount' => $newGrandTotal,
                'grand_total' => $newGrandTotal,
                'shipping_fee_amount' => $newShippingFee,
                'delivery_fee' => $newShippingFee,
                'proof_amount' => $channel === 'online' && $exchangePayment > 0 && $sale->proof_amount !== null
                    ? round((float) $sale->proof_amount + $exchangePayment, 2)
                    : $sale->proof_amount,
                'amount_paid' => $amountPaid,
                'withholding_tax_amount' => round($withholding, 2),
                'payment_status' => $paymentStatus,
                'sales_after_transaction_fee' => $channel === 'tiktok' ? null : $sale->sales_after_transaction_fee,
            ]);
            $records->each->update([
                'price_adjustment' => max(0, round($newGrandTotal - $originalOrderTotal, 2)),
                'status' => 'approved', 'reviewed_by' => auth()->id(), 'reviewed_at' => now(),
            ]);

            $movements = collect();
            if ($additionalReturnQuantity > 0) {
                $movements->push([$original, 'replacement_return', $additionalReturnQuantity, 'Wholesale replacement: original item returned', $record]);
            }
            foreach ($records as $line) {
                $movements->push([
                    $products->get($line->replacement_product_id), 'replacement_out',
                    (int) ($line->replacement_quantity ?: $line->quantity),
                    'Wholesale replacement: new item released', $line,
                ]);
            }
            foreach ($movements as [$product, $type, $quantity, $source, $movementRecord]) {
                InventoryTransaction::create([
                    'type' => $type, 'reference' => $sale->order_number, 'store_hub_id' => $sale->store_hub_id,
                    'product_id' => $product->id, 'channel' => $channel, 'source' => str_replace('Wholesale', ucfirst($channel), $source),
                    'product_replacement_id' => $movementRecord->id,
                    'condition' => $type === 'replacement_return' ? 'good' : null, 'quantity' => $quantity,
                    'occurred_on' => now()->toDateString(), 'notes' => $record->reason ?: "Replacement for {$channel} order {$sale->order_number}.",
                    'created_by' => auth()->id(),
                ]);
            }

            return $record->fresh(['transaction', 'creator']);
        });

        $channel = $this->normalizeChannel($record->transaction?->channel_type);
        if ($record->creator && (int) $record->creator->id !== (int) auth()->id()) {
            $record->creator->notify(new InventoryWorkflowNotification(
                'replacement_approved',
                sprintf(
                    'Your %s replacement request for order %s was approved by %s.',
                    ucfirst($channel),
                    $record->transaction?->order_number ?? $record->transaction_id,
                    auth()->user()?->name ?? 'Inventory staff'
                ),
                (int) $record->transaction?->store_hub_id,
                route('hub.report', [
                    'hub' => $record->transaction?->store_hub_id,
                    'channel' => $channel,
                ]),
                ($channel === 'walk_in' ? 'Walk-In' : ucfirst($channel)).' replacement approved',
                $channel
            ));
        }

        $message = 'Replacement verified. Inventory and the '.ucfirst(str_replace('_', ' ', $channel)).' order total were updated.';
        if ($channel === 'tiktok') {
            $message .= ' Enter the new sales-after-transactions total from TikTok.';
        }

        return back()->with('success', $message);
    }

    public function rejectWholesaleReplacement(Request $request, int $replacement)
    {
        $validated = $request->validate(['rejection_reason' => ['required', 'string', 'max:2000']]);
        $record = ProductReplacement::with(['transaction', 'creator'])->findOrFail($replacement);
        abort_unless(auth()->user()?->canAccessHub((int) $record->transaction->store_hub_id), 403);
        if ($record->status !== 'pending') {
            return back()->withErrors(['replacement' => 'This replacement request has already been reviewed.']);
        }
        $rejectQuery = $record->exchange_reference
            ? ProductReplacement::where('exchange_reference', $record->exchange_reference)
            : ProductReplacement::whereKey($record->id);
        $rejectQuery->update([
            'status' => 'rejected', 'rejection_reason' => $validated['rejection_reason'],
            'reviewed_by' => auth()->id(), 'reviewed_at' => now(),
        ]);

        if ($record->creator && (int) $record->creator->id !== (int) auth()->id()) {
            $channel = $this->normalizeChannel($record->transaction?->channel_type);
            $record->creator->notify(new InventoryWorkflowNotification(
                'replacement_rejected',
                sprintf(
                    'Your %s replacement request for order %s was rejected by %s. Reason: %s',
                    ucfirst($channel),
                    $record->transaction?->order_number ?? $record->transaction_id,
                    auth()->user()?->name ?? 'Inventory staff',
                    $validated['rejection_reason']
                ),
                (int) $record->transaction?->store_hub_id,
                route('hub.report', [
                    'hub' => $record->transaction?->store_hub_id,
                    'channel' => $channel,
                ]),
                ($channel === 'walk_in' ? 'Walk-In' : ucfirst($channel)).' replacement rejected',
                $channel
            ));
        }

        return back()->with('success', 'Replacement request rejected. Inventory and totals were not changed.');
    }

    private function normalizeChannel(?string $channel): string
    {
        $normalized = strtolower(trim((string) $channel));
        $normalized = str_replace(['-', ' '], '_', $normalized);
        $normalized = str_replace(['_sales', '_orders'], '', $normalized);
        if ($normalized === 'walkin') {
            $normalized = 'walk_in';
        }

        return in_array($normalized, ['shopee', 'lazada', 'tiktok', 'online', 'wholesale', 'walk_in'], true)
            ? $normalized
            : 'all';
    }

    private function tiktokOrderFullyReturned(SalesTransaction $transaction): bool
    {
        $transaction->loadMissing([
            'items.inventoryReturns',
            'items.replacements.inventoryReturns',
        ]);
        if ($transaction->items->isEmpty()) {
            return false;
        }

        return $transaction->items->every(function ($item) {
            $approvedReplacements = $item->replacements->where('status', 'approved');
            $returnedQuantity = max(
                (int) ($item->returned_quantity ?? 0),
                (int) $item->inventoryReturns->sum('quantity')
            );
            $originalRemaining = max(0,
                (int) $item->quantity
                - $returnedQuantity
                - (int) $approvedReplacements->sum('quantity')
            );
            $replacementRemaining = $approvedReplacements->sum(fn ($replacement) => max(0,
                (int) ($replacement->replacement_quantity ?: $replacement->quantity)
                - (int) $replacement->inventoryReturns->sum('quantity')
            ));

            return $originalRemaining + $replacementRemaining === 0;
        });
    }

    private function walkInPaymentBreakdown($transactions, bool $groupCustomAsOther = false)
    {
        $totals = collect();
        $add = function (string $method, float $amount, int $count = 1) use ($totals): void {
            if ($amount <= 0) {
               return;
            }
            $entry = $totals->get($method, ['count' => 0, 'total' => 0.0]);
            $totals->put($method, [
               'count' => $entry['count'] + $count,
               'total' => $entry['total'] + $amount,
            ]);
        };
        $label = static function ($record) use ($groupCustomAsOther): string {
            $method = strtoupper(trim((string) ($record->mode_of_payment ?: 'OTHERS')));
            if ($method === 'PAYMAYA' || $method === 'MAYA') {
               return 'PAYMAYA';
            }
            if (in_array($method, ['CASH', 'GCASH', 'QRPH', 'BDO', 'BPI', 'METROBANK', 'DATED_CHECK', 'POST_DATED_CHECK'], true)) {
               return str_replace('_', '-', $method);
            }

            if ($groupCustomAsOther) {
               return 'OTHER';
            }

            return strtoupper(trim((string) ($record->custom_mop ?: 'OTHERS')));
        };

        foreach ($transactions as $transaction) {
            $additionalPayments = (float) $transaction->paymentRecords->sum('amount');
            $basePaid = max(0, (float) ($transaction->amount_paid ?? 0) - $additionalPayments);
            $add($label($transaction), $basePaid);
            foreach ($transaction->paymentRecords as $payment) {
               $add($label($payment), (float) $payment->amount);
            }
        }

        return $totals->map(fn ($entry, $method) => (object) [
            'payment_method' => $method,
            'transaction_count' => $entry['count'],
            'total' => round($entry['total'], 2),
        ])->sortBy('payment_method')->values();
    }

    private function resolveTotal(SalesTransaction $transaction): float
    {
        $headerTotal = (float) ($transaction->grand_total ?? 0);

        return $headerTotal > 0
            ? $headerTotal
            : max((float) ($transaction->total_amount ?? 0), (float) $transaction->items->sum('line_total'));
    }

    private function resolveReportedTotal(SalesTransaction $transaction): float
    {
        if ($this->normalizeChannel($transaction->channel_type) === 'wholesale') {
            return min(
                $this->resolveTotal($transaction),
                max(0, (float) ($transaction->amount_paid ?? 0))
            );
        }

        return $this->resolveTotal($transaction);
    }
}
