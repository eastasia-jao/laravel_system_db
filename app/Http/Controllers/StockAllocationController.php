<?php

namespace App\Http\Controllers;

use App\Models\Product;
use App\Models\ProductStockAllocation;
use App\Models\StoreHub;
use App\Models\User;
use App\Notifications\InventoryWorkflowNotification;
use App\Services\StockAllocationSales;
use App\Support\KeywordSearch;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class StockAllocationController extends Controller
{
    private const CHANNELS = ['online', 'wholesale', 'shopee', 'lazada', 'tiktok'];

    public function index(Request $request)
    {
        $filters = $request->validate([
            'sold_from' => ['nullable', 'date_format:Y-m-d', 'required_with:sold_to'],
            'sold_to' => ['nullable', 'date_format:Y-m-d', 'required_with:sold_from', 'after_or_equal:sold_from'],
        ]);
        $soldFrom = $filters['sold_from'] ?? null;
        $soldTo = $filters['sold_to'] ?? null;
        $hasSoldDateRange = $soldFrom !== null && $soldTo !== null;
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
        if ($allocationHubs->count() === 1 && ! $hubId) {
            $hubId = (int) $allocationHubs->first()->id;
        }
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
                    KeywordSearch::apply($nested, $search, ['name', 'item_id', 'barcode']);
                });
            })
            ->when($hasSoldDateRange, function ($builder) use ($soldFrom, $soldTo) {
                $builder->whereIn('products.id', DB::table('transaction_items')
                    ->join('sales_transactions', 'sales_transactions.id', '=', 'transaction_items.transaction_id')
                    ->where('transaction_items.quantity', '>', 0)
                    ->whereDate('sales_transactions.order_date', '>=', $soldFrom)
                    ->whereDate('sales_transactions.order_date', '<=', $soldTo)
                    ->where(function ($query) {
                        $query->whereNull('sales_transactions.status')
                            ->orWhereNotIn('sales_transactions.status', ['cancelled', 'rejected']);
                    })
                    ->select('transaction_items.product_id'));
            })
            ->orderByCatalog('item_id');

        $products = $query->paginate(25)->withQueryString();
        $soldByProduct = app(StockAllocationSales::class)->soldByProduct(
            $products->getCollection()->pluck('id'),
            $soldFrom,
            $soldTo
        );

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
            'soldFrom',
            'soldTo',
            'hasSoldDateRange',
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
            'sold_from' => ['nullable', 'date_format:Y-m-d', 'required_with:sold_to'],
            'sold_to' => ['nullable', 'date_format:Y-m-d', 'required_with:sold_from', 'after_or_equal:sold_from'],
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
            'sold_from' => $validated['sold_from'] ?? null,
            'sold_to' => $validated['sold_to'] ?? null,
            'return_to' => $validated['return_to'] ?? null,
            'return_hub_id' => $validated['return_hub_id'] ?? null,
        ];

        return redirect()->route('stock-allocation.index', array_filter($allocationQuery, fn ($value) => $value !== null && $value !== ''))
            ->with('success', 'Stock allocations updated successfully.');
    }

}
