<?php

namespace App\Providers;

use App\Models\StoreHub;
use App\Models\FullyBookedOrder;
use App\Models\PendingSale;
use App\Models\ProductReplacement;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider; // 1. Make sure this is imported

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        Paginator::useBootstrapFive();
        $isBuiltInRole = static fn ($user): bool => $user->hasBuiltInRole();

        View::composer('layouts.sidebar', function ($view) {
            $user = auth()->user();
            $sidebarHubs = StoreHub::where('status', 'active')->orderBy('name')->get();
            $pendingVerificationCountsByHub = collect();
            if (in_array($user?->role, ['admin', 'inventory_staff'], true)) {
                $pendingVerificationCountsByHub = PendingSale::query()
                    ->where('status', 'pending')
                    ->selectRaw('store_hub_id, COUNT(*) as total')
                    ->groupBy('store_hub_id')
                    ->pluck('total', 'store_hub_id');

                ProductReplacement::query()
                    ->where('product_replacements.status', 'pending')
                    ->join('sales_transactions', 'product_replacements.transaction_id', '=', 'sales_transactions.id')
                    ->selectRaw('sales_transactions.store_hub_id, COUNT(*) as total')
                    ->groupBy('sales_transactions.store_hub_id')
                    ->pluck('total', 'store_hub_id')
                    ->each(function ($count, $hubId) use ($pendingVerificationCountsByHub) {
                        $pendingVerificationCountsByHub->put(
                            $hubId,
                            (int) $pendingVerificationCountsByHub->get($hubId, 0) + (int) $count
                        );
                    });

                FullyBookedOrder::query()
                    ->whereIn('status', ['pending', 'reviewed'])
                    ->whereNull('pulled_out_at')
                    ->selectRaw('store_hub_id, COUNT(*) as total')
                    ->groupBy('store_hub_id')
                    ->pluck('total', 'store_hub_id')
                    ->each(function ($count, $hubId) use ($pendingVerificationCountsByHub) {
                        $pendingVerificationCountsByHub->put(
                            $hubId,
                            (int) $pendingVerificationCountsByHub->get($hubId, 0) + (int) $count
                        );
                    });
            }

            $view->with([
                'sidebarHubs' => $sidebarHubs,
                'sidebarPendingVerificationCountsByHub' => $pendingVerificationCountsByHub,
            ]);
        });

        // 2. Define your Role-Based Gates here
        Gate::define('full-access', function ($user) {
            return $user->role === 'admin';
        });

        Gate::define('access-dashboard', function ($user) {
            return true;
        });

        Gate::define('view-products', function ($user) {
            return in_array($user->role, ['admin', 'inventory_staff', 'sales_associate', 'sales_marketing_staff'], true);
        });

        Gate::define('manage-stock-allocation', function ($user) {
            return in_array($user->role, ['admin', 'inventory_staff'], true);
        });

        Gate::define('view-fully-booked-orders', function ($user) {
            return in_array($user->role, ['admin', 'inventory_staff', 'sales_associate', 'sales_marketing_staff'], true);
        });

        Gate::define('manage-fully-booked-orders', function ($user) use ($isBuiltInRole) {
            return $isBuiltInRole($user);
        });

        Gate::define('review-branch-transfers', function ($user) {
            return in_array($user->role, ['admin', 'inventory_staff'], true);
        });

        Gate::define('request-fully-booked-orders', function ($user) {
            return in_array($user->role, ['admin', 'inventory_staff', 'sales_associate', 'sales_marketing_staff'], true);
        });

        Gate::define('view-sponsor-workshop', function ($user) use ($isBuiltInRole) {
            return $isBuiltInRole($user);
        });

        Gate::define('access-inventory', function ($user) {
            return in_array($user->role, ['admin', 'inventory_staff', 'sales_associate', 'sales_marketing_staff']);
        });

        Gate::define('access-sales', function ($user) {
            return in_array($user->role, ['admin', 'inventory_staff', 'sales_associate', 'sales_marketing_staff'], true);
        });

        Gate::define('record-channel-sales', function ($user) {
            return $user->canRecordChannelSales();
        });

        Gate::define('verify-inventory', function ($user) {
            return in_array($user->role, ['admin', 'inventory_staff'], true);
        });

        Gate::define('manage-sales-status', function ($user) {
            return in_array($user->role, ['admin', 'sales_marketing_staff']);
        });

        Gate::define('request-walk-in-replacements', function ($user) {
            return in_array($user->role, ['admin', 'sales_marketing_staff', 'sales_associate'], true);
        });

        Gate::define('view-inventory', function ($user) {
            return in_array($user->role, ['admin', 'inventory_staff', 'sales_associate', 'sales_marketing_staff'], true);
        });

        Gate::define('manage-inventory', function ($user) {
            return in_array($user->role, ['admin', 'inventory_staff']);
        });

        Gate::define('manage-shared-catalog', function ($user) {
            return in_array($user->role, ['admin', 'inventory_staff']);
        });

        Gate::define('manage-branch-transfers', function ($user) {
            return in_array($user->role, ['admin', 'inventory_staff', 'sales_associate'], true);
        });

        Gate::define('submit-branch-transfers', function ($user) {
            return $user->role === 'sales_associate';
        });

        Gate::define('manage-branch-returns', function ($user) {
            return in_array($user->role, ['admin', 'inventory_staff', 'sales_associate'], true);
        });

        Gate::define('manage-master-data', function ($user) {
            return in_array($user->role, ['admin', 'inventory_staff']);
        });

        Gate::define('view-sales-reports', function ($user) {
            return in_array($user->role, ['admin', 'sales_associate', 'sales_marketing_staff'], true);
        });

        Gate::define('view-rejected-sales', function ($user) {
            return in_array($user->role, ['admin', 'inventory_staff', 'sales_associate', 'sales_marketing_staff'], true);
        });

        Gate::define('view-wholesale-reports', function ($user) {
            return in_array($user->role, ['admin', 'sales_marketing_staff'], true);
        });

        Gate::define('view-staff-logs', function ($user) {
            return in_array($user->role, ['admin', 'inventory_staff', 'sales_associate'], true);
        });

        Gate::define('view-transaction-logs', function ($user) {
            return in_array($user->role, ['admin', 'inventory_staff'], true);
        });

        // This makes $sidebarHubs available to your sidebar view on every page
        View::composer('layouts.sidebar', function ($view) {
            $view->with('sidebarHubs', StoreHub::where('status', 'active')->orderBy('name')->get());
        });

        View::composer('*', function ($view) {
            $view->with('hubs', StoreHub::all());

            // If the view doesn't have $selectedHub defined yet, provide a fallback (e.g., null)
            if (! isset($view->getData()['selectedHub'])) {
                $view->with('selectedHub', null);
            }
        });

    }
}
