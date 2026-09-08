<?php

namespace App\Http\Controllers;

use App\Models\InventoryTransaction;
use App\Models\Product;
use App\Models\ProductStockAllocation;
use App\Models\StoreHub;
use App\Models\User;
use App\Notifications\InventoryWorkflowNotification;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class InventoryTransactionController extends Controller
{
    private const TYPES = ['stock_transfer', 'branch_transfer', 'sponsor_workshop', 'restock', 'return'];

    public function index(Request $request)
    {
        $user = auth()->user();
        $hubId = $request->integer('hub_id') ?: $user?->store_hub_id;
        if ($user && ! in_array($user->role, ['admin', 'inventory_staff'], true)) {
            $hubId = $user->store_hub_id;
        }

        $transactions = InventoryTransaction::with(['product', 'storeHub', 'sourceHub', 'targetHub', 'creator'])
            ->when($hubId, fn ($query) => $query->where('store_hub_id', $hubId))
            ->when(
                $user?->role === 'sales_associate',
                fn ($query) => $query->where('type', 'branch_transfer')
            )
            ->when($request->filled('type') && $user?->role !== 'sales_associate', fn ($query) => $query->where('type', $request->input('type')))
            ->when($request->filled('month'), fn ($query) => $query->whereBetween('occurred_on', [
                $request->input('month').'-01',
                now()->parse($request->input('month').'-01')->endOfMonth()->toDateString(),
            ]))
            ->latest('occurred_on')->latest()->paginate(20)->withQueryString();

        $hubs = in_array($user?->role, ['admin', 'inventory_staff'], true)
            ? StoreHub::where('status', 'active')->orderBy('name')->get()
            : StoreHub::whereKey($user?->store_hub_id)->get();

        return view('inventory-transactions.index', compact('transactions', 'hubs', 'hubId'));
    }

    public function create(Request $request)
    {
        $type = $request->route('type') ?? $request->input('type', 'restock');
        abort_unless(in_array($type, self::TYPES, true), 404);

        $user = auth()->user();
        $hubId = $request->integer('hub_id') ?: $user?->store_hub_id;
        $hubs = in_array($user?->role, ['admin', 'inventory_staff'], true)
            ? StoreHub::where('status', 'active')->orderBy('name')->get()
            : StoreHub::whereKey($user?->store_hub_id)->get();
        $allHubs = StoreHub::where('status', 'active')->orderBy('name')->get();
        $headOffices = $allHubs->where('is_head_office', true)->values();
        if ($type === 'branch_transfer') {
            $hubs = $hubs->where('is_head_office', false)->values();
            if ($user?->store_hub_id) {
                $hubs = $hubs->where('id', $user->store_hub_id)->values();
            }
            $allHubs = $allHubs->where('is_head_office', false)->values();
            $hubId = ($hubs->firstWhere('id', $hubId) ?: $hubs->first())?->id;
        }
        if ($type === 'stock_transfer') {
            $hubs = $headOffices;
            $allHubs = $allHubs->where('is_head_office', false)->values();
        }
        if (in_array($type, ['restock', 'stock_transfer'], true)) {
            $selectedHeadOffice = $headOffices->firstWhere('id', $hubId) ?: $headOffices->first();
            $hubId = $selectedHeadOffice?->id;
        } else {
            $hubId = $hubId ?: $hubs->first()?->id;
        }
        $products = Product::where('store_hub_id', $hubId)->where('status', 'active')->orderBy('name')->get();

        $form = $type === 'branch_transfer' ? 'stock_transfer' : $type;
        $transferSourceHubs = $hubs;

        return view('inventory-transactions.forms.'.$form, compact('hubs', 'allHubs', 'headOffices', 'products', 'hubId', 'type', 'transferSourceHubs'));
    }

    public function store(Request $request)
    {
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
            'reference' => ['nullable', 'string', 'max:100'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);

        $user = auth()->user();
        if ($user?->role !== 'admin' && $user?->role !== 'inventory_staff' && $user?->store_hub_id && (int) $user->store_hub_id !== (int) $validated['store_hub_id']) {
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
                    ->where('item_id', $product->item_id)->lockForUpdate()->first();
                if (! $targetProduct) {
                    abort(422, 'The product does not exist in the target store.');
                }
                $targetProduct->increment('stock', $quantity);
            }

            if ($type === 'return' && ($validated['condition'] ?? 'good') === 'good' && ($validated['channel'] ?? null) !== 'fully_booked') {
                $channel = $this->normalizeChannel($validated['channel'] ?? null);
                if ($channel) {
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
        $validated = $request->validate([
            'type' => ['required', Rule::in(['stock_transfer', 'branch_transfer'])],
            'store_hub_id' => ['required', 'exists:store_hubs,id'],
            'target_hub_id' => ['required', 'exists:store_hubs,id', 'different:store_hub_id'],
            'occurred_on' => ['required', 'date'],
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

        DB::transaction(function () use ($validated) {
            if ($validated['type'] === 'stock_transfer') {
                if (! StoreHub::whereKey($validated['store_hub_id'])->where('status', 'active')->where('is_head_office', true)->exists()) {
                    throw ValidationException::withMessages(['store_hub_id' => 'Select an active store with Head Office Mode enabled.']);
                }
                if (! StoreHub::whereKey($validated['target_hub_id'])->where('status', 'active')->where('is_head_office', false)->exists()) {
                    throw ValidationException::withMessages(['target_hub_id' => 'Select an active destination branch.']);
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
                    ->where('item_id', $product->item_id)
                    ->lockForUpdate()
                    ->first();
                if (! $targetProduct) {
                    throw ValidationException::withMessages([
                        'items' => "The product {$product->name} is not listed in the selected target store. Add the product to that branch before transferring.",
                    ]);
                }

                $product->decrement('stock', $quantity);
                $targetProduct->increment('stock', $quantity);
                InventoryTransaction::create([
                    'type' => $validated['type'],
                    'reference' => $validated['reference'] ?? null,
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

        $this->notifyInventoryStaff($validated);

        return redirect()->route('inventory-transactions.index', ['hub_id' => $validated['store_hub_id']])
            ->with('success', 'Stock transfer recorded successfully.');
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

        DB::transaction(function () use ($validated) {
            $products = Product::where('store_hub_id', $validated['store_hub_id'])
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
                        'items' => 'One or more selected products do not belong to the selected store.',
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
                    'reference' => $validated['reference'] ?? null,
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

        return redirect()->route('inventory-transactions.index', ['hub_id' => $validated['store_hub_id']])
            ->with('success', 'Sponsor/workshop items recorded successfully.');
    }

    private function storeRestock(Request $request)
    {
        $validated = $request->validate([
            'type' => ['required', Rule::in(['restock'])],
            'store_hub_id' => ['required', 'exists:store_hubs,id'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.product_id' => ['required', 'exists:products,id'],
            'items.*.quantity' => ['required', 'integer', 'min:1'],
            'occurred_on' => ['required', 'date'],
            'restock_source' => ['required', Rule::in(['warehouse_request', 'stock_transfer'])],
            'source_hub_id' => ['nullable', 'exists:store_hubs,id'],
            'reference' => ['nullable', 'string', 'max:100'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);

        $user = auth()->user();
        if ($user?->role !== 'admin' && $user?->role !== 'inventory_staff' && $user?->store_hub_id && (int) $user->store_hub_id !== (int) $validated['store_hub_id']) {
            abort(403, 'Unauthorized action for this store hub.');
        }
        if ($validated['restock_source'] === 'stock_transfer' && empty($validated['source_hub_id'])) {
            throw ValidationException::withMessages(['source_hub_id' => 'Select the store branch sending the stock.']);
        }
        if ($validated['restock_source'] === 'stock_transfer') {
            $sourceHub = StoreHub::whereKey($validated['source_hub_id'])->first();
            if ($sourceHub?->is_head_office) {
                throw ValidationException::withMessages(['source_hub_id' => 'Head Office cannot be selected as the source branch.']);
            }
        }
        $receivingHub = StoreHub::whereKey($validated['store_hub_id'])->first();
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
                if ($validated['restock_source'] === 'stock_transfer') {
                    $sourceProduct = Product::where('store_hub_id', $validated['source_hub_id'])
                        ->where('item_id', $receivingProduct->item_id)->lockForUpdate()->first();
                    if (! $sourceProduct) {
                        throw ValidationException::withMessages(['items' => "The product {$receivingProduct->name} is not listed in the selected source branch."]);
                    }
                    if ((int) $sourceProduct->stock < (int) $quantity) {
                        throw ValidationException::withMessages(['items' => "The source branch does not have enough stock for {$receivingProduct->name}."]);
                    }
                    $sourceProduct->decrement('stock', $quantity);
                }

                $receivingProduct->increment('stock', $quantity);
                InventoryTransaction::create([
                    'type' => 'restock',
                    'reference' => $validated['reference'] ?? null,
                    'store_hub_id' => $receivingProduct->store_hub_id,
                    'product_id' => $receivingProduct->id,
                    'source_hub_id' => $validated['source_hub_id'] ?? null,
                    'target_hub_id' => $validated['restock_source'] === 'stock_transfer'
                        ? $receivingProduct->store_hub_id
                        : null,
                    'source' => $validated['restock_source'],
                    'quantity' => $quantity,
                    'occurred_on' => $validated['occurred_on'],
                    'notes' => $validated['notes'] ?? null,
                    'created_by' => auth()->id(),
                ]);
            }
        });

        $this->notifyInventoryStaff($validated);

        return redirect()->route('inventory-transactions.index', ['hub_id' => $validated['store_hub_id']])
            ->with('success', 'Added items recorded successfully.');
    }

    private function storeReturn(Request $request)
    {
        $validated = $request->validate([
            'type' => ['required', Rule::in(['return'])],
            'store_hub_id' => ['required', 'exists:store_hubs,id'],
            'occurred_on' => ['required', 'date'],
            'channel' => ['required', Rule::in(['shopee', 'lazada', 'tiktok', 'online', 'wholesale', 'fully_booked'])],
            'source' => ['nullable', 'string', 'max:100'],
            'reference' => ['nullable', 'string', 'max:100'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.product_id' => ['required', 'exists:products,id'],
            'items.*.quantity' => ['required', 'integer', 'min:1'],
            'items.*.condition' => ['required', Rule::in(['good', 'damaged'])],
        ]);

        $user = auth()->user();
        if ($user?->role !== 'admin' && $user?->role !== 'inventory_staff' && $user?->store_hub_id && (int) $user->store_hub_id !== (int) $validated['store_hub_id']) {
            abort(403, 'Unauthorized action for this store hub.');
        }

        DB::transaction(function () use ($validated) {
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

                $quantity = (int) $item['quantity'];
                $product->increment('stock', $quantity);
                if ($item['condition'] === 'good' && $validated['channel'] !== 'fully_booked') {
                    $channel = $this->normalizeChannel($validated['channel']);
                    if ($channel) {
                        ProductStockAllocation::firstOrCreate(['product_id' => $product->id])
                            ->increment($channel, $quantity);
                    }
                }

                InventoryTransaction::create([
                    'type' => 'return',
                    'reference' => $validated['reference'] ?? null,
                    'store_hub_id' => $product->store_hub_id,
                    'product_id' => $product->id,
                    'channel' => $validated['channel'],
                    'source' => $validated['source'] ?? null,
                    'condition' => $item['condition'],
                    'quantity' => $quantity,
                    'occurred_on' => $validated['occurred_on'],
                    'notes' => $validated['notes'] ?? null,
                    'created_by' => auth()->id(),
                ]);
            }
        });

        $this->notifyInventoryStaff($validated);

        return redirect()->route('inventory-transactions.index', ['hub_id' => $validated['store_hub_id']])
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

    private function normalizeChannel(?string $channel): ?string
    {
        return match (str_replace(['-', ' '], '_', strtolower(trim((string) $channel)))) {
            'shopee' => 'shopee', 'lazada' => 'lazada', 'tiktok' => 'tiktok',
            'online', 'online_order', 'walk_in' => 'online', 'wholesale' => 'wholesale',
            default => null,
        };
    }
}
