<?php

namespace App\Http\Controllers;

use App\Models\Brand;
use Illuminate\Http\Request;

class BrandController extends Controller
{
    public function store(Request $request)
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
}
