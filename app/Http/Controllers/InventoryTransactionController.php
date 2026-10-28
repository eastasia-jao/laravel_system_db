<?php

namespace App\Http\Controllers;

use App\Models\EventPullout;
use App\Models\FullyBookedOrder;
use App\Models\InventoryTransaction;
use App\Models\NationalProduct;
use App\Models\NationalPullout;
use App\Models\PendingSale;
use App\Models\Product;
use App\Models\ProductReplacement;
use App\Models\ProductStockAllocation;
use App\Models\SalesTransaction;
use App\Models\StaffActivityLog;
use App\Models\StoreHub;
use App\Models\TransactionItem;
use App\Models\User;
use App\Notifications\InventoryWorkflowNotification;
use App\Support\AttachmentAvailability;
use App\Support\AttachmentResponse;
use App\Support\AttachmentStorage;
use App\Support\CsvIdentifier;
use App\Support\EventPulloutWorkbook;
use App\Support\ProductExportWorkbook;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
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
        $filename = $hubCode.'_Download_'.$typeLabel.'_'.now()->format('Ymd_His').'.xlsx';
        $rows = [$headers];
        Product::where('store_hub_id', $validated['hub_id'])
            ->where('status', 'active')
            ->when($validated['type'] === 'stock_transfer', fn ($query) => $query->with('stockAllocation'))
            ->when(
                $validated['type'] === 'branch_transfer',
                fn ($query) => $query->orderByCatalog('item_id'),
                fn ($query) => $query->orderByCatalog(),
            )
            ->each(function (Product $product) use (&$rows, $validated) {
                $row = [
                    $product->description ?: $product->name,
                    CsvIdentifier::read($product->barcode ?? '', 'Barcode'),
                    CsvIdentifier::read($product->item_id, 'Item ID'),
                    $validated['type'] === 'stock_transfer' ? $product->unallocatedStock() : (int) $product->stock,
                    '',
                ];
                if ($validated['type'] === 'return') {
                    $row[] = '';
                }
                $rows[] = $row;
            });

        return response(ProductExportWorkbook::fromRows($rows), 200, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'Content-Disposition' => 'attachment; filename="'.$filename.'"',
        ]);
    }

    public function downloadInventoryItemsWorkbook(Request $request)
    {
        $validated = $request->validate($this->inventoryWorkbookRules());
        $hub = $this->authorizeInventoryWorkbookHub((int) $validated['hub_id']);
        $items = $request->boolean('include_products', true)
            ? Product::where('store_hub_id', $hub->id)
                ->where('status', 'active')
                ->when($validated['type'] === 'stock_transfer', fn ($query) => $query->with('stockAllocation'))
                ->orderByCatalog('item_id')
                ->get()
                ->map(fn (Product $product) => $this->inventoryWorkbookProduct($product, '', $validated['type']))
                ->all()
            : [];
        $path = EventPulloutWorkbook::createInventoryItems($items, $this->inventoryWorkbookDocument($validated));

        return response()->download(
            $path,
            $this->inventoryWorkbookFilename($hub, $validated['type'], 'Download'),
            ['Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet']
        )->deleteFileAfterSend(true);
    }

    public function importInventoryItemsWorkbook(Request $request)
    {
        $validated = $request->validate([
            'hub_id' => ['required', 'integer', 'exists:store_hubs,id'],
            'type' => ['required', Rule::in(['stock_transfer', 'branch_transfer', 'restock'])],
            'file' => ['required', 'file', 'mimes:xlsx', 'max:10240'],
        ]);
        $hub = $this->authorizeInventoryWorkbookHub((int) $validated['hub_id']);
        try {
            $workbook = EventPulloutWorkbook::readInventoryItems($validated['file']->getRealPath());
        } catch (\RuntimeException $exception) {
            throw ValidationException::withMessages(['file' => $exception->getMessage()]);
        }

        $isZeroQuantity = static fn (string $quantity): bool => preg_match('/^\+?0+(?:\.0+)?$/', trim($quantity)) === 1;
        $rowsToMatch = array_values(array_filter(
            $workbook['items'],
            fn (array $row) => trim($row['quantity']) !== '' && ! $isZeroQuantity($row['quantity'])
        ));
        $productsByItemId = [];
        $productsByBarcode = [];
        foreach (array_chunk($rowsToMatch, 400) as $lookupRows) {
            $itemIds = collect($lookupRows)->pluck('item_id')->map(fn ($value) => trim($value))->filter()->unique()->values()->all();
            $barcodes = collect($lookupRows)->pluck('barcode')->map(fn ($value) => trim($value))->filter()->unique()->values()->all();
            if ($itemIds === [] && $barcodes === []) {
                continue;
            }
            $products = Product::where('store_hub_id', $hub->id)
                ->where('status', 'active')
                ->whereHas('catalogProduct', function ($catalog) use ($itemIds, $barcodes) {
                    $catalog->where(function ($query) use ($itemIds, $barcodes) {
                        if ($itemIds !== []) {
                            $query->whereIn('item_id', $itemIds);
                        }
                        if ($barcodes !== []) {
                            $itemIds === [] ? $query->whereIn('barcode', $barcodes) : $query->orWhereIn('barcode', $barcodes);
                        }
                    });
                })->get();
            foreach ($products as $product) {
                if (($itemId = trim((string) $product->item_id)) !== '') {
                    $productsByItemId[$itemId][$product->id] = $product;
                }
                if (($barcode = trim((string) $product->barcode)) !== '') {
                    $productsByBarcode[$barcode][$product->id] = $product;
                }
            }
        }

        $items = [];
        $errors = [];
        foreach ($workbook['items'] as $index => $row) {
            $quantity = trim($row['quantity']);
            if ($quantity === '' || $isZeroQuantity($quantity)) {
                continue;
            }
            $line = $index + 3;
            if (! preg_match('/^[1-9]\d*$/', $quantity) || filter_var($quantity, FILTER_VALIDATE_INT) === false) {
                $errors[] = "Row {$line}: Quantity must be a positive whole number.";

                continue;
            }
            $itemId = trim($row['item_id']);
            $barcode = trim($row['barcode']);
            $matches = $itemId !== '' ? array_values($productsByItemId[$itemId] ?? []) : array_values($productsByBarcode[$barcode] ?? []);
            if ($itemId !== '' && $barcode !== '') {
                $matches = array_values(array_filter($matches, fn (Product $product) => (string) $product->barcode === $barcode));
            }
            if (count($matches) !== 1) {
                $errors[] = 'Row '.$line.': '.($itemId ?: $barcode ?: trim($row['name']) ?: 'product').' is missing, ambiguous, or not active in the selected store.';

                continue;
            }
            $items[] = ['product_id' => $matches[0]->id, 'quantity' => (int) $quantity];
        }
        if ($errors !== []) {
            throw ValidationException::withMessages(['file' => implode("\n", $errors)]);
        }
        if ($items === []) {
            throw ValidationException::withMessages(['file' => 'No quantities found. Enter a Quantity before importing.']);
        }

        return response()->json([
            'items' => $items,
            'metadata' => [
                'reference' => $workbook['reference'],
                'occurred_on' => $workbook['occurred_on'],
                'notes' => $workbook['notes'],
            ],
        ]);
    }

    public function exportInventoryItemsWorkbook(Request $request)
    {
        $validated = $request->validate(array_merge($this->inventoryWorkbookRules(), [
            'items' => ['required', 'array', 'min:1'],
            'items.*.product_id' => ['required', 'integer'],
            'items.*.quantity' => ['required', 'integer', 'min:1'],
        ]));
        $hub = $this->authorizeInventoryWorkbookHub((int) $validated['hub_id']);
        $productIds = collect($validated['items'])->pluck('product_id')->map(fn ($id) => (int) $id)->unique();
        $products = Product::where('store_hub_id', $hub->id)
            ->where('status', 'active')
            ->whereIn('id', $productIds)
            ->when($validated['type'] === 'stock_transfer', fn ($query) => $query->with('stockAllocation'))
            ->get()
            ->keyBy('id');
        if ($products->count() !== $productIds->count()) {
            throw ValidationException::withMessages(['items' => 'One or more products are not active in the selected store.']);
        }
        $items = collect($validated['items'])->map(function (array $item) use ($products, $validated) {
            return $this->inventoryWorkbookProduct($products->get((int) $item['product_id']), (int) $item['quantity'], $validated['type']);
        })->all();
        $path = EventPulloutWorkbook::createInventoryItems($items, $this->inventoryWorkbookDocument($validated));

        return response()->download(
            $path,
            $this->inventoryWorkbookFilename($hub, $validated['type'], 'Export'),
            ['Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet']
        )->deleteFileAfterSend(true);
    }

    private function inventoryWorkbookRules(): array
    {
        return [
            'hub_id' => ['required', 'integer', 'exists:store_hubs,id'],
            'type' => ['required', Rule::in(['stock_transfer', 'branch_transfer', 'restock'])],
            'target_hub_id' => ['nullable', 'integer', 'exists:store_hubs,id'],
            'transfer_direction' => ['nullable', Rule::in(['ho_to_branch', 'branch_to_ho'])],
            'reference' => ['nullable', 'string', 'max:100'],
            'occurred_on' => ['nullable', 'date_format:Y-m-d'],
            'notes' => ['nullable', 'string', 'max:150'],
            'include_products' => ['sometimes', 'boolean'],
        ];
    }

    private function authorizeInventoryWorkbookHub(int $hubId): StoreHub
    {
        $user = auth()->user();
        abort_unless(
            in_array($user?->role, ['admin', 'inventory_staff'], true)
                || ($user?->role === 'sales_associate' && $user->canAccessHub($hubId)),
            403
        );

        return StoreHub::whereKey($hubId)->where('status', 'active')->firstOrFail();
    }

    private function inventoryWorkbookProduct(Product $product, int|string $quantity, string $type): array
    {
        return [
            'name' => $product->name,
            'barcode' => (string) ($product->barcode ?? ''),
            'item_id' => (string) $product->item_id,
            'physical_stock' => $type === 'stock_transfer'
                ? $product->unallocatedStock()
                : (int) $product->stock + (int) $product->damaged_stock,
            'quantity' => $quantity,
        ];
    }

    private function inventoryWorkbookDocument(array $data): array
    {
        $target = isset($data['target_hub_id'])
            ? StoreHub::whereKey($data['target_hub_id'])->where('status', 'active')->first()
            : null;
        $title = match ($data['type']) {
            'branch_transfer' => 'BRANCH TO BRANCH',
            'restock' => 'WAREHOUSE TO HO RESTOCK',
            default => ($data['transfer_direction'] ?? 'ho_to_branch') === 'branch_to_ho' ? 'BRANCH TO HO' : 'HO TO BRANCH',
        };

        return [
            'title' => $title,
            'reference' => $data['reference'] ?? '',
            'occurred_on' => $data['occurred_on'] ?? now()->toDateString(),
            'target' => $data['type'] === 'restock' ? '' : ($target?->name ?? ''),
            'notes' => $data['notes'] ?? '',
        ];
    }

    private function inventoryWorkbookFilename(StoreHub $hub, string $type, string $action): string
    {
        $hubCode = Str::upper(Str::slug($hub->code, '_')) ?: (string) $hub->id;
        $label = match ($type) {
            'branch_transfer' => 'StockTrf_B2B',
            'restock' => 'Restock_Warehouse_HO',
            default => 'StockTrf_HO_Branch',
        };

        return $hubCode.'_'.Str::studly($action).'_'.$label.'_'.now()->format('Ymd_His').'.xlsx';
    }

    public function downloadEventWorksheet(Request $request)
    {
        $validated = $request->validate([
            'hub_id' => ['required', 'integer', 'exists:store_hubs,id'],
            'reference' => ['nullable', 'string', 'max:100'],
            'occurred_on' => ['nullable', 'date_format:Y-m-d'],
            'source' => ['nullable', 'string', 'max:100'],
            'notes' => ['nullable', 'string', 'max:150'],
            'include_products' => ['sometimes', 'boolean'],
        ]);
        $hub = $this->authorizeEventWorkbookHub((int) $validated['hub_id']);
        $items = $request->boolean('include_products', true)
            ? Product::where('store_hub_id', $hub->id)
                ->where('status', 'active')
                ->orderByCatalog('item_id')
                ->get()
                ->flatMap(function (Product $product) {
                    $conditions = (int) $product->damaged_stock > 0
                        ? ['good', 'damaged']
                        : ['good'];

                    return collect($conditions)->map(fn (string $condition) => [
                        'name' => $product->description ?: $product->name,
                        'barcode' => (string) ($product->barcode ?? ''),
                        'item_id' => (string) $product->item_id,
                        'physical_stock' => (int) $product->stock + (int) $product->damaged_stock,
                        'good_stock' => (int) $product->stock,
                        'damaged_stock' => (int) $product->damaged_stock,
                        'pullout_qty' => '',
                        'actual_pullout' => '',
                        'stock_condition' => $condition,
                    ]);
                })
                ->values()
                ->all()
            : [];
        $path = EventPulloutWorkbook::create($items, [
            'reference' => $validated['reference'] ?? '',
            'occurred_on' => $validated['occurred_on'] ?? now()->toDateString(),
            'source' => $validated['source'] ?? '',
            'notes' => $validated['notes'] ?? '',
        ]);
        $hubCode = Str::upper(Str::slug($hub->code, '_')) ?: (string) $hub->id;

        return response()->download(
            $path,
            $hubCode.'_Download_Event_Pullout_'.now()->format('Ymd_His').'.xlsx',
            ['Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet']
        )->deleteFileAfterSend(true);
    }

    public function importEventWorksheet(Request $request)
    {
        $validated = $request->validate([
            'hub_id' => ['required', 'integer', 'exists:store_hubs,id'],
            'file' => ['required', 'file', 'mimes:xlsx', 'max:10240'],
        ]);
        $hub = $this->authorizeEventWorkbookHub((int) $validated['hub_id']);
        $workbook = EventPulloutWorkbook::read($validated['file']->getRealPath());
        $rowsToMatch = array_values(array_filter(
            $workbook['items'],
            fn (array $row) => trim($row['actual_pullout']) !== ''
        ));
        $productsByItemId = [];
        $productsByBarcode = [];
        foreach (array_chunk($rowsToMatch, 400) as $lookupRows) {
            $itemIds = collect($lookupRows)
                ->map(fn (array $row) => trim($row['item_id']))
                ->filter()
                ->unique()
                ->values()
                ->all();
            $barcodes = collect($lookupRows)
                ->map(fn (array $row) => trim($row['barcode']))
                ->filter()
                ->unique()
                ->values()
                ->all();
            if ($itemIds === [] && $barcodes === []) {
                continue;
            }

            $products = Product::where('store_hub_id', $hub->id)
                ->where('status', 'active')
                ->whereHas('catalogProduct', function ($catalog) use ($itemIds, $barcodes) {
                    $catalog->where(function ($query) use ($itemIds, $barcodes) {
                        if ($itemIds !== []) {
                            $query->whereIn('item_id', $itemIds);
                        }
                        if ($barcodes !== []) {
                            $itemIds === []
                                ? $query->whereIn('barcode', $barcodes)
                                : $query->orWhereIn('barcode', $barcodes);
                        }
                    });
                })
                ->get();

            foreach ($products as $product) {
                $itemId = trim((string) $product->item_id);
                $barcode = trim((string) $product->barcode);
                if ($itemId !== '') {
                    $productsByItemId[$itemId][$product->id] = $product;
                }
                if ($barcode !== '') {
                    $productsByBarcode[$barcode][$product->id] = $product;
                }
            }
        }
        $items = [];
        $errors = [];

        foreach ($workbook['items'] as $index => $row) {
            $quantity = trim($row['actual_pullout']);
            if ($quantity === '') {
                continue;
            }

            $line = $index + 3;
            $name = trim($row['name']);
            $pulloutQuantity = trim($row['pullout_qty']);
            if ($pulloutQuantity !== ''
                && (! preg_match('/^\d+$/', $pulloutQuantity) || filter_var($pulloutQuantity, FILTER_VALIDATE_INT) === false)) {
                $errors[] = "Row {$line}: Pull-Out Qty must be a non-negative whole number.";

                continue;
            }
            $worksheetCondition = strtolower(trim($row['stock_condition']));
            $condition = '';
            if ($worksheetCondition !== '') {
                if (! in_array($worksheetCondition, ['good', 'damaged'], true)) {
                    $errors[] = "Row {$line}: Stock Condition must be good or damaged.";

                    continue;
                }
                $condition = $worksheetCondition;
            }
            if (preg_match('/\s+\((GOOD|DAMAGED)\)\s*$/i', $name, $match)) {
                if ($worksheetCondition === '') {
                    $condition = strtolower($match[1]);
                }
                $name = trim(substr($name, 0, -strlen($match[0])));
            }
            if ($condition === '') {
                $errors[] = "Row {$line}: Stock Condition must be good or damaged.";

                continue;
            }
            if (! preg_match('/^[1-9]\d*$/', $quantity) || filter_var($quantity, FILTER_VALIDATE_INT) === false) {
                $errors[] = "Row {$line}: Physical Actual-Pullout must be a positive whole number.";

                continue;
            }

            $itemId = trim($row['item_id']);
            $barcode = trim($row['barcode']);
            $matches = $itemId !== ''
                ? array_values($productsByItemId[$itemId] ?? [])
                : array_values($productsByBarcode[$barcode] ?? []);
            if ($itemId !== '' && $barcode !== '') {
                $matches = array_values(array_filter(
                    $matches,
                    fn (Product $product) => (string) $product->barcode === $barcode
                ));
            }
            if (count($matches) !== 1) {
                $errors[] = "Row {$line}: ".($itemId ?: $barcode ?: $name).' is missing, ambiguous, or not active in the selected store.';

                continue;
            }

            $product = $matches[0];
            $items[] = [
                'product_id' => $product->id,
                'quantity' => (int) $quantity,
                'pullout_qty' => $pulloutQuantity === '' ? null : (int) $pulloutQuantity,
                'stock_condition' => $condition,
            ];
        }

        if ($errors !== []) {
            throw ValidationException::withMessages(['file' => implode("\n", $errors)]);
        }
        if ($items === []) {
            throw ValidationException::withMessages(['file' => 'No quantities found. Enter a quantity in Physical Actual-Pullout before importing.']);
        }

        return response()->json([
            'items' => $items,
            'metadata' => [
                'reference' => $workbook['reference'],
                'occurred_on' => $workbook['occurred_on'],
                'source' => $workbook['source'],
                'notes' => $workbook['notes'],
            ],
        ]);
    }

    public function exportEventItems(Request $request)
    {
        $validated = $request->validate([
            'hub_id' => ['required', 'integer', 'exists:store_hubs,id'],
            'occurred_on' => ['required', 'date_format:Y-m-d'],
            'source' => ['required', 'string', 'max:100'],
            'reference' => ['nullable', 'string', 'max:100'],
            'notes' => ['nullable', 'string', 'max:150'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.product_id' => ['required', 'integer'],
            'items.*.quantity' => ['nullable', 'integer', 'min:1'],
            'items.*.stock_condition' => ['required', Rule::in(['good', 'damaged'])],
        ]);
        $hub = $this->authorizeEventWorkbookHub((int) $validated['hub_id']);
        $products = Product::where('store_hub_id', $hub->id)
            ->where('status', 'active')
            ->whereIn('id', collect($validated['items'])->pluck('product_id'))
            ->get()
            ->keyBy('id');
        if (count($products) !== count(collect($validated['items'])->pluck('product_id')->unique())) {
            throw ValidationException::withMessages(['items' => 'One or more selected products are not active in the selected store.']);
        }

        $items = collect($validated['items'])->map(function ($item) use ($products) {
            $product = $products->get((int) $item['product_id']);
            $condition = $item['stock_condition'];

            return [
                'name' => $product->description ?: $product->name,
                'barcode' => (string) ($product->barcode ?? ''),
                'item_id' => (string) $product->item_id,
                'physical_stock' => (int) $product->stock + (int) $product->damaged_stock,
                'good_stock' => (int) $product->stock,
                'damaged_stock' => (int) $product->damaged_stock,
                'pullout_qty' => '',
                'actual_pullout' => '',
                'stock_condition' => $condition,
            ];
        })->all();
        $path = EventPulloutWorkbook::create($items, [
            'reference' => $validated['reference'] ?? '',
            'occurred_on' => $validated['occurred_on'],
            'source' => $validated['source'],
            'notes' => $validated['notes'] ?? '',
        ]);
        $hubCode = Str::upper(Str::slug($hub->code, '_')) ?: (string) $hub->id;

        return response()->download(
            $path,
            $hubCode.'_Exp_Event_Pullout_'.now()->format('Ymd_His').'.xlsx',
            ['Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet']
        )->deleteFileAfterSend(true);
    }

    private function authorizeEventWorkbookHub(int $hubId): StoreHub
    {
        abort_unless(auth()->user()?->can('manage-inventory'), 403);

        return StoreHub::whereKey($hubId)->where('status', 'active')->firstOrFail();
    }

    public function returnSalesLookup(Request $request)
    {
        $validated = $request->validate([
            'hub_id' => ['required', 'integer', 'exists:store_hubs,id'],
            'channel' => ['required', 'string', 'max:50'],
            'customer' => ['nullable', 'string', 'max:255'],
            'transaction_id' => ['nullable', 'integer'],
            'event_reference' => ['nullable', 'string', 'max:100'],
            'date' => ['nullable', 'date_format:Y-m-d'],
        ]);
        $user = auth()->user();
        abort_unless(
            $user?->role === 'admin'
                || ($user?->role === 'inventory_staff' && StoreHub::whereKey($validated['hub_id'])->where('status', 'active')->exists())
                || ($user?->role === 'sales_associate' && $user->canAccessHub((int) $validated['hub_id'])),
            403
        );

        $isEventReturn = strtolower(trim($validated['channel'])) === 'event';
        $normalizedChannel = $this->normalizeChannel($validated['channel']);
        $lookupHub = StoreHub::findOrFail((int) $validated['hub_id']);
        if ($user?->role === 'inventory_staff') {
            abort_unless($lookupHub->status === 'active', 403);
        }
        if (! $lookupHub->is_head_office && $normalizedChannel !== 'walk_in' && ! $isEventReturn) {
            throw ValidationException::withMessages([
                'channel' => 'Only Walk-In sales can be selected for a branch store.',
            ]);
        }
        if ($user?->role === 'sales_associate') {
            abort_unless($normalizedChannel === 'walk_in', 403, 'Sales associates can only view Walk-In sales.');
        }
        if ($isEventReturn) {
            abort_unless(in_array($user?->role, ['admin', 'inventory_staff'], true), 403, 'Only inventory staff can record Event returns.');
            $eventQuery = InventoryTransaction::query()
                ->where('store_hub_id', $validated['hub_id'])
                ->where('type', 'sponsor_workshop')
                ->where(function ($query) {
                    $query->whereNull('channel')
                        ->orWhere('channel', '!=', 'fully_booked');
                })
                ->whereNotNull('reference');

            if ($request->filled('event_reference')) {
                $reference = $validated['event_reference'];
                $event = EventPullout::query()
                    ->where('store_hub_id', $validated['hub_id'])
                    ->where('reference', $reference)
                    ->first();
                $pullouts = $eventQuery->where('reference', $reference)
                    ->with('product')
                    ->orderBy('id')
                    ->get();
                abort_if($pullouts->isEmpty(), 404, 'Event pullout reference not found.');
                abort_if($event?->status === 'closed', 422, 'Closed events cannot accept additional returns.');
                $soldByProduct = $event
                    ? InventoryTransaction::query()
                        ->where('event_pullout_id', $event->id)
                        ->where('type', 'sold')
                        ->where('channel', 'event')
                        ->selectRaw('product_id, condition, SUM(quantity) as quantity')
                        ->groupBy('product_id', 'condition')
                        ->get()
                        ->groupBy('product_id')
                    : collect();
                $returnedByProduct = InventoryTransaction::query()
                    ->where('store_hub_id', $validated['hub_id'])
                    ->where('type', 'return')
                    ->where('channel', 'event')
                    ->where('reference', $reference)
                    ->selectRaw('product_id, condition, SUM(quantity) as quantity')
                    ->groupBy('product_id', 'condition')
                    ->get()
                    ->groupBy('product_id');
                $items = $pullouts->groupBy('product_id')
                    ->map(function ($rows, $productId) use ($returnedByProduct, $soldByProduct) {
                        $first = $rows->first();
                        $soldRows = $soldByProduct->get($productId, collect());
                        $returnedRows = $returnedByProduct->get($productId, collect());
                        $conditionQuantity = fn ($conditionRows, string $condition) => (int) $conditionRows
                            ->filter(fn ($row) => strtolower((string) ($row->condition ?: 'good')) === $condition)
                            ->sum('quantity');
                        $pulledGood = $conditionQuantity($rows, 'good');
                        $pulledDamaged = $conditionQuantity($rows, 'damaged');
                        $soldGood = $conditionQuantity($soldRows, 'good');
                        $soldDamaged = $conditionQuantity($soldRows, 'damaged');
                        $returnedGood = $conditionQuantity($returnedRows, 'good');
                        $returnedDamaged = $conditionQuantity($returnedRows, 'damaged');

                        return [
                            'product_id' => $productId,
                            'name' => $first->product?->name ?? 'Product #'.$productId,
                            'item_id' => $first->product?->item_id,
                            'quantity' => $pulledGood + $pulledDamaged,
                            'sold_quantity' => $soldGood + $soldDamaged,
                            'returned_quantity' => $returnedGood + $returnedDamaged,
                            'pulled_good_quantity' => $pulledGood,
                            'pulled_damaged_quantity' => $pulledDamaged,
                            'sold_good_quantity' => $soldGood,
                            'sold_damaged_quantity' => $soldDamaged,
                            'returned_good_quantity' => $returnedGood,
                            'returned_damaged_quantity' => $returnedDamaged,
                            'remaining_good_quantity' => max(0, $pulledGood - $soldGood - $returnedGood),
                            'remaining_damaged_quantity' => max(0, $pulledDamaged - $soldDamaged - $returnedDamaged),
                            'unit_price' => 0,
                        ];
                    })
                    ->values();

                return response()->json([
                    'transaction' => [
                        'id' => $reference,
                        'order_number' => $reference,
                        'customer_name' => $pullouts->first()->source,
                        'items' => $items,
                    ],
                ]);
            }

            $pullouts = $eventQuery->orderByDesc('occurred_on')->orderByDesc('id')->get();
            $references = $pullouts->groupBy('reference');
            $eventsByReference = EventPullout::query()
                ->where('store_hub_id', $validated['hub_id'])
                ->whereIn('reference', $references->keys())
                ->get()
                ->keyBy('reference');
            $sold = InventoryTransaction::query()
                ->whereIn('event_pullout_id', $eventsByReference->pluck('id'))
                ->where('type', 'sold')
                ->where('channel', 'event')
                ->selectRaw('event_pullout_id, product_id, SUM(quantity) as sold_quantity')
                ->groupBy('event_pullout_id', 'product_id')
                ->get()
                ->groupBy('event_pullout_id')
                ->map(fn ($rows) => $rows->keyBy('product_id'));
            $returned = InventoryTransaction::query()
                ->where('store_hub_id', $validated['hub_id'])
                ->where('type', 'return')
                ->where('channel', 'event')
                ->whereIn('reference', $references->keys())
                ->selectRaw('reference, product_id, SUM(quantity) as returned_quantity')
                ->groupBy('reference', 'product_id')
                ->get()
                ->groupBy('reference')
                ->map(fn ($rows) => $rows->keyBy('product_id'));

            return response()->json([
                'orders' => $references
                    ->filter(function ($rows, $reference) use ($returned, $sold, $eventsByReference) {
                        $event = $eventsByReference->get($reference);
                        if ($event?->status === 'closed') {
                            return false;
                        }
                        $alreadyReturned = $returned->get($reference, collect());
                        $alreadySold = $event ? $sold->get($event->id, collect()) : collect();

                        return $rows->groupBy('product_id')->contains(
                            fn ($productRows, $productId) => (int) $productRows->sum('quantity')
                                - (int) ($alreadySold->get($productId)?->sold_quantity ?? 0)
                                > (int) ($alreadyReturned->get($productId)?->returned_quantity ?? 0)
                        );
                    })
                    ->map(fn ($rows, $reference) => [
                        'id' => $reference,
                        'order_number' => $reference,
                        'order_date' => optional($rows->first()->occurred_on)->format('M d, Y'),
                        'source' => $rows->first()->source,
                    ])
                    ->values(),
            ]);
        }
        if ($normalizedChannel === 'national_bookstore') {
            abort_unless(
                in_array($user?->role, ['admin', 'inventory_staff'], true) && $lookupHub->is_head_office,
                403,
                'National Bookstore returns are available only in Head Office mode.'
            );

            $query = NationalPullout::query()
                ->where('store_hub_id', $validated['hub_id'])
                ->with(['items.product', 'items.inventoryReturns']);

            if ($request->filled('transaction_id')) {
                $pullout = $query->whereKey($validated['transaction_id'])->firstOrFail();

                return response()->json([
                    'transaction' => [
                        'id' => $pullout->id,
                        'order_number' => $pullout->po_number,
                        'customer_name' => 'National Bookstore',
                        'items' => $pullout->items->map(fn ($item) => [
                            'id' => null,
                            'national_pullout_item_id' => $item->id,
                            'national_product_id' => $item->national_product_id,
                            'name' => $item->product_name,
                            'item_id' => $item->item_id,
                            'quantity' => (int) $item->actual_pullout,
                            'returned_quantity' => (int) $item->inventoryReturns->sum('quantity'),
                            'unit_price' => 0,
                            'discount_percentage' => 0,
                        ])->values(),
                    ],
                ]);
            }

            return response()->json([
                'orders' => $query->orderByDesc('occurred_on')->orderByDesc('id')->get()
                    ->filter(fn (NationalPullout $pullout) => $pullout->items->contains(
                        fn ($item) => $item->inventoryReturns->sum('quantity') < $item->actual_pullout
                    ))
                    ->map(fn (NationalPullout $pullout) => [
                        'id' => $pullout->id,
                        'order_number' => $pullout->po_number,
                        'order_date' => optional($pullout->occurred_on)->format('M d, Y'),
                    ])->values(),
            ]);
        }
        $channelValues = match ($normalizedChannel) {
            'online' => ['online', 'online_sales', 'online_order', 'event', 'fully_booked'],
            'walk_in' => ['walk_in', 'walkin'],
            default => [$this->normalizeChannel($validated['channel'])],
        };
        $query = SalesTransaction::query()
            ->where('store_hub_id', $validated['hub_id'])
            ->whereIn(DB::raw("LOWER(REPLACE(REPLACE(channel_type, '-', '_'), ' ', '_'))"), $channelValues)
            ->when($normalizedChannel === 'wholesale', fn ($query) => $query->whereIn('payment_status', ['partial', 'paid']))
            ->when($validated['date'] ?? null, fn ($query, $date) => $query
                ->where('order_date', '>=', $date)
                ->where('order_date', '<', Carbon::parse($date)->addDay()->toDateString()))
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
                    'payment_status' => $transaction->payment_status,
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
        if ($user?->role === 'sales_associate') {
            $hubId = $request->integer('hub_id') ?: $user->store_hub_id;
            abort_unless($hubId && $user->canAccessHub((int) $hubId), 403);
        } elseif (in_array($user?->role, ['admin', 'inventory_staff'], true)) {
            $hubId = $request->exists('hub_id')
                ? ($request->integer('hub_id') ?: null)
                : $user?->store_hub_id;
        } elseif ($user && ! in_array($user->role, ['admin', 'inventory_staff'], true)) {
            $hubId = $user->store_hub_id;
        } else {
            $hubId = null;
        }

        $fullyBookedOrders = in_array($user?->role, ['admin', 'inventory_staff'], true)
            && (! $request->filled('type') || $request->input('type') === 'fully_booked')
            && (! in_array($request->input('status'), ['approved'], true))
            ? FullyBookedOrder::with(['storeHub', 'salesStaff', 'submitter', 'reviewer', 'pullOutBy', 'items.product', 'attachments'])
                ->whereIn('status', ['reviewed', 'rejected'])
                ->when($hubId, fn ($query) => $query->where('store_hub_id', $hubId))
                ->when($request->filled('month'), function ($query) use ($request) {
                    $start = $request->input('month').'-01 00:00:00';
                    $end = now()->parse($request->input('month').'-01')->endOfMonth()->toDateString().' 23:59:59';
                    $query->where(function ($query) use ($start, $end) {
                        $query->where(function ($query) use ($start, $end) {
                            $query->where('status', 'rejected')->whereBetween('reviewed_at', [$start, $end]);
                        })->orWhere(function ($query) use ($start, $end) {
                            $query->where('status', '!=', 'rejected')->whereBetween('created_at', [$start, $end]);
                        });
                    });
                })
                ->when(in_array($request->input('status'), ['reviewed', 'rejected'], true), fn ($query) => $query->where('status', $request->input('status')))
                ->latest()
                ->get()
            : collect();

        $transactions = InventoryTransaction::with([
            'product', 'storeHub', 'sourceHub', 'targetHub', 'creator',
            'nationalProduct', 'nationalPullout', 'nationalPulloutItem',
            'salesTransaction.user', 'salesTransaction.confirmedBy',
            'salesTransaction.items.replacements.reviewer',
            'salesTransaction.items.replacements.originalProduct',
            'salesTransaction.items.replacements.replacementProduct',
            'productReplacement.originalProduct', 'productReplacement.replacementProduct',
            'productReplacement.reviewer', 'productReplacement.transaction', 'reviewer',
        ])
            ->when(! in_array($user?->role, ['admin', 'inventory_staff'], true), function ($query) {
                $query->where(function ($query) {
                    $query->where('type', '!=', 'branch_transfer')
                        ->orWhere('status', 'approved');
                });
            })
            ->when($hubId, fn ($query) => $query->where('store_hub_id', $hubId))
            ->when($request->filled('type') && $user?->role !== 'sales_associate', function ($query) use ($request) {
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
        $linkedFullyBookedOrders = FullyBookedOrder::with(['storeHub', 'salesStaff', 'submitter', 'reviewer', 'pullOutBy', 'items.product', 'attachments'])
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

                return $order && (! in_array($request->input('status'), ['pending', 'reviewed', 'rejected'], true)
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

        $rejectedSales = in_array($user?->role, ['admin', 'inventory_staff'], true)
            && (! $request->filled('type') || $request->input('type') === 'rejected_sale')
            && (! $request->filled('status') || $request->input('status') === 'rejected')
            ? PendingSale::with(['storeHub', 'submittedBy', 'rejectedBy'])
                ->where('status', 'rejected')
                ->when($hubId, fn ($query) => $query->where('store_hub_id', $hubId))
                ->when($request->filled('month'), function ($query) use ($request) {
                    $start = $request->input('month').'-01 00:00:00';
                    $end = now()->parse($request->input('month').'-01')->endOfMonth()->toDateString().' 23:59:59';
                    $query->whereBetween('rejected_at', [$start, $end]);
                })
                ->latest('rejected_at')
                ->get()
            : collect();
        $rejectedProductIds = $rejectedSales
            ->flatMap(fn ($sale) => collect($sale->items)->pluck('product_id'))
            ->filter()
            ->unique();
        $rejectedProducts = $rejectedProductIds->isNotEmpty()
            ? Product::whereIn('id', $rejectedProductIds)->get()->keyBy('id')
            : collect();
        $rejectedSaleGroups = $rejectedSales->mapWithKeys(function ($sale) use ($rejectedProducts) {
            $entries = collect($sale->items)->map(function ($item, $index) use ($sale, $rejectedProducts) {
                $rejectedAt = $sale->rejected_at ?? $sale->updated_at ?? $sale->created_at;
                $transaction = new InventoryTransaction;
                $transaction->setRawAttributes([
                    'id' => 'rejected-sale-'.$sale->id.'-'.$index,
                    'type' => 'rejected_sale',
                    'reference' => $sale->invoice_number ?: ($sale->order_number ?? 'ORDER-'.$sale->id),
                    'store_hub_id' => $sale->store_hub_id,
                    'channel' => $sale->sales_channel,
                    'quantity' => (int) ($item['quantity'] ?? 0),
                    'occurred_on' => $rejectedAt?->toDateString(),
                    'created_at' => $rejectedAt,
                    'created_by' => $sale->submitted_by,
                    'status' => 'rejected',
                ], true);
                $transaction->setRelation('storeHub', $sale->storeHub);
                $transaction->setRelation('creator', $sale->submittedBy);
                $transaction->setRelation('rejectedSale', $sale);
                $transaction->setRelation('product', $rejectedProducts->get($item['product_id'] ?? null));
                $transaction->setAttribute('unit_price', (float) ($item['unit_price'] ?? $item['price'] ?? 0));
                $transaction->setAttribute('line_total', (float) ($item['line_total'] ?? 0));

                return $transaction;
            });

            return ['rejected-sale-'.$sale->id => $entries];
        });

        $transactionGroups = $transactions->groupBy(function (InventoryTransaction $transaction) {
            if ($transaction->type === 'return' && $transaction->national_pullout_id) {
                return 'national-return-'.($transaction->transfer_batch_id ?: $transaction->id);
            }
            if ($transaction->type === 'return' && $transaction->channel === 'event') {
                $batch = $transaction->transfer_batch_id
                    ?: implode('-', [
                        $transaction->store_hub_id,
                        $transaction->reference,
                        $transaction->created_by,
                        $transaction->created_at?->format('YmdHis') ?: $transaction->id,
                    ]);

                return 'event-return-'.$batch;
            }
            if (in_array($transaction->type, ['return', 'sold'], true) && $transaction->salesTransaction) {
                return $transaction->type.'-'.$transaction->salesTransaction->id;
            }
            if ($transaction->productReplacement) {
                return 'replacement-'.$transaction->productReplacement->id;
            }
            if (in_array($transaction->type, ['stock_transfer', 'branch_transfer'], true)) {
                $legacyTransferGroup = $transaction->reference
                    ? $transaction->reference.'-'.$transaction->source_hub_id.'-'.$transaction->target_hub_id
                    : $transaction->source_hub_id.'-'.$transaction->target_hub_id.'-'.$transaction->occurred_on;
                $transferGroup = $transaction->transfer_batch_id ?: $legacyTransferGroup;

                return $transaction->type.'-'.$transferGroup;
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
                    'occurred_on' => ($order->status === 'rejected'
                        ? ($order->reviewed_at ?? $order->created_at)
                        : $order->created_at)?->toDateString(),
                    'created_at' => $order->status === 'rejected'
                        ? ($order->reviewed_at ?? $order->created_at)
                        : $order->created_at,
                    'created_by' => $order->submitted_by,
                    'status' => $order->status,
                ], true);
                $transaction->setRelation('storeHub', $order->storeHub);
                $transaction->setRelation('creator', $order->submitter);
                $transaction->setRelation('fullyBookedOrder', $order);

                return ['fully-booked-'.$order->id => collect([$transaction])];
            });

        $nationalPullouts = in_array($user?->role, ['admin', 'inventory_staff'], true)
            && (! $request->filled('type') || $request->input('type') === 'national_bookstore_pullout')
            && ! $request->filled('status')
            ? NationalPullout::with(['items.product', 'storeHub', 'creator'])
                ->when($hubId, fn ($query) => $query->where('store_hub_id', $hubId))
                ->when($user?->role === 'inventory_staff', fn ($query) => $query->where('store_hub_id', $user->store_hub_id))
                ->when($request->filled('month'), fn ($query) => $query->whereBetween('occurred_on', [
                    $request->input('month').'-01',
                    now()->parse($request->input('month').'-01')->endOfMonth()->toDateString(),
                ]))
                ->latest('occurred_on')
                ->latest()
                ->get()
            : collect();
        $nationalPulloutGroups = $nationalPullouts->mapWithKeys(function (NationalPullout $pullout) {
            $entries = $pullout->items->map(function ($item) use ($pullout) {
                $transaction = new InventoryTransaction;
                $transaction->setRawAttributes([
                    'id' => 'national-pullout-'.$pullout->id.'-'.$item->id,
                    'type' => 'national_bookstore_pullout',
                    'reference' => $pullout->po_number,
                    'store_hub_id' => $pullout->store_hub_id,
                    'source' => 'National Bookstore',
                    'quantity' => $item->actual_pullout,
                    'occurred_on' => $pullout->occurred_on?->toDateString(),
                    'created_at' => $pullout->created_at,
                    'notes' => $pullout->remarks,
                    'created_by' => $pullout->created_by,
                ], true);
                $transaction->setRelation('storeHub', $pullout->storeHub);
                $transaction->setRelation('creator', $pullout->creator);
                $transaction->setRelation('nationalPullout', $pullout);
                $transaction->setRelation('nationalPulloutItem', $item);

                return $transaction;
            });

            return ['national-pullout-'.$pullout->id => $entries];
        });
        $transactionGroups = collect($transactionGroups->all())
            ->merge($fullyBookedGroups)
            ->merge($nationalPulloutGroups)
            ->merge($rejectedSaleGroups)
            ->map(function ($group) {
                return $group->sortBy(function (InventoryTransaction $transaction) {
                    $itemId = $transaction->nationalPulloutItem?->item_id
                        ?? $transaction->product?->item_id
                        ?? $transaction->nationalProduct?->item_id;

                    return $itemId === null ? '1:' : '0:'.$itemId;
                }, SORT_NATURAL | SORT_FLAG_CASE)->values();
            })
            ->sort(function ($leftGroup, $rightGroup) {
                $sortValues = static function ($group): array {
                    $transaction = $group->first();

                    return [
                        $transaction->occurred_on?->timestamp ?? 0,
                        $group->max(fn ($entry) => $entry->created_at?->timestamp ?? 0),
                        (string) $transaction->getKey(),
                    ];
                };

                [$leftDate, $leftTime, $leftId] = $sortValues($leftGroup);
                [$rightDate, $rightTime, $rightId] = $sortValues($rightGroup);

                return ($rightDate <=> $leftDate)
                    ?: ($rightTime <=> $leftTime)
                    ?: strnatcasecmp($rightId, $leftId);
            })
            ->values();
        $page = LengthAwarePaginator::resolveCurrentPage();
        $transactions = new LengthAwarePaginator(
            $transactionGroups->forPage($page, 20),
            $transactionGroups->count(),
            20,
            $page,
            ['path' => $request->url(), 'query' => $request->query()]
        );

        $hubs = in_array($user?->role, ['admin', 'inventory_staff'], true)
            ? StoreHub::where('status', 'active')->orderBy('name')->get()
            : StoreHub::whereIn('id', $user?->accessibleStoreHubIds() ?? [])->where('status', 'active')->get();

        $fullyBookedPulloutCount = in_array($user?->role, ['admin', 'inventory_staff'], true)
            ? FullyBookedOrder::whereIn('status', ['pending', 'reviewed'])
                ->whereNull('pulled_out_at')
                ->when($hubId, fn ($query) => $query->where('store_hub_id', $hubId))
                ->count()
            : 0;

        $nationalHeadOffice = StoreHub::query()
            ->where('is_head_office', true)
            ->where('status', 'active')
            ->when($user?->role === 'inventory_staff', fn ($query) => $query->whereKey($user->store_hub_id))
            ->first();
        $canUseNationalPullout = in_array($user?->role, ['admin', 'inventory_staff'], true)
            && $nationalHeadOffice !== null;
        $nationalHeadOfficeId = $nationalHeadOffice?->id;

        return view('inventory-transactions.index', compact(
            'transactions',
            'hubs',
            'hubId',
            'fullyBookedPulloutCount',
            'canUseNationalPullout',
            'nationalHeadOfficeId'
        ));
    }

    public function salesOrderSlip(int $salesTransaction)
    {
        $sale = SalesTransaction::with('storeHub')->findOrFail($salesTransaction);
        abort_unless(
            $sale->storeHub && auth()->user()?->canAccessHub((int) $sale->store_hub_id),
            403
        );

        $isEventSale = str_replace(['-', ' '], '_', strtolower(trim((string) $sale->channel_type))) === 'event';
        $path = $isEventSale
            ? ($sale->event_attachments['order_slip'] ?? null)
            : $sale->order_slip;
        abort_unless(is_string($path) && $path !== '', 404);

        $diskName = AttachmentStorage::diskName(
            $isEventSale ? AttachmentStorage::EVENTS : AttachmentStorage::SALES
        );
        $disk = Storage::disk($diskName);
        $availability = config("filesystems.disks.{$diskName}.driver") === 's3'
            ? true
            : AttachmentAvailability::check($disk, $path);
        abort_if($availability === null, 503, 'Attachment storage is temporarily unavailable.');
        abort_unless($availability, 404, 'The order attachment is missing or empty.');

        return AttachmentResponse::inline($disk, $path, headers: [
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    public function create(Request $request)
    {
        $type = $request->route('type') ?? $request->input('type', 'restock');
        abort_unless(in_array($type, self::TYPES, true), 404);

        $user = auth()->user();
        if ($type === 'sponsor_workshop' && $user?->role === 'sales_marketing_staff') {
            abort_unless($user->hasSalesChannel('fully_booked'), 403);
        }
        if ($type === 'branch_transfer') {
            abort_unless(
                $user?->can('manage-inventory') || $user?->can('submit-branch-transfers'),
                403
            );
        }
        $hubId = $request->integer('hub_id') ?: $request->old('store_hub_id') ?: $user?->store_hub_id;
        if ($type === 'return' && ! in_array($user?->role, ['admin', 'inventory_staff'], true)) {
            abort_unless(
                $hubId && in_array((int) $hubId, $user?->accessibleStoreHubIds() ?? [], true),
                403,
                'You can only select an assigned store.'
            );
        }
        $hubs = in_array($user?->role, ['admin', 'inventory_staff'], true)
            ? StoreHub::where('status', 'active')->orderBy('name')->get()
            : StoreHub::whereIn('id', $user?->accessibleStoreHubIds() ?? [])->where('status', 'active')->orderBy('name')->get();
        $hubs = $hubs->filter(fn (StoreHub $hub) => $hub->status === 'active')->values();
        $allHubs = StoreHub::where('status', 'active')->orderBy('name')->get();
        $allHubs = $allHubs->filter(fn (StoreHub $hub) => $hub->status === 'active')->values();
        $headOffices = $allHubs->filter(fn (StoreHub $hub) => $hub->is_head_office)->values();
        $requestedDirection = $request->route('direction') ?? $request->input('direction');
        $transferDirection = $requestedDirection === 'branch_to_ho'
            ? 'branch_to_ho'
            : 'ho_to_branch';
        if ($type === 'branch_transfer') {
            $hubs = $hubs->filter(fn (StoreHub $hub) => ! $hub->is_head_office)->values();
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
        $products = Product::where('store_hub_id', $hubId)
            ->where('status', 'active')
            ->when($type === 'stock_transfer', fn ($query) => $query->with('stockAllocation'))
            ->orderByCatalog()
            ->limit(100)
            ->get();
        $products->each(fn (Product $product) => $product->setAttribute(
            'transfer_available_stock',
            $type === 'stock_transfer' ? $product->unallocatedStock() : max(0, (int) $product->stock)
        ));
        $sponsorProductOptions = $products->map(fn (Product $product) => [
            'id' => $product->id,
            'item_id' => $product->item_id,
            'barcode' => $product->barcode,
            'name' => $product->name,
            'description' => $product->description ?: $product->name,
            'good_stock_available' => max(0, (int) $product->stock),
            'damaged_stock_available' => max(0, (int) $product->damaged_stock),
            'label' => ($product->item_id ?: $product->id).' — '.($product->name ?: $product->description ?: 'Unnamed product')
                .($product->barcode ? ' · '.$product->barcode : '')
                .' (stock: '.max(0, (int) $product->stock).')',
        ])->values();

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
        $fullyBookedStores = $type === 'sponsor_workshop'
            ? StoreHub::where('status', 'active')->orderByDesc('is_head_office')->orderBy('name')->get()
            : collect();
        $canManageEvent = $user?->can('manage-inventory') ?? false;
        $canSubmitFullyBooked = $user?->role === 'admin'
            || ($user?->role === 'sales_marketing_staff' && $user->hasSalesChannel('fully_booked'));
        $canUseFullyBookedForm = $canManageEvent || $canSubmitFullyBooked;
        $activityType = $canSubmitFullyBooked && ! $canManageEvent
            ? 'fully_booked'
            : ($request->input('activity_type') === 'fully_booked' && $canUseFullyBookedForm ? 'fully_booked' : 'event');
        $fullyBookedOrderSearch = trim((string) $request->query('fully_booked_order_search', ''));
        $fullyBookedOrders = $canManageEvent
            ? FullyBookedOrder::with(['storeHub', 'salesStaff', 'attachments'])
                ->whereNull('pulled_out_at')
                ->where('status', '!=', 'rejected')
                ->when($fullyBookedOrderSearch !== '', fn ($query) => $query->where('order_number', 'like', '%'.$fullyBookedOrderSearch.'%'))
                ->latest()
                ->get()
            : ($canSubmitFullyBooked
                ? FullyBookedOrder::with(['storeHub', 'items', 'attachments'])
                    ->where('submitted_by', $user->id)
                    ->when($fullyBookedOrderSearch !== '', fn ($query) => $query->where('order_number', 'like', '%'.$fullyBookedOrderSearch.'%'))
                    ->latest()
                    ->orderByDesc('id')
                    ->paginate(6)
                    ->withQueryString()
                : collect());

        return view('inventory-transactions.forms.'.$form, compact(
            'hubs', 'allHubs', 'headOffices', 'products', 'hubId', 'type', 'transferSourceHubs',
            'selectedHub', 'transferReference', 'sponsorReference', 'restockReference',
            'transferDirection', 'fullyBookedStaff', 'canManageEvent', 'canSubmitFullyBooked',
            'canUseFullyBookedForm', 'activityType', 'fullyBookedOrders', 'fullyBookedStores',
            'sponsorProductOptions', 'fullyBookedOrderSearch'
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
        if (is_array($request->input('items'))
            && ! ($request->input('type') === 'return' && $this->normalizeChannel($request->input('channel')) === 'national_bookstore')) {
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
            'notes' => ['nullable', 'string', 'max:150'],
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
        $user = auth()->user();
        $isImmediateBranchTransfer = $request->input('type') === 'branch_transfer'
            && $user?->can('manage-inventory');
        if ($request->input('type') === 'branch_transfer') {
            abort_unless($isImmediateBranchTransfer || $user?->can('submit-branch-transfers'), 403);
        }
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
            'notes' => ['nullable', 'string', 'max:150'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.product_id' => ['required', 'exists:products,id'],
            'items.*.quantity' => ['required', 'integer', 'min:1'],
            'items.*.stock_condition' => ['nullable', Rule::in(['good', 'damaged'])],
        ]);
        $sourceHub = StoreHub::findOrFail((int) $validated['store_hub_id']);
        $submittedReference = (string) ($validated['reference'] ?? '');
        $sourceHubCode = $this->transferReferenceHubCode($sourceHub->code);
        $referencePattern = '/^TRF-'.preg_quote($sourceHubCode, '/').'-\d{8}-\d{6}-[A-F0-9]{6}$/';
        $validated['reference'] = preg_match($referencePattern, $submittedReference) === 1
            ? $submittedReference
            : $this->newTransferReference($sourceHub->code);

        if ($user?->role !== 'admin' && $user?->role !== 'inventory_staff' && ! $user?->canAccessHub((int) $validated['store_hub_id'])) {
            abort(403, 'Unauthorized action for this store hub.');
        }

        $transferBatchId = (string) Str::uuid();
        DB::transaction(function () use ($validated, $transferBatchId, $isImmediateBranchTransfer) {
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
                $availableStock = $validated['type'] === 'stock_transfer'
                    ? $product->unallocatedStock()
                    : max(0, (int) $product->stock);
                if ($availableStock < (int) $quantity) {
                    throw ValidationException::withMessages([
                        'items' => $validated['type'] === 'stock_transfer'
                            ? "Not enough unallocated physical stock for {$product->name}."
                            : "Not enough stock for {$product->name}.",
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

                if ($validated['type'] === 'stock_transfer' || $isImmediateBranchTransfer) {
                    $product->decrement('stock', $quantity);
                    $targetProduct->increment('stock', $quantity);
                }
                $isApproved = $validated['type'] === 'stock_transfer' || $isImmediateBranchTransfer;
                InventoryTransaction::create([
                    'type' => $validated['type'],
                    'reference' => $validated['reference'] ?? null,
                    'transfer_batch_id' => $transferBatchId,
                    'status' => $isApproved ? 'approved' : 'pending',
                    'reviewed_by' => $isImmediateBranchTransfer ? auth()->id() : null,
                    'reviewed_at' => $isImmediateBranchTransfer ? now() : null,
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
            if (! $isImmediateBranchTransfer) {
                $this->notifyBranchTransferReview($validated, $transferBatchId);
            }
        } else {
            $this->notifyInventoryStaff($validated);
        }

        if ($validated['type'] === 'branch_transfer') {
            if ($isImmediateBranchTransfer) {
                return redirect()->route('inventory-transactions.index', [
                    'hub_id' => $validated['store_hub_id'],
                    'stock_transfer_saved' => 1,
                    'reference' => $validated['reference'],
                ]);
            }

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
            'notes' => ['nullable', 'string', 'max:150'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.product_id' => ['required', 'exists:products,id'],
            'items.*.quantity' => ['required', 'integer', 'min:1'],
            'items.*.pullout_qty' => ['nullable', 'integer', 'min:0'],
            'items.*.stock_condition' => ['nullable', Rule::in(['good', 'damaged'])],
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
            $quantities = collect($validated['items'])->groupBy(
                fn ($item) => $item['product_id'].':'.($item['stock_condition'] ?? 'good')
            )
                ->map(fn ($rows) => [
                    'quantity' => $rows->sum('quantity'),
                    'pullout_qty' => $rows->sum(fn ($item) => (int) ($item['pullout_qty'] ?? 0)),
                ]);

            $event = EventPullout::create([
                'reference' => $validated['reference'],
                'store_hub_id' => $validated['store_hub_id'],
                'source' => $validated['source'],
                'occurred_on' => $validated['occurred_on'],
                'notes' => $validated['notes'] ?? null,
                'status' => 'open',
                'created_by' => auth()->id(),
            ]);

            foreach ($quantities as $stockKey => $amounts) {
                [$productId, $stockCondition] = explode(':', (string) $stockKey, 2);
                $quantity = $amounts['quantity'];
                $product = $products->get((int) $productId);
                if (! $product) {
                    throw ValidationException::withMessages([
                        'items' => 'One or more selected products are not active in the selected store.',
                    ]);
                }
                $stockColumn = $stockCondition === 'damaged' ? 'damaged_stock' : 'stock';
                if ((int) $product->{$stockColumn} < (int) $quantity) {
                    throw ValidationException::withMessages([
                        'items' => "Not enough {$stockCondition} stock for {$product->name}.",
                    ]);
                }

                $product->decrement($stockColumn, $quantity);
                $eventItem = $event->items()->create([
                    'product_id' => $product->id,
                    'stock_condition' => $stockCondition,
                    'requested_quantity' => $amounts['pullout_qty'] > 0 ? $amounts['pullout_qty'] : null,
                    'quantity' => $quantity,
                ]);
                InventoryTransaction::create([
                    'type' => 'sponsor_workshop',
                    'reference' => $validated['reference'],
                    'store_hub_id' => $product->store_hub_id,
                    'product_id' => $product->id,
                    'event_pullout_id' => $event->id,
                    'event_pullout_item_id' => $eventItem->id,
                    'source' => $validated['source'],
                    'condition' => $stockCondition,
                    'quantity' => $quantity,
                    'pullout_qty' => $amounts['pullout_qty'] > 0 ? $amounts['pullout_qty'] : null,
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
            'notes' => ['nullable', 'string', 'max:150'],
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
        if (strtolower(trim((string) $request->input('channel'))) === 'event') {
            return $this->storeEventReturn($request);
        }
        if ($this->normalizeChannel($request->input('channel')) === 'national_bookstore') {
            return $this->storeNationalBookstoreReturn($request);
        }

        $validated = $request->validate([
            'type' => ['required', Rule::in(['return'])],
            'store_hub_id' => ['required', 'exists:store_hubs,id'],
            'occurred_on' => ['required', 'date'],
            'channel' => ['required', Rule::in(['shopee', 'lazada', 'tiktok', 'online', 'walk_in', 'wholesale', 'fully_booked'])],
            'source' => ['nullable', 'string', 'max:100'],
            'reference' => ['nullable', 'string', 'max:100'],
            'notes' => ['nullable', 'string', 'max:150'],
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
        if (! $hasReturnQuantity && (in_array($validated['channel'], ['fully_booked', 'tiktok', 'shopee', 'lazada'], true)
            || collect($validated['items'])->sum(fn ($item) => (float) ($item['refund_amount'] ?? 0)) <= 0)) {
            throw ValidationException::withMessages([
                'items' => in_array($validated['channel'], ['fully_booked', 'tiktok', 'shopee', 'lazada'], true)
                    ? 'Enter at least one good or damaged return quantity.'
                    : 'Enter at least one good or damaged return quantity, or a refund amount.',
            ]);
        }

        $user = auth()->user();
        if ($user?->role === 'inventory_staff' && ! StoreHub::whereKey($validated['store_hub_id'])->where('status', 'active')->exists()) {
            abort(403, 'Inventory staff can only record returns for an active store.');
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
            if ($returnChannel === 'wholesale' && ! in_array($sale->payment_status, ['partial', 'paid'], true)) {
                throw ValidationException::withMessages([
                    'sales_transaction_id' => 'Only partially paid or fully paid wholesale orders can be returned.',
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
                    ? ProductReplacement::whereKey($item['replacement_id'])
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
                $refundAmount = in_array($returnChannel, ['fully_booked', 'tiktok', 'shopee', 'lazada'], true)
                    || ($returnChannel === 'wholesale' && $sale->payment_status === 'partial') ? 0 : ($quantity > 0
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

                if ($goodQuantity > 0) {
                    $product->increment('stock', $goodQuantity);
                }
                if ($damagedQuantity > 0) {
                    $product->increment('damaged_stock', $damagedQuantity);
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

        $redirect = in_array(auth()->user()?->role, ['admin', 'inventory_staff'], true)
            ? redirect()->route('inventory-transactions.index', [
                'hub_id' => $validated['store_hub_id'],
                'type' => 'return',
            ])
            : redirect()->route('inventory-transactions.return.create', ['hub_id' => $validated['store_hub_id']]);

        return $redirect->with('success', 'Return items recorded successfully.');
    }

    private function storeEventReturn(Request $request)
    {
        $validated = $request->validate([
            'type' => ['required', Rule::in(['return'])],
            'store_hub_id' => ['required', 'exists:store_hubs,id'],
            'occurred_on' => ['required', 'date'],
            'channel' => ['required', Rule::in(['event'])],
            'event_reference' => ['required', 'string', 'max:100'],
            'notes' => ['nullable', 'string', 'max:150'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.product_id' => ['required', 'exists:products,id'],
            'items.*.good_quantity' => ['nullable', 'integer', 'min:0'],
            'items.*.damaged_quantity' => ['nullable', 'integer', 'min:0'],
        ]);
        $user = auth()->user();
        abort_unless(in_array($user?->role, ['admin', 'inventory_staff'], true), 403, 'Only inventory staff can record Event returns.');
        $hub = StoreHub::findOrFail($validated['store_hub_id']);
        abort_unless($hub->status === 'active', 403, 'Event returns can only be recorded for an active store.');

        $quantities = collect($validated['items'])
            ->groupBy('product_id')
            ->map(fn ($rows) => [
                'good' => $rows->sum(fn ($item) => (int) ($item['good_quantity'] ?? 0)),
                'damaged' => $rows->sum(fn ($item) => (int) ($item['damaged_quantity'] ?? 0)),
            ]);
        if ($quantities->sum(fn ($amounts) => $amounts['good'] + $amounts['damaged']) < 1) {
            throw ValidationException::withMessages(['items' => 'Enter at least one good or damaged return quantity.']);
        }

        $batchId = (string) Str::uuid();
        DB::transaction(function () use ($validated, $quantities, $batchId) {
            $event = EventPullout::query()
                ->where('store_hub_id', $validated['store_hub_id'])
                ->where('reference', $validated['event_reference'])
                ->lockForUpdate()
                ->first();
            if ($event?->status === 'closed') {
                throw ValidationException::withMessages(['event_reference' => 'Closed events cannot accept additional returns.']);
            }
            $pullouts = InventoryTransaction::query()
                ->where('store_hub_id', $validated['store_hub_id'])
                ->where('type', 'sponsor_workshop')
                ->where('reference', $validated['event_reference'])
                ->lockForUpdate()
                ->get()
                ->groupBy('product_id');
            if ($pullouts->isEmpty()) {
                throw ValidationException::withMessages(['event_reference' => 'The selected Event pullout could not be found.']);
            }

            $products = Product::where('store_hub_id', $validated['store_hub_id'])
                ->whereIn('id', $quantities->keys())
                ->lockForUpdate()
                ->get()
                ->keyBy('id');
            $alreadyReturned = InventoryTransaction::query()
                ->where('store_hub_id', $validated['store_hub_id'])
                ->where('type', 'return')
                ->where('channel', 'event')
                ->where('reference', $validated['event_reference'])
                ->whereIn('product_id', $quantities->keys())
                ->selectRaw('product_id, condition, SUM(quantity) as quantity')
                ->groupBy('product_id', 'condition')
                ->get()
                ->groupBy('product_id');
            $alreadySold = $event
                ? InventoryTransaction::query()
                    ->where('event_pullout_id', $event->id)
                    ->where('type', 'sold')
                    ->where('channel', 'event')
                    ->whereIn('product_id', $quantities->keys())
                    ->selectRaw('product_id, condition, SUM(quantity) as quantity')
                    ->groupBy('product_id', 'condition')
                    ->get()
                    ->groupBy('product_id')
                : collect();

            foreach ($quantities as $productId => $amounts) {
                $productPullouts = $pullouts->get($productId);
                $product = $products->get((int) $productId);
                if (! $product || ! $productPullouts) {
                    throw ValidationException::withMessages([
                        'items' => 'Each returned product must belong to the selected Event and cannot exceed its remaining pullout quantity.',
                    ]);
                }

                $soldRows = $alreadySold->get($productId, collect());
                $returnedRows = $alreadyReturned->get($productId, collect());
                $conditionQuantity = fn ($conditionRows, string $condition) => (int) $conditionRows
                    ->filter(fn ($row) => strtolower((string) ($row->condition ?: 'good')) === $condition)
                    ->sum('quantity');
                foreach (['good' => 'Good', 'damaged' => 'Damaged'] as $condition => $conditionLabel) {
                    $available = max(
                        0,
                        $conditionQuantity($productPullouts, $condition)
                            - $conditionQuantity($soldRows, $condition)
                            - $conditionQuantity($returnedRows, $condition)
                    );
                    if ($amounts[$condition] > $available) {
                        throw ValidationException::withMessages([
                            'items' => "{$conditionLabel} return quantity for {$product->name} exceeds the remaining {$conditionLabel} Event stock. Only {$available} {$conditionLabel} item(s) can be returned.",
                        ]);
                    }
                }

                if ($amounts['good'] > 0) {
                    $product->increment('stock', $amounts['good']);
                }
                if ($amounts['damaged'] > 0) {
                    $product->increment('damaged_stock', $amounts['damaged']);
                }

                foreach (['good' => $amounts['good'], 'damaged' => $amounts['damaged']] as $condition => $conditionQuantity) {
                    if ($conditionQuantity < 1) {
                        continue;
                    }
                    InventoryTransaction::create([
                        'type' => 'return',
                        'reference' => $validated['event_reference'],
                        'store_hub_id' => $product->store_hub_id,
                        'product_id' => $product->id,
                        'event_pullout_id' => $event?->id,
                        'channel' => 'event',
                        'source' => $productPullouts->first()->source,
                        'condition' => $condition,
                        'quantity' => $conditionQuantity,
                        'occurred_on' => $validated['occurred_on'],
                        'notes' => $validated['notes'] ?? null,
                        'created_by' => auth()->id(),
                        'transfer_batch_id' => $batchId,
                    ]);
                }
            }
        });

        $redirect = in_array(auth()->user()?->role, ['admin', 'inventory_staff'], true)
            ? redirect()->route('inventory-transactions.index', [
                'hub_id' => $validated['store_hub_id'],
                'type' => 'return',
            ])
            : redirect()->route('inventory-transactions.return.create', ['hub_id' => $validated['store_hub_id']]);

        return $redirect->with('success', 'Return items recorded successfully.');
    }

    private function storeNationalBookstoreReturn(Request $request)
    {
        $validated = $request->validate([
            'type' => ['required', Rule::in(['return'])],
            'store_hub_id' => ['required', 'integer', 'exists:store_hubs,id'],
            'occurred_on' => ['required', 'date'],
            'channel' => ['required', Rule::in(['national_bookstore'])],
            'reference' => ['required', 'string', 'max:100'],
            'notes' => ['nullable', 'string', 'max:150'],
            'national_pullout_id' => ['required', 'integer', 'exists:national_pullouts,id'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.national_pullout_item_id' => ['required', 'integer', 'distinct', 'exists:national_pullout_items,id'],
            'items.*.national_product_id' => ['required', 'integer', 'distinct', 'exists:national_products,id'],
            'items.*.good_quantity' => ['nullable', 'integer', 'min:0'],
            'items.*.damaged_quantity' => ['nullable', 'integer', 'min:0'],
        ]);
        $user = $request->user();
        $hub = StoreHub::whereKey($validated['store_hub_id'])->where('status', 'active')->firstOrFail();
        abort_unless(
            in_array($user?->role, ['admin', 'inventory_staff'], true) && $hub->is_head_office,
            403,
            'National Bookstore returns are available only in Head Office mode.'
        );
        if (collect($validated['items'])->sum(
            fn ($item) => (int) ($item['good_quantity'] ?? 0) + (int) ($item['damaged_quantity'] ?? 0)
        ) < 1) {
            throw ValidationException::withMessages(['items' => 'Enter at least one good or damaged return quantity.']);
        }

        DB::transaction(function () use ($validated) {
            $pullout = NationalPullout::whereKey($validated['national_pullout_id'])
                ->where('store_hub_id', $validated['store_hub_id'])
                ->lockForUpdate()
                ->firstOrFail();
            $pulloutItems = $pullout->items()->whereIn('id', collect($validated['items'])->pluck('national_pullout_item_id'))
                ->lockForUpdate()->get()->keyBy('id');
            $products = NationalProduct::whereIn('id', collect($validated['items'])->pluck('national_product_id'))
                ->lockForUpdate()->get()->keyBy('id');
            $batchId = (string) Str::uuid();

            foreach ($validated['items'] as $item) {
                $pulloutItem = $pulloutItems->get((int) $item['national_pullout_item_id']);
                $product = $products->get((int) $item['national_product_id']);
                if (! $pulloutItem || ! $product || (int) $pulloutItem->national_product_id !== (int) $product->id) {
                    throw ValidationException::withMessages(['items' => 'Each returned item must belong to the selected P.O. number.']);
                }

                $goodQuantity = (int) ($item['good_quantity'] ?? 0);
                $damagedQuantity = (int) ($item['damaged_quantity'] ?? 0);
                $quantity = $goodQuantity + $damagedQuantity;
                $alreadyReturned = InventoryTransaction::where('type', 'return')
                    ->where('national_pullout_item_id', $pulloutItem->id)
                    ->sum('quantity');
                if ($quantity > $pulloutItem->actual_pullout - (int) $alreadyReturned) {
                    throw ValidationException::withMessages([
                        'items' => "The return quantity for {$pulloutItem->product_name} exceeds the remaining pull-out quantity.",
                    ]);
                }
                if ($quantity < 1) {
                    continue;
                }

                if ($goodQuantity > 0) {
                    $product->increment('stock', $goodQuantity);
                }
                if ($damagedQuantity > 0) {
                    $product->increment('damaged_stock', $damagedQuantity);
                }
                foreach (['good' => $goodQuantity, 'damaged' => $damagedQuantity] as $condition => $conditionQuantity) {
                    if ($conditionQuantity < 1) {
                        continue;
                    }
                    InventoryTransaction::create([
                        'type' => 'return',
                        'reference' => $pullout->po_number,
                        'national_pullout_id' => $pullout->id,
                        'national_pullout_item_id' => $pulloutItem->id,
                        'national_product_id' => $product->id,
                        'store_hub_id' => $pullout->store_hub_id,
                        'product_id' => null,
                        'channel' => 'national_bookstore',
                        'source' => 'National Bookstore',
                        'condition' => $condition,
                        'quantity' => $conditionQuantity,
                        'refund_amount' => 0,
                        'occurred_on' => $validated['occurred_on'],
                        'notes' => $validated['notes'] ?? null,
                        'created_by' => auth()->id(),
                        'transfer_batch_id' => $batchId,
                    ]);
                }
            }
        });

        $this->notifyInventoryStaff($validated);

        return redirect()->route('inventory-transactions.index', [
            'hub_id' => $validated['store_hub_id'],
            'type' => 'return',
        ])->with('success', 'National Bookstore return items recorded and restored to National Inventory.');
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

        $channel = $this->normalizeChannel($sale->channel_type);
        $url = match ($channel) {
            'shopee', 'lazada' => route('hub.marketplace-returns', ['hub' => $sale->store_hub_id, 'channel' => $channel]),
            'tiktok' => route('hub.tiktok-returns', ['hub' => $sale->store_hub_id]),
            'fully_booked' => route('hub.fully-booked-returns', ['hub' => $sale->store_hub_id]),
            default => route('hub.report', ['hub' => $sale->store_hub_id, 'channel' => $channel ?: 'all']),
        };
        $orderNumber = $sale->order_number ?: $sale->id;
        $actorName = auth()->user()?->name ?? 'Inventory staff';
        $message = match ($channel) {
            'shopee', 'lazada' => sprintf(
                'Return items for order %s were recorded by %s. Returned quantities and conditions are now visible in %s Returns.',
                $orderNumber,
                $actorName,
                ucfirst($channel)
            ),
            'tiktok' => sprintf(
                'Return items for order %s were recorded by %s. Returned quantities and conditions are now visible in TikTok Returns.',
                $orderNumber,
                $actorName
            ),
            'fully_booked' => sprintf(
                'Return items for Fully Booked order %s were recorded by %s. Returned quantities and conditions are now visible in Fully Booked Returns.',
                $orderNumber,
                $actorName
            ),
            default => sprintf(
                'Return items were recorded for order %s by %s. Good and damaged quantities are now reflected in the sales report.',
                $orderNumber,
                $actorName
            ),
        };

        $submitter->notify(new InventoryWorkflowNotification(
            'return_recorded',
            $message,
            (int) $sale->store_hub_id,
            $url,
            channel: $channel,
            reference: $sale->order_number,
        ));
    }

    private function normalizeChannel(?string $channel): ?string
    {
        return match (str_replace(['-', ' '], '_', strtolower(trim((string) $channel)))) {
            'shopee' => 'shopee', 'lazada' => 'lazada', 'tiktok' => 'tiktok',
            'online', 'online_order', 'online_sales', 'event' => 'online', 'walk_in', 'walkin' => 'walk_in',
            'wholesale' => 'wholesale', 'fully_booked' => 'fully_booked',
            'national_bookstore', 'national_bookstore_pullout' => 'national_bookstore',
            default => null,
        };
    }
}
