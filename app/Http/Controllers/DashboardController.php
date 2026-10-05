<?php

namespace App\Http\Controllers;

use App\Models\PendingSale;
use App\Models\Product;
use App\Models\ProductFileRequest;
use App\Models\ProductReplacement;
use App\Models\StoreHub;
use Illuminate\Http\Request;
use Carbon\Carbon;

class DashboardController extends Controller
{
    public function dashboard(Request $request, $id)
    {
        if (auth()->user() && ! auth()->user()->canAccessHub((int) $id)) {
            abort(403, 'Unauthorized access to this store hub.');
        }
        $hub = StoreHub::findOrFail($id);
        $selectedHub = StoreHub::findOrFail($id);
        $user = auth()->user();
        $walkInHubs = $user?->role === 'sales_associate'
            ? StoreHub::whereIn('id', $user->accessibleStoreHubIds())
                ->where('is_head_office', false)
                ->where('status', 'active')
                ->orderBy('name')
                ->get()
            : StoreHub::whereKey($id)->get();

        $brands = Product::catalogOptions((int) $id, 'brand');
        $groups = Product::catalogOptions((int) $id, 'retail_group');
        $departments = Product::catalogOptions((int) $id, 'retail_department');

        // Sale forms search the selected branch on demand instead of embedding its entire catalog.
        $products = collect();
        $pendingSalesCount = PendingSale::where('store_hub_id', $id)->where('status', 'pending')->count()
            + ProductReplacement::where('status', 'pending')->whereHas('transaction', fn ($query) => $query->where('store_hub_id', $id))->count();
        $latestImport = ProductFileRequest::query()
            ->where('store_hub_id', $id)
            ->where('type', 'import')
            ->latest('id')
            ->first();

        // Monthly filter handling for Top 10 Products
        $selectedMonth = $request->input('month', now()->format('Y-m'));
        $startDate = Carbon::parse($selectedMonth)->startOfMonth();
        $endDate = Carbon::parse($selectedMonth)->endOfMonth();

        // Example query for Top 10 products within the selected month range
        // Adjust the relationship or model name based on your sales records structure
        /*
        $topProducts = SalesItem::where('store_hub_id', $id)
            ->whereBetween('created_at', [$startDate, $endDate])
            ->select('product_id', 'name', \DB::raw('sum(quantity) as units'))
            ->groupBy('product_id', 'name')
            ->orderByDesc('units')
            ->take(10)
            ->get();
        */

        return view('hubs.dashboard', compact(
            'hub', 
            'selectedHub', 
            'brands', 
            'groups', 
            'departments', 
            'products', 
            'walkInHubs',
            'pendingSalesCount',
            'latestImport',
            'selectedMonth'
        ));
    }
}
