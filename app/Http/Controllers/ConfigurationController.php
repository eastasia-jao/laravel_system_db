<?php

namespace App\Http\Controllers;

use App\Models\Brand;
use App\Models\Department;
use App\Models\Group;
use App\Models\Product;
use App\Models\StoreHub;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ConfigurationController extends Controller
{
    public function index(Request $request)
    {
        // Optimized native pagination with global A-Z sorting
        $hubs = StoreHub::orderBy('name', 'asc')->paginate(10, ['*'], 'hubs_page');
        $brands = Brand::orderBy('brand_name', 'asc')->paginate(10, ['*'], 'brand_page');
        $departments = Department::orderBy('name', 'asc')->paginate(10, ['*'], 'dept_page');
        $groups = Group::orderBy('name', 'asc')->paginate(10, ['*'], 'group_page');
        $unitTypes = DB::table('unit_types')->orderBy('abbreviation', 'asc')->paginate(10, ['*'], 'unit_page');

        return view('configuration', compact('hubs', 'brands', 'unitTypes', 'departments', 'groups'));
    }

    public function storeHub(Request $request)
    {
        $request->validate([
            'name' => ['required', 'string', 'max:255', 'unique:store_hubs,name'],
            'code' => ['required', 'string', 'max:50', 'unique:store_hubs,code'],
        ], [
            'name.unique' => 'This store hub name already exists.',
            'code.unique' => 'This hub code reference already exists.',
        ]);

        StoreHub::create([
            'name' => strtoupper($request->input('name')),
            'code' => strtoupper($request->input('code')),
            'is_head_office' => $request->has('is_head_office'),
        ]);

        return redirect()->back()->with('success', 'Store hub registered successfully.');
    }

    public function toggleHubStatus($id)
    {
        $hub = StoreHub::findOrFail($id);

        if ($hub->status === 'active') {
            $activeStaffCount = User::where('hub_id', $hub->id)
                ->where('status', 'active')
                ->count();

            if ($activeStaffCount > 0) {
                return redirect()->back()->with('error', 'Cannot deactivate this hub: '.$activeStaffCount.' staff members are currently assigned to it.');
            }
        }

        $hub->status = ($hub->status === 'active') ? 'inactive' : 'active';
        $hub->save();

        return redirect()->back()->with('success', 'Hub status updated successfully.');
    }

    public function destroyHub($id)
    {
        StoreHub::findOrFail($id)->delete();

        return redirect()->back()->with('success', 'Store hub deleted successfully.');
    }

    public function updateHub(Request $request, $id)
    {
        $hub = StoreHub::findOrFail($id);

        $hub->update([
            'name' => $request->input('name'),
            'code' => $request->input('code'),
            'is_head_office' => $request->has('is_head_office') ? 1 : 0,
        ]);

        return redirect()->back()->with('success', 'Store hub updated successfully.');
    }

    // --- Brand Management Methods ---

    public function storeBrand(Request $request)
    {
        $validated = $request->validate([
            'brand_name' => 'required|string|max:255|unique:brands,brand_name',
        ], [
            'brand_name.unique' => 'This brand name already exists.',
        ]);

        Brand::create([
            'brand_name' => strtoupper($validated['brand_name']),
        ]);

        return redirect()->to('/configuration?tab=brands')->with('success', 'Brand registered successfully.');
    }

    public function destroyBrand($id)
    {
        $brand = Brand::findOrFail($id);

        if (Product::where('brand', $brand->brand_name)->exists()) {
            return redirect()->back()->with('error', 'Cannot delete this brand because it is currently assigned to one or more products.');
        }

        $brand->delete();

        return redirect()->back()->with('success', 'Brand deleted successfully.');
    }

    public function destroyDepartment($id)
    {
        $department = Department::findOrFail($id);

        if (Product::where('retail_department', $department->name)->exists()) {
            return redirect()->back()->with('error', 'Cannot delete this department because it is currently assigned to one or more products.');
        }

        $department->delete();

        return redirect()->back()->with('success', 'Department deleted successfully.');
    }

    public function destroyGroup($id)
    {
        $group = Group::findOrFail($id);

        if (Product::where('retail_group', $group->name)->exists()) {
            return redirect()->back()->with('error', 'Cannot delete this group because it is currently assigned to one or more products.');
        }

        $group->delete();

        return redirect()->back()->with('success', 'Group deleted successfully.');
    }
}
