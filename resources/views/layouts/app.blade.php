<!DOCTYPE html>
<html lang="en">
<head>
    @php
        $pageTitles = [
            'dashboard' => 'Dashboard',
            'products.index' => 'Products',
            'national-inventory.index' => 'National Inventory',
            'catalog.index' => 'Shared Product Catalog',
            'users.index' => 'Staff Directory',
            'configuration' => 'Configuration',
            'staff-logs.index' => 'Staff Logs',
            'inventory-transactions.index' => 'Transaction Logs',
            'inventory-transactions.return.create' => 'Return Items',
            'inventory-transactions.branch-transfer.create' => 'Branch Transfer',
            'inventory-transactions.fully-booked.index' => 'Fully Booked Orders',
            'inventory-transactions.restock.create' => 'Restock Inventory',
            'inventory-transactions.sponsor.create' => 'Event / Fully Booked',
            'inventory-transactions.transfer.create' => 'Stock Transfer',
            'national-pullouts.create' => 'National Bookstore Pull-Out',
            'product-file-requests.index' => 'Stock Transfer Review Requests',
            'product-file-requests.show' => 'Review Transfer Request',
            'products.create.hub' => 'Add Products to Store',
            'hub.dashboard' => 'Store Hub Dashboard',
            'hub.sales.pending' => 'Pending Sales',
            'hub.branch-returns' => 'Branch Returns',
            'hub.marketplace-orders' => 'Marketplace Orders',
            'hub.marketplace-returns' => 'Marketplace Returns',
            'hub.tiktok-returns' => 'TikTok Returns',
            'hub.report' => 'Sales Report',
            'hub.fully-booked-returns' => 'Fully Booked Returns',
            'hub.fully-booked-rejected' => 'Rejected Fully Booked Orders',
            'hub.wholesale.report' => 'Wholesale Report',
            'event-inventory.index' => 'Event Inventory',
            'event-inventory.transactions' => 'Event Transaction Logs',
            'event-inventory.sales.create' => 'Record Event Sale',
            'sales.pending' => 'Pending Sales',
            'sales.rejected' => 'Rejected Sales',
            'profile.edit' => 'My Profile',
        ];
        $pageTitle = collect($pageTitles)->first(fn ($title, $route) => request()->routeIs($route)) ?? 'Inventory Management System';
    @endphp
    <title>{{ $pageTitle }}</title>
    <link rel="icon" type="image/svg+xml" href="{{ asset('favicon.svg') }}">
    <link rel="shortcut icon" href="{{ asset('favicon.svg') }}">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @stack('styles')

<link rel="stylesheet" href="{{ asset('app-alert.css') }}?v=20260929-branch-transfer-popup2">
</head>
<body class="app-shell sidebar-collapsed">
    <div class="app-layout">
        @include('layouts.sidebar')
        <div id="sidebarBackdrop" class="sidebar-backdrop" aria-hidden="true"></div>

        <div id="appMain" class="app-main">
            <header class="app-topbar">
                <div class="topbar-leading">
                    <button id="mobileSidebarToggle" type="button" class="icon-button d-xl-none" aria-label="Open navigation" aria-controls="appSidebar" aria-expanded="false">
                        <i class="fa-solid fa-bars" aria-hidden="true"></i>
                    </button>
                    <div class="topbar-title-wrap">
                        <div class="topbar-breadcrumb"><span>Art Caravan PH</span><i class="fa-solid fa-chevron-right" aria-hidden="true"></i><span aria-current="page">{{ $pageTitle }}</span></div>
                        <h1 class="topbar-title">{{ $pageTitle }}</h1>
                    </div>
                </div>

            </header>

            <main class="app-content">
                @yield('content')
            </main>
        </div>
    </div>

    <nav class="mobile-action-bar" aria-label="Primary mobile navigation">
        @can('access-dashboard')
        <a href="{{ route('dashboard') }}" class="{{ request()->routeIs('dashboard') ? 'active' : '' }}"><i class="fa-solid fa-chart-pie" aria-hidden="true"></i><span>Dashboard</span></a>
        @endcan
        @can('view-products')
        <a href="{{ route('products.index') }}" class="{{ request()->routeIs('products.*') ? 'active' : '' }}"><i class="fa-solid fa-boxes-stacked" aria-hidden="true"></i><span>Products</span></a>
        @endcan
        @can('view-transaction-logs')
        <a href="{{ route('inventory-transactions.index') }}" class="{{ request()->routeIs('inventory-transactions.index') ? 'active' : '' }}"><i class="fa-solid fa-clipboard-list" aria-hidden="true"></i><span>Transactions</span></a>
        @endcan
        @can('access-sales')
        @if(auth()->user()->store_hub_id)
        <a href="{{ route('hub.dashboard', auth()->user()->store_hub_id) }}" class="{{ request()->routeIs('hub.*') ? 'active' : '' }}"><i class="fa-solid fa-shop" aria-hidden="true"></i><span>Hubs</span></a>
        @endif
        @endcan
        <button id="mobileMoreToggle" type="button" aria-label="Open more navigation"><i class="fa-solid fa-ellipsis" aria-hidden="true"></i><span>More</span></button>
    </nav>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
     @stack('scripts')
<script src="{{ asset('js/app-alert.js') }}?v=20260929-branch-transfer-popup2"></script>
@include('layouts.popup-messages')
<script>
    (() => {
        const mobileToggle = document.getElementById('mobileSidebarToggle');
        const moreToggle = document.getElementById('mobileMoreToggle');
        const backdrop = document.getElementById('sidebarBackdrop');
        const isOverlay = () => window.matchMedia('(max-width: 1199.98px)').matches;
        const setDrawerOpen = open => {
            document.body.classList.toggle('sidebar-drawer-open', open);
            mobileToggle?.setAttribute('aria-expanded', open ? 'true' : 'false');
        };

        mobileToggle?.addEventListener('click', () => setDrawerOpen(true));
        moreToggle?.addEventListener('click', () => setDrawerOpen(true));
        backdrop?.addEventListener('click', () => setDrawerOpen(false));
        document.addEventListener('keydown', event => {
            if (event.key === 'Escape') setDrawerOpen(false);
        });
        window.addEventListener('resize', () => {
            if (!isOverlay()) setDrawerOpen(false);
        });
    })();
    (() => {
        const timeoutMilliseconds = {{ (int) config('session.lifetime') * 60 * 1000 }};
        const activityUrl = @json(route('session.activity'));
        const timeoutUrl = @json(route('session.timeout'));
        const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content;
        let logoutTimer;
        let lastActivitySync = 0;

        const signOutForInactivity = () => {
            const form = document.createElement('form');
            form.method = 'POST';
            form.action = timeoutUrl;
            document.body.appendChild(form);
            form.submit();
        };

        const recordActivity = () => {
            window.clearTimeout(logoutTimer);
            logoutTimer = window.setTimeout(signOutForInactivity, timeoutMilliseconds);

            if (Date.now() - lastActivitySync < 60000) return;

            lastActivitySync = Date.now();
            fetch(activityUrl, {
                method: 'POST',
                credentials: 'same-origin',
                headers: {
                    'X-CSRF-TOKEN': csrfToken,
                    'X-Requested-With': 'XMLHttpRequest',
                },
            }).then(response => {
                if (response.redirected) window.location.assign(response.url);
            }).catch(() => {});
        };

        ['click', 'keydown', 'scroll', 'touchstart', 'mousemove'].forEach(eventName => {
            window.addEventListener(eventName, recordActivity, { passive: true });
        });
        recordActivity();
    })();
</script>
</body>
</html>
