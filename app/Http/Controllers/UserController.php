<?php

namespace App\Http\Controllers;

use App\Models\StoreHub;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\Rule;
use Illuminate\Support\Str;

class UserController extends Controller
{
    public function index()
    {
        $users = User::all();
        $hubs = StoreHub::all();
        $staffRoles = User::BUILT_IN_ROLES;

        return view('users', compact('users', 'hubs', 'staffRoles'));
    }

    public function store(Request $request)
    {
        $request->merge([
            'employee_id' => Str::upper(trim((string) $request->input('employee_id'))),
        ]);
        $hubRule = Rule::exists('store_hubs', 'id');
        if ($request->input('role') === 'sales_associate') {
            $hubRule->where('is_head_office', 0);
        }

        $request->validate([
            'employee_id' => 'required|string|max:10|unique:users,employee_id',
            'first_name' => 'required|string|max:100',
            'last_name' => 'required|string|max:100',
            'email' => 'nullable|email|unique:users,email',
            'mobile_number' => 'required|string|max:30',
            'password' => ['required', 'confirmed', Password::min(8)->letters()->mixedCase()->numbers()->symbols()],
            'role' => ['required', Rule::in(array_keys(User::BUILT_IN_ROLES))],
            'hub_id' => ['required', $hubRule],
            'additional_hub_ids' => ['nullable', 'array'],
            'additional_hub_ids.*' => [
                'integer',
                'distinct',
                Rule::exists('store_hubs', 'id')->where('is_head_office', 0),
                'different:hub_id',
            ],
            'sales_channels' => ['nullable', 'array'],
            'sales_channels.*' => [Rule::in($request->input('role') === 'sales_marketing_staff'
                ? ['shopee', 'lazada', 'online', 'walk_in', 'wholesale', 'tiktok', 'fully_booked']
                : ['shopee', 'lazada', 'online', 'walk_in', 'wholesale', 'tiktok'])],
        ]);

        $user = User::create([
            'name' => $this->formalName($request->first_name).' '.$this->formalName($request->last_name),
            'username' => $request->employee_id,
            'email' => $request->input('email') ?: Str::lower($request->employee_id).'@inventory.local',
            'password' => Hash::make($request->password),
            'employee_id' => $request->employee_id,
            'hub_id' => $request->hub_id,
            'role' => $request->role,
            'sales_channels' => in_array($request->role, ['inventory_staff', 'sales_marketing_staff'], true)
                ? $request->input('sales_channels', [])
                : [],
            'mobile_number' => $request->mobile_number,
            'status' => 'active',
        ]);
        $this->syncSalesAssociateHubs($user, $request);

        return redirect()->back()->with('success', 'New staff account created successfully.');
    }

    public function edit($id)
    {
        $user = User::findOrFail($id);
        $names = preg_split('/\s+/', trim($user->name), 2);

        return response()->json(['user' => [
            ...$user->toArray(),
            'first_name' => $names[0] ?? '',
            'last_name' => $names[1] ?? '',
            'additional_hub_ids' => $user->assignedStoreHubs()->where('store_hubs.id', '<>', $user->hub_id)->pluck('store_hubs.id'),
        ]]);
    }

    public function update(Request $request, $id)
    {
        $user = User::findOrFail($id);
        $request->merge([
            'employee_id' => Str::upper(trim((string) $request->input('employee_id'))),
        ]);

        // Define validation rules
        $hubRule = Rule::exists('store_hubs', 'id');
        if ($request->input('role') === 'sales_associate') {
            $hubRule->where('is_head_office', 0);
        }

        $rules = [
            'first_name' => 'nullable|string|max:100|required_without:name',
            'last_name' => 'nullable|string|max:100|required_without:name',
            'name' => 'nullable|string|max:255|required_without:first_name',
            'employee_id' => ['nullable', 'string', 'max:10', Rule::unique('users', 'employee_id')->ignore($user->id)],
            'email' => 'nullable|email|unique:users,email,'.$id,
            'mobile_number' => 'required|string|max:30',
            // Only require current_password if user is changing password OR email/details
            // If you strictly want this for password changes, use required_with:password
            'current_password' => ['nullable', 'required_with:password', 'current_password'],
            'password' => 'nullable|min:8|confirmed',
            'role' => ['required', Rule::in(array_keys(User::BUILT_IN_ROLES))],
            'hub_id' => ['required', $hubRule],
            'additional_hub_ids' => ['nullable', 'array'],
            'additional_hub_ids.*' => [
                'integer',
                'distinct',
                Rule::exists('store_hubs', 'id')->where('is_head_office', 0),
                'different:hub_id',
            ],
            'sales_channels' => ['nullable', 'array'],
            'sales_channels.*' => [Rule::in($request->input('role') === 'sales_marketing_staff'
                ? ['shopee', 'lazada', 'online', 'walk_in', 'wholesale', 'tiktok', 'fully_booked']
                : ['shopee', 'lazada', 'online', 'walk_in', 'wholesale', 'tiktok'])],
        ];

        $request->validate($rules);

        // Update basic info
        $user->name = $request->filled('name')
            ? $this->formalName($request->name)
            : $this->formalName($request->first_name).' '.$this->formalName($request->last_name);
        if ($request->filled('email')) {
            $user->email = $request->email;
        }
        if ($request->filled('employee_id')) {
            $user->employee_id = $request->employee_id;
            $user->username = $request->employee_id;
        }
        $user->mobile_number = $request->mobile_number;
        $user->hub_id = $request->hub_id;
        $user->role = $request->role;
        $user->sales_channels = in_array($request->role, ['inventory_staff', 'sales_marketing_staff'], true)
            ? $request->input('sales_channels', [])
            : [];

        // Update password if provided
        if ($request->filled('password')) {
            $user->password = Hash::make($request->password);
        }

        $user->save();
        $this->syncSalesAssociateHubs($user, $request);

        $message = 'Staff account updated successfully.';

        return $request->expectsJson()
            ? response()->json(['success' => $message])
            : redirect()->back()->with('success', $message);
    }

    public function resetPassword(Request $request, $id)
    {
        $data = $request->validate([
            'password' => ['required', 'confirmed', Password::min(8)->letters()->mixedCase()->numbers()->symbols()],
        ]);

        $user = User::findOrFail($id);
        $user->password = Hash::make($data['password']);
        $user->save();

        $message = 'Password reset successfully.';

        return $request->expectsJson()
            ? response()->json(['success' => $message])
            : redirect()->back()->with('success', $message);
    }

    private function formalName(string $name): string
    {
        return Str::title(Str::lower(trim($name)));
    }

    private function syncSalesAssociateHubs(User $user, Request $request): void
    {
        $hubIds = $user->role === 'sales_associate'
            ? array_values(array_unique([
                (int) $user->hub_id,
                ...array_map('intval', (array) $request->input('additional_hub_ids', [])),
            ]))
            : [];

        $user->assignedStoreHubs()->sync($hubIds);
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
