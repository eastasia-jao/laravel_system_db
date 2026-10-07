<?php

namespace App\Http\Controllers;

use App\Jobs\AssignCatalogToBranch;
use App\Models\CatalogProduct;
use App\Models\Product;
use App\Models\StoreHub;
use App\Support\KeywordSearch;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class CatalogController extends Controller
{
    public function index(Request $request)
    {
        $sourceHub = $this->headOfficeSource($request);
        $itemIdNumericExpression = match (DB::connection()->getDriverName()) {
            'mysql', 'mariadb' => 'CAST(item_id AS UNSIGNED)',
            'pgsql' => "CAST(NULLIF(regexp_replace(item_id, '[^0-9]', '', 'g'), '') AS BIGINT)",
            default => 'CAST(item_id AS INTEGER)',
        };
        $catalog = CatalogProduct::query()->when($request->filled('search'), function ($query) use ($request) {
            KeywordSearch::apply($query, $request->string('search')->toString(), ['item_id', 'name', 'barcode', 'brand']);
        })->withCount('branchInventories')
            ->orderByRaw($itemIdNumericExpression)
            ->orderBy('item_id')
            ->orderBy('id')
            ->paginate(50)
            ->withQueryString();
        $branches = StoreHub::where('status', 'active')
            ->where('is_head_office', false)
            ->orderBy('name')
            ->get();

        return view('products.catalog', compact('catalog', 'branches', 'sourceHub'));
    }

    public function assign(Request $request)
    {
        $data = $request->validate([
            'source_hub_id' => ['required', Rule::exists('store_hubs', 'id')->where('status', 'active')->where('is_head_office', 1)],
            'hub_id' => ['required', Rule::exists('store_hubs', 'id')->where('status', 'active')->where('is_head_office', 0)],
            'all_products' => ['sometimes', 'boolean'],
            'catalog_ids' => [Rule::requiredIf(fn () => ! $request->boolean('all_products')), 'array', 'min:1', 'max:50'],
            'catalog_ids.*' => ['integer', 'distinct', 'exists:catalog_products,id'],
        ]);
        $this->headOfficeSource($request, (int) $data['source_hub_id']);

        if ($request->boolean('all_products')) {
            // Catalog assignment must also work on installations where no persistent queue worker has
            // been configured. Run it in this request; the job still chunks the
            // catalog so it does not load every product into memory at once.
            AssignCatalogToBranch::dispatchSync((int) $data['hub_id'], auth()->id(), (int) $data['source_hub_id']);

            return back()->with(
                'success',
                'All shared catalog products were added to the branch with zero stock. Existing products were preserved.'
            );
        }
        $added = DB::transaction(function () use ($data, $request) {
            StoreHub::whereKey($data['hub_id'])->lockForUpdate()->firstOrFail();
            $count = 0;
            $log = \App\Models\StaffActivityLog::create([
                'user_id' => auth()->id(), 'store_hub_id' => $data['hub_id'],
                'action_type' => 'catalog_assignment', 'description' => 'Assigned selected shared catalog products to branch.',
                'ip_address' => $request->ip(),
            ]);
            foreach (CatalogProduct::whereIn('id', $data['catalog_ids'])->get() as $catalog) {
                $product = Product::firstOrCreate(['store_hub_id' => $data['hub_id'], 'catalog_product_id' => $catalog->id], [
                    'stock' => 0, 'status' => 'active',
                ]);
                $count += $product->wasRecentlyCreated ? 1 : 0;
                $log->items()->create([
                    'product_id' => $product->id, 'item_id' => $catalog->item_id, 'product_name' => $catalog->name,
                    'operation' => $product->wasRecentlyCreated ? 'created' : 'skipped',
                    'quantity' => 0, 'stock_before' => $product->stock, 'stock_after' => $product->stock,
                ]);
            }
            $log->update(['description' => "$count shared catalog products added to branch; existing products preserved.", 'details' => ['created_count' => $count, 'skipped_count' => count($data['catalog_ids']) - $count]]);

            return $count;
        });

        return back()->with('success', "$added products added to the branch with zero stock. Set branch prices and receive stock before selling.");
    }

    private function headOfficeSource(Request $request, ?int $sourceHubId = null): StoreHub
    {
        $user = $request->user();
        $sourceHubId ??= $request->integer('hub_id');
        $sourceHub = StoreHub::whereKey($sourceHubId)
            ->where('status', 'active')
            ->where('is_head_office', true)
            ->first();

        abort_unless($sourceHub, 403, 'Open the Shared Product Catalog from an active Head Office workspace.');
        if ($user->role === 'inventory_staff' && (int) $user->hub_id !== (int) $sourceHub->id) {
            abort(403, 'You can only manage the Shared Product Catalog from your designated Head Office.');
        }

        return $sourceHub;
    }
}
