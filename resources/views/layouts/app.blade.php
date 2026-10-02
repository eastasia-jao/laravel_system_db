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
            'product-file-requests.index' => 'Product Import / Export Requests',
            'hub.dashboard' => 'Store Hub Dashboard',
            'hub.report' => 'Sales Report',
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
        /* Optional: Smooth transition for content if sidebar ever expands/collapses */
        main {
            transition: margin-left 0.3s ease;
        }
    </style>
<link rel="stylesheet" href="{{ asset('app-alert.css') }}?v=20260929-branch-transfer-popup2">
</head>
<body class="bg-light">
    <div class="d-flex">
        @include('layouts.sidebar')

        <main class="flex-grow-1 p-4" style="margin-left: 280px; width: calc(100% - 280px);">
            @yield('content')
        </main>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
     @stack('scripts')
<script src="{{ asset('js/app-alert.js') }}?v=20260929-branch-transfer-popup2"></script>
@include('layouts.popup-messages')
</body>
</html>
