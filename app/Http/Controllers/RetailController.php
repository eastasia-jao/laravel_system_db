<?php

namespace App\Http\Controllers;

use App\Models\Department;
use App\Models\Group;
use App\Models\StoreHub;
use App\Models\UnitType;
use Illuminate\Http\Request;

class RetailController extends Controller
{
    // Save new Department
    public function storeDept(Request $request)
    {
        $request->validate([
            'dept_name' => 'required|string|max:255',
        ]);

        Department::create([
            'name' => strtoupper($request->dept_name),
        ]);

        return redirect()->back()->with('success', 'Department added successfully!');
    }

    // Save new Group
    public function storeGroup(Request $request)
    {
        $request->validate([
            'group_name' => 'required|string|max:255',
        ]);

        Group::create([
            'name' => strtoupper($request->group_name),
        ]);

        return redirect()->back()->with('success', 'Group added successfully!');
    }

    public function index() // Or whatever method loads your config page
    {
        $departments = Department::all();
        $groups = Group::all();
        $hubs = StoreHub::all();
        $unitTypes = UnitType::all();

        return view('configuration', compact('departments', 'groups', 'hubs', 'unitTypes'));
    }
}
