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
        $hubId = $request->integer('hub_id') ?: $user?->store_hub_id;

        if ($user && ! in_array($user->role, ['admin', 'inventory_staff'], true)) {
            $hubId = $user->store_hub_id;
        }

        $hub = $hubId ? StoreHub::findOrFail($hubId) : null;
        $query = Product::query()
            ->with('stockAllocation')
            ->when($hubId, fn ($builder) => $builder->where('store_hub_id', $hubId))
            ->when($request->filled('search'), function ($builder) use ($request) {
                $search = $request->string('search')->toString();
                $builder->where(function ($nested) use ($search) {
                    $nested->where('name', 'like', "%{$search}%")
                        ->orWhere('item_id', 'like', "%{$search}%")
                        ->orWhere('barcode', 'like', "%{$search}%");
                });
            })
            ->orderBy('name');

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
            $product->remaining_values = $allocated->map(
                fn ($quantity, $channel) => max(0, $quantity - (int) $sold->get($channel, 0))
            );
            $product->starting_inventory = (int) $product->stock + $sold->sum();

            return $product;
        });

        $hubs = in_array($user?->role, ['admin', 'inventory_staff'], true)
            ? StoreHub::where('status', 'active')->orderBy('name')->get()
            : StoreHub::whereKey($user?->store_hub_id)->get();
        $assignedChannels = $user?->role === 'sales_marketing_staff'
            ? collect($user->sales_channels ?? [])->map(fn ($channel) => $this->normalizeChannel($channel))->filter()->values()->all()
            : self::CHANNELS;

        return view('stock-allocation.index', compact(
            'products',
            'hubs',
            'hub',
            'assignedChannels',
        ));
    }

    public function update(Request $request)
    {
        $validated = $request->validate([
            'hub_id' => ['required', 'exists:store_hubs,id'],
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
        if ($user?->role !== 'admin' && $user?->role !== 'inventory_staff' && $user?->store_hub_id && (int) $user->store_hub_id !== (int) $validated['hub_id']) {
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
                $sold = $this->soldByProduct(collect([$product->id]))
                    ->get($product->id, collect())
                    ->sum();
                if ($total > ((int) $product->stock + $sold)) {
                    abort(422, "The allocation for {$product->name} exceeds its starting inventory.");
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

        return redirect()->route('stock-allocation.index', ['hub_id' => $validated['hub_id']])
            ->with('success', 'Stock allocations updated successfully.');
    }

    private function soldByProduct($productIds)
    {
        return DB::table('transaction_items')
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
    }

    private function normalizeChannel(?string $channel): string
    {
        $normalized = str_replace(['-', ' '], '_', strtolower(trim((string) $channel)));

        return match ($normalized) {
            'online_order', 'online_sales', 'walk_in', 'event', 'fully_booked' => 'online',
            'wholesale' => 'wholesale',
            'shopee' => 'shopee',
            'lazada' => 'lazada',
            'tiktok' => 'tiktok',
            default => 'online',
        };
    }
}
