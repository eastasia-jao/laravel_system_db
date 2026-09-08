<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class UnitTypeController extends Controller
{
    public function store(Request $request)
    {
        $validated = $request->validate([
            'abbreviation' => 'required|string|max:50|unique:unit_types,abbreviation',
        ], [
            'abbreviation.unique' => 'This unit abbreviation already exists.',
        ]);

        DB::table('unit_types')->insert([
            'abbreviation' => strtoupper($validated['abbreviation']),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return redirect()->to('/configuration?tab=units')->with('success', 'Unit added successfully!');
    }

    public function destroy($id)
    {
        $unitType = DB::table('unit_types')->where('id', $id)->first();

        if (! $unitType) {
            return redirect()->to('/configuration?tab=units')->with('error', 'Unit type not found.');
        }

        // Check if any products are currently using this abbreviation
        $isUsed = DB::table('products')->where('unit_type', $unitType->abbreviation)->exists();

        if ($isUsed) {
            return redirect()->to('/configuration?tab=units')->with('error', 'Cannot delete this unit type because it is currently assigned to one or more products.');
        }

        DB::table('unit_types')->where('id', $id)->delete();

        return redirect()->to('/configuration?tab=units')->with('success', 'Unit type deleted successfully!');
    }

    public function toggleStatus($id)
    {
        $unitType = DB::table('unit_types')->where('id', $id)->first();

        if (! $unitType) {
            return redirect()->to('/configuration?tab=units')->with('error', 'Unit type not found.');
        }

        $status = $unitType->status === 'active' ? 'inactive' : 'active';

        DB::table('unit_types')->where('id', $id)->update([
            'status' => $status,
            'updated_at' => now(),
        ]);

        return redirect()->to('/configuration?tab=units')->with('success', 'Unit type status updated successfully.');
    }
}
