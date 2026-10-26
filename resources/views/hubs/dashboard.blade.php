@extends('layouts.app')

@section('content')
@php
    $reportableChannels = ['online', 'wholesale', 'walk_in'];
    $currentUser = auth()->user();
    $assignedReportChannels = collect($currentUser?->sales_channels ?? [])
        ->map(fn ($channel) => strtolower(str_replace(['-', ' '], '_', (string) $channel)))
        ->filter(fn ($channel) => in_array($channel, $reportableChannels, true))
        ->values();
    $reportChannel = $hub->is_head_office
        ? (in_array($currentUser?->role, ['sales_associate', 'sales_marketing_staff'], true)
            ? $assignedReportChannels->first()
            : 'all')
        : 'walk_in';
    $isAdminDashboard = $currentUser?->role === 'admin';

    $adminActionGroups = [];
    if ($isAdminDashboard) {
        $salesActions = [[
            'label' => $hub->is_head_office ? 'Record Sale' : 'Record Walk-In',
            'icon' => $hub->is_head_office ? 'fa-cart-shopping' : 'fa-cash-register',
            'modal' => $hub->is_head_office ? '#recordSaleModal' : '#walkInSaleModal',
        ]];
        if ($reportChannel) {
            $salesActions[] = ['label' => 'Sales Report', 'icon' => 'fa-chart-line', 'url' => route('hub.report', ['hub' => $hub->id, 'channel' => $reportChannel])];
        }
        $salesActions[] = ['label' => 'Verify Sales', 'icon' => 'fa-clipboard-check', 'url' => route('hub.sales.pending', $hub->id), 'pending' => $pendingSalesCount ?? 0];
        $salesActions[] = ['label' => 'Rejected Sales', 'icon' => 'fa-circle-xmark', 'url' => route('sales.rejected', ['hub_id' => $hub->id])];
        if (! $hub->is_head_office) {
            $salesActions[] = ['label' => 'Return Items', 'icon' => 'fa-rotate-left', 'url' => route('hub.branch-returns', ['hub' => $hub->id])];
        }
        $adminActionGroups[] = ['title' => 'Sales operations', 'description' => 'Record, verify, and review store sales.', 'actions' => $salesActions];

        if ($hub->is_head_office) {
            $marketplaceActions = [];
            foreach (['shopee' => 'Shopee', 'lazada' => 'Lazada', 'tiktok' => 'TikTok'] as $channel => $label) {
                $marketplaceActions[] = ['label' => $label.' Orders', 'icon' => 'fa-bag-shopping', 'url' => route('hub.marketplace-orders', ['hub' => $hub->id, 'channel' => $channel])];
                $marketplaceActions[] = [
                    'label' => $label.' Returns',
                    'icon' => 'fa-rotate-left',
                    'url' => $channel === 'tiktok'
                        ? route('hub.tiktok-returns', ['hub' => $hub->id])
                        : route('hub.marketplace-returns', ['hub' => $hub->id, 'channel' => $channel]),
                ];
            }
            $adminActionGroups[] = ['title' => 'Marketplace operations', 'description' => 'Review orders and returns by marketplace.', 'actions' => $marketplaceActions];
            $adminActionGroups[] = ['title' => 'Fully Booked operations', 'description' => 'Manage Head Office orders and return activity.', 'actions' => [
                ['label' => 'Fully Booked Order', 'icon' => 'fa-file-arrow-up', 'url' => route('inventory-transactions.sponsor.create', ['hub_id' => $hub->id, 'activity_type' => 'fully_booked'])],
                ['label' => 'Fully Booked Returns', 'icon' => 'fa-rotate-left', 'url' => route('hub.fully-booked-returns', ['hub' => $hub->id])],
                ['label' => 'Rejected Fully Booked', 'icon' => 'fa-circle-xmark', 'url' => route('hub.fully-booked-rejected', ['hub' => $hub->id])],
            ]];
        }

        $adminActionGroups[] = ['title' => 'Inventory management', 'description' => 'Review inventory or exchange product files.', 'actions' => [
            ['label' => 'Inventory', 'icon' => 'fa-boxes-stacked', 'url' => route('products.index', ['hub_id' => $hub->id])],
            ['label' => 'Import CSV', 'icon' => 'fa-file-arrow-up', 'modal' => '#importProductModal'],
            ['label' => 'Export Products', 'icon' => 'fa-file-export', 'modal' => '#exportProductModal'],
        ]];
    }
@endphp
<style>
    .hub-action {
        display: flex !important;
        flex-direction: row !important;
        align-items: center !important;
        justify-content: center !important;
        min-height: 56px;
        border: 1px solid var(--bs-primary);
        border-radius: .375rem;
        background: #fff;
        color: var(--bs-primary);
        box-shadow: var(--bs-box-shadow-sm);
        text-decoration: none;
    }
    .hub-action:hover { background: var(--bs-primary); color: #fff; }
    .hub-action-icon { margin-right: .5rem; }
    .hub-action-label { font-weight: 600; }
    .admin-hub-action-groups { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 1rem; margin-bottom: 1.5rem; }
    .admin-hub-action-group { border: 1px solid #dbe4f0; border-radius: 1rem; padding: 1rem; background: #fff; box-shadow: 0 .25rem .8rem rgb(15 23 42 / 5%); }
    .admin-hub-action-heading { margin-bottom: .85rem; }
    .admin-hub-action-heading h2 { margin: 0; color: #17345f; font-size: .78rem; font-weight: 800; letter-spacing: .06em; text-transform: uppercase; }
    .admin-hub-action-heading p { margin: .25rem 0 0; color: #64748b; font-size: .8rem; }
    .admin-hub-action-grid { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: .75rem; }
    .admin-hub-action { justify-content: flex-start !important; min-height: 64px; padding: .75rem 1rem; border-color: #d7e3ff; box-shadow: none; text-align: left; }
    .admin-hub-action .hub-action-icon { display: grid; flex: 0 0 2.1rem; width: 2.1rem; height: 2.1rem; place-items: center; border-radius: .65rem; background: #edf4ff; }
    .admin-hub-action:hover .hub-action-icon { background: rgb(255 255 255 / 18%); }
    @media (max-width: 991.98px) { .admin-hub-action-groups { grid-template-columns: 1fr; } }
    @media (max-width: 575.98px) { .admin-hub-action-grid { grid-template-columns: 1fr; } }
</style>
<div class="container-fluid p-4">
    <x-page-header class="mb-4" eyebrow="Sales management hub" :title="$hub->name" :description="$hub->is_head_office ? 'Head Office · Multi-channel operations' : 'Store · Walk-In operations'" :icon="$hub->is_head_office ? 'fa-building-columns' : 'fa-store'" />

    @if(session('error'))
        <div class="alert alert-danger alert-dismissible fade show shadow-sm mb-4" role="alert">
            <i class="fa-solid fa-triangle-exclamation me-2"></i> {{ session('error') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    @endif

    @if($isAdminDashboard)
    <div class="admin-hub-action-groups" data-admin-hub-action-groups>
        @foreach($adminActionGroups as $group)
        <section class="admin-hub-action-group">
            <header class="admin-hub-action-heading">
                <h2>{{ $group['title'] }}</h2>
                <p>{{ $group['description'] }}</p>
            </header>
            <div class="admin-hub-action-grid">
                @foreach($group['actions'] as $action)
                    @if(isset($action['modal']))
                    <button type="button" class="hub-action admin-hub-action w-100 position-relative" data-bs-toggle="modal" data-bs-target="{{ $action['modal'] }}">
                    @else
                    <a href="{{ $action['url'] }}" class="hub-action admin-hub-action w-100 position-relative">
                    @endif
                        <span class="hub-action-icon"><i class="fa-solid {{ $action['icon'] }}"></i></span>
                        <span class="hub-action-label">{{ $action['label'] }}</span>
                        @if(($action['pending'] ?? 0) > 0)
                            <span class="badge rounded-pill bg-danger ms-auto" aria-label="{{ $action['pending'] }} inventory item(s) awaiting verification">{{ $action['pending'] }}</span>
                        @endif
                    @if(isset($action['modal']))
                    </button>
                    @else
                    </a>
                    @endif
                @endforeach
            </div>
        </section>
        @endforeach
    </div>
    @else
    <div class="row row-cols-1 row-cols-md-4 g-3 mb-4">
        <!-- Conditional Record Sale Button -->
        @can('record-channel-sales')
        @if(($hub->is_head_office && auth()->user()?->role !== 'sales_associate')
            || (!$hub->is_head_office && in_array(auth()->user()?->role, ['admin', 'sales_associate'], true))
            || (!$hub->is_head_office && auth()->user()?->role === 'inventory_staff' && auth()->user()->hasSalesChannel('walk_in')))
        <div class="col">
            @if($hub->is_head_office)
                <button type="button" class="hub-action w-100 p-3" data-bs-toggle="modal" data-bs-target="#recordSaleModal">
                        <span class="hub-action-icon"><i class="fa-solid fa-cart-shopping"></i></span>
                        <span class="hub-action-label">Record Sale</span>
                </button>
            @else
                <button type="button" class="hub-action w-100 p-3" data-bs-toggle="modal" data-bs-target="#walkInSaleModal">
                    <span class="hub-action-icon"><i class="fa-solid fa-cash-register"></i></span>
                    <span class="hub-action-label">Record Walk-In</span>
                </button>
            @endif
        </div>
        @endif
        @endcan

        @can('manage-branch-returns')
        @unless($hub->is_head_office)
        <div class="col">
            <a href="{{ route('hub.branch-returns', ['hub' => $hub->id]) }}" class="hub-action w-100 p-3 d-flex flex-column align-items-center justify-content-center">
                <span class="hub-action-icon"><i class="fa-solid fa-rotate-left"></i></span><span class="hub-action-label">Return Items</span>
            </a>
        </div>
        @endunless
        @endcan

        @if($hub->is_head_office && in_array(auth()->user()?->role, ['admin', 'sales_marketing_staff'], true))
            @foreach(['shopee' => 'Shopee', 'lazada' => 'Lazada', 'tiktok' => 'TikTok'] as $marketplaceChannel => $marketplaceLabel)
                @if(auth()->user()->hasSalesChannel($marketplaceChannel))
                <div class="col">
                    <a href="{{ route('hub.marketplace-orders', ['hub' => $hub->id, 'channel' => $marketplaceChannel]) }}" class="hub-action w-100 p-3 d-flex flex-column align-items-center justify-content-center">
                        <span class="hub-action-icon"><i class="fa-solid fa-bag-shopping"></i></span><span class="hub-action-label">{{ $marketplaceLabel }} Orders</span>
                    </a>
                </div>
                <div class="col">
                    <a href="{{ $marketplaceChannel === 'tiktok'
                        ? route('hub.tiktok-returns', ['hub' => $hub->id])
                        : route('hub.marketplace-returns', ['hub' => $hub->id, 'channel' => $marketplaceChannel]) }}" class="hub-action w-100 p-3 d-flex flex-column align-items-center justify-content-center">
                        <span class="hub-action-icon"><i class="fa-solid fa-rotate-left"></i></span><span class="hub-action-label">{{ $marketplaceLabel }} Returns</span>
                    </a>
                </div>
                @endif
            @endforeach
        @endif

        <!-- Sales Report -->
        @can('view-sales-reports')
        @if($reportChannel)
        <div class="col">
            <a href="{{ route('hub.report', ['hub' => $hub->id, 'channel' => $reportChannel]) }}" class="hub-action w-100 p-3 d-flex flex-column align-items-center justify-content-center">
                <span class="hub-action-icon"><i class="fa-solid fa-chart-line"></i></span><span class="hub-action-label">Sales Report</span>
            </a>
        </div>
        @endif
        @endcan

        @if($hub->is_head_office && auth()->user()?->role === 'sales_marketing_staff' && auth()->user()->hasSalesChannel('fully_booked'))
        <div class="col">
            <a href="{{ route('inventory-transactions.sponsor.create', ['hub_id' => $hub->id, 'activity_type' => 'fully_booked']) }}" class="hub-action w-100 p-3 d-flex flex-column align-items-center justify-content-center">
                <span class="hub-action-icon"><i class="fa-solid fa-file-arrow-up"></i></span><span class="hub-action-label">Fully Booked Order</span>
            </a>
        </div>
        <div class="col">
            <a href="{{ route('hub.fully-booked-returns', ['hub' => $hub->id]) }}" class="hub-action w-100 p-3 d-flex flex-column align-items-center justify-content-center">
                <span class="hub-action-icon"><i class="fa-solid fa-rotate-left"></i></span><span class="hub-action-label">Fully Booked Returns</span>
            </a>
        </div>
        <div class="col">
            <a href="{{ route('hub.fully-booked-rejected', ['hub' => $hub->id]) }}" class="hub-action w-100 p-3 d-flex flex-column align-items-center justify-content-center">
                <span class="hub-action-icon"><i class="fa-solid fa-circle-xmark"></i></span><span class="hub-action-label">Rejected Fully Booked</span>
            </a>
        </div>
        @endif

        <!-- Inventory -->
        <div class="col">
            <a href="{{ route('products.index', ['hub_id' => $hub->id]) }}" class="hub-action w-100 p-3 d-flex flex-column align-items-center justify-content-center">
                <span class="hub-action-icon"><i class="fa-solid fa-boxes-stacked"></i></span><span class="hub-action-label">Inventory</span>
            </a>
        </div>

        @can('manage-inventory')
        <!-- Import Products (CSV Button Trigger) -->
        <div class="col">
            <button type="button" class="hub-action w-100 p-3" data-bs-toggle="modal" data-bs-target="#importProductModal">
                <span class="hub-action-icon"><i class="fa-solid fa-file-arrow-up"></i></span><span class="hub-action-label">Import CSV</span>
            </button>
        </div>      
        
        <!-- Export Products Modal Trigger -->
        <div class="col">
            <button type="button" class="hub-action w-100 p-3" data-bs-toggle="modal" data-bs-target="#exportProductModal">
                <span class="hub-action-icon"><i class="fa-solid fa-file-export"></i></span><span class="hub-action-label">Export Products</span>
            </button>
        </div>
        @endcan

        @can('verify-inventory')
      <!-- Inventory Verification -->
        <div class="col">
            <a href="{{ route('hub.sales.pending', $hub->id) }}" class="hub-action w-100 p-3 d-flex flex-column align-items-center justify-content-center position-relative">
                <span class="hub-action-icon"><i class="fa-solid fa-clipboard-check"></i></span><span class="hub-action-label">Verify Sales</span>
                
                @if(isset($pendingSalesCount) && $pendingSalesCount > 0)
                    <span class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-danger" aria-label="{{ $pendingSalesCount }} inventory item(s) awaiting verification">
                        {{ $pendingSalesCount }}
                    </span>
                @endif
            </a>
        </div>
        @endcan

        @if(in_array(auth()->user()?->role, ['admin', 'inventory_staff', 'sales_associate', 'sales_marketing_staff'], true))
        <div class="col">
            <a href="{{ route('sales.rejected', ['hub_id' => $hub->id]) }}" class="hub-action w-100 p-3 d-flex flex-column align-items-center justify-content-center">
                <span class="hub-action-icon"><i class="fa-solid fa-circle-xmark"></i></span><span class="hub-action-label">Rejected Sales</span>
            </a>
        </div>
        @endif
    </div>
    @endif
</div>

<!-- Modals -->
<script src="{{ asset('js/product-suggestions.js') }}?v=20260918-1439"></script>
@can('manage-inventory')
@include('hubs.export-modal')
@include('hubs.import-modal')
@endcan

@can('record-channel-sales')
@if($hub->is_head_office && auth()->user()?->role !== 'sales_associate')
    @include('hubs.record-sale-modal')
@elseif(auth()->user()?->role === 'sales_associate')
    @include('hubs.walk-in-sale-modal')
@elseif(auth()->user()?->role === 'inventory_staff' && auth()->user()->hasSalesChannel('walk_in'))
    @include('hubs.walk-in-sale-modal')
@elseif(!in_array(auth()->user()?->role, ['inventory_staff', 'sales_marketing_staff'], true))
    @include('hubs.walk-in-sale-modal')
@endif
@endcan
@endsection
