@extends('layouts.app')

@section('content')
@php
    $reportChannel = auth()->user()?->role === 'sales_associate' && ! $hub->is_head_office
        ? 'walk_in'
        : (in_array(auth()->user()?->role, ['sales_associate', 'sales_marketing_staff'], true)
        ? (auth()->user()->sales_channels ?? [])[0] ?? 'all'
        : 'all');
@endphp
<div class="container-fluid p-4">
    <div class="d-flex flex-wrap justify-content-between align-items-center gap-3 mb-4">
        <div>
            <div class="text-uppercase small fw-bold text-muted">Sales Management Hub</div>
            <h2 class="mb-1 fw-bold text-primary">{{ $hub->name }}</h2>
            <span class="badge rounded-pill {{ $hub->is_head_office ? 'bg-primary' : 'bg-secondary' }}">
                <i class="fa-solid {{ $hub->is_head_office ? 'fa-building-columns' : 'fa-store' }} me-1"></i>
                {{ $hub->is_head_office ? 'Head Office · Multi-channel' : 'Store · Walk-In' }}
            </span>
        </div>
    </div>

    {{-- Success and Error Flash Messages --}}
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
        @if(($hub->is_head_office && auth()->user()?->role !== 'sales_associate') || (!$hub->is_head_office && auth()->user()?->role === 'sales_associate') || auth()->user()?->role === 'admin' || auth()->user()?->role === 'inventory_staff')
        <div class="col">
            @if($hub->is_head_office)
                <button type="button" class="btn btn-warning text-dark w-100 py-3 shadow-sm" data-bs-toggle="modal" data-bs-target="#recordSaleModal">
                    <i class="fa-solid fa-cart-shopping me-2"></i> Record Multi-Channel Sale
                </button>
            @else
                <button type="button" class="btn btn-success text-white w-100 py-3 shadow-sm" data-bs-toggle="modal" data-bs-target="#walkInSaleModal">
                    <i class="fa-solid fa-cash-register me-2"></i> Record Walk-In Sale
                </button>
            @endif
        </div>
        @endif
        @endcan

        <!-- Sales Report -->
        @can('view-sales-reports')
        <div class="col">
            <a href="{{ route('hub.report', ['hub' => $hub->id, 'channel' => $reportChannel]) }}" class="btn btn-outline-primary w-100 py-3 shadow-sm d-flex align-items-center justify-content-center">
                <i class="fa-solid fa-chart-line me-2"></i> Sales Report
            </a>
        </div>
        @endcan

        <!-- Inventory -->
        <div class="col">
            <a href="{{ route('products.index', ['hub_id' => $hub->id]) }}" class="btn btn-outline-secondary w-100 py-3 shadow-sm d-flex align-items-center justify-content-center">
                <i class="fa-solid fa-boxes-stacked me-2"></i> Inventory
            </a>
        </div>

        @can('manage-inventory')
               <!-- Import Products (CSV Button Trigger) -->
        <div class="col">
            <button type="button" class="btn btn-success text-white w-100 py-3 shadow-sm d-flex align-items-center justify-content-center" data-bs-toggle="modal" data-bs-target="#importProductModal">
                <i class="fa-solid fa-file-arrow-up me-2"></i> Import CSV
            </button>
        </div>      
        
        <!-- Export Products (CSV Modal Trigger) -->
        <div class="col">
            <button type="button" class="btn btn-success text-white w-100 py-3 shadow-sm d-flex align-items-center justify-content-center" data-bs-toggle="modal" data-bs-target="#exportProductModal">
                <i class="fa-solid fa-file-export me-2"></i> Export Products (CSV)
            </button>
        </div>
        @endcan

        @can('verify-inventory')
      <!-- Inventory Verification -->
        <div class="col">
            <a href="{{ route('hub.sales.pending', $hub->id) }}" class="btn btn-outline-warning text-dark w-100 py-3 shadow-sm d-flex align-items-center justify-content-center position-relative">
                <i class="fa-solid fa-clipboard-check me-2"></i> Inventory Verification
                
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
            <a href="{{ route('sales.rejected') }}" class="btn btn-outline-danger w-100 py-3 shadow-sm d-flex align-items-center justify-content-center">
                <i class="fa-solid fa-circle-xmark me-2"></i> Rejected Sales
            </a>
        </div>
        @endif
    </div>
</div>

<!-- Modals -->
@include('hubs.export-modal')
@include('hubs.import-modal')

@can('record-channel-sales')
@if($hub->is_head_office && auth()->user()?->role !== 'sales_associate')
    @include('hubs.record-sale-modal')
@elseif(auth()->user()?->role === 'sales_associate')
    @include('hubs.walk-in-sale-modal')
@elseif(auth()->user()?->role !== 'sales_marketing_staff')
    @include('hubs.walk-in-sale-modal')
@endif
@endcan
@endsection
