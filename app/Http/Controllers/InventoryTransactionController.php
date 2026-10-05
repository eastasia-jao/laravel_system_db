<?php

namespace App\Http\Controllers;

use App\Models\InventoryTransaction;
use App\Models\FullyBookedOrder;
use App\Models\FullyBookedOrderItem;
use App\Models\Product;
use App\Models\ProductStockAllocation;
use App\Models\SalesTransaction;
use App\Models\StaffActivityLog;
use App\Models\StoreHub;
use App\Models\TransactionItem;
use App\Models\User;
use App\Notifications\InventoryWorkflowNotification;
use App\Support\CsvIdentifier;
use Illuminate\Support\Carbon;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class InventoryTransactionController extends Controller
{
    private const TYPES = ['stock_transfer', 'branch_transfer', 'sponsor_workshop', 'restock', 'return'];

    public function downloadProductWorksheet(Request $request)
    {
        $validated = $request->validate([
            'hub_id' => ['required', 'integer', 'exists:store_hubs,id'],
            'type' => ['required', Rule::in(self::TYPES)],
        ]);
        $user = auth()->user();
        abort_unless(
            in_array($user?->role, ['admin', 'inventory_staff'], true)
                || ($user?->role === 'sales_associate' && $user->canAccessHub((int) $validated['hub_id'])),
            403
        );

        $quantityHeading = match ($validated['type']) {
            'restock' => 'Actual Added Qty',
            'return' => 'Actual Return Qty',
            default => 'Physical Actual Pullout',
        };
        $headers = ['Name / Description', 'Barcode', 'Item ID', 'Physical Stocks Qty', $quantityHeading];
        if ($validated['type'] === 'return') {
            $headers[] = 'Condition';
        }

        $hub = StoreHub::findOrFail($validated['hub_id']);
        $hubCode = Str::upper(Str::slug($hub->code, '_')) ?: (string) $hub->id;
        $typeLabel = match ($validated['type']) {
            'stock_transfer' => $hub->is_head_office ? 'StockTrf_HO2B' : 'StockTrf_B2HO',
            'branch_transfer' => 'StockTrf_B2B',
            'sponsor_workshop' => 'SponsorWorkshop',
            'restock' => 'Restock',
            'return' => 'Return',
        };
        $filename = $hubCode.'_Download_'.$typeLabel.'_'.now()->format('Ymd_His').'.csv';
        $callback = function () use ($headers, $validated) {
            $file = fopen('php://output', 'w');
            fwrite($file, "\xEF\xBB\xBF");
            fputcsv($file, $headers);

            Product::where('store_hub_id', $validated['hub_id'])
                ->where('status', 'active')
                ->when(
                    $validated['type'] === 'branch_transfer',
                    fn ($query) => $query->orderByCatalog('item_id'),
                    fn ($query) => $query->orderByCatalog(),
                )
                ->each(function (Product $product) use ($file, $validated) {
                    $row = [
                        $product->description ?: $product->name,
                        CsvIdentifier::write($product->barcode ?? '', 'Barcode'),
                        CsvIdentifier::write($product->item_id, 'Item ID'),
                        (int) $product->stock,
                        '',
                    ];
                    if ($validated['type'] === 'return') {
                        $row[] = '';
                    }
                    fputcsv($file, $row);
                });

            fclose($file);
        };

        return response()->streamDownload($callback, $filename, [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }

    public function returnSalesLookup(Request $request)
    {
        $validated = $request->validate([
            'hub_id' => ['required', 'integer', 'exists:store_hubs,id'],
            'channel' => ['required', 'string', 'max:50'],
            'customer' => ['nullable', 'string', 'max:255'],
            'transaction_id' => ['nullable', 'integer'],
            'date' => ['nullable', 'date_format:Y-m-d'],
        ]);
        $user = auth()->user();
        abort_unless(
            $user?->role === 'admin'
                || ($user?->role === 'inventory_staff' && (int) $user->store_hub_id === (int) $validated['hub_id'])
                || ($user?->role === 'sales_associate' && $user->canAccessHub((int) $validated['hub_id'])),
            403
        );

        $normalizedChannel = $this->normalizeChannel($validated['channel']);
        $lookupHub = StoreHub::findOrFail((int) $validated['hub_id']);
        if (! $lookupHub->is_head_office && $normalizedChannel !== 'walk_in') {
            throw ValidationException::withMessages([
                'channel' => 'Only Walk-In sales can be selected for a branch store.',
            ]);
        }
        if ($user?->role === 'sales_associate') {
            abort_unless($normalizedChannel === 'walk_in', 403, 'Sales associates can only view Walk-In sales.');
        }
        $channelValues = match ($normalizedChannel) {
            'online' => ['online', 'online_sales', 'online_order', 'event', 'fully_booked'],
            'walk_in' => ['walk_in', 'walkin'],
            default => [$this->normalizeChannel($validated['channel'])],
        };
        $query = SalesTransaction::query()
            ->where('store_hub_id', $validated['hub_id'])
            ->whereIn(DB::raw("LOWER(REPLACE(REPLACE(channel_type, '-', '_'), ' ', '_'))"), $channelValues)
            ->when($validated['date'] ?? null, fn ($query, $date) => $query
                ->where('order_date', '>=', $date)
                ->where('order_date', '<', Carbon::parse($date)->addDay()->toDateString()))
            ->when($normalizedChannel === 'tiktok', fn ($query) => $query->whereNotNull('sales_after_transaction_fee'))
            ->with(['items.product', 'items.inventoryReturns', 'replacements.replacementProduct', 'replacements.inventoryReturns']);

        if ($request->filled('transaction_id')) {
            $transaction = $query->whereKey($validated['transaction_id'])->firstOrFail();
            $fullyBookedOrder = $normalizedChannel === 'fully_booked'
                ? FullyBookedOrder::where('store_hub_id', $transaction->store_hub_id)
                    ->where('order_number', $transaction->order_number)
                    ->first()
                : null;

            return response()->json([
                'transaction' => [
                    'id' => $transaction->id,
                    'order_number' => $transaction->order_number,
                    'customer_name' => $transaction->customer_name,
                    'fully_booked_attachment' => $fullyBookedOrder ? [
                        'url' => route('inventory-transactions.fully-booked.attachment', $fullyBookedOrder),
                        'file_name' => $fullyBookedOrder->original_filename,
                        'mime_type' => $fullyBookedOrder->mime_type,
                    ] : null,
                    'items' => $transaction->items
                        ->map(function ($item) use ($transaction) {
                            $replacedQuantity = (int) $transaction->replacements
                                ->where('status', 'approved')
                                ->where('transaction_item_id', $item->id)
                                ->sum('quantity');

                            return [
                                'id' => $item->id,
                                'product_id' => $item->product_id,
                                'name' => $item->product?->name ?? 'Product #'.$item->product_id,
                                'item_id' => $item->product?->item_id,
                                'quantity' => max(0, (int) $item->quantity - $replacedQuantity),
                                'returned_quantity' => (int) $item->inventoryReturns->sum('quantity'),
                                'unit_price' => (float) $item->unit_price,
                                'discount_percentage' => (float) ($item->discount_percentage ?? 0),
                            ];
                        })
                        ->concat($transaction->replacements
                            ->where('status', 'approved')
                            ->map(fn ($replacement) => [
                            'id' => null,
                            'replacement_id' => $replacement->id,
                            'product_id' => $replacement->replacement_product_id,
                            'name' => $replacement->replacementProduct?->name ?? 'Product #'.$replacement->replacement_product_id,
                            'item_id' => $replacement->replacementProduct?->item_id,
                            'quantity' => (int) ($replacement->replacement_quantity ?: $replacement->quantity),
                            'returned_quantity' => (int) $replacement->inventoryReturns->sum('quantity'),
                            'unit_price' => (float) $replacement->replacement_unit_price,
                            'discount_percentage' => (float) ($replacement->replacement_discount_percentage ?? 0),
                        ]))
                        ->values(),
                ],
            ]);
        }

        if ($request->filled('customer')) {
            return response()->json([
                'orders' => $query->where('customer_name', $validated['customer'])
                    ->orderByDesc('order_date')
                    ->get(['id', 'order_number', 'order_date', 'customer_name'])
                    ->map(fn ($transaction) => [
                        'id' => $transaction->id,
                        'order_number' => $transaction->order_number,
                        'order_date' => optional($transaction->order_date)->format('M d, Y'),
                        'customer_name' => $transaction->customer_name,
                    ])->values(),
            ]);
        }

        if ($normalizedChannel === 'fully_booked') {
            return response()->json([
                'orders' => $query->orderByDesc('order_date')
                    ->get(['id', 'order_number', 'order_date'])
                    ->map(fn ($transaction) => [
                        'id' => $transaction->id,
                        'order_number' => $transaction->order_number,
                        'order_date' => optional($transaction->order_date)->format('M d, Y'),
                    ])->values(),
            ]);
        }

        return response()->json([
            'customers' => $query->whereNotNull('customer_name')
                ->distinct()
                ->orderBy('customer_name')
                ->pluck('customer_name')
                ->values(),
        ]);
    }

    public function index(Request $request)
    {
        $user = auth()->user();
        $hubId = $request->integer('hub_id') ?: $user?->store_hub_id;
        $returnLogForAssignedHub = $request->input('type') === 'return'
            && $user?->role === 'inventory_staff';
        if ($returnLogForAssignedHub) {
            $hubId = $user->store_hub_id;
        }
        if ($user?->role === 'sales_associate') {
            $hubId = $request->integer('hub_id') ?: $user->store_hub_id;
            abort_unless($hubId && $user->canAccessHub((int) $hubId), 403);
        } elseif ($user && ! in_array($user->role, ['admin', 'inventory_staff'], true)) {
            $hubId = $user->store_hub_id;
        }

        $fullyBookedOrders = in_array($user?->role, ['admin', 'inventory_staff'], true)
            && (! $request->filled('type') || $request->input('type') === 'fully_booked')
            && (! in_array($request->input('status'), ['approved', 'rejected'], true))
            ? FullyBookedOrder::with(['storeHub', 'salesStaff', 'submitter', 'reviewer', 'pullOutBy', 'items.product'])
                ->where('status', '!=', 'pending')
                ->when($hubId, fn ($query) => $query->where('store_hub_id', $hubId))
                ->when($request->filled('month'), fn ($query) => $query->whereBetween('created_at', [
                    $request->input('month').'-01 00:00:00',
                    now()->parse($request->input('month').'-01')->endOfMonth()->toDateString().' 23:59:59',
                ]))
                ->when(in_array($request->input('status'), ['pending', 'reviewed'], true), fn ($query) => $query->where('status', $request->input('status')))
                ->latest()
                ->get()
            : collect();

        $transactions = InventoryTransaction::with([
            'product', 'storeHub', 'sourceHub', 'targetHub', 'creator',
            'salesTransaction.user', 'salesTransaction.confirmedBy',
            'salesTransaction.items.replacements.reviewer',
            'salesTransaction.items.replacements.originalProduct',
            'salesTransaction.items.replacements.replacementProduct',
            'productReplacement.originalProduct', 'productReplacement.replacementProduct',
            'productReplacement.reviewer', 'productReplacement.transaction', 'reviewer',
        ])
            ->where('type', '!=', 'branch_transfer')
            ->when($hubId, fn ($query) => $query->where('store_hub_id', $hubId))
            ->when($request->filled('type') && $user?->role !== 'sales_associate', function ($query) use ($request) {
                if ($request->input('type') === 'branch_transfer') {
                    return $query;
                }

                if ($request->input('type') === 'fully_booked') {
                    return $query->where('type', 'sponsor_workshop')->where('channel', 'fully_booked');
                }

                if ($request->input('type') === 'replacement') {
                    return $query->whereIn('type', ['replacement_return', 'replacement_out']);
                }

                return $query->where('type', $request->input('type'));
            })
            ->when($request->filled('month'), fn ($query) => $query->whereBetween('occurred_on', [
                $request->input('month').'-01',
                now()->parse($request->input('month').'-01')->endOfMonth()->toDateString(),
            ]))
            ->when(
                in_array($request->input('status'), ['pending', 'approved', 'rejected'], true)
                    && $request->input('type') !== 'fully_booked',
                fn ($query) => $query->where('status', $request->input('status'))
            )
            ->latest('occurred_on')->latest()->get();

        $fullyBookedReturnReferences = $transactions
            ->where('type', 'return')
            ->filter(fn (InventoryTransaction $transaction) => $this->normalizeChannel($transaction->salesTransaction?->channel_type) === 'fully_booked')
            ->map(fn (InventoryTransaction $transaction) => $transaction->salesTransaction?->order_number)
            ->filter();
        $fullyBookedReferences = $transactions
                ->where('type', 'sponsor_workshop')
                ->where('channel', 'fully_booked')
                ->pluck('reference')
            ->merge($fullyBookedReturnReferences)
            ->filter()
            ->unique();
        $linkedFullyBookedOrders = FullyBookedOrder::with(['storeHub', 'salesStaff', 'submitter', 'reviewer', 'pullOutBy', 'items.product'])
            ->whereIn('order_number', $fullyBookedReferences)
            ->get();
        $fullyBookedOrders = $fullyBookedOrders->merge($linkedFullyBookedOrders)->unique('id')->values();
        $fullyBookedOrdersByNumber = $fullyBookedOrders->keyBy('order_number');
        $transactions->each(function (InventoryTransaction $transaction) use ($fullyBookedOrdersByNumber) {
            if ($transaction->type === 'sponsor_workshop' && $transaction->channel === 'fully_booked') {
                $transaction->setRelation('fullyBookedOrder', $fullyBookedOrdersByNumber->get($transaction->reference));
            } elseif ($transaction->type === 'return'
                && $this->normalizeChannel($transaction->salesTransaction?->channel_type) === 'fully_booked') {
                $transaction->setRelation(
                    'fullyBookedOrder',
                    $fullyBookedOrdersByNumber->get($transaction->salesTransaction?->order_number)
                );
            }
        });
        if ($request->input('type') === 'fully_booked') {
            $transactions = $transactions->filter(function (InventoryTransaction $transaction) use ($request) {
                $order = $transaction->getRelation('fullyBookedOrder');

                return $order && (! in_array($request->input('status'), ['pending', 'reviewed'], true)
                    || $order->status === $request->input('status'));
            })->values();
        }

        $soldReferences = $transactions
            ->where('type', 'sold')
            ->whereNull('sales_transaction_id')
            ->pluck('reference')
            ->filter()
            ->unique();
        if ($soldReferences->isNotEmpty()) {
            $salesByReference = SalesTransaction::with([
                'user', 'confirmedBy', 'items.replacements.reviewer',
                'items.replacements.originalProduct', 'items.replacements.replacementProduct',
            ])
                ->whereIn('order_number', $soldReferences)
                ->get()
                ->keyBy('order_number');
            $transactions->each(function (InventoryTransaction $transaction) use ($salesByReference) {
                if ($transaction->type === 'sold' && ! $transaction->sales_transaction_id && $transaction->reference) {
                    $transaction->setRelation('salesTransaction', $salesByReference->get($transaction->reference));
                }
            });
        }

        $unlinkedTikTokReturns = $transactions
            ->where('type', 'return')
            ->filter(fn (InventoryTransaction $transaction) => ! $transaction->sales_transaction_id
                && $this->normalizeChannel($transaction->channel) === 'tiktok'
                && $transaction->reference);
        if ($unlinkedTikTokReturns->isNotEmpty()) {
            $salesByHubAndReference = SalesTransaction::query()
                ->where('channel_type', 'tiktok')
                ->whereIn('store_hub_id', $unlinkedTikTokReturns->pluck('store_hub_id')->unique())
                ->whereIn('order_number', $unlinkedTikTokReturns->pluck('reference')->unique())
                ->get()
                ->keyBy(fn (SalesTransaction $sale) => $sale->store_hub_id.'|'.$sale->order_number);
            $unlinkedTikTokReturns->each(function (InventoryTransaction $transaction) use ($salesByHubAndReference) {
                $transaction->setRelation(
                    'salesTransaction',
                    $salesByHubAndReference->get($transaction->store_hub_id.'|'.$transaction->reference)
                );
            });
        }

        $replacementTransactions = $transactions
            ->whereIn('type', ['replacement_return', 'replacement_out'])
            ->whereNull('product_replacement_id');
        $replacementReferences = $replacementTransactions->pluck('reference')->filter()->unique();
        if ($replacementReferences->isNotEmpty()) {
            $replacementSales = SalesTransaction::with([
                'replacements.reviewer', 'replacements.originalProduct', 'replacements.replacementProduct',
                'replacements.transaction',
            ])->whereIn('order_number', $replacementReferences)->get()->keyBy('order_number');
            $replacementTransactions->each(function (InventoryTransaction $transaction) use ($replacementSales) {
                $sale = $replacementSales->get($transaction->reference);
                $replacement = $sale?->replacements
                    ->first(fn ($candidate) => $transaction->type === 'replacement_return'
                        ? (int) $candidate->original_product_id === (int) $transaction->product_id
                        : (int) $candidate->replacement_product_id === (int) $transaction->product_id);
                if ($replacement) {
                    $transaction->setRelation('productReplacement', $replacement);
                }
            });
        }

        $transactionGroups = $transactions->groupBy(function (InventoryTransaction $transaction) {
            if (in_array($transaction->type, ['return', 'sold'], true) && $transaction->salesTransaction) {
                return $transaction->type.'-'.$transaction->salesTransaction->id;
            }
            if ($transaction->productReplacement) {
                return 'replacement-'.$transaction->productReplacement->id;
            }
            if (in_array($transaction->type, ['stock_transfer', 'branch_transfer'], true)) {
                return $transaction->type.'-'.(
                    $transaction->transfer_batch_id
                    ?: ($transaction->reference ?: $transaction->source_hub_id.'-'.$transaction->target_hub_id.'-'.$transaction->occurred_on)
                );
            }
            if ($transaction->type === 'sponsor_workshop' && $transaction->channel === 'fully_booked' && $transaction->reference) {
                return 'fully-booked-'.$transaction->reference;
            }
            if ($transaction->type === 'sponsor_workshop' && $transaction->reference) {
                return 'sponsor_workshop-'.$transaction->reference;
            }
            if ($transaction->type === 'restock') {
                return $transaction->reference
                    ? 'restock-'.$transaction->reference
                    : 'restock-legacy-'.$transaction->created_by.'-'.$transaction->occurred_on.'-'
                        .$transaction->source.'-'.$transaction->notes.'-'.$transaction->created_at?->format('YmdHis');
            }

            return 'transaction-'.$transaction->id;
        });
        $fullyBookedGroups = $fullyBookedOrders
            ->where('status', '!=', 'pending')
            ->whereNull('pulled_out_at')
            ->mapWithKeys(function (FullyBookedOrder $order) {
            $transaction = new InventoryTransaction;
            $transaction->setRawAttributes([
                'id' => 'fully-booked-'.$order->id,
                'type' => 'fully_booked',
                'reference' => $order->order_number,
                'store_hub_id' => $order->store_hub_id,
                'channel' => 'fully_booked',
                'quantity' => 0,
                'occurred_on' => $order->created_at?->toDateString(),
                'created_by' => $order->submitted_by,
                'status' => $order->status,
            ], true);
            $transaction->setRelation('storeHub', $order->storeHub);
            $transaction->setRelation('creator', $order->submitter);
            $transaction->setRelation('fullyBookedOrder', $order);

            return ['fully-booked-'.$order->id => collect([$transaction])];
            });
        $transactionGroups = collect($transactionGroups->all())
            ->merge($fullyBookedGroups)
            ->sortByDesc(fn ($group) => $group->first()->occurred_on?->timestamp)
            ->values();
        $page = LengthAwarePaginator::resolveCurrentPage();
        $transactions = new LengthAwarePaginator(
            $transactionGroups->forPage($page, 20),
            $transactionGroups->count(),
            20,
            $page,
            ['path' => $request->url(), 'query' => $request->query()]
        );

        $hubs = $returnLogForAssignedHub && $user->store_hub_id
            ? StoreHub::whereKey($user->store_hub_id)->where('status', 'active')->get()
            : ($returnLogForAssignedHub
            ? collect()
            : (in_array($user?->role, ['admin', 'inventory_staff'], true)
            ? StoreHub::where('status', 'active')->orderBy('name')->get()
            : StoreHub::whereIn('id', $user?->accessibleStoreHubIds() ?? [])->where('status', 'active')->get()));

        return view('inventory-transactions.index', compact('transactions', 'hubs', 'hubId'));
    }

    public function create(Request $request)
    {
        $type = $request->route('type') ?? $request->input('type', 'restock');
        abort_unless(in_array($type, self::TYPES, true), 404);

        $user = auth()->user();
        if ($type === 'sponsor_workshop' && $user?->role === 'sales_marketing_staff') {
            abort_unless($user->hasSalesChannel('fully_booked'), 403);
        }
        if ($type === 'return' && $user?->role === 'inventory_staff') {
            abort_unless($user->store_hub_id, 403, 'Your account has no designated store.');

            $hubId = (int) $user->store_hub_id;
            $hubs = StoreHub::whereKey($hubId)->where('status', 'active')->get();
            $allHubs = $hubs;
            $headOffices = $hubs->where('is_head_office', true)->values();
            $products = Product::where('store_hub_id', $hubId)
                ->where('status', 'active')
                ->orderByCatalog()
                ->limit(100)
                ->get();
            $form = 'return';
            $transferSourceHubs = $hubs;
            $selectedHub = $hubs->first();

            return view('inventory-transactions.forms.'.$form, compact(
                'hubs', 'allHubs', 'headOffices', 'products', 'hubId', 'type', 'transferSourceHubs',
                'selectedHub'
            ));
        }

        $hubId = $request->integer('hub_id') ?: $user?->store_hub_id;
        if ($type === 'return' && $user?->role !== 'admin') {
            abort_unless(
                $hubId && in_array((int) $hubId, $user?->accessibleStoreHubIds() ?? [], true),
                403,
                'You can only select an assigned store.'
            );
        }
        $hubs = in_array($user?->role, ['admin', 'inventory_staff'], true)
            ? StoreHub::where('status', 'active')->orderBy('name')->get()
            : StoreHub::whereIn('id', $user?->accessibleStoreHubIds() ?? [])->where('status', 'active')->orderBy('name')->get();
        if ($type === 'return' && $user?->role === 'inventory_staff') {
            $hubs = $user->store_hub_id
                ? StoreHub::whereKey($user->store_hub_id)->where('status', 'active')->get()
                : collect();
            $hubId = $hubs->first()?->id;
        }
        $hubs = $hubs->filter(fn (StoreHub $hub) => $hub->status === 'active')->values();
        $allHubs = StoreHub::where('status', 'active')->orderBy('name')->get();
        $allHubs = $allHubs->filter(fn (StoreHub $hub) => $hub->status === 'active')->values();
        $headOffices = $allHubs->filter(fn (StoreHub $hub) => $hub->is_head_office)->values();
        $transferDirection = $request->input('direction') === 'branch_to_ho'
            ? 'branch_to_ho'
            : 'ho_to_branch';
        if ($type === 'branch_transfer') {
            $hubs = $hubs->filter(fn (StoreHub $hub) => ! $hub->is_head_office)->values();
            if ($user?->store_hub_id && $user?->role !== 'sales_associate') {
                $hubs = $hubs->filter(fn (StoreHub $hub) => (int) $hub->id === (int) $user->store_hub_id)->values();
            }
                $allHubs = $allHubs->filter(fn (StoreHub $hub) => ! $hub->is_head_office)->values();
            $hubId = ($hubs->firstWhere('id', $hubId) ?: $hubs->first())?->id;
        }
        if ($type === 'stock_transfer') {
            $hubs = $transferDirection === 'branch_to_ho'
                ? $allHubs->filter(fn (StoreHub $hub) => ! $hub->is_head_office)->values()
                : $headOffices;
            $allHubs = $transferDirection === 'branch_to_ho'
                ? $headOffices
                : $allHubs->filter(fn (StoreHub $hub) => ! $hub->is_head_office)->values();
        }
        if ($type === 'restock') {
            $selectedHeadOffice = $headOffices->firstWhere('id', $hubId) ?: $headOffices->first();
            $hubId = $selectedHeadOffice?->id;
        } else {
            $hubId = ($hubs->firstWhere('id', $hubId) ?: $hubs->first())?->id;
        }
        $products = Product::where('store_hub_id', $hubId)->where('status', 'active')->orderByCatalog()->limit(100)->get();

        $form = $type === 'branch_transfer' ? 'stock_transfer' : $type;
        $transferSourceHubs = $hubs;
        $selectedHub = $hubs->firstWhere('id', $hubId);
        $transferReference = in_array($type, ['stock_transfer', 'branch_transfer'], true) && $selectedHub
            ? $this->newTransferReference($selectedHub->code)
            : null;
        $sponsorReference = $type === 'sponsor_workshop' && $selectedHub
            ? $this->newSponsorReference($selectedHub->code)
            : null;
        $restockReference = $type === 'restock' && $selectedHub
            ? $this->newRestockReference($selectedHub->code)
            : null;
        $fullyBookedStaff = $type === 'sponsor_workshop'
            ? User::with('storeHub')->where('role', 'sales_marketing_staff')->where('status', 'active')->orderBy('name')->get()
                ->filter(fn (User $staff) => $staff->hasSalesChannel('fully_booked'))->values()
            : collect();
        $canManageEvent = $user?->can('manage-inventory') ?? false;
        $canSubmitFullyBooked = $user?->role === 'sales_marketing_staff' && $user->hasSalesChannel('fully_booked');
        $canUseFullyBookedForm = $canManageEvent || $canSubmitFullyBooked;
        $activityType = $canSubmitFullyBooked && ! $canManageEvent
            ? 'fully_booked'
            : ($request->input('activity_type') === 'fully_booked' && $canUseFullyBookedForm ? 'fully_booked' : 'event');
        $fullyBookedOrders = $canManageEvent
            ? FullyBookedOrder::with(['storeHub', 'salesStaff'])
                ->whereNull('pulled_out_at')
                ->latest()
                ->get()
            : ($canSubmitFullyBooked
                ? FullyBookedOrder::with(['storeHub', 'items'])
                    ->where('submitted_by', $user->id)
                    ->latest()
                    ->orderByDesc('id')
                    ->paginate(6)
                    ->withQueryString()
                : collect());

        return view('inventory-transactions.forms.'.$form, compact(
            'hubs', 'allHubs', 'headOffices', 'products', 'hubId', 'type', 'transferSourceHubs',
            'selectedHub', 'transferReference', 'sponsorReference', 'restockReference',
            'transferDirection', 'fullyBookedStaff', 'canManageEvent', 'canSubmitFullyBooked',
            'canUseFullyBookedForm', 'activityType', 'fullyBookedOrders'
        ));
    }

    public function store(Request $request)
    {
        // One JSON field avoids PHP max_input_vars truncating large item lists.
        if ($request->has('items_json')) {
            $request->validate(['items_json' => ['required', 'json']]);
            $items = json_decode($request->input('items_json'), true);
            if (! is_array($items) || ! array_is_list($items)) {
                throw ValidationException::withMessages(['items' => 'The imported items must be a list.']);
            }
            $request->merge(['items' => $items]);
        }
        if (is_array($request->input('items'))) {
            $request->validate([
                'items' => ['required', 'array', 'min:1'],
                'items.*.product_id' => ['required', 'integer'],
            ]);
            $ids = collect($request->input('items'))->pluck('product_id')->unique();
            $listed = Product::where('store_hub_id', $request->input('store_hub_id'))
                ->where('status', 'active')->whereIn('id', $ids)->pluck('id');
            if ($ids->diff($listed)->isNotEmpty()) {
                throw ValidationException::withMessages(['items' => 'One or more items are not in the active List of Products for the selected store.']);
            }
        }
        if (in_array($request->input('type'), ['stock_transfer', 'branch_transfer'], true)) {
            return $this->storeStockTransfer($request);
        }
        if ($request->input('type') === 'sponsor_workshop') {
            return $this->storeSponsorWorkshop($request);
        }
        if ($request->input('type') === 'restock') {
            return $this->storeRestock($request);
        }
        if ($request->input('type') === 'return') {
            return $this->storeReturn($request);
        }

        $validated = $request->validate([
            'type' => ['required', Rule::in(self::TYPES)],
            'store_hub_id' => ['required', 'exists:store_hubs,id'],
            'product_id' => ['required', 'exists:products,id'],
            'quantity' => ['required', 'integer', 'min:1'],
            'occurred_on' => ['required', 'date'],
            'channel' => ['nullable', 'string', 'max:50'],
            'source' => ['nullable', 'string', 'max:100'],
            'condition' => ['nullable', Rule::in(['good', 'damaged'])],
            'source_hub_id' => ['nullable', 'exists:store_hubs,id'],
            'target_hub_id' => ['nullable', 'exists:store_hubs,id'],
            'reference' => ['required', 'string', 'max:100'],
            'notes' => ['nullable', 'string', 'max:120'],
        ]);

        $user = auth()->user();
        if ($user?->role !== 'admin' && $user?->role !== 'inventory_staff' && ! $user?->canAccessHub((int) $validated['store_hub_id'])) {
            abort(403, 'Unauthorized action for this store hub.');
        }

        DB::transaction(function () use ($validated) {
            $product = Product::whereKey($validated['product_id'])->lockForUpdate()->firstOrFail();
            if ((int) $product->store_hub_id !== (int) $validated['store_hub_id']) {
                abort(422, 'The product does not belong to the selected store hub.');
            }

            $type = $validated['type'];
            $quantity = (int) $validated['quantity'];
            $targetHubId = $validated['target_hub_id'] ?? null;
            if ($type === 'stock_transfer' && ! $targetHubId) {
                abort(422, 'A target store is required for stock transfers.');
            }

            if (in_array($type, ['stock_transfer', 'sponsor_workshop'], true)) {
                if ($product->stock < $quantity) {
                    abort(422, "Not enough stock for {$product->name}.");
                }
                $product->decrement('stock', $quantity);
            } elseif (in_array($type, ['restock', 'return'], true)) {
                $product->increment('stock', $quantity);
            }

            if ($type === 'stock_transfer' && $targetHubId) {
                $targetProduct = Product::where('store_hub_id', $targetHubId)
                    ->whereCatalog('item_id', $product->item_id)->lockForUpdate()->first();
                if (! $targetProduct) {
                    abort(422, 'The product does not exist in the target store.');
                }
                $targetProduct->increment('stock', $quantity);
            }

            if ($type === 'return' && ($validated['condition'] ?? 'good') === 'good' && ($validated['channel'] ?? null) !== 'fully_booked') {
                $channel = $this->normalizeChannel($validated['channel'] ?? null);
                if ($channel && $channel !== 'walk_in') {
                    ProductStockAllocation::firstOrCreate(['product_id' => $product->id])->increment($channel, $quantity);
                }
            }

            InventoryTransaction::create([
                ...$validated,
                'created_by' => auth()->id(),
                'source_hub_id' => $validated['source_hub_id'] ?? null,
                'target_hub_id' => $targetHubId,
            ]);
        });

        $this->notifyInventoryStaff($validated);

        return redirect()->route('inventory-transactions.index', ['hub_id' => $validated['store_hub_id']])
            ->with('success', 'Inventory transaction recorded successfully.');
    }

    private function storeStockTransfer(Request $request)
    {
        if ($request->input('type') === 'stock_transfer' && ! $request->exists('transfer_direction')) {
            $request->merge(['transfer_direction' => 'ho_to_branch']);
        }
        $validated = $request->validate([
            'type' => ['required', Rule::in(['stock_transfer', 'branch_transfer'])],
            'store_hub_id' => ['required', 'exists:store_hubs,id'],
            'target_hub_id' => ['required', 'exists:store_hubs,id', 'different:store_hub_id'],
            'transfer_direction' => ['required_if:type,stock_transfer', Rule::in(['ho_to_branch', 'branch_to_ho'])],
            'occurred_on' => ['required', 'date'],
            'reference' => [
                'nullable',
                'string',
                'max:100',
            ],
            'notes' => ['nullable', 'string', 'max:2000'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.product_id' => ['required', 'exists:products,id'],
            'items.*.quantity' => ['required', 'integer', 'min:1'],
        ]);
        $sourceHub = StoreHub::findOrFail((int) $validated['store_hub_id']);
        $submittedReference = (string) ($validated['reference'] ?? '');
        $sourceHubCode = $this->transferReferenceHubCode($sourceHub->code);
        $referencePattern = '/^TRF-'.preg_quote($sourceHubCode, '/').'-\d{8}-\d{6}-[A-F0-9]{6}$/';
        $validated['reference'] = preg_match($referencePattern, $submittedReference) === 1
            ? $submittedReference
            : $this->newTransferReference($sourceHub->code);

        $user = auth()->user();
        if ($user?->role !== 'admin' && $user?->role !== 'inventory_staff' && ! $user?->canAccessHub((int) $validated['store_hub_id'])) {
            abort(403, 'Unauthorized action for this store hub.');
        }

        $transferBatchId = $validated['type'] === 'branch_transfer' ? (string) Str::uuid() : null;
        DB::transaction(function () use ($validated, $transferBatchId) {
            if ($validated['type'] === 'stock_transfer') {
                $sourceIsHeadOffice = $validated['transfer_direction'] === 'ho_to_branch';
                if (! StoreHub::whereKey($validated['store_hub_id'])->where('status', 'active')->where('is_head_office', $sourceIsHeadOffice)->exists()) {
                    throw ValidationException::withMessages([
                        'store_hub_id' => $sourceIsHeadOffice
                            ? 'Select an active store with Head Office Mode enabled.'
                            : 'Select an active source branch.',
                    ]);
                }
                if (! StoreHub::whereKey($validated['target_hub_id'])->where('status', 'active')->where('is_head_office', ! $sourceIsHeadOffice)->exists()) {
                    throw ValidationException::withMessages([
                        'target_hub_id' => $sourceIsHeadOffice
                            ? 'Select an active destination branch.'
                            : 'Select an active destination Head Office.',
                    ]);
                }
            }
            if ($validated['type'] === 'branch_transfer') {
                foreach (['store_hub_id', 'target_hub_id'] as $field) {
                    if (! StoreHub::whereKey($validated[$field])->where('status', 'active')->where('is_head_office', false)->exists()) {
                        throw ValidationException::withMessages([$field => 'Select an active branch, not Head Office.']);
                    }
                }
            }

            $sourceProducts = Product::where('store_hub_id', $validated['store_hub_id'])
                ->whereIn('id', collect($validated['items'])->pluck('product_id'))
                ->lockForUpdate()
                ->get()
                ->keyBy('id');
            $quantities = collect($validated['items'])->groupBy('product_id')
                ->map(fn ($rows) => $rows->sum('quantity'));

            foreach ($quantities as $productId => $quantity) {
                $product = $sourceProducts->get((int) $productId);
                if (! $product) {
                    throw ValidationException::withMessages([
                        'items' => 'One or more selected products do not belong to the source store.',
                    ]);
                }
                if ((int) $product->stock < (int) $quantity) {
                    throw ValidationException::withMessages([
                        'items' => "Not enough stock for {$product->name}.",
                    ]);
                }

                $targetProduct = Product::where('store_hub_id', $validated['target_hub_id'])
                    ->whereCatalog('item_id', $product->item_id)
                    ->lockForUpdate()
                    ->first();
                if (! $targetProduct) {
                    throw ValidationException::withMessages([
                        'items' => "The product {$product->name} is not listed in the selected target store. Add the product to that branch before transferring.",
                    ]);
                }

                if ($validated['type'] === 'stock_transfer') {
                    $product->decrement('stock', $quantity);
                    $targetProduct->increment('stock', $quantity);
                }
                InventoryTransaction::create([
                    'type' => $validated['type'],
                    'reference' => $validated['reference'] ?? null,
                    'transfer_batch_id' => $transferBatchId,
                    'status' => $validated['type'] === 'branch_transfer' ? 'pending' : 'approved',
                    'store_hub_id' => $product->store_hub_id,
                    'product_id' => $product->id,
                    'source_hub_id' => $validated['store_hub_id'],
                    'target_hub_id' => $validated['target_hub_id'],
                    'quantity' => $quantity,
                    'occurred_on' => $validated['occurred_on'],
                    'notes' => $validated['notes'] ?? null,
                    'created_by' => auth()->id(),
                ]);
            }
        });

        if ($validated['type'] === 'branch_transfer') {
            $this->notifyBranchTransferReview($validated, $transferBatchId);
        } else {
            $this->notifyInventoryStaff($validated);
        }

        if ($validated['type'] === 'branch_transfer') {
            $redirectRoute = auth()->user()?->role === 'sales_associate'
                ? route('hub.dashboard', [
                    'id' => $validated['store_hub_id'],
                    'branch_transfer_submitted' => 1,
                ])
                : route('inventory-transactions.index', [
                    'hub_id' => $validated['store_hub_id'],
                    'branch_transfer_submitted' => 1,
                ]);

            return redirect($redirectRoute);
        }

        $redirectRoute = auth()->user()?->role === 'sales_associate'
            ? route('hub.dashboard', [
                'id' => $validated['store_hub_id'],
                'stock_transfer_saved' => 1,
                'reference' => $validated['reference'],
            ])
            : route('inventory-transactions.index', [
                'hub_id' => $validated['store_hub_id'],
                'stock_transfer_saved' => 1,
                'reference' => $validated['reference'],
            ]);

        return redirect($redirectRoute);
    }

    private function newTransferReference(string $sourceHubCode): string
    {
        return 'TRF-'.$this->transferReferenceHubCode($sourceHubCode).'-'.now()->format('Ymd-His').'-'.strtoupper(bin2hex(random_bytes(3)));
    }

    private function newSponsorReference(string $hubCode): string
    {
        return 'SW-'.$this->transferReferenceHubCode($hubCode).'-'.now()->format('Ymd-His').'-'.strtoupper(bin2hex(random_bytes(3)));
    }

    private function newRestockReference(string $hubCode): string
    {
        return 'RST-'.$this->transferReferenceHubCode($hubCode).'-'.now()->format('Ymd-His').'-'.strtoupper(bin2hex(random_bytes(3)));
    }

    private function transferReferenceHubCode(string $sourceHubCode): string
    {
        return Str::upper(Str::slug($sourceHubCode));
    }

    public function reviewBranchTransfer(Request $request, string $batch)
    {
        $validated = $request->validate([
            'decision' => ['required', Rule::in(['approved', 'rejected'])],
            'rejection_reason' => ['required_if:decision,rejected', 'nullable', 'string', 'max:2000'],
        ]);
        $submitterId = null;
        $sourceHubId = null;
        $targetHubId = null;
        $documentNo = null;
        $transferItems = [];
        $receivingAssociates = collect();

        DB::transaction(function () use ($validated, $batch, &$submitterId, &$sourceHubId, &$targetHubId, &$documentNo, &$transferItems, &$receivingAssociates) {
            $entries = InventoryTransaction::where('transfer_batch_id', $batch)
                ->where('type', 'branch_transfer')
                ->lockForUpdate()
                ->get();
            abort_unless($entries->isNotEmpty(), 404);
            abort_unless($entries->every(fn ($entry) => $entry->status === 'pending'), 409, 'This transfer request has already been reviewed.');

            $submitterId = (int) $entries->first()->created_by;
            abort_unless($submitterId !== (int) auth()->id(), 403, 'You cannot review your own transfer request.');
            $sourceHubId = (int) $entries->first()->source_hub_id;
            $targetHubId = (int) $entries->first()->target_hub_id;
            $documentNo = $entries->first()->reference ?: $batch;

            if ($validated['decision'] === 'approved') {
                $sourceHub = StoreHub::whereKey($sourceHubId)->where('status', 'active')->where('is_head_office', false)->first();
                $targetHub = StoreHub::whereKey($targetHubId)->where('status', 'active')->where('is_head_office', false)->first();
                abort_unless($sourceHub && $targetHub, 422, 'Both transfer locations must still be active branches.');

                $quantities = $entries->groupBy('product_id')
                    ->map(fn ($items) => $items->sum('quantity'));
                $sourceProducts = Product::where('store_hub_id', $sourceHubId)
                    ->whereIn('id', $quantities->keys())
                    ->orderBy('id')
                    ->lockForUpdate()
                    ->get()
                    ->keyBy('id');

                foreach ($quantities as $productId => $quantity) {
                    $sourceProduct = $sourceProducts->get((int) $productId);
                    abort_unless($sourceProduct, 422, 'A requested product is no longer available at the source branch.');
                    if ((int) $sourceProduct->stock < (int) $quantity) {
                        throw ValidationException::withMessages([
                            'decision' => "Not enough stock remains for {$sourceProduct->name}; the transfer was not approved.",
                        ]);
                    }
                    $targetProduct = Product::where('store_hub_id', $targetHubId)
                        ->whereCatalog('item_id', $sourceProduct->item_id)
                        ->lockForUpdate()
                        ->first();
                    abort_unless($targetProduct, 422, "The product {$sourceProduct->name} is no longer listed at the destination branch.");

                    $stockBefore = (int) $sourceProduct->stock;
                    $sourceProduct->decrement('stock', $quantity);
                    $targetProduct->increment('stock', $quantity);
                    $transferItems[] = [
                        'product_id' => $sourceProduct->id,
                        'item_id' => $sourceProduct->item_id,
                        'product_name' => $sourceProduct->name,
                        'quantity' => (int) $quantity,
                        'stock_before' => $stockBefore,
                        'stock_after' => $stockBefore - (int) $quantity,
                    ];
                }
            }

            InventoryTransaction::where('transfer_batch_id', $batch)
                ->where('type', 'branch_transfer')
                ->update([
                    'status' => $validated['decision'],
                    'reviewed_by' => auth()->id(),
                    'reviewed_at' => now(),
                    'rejection_reason' => $validated['decision'] === 'rejected' ? $validated['rejection_reason'] : null,
                    'updated_at' => now(),
                ]);

            $receivingAssociates = User::where('role', 'sales_associate')
                ->where('id', '<>', $submitterId)
                ->where(function ($query) use ($targetHubId) {
                    $query->where('hub_id', $targetHubId)
                        ->orWhereHas('assignedStoreHubs', fn ($hubs) => $hubs->whereKey($targetHubId));
                })
                ->orderBy('name')
                ->get(['id', 'name']);

            if ($validated['decision'] !== 'approved') {
                $entries->load('product');
                $transferItems = $entries->groupBy('product_id')
                    ->map(function ($items) {
                        $product = $items->first()->product;

                        return [
                            'product_id' => $product?->id,
                            'item_id' => $product?->item_id,
                            'product_name' => $product?->name ?? 'Deleted product',
                            'quantity' => (int) $items->sum('quantity'),
                            'stock_before' => null,
                            'stock_after' => null,
                        ];
                    })
                    ->values()
                    ->all();
            }

            $sourceName = StoreHub::find($sourceHubId)?->name ?? 'the source branch';
            $targetName = StoreHub::find($targetHubId)?->name ?? 'the destination branch';
            $isApproved = $validated['decision'] === 'approved';
            $log = StaffActivityLog::create([
                'user_id' => $submitterId,
                'store_hub_id' => $sourceHubId,
                'action_type' => 'branch_transfer_sent',
                'description' => "Transfer Document {$documentNo} from {$sourceName} to {$targetName}.",
                'details' => [
                    'transfer_batch_id' => $batch,
                    'reference' => $documentNo,
                    'source_hub_id' => $sourceHubId,
                    'source_hub_name' => $sourceName,
                    'target_hub_id' => $targetHubId,
                    'target_hub_name' => $targetName,
                    'receiver_names' => $receivingAssociates->pluck('name')->all(),
                    'status' => $validated['decision'],
                    'rejection_reason' => $isApproved ? null : $validated['rejection_reason'],
                    'approved_by_user_id' => $isApproved ? auth()->id() : null,
                    'approved_by_name' => $isApproved ? auth()->user()->name : null,
                    'reviewed_by_name' => auth()->user()->name,
                    'reviewed_by_role' => auth()->user()->role,
                ],
            ]);

            $log->items()->createMany(array_map(
                fn (array $item) => [
                    ...$item,
                    'operation' => $isApproved ? 'sent' : 'rejected',
                ],
                $transferItems
            ));
        });

        $submitter = User::find($submitterId);
        $decision = $validated['decision'];
        if ($submitter) {
            $submitterNotificationUrl = route('staff-logs.index', ['search' => $documentNo]);
            $submitter->notify(new InventoryWorkflowNotification(
                $decision === 'approved' ? 'branch_transfer_approved' : 'branch_transfer_rejected',
                sprintf(
                    'Transfer Document %s from %s to %s was %s%s.',
                    $documentNo,
                    StoreHub::find($sourceHubId)?->name ?? 'the source branch',
                    StoreHub::find($targetHubId)?->name ?? 'the destination branch',
                    $decision,
                    $decision === 'rejected' ? ': '.$validated['rejection_reason'] : ''
                ),
                $sourceHubId,
                $submitterNotificationUrl,
                reference: $documentNo
            ));
        }
        if ($decision === 'approved') {
            $sourceName = StoreHub::find($sourceHubId)?->name ?? 'the source branch';
            $targetName = StoreHub::find($targetHubId)?->name ?? 'the destination branch';
            $receivingAssociates->each(fn (User $recipient) => $recipient->notify(new InventoryWorkflowNotification(
                    'branch_transfer_approved',
                    "Transfer Document {$documentNo} from {$sourceName} to {$targetName} was approved. The transferred stock is now available at your branch.",
                    $targetHubId,
                    route('hub.dashboard', $targetHubId)
                )));
        }

        return back()->with('success', 'Branch-to-branch transfer request '.$decision.'.');
    }

    private function storeSponsorWorkshop(Request $request)
    {
        $validated = $request->validate([
            'type' => ['required', Rule::in(['sponsor_workshop'])],
            'store_hub_id' => ['required', 'exists:store_hubs,id'],
            'occurred_on' => ['required', 'date'],
            'source' => ['required', 'string', 'max:100'],
            'reference' => ['nullable', 'string', 'max:100'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.product_id' => ['required', 'exists:products,id'],
            'items.*.quantity' => ['required', 'integer', 'min:1'],
        ]);
        $user = auth()->user();
        if ($user?->role !== 'admin' && $user?->role !== 'inventory_staff' && $user?->store_hub_id && (int) $user->store_hub_id !== (int) $validated['store_hub_id']) {
            abort(403, 'Unauthorized action for this store hub.');
        }
        $hub = StoreHub::whereKey($validated['store_hub_id'])->where('status', 'active')->first();
        if (! $hub) {
            throw ValidationException::withMessages(['store_hub_id' => 'Select an active store hub.']);
        }
        $hubCode = $this->transferReferenceHubCode($hub->code);
        $referencePattern = '/^SW-'.preg_quote($hubCode, '/').'-\d{8}-\d{6}-[A-F0-9]{6}$/';
        $submittedReference = (string) ($validated['reference'] ?? '');
        $validated['reference'] = preg_match($referencePattern, $submittedReference) === 1
            ? $submittedReference
            : $this->newSponsorReference($hub->code);

        DB::transaction(function () use ($validated) {
            $products = Product::where('store_hub_id', $validated['store_hub_id'])
                ->where('status', 'active')
                ->whereIn('id', collect($validated['items'])->pluck('product_id'))
                ->lockForUpdate()
                ->get()
                ->keyBy('id');
            $quantities = collect($validated['items'])->groupBy('product_id')
                ->map(fn ($rows) => $rows->sum('quantity'));

            foreach ($quantities as $productId => $quantity) {
                $product = $products->get((int) $productId);
                if (! $product) {
                    throw ValidationException::withMessages([
                        'items' => 'One or more selected products are not active in the selected store.',
                    ]);
                }
                if ((int) $product->stock < (int) $quantity) {
                    throw ValidationException::withMessages([
                        'items' => "Not enough stock for {$product->name}.",
                    ]);
                }

                $product->decrement('stock', $quantity);
                InventoryTransaction::create([
                    'type' => 'sponsor_workshop',
                    'reference' => $validated['reference'],
                    'store_hub_id' => $product->store_hub_id,
                    'product_id' => $product->id,
                    'source' => $validated['source'],
                    'quantity' => $quantity,
                    'occurred_on' => $validated['occurred_on'],
                    'notes' => $validated['notes'] ?? null,
                    'created_by' => auth()->id(),
                ]);
            }
        });

        $this->notifyInventoryStaff($validated);

        return redirect()->route('inventory-transactions.index', [
            'hub_id' => $validated['store_hub_id'],
            'sponsor_workshop_saved' => 1,
            'reference' => $validated['reference'],
        ]);
    }

    private function storeRestock(Request $request)
    {
        if (! $request->exists('restock_source')) {
            $request->merge(['restock_source' => 'warehouse_request']);
        }
        $validated = $request->validate([
            'type' => ['required', Rule::in(['restock'])],
            'store_hub_id' => ['required', 'exists:store_hubs,id'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.product_id' => ['required', 'exists:products,id'],
            'items.*.quantity' => ['required', 'integer', 'min:1'],
            'occurred_on' => ['required', 'date'],
            'restock_source' => ['required', Rule::in(['warehouse_request'])],
            'reference' => ['nullable', 'string', 'max:100'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);
        $receivingHub = StoreHub::findOrFail($validated['store_hub_id']);
        $hubCode = $this->transferReferenceHubCode($receivingHub->code);
        $referencePattern = '/^RST-'.preg_quote($hubCode, '/').'-\d{8}-\d{6}-[A-F0-9]{6}$/';
        $submittedReference = (string) ($validated['reference'] ?? '');
        $validated['reference'] = preg_match($referencePattern, $submittedReference) === 1
            ? $submittedReference
            : $this->newRestockReference($receivingHub->code);

        $user = auth()->user();
        if ($user?->role !== 'admin' && $user?->role !== 'inventory_staff' && $user?->store_hub_id && (int) $user->store_hub_id !== (int) $validated['store_hub_id']) {
            abort(403, 'Unauthorized action for this store hub.');
        }
        if ($receivingHub->status !== 'active') {
            throw ValidationException::withMessages(['store_hub_id' => 'Select an active Head Office store.']);
        }
        if (! $receivingHub?->is_head_office) {
            throw ValidationException::withMessages(['store_hub_id' => 'The receiving store must have Head Office Mode enabled.']);
        }

        DB::transaction(function () use ($validated) {
            $products = Product::where('store_hub_id', $validated['store_hub_id'])
                ->whereIn('id', collect($validated['items'])->pluck('product_id'))
                ->lockForUpdate()->get()->keyBy('id');
            $quantities = collect($validated['items'])->groupBy('product_id')
                ->map(fn ($rows) => $rows->sum('quantity'));

            foreach ($quantities as $productId => $quantity) {
                $receivingProduct = $products->get((int) $productId);
                if (! $receivingProduct) {
                    throw ValidationException::withMessages(['items' => 'Select products listed in the receiving Head Office.']);
                }
                $receivingProduct->increment('stock', $quantity);
                InventoryTransaction::create([
                    'type' => 'restock',
                    'reference' => $validated['reference'] ?? null,
                    'store_hub_id' => $receivingProduct->store_hub_id,
                    'product_id' => $receivingProduct->id,
                    'source' => $validated['restock_source'],
                    'quantity' => $quantity,
                    'occurred_on' => $validated['occurred_on'],
                    'notes' => $validated['notes'] ?? null,
                    'created_by' => auth()->id(),
                ]);
            }
        });

        $this->notifyInventoryStaff($validated);

        return redirect()->route('inventory-transactions.index', [
            'hub_id' => $validated['store_hub_id'],
            'restock_saved' => 1,
            'reference' => $validated['reference'],
        ]);
    }

    private function storeReturn(Request $request)
    {
        $validated = $request->validate([
            'type' => ['required', Rule::in(['return'])],
            'store_hub_id' => ['required', 'exists:store_hubs,id'],
            'occurred_on' => ['required', 'date'],
            'channel' => ['required', Rule::in(['shopee', 'lazada', 'tiktok', 'online', 'walk_in', 'wholesale', 'fully_booked'])],
            'source' => ['nullable', 'string', 'max:100'],
            'reference' => ['nullable', 'string', 'max:100'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.product_id' => ['required', 'exists:products,id'],
            'items.*.good_quantity' => ['nullable', 'integer', 'min:0'],
            'items.*.damaged_quantity' => ['nullable', 'integer', 'min:0'],
            'items.*.refund_amount' => ['nullable', 'numeric', 'min:0'],
            'sales_transaction_id' => ['required', 'integer', 'exists:sales_transactions,id'],
            'items.*.transaction_item_id' => ['nullable', 'integer', 'exists:transaction_items,id'],
            'items.*.replacement_id' => ['nullable', 'integer', 'exists:product_replacements,id'],
        ]);
        $hasReturnQuantity = collect($validated['items'])->sum(fn ($item) => (int) ($item['good_quantity'] ?? 0) + (int) ($item['damaged_quantity'] ?? 0)) >= 1;
        if (! $hasReturnQuantity && ($validated['channel'] === 'fully_booked'
            || collect($validated['items'])->sum(fn ($item) => (float) ($item['refund_amount'] ?? 0)) <= 0)) {
            throw ValidationException::withMessages([
                'items' => $validated['channel'] === 'fully_booked'
                    ? 'Enter at least one good or damaged return quantity.'
                    : 'Enter at least one good or damaged return quantity, or a refund amount.',
            ]);
        }

        $user = auth()->user();
        if ($user?->role === 'inventory_staff' && (! $user->store_hub_id || (int) $user->store_hub_id !== (int) $validated['store_hub_id'])) {
            abort(403, 'Inventory staff can only record returns for their designated store.');
        }
        if ($user?->role !== 'admin' && $user?->role !== 'inventory_staff' && ! $user?->canAccessHub((int) $validated['store_hub_id'])) {
            abort(403, 'Unauthorized action for this store hub.');
        }
        if ($user?->role === 'sales_associate' && $this->normalizeChannel($validated['channel']) !== 'walk_in') {
            abort(403, 'Sales associates can only record Walk-In returns.');
        }

        DB::transaction(function () use ($validated) {
            $sale = SalesTransaction::whereKey($validated['sales_transaction_id'])
                ->where('store_hub_id', $validated['store_hub_id'])
                ->lockForUpdate()
                ->firstOrFail();
            $returnChannel = $this->normalizeChannel($sale->channel_type);
            $submittedChannel = $this->normalizeChannel($validated['channel']);
            if ($returnChannel !== $submittedChannel
                || (($validated['channel'] === 'fully_booked') !== (str_replace(['-', ' '], '_', strtolower((string) $sale->channel_type)) === 'fully_booked'))) {
                throw ValidationException::withMessages([
                    'channel' => 'The return channel must match the original sales channel.',
                ]);
            }
            $products = Product::where('store_hub_id', $validated['store_hub_id'])
                ->whereIn('id', collect($validated['items'])->pluck('product_id'))
                ->lockForUpdate()->get()->keyBy('id');

            foreach ($validated['items'] as $item) {
                $product = $products->get((int) $item['product_id']);
                if (! $product) {
                    throw ValidationException::withMessages([
                        'items' => 'Select products listed in the selected store hub.',
                    ]);
                }
                $replacement = ! empty($item['replacement_id'])
                    ? \App\Models\ProductReplacement::whereKey($item['replacement_id'])
                        ->where('transaction_id', $validated['sales_transaction_id'])
                        ->where('status', 'approved')
                        ->where('replacement_product_id', $product->id)
                        ->first()
                    : null;
                $transactionItem = empty($item['transaction_item_id']) ? null : TransactionItem::whereKey($item['transaction_item_id'])
                    ->where('transaction_id', $validated['sales_transaction_id'])
                    ->where('product_id', $product->id)
                    ->first();
                $goodQuantity = (int) ($item['good_quantity'] ?? 0);
                $damagedQuantity = (int) ($item['damaged_quantity'] ?? 0);
                $quantity = $goodQuantity + $damagedQuantity;
                $orderedQuantity = $replacement
                    ? (int) ($replacement->replacement_quantity ?: $replacement->quantity)
                    : (int) ($transactionItem?->quantity ?? 0);
                if ((! $transactionItem && ! $replacement) || $quantity > $orderedQuantity) {
                    throw ValidationException::withMessages([
                        'items' => 'Each returned item must belong to the selected order and cannot exceed its ordered quantity.',
                    ]);
                }
                $unitPrice = $replacement
                    ? (float) $replacement->replacement_unit_price
                    : (float) ($transactionItem?->unit_price ?? 0);
                $discount = $replacement
                    ? (float) ($replacement->replacement_discount_percentage ?? 0)
                    : (float) ($transactionItem?->discount_percentage ?? 0);
                $effectiveUnitPrice = round($unitPrice * (1 - ($discount / 100)), 2);
                $refundQuantity = $quantity > 0 ? $quantity : $orderedQuantity;
                $refundCap = round($effectiveUnitPrice * $refundQuantity, 2);
                $refundAmount = $returnChannel === 'fully_booked' ? 0 : ($quantity > 0
                    ? $refundCap
                    : min((float) ($item['refund_amount'] ?? 0), $refundCap));
                $alreadyReturned = InventoryTransaction::query()
                    ->when($replacement, fn ($query) => $query->where('product_replacement_id', $replacement->id))
                    ->when(! $replacement, fn ($query) => $query->where('transaction_item_id', $transactionItem->id))
                    ->where('type', 'return')
                    ->sum('quantity');
                if ($quantity > $orderedQuantity - (int) $alreadyReturned) {
                    throw ValidationException::withMessages([
                        'items' => 'The return quantity exceeds the remaining quantity for one of the selected order items.',
                    ]);
                }

                if ($quantity > 0) {
                    $product->increment('stock', $quantity);
                }
                if ($goodQuantity > 0 && $returnChannel && ! in_array($returnChannel, ['walk_in', 'fully_booked'], true)) {
                    ProductStockAllocation::firstOrCreate(['product_id' => $product->id])
                        ->increment($returnChannel, $goodQuantity);
                }

                $refundCondition = $goodQuantity > 0 ? 'good' : 'damaged';
                foreach (['good' => $goodQuantity, 'damaged' => $damagedQuantity] as $condition => $conditionQuantity) {
                    if ($conditionQuantity < 1) {
                        continue;
                    }
                    InventoryTransaction::create([
                        'type' => 'return',
                        'reference' => $validated['reference'] ?? null,
                        'sales_transaction_id' => $validated['sales_transaction_id'],
                        'transaction_item_id' => $transactionItem?->id,
                        'product_replacement_id' => $replacement?->id,
                        'store_hub_id' => $product->store_hub_id,
                        'product_id' => $product->id,
                        'channel' => $returnChannel ?? 'fully_booked',
                        'source' => $validated['source'] ?? null,
                        'condition' => $condition,
                        'quantity' => $conditionQuantity,
                        'refund_amount' => $condition === $refundCondition ? $refundAmount : 0,
                        'occurred_on' => $validated['occurred_on'],
                        'notes' => $validated['notes'] ?? null,
                        'created_by' => auth()->id(),
                    ]);
                }
                if ($refundAmount > 0 && $goodQuantity === 0 && $damagedQuantity === 0) {
                    InventoryTransaction::create([
                        'type' => 'return',
                        'reference' => $validated['reference'] ?? null,
                        'sales_transaction_id' => $validated['sales_transaction_id'],
                        'transaction_item_id' => $transactionItem?->id,
                        'product_replacement_id' => $replacement?->id,
                        'store_hub_id' => $product->store_hub_id,
                        'product_id' => $product->id,
                        'channel' => $returnChannel ?? 'fully_booked',
                        'source' => $validated['source'] ?? null,
                        'condition' => null,
                        'quantity' => 0,
                        'refund_amount' => $refundAmount,
                        'occurred_on' => $validated['occurred_on'],
                        'notes' => ($validated['notes'] ?? null).' Refund only.',
                        'created_by' => auth()->id(),
                    ]);
                }
            }
        });

        $this->notifyInventoryStaff($validated);
        $this->notifySalesSubmitter($validated);

        return redirect()->route('inventory-transactions.return.create', ['hub_id' => $validated['store_hub_id']])
            ->with('success', 'Return items recorded successfully.');
    }

    private function notifyInventoryStaff(array $validated): void
    {
        $hub = StoreHub::find($validated['store_hub_id']);
        User::where('role', 'inventory_staff')
            ->where('id', '<>', auth()->id())
            ->get()
            ->each(fn (User $recipient) => $recipient->notify(
                new InventoryWorkflowNotification(
                    'transaction_log',
                    sprintf(
                        '%s recorded a %s transaction for %s.',
                        auth()->user()->name,
                        str_replace('_', ' ', $validated['type']),
                        $hub?->name ?? 'the store hub'
                    ),
                    (int) $validated['store_hub_id'],
                    route('inventory-transactions.index', ['hub_id' => $validated['store_hub_id']])
                )
            ));
    }

    private function notifyBranchTransferReview(array $validated, string $batch): void
    {
        $source = StoreHub::find($validated['store_hub_id']);
        $target = StoreHub::find($validated['target_hub_id']);
        User::whereIn('role', ['admin', 'inventory_staff'])
            ->where('id', '<>', auth()->id())
            ->get()
            ->each(fn (User $recipient) => $recipient->notify(
                new InventoryWorkflowNotification(
                    'branch_transfer_request',
                    sprintf(
                        '%s submitted Transfer Document %s from %s to %s for review.',
                        auth()->user()->name,
                        $validated['reference'] ?? $batch,
                        $source?->name ?? 'a branch',
                        $target?->name ?? 'a branch'
                    ),
                    (int) $validated['store_hub_id'],
                    route('product-file-requests.index')
                )
            ));
    }

    private function notifySalesSubmitter(array $validated): void
    {
        $sale = SalesTransaction::with('user')->find($validated['sales_transaction_id']);
        $submitter = $sale?->user;
        if (! $submitter || (int) $submitter->id === (int) auth()->id()) {
            return;
        }

        $submitter->notify(new InventoryWorkflowNotification(
            'return_recorded',
            sprintf(
                'Return items were recorded for order %s by %s. Good and damaged quantities are now reflected in the sales report.',
                $sale->order_number ?: $sale->id,
                auth()->user()?->name ?? 'Inventory staff'
            ),
            (int) $sale->store_hub_id,
            route('hub.report', ['hub' => $sale->store_hub_id, 'channel' => $this->normalizeChannel($sale->channel_type) ?: 'all'])
        ));
    }

    private function normalizeChannel(?string $channel): ?string
    {
        return match (str_replace(['-', ' '], '_', strtolower(trim((string) $channel)))) {
            'shopee' => 'shopee', 'lazada' => 'lazada', 'tiktok' => 'tiktok',
            'online', 'online_order', 'online_sales', 'event' => 'online', 'walk_in', 'walkin' => 'walk_in',
            'wholesale' => 'wholesale', 'fully_booked' => 'fully_booked',
            default => null,
        };
    }
}
