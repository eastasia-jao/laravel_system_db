<?php

namespace App\Http\Controllers;

use App\Support\KeywordSearch;
use App\Models\StaffActivityLog;
use App\Models\PendingSale;
use App\Models\StoreHub;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\Request;

class StaffActivityLogController extends Controller
{
    public function index(Request $request)
    {
        $validated = $request->validate([
            'action' => 'nullable|in:product_import,product_export,inventory_verification,catalog_assignment,branch_transfer_sent',
            'hub_id' => 'nullable|integer|exists:store_hubs,id',
            'user_id' => 'nullable|integer|exists:users,id',
            'date_from' => 'nullable|date',
            'date_to' => 'nullable|date|after_or_equal:date_from',
            'search' => 'nullable|string|max:255',
        ]);

        $query = StaffActivityLog::with(['user', 'storeHub'])->withCount('items')->latest();
        $user = auth()->user();
        if ($user->role === 'sales_associate') {
            $query->where('action_type', 'branch_transfer_sent')
                ->whereIn('store_hub_id', $user->accessibleStoreHubIds());
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
            $query->where('created_at', '>=', Carbon::parse($validated['date_from'])->startOfDay());
        }
        if (! empty($validated['date_to'])) {
            $query->where('created_at', '<', Carbon::parse($validated['date_to'])->addDay()->startOfDay());
        }
        if (! empty($validated['search'])) {
            $search = $validated['search'];
            $query->where(function ($logQuery) use ($search) {
                foreach (preg_split('/\s+/u', trim($search), -1, PREG_SPLIT_NO_EMPTY) as $keyword) {
                    $logQuery->where(function ($keywordQuery) use ($keyword) {
                        $keywordQuery->whereRaw('LOWER(description) LIKE ?', ['%'.mb_strtolower($keyword, 'UTF-8').'%'])
                            ->orWhereRaw('LOWER(details) LIKE ?', ['%'.mb_strtolower($keyword, 'UTF-8').'%'])
                            ->orWhereHas('user', fn ($userQuery) => KeywordSearch::apply($userQuery, $keyword, ['name']));
                    });
                }
            });
        }

        $logs = $query->paginate($user->role === 'sales_associate' ? 10 : 15)->withQueryString();
        $isSalesAssociate = $user->role === 'sales_associate';
        $accessibleHubIds = $user->accessibleStoreHubIds();
        $hubs = ! $isSalesAssociate
            ? StoreHub::orderBy('name')->get()
            : StoreHub::whereIn('id', $accessibleHubIds)->orderBy('name')->get();
        $staff = $isSalesAssociate
            ? User::whereIn('id', StaffActivityLog::query()
                ->where('action_type', 'branch_transfer_sent')
                ->whereIn('store_hub_id', $accessibleHubIds)
                ->whereNotNull('user_id')
                ->select('user_id')
                ->distinct())
                ->orderBy('name')
                ->get()
            : (in_array($user->role, ['admin', 'inventory_staff'], true)
                ? User::whereIn('id', StaffActivityLog::query()
                    ->whereNotNull('user_id')
                    ->select('user_id')
                    ->distinct())
                    ->orderBy('name')
                    ->get()
                : User::whereKey($user->id)->get());
        $showHubFilter = ! $isSalesAssociate || $hubs->count() > 1;

        return view('staff-logs.index', compact('logs', 'hubs', 'staff', 'isSalesAssociate', 'showHubFilter'));
    }

    public function show(Request $request, StaffActivityLog $staffLog)
    {
        $user = auth()->user();
        if ($user->role === 'sales_associate'
            && ($staffLog->action_type !== 'branch_transfer_sent'
                || ! in_array((int) $staffLog->store_hub_id, $user->accessibleStoreHubIds(), true))) {
            abort(403);
        }
        $staffLog->load(['user', 'storeHub']);
        $pendingSale = ! empty($staffLog->details['pending_sale_id'])
            ? PendingSale::find($staffLog->details['pending_sale_id'])
            : null;
        $isInventoryVerification = $staffLog->action_type === 'inventory_verification';
        $items = $staffLog->items()
            ->when(! $isInventoryVerification && $request->filled('search'), function ($query) use ($request) {
                $search = $request->string('search')->trim();
                $query->where(function ($itemQuery) use ($search) {
                    KeywordSearch::apply($itemQuery, $search, ['item_id', 'product_name']);
                });
            })
            ->orderBy('id')
            ->paginate($isInventoryVerification ? 10 : 50)
            ->withQueryString();

        return view('staff-logs.show', compact('staffLog', 'items', 'pendingSale'));
    }
}
