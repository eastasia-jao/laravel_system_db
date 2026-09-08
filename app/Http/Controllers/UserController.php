<?php

namespace App\Http\Controllers;

use App\Models\StoreHub;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\Rule;

class UserController extends Controller
{
    public function index()
    {
        $users = User::all();
        $hubs = StoreHub::all();

        return view('users', compact('users', 'hubs'));
    }

    public function store(Request $request)
    {
        $request->validate([
            'employee_id' => 'required|unique:users,employee_id',
            'first_name' => 'required',
            'last_name' => 'required',
            'email' => 'required|email|unique:users,email',
            'password' => ['required', 'confirmed', Password::min(8)->letters()->mixedCase()->numbers()->symbols()],
            'role' => ['required', Rule::in(['admin', 'inventory_staff', 'sales_associate', 'sales_marketing_staff'])],
            'hub_id' => 'required|exists:store_hubs,id',
            'sales_channels' => ['nullable', 'array'],
            'sales_channels.*' => [Rule::in(['shopee', 'lazada', 'online', 'wholesale', 'tiktok'])],
        ]);

        User::create([
            'name' => $request->first_name.' '.$request->last_name,
            'username' => $request->employee_id,
            'email' => $request->email,
            'password' => Hash::make($request->password),
            'employee_id' => $request->employee_id,
            'hub_id' => $request->hub_id,
            'role' => $request->role,
            'sales_channels' => $request->role === 'sales_marketing_staff' ? ($request->input('sales_channels', [])) : [],
            'mobile_number' => $request->mobile_number,
            'status' => 'active',
        ]);

        return redirect()->back()->with('success', 'Staff registered successfully!');
    }

    public function edit($id)
    {
        return response()->json(['user' => User::find($id)]);
    }

    public function update(Request $request, $id)
    {
        $user = User::findOrFail($id);

        // Define validation rules
        $rules = [
            'name' => 'required|string|max:255',
            'email' => 'required|email|unique:users,email,'.$id,
            // Only require current_password if user is changing password OR email/details
            // If you strictly want this for password changes, use required_with:password
            'current_password' => ['nullable', 'required_with:password', 'current_password'],
            'password' => 'nullable|min:8|confirmed',
            'role' => ['required', Rule::in(['admin', 'inventory_staff', 'sales_associate', 'sales_marketing_staff'])],
            'hub_id' => 'required|exists:store_hubs,id',
            'sales_channels' => ['nullable', 'array'],
            'sales_channels.*' => [Rule::in(['shopee', 'lazada', 'online', 'wholesale', 'tiktok'])],
        ];

        $request->validate($rules);

        // Update basic info
        $user->name = $request->name;
        $user->email = $request->email;
        $user->mobile_number = $request->mobile_number;
        $user->hub_id = $request->hub_id;
        $user->role = $request->role;
        $user->sales_channels = $request->role === 'sales_marketing_staff' ? ($request->input('sales_channels', [])) : [];

        // Update password if provided
        if ($request->filled('password')) {
            $user->password = Hash::make($request->password);
        }

        $user->save();

        return response()->json(['success' => 'Staff updated successfully.']);
    }

    public function toggleStatus($id)
    {
        $user = User::findOrFail($id);
        $user->status = ($user->status == 'active') ? 'inactive' : 'active';
        $user->save();

        // Pass the status so the view knows which color to use
        return redirect()->back()->with([
            'success' => 'Status updated to '.strtoupper($user->status),
            'status_color' => ($user->status == 'active') ? 'success' : 'danger',
        ]);
    }

    public function destroy($id)
    {
        $user = User::findOrFail($id);

        if ($user->is(auth()->user())) {
            return back()->with('error', 'You cannot delete your own account.');
        }

        $user->delete();

        return back()->with('success', 'Staff account deleted successfully.');
    }
}
