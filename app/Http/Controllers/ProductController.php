<?php

namespace App\Http\Controllers;

use App\Models\Brand;
use App\Models\Department;
use App\Models\Group;
use App\Models\Product;
use App\Models\StoreHub;
use App\Models\StaffActivityLog;
use App\Models\StaffActivityLogItem;
use App\Models\UnitType;
use App\Models\InventoryTransaction;
use App\Models\User;
use App\Notifications\InventoryWorkflowNotification;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class ProductController extends Controller
{
    use AuthorizesRequests;

    public function index(Request $request)
    {
        $user = auth()->user();
        $selectedHubId = $request->query('hub_id');

        if ($user && ! in_array($user->role, ['admin', 'inventory_staff'], true)) {
            $selectedHubId = $user->store_hub_id;
        }

        $selectedHub = $selectedHubId ? StoreHub::find($selectedHubId) : null;
        $query = Product::query();

        if ($selectedHubId) {
            $query->where('store_hub_id', $selectedHubId);
        }

        if ($request->filled('search')) {
            $search = $request->input('search');
            $field = $request->input('field', 'name');

            if ($field === 'item_id') {
                $query->where('item_id', 'LIKE', "%{$search}%");
            } elseif ($field === 'barcode') {
                $query->where('barcode', 'LIKE', "%{$search}%");
            } elseif ($field === 'brand') {
                $query->where('brand', 'LIKE', "%{$search}%");
            } elseif ($field === 'retail_group') {
                $query->where('retail_group', 'LIKE', "%{$search}%");
            } elseif ($field === 'retail_department') {
                $query->where('retail_department', 'LIKE', "%{$search}%");
            } elseif ($field === 'unit_type') {
                $query->where('unit_type', 'LIKE', "%{$search}%");
            } else {
                $query->where('name', 'LIKE', "%{$search}%");
            }
        }

        $products = $query->paginate(10)->appends($request->query());

        $hubs = ($user && ! in_array($user->role, ['admin', 'inventory_staff'], true))
            ? StoreHub::whereKey($user->store_hub_id)->get()
            : StoreHub::all();

        $brands = Brand::all();
        $groups = DB::table('groups')->get();
        $departments = DB::table('departments')->get();
        $unitTypes = UnitType::all();
        $baseUnits = $unitTypes;

        return view('products.index', compact(
            'products', 'hubs', 'selectedHub', 'brands', 'groups', 'departments', 'unitTypes', 'baseUnits'
        ));
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
                Rule::unique('products')->where('store_hub_id', $hub_id),
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

        DB::table('products')->insert([
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
                Rule::unique('products')
                    ->where('store_hub_id', $product->store_hub_id)
                    ->ignore($product->id),
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
        $user = auth()->user();
        if ($user && $user->role !== 'admin' && $user->role !== 'inventory_staff' && $user->store_hub_id && $user->store_hub_id != $hub_id) {
            abort(403, 'Unauthorized action for this store hub.');
        }

        $request->validate(['file' => 'required|mimes:csv,txt,text/csv']);
        $path = $request->file('file')->getRealPath();
        $handle = fopen($path, 'r');
        fgetcsv($handle);
        $logItems = [];
        $createdCount = 0;
        $updatedCount = 0;
        $skippedCount = 0;

        $cleanPrice = function ($value) {
            if (empty($value)) {
                return 0;
            }
            $cleaned = preg_replace('/[^\d.]/', '', $value);

            return is_numeric($cleaned) ? $cleaned : 0;
        };

        while (($row = fgetcsv($handle)) !== false) {
            if (empty(array_filter($row))) {
                $skippedCount++;
                continue;
            }
            $itemId = trim($row[1] ?? $row[0] ?? null);
            if (! $itemId) {
                $skippedCount++;
                continue;
            }

            $existingProduct = Product::where('item_id', $itemId)
                ->where('store_hub_id', $hub_id)
                ->first();
            $stockBefore = $existingProduct ? (int) $existingProduct->stock : null;

            $brandName = trim($row[5] ?? null);
            $groupName = trim($row[6] ?? null);
            $deptName = trim($row[7] ?? null);
            $unitTypeName = trim($row[15] ?? null);

            if (! empty($brandName)) {
                Brand::firstOrCreate(['brand_name' => $brandName]);
            }
            if (! empty($groupName)) {
                Group::firstOrCreate(['name' => $groupName]);
            }
            if (! empty($deptName)) {
                Department::firstOrCreate(['name' => $deptName]);
            }
            if (! empty($unitTypeName)) {
                UnitType::firstOrCreate(['abbreviation' => $unitTypeName], ['status' => 'active']);
            }

            $stockValue = isset($row[14]) && is_numeric(trim($row[14])) ? (int) trim($row[14]) : 0;

            $nameValue = iconv('UTF-8', 'UTF-8//IGNORE', trim($row[2] ?? ''));
            $descriptionValue = iconv('UTF-8', 'UTF-8//IGNORE', trim($row[3] ?? ''));

            // Some source CSVs leave the name column blank and put the
            // actual product text in the description column instead.
            // Falling back keeps products from being saved with no
            // visible name anywhere in the app.
            if ($nameValue === '') {
                $nameValue = $descriptionValue;
            }

            $savedProduct = Product::updateOrCreate(
                ['item_id' => $itemId, 'store_hub_id' => $hub_id],
                [
                    'name' => $nameValue,
                    'description' => $descriptionValue,
                    'barcode' => trim($row[4] ?? null),
                    'brand' => $brandName,
                    'retail_group' => $groupName,
                    'retail_department' => $deptName,
                    'unit_type' => $unitTypeName,
                    'cost_price' => $cleanPrice($row[8] ?? 0),
                    'sales_price' => $cleanPrice($row[9] ?? 0),
                    'wholesale_price' => $cleanPrice($row[10] ?? 0),
                    'shopee_price' => $cleanPrice($row[11] ?? 0),
                    'lazada_price' => $cleanPrice($row[12] ?? 0),
                    'tiktok_price' => $cleanPrice($row[13] ?? 0),
                    'stock' => $stockValue,
                    'status' => 'active',
                ]
            );
            $operation = $existingProduct ? 'updated' : 'created';
            $operation === 'created' ? $createdCount++ : $updatedCount++;
            $logItems[] = [
                'product_id' => $savedProduct?->id,
                'item_id' => $itemId,
                'product_name' => $nameValue !== '' ? $nameValue : ($savedProduct?->name ?? 'Unnamed product'),
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

        return redirect()->route('hub.dashboard', $hub_id)->with(
            'success',
            sprintf(
                'Import completed successfully. %d product item(s) processed: %d created and %d updated.',
                count($logItems),
                $createdCount,
                $updatedCount
            )
        );
    }

    public function export(Request $request, $hubId)
    {
        $user = auth()->user();
        if ($user && $user->role !== 'admin' && $user->role !== 'inventory_staff' && $user->store_hub_id && $user->store_hub_id != $hubId) {
            abort(403, 'Unauthorized action.');
        }

        $productIds = $request->input('product_ids', []);
        $query = Product::where('store_hub_id', $hubId);

        if (! empty($productIds)) {
            $query->whereIn('id', $productIds);
        } else {
            $query->whereRaw('1 = 0');
        }

        $products = $query->get();
        $filename = 'products-export-'.date('Y-m-d-H-i-s').'.csv';

        $activityLog = StaffActivityLog::create([
            'user_id' => auth()->id(),
            'store_hub_id' => $hubId,
            'action_type' => 'product_export',
            'description' => 'Exported '.$products->count().' product item(s) to CSV.',
            'details' => [
                'file_name' => $filename,
                'item_count' => $products->count(),
            ],
            'ip_address' => $request->ip(),
        ]);
        $this->notifyInventoryTeam(
            'export',
            sprintf('User %s exported %d product item(s) from %s.', auth()->user()->name, $products->count(), StoreHub::find($hubId)?->name ?? 'the store hub'),
            (int) $hubId
        );

        $products->chunk(500)->each(function ($productsChunk) use ($activityLog) {
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

        $callback = function () use ($products) {
            $file = fopen('php://output', 'w');
            fputcsv($file, ['ID', 'Item ID', 'Name', 'Description', 'Barcode', 'Brand', 'Retail Group', 'Retail Department', 'Cost Price', 'Sales Price', 'Wholesale Price', 'Shopee Price', 'Lazada Price', 'Tiktok Price', 'Stock', 'Unit Type']);

            foreach ($products as $prod) {
                fputcsv($file, [
                    $prod->id, $prod->item_id, $prod->name, $prod->description, $prod->barcode, $prod->brand, $prod->retail_group, $prod->retail_department, $prod->cost_price, $prod->sales_price, $prod->wholesale_price, $prod->shopee_price, $prod->lazada_price, $prod->tiktok_price, $prod->stock, $prod->unit_type,
                ]);
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
        if ($user && $user->role !== 'admin' && $user->role !== 'inventory_staff' && $user->store_hub_id && $user->store_hub_id != $hubId) {
            return response()->json([], 403);
        }

        $search = $request->input('q', '');
        $query = Product::where('store_hub_id', $hubId);

        if ($search !== '') {
            $query->where(function ($builder) use ($search) {
                foreach (['name', 'description', 'item_id', 'barcode', 'brand'] as $field) {
                    $builder->orWhere($field, 'LIKE', "%{$search}%");
                }
            });
        }

        return response()->json($query->orderBy('name')->limit(100)->get());
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
