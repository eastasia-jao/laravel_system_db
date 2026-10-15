<?php

namespace App\Http\Controllers;

use App\Models\EventPullout;
use App\Models\EventPulloutItem;
use App\Models\InventoryTransaction;
use App\Models\SalesTransaction;
use App\Models\StoreHub;
use App\Models\TransactionItem;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
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
            'search' => ['nullable', 'string', 'max:100'],
        ]);
        $hubs = StoreHub::where('status', 'active')->orderBy('name')->get();
        $hubId = (int) ($validated['hub_id'] ?? auth()->user()?->store_hub_id ?? $hubs->first()?->id);
        $status = $validated['status'] ?? 'open';

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
            $event->setAttribute('pulled_total', $rows->sum('pulled_total'));
            $event->setAttribute('sold_total', $rows->sum('sold_total'));
            $event->setAttribute('returned_total', $rows->sum('returned_total'));
            $event->setAttribute('missing_total', $rows->sum('missing_total'));
            $event->setAttribute('remaining_total', $rows->sum('remaining'));
        });

        return view('event-inventory.index', compact('events', 'hubs', 'hubId', 'status'));
    }

    public function storeSale(Request $request, EventPullout $eventPullout)
    {
        $validated = $request->validate([
            'order_date' => ['required', 'date'],
            'customer_name' => ['nullable', 'string', 'max:255'],
            'mode_of_payment' => ['nullable', 'string', 'max:100'],
            'note' => ['nullable', 'string', 'max:2000'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.event_pullout_item_id' => ['required', 'integer', 'distinct', 'exists:event_pullout_items,id'],
            'items.*.quantity' => ['nullable', 'integer', 'min:0'],
            'items.*.unit_price' => ['required', 'numeric', 'min:0'],
        ]);

        $sale = DB::transaction(function () use ($eventPullout, $validated, $request) {
            $event = EventPullout::query()->lockForUpdate()->findOrFail($eventPullout->id);
            if ($event->status !== 'open') {
                throw ValidationException::withMessages(['event' => 'Closed events cannot accept additional sales.']);
            }

            $requestedItems = collect($validated['items'])
                ->filter(fn ($item) => (int) ($item['quantity'] ?? 0) > 0)
                ->keyBy('event_pullout_item_id');
            if ($requestedItems->isEmpty()) {
                throw ValidationException::withMessages(['items' => 'Enter a quantity for at least one Event item.']);
            }
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
                $lineAvailable = (int) $line->quantity - (int) ($soldByLine[$line->id] ?? 0);
                $productAvailable = (int) ($pulledByProduct[$line->product_id] ?? 0)
                    - (int) ($soldByProduct[$line->product_id] ?? 0)
                    - (int) ($returnedByProduct[$line->product_id] ?? 0);
                if ($quantity > min($lineAvailable, $productAvailable)) {
                    throw ValidationException::withMessages([
                        'items' => "Not enough remaining {$line->stock_condition} Event stock for {$line->product->name}.",
                    ]);
                }
            }

            $orderNumber = 'EVENT-'.$event->id.'-'.now()->format('YmdHis').'-'.Str::upper(Str::random(4));
            $subTotal = $requestedItems->sum(fn ($item) => (int) $item['quantity'] * (float) $item['unit_price']);
            $sale = SalesTransaction::create([
                'user_id' => $request->user()->id,
                'store_hub_id' => $event->store_hub_id,
                'event_pullout_id' => $event->id,
                'channel_type' => 'event',
                'order_date' => $validated['order_date'],
                'order_number' => $orderNumber,
                'customer_name' => trim((string) ($validated['customer_name'] ?? '')) ?: $event->source.' customer',
                'mode_of_payment' => $validated['mode_of_payment'] ?? null,
                'note' => $validated['note'] ?? null,
                'sub_total' => $subTotal,
                'total_amount' => $subTotal,
                'grand_total' => $subTotal,
                'status' => 'confirmed',
                'payment_status' => 'paid',
                'amount_paid' => $subTotal,
                'delivery_status' => 'delivered',
            ]);

            foreach ($requestedItems as $lineId => $item) {
                $line = $eventItems->get((int) $lineId);
                $quantity = (int) $item['quantity'];
                $unitPrice = (float) $item['unit_price'];
                $transactionItem = TransactionItem::create([
                    'transaction_id' => $sale->id,
                    'product_id' => $line->product_id,
                    'quantity' => $quantity,
                    'stock_condition' => $line->stock_condition,
                    'unit_price' => $unitPrice,
                    'discount_percentage' => 0,
                    'line_total' => $quantity * $unitPrice,
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

        return redirect()->route('event-inventory.index', [
            'hub_id' => $eventPullout->store_hub_id,
            'status' => 'open',
        ])->with('success', "Event sale {$sale->order_number} recorded successfully.");
    }

    public function close(Request $request, EventPullout $eventPullout)
    {
        $validated = $request->validate([
            'notes' => ['nullable', 'string', 'max:2000'],
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
                'missing_total' => $missing,
                'remaining' => max(0, $pulled - $sold - $returned - $missing),
            ];
        })->sortBy(fn ($row) => $row['product']?->item_id ?? '')->values();
    }
}
