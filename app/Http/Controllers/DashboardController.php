<?php

namespace App\Http\Controllers;

use App\Models\PendingSale;
use App\Models\Product;
use App\Models\StoreHub;

class DashboardController extends Controller
{
    // Make sure this method is present:
    public function dashboard($id)
    {
        if (auth()->user() && ! auth()->user()->canAccessHub((int) $id)) {
            abort(403, 'Unauthorized access to this store hub.');
        }
        $hub = StoreHub::findOrFail($id);
        $selectedHub = StoreHub::findOrFail($id);

        $brands = Product::where('store_hub_id', $id)->whereNotNull('brand')->distinct()->pluck('brand');
        $groups = Product::where('store_hub_id', $id)->whereNotNull('retail_group')->distinct()->pluck('retail_group');
        $departments = Product::where('store_hub_id', $id)->whereNotNull('retail_department')->distinct()->pluck('retail_department');

        $products = Product::where('store_hub_id', $id)->get();
        $pendingSalesCount = PendingSale::where('store_hub_id', $id)->where('status', 'pending')->count();
        return view('hubs.dashboard', compact('hub', 'selectedHub', 'brands', 'groups', 'departments', 'products', 'pendingSalesCount'));
    }
}
