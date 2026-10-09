<?php

namespace App\Http\Controllers;

use App\Models\NationalProduct;
use App\Models\StaffActivityLog;
use App\Models\StoreHub;
use App\Support\CsvIdentifier;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class NationalInventoryController extends Controller
{
    private const EXPORT_HEADERS = [
        'ID', 'Item ID', 'Name', 'Description', 'Barcode', 'Brand', 'Stock', 'Unit Type',
    ];

    public function index(Request $request)
    {
        $hub = $this->headOffice($request);
        $query = NationalProduct::query();

        if ($request->filled('search')) {
            $search = trim((string) $request->input('search'));
            $query->where(function ($builder) use ($search) {
                $builder->where('name', 'like', "%{$search}%")
                    ->orWhere('item_id', 'like', "%{$search}%")
                    ->orWhere('barcode', 'like', "%{$search}%")
                    ->orWhere('brand', 'like', "%{$search}%");
            });
        }

        $this->orderByItemId($query);
        $nationalProducts = $query->paginate(25)->withQueryString();

        return view('products.national', compact('nationalProducts', 'hub'));
    }

    public function import(Request $request)
    {
        $hub = $this->headOffice($request);
        $request->validate([
            'file' => ['required', 'file', 'mimes:csv,txt', 'max:10240'],
        ]);

        $stream = fopen($request->file('file')->getRealPath(), 'rb');
        if (! $stream) {
            throw ValidationException::withMessages(['file' => 'The CSV file could not be opened.']);
        }

        $header = fgetcsv($stream);
        if (! is_array($header)) {
            fclose($stream);
            throw ValidationException::withMessages(['file' => 'The CSV file must include a header row.']);
        }

        $columns = collect($header)->mapWithKeys(fn ($value, $index) => [
            $this->normalizeHeader((string) $value) => $index,
        ]);
        foreach (['item_id', 'name'] as $required) {
            if (! $columns->has($required)) {
                fclose($stream);
                throw ValidationException::withMessages(['file' => 'The CSV file must include Item ID and Name columns.']);
            }
        }

        $rows = [];
        $seenItemIds = [];
        $line = 1;
        try {
            while (($values = fgetcsv($stream)) !== false) {
                $line++;
                if (count(array_filter($values, fn ($value) => trim((string) $value) !== '')) === 0) {
                    continue;
                }

                $value = fn (string $key) => $columns->has($key) ? trim((string) ($values[$columns[$key]] ?? '')) : '';
                $itemId = CsvIdentifier::read($value('item_id'), "Row {$line} Item ID");
                $name = $value('name');
                if ($itemId === '' || $name === '') {
                    throw ValidationException::withMessages(['file' => "Row {$line} requires both Item ID and Name."]);
                }
                if (isset($seenItemIds[$itemId])) {
                    throw ValidationException::withMessages(['file' => "Item ID {$itemId} is duplicated in rows {$seenItemIds[$itemId]} and {$line}."]);
                }
                $seenItemIds[$itemId] = $line;

                $rows[] = [
                    'item_id' => $itemId,
                    'name' => $name,
                    'description' => $value('description') ?: null,
                    'barcode' => ($barcode = CsvIdentifier::read($value('barcode'), "Row {$line} Barcode")) !== '' ? $barcode : null,
                    'brand' => $value('brand') ?: null,
                    'stock' => $this->stock($value('stock'), $line),
                    'unit_type' => $value('unit_type') ?: null,
                ];
            }
        } finally {
            fclose($stream);
        }

        if ($rows === []) {
            throw ValidationException::withMessages(['file' => 'The CSV file contains no product rows.']);
        }

        $created = 0;
        $updated = 0;
        DB::transaction(function () use ($rows, $request, $hub, &$created, &$updated) {
            $logItems = [];
            foreach ($rows as $row) {
                $product = NationalProduct::where('item_id', $row['item_id'])->first();
                $stockBefore = $product?->stock ?? 0;
                $operation = $product ? 'updated' : 'created';
                $product ? $updated++ : $created++;
                $product = NationalProduct::updateOrCreate(['item_id' => $row['item_id']], $row);
                $logItems[] = [
                    'product_id' => null,
                    'item_id' => $product->item_id,
                    'product_name' => $product->name,
                    'operation' => $operation,
                    'stock_before' => $stockBefore,
                    'stock_after' => $product->stock,
                    'details' => ['inventory_scope' => 'national', 'national_product_id' => $product->id],
                ];
            }

            $activityLog = StaffActivityLog::create([
                'user_id' => $request->user()->id,
                'store_hub_id' => $hub->id,
                'action_type' => 'product_import',
                'description' => "Imported {$created} new and updated {$updated} National inventory item(s).",
                'details' => ['inventory_scope' => 'national', 'created_count' => $created, 'updated_count' => $updated],
                'ip_address' => $request->ip(),
            ]);
            $activityLog->items()->createMany($logItems);
        });

        return redirect()->route('national-inventory.index', ['hub_id' => $hub->id])
            ->with('success', "National inventory imported: {$created} created, {$updated} updated.");
    }

    public function export(Request $request)
    {
        $hub = $this->headOffice($request);
        $request->validate([
            'export_all' => ['sometimes', 'boolean'],
            'product_ids' => ['sometimes', 'array', 'max:5000'],
            'product_ids.*' => ['integer', 'distinct', 'exists:national_products,id'],
        ]);

        $query = NationalProduct::query();
        if (! $request->boolean('export_all')) {
            $ids = $request->input('product_ids', []);
            if ($ids === []) {
                throw ValidationException::withMessages(['product_ids' => 'Select at least one National product or choose Export All.']);
            }
            $query->whereIn('id', $ids);
        }
        $count = (clone $query)->count();
        if ($count === 0) {
            throw ValidationException::withMessages(['product_ids' => 'No National inventory products are available to export.']);
        }

        $activityLog = StaffActivityLog::create([
            'user_id' => $request->user()->id,
            'store_hub_id' => $hub->id,
            'action_type' => 'product_export',
            'description' => "Exported {$count} National inventory item(s) to CSV.",
            'details' => ['inventory_scope' => 'national', 'item_count' => $count],
            'ip_address' => $request->ip(),
        ]);
        (clone $query)->orderBy('id')->chunk(250, function ($products) use ($activityLog) {
            $activityLog->items()->createMany($products->map(fn (NationalProduct $product) => [
                'product_id' => null,
                'item_id' => $product->item_id,
                'product_name' => $product->name,
                'operation' => 'exported',
                'stock_before' => $product->stock,
                'stock_after' => $product->stock,
                'details' => ['inventory_scope' => 'national', 'national_product_id' => $product->id],
            ])->all());
        });

        $filename = 'national_inventory_'.now()->format('Ymd_His').'.csv';
        return response()->streamDownload(function () use ($query) {
            $file = fopen('php://output', 'wb');
            fwrite($file, "\xEF\xBB\xBF");
            fputcsv($file, self::EXPORT_HEADERS);
            $query->orderBy('id')->chunk(250, function ($products) use ($file) {
                foreach ($products as $product) {
                    fputcsv($file, [
                        $product->id,
                        CsvIdentifier::write($product->item_id, "National product {$product->id} Item ID"),
                        $product->name,
                        $product->description,
                        CsvIdentifier::write($product->barcode, "National product {$product->id} Barcode"),
                        $product->brand,
                        $product->stock,
                        $product->unit_type,
                    ]);
                }
            });
            fclose($file);
        }, $filename, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    public function update(Request $request, NationalProduct $nationalProduct)
    {
        $hub = $this->headOffice($request);
        $stockBefore = $nationalProduct->stock;
        $request->merge([
            'item_id' => CsvIdentifier::read($request->input('item_id'), 'Item ID'),
            'barcode' => CsvIdentifier::read($request->input('barcode'), 'Barcode') ?: null,
        ]);
        $validated = $request->validate([
            'item_id' => ['required', 'string', 'max:255', Rule::unique('national_products', 'item_id')->ignore($nationalProduct->id)],
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'barcode' => ['nullable', 'string', 'max:255'],
            'brand' => ['nullable', 'string', 'max:255'],
            'stock' => ['required', 'integer', 'min:0'],
            'unit_type' => ['nullable', 'string', 'max:255'],
        ]);
        $nationalProduct->update($validated);

        $this->logAction($request, $hub, 'product_update', "Updated National product {$nationalProduct->item_id}.", $nationalProduct, 'updated', $stockBefore, $nationalProduct->stock);

        return redirect()->route('national-inventory.index', ['hub_id' => $hub->id])
            ->with('success', 'National product updated successfully.');
    }

    public function toggleStatus(Request $request, NationalProduct $nationalProduct)
    {
        $hub = $this->headOffice($request);
        $previousStatus = $nationalProduct->status;
        $nationalProduct->status = $nationalProduct->status === 'active' ? 'inactive' : 'active';
        $nationalProduct->save();

        $this->logAction($request, $hub, 'product_status_change', "Set National product {$nationalProduct->item_id} to {$nationalProduct->status}.", $nationalProduct, $nationalProduct->status === 'active' ? 'activated' : 'deactivated', $nationalProduct->stock, $nationalProduct->stock, ['status_before' => $previousStatus, 'status_after' => $nationalProduct->status]);

        return redirect()->route('national-inventory.index', ['hub_id' => $hub->id])
            ->with('success', "National product {$nationalProduct->status}.");
    }

    public function destroy(Request $request, NationalProduct $nationalProduct)
    {
        $hub = $this->headOffice($request);
        $itemId = $nationalProduct->item_id;
        $this->logAction($request, $hub, 'product_delete', "Deleted National product {$itemId}.", $nationalProduct, 'deleted', $nationalProduct->stock, null);
        $nationalProduct->delete();

        return redirect()->route('national-inventory.index', ['hub_id' => $hub->id])
            ->with('success', 'National product deleted.');
    }

    private function headOffice(Request $request): StoreHub
    {
        $user = $request->user();
        abort_unless(in_array($user?->role, ['admin', 'inventory_staff'], true), 403);
        $query = StoreHub::query()->where('status', 'active')->where('is_head_office', true);
        if ($user->role === 'inventory_staff') {
            $query->whereKey($user->hub_id);
        }
        $hub = $request->integer('hub_id')
            ? $query->whereKey($request->integer('hub_id'))->first()
            : $query->orderBy('name')->first();
        abort_unless($hub, 403, 'National inventory is available only in an active Head Office mode.');

        return $hub;
    }

    private function normalizeHeader(string $header): string
    {
        $header = Str::of((string) preg_replace('/^\xEF\xBB\xBF/', '', $header))->lower()->replace(['/', '-'], ' ')->squish()->value();

        return match ($header) {
            'id' => 'id',
            'item id', 'item code' => 'item_id',
            'name', 'product name' => 'name',
            'description' => 'description',
            'barcode' => 'barcode',
            'brand' => 'brand',
            'stock', 'quantity' => 'stock',
            'unit type', 'unit' => 'unit_type',
            default => Str::snake($header),
        };
    }

    private function stock(string $value, int $line): int
    {
        if ($value === '') {
            return 0;
        }
        if (filter_var($value, FILTER_VALIDATE_INT) === false || (int) $value < 0) {
            throw ValidationException::withMessages(['file' => "Row {$line} Stock must be a non-negative whole number."]);
        }

        return (int) $value;
    }

    private function logAction(Request $request, StoreHub $hub, string $actionType, string $description, NationalProduct $product, string $operation, ?int $stockBefore, ?int $stockAfter, array $itemDetails = []): void
    {
        $activityLog = StaffActivityLog::create([
            'user_id' => $request->user()->id,
            'store_hub_id' => $hub->id,
            'action_type' => $actionType,
            'description' => $description,
            'details' => ['inventory_scope' => 'national', 'product_id' => $product->id, 'item_id' => $product->item_id],
            'ip_address' => $request->ip(),
        ]);
        $activityLog->items()->create([
            'product_id' => null,
            'item_id' => $product->item_id,
            'product_name' => $product->name,
            'operation' => $operation,
            'stock_before' => $stockBefore,
            'stock_after' => $stockAfter,
            'details' => array_merge(['inventory_scope' => 'national', 'national_product_id' => $product->id], $itemDetails),
        ]);
    }

    private function orderByItemId($query): void
    {
        $driver = DB::connection()->getDriverName();
        if ($driver === 'sqlite') {
            $query->orderByRaw("CASE WHEN item_id <> '' AND item_id NOT GLOB '*[^0-9]*' THEN 0 ELSE 1 END")
                ->orderByRaw("CASE WHEN item_id <> '' AND item_id NOT GLOB '*[^0-9]*' THEN LENGTH(LTRIM(item_id, '0')) END")
                ->orderByRaw("CASE WHEN item_id <> '' AND item_id NOT GLOB '*[^0-9]*' THEN LTRIM(item_id, '0') END");
        } elseif ($driver === 'pgsql') {
            $query->orderByRaw("CASE WHEN item_id ~ '^[0-9]+$' THEN 0 ELSE 1 END")
                ->orderByRaw("CASE WHEN item_id ~ '^[0-9]+$' THEN LENGTH(LTRIM(item_id, '0')) END")
                ->orderByRaw("CASE WHEN item_id ~ '^[0-9]+$' THEN LTRIM(item_id, '0') END");
        } elseif ($driver === 'mysql' || $driver === 'mariadb') {
            $query->orderByRaw("CASE WHEN item_id REGEXP '^[0-9]+$' THEN 0 ELSE 1 END")
                ->orderByRaw("CASE WHEN item_id REGEXP '^[0-9]+$' THEN LENGTH(TRIM(LEADING '0' FROM item_id)) END")
                ->orderByRaw("CASE WHEN item_id REGEXP '^[0-9]+$' THEN TRIM(LEADING '0' FROM item_id) END");
        } else {
            $query->orderByRaw('LENGTH(item_id)');
        }

        $query->orderBy('item_id');
    }
}
