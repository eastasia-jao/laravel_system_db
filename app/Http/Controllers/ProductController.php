<?php

namespace App\Http\Controllers;

use App\Jobs\ProcessProductFileRequest;
use App\Models\Brand;
use App\Models\Department;
use App\Models\Group;
use App\Models\InventoryTransaction;
use App\Models\Product;
use App\Models\ProductFileRequest;
use App\Models\StaffActivityLog;
use App\Models\StaffActivityLogItem;
use App\Models\StoreHub;
use App\Models\UnitType;
use App\Models\User;
use App\Notifications\InventoryWorkflowNotification;
use App\Support\CsvIdentifier;
use App\Support\KeywordSearch;
use App\Support\ProductExportFilename;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class ProductController extends Controller
{
    use AuthorizesRequests;

    private const SALES_STOCK_CHANNELS = [
        'walk_in' => 'Walk-In Physical Stock',
        'online' => 'Online / Event / Restock',
        'wholesale' => 'Wholesale',
        'shopee' => 'Shopee',
        'lazada' => 'Lazada',
        'tiktok' => 'TikTok',
    ];

    public function index(Request $request)
    {
        $user = auth()->user();
        $isSalesAssociate = $user?->role === 'sales_associate';
        $salesAssociateHubs = $isSalesAssociate
            ? StoreHub::whereIn('id', $user->accessibleStoreHubIds())->orderBy('name')->get()
            : collect();
        $productHubs = $isSalesAssociate
            ? $salesAssociateHubs
            : (($user && ! in_array($user->role, ['admin', 'inventory_staff'], true))
                ? StoreHub::whereKey($user->store_hub_id)->get()
                : StoreHub::orderByDesc('is_head_office')->orderBy('name')->get());
        $selectedHubId = $request->query('hub_id');
        $displayChannelOptions = $user?->role === 'sales_marketing_staff'
            ? collect($user->sales_channels ?? [])
                ->map(fn ($channel) => $this->normalizeSalesChannel($channel))
                ->filter(fn ($channel) => array_key_exists($channel, self::SALES_STOCK_CHANNELS))
                ->unique()
                ->values()
            : collect();
        $displayChannel = null;
        if ($user?->role === 'sales_marketing_staff' && $displayChannelOptions->isNotEmpty()) {
            $requestedChannel = $request->query('channel');
            if ($displayChannelOptions->count() > 1
                && $requestedChannel !== null
                && ! $displayChannelOptions->contains($requestedChannel)) {
                abort(403, 'You are not assigned to view stock for this sales channel.');
            }
            $displayChannel = $displayChannelOptions->count() > 1
                ? ($requestedChannel ?: $displayChannelOptions->first())
                : $displayChannelOptions->first();
        }
        $displayChannelLabel = $displayChannel
            ? self::SALES_STOCK_CHANNELS[$displayChannel]
            : 'Available Stock';

        if ($isSalesAssociate) {
            $requestedHubId = $request->query('hub_id');
            $selectedHubId = $requestedHubId !== null && $salesAssociateHubs->contains('id', (int) $requestedHubId)
                ? (int) $requestedHubId
                : $user->store_hub_id;
        } elseif ($user && ! in_array($user->role, ['admin', 'inventory_staff'], true)) {
            $selectedHubId = $user->store_hub_id;
        } else {
            $defaultHub = $user?->role === 'inventory_staff'
                ? $productHubs->firstWhere('id', (int) $user->hub_id)
                : null;
            $selectedHubId = $productHubs->firstWhere('id', (int) $selectedHubId)?->id
                ?? $defaultHub?->id
                ?? $productHubs->first()?->id;
        }

        $selectedHub = $selectedHubId ? StoreHub::find($selectedHubId) : null;
        $query = Product::query()->with('stockAllocation');
        if (! in_array($user->role, ['admin', 'inventory_staff'], true) && ! $selectedHubId) {
            $query->whereRaw('1 = 0');
        }

        if ($selectedHubId) {
            $query->where('store_hub_id', $selectedHubId);
        }

        if ($request->filled('search')) {
            $search = $request->input('search');
            $field = $request->input('field', 'all');

            if ($field === 'all') {
                $query->whereHas('catalogProduct', function ($catalog) use ($search) {
                    KeywordSearch::apply($catalog, $search, ['name', 'item_id', 'barcode', 'brand']);
                });
            } else {
                $searchableField = in_array($field, ['item_id', 'barcode', 'brand', 'retail_group', 'retail_department', 'unit_type'], true)
                    ? $field
                    : 'name';
                $query->whereHas('catalogProduct', fn ($catalog) => KeywordSearch::apply($catalog, $search, [$searchableField]));
            }
        }

        $products = $query->orderByCatalog('item_id')->paginate(10)->appends($request->query());
        $productIds = $products->getCollection()->pluck('id');
        $channelAliases = $displayChannel ? $this->salesChannelAliases($displayChannel) : [];
        $soldByProduct = DB::table('transaction_items')
            ->join('sales_transactions', 'sales_transactions.id', '=', 'transaction_items.transaction_id')
            ->whereIn('transaction_items.product_id', $productIds)
            ->when($displayChannel, function ($query) use ($displayChannel) {
                $aliases = $this->salesChannelAliases($displayChannel);
                $query->whereRaw(
                    'LOWER(REPLACE(REPLACE(sales_transactions.channel_type, ?, ?), ?, ?)) IN ('.implode(',', array_fill(0, count($aliases), '?')).')',
                    ['-', '_', ' ', '_', ...$aliases]
                );
            }, function ($query) {
                $query->whereRaw('1 = 0');
            })
            ->where(function ($query) {
                $query->whereNull('sales_transactions.status')
                    ->orWhereNotIn('sales_transactions.status', ['cancelled', 'rejected']);
            })
            ->select('transaction_items.product_id')->selectRaw('SUM(transaction_items.quantity) AS quantity')
            ->groupBy('transaction_items.product_id')->pluck('quantity', 'transaction_items.product_id');
        $channelMovements = $displayChannel
            ? DB::table('inventory_transactions')
                ->whereIn('product_id', $productIds)
                ->whereIn('type', ['return', 'replacement_return', 'replacement_out'])
                ->whereRaw(
                    'LOWER(REPLACE(REPLACE(channel, ?, ?), ?, ?)) IN ('.implode(',', array_fill(0, count($channelAliases), '?')).')',
                    ['-', '_', ' ', '_', ...$channelAliases]
                )
                ->select('product_id', 'type')
                ->selectRaw('SUM(quantity) AS quantity')
                ->groupBy('product_id', 'type')
                ->get()
                ->groupBy('product_id')
            : collect();
        $products->getCollection()->each(function (Product $product) use ($displayChannel, $soldByProduct, $channelMovements) {
            $allocated = $displayChannel === 'walk_in'
                ? $product->unallocatedStock()
                : (int) ($displayChannel ? ($product->stockAllocation?->{$displayChannel} ?? 0) : 0);
            $movements = $channelMovements->get($product->id, collect())->keyBy('type');
            $returned = (int) ($movements->get('return')->quantity ?? 0)
                + (int) ($movements->get('replacement_return')->quantity ?? 0);
            $replacementOut = (int) ($movements->get('replacement_out')->quantity ?? 0);
            $used = max(0, (int) ($soldByProduct[$product->id] ?? 0) - $returned + $replacementOut);
            $product->setAttribute(
                'allocated_available_stock',
                max(0, $allocated - ($displayChannel === 'walk_in' ? 0 : $used))
            );
        });

        $brands = Brand::all();
        $groups = DB::table('groups')->get();
        $departments = DB::table('departments')->get();
        $unitTypes = UnitType::all();
        $baseUnits = $unitTypes;

        return view('products.index', compact(
            'products', 'productHubs', 'selectedHub', 'brands', 'groups', 'departments', 'unitTypes', 'baseUnits',
            'displayChannel', 'displayChannelLabel', 'displayChannelOptions', 'isSalesAssociate'
        ));
    }

    private function normalizeSalesChannel(?string $channel): string
    {
        return strtolower(str_replace(['-', ' '], '_', trim((string) $channel)));
    }

    private function salesChannelAliases(string $channel): array
    {
        return match ($channel) {
            'online' => ['online', 'online_order', 'online_sales', 'event', 'restock', 'fully_booked'],
            default => [$channel],
        };
    }

    public function store(Request $request, $hub_id)
    {
        $user = auth()->user();
        if ($user && $user->role !== 'admin' && $user->role !== 'inventory_staff' && $user->store_hub_id && $user->store_hub_id != $hub_id) {
            abort(403, 'Unauthorized action for this store hub.');
        }

        $validated = $request->validate([
            'item_id' => [
                'required',
                'string',
                'max:255',
                function ($attribute, $value, $fail) use ($hub_id) {
                    if (Product::where('store_hub_id', $hub_id)->whereCatalog('item_id', $value)->exists()) {
                        $fail('This Item ID is already listed in this branch.');
                    }
                },
            ],
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'barcode' => ['nullable', 'string', 'max:255'],
            'brand' => ['nullable', 'string', 'max:255'],
            'retail_group' => ['nullable', 'string', 'max:255'],
            'retail_department' => ['nullable', 'string', 'max:255'],
            'unit_type' => ['nullable', 'string', 'max:255'],
            'cost_price' => ['nullable', 'numeric', 'min:0'],
            'sales_price' => ['nullable', 'numeric', 'min:0'],
            'wholesale_price' => ['nullable', 'numeric', 'min:0'],
            'shopee_price' => ['nullable', 'numeric', 'min:0'],
            'lazada_price' => ['nullable', 'numeric', 'min:0'],
            'tiktok_price' => ['nullable', 'numeric', 'min:0'],
        ]);

        Product::create([
            ...$validated,
            'status' => 'active',
            'store_hub_id' => $hub_id,
            'stock' => 0,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return redirect()->route('hub.dashboard', $hub_id)->with('success', 'Product added successfully!');
    }

    public function update(Request $request, $id)
    {
        $product = Product::findOrFail($id);
        $user = auth()->user();
        if ($user && $user->role !== 'admin' && $user->role !== 'inventory_staff' && $user->store_hub_id && $product->store_hub_id != $user->store_hub_id) {
            abort(403, 'Unauthorized action.');
        }

        $validated = $request->validate([
            'item_id' => [
                'required',
                'string',
                'max:255',
                function ($attribute, $value, $fail) use ($product) {
                    if (Product::where('store_hub_id', $product->store_hub_id)->whereKeyNot($product->id)->whereCatalog('item_id', $value)->exists()) {
                        $fail('This Item ID is already listed in this branch.');
                    }
                },
                Rule::unique('catalog_products', 'item_id')->ignore($product->catalog_product_id),
            ],
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'barcode' => 'nullable|string|max:255',
            'brand' => 'nullable|string|max:255',
            'retail_group' => 'nullable|string|max:255',
            'retail_department' => 'nullable|string|max:255',
            'unit_type' => 'nullable|string|max:255',
            'cost_price' => 'nullable|numeric|min:0',
            'sales_price' => 'nullable|numeric|min:0',
        ]);

        $product->update($validated);

        return redirect()->back()->with('success', 'Product updated successfully.');
    }

    public function toggleStatus($id)
    {
        $product = Product::findOrFail($id);
        $user = auth()->user();
        if ($user && $user->role !== 'admin' && $user->role !== 'inventory_staff' && $user->store_hub_id && $product->store_hub_id != $user->store_hub_id) {
            abort(403, 'Unauthorized action.');
        }

        $product->status = ($product->status === 'active') ? 'inactive' : 'active';
        $product->save();

        return back()->with('success', 'Status updated successfully!');
    }

    public function destroy($id)
    {
        $this->authorize('full-access');
        $product = Product::findOrFail($id);
        $user = auth()->user();
        if ($user && $user->role !== 'admin' && $user->role !== 'inventory_staff' && $user->store_hub_id && $product->store_hub_id != $user->store_hub_id) {
            abort(403, 'Unauthorized action.');
        }

        $product->delete();

        return redirect()->back()->with('success', 'Product deleted.');
    }

    public function addStock(Request $request)
    {
        $request->validate([
            'product_id' => 'required|exists:products,id',
            'quantity' => 'required|integer|min:1',
        ]);

        $product = Product::findOrFail($request->product_id);
        $user = auth()->user();
        if ($user && $user->role !== 'admin' && $user->role !== 'inventory_staff' && $user->store_hub_id && $product->store_hub_id != $user->store_hub_id) {
            abort(403, 'Unauthorized action.');
        }

        $product->increment('stock', $request->quantity);
        InventoryTransaction::create([
            'type' => 'restock',
            'store_hub_id' => $product->store_hub_id,
            'product_id' => $product->id,
            'source' => 'Manual stock addition',
            'quantity' => $request->quantity,
            'occurred_on' => now()->toDateString(),
            'created_by' => auth()->id(),
        ]);

        return back()->with('success', 'Successfully added '.$request->quantity.' to '.$product->name);
    }

    public function create()
    {
        return redirect()->back();
    }

    public function importCsv(Request $request, $hub_id)
    {
        abort_unless(auth()->user()?->can('manage-inventory'), 403);
        $hub = StoreHub::findOrFail($hub_id);
        $request->validate(['file' => 'required|file|mimes:csv,txt|max:5120']);
        $csv = app(ProductFileRequestController::class)->prepareCsv(
            file_get_contents($request->file('file')->getRealPath())
        );

        DB::transaction(function () use ($csv, $request, $hub) {
            // This record is internal queue storage, not an approval request.
            // The submitting inventory user automatically authorizes the import.
            $record = ProductFileRequest::create([
                'type' => 'import',
                'store_hub_id' => $hub->id,
                'submitted_by' => auth()->id(),
                'file_name' => $request->file('file')->getClientOriginalName(),
                'status' => 'pending',
                'processing_status' => 'queued',
                'reviewed_by' => auth()->id(),
                'reviewed_at' => now(),
                'total_rows' => $this->countCsvDataRows($csv),
            ]);
            $record->storeCsv($csv);

            ProcessProductFileRequest::dispatch($record->id, auth()->id(), $request->ip() ?? '127.0.0.1');

            Log::info('Product import queued.', [
                'request_id' => $record->id,
                'store_hub_id' => $hub->id,
                'submitted_by' => auth()->id(),
            ]);
        });

        return redirect()->route('hub.dashboard', $hub->id)->with(
            'success',
            'Import queued and will be applied automatically in the background.'
        );
    }

    /**
     * Return the latest import state for the status panel. The CSV itself is
     * deliberately never exposed by this endpoint.
     */
    public function importStatus($hub_id)
    {
        abort_unless(auth()->user()?->can('manage-inventory'), 403);
        $hub = StoreHub::findOrFail($hub_id);
        $record = ProductFileRequest::query()
            ->where('store_hub_id', $hub->id)
            ->where('type', 'import')
            ->latest('id')
            ->first();

        if (! $record) {
            return response()->json(['import' => null]);
        }

        $status = $record->processing_status ?: match ($record->status) {
            'approved' => 'completed',
            'rejected' => 'failed',
            default => 'queued',
        };

        return response()->json(['import' => [
            'id' => $record->id,
            'status' => $status,
            'file_name' => $record->file_name,
            'total_rows' => $record->total_rows,
            'created_count' => $record->created_count,
            'updated_count' => $record->updated_count,
            'skipped_count' => $record->skipped_count,
            'error' => $record->processing_error,
            'updated_at' => optional($record->updated_at)->toIso8601String(),
        ]]);
    }

    public function applyImportCsv(Request $request, $hub_id)
    {
        abort_unless(auth()->user()?->can('manage-inventory'), 403);
        $user = auth()->user();
        if ($user && $user->role !== 'admin' && $user->role !== 'inventory_staff' && $user->store_hub_id && $user->store_hub_id != $hub_id) {
            abort(403, 'Unauthorized action for this store hub.');
        }

        $request->validate(['file' => 'required|mimes:csv,txt,text/csv']);
        $path = $request->file('file')->getRealPath();
        $handle = fopen($path, 'r');
        $header = fgetcsv($handle) ?: [];
        $normalizedHeader = array_map(
            fn ($value) => strtolower(trim((string) preg_replace('/^\xEF\xBB\xBF/', '', $value))),
            $header
        );
        $columnIndex = function (string $column, int $fallback = -1) use ($normalizedHeader): int {
            $index = array_search(strtolower($column), $normalizedHeader, true);

            return $index === false ? $fallback : $index;
        };
        $columns = [
            'item_id' => $columnIndex('item id'),
            'name' => $columnIndex('name'),
            'description' => $columnIndex('description'),
            'barcode' => $columnIndex('barcode'),
            'brand' => $columnIndex('brand'),
            'retail_group' => $columnIndex('retail group'),
            'retail_department' => $columnIndex('retail department'),
            'cost_price' => $columnIndex('cost price'),
            'sales_price' => $columnIndex('retail price', $columnIndex('sales price')),
            'wholesale_price' => $columnIndex('wholesale price'),
            'shopee_price' => $columnIndex('shopee price'),
            'lazada_price' => $columnIndex('lazada price'),
            'tiktok_price' => $columnIndex('tiktok price'),
            'stock' => $columnIndex('stock'),
            'unit_type' => $columnIndex('unit type'),
        ];
        // Validate every identifier before making any product or stock changes.
        $line = 1;
        try {
            while (($row = fgetcsv($handle)) !== false) {
                $line++;
                CsvIdentifier::read($row[$columns['item_id']] ?? '', "Row $line Item ID");
                if ($columns['barcode'] >= 0) {
                    CsvIdentifier::read($row[$columns['barcode']] ?? '', "Row $line Barcode");
                }
            }
        } finally {
            fclose($handle);
        }
        $handle = fopen($path, 'r');
        fgetcsv($handle);
        $logItems = [];
        $createdCount = 0;
        $updatedCount = 0;
        $skippedCount = 0;
        $knownBrands = $knownGroups = $knownDepartments = $knownUnits = [];
        $importUpdatesCatalog = (bool) StoreHub::findOrFail($hub_id)->is_head_office;

        $cleanPrice = function ($value) {
            if (empty($value)) {
                return 0;
            }
            $cleaned = preg_replace('/[^\d.]/', '', $value);

            return is_numeric($cleaned) ? $cleaned : 0;
        };
        $columnValue = fn (array $row, string $column) => $columns[$column] >= 0 ? ($row[$columns[$column]] ?? null) : null;

        while (($row = fgetcsv($handle)) !== false) {
            if (empty(array_filter($row))) {
                $skippedCount++;

                continue;
            }
            $itemId = CsvIdentifier::read($columnValue($row, 'item_id') ?? '', 'Item ID');
            if (! $itemId) {
                $skippedCount++;

                continue;
            }

            $existingProduct = Product::whereCatalog('item_id', $itemId)
                ->where('store_hub_id', $hub_id)
                ->first();
            $stockBefore = $existingProduct ? (int) $existingProduct->stock : null;
            $catalogProduct = $existingProduct?->catalogProduct;

            $brandName = trim((string) ($columnValue($row, 'brand') ?? '')) ?: ($catalogProduct?->brand ?? '');
            $groupName = trim((string) ($columnValue($row, 'retail_group') ?? '')) ?: ($catalogProduct?->retail_group ?? '');
            $deptName = trim((string) ($columnValue($row, 'retail_department') ?? '')) ?: ($catalogProduct?->retail_department ?? '');
            $unitTypeName = trim((string) ($columnValue($row, 'unit_type') ?? '')) ?: ($catalogProduct?->unit_type ?? '');

            if (! empty($brandName) && ! isset($knownBrands[$brandName])) {
                Brand::firstOrCreate(['brand_name' => $brandName]);
                $knownBrands[$brandName] = true;
            }
            if (! empty($groupName) && ! isset($knownGroups[$groupName])) {
                Group::firstOrCreate(['name' => $groupName]);
                $knownGroups[$groupName] = true;
            }
            if (! empty($deptName) && ! isset($knownDepartments[$deptName])) {
                Department::firstOrCreate(['name' => $deptName]);
                $knownDepartments[$deptName] = true;
            }
            if (! empty($unitTypeName) && ! isset($knownUnits[$unitTypeName])) {
                UnitType::firstOrCreate(['abbreviation' => $unitTypeName], ['status' => 'active']);
                $knownUnits[$unitTypeName] = true;
            }

            $stockValue = is_numeric(trim((string) ($columnValue($row, 'stock') ?? '')))
                ? (int) trim($columnValue($row, 'stock'))
                : (int) ($existingProduct?->stock ?? 0);

            $nameValue = iconv('UTF-8', 'UTF-8//IGNORE', trim((string) ($columnValue($row, 'name') ?? '')));
            $descriptionValue = iconv('UTF-8', 'UTF-8//IGNORE', trim((string) ($columnValue($row, 'description') ?? '')));
            $nameValue = $nameValue ?: ($catalogProduct?->name ?? '');
            $descriptionValue = $descriptionValue ?: ($catalogProduct?->description ?? '');

            // Some source CSVs leave the name column blank and put the
            // actual product text in the description column instead.
            // Falling back keeps products from being saved with no
            // visible name anywhere in the app.
            if ($nameValue === '') {
                $nameValue = $descriptionValue;
            }

            $savedProduct = $existingProduct ?? new Product(['item_id' => $itemId, 'store_hub_id' => $hub_id]);
            $savedProduct->preserveCatalogMetadata = ! $importUpdatesCatalog;
            $savedProduct->updateExistingCatalog = $importUpdatesCatalog;
            $productData = ['status' => 'active'];
            foreach (['name' => $nameValue, 'description' => $descriptionValue, 'brand' => $brandName, 'retail_group' => $groupName, 'retail_department' => $deptName, 'unit_type' => $unitTypeName] as $field => $value) {
                if ($columns[$field === 'retail_group' ? 'retail_group' : $field] >= 0 && trim((string) ($columnValue($row, $field === 'retail_group' ? 'retail_group' : $field) ?? '')) !== '') {
                    $productData[$field] = $value;
                }
            }
            if ($columns['barcode'] >= 0 && trim((string) ($columnValue($row, 'barcode') ?? '')) !== '') {
                $productData['barcode'] = CsvIdentifier::read($columnValue($row, 'barcode'), 'Barcode');
            }
            foreach (['cost_price', 'sales_price', 'wholesale_price', 'shopee_price', 'lazada_price'] as $field) {
                if ($columns[$field] >= 0 && trim((string) ($columnValue($row, $field) ?? '')) !== '') {
                    $productData[$field] = $cleanPrice($columnValue($row, $field));
                }
            }
            if ($columns['stock'] >= 0 && trim((string) ($columnValue($row, 'stock') ?? '')) !== '') {
                $productData['stock'] = $stockValue;
            }
            $savedProduct->fill($productData)->save();
            $operation = $existingProduct ? 'updated' : 'created';
            $operation === 'created' ? $createdCount++ : $updatedCount++;
            $logItems[] = [
                'product_id' => $savedProduct?->id,
                'item_id' => $itemId,
                'product_name' => $savedProduct->name ?: 'Unnamed product',
                'operation' => $operation,
                'quantity' => null,
                'stock_before' => $stockBefore,
                'stock_after' => $stockValue,
                'details' => null,
            ];
        }

        fclose($handle);

        $activityLog = StaffActivityLog::create([
            'user_id' => auth()->id(),
            'store_hub_id' => $hub_id,
            'action_type' => 'product_import',
            'description' => sprintf(
                'Imported %d product item(s): %d created and %d updated.',
                count($logItems),
                $createdCount,
                $updatedCount
            ),
            'details' => [
                'file_name' => $request->file('file')->getClientOriginalName(),
                'item_count' => count($logItems),
                'created_count' => $createdCount,
                'updated_count' => $updatedCount,
                'skipped_count' => $skippedCount,
            ],
            'ip_address' => $request->ip(),
        ]);
        $this->notifyInventoryTeam(
            'import',
            sprintf('User %s imported %d product item(s) into %s.', auth()->user()->name, count($logItems), StoreHub::find($hub_id)?->name ?? 'the store hub'),
            (int) $hub_id
        );

        collect($logItems)->chunk(500)->each(function ($items) use ($activityLog) {
            StaffActivityLogItem::insert($items->map(fn ($item) => [
                ...$item,
                'staff_activity_log_id' => $activityLog->id,
            ])->all());
        });

        return [
            'total_rows' => count($logItems) + $skippedCount,
            'created_count' => $createdCount,
            'updated_count' => $updatedCount,
            'skipped_count' => $skippedCount,
        ];
    }

    private function countCsvDataRows(string $csv): int
    {
        $stream = fopen('php://temp', 'r+');
        fwrite($stream, $csv);
        rewind($stream);
        fgetcsv($stream); // Header
        $count = 0;
        while (fgetcsv($stream) !== false) {
            $count++;
        }
        fclose($stream);

        return $count;
    }

    public function export(Request $request, $hubId)
    {
        abort_unless(auth()->user()?->can('manage-inventory'), 403);
        $hub = StoreHub::findOrFail($hubId);
        if (in_array(auth()->user()->role, ['admin', 'inventory_staff'], true)) {
            return $this->prepareExport($request, $hubId);
        }

        $request->validate([
            'export_all' => 'sometimes|boolean',
            'product_ids' => [Rule::requiredIf(fn () => ! $request->boolean('export_all')), 'array', 'max:5000'],
            'product_ids.*' => ['integer', 'distinct', Rule::exists('products', 'id')->where('store_hub_id', $hubId)],
        ]);
        $ids = Product::where('store_hub_id', $hubId)
            ->when(! $request->boolean('export_all'), fn ($q) => $q->whereIn('id', $request->input('product_ids', [])))
            ->pluck('id')->all();
        if (! $ids) {
            throw ValidationException::withMessages(['product_ids' => 'No products are available to export.']);
        }
        $record = DB::transaction(function () use ($request, $hubId, $ids, $hub) {
            $record = ProductFileRequest::create([
                'type' => 'export', 'store_hub_id' => $hubId, 'submitted_by' => auth()->id(), 'product_ids' => $ids,
                'file_name' => ProductExportFilename::make($hub, 'csv'),
                'processing_status' => 'queued', 'reviewed_by' => auth()->id(), 'reviewed_at' => now(),
            ]);
            ProcessProductFileRequest::dispatch($record->id, auth()->id(), $request->ip() ?? '127.0.0.1');

            return $record;
        });
        $url = route('product-file-requests.show', $record);

        return $request->expectsJson() ? response()->json(['redirect' => $url]) : redirect($url)->with('success', 'Export queued. Download it here when processing completes.');
    }

    public function prepareExport(Request $request, $hubId, ?string $filename = null)
    {
        abort_if(auth()->user()?->role === 'sales_associate', 403, 'Submit an export request from List of Products for inventory staff approval.');
        $user = auth()->user();
        if ($user && $user->role !== 'admin' && $user->role !== 'inventory_staff' && $user->store_hub_id && $user->store_hub_id != $hubId) {
            abort(403, 'Unauthorized action.');
        }

        $productIds = $request->input('product_ids', []);
        $query = Product::where('store_hub_id', $hubId);

        $request->validate(['export_all' => 'sometimes|boolean']);
        if (! $request->boolean('export_all')) {
            if (! empty($productIds)) {
                $query->whereIn('id', $productIds);
            } else {
                $query->whereRaw('1 = 0');
            }
        }

        $hub = StoreHub::findOrFail($hubId);
        $isHeadOffice = (bool) $hub->is_head_office;
        $productCount = (clone $query)->count();
        $products = $query->lazyById(250);
        foreach ($products as $product) {
            CsvIdentifier::read($product->barcode, "Product {$product->item_id} Barcode");
            CsvIdentifier::read($product->item_id, "Product {$product->id} Item ID");
        }
        $filename ??= ProductExportFilename::make($hub, 'csv');

        $activityLog = StaffActivityLog::create([
            'user_id' => auth()->id(),
            'store_hub_id' => $hubId,
            'action_type' => 'product_export',
            'description' => 'Exported '.$productCount.' product item(s) to CSV.',
            'details' => [
                'file_name' => $filename,
                'item_count' => $productCount,
            ],
            'ip_address' => $request->ip(),
        ]);
        $this->notifyInventoryTeam(
            'export',
            sprintf('User %s exported %d product item(s) from %s.', auth()->user()->name, $productCount, StoreHub::find($hubId)?->name ?? 'the store hub'),
            (int) $hubId
        );

        $products->chunk(100)->each(function ($productsChunk) use ($activityLog) {
            StaffActivityLogItem::insert($productsChunk->map(fn (Product $product) => [
                'staff_activity_log_id' => $activityLog->id,
                'product_id' => $product->id,
                'item_id' => $product->item_id,
                'product_name' => $product->name ?: 'Unnamed product',
                'operation' => 'exported',
                'quantity' => null,
                'stock_before' => (int) $product->stock,
                'stock_after' => (int) $product->stock,
                'details' => null,
            ])->all());
        });

        $headers = [
            'Content-type' => 'text/csv',
            'Content-Disposition' => "attachment; filename=$filename",
            'Pragma' => 'no-cache',
            'Cache-Control' => 'must-revalidate, post-check=0, pre-check=0',
            'Expires' => '0',
        ];

        $callback = function () use ($products, $isHeadOffice) {
            $file = fopen('php://output', 'w');
            fwrite($file, "\xEF\xBB\xBF");
            $headers = ['ID', 'Item ID', 'Name', 'Description', 'Barcode', 'Brand', 'Retail Group', 'Retail Department', 'Cost Price', 'Retail Price'];
            if ($isHeadOffice) {
                $headers = [...$headers, 'Wholesale Price', 'Shopee Price', 'Lazada Price'];
            }
            $headers = [...$headers, 'Stock', 'Unit Type'];
            fputcsv($file, $headers);

            foreach ($products as $prod) {
                $row = [
                    $prod->id, CsvIdentifier::write($prod->item_id, 'Item ID'), $prod->name, $prod->description, CsvIdentifier::write($prod->barcode, 'Barcode'), $prod->brand, $prod->retail_group, $prod->retail_department, $prod->cost_price, $prod->sales_price,
                ];
                if ($isHeadOffice) {
                    $row = [...$row, $prod->wholesale_price, $prod->shopee_price, $prod->lazada_price];
                }
                fputcsv($file, [...$row, $prod->stock, $prod->unit_type]);
            }
            fclose($file);
        };

        return response()->stream($callback, 200, $headers);
    }

    public function bulkDestroy(Request $request)
    {
        $request->validate([
            'product_ids' => 'required|array',
            'product_ids.*' => 'exists:products,id',
        ]);

        $user = auth()->user();
        $query = Product::whereIn('id', $request->product_ids);

        if ($user && $user->store_hub_id) {
            $query->where('store_hub_id', $user->store_hub_id);
        }

        $query->delete();

        return redirect()->back()->with('success', 'Selected products deleted successfully.');
    }

    public function toggle($id)
    {
        $product = Product::findOrFail($id);
        $user = auth()->user();
        if ($user && $user->role !== 'admin' && $user->role !== 'inventory_staff' && $user->store_hub_id && $product->store_hub_id != $user->store_hub_id) {
            abort(403, 'Unauthorized action.');
        }

        $product->status = ($product->status == 'active') ? 'inactive' : 'active';
        $product->save();

        return back()->with('success', 'Product status updated successfully.');
    }

    public function searchAjax(Request $request, $hubId)
    {
        $user = auth()->user();
        if ($user && ! in_array($user->role, ['admin', 'inventory_staff'], true) && ! $user->canAccessHub((int) $hubId)) {
            return response()->json([], 403);
        }
        if ($user?->role === 'sales_associate'
            && ! StoreHub::whereKey($hubId)->where('is_head_office', false)->exists()) {
            return response()->json([], 403);
        }

        $search = $request->input('q', '');
        $field = $request->input('field');
        $stockChannel = $request->input('stock_channel');
        if ($stockChannel !== null) {
            $request->validate([
                'stock_channel' => ['string', Rule::in(array_keys(self::SALES_STOCK_CHANNELS))],
            ]);
        }
        $query = Product::where('store_hub_id', $hubId);
        if ($request->boolean('active_only')) {
            $query->where('status', 'active');
        }
        if ($request->boolean('inventory_page')) {
            $request->validate(['after' => 'nullable|integer|min:0']);

            return response()->json($query->where('status', 'active')->where('id', '>', $request->integer('after'))->orderBy('id')->limit(500)->get());
        }

        if ($search !== '') {
            if (in_array($field, ['brand', 'retail_group', 'retail_department'], true)) {
                $query->whereHas('catalogProduct', fn ($catalog) => KeywordSearch::apply($catalog, $search, [$field]));
            } elseif (in_array($field, ['item_id', 'barcode'], true)) {
                $query->where(function ($builder) use ($field, $search) {
                    $builder->whereHas('catalogProduct', fn ($catalog) => KeywordSearch::apply($catalog, $search, [$field]));
                    if ($field === 'item_id' && ctype_digit((string) $search)) {
                        $builder->orWhere('products.id', (int) $search);
                    }
                });
            } else {
                $fields = $field === null
                    ? ['name', ...($request->boolean('exclude_description') ? [] : ['description']), 'item_id', 'barcode', ...($request->boolean('exclude_brand') ? [] : ['brand'])]
                    : ['name', 'description'];
                $query->where(function ($builder) use ($search, $fields) {
                    $builder->whereHas('catalogProduct', function ($catalog) use ($search, $fields) {
                        KeywordSearch::apply($catalog, $search, $fields);
                    });
                    if (ctype_digit((string) $search)) {
                        $builder->orWhere('products.id', (int) $search);
                    }
                });
            }
        }

        $sortField = $request->input('sort') === 'item_id' ? 'item_id' : 'name';
        if ($search !== '') {
            $priority = ctype_digit((string) $search)
                ? '(CASE WHEN products.id = ? THEN 0 ELSE 1 END) + '
                : '';
            $bindings = ctype_digit((string) $search) ? [(int) $search] : [];
            $query->orderByRaw(
                $priority.'(SELECT CASE
                    WHEN item_id = ? THEN 0
                    WHEN item_id LIKE ? THEN 1
                    WHEN barcode = ? THEN 2
                    WHEN barcode LIKE ? THEN 3
                    ELSE 4
                END FROM catalog_products WHERE catalog_products.id = products.catalog_product_id)',
                [...$bindings, $search, "{$search}%", $search, "{$search}%"]
            );
        }

        $products = $query
            ->when($stockChannel, fn ($builder) => $builder->with('stockAllocation'))
            ->orderByCatalog($sortField)
            ->limit(100)
            ->get();

        $products->each(function (Product $product): void {
            // Normalized products keep catalog metadata in catalog_products; expose
            // the searchable fields explicitly for AJAX consumers.
            $product->setAttribute('item_id', $product->catalogProduct?->item_id);
            $product->setAttribute('name', $product->catalogProduct?->name);
            $product->setAttribute('barcode', $product->catalogProduct?->barcode);
        });

        if ($stockChannel && $products->isNotEmpty()) {
            $products->each(function (Product $product) use ($stockChannel) {
                $product->setAttribute(
                    'channel_available_stock',
                    $stockChannel === 'walk_in'
                        ? $product->unallocatedStock()
                        : $product->channelAvailableStock($stockChannel, $stockChannel !== 'online')
                );
            });
        }

        return response()->json($products);
    }

    private function notifyInventoryTeam(string $event, string $message, int $hubId): void
    {
        User::whereIn('role', ['admin', 'inventory_staff'])
            ->where('id', '<>', auth()->id())
            ->get()
            ->each(fn (User $recipient) => $recipient->notify(
                new InventoryWorkflowNotification($event, $message, $hubId, route('staff-logs.index'))
            ));
    }
}
