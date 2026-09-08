<?php

namespace App\Http\Controllers;

use App\Models\PendingSale;
use App\Models\Product;
use App\Models\SalesTransaction;
use App\Models\StoreHub;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

class HubController extends Controller
{
    use AuthorizesRequests;

    public function dashboard($id)
    {
        $user = auth()->user();
        if ($user && ! $user->canAccessHub((int) $id)) {
            abort(403, 'Unauthorized access to this store hub dashboard.');
        }

        $hub = StoreHub::findOrFail($id);

        $pendingSalesCount = PendingSale::where('store_hub_id', $id)
            ->where('status', 'pending')
            ->count();

        $totalProducts = Product::where('store_hub_id', $id)->count();
        $totalSales = SalesTransaction::where('store_hub_id', $id)->sum('grand_total');

        return view('hubs.dashboard', compact('hub', 'totalProducts', 'totalSales', 'pendingSalesCount'));
    }

    public function index(Request $request)
    {
        $user = auth()->user();

        $hubsQuery = StoreHub::where('status', 'active');
        if ($user && ! in_array($user->role, ['admin', 'inventory_staff'], true)) {
            $hubsQuery->where('id', $user->store_hub_id);
        }

        $filters = $request->validate(['hub_id' => ['nullable', 'integer'], 'date' => ['nullable', 'date_format:Y-m-d']]);
        $dashboardHubs = $hubsQuery->orderBy('name')->get();
        $hubId = $filters['hub_id'] ?? null;
        if ($hubId) {
            abort_unless($dashboardHubs->contains('id', (int) $hubId), 403);
        }
        $scopeIds = $hubId ? [(int) $hubId] : $dashboardHubs->modelKeys();
        $asOf = Carbon::parse($filters['date'] ?? now()->toDateString())->startOfDay();
        $periods = [
            'Daily' => $asOf->copy(), 'Weekly' => $asOf->copy()->startOfWeek(),
            'Monthly' => $asOf->copy()->startOfMonth(), 'Quarterly' => $asOf->copy()->startOfQuarter(),
            'Yearly' => $asOf->copy()->startOfYear(),
        ];
        $salesQuery = SalesTransaction::whereIn('store_hub_id', $scopeIds)
            ->whereIn('status', ['completed', 'confirmed']);
        $assignedChannels = $user?->role === 'sales_marketing_staff'
            ? collect($user->sales_channels ?? [])->map(fn ($channel) => strtolower(str_replace(['-', ' '], '_', (string) $channel)))->values()
            : collect();
        if ($assignedChannels->isNotEmpty()) {
            $salesQuery->whereIn('channel_type', $assignedChannels->all());
        }
        $salesTotals = collect($periods)->map(fn ($start) => (clone $salesQuery)
            ->whereBetween('order_date', [$start, $asOf->copy()->endOfDay()])->sum('grand_total'));
        $monthlySales = (clone $salesQuery)->whereBetween('order_date', [$asOf->copy()->startOfMonth(), $asOf->copy()->endOfDay()])
            ->with('items.product')->get();
        if ($assignedChannels->isNotEmpty()) {
            $monthlySales = $monthlySales->filter(function ($sale) use ($assignedChannels) {
                $channel = strtolower(str_replace(['-', ' '], '_', (string) $sale->channel_type));
                return $assignedChannels->contains($channel);
            })->values();
        }
        $channelDefinitions = [
            'shopee' => ['label' => 'Shopee', 'icon' => 'fa-bag-shopping', 'color' => 'warning'],
            'lazada' => ['label' => 'Lazada', 'icon' => 'fa-store', 'color' => 'info'],
            'tiktok' => ['label' => 'TikTok', 'icon' => 'fa-music', 'color' => 'dark'],
            'online' => ['label' => 'Online', 'icon' => 'fa-globe', 'color' => 'primary'],
            'wholesale' => ['label' => 'Wholesale', 'icon' => 'fa-building', 'color' => 'success'],
            'walk_in' => ['label' => 'Walk-In', 'icon' => 'fa-cash-register', 'color' => 'secondary'],
        ];
        $scopedHubs = $dashboardHubs->whereIn('id', $scopeIds);
        $availableChannels = $scopedHubs->contains('is_head_office', true)
            ? ['shopee', 'lazada', 'tiktok', 'online', 'wholesale', 'walk_in']
            : ['walk_in'];
        if ($assignedChannels->isNotEmpty()) {
            $availableChannels = array_values(array_intersect($availableChannels, $assignedChannels->all()));
        }
        $channelSummaries = collect($availableChannels)->map(function ($channel) use ($channelDefinitions, $monthlySales) {
            $channelSales = $monthlySales->filter(function ($sale) use ($channel) {
                $value = strtolower(str_replace(['-', ' '], '_', (string) $sale->channel_type));
                return $value === $channel || ($channel === 'walk_in' && $value === 'walkin');
            });

            return array_merge($channelDefinitions[$channel], [
                'key' => $channel,
                'transactions' => $channelSales->count(),
                'total' => $channelSales->sum(fn ($sale) => max(
                    (float) $sale->grand_total,
                    (float) $sale->total_amount,
                    0
                )),
            ]);
        });
        $paymentColumns = ['Cash', 'GCash', 'Maya', 'BDO', 'BPI', 'Other'];
        $matrix = $dashboardHubs->whereIn('id', $scopeIds)->map(function ($hub) use ($monthlySales, $paymentColumns) {
            $amounts = array_fill_keys($paymentColumns, 0);
            foreach ($monthlySales->where('store_hub_id', $hub->id) as $sale) {
                $method = strtoupper(str_replace([' ', '_', '-'], '', (string) $sale->mode_of_payment));
                $bank = strtoupper(trim((string) $sale->bank_name));
                $key = match (true) {
                    $method === 'CASH' => 'Cash', $method === 'GCASH' => 'GCash',
                    in_array($method, ['MAYA', 'PAYMAYA'], true) => 'Maya',
                    $method === 'BDO' || $bank === 'BDO' => 'BDO',
                    $method === 'BPI' || $bank === 'BPI' => 'BPI', default => 'Other',
                };
                $amounts[$key] += (float) $sale->grand_total;
            }
            return ['name' => $hub->name, 'amounts' => $amounts];
        });
        $topProducts = $monthlySales->flatMap->items->groupBy('product_id')->map(fn ($items) => [
            'name' => $items->first()->product?->name ?? 'Unavailable product',
            'units' => $items->sum('quantity'),
        ])->sortByDesc('units')->take(10)->values();
        $soldUnits = $monthlySales->flatMap->items->groupBy('product_id')->map(fn ($items) => $items->sum('quantity'));
        $slowProducts = Product::whereIn('store_hub_id', $scopeIds)
            ->where('status', 'active')
            ->get()
            ->map(fn ($product) => [
                'name' => $product->name ?: $product->item_id,
                'units' => (int) $soldUnits->get($product->id, 0),
            ])
            ->sortBy('units')
            ->take(10)
            ->values();
        $alertsQuery = Product::with('storeHub')
            ->whereIn('store_hub_id', $scopeIds)
            ->where('status', 'active')
            ->whereBetween('stock', [0, 30]);
        $alertCount = (clone $alertsQuery)->count();
        $alerts = $alertsQuery->orderBy('stock')->orderBy('name')->limit(30)->get();
        $pendingVerificationCount = $user?->role === 'admin'
            ? PendingSale::whereIn('store_hub_id', $scopeIds)->where('status', 'pending')->count()
            : 0;
        $scopeName = $hubId ? $dashboardHubs->firstWhere('id', $hubId)->name : 'All accessible stores';

        return view('dashboard', compact('dashboardHubs', 'hubId', 'asOf', 'salesTotals', 'paymentColumns', 'matrix', 'topProducts', 'slowProducts', 'alerts', 'alertCount', 'scopeName', 'channelSummaries', 'pendingVerificationCount'));
    }

    public function destroy($id)
    {
        $this->authorize('full-access');

        $hub = StoreHub::findOrFail($id);
        $hub->delete();

        return redirect()->back()->with('success', 'Hub deleted successfully.');
    }
}
