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
</style>
<div class="container-fluid p-4">
    <x-page-header class="mb-4" eyebrow="Sales management hub" :title="$hub->name" :description="$hub->is_head_office ? 'Head Office · Multi-channel operations' : 'Store · Walk-In operations'" :icon="$hub->is_head_office ? 'fa-building-columns' : 'fa-store'" />

    @if(session('success'))
        <div class="alert alert-success alert-dismissible fade show shadow-sm mb-4" role="alert">
            <i class="fa-solid fa-circle-check me-2"></i> {{ session('success') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    @endif

    @if(session('error'))
        <div class="alert alert-danger alert-dismissible fade show shadow-sm mb-4" role="alert">
            <i class="fa-solid fa-triangle-exclamation me-2"></i> {{ session('error') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    @endif

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

        @if($hub->is_head_office && auth()->user()?->role === 'sales_marketing_staff')
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

        @if(auth()->user()?->role === 'sales_marketing_staff' && auth()->user()->hasSalesChannel('fully_booked'))
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
        
        <!-- Export Products (CSV Modal Trigger) -->
        <div class="col">
            <button type="button" class="hub-action w-100 p-3" data-bs-toggle="modal" data-bs-target="#exportProductModal">
                <span class="hub-action-icon"><i class="fa-solid fa-file-export"></i></span><span class="hub-action-label">Export CSV</span>
            </button>
        </div>
        @endcan

        @can('verify-inventory')
      <!-- Inventory Verification -->
        <div class="col">
            <a href="{{ route('hub.sales.pending', $hub->id) }}" class="hub-action w-100 p-3 d-flex flex-column align-items-center justify-content-center position-relative">
                <span class="hub-action-icon"><i class="fa-solid fa-clipboard-check"></i></span><span class="hub-action-label">Verify Sales</span>
                
                @if(isset($pendingSalesCount) && $pendingSalesCount > 0)
                    <span class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-danger" aria-label="{{ $pendingSalesCount }} inventory item(s) awaiting verification, including unprocessed Fully Booked orders">
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
