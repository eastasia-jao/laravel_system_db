<?php

namespace App\Http\Controllers;

use App\Models\EventPullout;
use App\Models\EventPulloutItem;
use App\Models\InventoryTransaction;
use App\Models\SalesTransaction;
use App\Models\StoreHub;
use App\Models\TransactionItem;
use App\Support\AttachmentAvailability;
use App\Support\AttachmentResponse;
use App\Support\AttachmentStorage;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class EventInventoryController extends Controller
{
    public function index(Request $request)
    {
        $validated = $request->validate([
            'hub_id' => ['nullable', 'integer', 'exists:store_hubs,id'],
            'status' => ['nullable', Rule::in(['open', 'closed', 'all'])],
            'section' => ['nullable', Rule::in(['overview', 'transactions'])],
            'event_id' => ['nullable', 'integer', 'exists:event_pullouts,id'],
            'sale_event_id' => ['nullable', 'integer', 'exists:event_pullouts,id'],
            'activity' => ['nullable', Rule::in(['all', 'pullout', 'sale', 'return', 'missing'])],
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date', 'after_or_equal:from'],
            'search' => ['nullable', 'string', 'max:100'],
        ]);
        $hubs = StoreHub::where('status', 'active')->orderBy('name')->get();
        $hubId = (int) ($validated['hub_id'] ?? auth()->user()?->store_hub_id ?? $hubs->first()?->id);
        $status = $validated['status'] ?? 'open';
        $section = $validated['section'] ?? 'overview';
        $activity = $validated['activity'] ?? 'all';

        $events = EventPullout::query()
            ->with([
                'storeHub', 'creator', 'closer', 'items.product',
                'salesTransactions.items',
                'inventoryTransactions' => fn ($query) => $query
                    ->whereIn('type', ['return', 'event_variance'])
                    ->orderBy('id'),
            ])
            ->when($hubId, fn ($query) => $query->where('store_hub_id', $hubId))
            ->when($status !== 'all', fn ($query) => $query->where('status', $status))
            ->when(filled($validated['search'] ?? null), function ($query) use ($validated) {
                $search = trim($validated['search']);
                $query->where(fn ($inner) => $inner
                    ->where('reference', 'like', "%{$search}%")
                    ->orWhere('source', 'like', "%{$search}%"));
            })
            ->latest('occurred_on')
            ->latest('id')
            ->paginate(12)
            ->withQueryString();

        $events->getCollection()->each(function (EventPullout $event) {
            $rows = $this->summaryRows($event);
            $event->setAttribute('summary_rows', $rows);
            $event->setAttribute('product_item_count', $rows->where('pulled_total', '>', 0)->count());
            $event->setAttribute('pulled_total', $rows->sum('pulled_total'));
            $event->setAttribute('sold_total', $rows->sum('sold_total'));
            $event->setAttribute('returned_total', $rows->sum('returned_total'));
            $event->setAttribute('missing_total', $rows->sum('missing_total'));
            $event->setAttribute('remaining_total', $rows->sum('remaining'));
        });

        $eventOptions = EventPullout::query()
            ->when($hubId, fn ($query) => $query->where('store_hub_id', $hubId))
            ->latest('occurred_on')
            ->latest('id')
            ->get(['id', 'reference', 'source', 'occurred_on', 'status']);
        $saleEvents = EventPullout::query()
            ->with(['storeHub', 'items.product', 'salesTransactions.items', 'inventoryTransactions'])
            ->where('status', 'open')
            ->when($hubId, fn ($query) => $query->where('store_hub_id', $hubId))
            ->latest('occurred_on')
            ->latest('id')
            ->get()
            ->each(function (EventPullout $event) {
                $event->setAttribute('sale_lines', $this->saleLines($event));
                $event->setAttribute('sale_order_number', $this->newEventSaleOrderNumber($event));
            });
        $eventTransactionGroups = $section === 'transactions'
            ? $this->eventTransactionGroups($validated, $hubId, $activity, $request)
            : null;

        return view('event-inventory.index', compact(
            'events', 'eventOptions', 'saleEvents', 'eventTransactionGroups', 'hubs', 'hubId',
            'status', 'section', 'activity'
        ));
    }

    public function transactions(Request $request)
    {
        return redirect()->route('event-inventory.index', array_merge(
            $request->query(),
            ['section' => 'transactions']
        ));
    }

    public function createSale(EventPullout $eventPullout)
    {
        if ($eventPullout->status !== 'open') {
            return redirect()->route('event-inventory.index', [
                'hub_id' => $eventPullout->store_hub_id,
                'status' => 'closed',
            ])->with('error', 'Closed events cannot accept additional sales.');
        }

        return redirect()->route('event-inventory.index', [
            'hub_id' => $eventPullout->store_hub_id,
            'sale_event_id' => $eventPullout->id,
        ]);
    }

    public function storeSale(Request $request, EventPullout $eventPullout)
    {
        $request->merge([
            'contact_number' => $this->normalizePhilippineMobileNumber($request->input('contact_number')),
        ]);

        $validated = $request->validate([
            'order_date' => ['required', 'date'],
            'order_number' => ['required', 'string', 'max:100'],
            'customer_name' => ['nullable', 'string', 'max:255'],
            'contact_number' => ['nullable', 'regex:/^\+639\d{9}$/'],
            'address' => ['nullable', 'string', 'max:150'],
            'mode_of_payment' => ['required', Rule::in(['CASH', 'GCASH', 'BDO', 'BPI', 'PAYMAYA', 'OTHERS'])],
            'custom_mop' => ['nullable', 'required_if:mode_of_payment,OTHERS', 'string', 'max:100'],
            'additional_discount_percentage' => ['nullable', 'numeric', 'between:0,100'],
            'note' => ['nullable', 'string', 'max:150'],
            'order_slip_image' => ['nullable', 'image', 'mimes:jpeg,jpg,png,webp', 'max:2048'],
            'item_images' => ['nullable', 'array', 'max:5'],
            'item_images.*' => ['image', 'mimes:jpeg,jpg,png,webp', 'max:2048'],
            'proof_of_payment_image' => ['nullable', 'image', 'mimes:jpeg,jpg,png,webp', 'max:2048'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.event_pullout_item_id' => ['required', 'integer', 'distinct', 'exists:event_pullout_items,id'],
            'items.*.stock_condition' => ['required', Rule::in(['good', 'damaged'])],
            'items.*.quantity' => ['required', 'integer', 'min:1'],
            'items.*.unit_price' => ['required', 'numeric', 'min:0'],
            'items.*.discount_percentage' => ['nullable', 'numeric', 'between:0,100'],
        ]);

        $attachmentPaths = [
            'order_slip' => null,
            'item_images' => [],
            'proof_of_payment' => null,
        ];
        $storedAttachmentPaths = [];
        try {
            if ($request->hasFile('order_slip_image')) {
                $attachmentPaths['order_slip'] = $request->file('order_slip_image')
                    ->store('event_sale_attachments/order_slips', AttachmentStorage::diskName(AttachmentStorage::EVENTS));
                $storedAttachmentPaths[] = $attachmentPaths['order_slip'];
            }
            foreach ($request->file('item_images', []) as $attachment) {
                $path = $attachment->store('event_sale_attachments/items', AttachmentStorage::diskName(AttachmentStorage::EVENTS));
                $attachmentPaths['item_images'][] = $path;
                $storedAttachmentPaths[] = $path;
            }
            if ($request->hasFile('proof_of_payment_image')) {
                $attachmentPaths['proof_of_payment'] = $request->file('proof_of_payment_image')
                    ->store('event_sale_attachments/payment_proofs', AttachmentStorage::diskName(AttachmentStorage::EVENTS));
                $storedAttachmentPaths[] = $attachmentPaths['proof_of_payment'];
            }

            $sale = DB::transaction(function () use ($eventPullout, $validated, $request, $attachmentPaths) {
                $event = EventPullout::query()->lockForUpdate()->findOrFail($eventPullout->id);
                if ($event->status !== 'open') {
                    throw ValidationException::withMessages(['event' => 'Closed events cannot accept additional sales.']);
                }

                $requestedItems = collect($validated['items'])->keyBy('event_pullout_item_id');
                $eventItems = EventPulloutItem::query()
                    ->where('event_pullout_id', $event->id)
                    ->whereIn('id', $requestedItems->keys())
                    ->with('product')
                    ->lockForUpdate()
                    ->get()
                    ->keyBy('id');
                if ($eventItems->count() !== $requestedItems->count()) {
                    throw ValidationException::withMessages(['items' => 'One or more products do not belong to this event.']);
                }

                $soldByLine = InventoryTransaction::query()
                    ->where('event_pullout_id', $event->id)
                    ->where('type', 'sold')
                    ->where('channel', 'event')
                    ->whereIn('event_pullout_item_id', $eventItems->keys())
                    ->selectRaw('event_pullout_item_id, SUM(quantity) as quantity')
                    ->groupBy('event_pullout_item_id')
                    ->pluck('quantity', 'event_pullout_item_id');
                $returnedByProduct = InventoryTransaction::query()
                    ->where('event_pullout_id', $event->id)
                    ->where('type', 'return')
                    ->where('channel', 'event')
                    ->selectRaw('product_id, SUM(quantity) as quantity')
                    ->groupBy('product_id')
                    ->pluck('quantity', 'product_id');
                $soldByProduct = InventoryTransaction::query()
                    ->where('event_pullout_id', $event->id)
                    ->where('type', 'sold')
                    ->where('channel', 'event')
                    ->selectRaw('product_id, SUM(quantity) as quantity')
                    ->groupBy('product_id')
                    ->pluck('quantity', 'product_id');
                $pulledByProduct = EventPulloutItem::query()
                    ->where('event_pullout_id', $event->id)
                    ->selectRaw('product_id, SUM(quantity) as quantity')
                    ->groupBy('product_id')
                    ->pluck('quantity', 'product_id');

                foreach ($requestedItems as $lineId => $item) {
                    $line = $eventItems->get((int) $lineId);
                    $quantity = (int) $item['quantity'];
                    if ($line->stock_condition !== $item['stock_condition']) {
                        throw ValidationException::withMessages([
                            'items' => 'Every selected product must match the chosen stock condition.',
                        ]);
                    }
                    $lineAvailable = (int) $line->quantity - (int) ($soldByLine[$line->id] ?? 0);
                    if ($quantity > $lineAvailable) {
                        throw ValidationException::withMessages([
                            'items' => "Not enough remaining {$line->stock_condition} Event stock for {$line->product->name}.",
                        ]);
                    }
                }
                $requestedItems->groupBy(fn ($item, $lineId) => $eventItems->get((int) $lineId)->product_id, true)
                    ->each(function (Collection $items, $productId) use ($eventItems, $pulledByProduct, $soldByProduct, $returnedByProduct) {
                        $requested = (int) $items->sum(fn ($item) => (int) $item['quantity']);
                        $available = (int) ($pulledByProduct[$productId] ?? 0)
                            - (int) ($soldByProduct[$productId] ?? 0)
                            - (int) ($returnedByProduct[$productId] ?? 0);
                        if ($requested > $available) {
                            $lineId = $items->keys()->first();
                            $productName = $eventItems->get((int) $lineId)?->product?->name ?? 'the selected product';
                            throw ValidationException::withMessages([
                                'items' => "Not enough total Event stock remains for {$productName}.",
                            ]);
                        }
                    });

                $submittedOrderNumber = trim($validated['order_number']);
                $validOrderNumber = preg_match(
                    '/^EVENT-'.preg_quote((string) $event->id, '/').'-\d{14}-[A-Z0-9]{4}$/',
                    $submittedOrderNumber
                ) === 1;
                if (! $validOrderNumber || SalesTransaction::where('order_number', $submittedOrderNumber)->exists()) {
                    throw ValidationException::withMessages([
                        'order_number' => 'The generated Event order number is invalid or already used. Reopen the form and try again.',
                    ]);
                }
                $orderNumber = $submittedOrderNumber;
                $subTotal = $requestedItems->sum(function ($item) use ($eventItems) {
                    $unitPrice = (float) ($eventItems->get((int) $item['event_pullout_item_id'])?->product?->sales_price ?? 0);
                    $gross = (int) $item['quantity'] * $unitPrice;

                    return round($gross * (1 - ((float) ($item['discount_percentage'] ?? 0) / 100)), 2);
                });
                $additionalDiscountPercentage = (float) ($validated['additional_discount_percentage'] ?? 0);
                $grandTotal = round($subTotal * (1 - ($additionalDiscountPercentage / 100)), 2);
                $sale = SalesTransaction::create([
                    'user_id' => $request->user()->id,
                    'store_hub_id' => $event->store_hub_id,
                    'event_pullout_id' => $event->id,
                    'channel_type' => 'event',
                    'order_date' => $validated['order_date'],
                    'order_number' => $orderNumber,
                    'customer_name' => $this->titleCaseCustomerName($validated['customer_name'] ?? null)
                        ?: $event->source.' Customer',
                    'contact_number' => $validated['contact_number'] ?? null,
                    'address' => $validated['address'] ?? null,
                    'mode_of_payment' => $validated['mode_of_payment'] ?? null,
                    'custom_mop' => $validated['custom_mop'] ?? null,
                    'note' => $validated['note'] ?? null,
                    'sub_total' => $subTotal,
                    'additional_discount_percentage' => $additionalDiscountPercentage,
                    'total_amount' => $grandTotal,
                    'grand_total' => $grandTotal,
                    'status' => 'confirmed',
                    'payment_status' => 'paid',
                    'amount_paid' => $grandTotal,
                    'delivery_status' => 'delivered',
                    'event_attachments' => $attachmentPaths,
                ]);

                foreach ($requestedItems as $lineId => $item) {
                    $line = $eventItems->get((int) $lineId);
                    $quantity = (int) $item['quantity'];
                    $unitPrice = (float) ($line->product?->sales_price ?? 0);
                    $discountPercentage = (float) ($item['discount_percentage'] ?? 0);
                    $lineTotal = round(($quantity * $unitPrice) * (1 - ($discountPercentage / 100)), 2);
                    $transactionItem = TransactionItem::create([
                        'transaction_id' => $sale->id,
                        'product_id' => $line->product_id,
                        'quantity' => $quantity,
                        'stock_condition' => $line->stock_condition,
                        'unit_price' => $unitPrice,
                        'discount_percentage' => $discountPercentage,
                        'line_total' => $lineTotal,
                    ]);
                    InventoryTransaction::create([
                        'type' => 'sold',
                        'reference' => $sale->order_number,
                        'sales_transaction_id' => $sale->id,
                        'transaction_item_id' => $transactionItem->id,
                        'event_pullout_id' => $event->id,
                        'event_pullout_item_id' => $line->id,
                        'store_hub_id' => $event->store_hub_id,
                        'product_id' => $line->product_id,
                        'channel' => 'event',
                        'source' => $event->source,
                        'condition' => $line->stock_condition,
                        'quantity' => $quantity,
                        'occurred_on' => $validated['order_date'],
                        'notes' => $validated['note'] ?? null,
                        'created_by' => $request->user()->id,
                    ]);
                }

                return $sale;
            });
        } catch (\Throwable $exception) {
            foreach ($storedAttachmentPaths as $attachmentPath) {
                AttachmentStorage::disk(AttachmentStorage::EVENTS)->delete($attachmentPath);
            }

            throw $exception;
        }

        return redirect()->route('event-inventory.index', [
            'hub_id' => $eventPullout->store_hub_id,
            'status' => 'open',
        ])->with('success', "Event sale {$sale->order_number} recorded successfully.");
    }

    public function saleAttachment(SalesTransaction $transaction, string $type, int $index = 0)
    {
        abort_unless($transaction->channel_type === 'event', 404);
        abort_unless(auth()->user()?->canAccessHub((int) $transaction->store_hub_id), 403);

        $attachments = $transaction->event_attachments ?? [];
        $path = match ($type) {
            'order-slip' => $index === 0 ? ($attachments['order_slip'] ?? null) : null,
            'item-image' => $attachments['item_images'][$index] ?? null,
            'payment-proof' => $index === 0 ? ($attachments['proof_of_payment'] ?? null) : null,
            'image' => $attachments[$index] ?? null,
            default => null,
        };
        abort_unless(is_string($path) && $path !== '', 404);

        $diskName = AttachmentStorage::diskName(AttachmentStorage::EVENTS);
        $disk = Storage::disk($diskName);
        $availability = config("filesystems.disks.{$diskName}.driver") === 's3'
            ? true
            : AttachmentAvailability::check($disk, $path);
        abort_if($availability === null, 503, 'Attachment storage is temporarily unavailable.');
        abort_unless($availability, 404, 'The Event attachment is missing or empty.');

        return AttachmentResponse::inline($disk, $path);
    }

    public function close(Request $request, EventPullout $eventPullout)
    {
        $validated = $request->validate([
            'notes' => ['nullable', 'string', 'max:150'],
        ]);

        $missing = DB::transaction(function () use ($eventPullout, $validated, $request) {
            $event = EventPullout::query()->lockForUpdate()->findOrFail($eventPullout->id);
            if ($event->status !== 'open') {
                throw ValidationException::withMessages(['event' => 'This event is already closed.']);
            }
            $event->load(['items.product', 'salesTransactions.items', 'inventoryTransactions']);
            $rows = $this->summaryRows($event);
            $missing = 0;
            foreach ($rows as $row) {
                $quantity = (int) $row['remaining'];
                if ($quantity < 1) {
                    continue;
                }
                $missing += $quantity;
                InventoryTransaction::create([
                    'type' => 'event_variance',
                    'reference' => $event->reference,
                    'event_pullout_id' => $event->id,
                    'store_hub_id' => $event->store_hub_id,
                    'product_id' => $row['product_id'],
                    'channel' => 'event',
                    'source' => $event->source,
                    'condition' => 'missing',
                    'quantity' => $quantity,
                    'occurred_on' => now()->toDateString(),
                    'notes' => $validated['notes'] ?? 'Recorded automatically during Event closeout.',
                    'created_by' => $request->user()->id,
                ]);
            }
            $event->update([
                'status' => 'closed',
                'closed_by' => $request->user()->id,
                'closed_at' => now(),
            ]);

            return $missing;
        });

        $message = $missing > 0
            ? "Event closed with {$missing} missing item(s) recorded."
            : 'Event closed with no missing items.';

        return redirect()->route('event-inventory.index', [
            'hub_id' => $eventPullout->store_hub_id,
            'status' => 'closed',
        ])->with('success', $message);
    }

    private function summaryRows(EventPullout $event): Collection
    {
        $event->loadMissing(['items.product', 'salesTransactions.items', 'inventoryTransactions']);
        $soldItems = $event->salesTransactions->flatMap->items;
        $returns = $event->inventoryTransactions
            ->where('type', 'return')
            ->where('channel', 'event');
        $variances = $event->inventoryTransactions->where('type', 'event_variance');

        return $event->items->groupBy('product_id')->map(function (Collection $lines, $productId) use ($soldItems, $returns, $variances) {
            $product = $lines->first()->product;
            $productSales = $soldItems->where('product_id', (int) $productId);
            $productReturns = $returns->where('product_id', (int) $productId);
            $pulledGood = (int) $lines->where('stock_condition', 'good')->sum('quantity');
            $pulledDamaged = (int) $lines->where('stock_condition', 'damaged')->sum('quantity');
            $soldGood = (int) $productSales->where('stock_condition', 'good')->sum('quantity');
            $soldDamaged = (int) $productSales->where('stock_condition', 'damaged')->sum('quantity');
            $returnedGood = (int) $productReturns->where('condition', 'good')->sum('quantity');
            $returnedDamaged = (int) $productReturns->where('condition', 'damaged')->sum('quantity');
            $missing = (int) $variances->where('product_id', (int) $productId)->sum('quantity');
            $pulled = $pulledGood + $pulledDamaged;
            $sold = $soldGood + $soldDamaged;
            $returned = $returnedGood + $returnedDamaged;

            return [
                'product_id' => (int) $productId,
                'product' => $product,
                'lines' => $lines,
                'pulled_good' => $pulledGood,
                'pulled_damaged' => $pulledDamaged,
                'pulled_total' => $pulled,
                'sold_good' => $soldGood,
                'sold_damaged' => $soldDamaged,
                'sold_total' => $sold,
                'returned_good' => $returnedGood,
                'returned_damaged' => $returnedDamaged,
                'returned_total' => $returned,
                'remaining_good' => max(0, $pulledGood - $soldGood - $returnedGood),
                'remaining_damaged' => max(0, $pulledDamaged - $soldDamaged - $returnedDamaged),
                'missing_total' => $missing,
                'remaining' => max(0, $pulled - $sold - $returned - $missing),
            ];
        })->sortBy(fn ($row) => $row['product']?->item_id ?? '')->values();
    }

    private function eventTransactionGroups(array $validated, int $hubId, string $activity, Request $request): LengthAwarePaginator
    {
        $typeForActivity = [
            'pullout' => 'sponsor_workshop',
            'sale' => 'sold',
            'return' => 'return',
            'missing' => 'event_variance',
        ];
        $transactions = InventoryTransaction::query()
            ->whereNotNull('event_pullout_id')
            ->with(['eventPullout.storeHub', 'eventPulloutItem', 'product', 'creator', 'salesTransaction', 'transactionItem'])
            ->when($hubId, fn ($query) => $query->where('store_hub_id', $hubId))
            ->when(filled($validated['event_id'] ?? null), fn ($query) => $query->where('event_pullout_id', $validated['event_id']))
            ->when($activity !== 'all', fn ($query) => $query->where('type', $typeForActivity[$activity]))
            ->when(filled($validated['from'] ?? null), fn ($query) => $query->whereDate('occurred_on', '>=', $validated['from']))
            ->when(filled($validated['to'] ?? null), fn ($query) => $query->whereDate('occurred_on', '<=', $validated['to']))
            ->when(filled($validated['search'] ?? null), function ($query) use ($validated) {
                $search = trim($validated['search']);
                $query->where(function ($inner) use ($search) {
                    $inner->where('reference', 'like', "%{$search}%")
                        ->orWhere('source', 'like', "%{$search}%")
                        ->orWhereHas('product', fn ($product) => $product
                            ->where('name', 'like', "%{$search}%")
                            ->orWhere('item_id', 'like', "%{$search}%"))
                        ->orWhereHas('eventPullout', fn ($event) => $event
                            ->where('reference', 'like', "%{$search}%")
                            ->orWhere('source', 'like', "%{$search}%"));
                });
            })
            ->orderByDesc('occurred_on')
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->get();

        $groups = $transactions->groupBy(function (InventoryTransaction $transaction) {
            $transactionKey = match ($transaction->type) {
                'sold' => $transaction->sales_transaction_id ?: $transaction->reference,
                'return' => $transaction->transfer_batch_id ?: $transaction->id,
                default => $transaction->reference,
            };

            return implode('|', [$transaction->event_pullout_id, $transaction->type, $transactionKey]);
        })->map(function (Collection $rows) {
            $first = $rows->first();
            $products = $rows->groupBy('product_id')->map(function (Collection $productRows) {
                $product = $productRows->first()->product;

                return [
                    'product' => $product,
                    'quantity' => (int) $productRows->sum('quantity'),
                    'conditions' => $productRows->groupBy(fn ($row) => $row->condition ?: $row->eventPulloutItem?->stock_condition ?: 'good')
                        ->map(fn (Collection $conditionRows) => (int) $conditionRows->sum('quantity')),
                ];
            })->values();

            return [
                'event' => $first->eventPullout,
                'type' => $first->type,
                'occurred_on' => $first->occurred_on,
                'created_at' => $first->created_at,
                'reference' => $first->salesTransaction?->order_number ?? $first->reference,
                'sale' => $first->salesTransaction,
                'creator' => $first->creator,
                'notes' => $first->notes,
                'products' => $products,
                'quantity' => (int) $products->sum('quantity'),
            ];
        })->values();

        $pageName = 'log_page';
        $page = LengthAwarePaginator::resolveCurrentPage($pageName);
        $perPage = 15;

        return new LengthAwarePaginator(
            $groups->forPage($page, $perPage)->values(),
            $groups->count(),
            $perPage,
            $page,
            [
                'path' => $request->url(),
                'pageName' => $pageName,
                'query' => $request->query(),
            ]
        );
    }

    private function saleLines(EventPullout $event): Collection
    {
        return $this->summaryRows($event)->flatMap(function ($row) {
            return $row['lines']->map(function (EventPulloutItem $line) use ($row) {
                $conditionSold = $line->stock_condition === 'damaged'
                    ? $row['sold_damaged']
                    : $row['sold_good'];
                $conditionReturned = $line->stock_condition === 'damaged'
                    ? $row['returned_damaged']
                    : $row['returned_good'];
                $available = max(0, min(
                    $row['remaining'],
                    (int) $line->quantity - $conditionSold - $conditionReturned
                ));

                return [
                    'id' => $line->id,
                    'product_id' => $line->product_id,
                    'product' => $line->product,
                    'condition' => $line->stock_condition,
                    'available' => $available,
                    'unit_price' => (float) ($line->product?->sales_price ?? 0),
                ];
            });
        })->filter(fn ($line) => $line['available'] > 0)->values();
    }

    private function newEventSaleOrderNumber(EventPullout $event): string
    {
        do {
            $orderNumber = 'EVENT-'.$event->id.'-'.now()->format('YmdHis').'-'.Str::upper(Str::random(4));
        } while (SalesTransaction::where('order_number', $orderNumber)->exists());

        return $orderNumber;
    }

    private function normalizePhilippineMobileNumber(mixed $value): ?string
    {
        $rawValue = trim((string) $value);
        if ($rawValue === '') {
            return null;
        }
        $digits = preg_replace('/\D+/', '', $rawValue);
        if ($digits === '') {
            return $rawValue;
        }

        if (str_starts_with($digits, '63')) {
            $digits = substr($digits, 2);
        }
        if (str_starts_with($digits, '0')) {
            $digits = substr($digits, 1);
        }

        return '+63'.$digits;
    }

    private function titleCaseCustomerName(mixed $value): ?string
    {
        $name = preg_replace('/\s+/u', ' ', trim((string) $value));

        return $name === '' ? null : mb_convert_case($name, MB_CASE_TITLE, 'UTF-8');
    }
}
