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
    .hub-page { max-width: 1500px; }
    .hub-hero { position: relative; overflow: hidden; border-radius: 24px; padding: 2rem; color: #fff; background: linear-gradient(135deg, #1e3a8a, #2563eb 60%, #38bdf8); box-shadow: 0 16px 36px rgba(37, 99, 235, .2); }
    .hub-hero::after { content: ''; position: absolute; width: 240px; height: 240px; right: -70px; top: -100px; border-radius: 50%; background: rgba(255,255,255,.12); }
    .hub-hero-content { position: relative; z-index: 1; }
    .hub-eyebrow { letter-spacing: .12em; font-size: .7rem; font-weight: 700; opacity: .75; }
    .hub-mode { display: inline-flex; align-items: center; gap: .45rem; padding: .45rem .75rem; border: 1px solid rgba(255,255,255,.3); border-radius: 999px; background: rgba(255,255,255,.13); font-size: .78rem; }
    .hub-actions { margin-top: -1rem; position: relative; z-index: 2; }
    .hub-action { min-height: 112px; border: 0; border-radius: 16px; background: #fff; color: #1e293b; box-shadow: 0 8px 24px rgba(15, 23, 42, .08); transition: transform .18s ease, box-shadow .18s ease; }
    .hub-action:hover { color: #1d4ed8; transform: translateY(-3px); box-shadow: 0 14px 28px rgba(15, 23, 42, .13); }
    .hub-action-icon { width: 42px; height: 42px; display: inline-flex; align-items: center; justify-content: center; border-radius: 12px; background: #eff6ff; color: #2563eb; font-size: 1.05rem; }
    .hub-action-label { display: block; margin-top: .65rem; font-size: .82rem; font-weight: 700; }
    .hub-section-title { color: #64748b; font-size: .72rem; font-weight: 700; letter-spacing: .1em; text-transform: uppercase; }
</style>
<div class="container-fluid p-4 hub-page">
    <div class="hub-hero mb-4">
        <div class="hub-hero-content d-flex flex-wrap justify-content-between align-items-end gap-3">
            <div>
                <div class="hub-eyebrow mb-2">Sales Management Hub</div>
                <h2 class="fw-bold mb-2">{{ $hub->name }}</h2>
                <span class="hub-mode">
                    <i class="fa-solid {{ $hub->is_head_office ? 'fa-building-columns' : 'fa-store' }}"></i>
                    {{ $hub->is_head_office ? 'Head Office · Multi-channel' : 'Store · Walk-In' }}
                </span>
            </div>
            <div class="small text-white-50"><i class="fa-solid fa-shield-halved me-1"></i> Verified workspace</div>
        </div>
    </div>

    @can('manage-inventory')
    @include('hubs.import-status-panel')
    @endcan

    {{-- Success and Error Flash Messages --}}

    <div class="hub-section-title mb-2">Quick actions</div>
    <div class="row row-cols-2 row-cols-md-3 row-cols-xl-6 g-3 mb-4 hub-actions">
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
                    <span class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-danger">
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
