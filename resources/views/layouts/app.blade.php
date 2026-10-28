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
            'inventory-transactions.transfer.create' => 'Stock Transfer',
            'inventory-transactions.transfer.ho-to-branch.create' => 'HO to Branch Transfer',
            'inventory-transactions.transfer.branch-to-ho.create' => 'Branch to HO Transfer',
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
        $pageTitle = request()->routeIs('inventory-transactions.sponsor.create')
            ? (($activityType ?? request('activity_type')) === 'fully_booked' ? 'Fully Booked' : 'Event')
            : (collect($pageTitles)->first(fn ($title, $route) => request()->routeIs($route)) ?? 'Inventory Management System');
    @endphp
    <title>{{ $pageTitle }}</title>
    <link rel="icon" type="image/svg+xml" href="{{ asset('favicon.svg') }}">
    <link rel="shortcut icon" href="{{ asset('favicon.svg') }}">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
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

    <div class="modal fade" id="inactivityWarningModal" tabindex="-1" aria-labelledby="inactivityWarningTitle" aria-describedby="inactivityWarningMessage" aria-hidden="true" data-bs-backdrop="static" data-bs-keyboard="false">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content border-0 shadow-lg rounded-4 overflow-hidden">
                <div class="modal-body p-4 p-md-5 text-center">
                    <span class="d-inline-grid place-items-center rounded-circle bg-warning-subtle text-warning-emphasis mb-3" style="width:4rem;height:4rem;place-items:center;font-size:1.5rem" aria-hidden="true">
                        <i class="fa-solid fa-clock"></i>
                    </span>
                    <h2 class="h4 fw-bold text-dark" id="inactivityWarningTitle">Are you still there?</h2>
                    <p class="text-muted mb-2" id="inactivityWarningMessage">You are about to be signed out because of inactivity.</p>
                    <p class="mb-0 fw-semibold text-danger">Signing out in <span id="inactivityCountdown">60 seconds</span>.</p>
                </div>
                <div class="modal-footer justify-content-center gap-2 border-0 bg-light px-4 py-3">
                    <form method="POST" action="{{ route('logout') }}" class="m-0">
                        @csrf
                        <button type="submit" class="btn btn-outline-secondary">Sign Out Now</button>
                    </form>
                    <button type="button" class="btn btn-primary" id="staySignedInButton"><i class="fa-solid fa-shield-halved me-1" aria-hidden="true"></i>Stay Signed In</button>
                </div>
            </div>
        </div>
    </div>

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
        const warningLeadMilliseconds = Math.min(60 * 1000, Math.max(1000, Math.floor(timeoutMilliseconds / 2)));
        const activityUrl = @json(route('session.activity'));
        const timeoutUrl = @json(route('session.timeout'));
        const activeSessionToken = @json(session('active_user_session_token'));
        const activityStorageKey = 'art-caravan-last-activity-{{ auth()->id() }}';
        const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content;
        const warningElement = document.getElementById('inactivityWarningModal');
        const warningCountdown = document.getElementById('inactivityCountdown');
        const staySignedInButton = document.getElementById('staySignedInButton');
        if (!csrfToken) return;
        if (!warningElement || !warningCountdown || !staySignedInButton || !window.bootstrap?.Modal) return;

        const warningModal = window.bootstrap.Modal.getOrCreateInstance(warningElement, {
            backdrop: 'static',
            keyboard: false,
        });
        let logoutTimer;
        let warningTimer;
        let countdownTimer;
        let warningVisible = false;
        let signingOut = false;
        let lastActivitySync = Date.now();
        let lastSharedActivityWrite = 0;
        let lastTimerRefresh = 0;
        let localLastActivity = Date.now();

        const sharedLastActivity = () => {
            try {
                return Math.max(localLastActivity, Number(localStorage.getItem(activityStorageKey)) || 0);
            } catch (error) {
                return localLastActivity;
            }
        };

        const hideInactivityWarning = () => {
            window.clearInterval(countdownTimer);
            warningVisible = false;
            warningModal.hide();
        };

        const signOutForInactivity = () => {
            if (signingOut) return;
            signingOut = true;
            window.clearTimeout(logoutTimer);
            window.clearTimeout(warningTimer);
            window.clearInterval(countdownTimer);
            const form = document.createElement('form');
            form.method = 'POST';
            form.action = timeoutUrl;
            form.innerHTML = `<input type="hidden" name="active_session_token" value="${activeSessionToken}">`;
            document.body.appendChild(form);
            form.submit();
        };

        const updateInactivityCountdown = () => {
            const remaining = Math.max(0, timeoutMilliseconds - (Date.now() - sharedLastActivity()));
            const seconds = Math.ceil(remaining / 1000);
            warningCountdown.textContent = `${seconds} ${seconds === 1 ? 'second' : 'seconds'}`;
            if (remaining <= 0) signOutForInactivity();
        };

        const showInactivityWarning = () => {
            const remaining = timeoutMilliseconds - (Date.now() - sharedLastActivity());
            if (remaining <= 0) {
                signOutForInactivity();
                return;
            }
            if (remaining > warningLeadMilliseconds) {
                scheduleInactivityCheck();
                return;
            }
            if (!warningVisible) {
                warningVisible = true;
                warningModal.show();
            }
            updateInactivityCountdown();
            window.clearInterval(countdownTimer);
            countdownTimer = window.setInterval(updateInactivityCountdown, 1000);
        };

        const scheduleInactivityCheck = () => {
            window.clearTimeout(logoutTimer);
            window.clearTimeout(warningTimer);
            const remaining = Math.max(0, timeoutMilliseconds - (Date.now() - sharedLastActivity()));
            const untilWarning = remaining - warningLeadMilliseconds;
            if (untilWarning <= 0) {
                showInactivityWarning();
            } else {
                warningTimer = window.setTimeout(showInactivityWarning, Math.max(250, untilWarning));
            }
            logoutTimer = window.setTimeout(checkInactivity, Math.max(250, remaining));
        };

        const checkInactivity = () => {
            if (Date.now() - sharedLastActivity() >= timeoutMilliseconds) {
                signOutForInactivity();
                return;
            }

            scheduleInactivityCheck();
        };

        const recordActivity = (force = false) => {
            force = force === true;
            if (warningVisible && !force) return;
            if (force) hideInactivityWarning();
            const now = Date.now();
            localLastActivity = now;
            if (now - lastSharedActivityWrite >= 1000) {
                try {
                    localStorage.setItem(activityStorageKey, String(now));
                } catch (error) {}
                lastSharedActivityWrite = now;
            }
            if (force || now - lastTimerRefresh >= 1000) {
                scheduleInactivityCheck();
                lastTimerRefresh = now;
            }

            if (!force && now - lastActivitySync < 60000) return;

            lastActivitySync = now;
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

        staySignedInButton.addEventListener('click', () => recordActivity(true));

        ['click', 'keydown', 'scroll', 'touchstart', 'mousemove'].forEach(eventName => {
            window.addEventListener(eventName, () => recordActivity(), { passive: true });
        });
        window.addEventListener('focus', () => recordActivity());
        window.addEventListener('storage', event => {
            if (event.key !== activityStorageKey || !event.newValue) return;
            localLastActivity = Math.max(localLastActivity, Number(event.newValue) || 0);
            if (warningVisible) hideInactivityWarning();
            scheduleInactivityCheck();
        });
        document.addEventListener('visibilitychange', () => {
            if (document.visibilityState === 'visible') recordActivity();
        });
        recordActivity();
    })();
</script>
</body>
</html>
