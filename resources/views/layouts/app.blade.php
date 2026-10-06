<!DOCTYPE html>
<html lang="en">
<head>
    @php
        $pageTitles = [
            'dashboard' => 'Dashboard',
            'products.index' => 'Products',
            'catalog.index' => 'Shared Product Catalog',
            'users.index' => 'Staff Directory',
            'configuration' => 'Configuration',
            'staff-logs.index' => 'Staff Logs',
            'inventory-transactions.index' => 'Transaction Logs',
            'inventory-transactions.return.create' => 'Return Items',
            'product-file-requests.index' => 'Stock Transfer Review Requests',
            'hub.dashboard' => 'Store Hub Dashboard',
            'hub.report' => 'Sales Report',
            'hub.fully-booked-returns' => 'Fully Booked Returns',
            'hub.wholesale.report' => 'Wholesale Report',
            'configuration' => 'Configuration',
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

    <style>
        #appSidebar, #appMain { transition: transform .25s ease, margin-left .25s ease, width .25s ease; }
        body.sidebar-collapsed #appSidebar { transform: translateX(-100%); }
        body.sidebar-collapsed #appMain { margin-left: 0 !important; width: 100% !important; }
        body.sidebar-collapsed #appMain > .workspace-page { max-width: none; padding: 1rem !important; }
        #sidebarRestore { display: none; position: fixed; top: 12px; left: 12px; z-index: 1100; }
        body.sidebar-collapsed #sidebarRestore { display: inline-flex; }
        @media (max-width: 991.98px) {
            #appMain { margin-left: 0 !important; width: 100% !important; }
        }
    </style>
<link rel="stylesheet" href="{{ asset('app-alert.css') }}?v=20260929-branch-transfer-popup2">
</head>
<body class="bg-light">
    <div class="d-flex">
        @include('layouts.sidebar')
        <button id="sidebarRestore" type="button" class="btn btn-primary btn-sm shadow" title="Show sidebar" aria-label="Show sidebar">
            <i class="fa-solid fa-bars"></i>
        </button>

        <main id="appMain" class="flex-grow-1 p-4" style="margin-left: 280px; width: calc(100% - 280px);">
            @yield('content')
        </main>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
     @stack('scripts')
<script src="{{ asset('js/app-alert.js') }}?v=20260929-branch-transfer-popup2"></script>
@include('layouts.popup-messages')
<script>
    (() => {
        const toggle = document.getElementById('sidebarToggle');
        const restore = document.getElementById('sidebarRestore');
        const preferenceKey = 'art-caravan-sidebar-collapsed';
        const isMobile = () => window.matchMedia('(max-width: 991.98px)').matches;
        const setCollapsed = (collapsed, persist = true) => {
            document.body.classList.toggle('sidebar-collapsed', collapsed);
            if (persist) localStorage.setItem(preferenceKey, collapsed ? '1' : '0');
            if (toggle) {
                toggle.title = collapsed ? 'Show sidebar' : 'Hide sidebar';
                toggle.setAttribute('aria-label', toggle.title);
            }
        };

        setCollapsed(isMobile() || localStorage.getItem(preferenceKey) === '1', false);
        toggle?.addEventListener('click', () => setCollapsed(!document.body.classList.contains('sidebar-collapsed')));
        restore?.addEventListener('click', () => setCollapsed(false));
        window.addEventListener('resize', () => {
            if (isMobile()) setCollapsed(true, false);
        });
    })();
    (() => {
        const timeoutMilliseconds = {{ (int) config('session.lifetime') * 60 * 1000 }};
        const activityUrl = @json(route('session.activity'));
        const logoutUrl = @json(route('logout'));
        const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content;
        let logoutTimer;
        let lastActivitySync = 0;

        const signOutForInactivity = () => {
            const form = document.createElement('form');
            form.method = 'POST';
            form.action = logoutUrl;
            form.innerHTML = `<input type="hidden" name="_token" value="${csrfToken}"><input type="hidden" name="idle_logout" value="1">`;
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
