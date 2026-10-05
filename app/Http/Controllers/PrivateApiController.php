<?php

namespace App\Http\Controllers;

use App\Models\Product;
use App\Models\ProductFileRequest;
use App\Models\StoreHub;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class PrivateApiController extends Controller
{
    private const PRODUCT_SEARCH_FIELDS = [
        'all',
        'name',
        'item_id',
        'barcode',
        'brand',
        'retail_group',
        'retail_department',
        'unit_type',
    ];

    public function me(Request $request)
    {
        $user = $request->user();
        $hubIds = $this->accessibleHubIds($user);

        return response()->json([
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'username' => $user->username,
                'email' => $user->email,
                'role' => $user->role,
                'hub_id' => $user->hub_id,
                'sales_channels' => $user->sales_channels ?? [],
            ],
            'permissions' => [
                'view_products' => $user->can('view-products'),
                'manage_inventory' => $user->can('manage-inventory'),
                'manage_stock_allocation' => $user->can('manage-stock-allocation'),
                'view_transaction_logs' => $user->can('view-transaction-logs'),
            ],
            'hubs' => StoreHub::query()
                ->when(! in_array($user->role, ['admin', 'inventory_staff'], true), fn ($query) => $query->whereIn('id', $hubIds))
                ->orderBy('name')
                ->get(['id', 'name', 'code', 'status', 'is_head_office'])
                ->map(fn (StoreHub $hub) => $this->hubResource($hub))
                ->values(),
        ]);
    }

    public function products(Request $request)
    {
        abort_unless($request->user()?->can('view-products'), 403);

        $validated = $request->validate([
            'hub_id' => ['nullable', 'integer', 'exists:store_hubs,id'],
            'search' => ['nullable', 'string', 'max:255'],
            'field' => ['nullable', Rule::in(self::PRODUCT_SEARCH_FIELDS)],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);

        $query = $this->scopedProductQuery($request);
        $this->applyProductSearch($query, $validated['search'] ?? '', $validated['field'] ?? 'all');

        $products = $query
            ->with(['stockAllocation', 'storeHub'])
            ->orderByCatalog('item_id')
            ->paginate($validated['per_page'] ?? 25)
            ->appends($request->query());

        return response()->json([
            'data' => $products->getCollection()->map(fn (Product $product) => $this->productResource($product))->values(),
            'meta' => [
                'current_page' => $products->currentPage(),
                'last_page' => $products->lastPage(),
                'per_page' => $products->perPage(),
                'total' => $products->total(),
            ],
        ]);
    }

    public function productSearch(Request $request)
    {
        abort_unless($request->user()?->can('view-products'), 403);

        $validated = $request->validate([
            'hub_id' => ['required', 'integer', 'exists:store_hubs,id'],
            'q' => ['nullable', 'string', 'max:255'],
            'field' => ['nullable', Rule::in(self::PRODUCT_SEARCH_FIELDS)],
            'active_only' => ['nullable', 'boolean'],
            'limit' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);

        $request->merge(['hub_id' => $validated['hub_id']]);
        $query = $this->scopedProductQuery($request);

        if ($request->boolean('active_only')) {
            $query->where('status', 'active');
        }

        $this->applyProductSearch($query, $validated['q'] ?? '', $validated['field'] ?? 'all');

        return response()->json([
            'data' => $query
                ->with(['stockAllocation', 'storeHub'])
                ->orderByCatalog('item_id')
                ->limit($validated['limit'] ?? 25)
                ->get()
                ->map(fn (Product $product) => $this->productResource($product))
                ->values(),
        ]);
    }

    public function importStatus(Request $request, int $hubId)
    {
        abort_unless($request->user()?->can('manage-inventory'), 403);
        $hub = StoreHub::findOrFail($hubId);

        $record = ProductFileRequest::query()
            ->where('store_hub_id', $hub->id)
            ->where('type', 'import')
            ->latest('id')
            ->first();

        return response()->json([
            'import' => $record ? $this->importResource($record) : null,
        ]);
    }

    public function notifications(Request $request)
    {
        $validated = $request->validate([
            'per_page' => ['nullable', 'integer', 'min:1', 'max:50'],
        ]);

        $notifications = $request->user()
            ->notifications()
            ->latest()
            ->paginate($validated['per_page'] ?? 10);

        return response()->json([
            'unread_count' => $request->user()->unreadNotifications()->count(),
            'data' => $notifications->getCollection()
                ->map(fn ($notification) => $this->notificationResource($notification))
                ->values(),
            'meta' => [
                'current_page' => $notifications->currentPage(),
                'last_page' => $notifications->lastPage(),
                'per_page' => $notifications->perPage(),
                'total' => $notifications->total(),
            ],
        ]);
    }

    public function readNotification(Request $request, string $notification)
    {
        $record = $request->user()->notifications()->whereKey($notification)->firstOrFail();
        $record->markAsRead();

        return response()->json([
            'notification' => $this->notificationResource($record->fresh()),
            'unread_count' => $request->user()->unreadNotifications()->count(),
        ]);
    }

    public function readAllNotifications(Request $request)
    {
        $request->user()->unreadNotifications->markAsRead();

        return response()->json([
            'unread_count' => 0,
        ]);
    }

    private function scopedProductQuery(Request $request)
    {
        $user = $request->user();
        $hubId = $request->integer('hub_id') ?: null;
        $query = Product::query();

        if (in_array($user->role, ['admin', 'inventory_staff'], true)) {
            return $hubId ? $query->where('store_hub_id', $hubId) : $query;
        }

        $allowedHubIds = $this->accessibleHubIds($user);
        if ($hubId) {
            abort_unless(in_array($hubId, $allowedHubIds, true), 403);

            return $query->where('store_hub_id', $hubId);
        }

        abort_unless(! empty($allowedHubIds), 403);

        return $query->whereIn('store_hub_id', $allowedHubIds);
    }

    private function applyProductSearch($query, ?string $search, string $field): void
    {
        $search = trim((string) $search);
        if ($search === '') {
            return;
        }

        if ($field === 'all') {
            $query->whereHas('catalogProduct', function ($catalog) use ($search) {
                foreach (preg_split('/\s+/', $search, -1, PREG_SPLIT_NO_EMPTY) as $word) {
                    $catalog->where(fn ($builder) => $builder
                        ->where('name', 'like', "%{$word}%")
                        ->orWhere('item_id', 'like', "%{$word}%")
                        ->orWhere('barcode', 'like', "%{$word}%")
                        ->orWhere('brand', 'like', "%{$word}%"));
                }
            });

            return;
        }

        if ($field === 'item_id' && ctype_digit($search)) {
            $query->where(function ($builder) use ($search) {
                $builder->whereCatalog('item_id', 'LIKE', "%{$search}%")
                    ->orWhere('products.id', (int) $search);
            });

            return;
        }

        $query->whereCatalog($field, 'LIKE', "%{$search}%");
    }

    private function accessibleHubIds($user): array
    {
        if (in_array($user->role, ['admin', 'inventory_staff'], true)) {
            return StoreHub::query()->pluck('id')->map(fn ($id) => (int) $id)->all();
        }

        return $user->accessibleStoreHubIds();
    }

    private function productResource(Product $product): array
    {
        return [
            'id' => $product->id,
            'hub_id' => $product->store_hub_id,
            'hub_name' => $product->storeHub?->name,
            'item_id' => $product->item_id,
            'name' => $product->name,
            'description' => $product->description,
            'barcode' => $product->barcode,
            'brand' => $product->brand,
            'retail_group' => $product->retail_group,
            'retail_department' => $product->retail_department,
            'unit_type' => $product->unit_type,
            'stock' => (int) $product->stock,
            'status' => $product->status,
            'prices' => [
                'cost' => $this->decimal($product->cost_price),
                'retail' => $this->decimal($product->sales_price),
                'wholesale' => $this->decimal($product->wholesale_price),
                'shopee' => $this->decimal($product->shopee_price),
                'lazada' => $this->decimal($product->lazada_price),
                'tiktok' => $this->decimal($product->tiktok_price),
            ],
            'updated_at' => optional($product->updated_at)->toIso8601String(),
        ];
    }

    private function hubResource(StoreHub $hub): array
    {
        return [
            'id' => $hub->id,
            'name' => $hub->name,
            'code' => $hub->code,
            'status' => $hub->status,
            'is_head_office' => (bool) $hub->is_head_office,
        ];
    }

    private function importResource(ProductFileRequest $record): array
    {
        $status = $record->processing_status ?: match ($record->status) {
            'approved' => 'completed',
            'rejected' => 'failed',
            default => 'queued',
        };

        return [
            'id' => $record->id,
            'status' => $status,
            'file_name' => $record->file_name,
            'total_rows' => $record->total_rows,
            'created_count' => $record->created_count,
            'updated_count' => $record->updated_count,
            'skipped_count' => $record->skipped_count,
            'error' => $record->processing_error,
            'updated_at' => optional($record->updated_at)->toIso8601String(),
        ];
    }

    private function notificationResource($notification): array
    {
        $data = $notification->data ?? [];

        return [
            'id' => $notification->id,
            'event' => $data['event'] ?? null,
            'title' => $data['title'] ?? null,
            'message' => $data['message'] ?? '',
            'url' => $data['url'] ?? null,
            'hub_id' => $data['hub_id'] ?? null,
            'read_at' => optional($notification->read_at)->toIso8601String(),
            'created_at' => optional($notification->created_at)->toIso8601String(),
        ];
    }

    private function decimal($value): ?float
    {
        return $value === null ? null : (float) $value;
    }
}
