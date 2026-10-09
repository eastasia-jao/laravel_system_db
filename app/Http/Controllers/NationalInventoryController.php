<?php

namespace App\Http\Controllers;

use App\Models\NationalProduct;
use App\Models\StaffActivityLog;
use App\Models\StoreHub;
use App\Support\CsvIdentifier;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class NationalInventoryController extends Controller
{
    private const EXPORT_HEADERS = [
        'ID', 'Item ID', 'Name', 'Description', 'Barcode', 'Brand', 'Retail Group',
        'Retail Department', 'Cost Price', 'Retail Price', 'Wholesale Price',
        'Shopee Price', 'Lazada Price', 'TikTok Price', 'Stock', 'Unit Type', 'Status',
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

        $nationalProducts = $query->orderBy('item_id')->paginate(25)->withQueryString();

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
                    'retail_group' => $value('retail_group') ?: null,
                    'retail_department' => $value('retail_department') ?: null,
                    'cost_price' => $this->decimal($value('cost_price'), $line, 'Cost Price'),
                    'sales_price' => $this->decimal($value('sales_price'), $line, 'Retail Price'),
                    'wholesale_price' => $this->decimal($value('wholesale_price'), $line, 'Wholesale Price'),
                    'shopee_price' => $this->decimal($value('shopee_price'), $line, 'Shopee Price'),
                    'lazada_price' => $this->decimal($value('lazada_price'), $line, 'Lazada Price'),
                    'tiktok_price' => $this->decimal($value('tiktok_price'), $line, 'TikTok Price'),
                    'stock' => $this->stock($value('stock'), $line),
                    'unit_type' => $value('unit_type') ?: null,
                    'status' => in_array(strtolower($value('status')), ['active', 'inactive'], true)
                        ? strtolower($value('status'))
                        : 'active',
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
        DB::transaction(function () use ($rows, &$created, &$updated) {
            foreach ($rows as $row) {
                $product = NationalProduct::where('item_id', $row['item_id'])->first();
                $product ? $updated++ : $created++;
                NationalProduct::updateOrCreate(['item_id' => $row['item_id']], $row);
            }
        });

        StaffActivityLog::create([
            'user_id' => $request->user()->id,
            'store_hub_id' => $hub->id,
            'action_type' => 'product_import',
            'description' => "Imported {$created} new and updated {$updated} National inventory item(s).",
            'details' => ['inventory_scope' => 'national', 'created_count' => $created, 'updated_count' => $updated],
            'ip_address' => $request->ip(),
        ]);

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

        StaffActivityLog::create([
            'user_id' => $request->user()->id,
            'store_hub_id' => $hub->id,
            'action_type' => 'product_export',
            'description' => "Exported {$count} National inventory item(s) to CSV.",
            'details' => ['inventory_scope' => 'national', 'item_count' => $count],
            'ip_address' => $request->ip(),
        ]);

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
                        $product->retail_group,
                        $product->retail_department,
                        $product->cost_price,
                        $product->sales_price,
                        $product->wholesale_price,
                        $product->shopee_price,
                        $product->lazada_price,
                        $product->tiktok_price,
                        $product->stock,
                        $product->unit_type,
                        $product->status,
                    ]);
                }
            });
            fclose($file);
        }, $filename, ['Content-Type' => 'text/csv; charset=UTF-8']);
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
            'retail group' => 'retail_group',
            'retail department' => 'retail_department',
            'cost price' => 'cost_price',
            'retail price', 'sales price' => 'sales_price',
            'wholesale price' => 'wholesale_price',
            'shopee price' => 'shopee_price',
            'lazada price' => 'lazada_price',
            'tiktok price' => 'tiktok_price',
            'stock', 'quantity' => 'stock',
            'unit type', 'unit' => 'unit_type',
            'status' => 'status',
            default => Str::snake($header),
        };
    }

    private function decimal(string $value, int $line, string $field): ?float
    {
        if ($value === '') {
            return null;
        }
        $normalized = str_replace([',', '₱', 'P'], '', $value);
        if (! is_numeric($normalized) || (float) $normalized < 0) {
            throw ValidationException::withMessages(['file' => "Row {$line} {$field} must be a non-negative number."]);
        }

        return round((float) $normalized, 2);
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
}
