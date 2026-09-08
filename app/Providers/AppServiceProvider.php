<?php

namespace App\Providers;

use App\Models\StoreHub;
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

        View::composer('layouts.sidebar', function ($view) {
            $view->with('sidebarHubs', StoreHub::all());
        });

        // 2. Define your Role-Based Gates here
        Gate::define('full-access', function ($user) {
            return $user->role === 'admin';
        });

        Gate::define('access-inventory', function ($user) {
            return in_array($user->role, ['admin', 'inventory_staff', 'sales_associate', 'sales_marketing_staff']);
        });

        Gate::define('access-sales', function ($user) {
            return in_array($user->role, ['admin', 'inventory_staff', 'sales_associate', 'sales_marketing_staff']);
        });

        Gate::define('record-channel-sales', function ($user) {
            return in_array($user->role, ['admin', 'sales_associate', 'sales_marketing_staff']);
        });

        Gate::define('verify-inventory', function ($user) {
            return in_array($user->role, ['admin', 'inventory_staff']);
        });

        Gate::define('manage-sales-status', function ($user) {
            return in_array($user->role, ['admin', 'sales_marketing_staff']);
        });

        Gate::define('view-inventory', function ($user) {
            return in_array($user->role, ['admin', 'inventory_staff', 'sales_associate', 'sales_marketing_staff']);
        });

        Gate::define('manage-inventory', function ($user) {
            return in_array($user->role, ['admin', 'inventory_staff']);
        });

        Gate::define('manage-branch-transfers', function ($user) {
            return in_array($user->role, ['admin', 'inventory_staff', 'sales_associate']);
        });

        Gate::define('manage-master-data', function ($user) {
            return in_array($user->role, ['admin', 'inventory_staff']);
        });

        Gate::define('view-sales-reports', function ($user) {
            return in_array($user->role, ['admin', 'sales_associate', 'sales_marketing_staff']);
        });

        Gate::define('view-rejected-sales', function ($user) {
            return in_array($user->role, ['admin', 'inventory_staff', 'sales_associate', 'sales_marketing_staff']);
        });

        Gate::define('view-wholesale-reports', function ($user) {
            return in_array($user->role, ['admin', 'sales_marketing_staff']);
        });

        Gate::define('view-staff-logs', function ($user) {
            return in_array($user->role, ['admin', 'inventory_staff', 'sales_associate']);
        });

        Gate::define('view-transaction-logs', function ($user) {
            return in_array($user->role, ['admin', 'inventory_staff', 'sales_associate']);
        });

        // This makes $sidebarHubs available to your sidebar view on every page
        View::composer('layouts.sidebar', function ($view) {
            $view->with('sidebarHubs', StoreHub::all());
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
