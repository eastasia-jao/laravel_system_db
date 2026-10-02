<?php

namespace App\Http\Controllers;

use App\Models\Product;
use App\Models\ProductStockAllocation;
use App\Models\StoreHub;
use App\Models\User;
use App\Notifications\InventoryWorkflowNotification;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class StockAllocationController extends Controller
{
    private const CHANNELS = ['online', 'wholesale', 'shopee', 'lazada', 'tiktok'];

    public function index(Request $request)
    {
        $user = auth()->user();
        $assignedHub = $user?->store_hub_id ? StoreHub::find($user->store_hub_id) : null;
        abort_if($assignedHub && ! $assignedHub->is_head_office, 403, 'Stock allocations are only available for head office stores.');
        $canViewAllHubs = in_array($user?->role, ['admin', 'inventory_staff'], true)
            && (! $assignedHub || $assignedHub->is_head_office);
        $hubId = $request->integer('hub_id') ?: $user?->store_hub_id;

        if (! $canViewAllHubs) {
            $hubId = $user?->store_hub_id ?: 0;
        }

        $allocationHubs = $canViewAllHubs
            ? StoreHub::where('status', 'active')->where('is_head_office', true)->orderBy('name')->get()
            : StoreHub::whereKey($user?->store_hub_id)->where('is_head_office', true)->get();
        if ($hubId && $hubId !== 0) {
            abort_unless($allocationHubs->contains('id', (int) $hubId), 403, 'Stock allocations are only available for head office stores.');
        }
        $hub = $hubId ? $allocationHubs->firstWhere('id', (int) $hubId) : null;
        abort_if($hubId && ! $hub, 404);
        $query = Product::query()
            ->with('stockAllocation')
            ->whereIn('store_hub_id', $hubId ? [(int) $hubId] : $allocationHubs->modelKeys())
            ->when($request->filled('product_id'), fn ($builder) => $builder->whereKey($request->integer('product_id')))
            ->when($request->filled('search'), function ($builder) use ($request) {
                $search = $request->string('search')->toString();
                $builder->whereHas('catalogProduct', function ($nested) use ($search) {
                    $nested->where(function ($nested) use ($search) {
                    $nested->where('name', 'like', "%{$search}%")
                        ->orWhere('item_id', 'like', "%{$search}%")
                        ->orWhere('barcode', 'like', "%{$search}%");
                    });
                });
            })
            ->orderByCatalog('item_id');

        $products = $query->paginate(25)->withQueryString();
        $soldByProduct = $this->soldByProduct($products->getCollection()->pluck('id'));

        $products->getCollection()->transform(function (Product $product) use ($soldByProduct) {
            $allocation = $product->stockAllocation;
            $allocated = collect(self::CHANNELS)->mapWithKeys(
                fn ($channel) => [$channel => (int) ($allocation?->{$channel} ?? 0)]
            );
            $sold = $soldByProduct->get($product->id, collect());
            $product->allocation_values = $allocated;
            $product->sold_values = $sold;
            $product->remaining_values = $allocated;
            $product->starting_inventory = (int) $product->stock;

            return $product;
        });

        $assignedChannels = $user?->role === 'sales_marketing_staff'
            ? collect($user->sales_channels ?? [])->map(fn ($channel) => $this->normalizeChannel($channel))->filter()->values()->all()
            : self::CHANNELS;

        return view('stock-allocation.index', compact(
            'products',
            'allocationHubs',
            'hub',
            'assignedChannels',
        ));
    }

    public function update(Request $request)
    {
        $validated = $request->validate([
            'hub_id' => ['required', 'exists:store_hubs,id'],
            'return_to' => ['nullable', 'in:verification-queue'],
            'return_hub_id' => ['nullable', 'integer', 'exists:store_hubs,id'],
            'product_id' => ['nullable', 'integer', 'exists:products,id'],
            'search' => ['nullable', 'string', 'max:255'],
            'allocations' => ['required', 'array'],
            'allocations.*.online' => ['required', 'integer', 'min:0'],
            'allocations.*.wholesale' => ['required', 'integer', 'min:0'],
            'allocations.*.shopee' => ['required', 'integer', 'min:0'],
            'allocations.*.lazada' => ['required', 'integer', 'min:0'],
            'allocations.*.tiktok' => ['required', 'integer', 'min:0'],
        ]);

        $user = auth()->user();
        if ($user && ! in_array($user->role, ['admin', 'inventory_staff'], true)) {
            abort(403, 'Only inventory staff can update stock allocations.');
        }
        $assignedHub = $user?->store_hub_id ? StoreHub::find($user->store_hub_id) : null;
        $canUpdateAllHubs = ! $assignedHub || $assignedHub->is_head_office;
        $targetHub = StoreHub::findOrFail($validated['hub_id']);
        abort_unless($targetHub->is_head_office, 403, 'Stock allocations are only available for head office stores.');
        if (! $canUpdateAllHubs && (int) $user->store_hub_id !== (int) $validated['hub_id']) {
            abort(403, 'Unauthorized action for this store hub.');
        }

        DB::transaction(function () use ($validated) {
            $products = Product::where('store_hub_id', $validated['hub_id'])
                ->whereIn('id', array_keys($validated['allocations']))
                ->lockForUpdate()
                ->get()
                ->keyBy('id');

            foreach ($validated['allocations'] as $productId => $values) {
                $product = $products->get((int) $productId);
                if (! $product) {
                    continue;
                }

                $total = collect(self::CHANNELS)->sum(fn ($channel) => (int) $values[$channel]);
                if ($total > (int) $product->stock) {
                    abort(422, "The allocation for {$product->name} exceeds its physical stock remaining.");
                }

                ProductStockAllocation::updateOrCreate(
                    ['product_id' => $product->id],
                    collect(self::CHANNELS)->mapWithKeys(fn ($channel) => [$channel => (int) $values[$channel]])->all()
                );
            }
        });

        $hub = StoreHub::find($validated['hub_id']);
        User::whereIn('role', ['admin', 'inventory_staff'])
            ->where('id', '<>', auth()->id())
            ->get()
            ->each(fn (User $recipient) => $recipient->notify(
                new InventoryWorkflowNotification(
                    'stock_allocation',
                    sprintf('%s updated stock allocation for %s.', auth()->user()->name, $hub?->name ?? 'the store hub'),
                    (int) $validated['hub_id'],
                    route('stock-allocation.index', ['hub_id' => $validated['hub_id']])
                )
            ));

        $allocationQuery = [
            'hub_id' => $validated['hub_id'],
            'product_id' => $validated['product_id'] ?? null,
            'search' => $validated['search'] ?? null,
            'return_to' => $validated['return_to'] ?? null,
            'return_hub_id' => $validated['return_hub_id'] ?? null,
        ];

        return redirect()->route('stock-allocation.index', array_filter($allocationQuery, fn ($value) => $value !== null && $value !== ''))
            ->with('success', 'Stock allocations updated successfully.');
    }

    private function soldByProduct($productIds)
    {
        $sales = DB::table('transaction_items')
            ->join('sales_transactions', 'sales_transactions.id', '=', 'transaction_items.transaction_id')
            ->whereIn('transaction_items.product_id', $productIds)
            ->where(function ($query) {
                $query->whereNull('sales_transactions.status')
                    ->orWhereNotIn('sales_transactions.status', ['cancelled', 'rejected']);
            })
            ->select('transaction_items.product_id', 'sales_transactions.channel_type', DB::raw('SUM(transaction_items.quantity) as quantity'))
            ->groupBy('transaction_items.product_id', 'sales_transactions.channel_type')
            ->get()
            ->groupBy('product_id')
            ->map(function ($rows) {
                return $rows->reduce(function ($totals, $row) {
                    $channel = $this->normalizeChannel($row->channel_type);
                    $totals[$channel] = ($totals[$channel] ?? 0) + (int) $row->quantity;

                    return $totals;
                }, collect());
            });

        $movements = DB::table('inventory_transactions')
            ->whereIn('product_id', $productIds)
            ->whereIn('type', ['return', 'replacement_return', 'replacement_out'])
            ->whereNotNull('channel')
            ->select('product_id', 'channel', 'type')
            ->selectRaw('SUM(quantity) AS quantity')
            ->groupBy('product_id', 'channel', 'type')
            ->get();

        foreach ($movements as $movement) {
            $productTotals = $sales->get($movement->product_id, collect());
            $channel = $this->normalizeChannel($movement->channel);
            $quantity = (int) $movement->quantity;
            $current = (int) $productTotals->get($channel, 0);

            $productTotals[$channel] = match ($movement->type) {
                'replacement_out' => $current + $quantity,
                default => max(0, $current - $quantity),
            };
            $sales->put($movement->product_id, $productTotals);
        }

        return $sales;
    }

    private function normalizeChannel(?string $channel): string
    {
        $normalized = str_replace(['-', ' '], '_', strtolower(trim((string) $channel)));

        return match ($normalized) {
            'online_order', 'online_sales', 'event', 'fully_booked' => 'online',
            'walk_in', 'walkin' => 'walk_in',
            'wholesale' => 'wholesale',
            'shopee' => 'shopee',
            'lazada' => 'lazada',
            'tiktok' => 'tiktok',
            default => 'online',
        };
    }
}
