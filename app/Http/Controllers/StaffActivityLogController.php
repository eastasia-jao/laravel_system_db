<?php

namespace App\Http\Controllers;

use App\Models\StaffActivityLog;
use App\Models\StoreHub;
use App\Models\User;
use Illuminate\Http\Request;

class StaffActivityLogController extends Controller
{
    public function index(Request $request)
    {
        $validated = $request->validate([
            'action' => 'nullable|in:product_import,product_export,inventory_verification',
            'hub_id' => 'nullable|integer|exists:store_hubs,id',
            'user_id' => 'nullable|integer|exists:users,id',
            'date_from' => 'nullable|date',
            'date_to' => 'nullable|date|after_or_equal:date_from',
            'search' => 'nullable|string|max:255',
        ]);

        $query = StaffActivityLog::with(['user', 'storeHub'])->withCount('items')->latest();
        if (in_array(auth()->user()->role, ['sales_associate', 'sales_marketing_staff'], true)) {
            $query->where('store_hub_id', auth()->user()->store_hub_id)
                ->where('user_id', auth()->id());
        }

        if (! empty($validated['action'])) {
            $query->where('action_type', $validated['action']);
        }
        if (! empty($validated['hub_id'])) {
            $query->where('store_hub_id', $validated['hub_id']);
        }
        if (! empty($validated['user_id'])) {
            $query->where('user_id', $validated['user_id']);
        }
        if (! empty($validated['date_from'])) {
            $query->whereDate('created_at', '>=', $validated['date_from']);
        }
        if (! empty($validated['date_to'])) {
            $query->whereDate('created_at', '<=', $validated['date_to']);
        }
        if (! empty($validated['search'])) {
            $search = $validated['search'];
            $query->where(function ($logQuery) use ($search) {
                $logQuery->where('description', 'like', "%{$search}%")
                    ->orWhere('details', 'like', "%{$search}%")
                    ->orWhereHas('user', fn ($userQuery) => $userQuery->where('name', 'like', "%{$search}%"));
            });
        }

        $logs = $query->paginate(15)->withQueryString();
        $hubs = in_array(auth()->user()->role, ['admin', 'inventory_staff'], true)
            ? StoreHub::orderBy('name')->get()
            : StoreHub::whereKey(auth()->user()->store_hub_id)->get();
        $staff = in_array(auth()->user()->role, ['admin', 'inventory_staff'], true)
            ? User::whereNotNull('role')->orderBy('name')->get()
            : User::whereKey(auth()->id())->get();

        return view('staff-logs.index', compact('logs', 'hubs', 'staff'));
    }

    public function show(Request $request, StaffActivityLog $staffLog)
    {
        if (in_array(auth()->user()->role, ['sales_associate', 'sales_marketing_staff'], true)
            && ($staffLog->store_hub_id !== auth()->user()->store_hub_id || $staffLog->user_id !== auth()->id())) {
            abort(403);
        }
        $staffLog->load(['user', 'storeHub']);
        $items = $staffLog->items()
            ->when($request->filled('search'), function ($query) use ($request) {
                $search = $request->string('search')->trim();
                $query->where(function ($itemQuery) use ($search) {
                    $itemQuery->where('item_id', 'like', "%{$search}%")
                        ->orWhere('product_name', 'like', "%{$search}%");
                });
            })
            ->orderBy('id')
            ->paginate(50)
            ->withQueryString();

        return view('staff-logs.show', compact('staffLog', 'items'));
    }
}
