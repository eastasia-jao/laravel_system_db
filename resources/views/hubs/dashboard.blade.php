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
    $workspaceChannelCount = $hub->is_head_office
        ? ($currentUser?->role === 'sales_marketing_staff' ? max(1, count($currentUser?->sales_channels ?? [])) : 3)
        : 1;
@endphp
<style>
    .hub-page { max-width: 1540px; }
    .hub-hero { position: relative; overflow: hidden; border-radius: 24px; padding: 2rem; color: #fff; background: linear-gradient(120deg, #102a62 0%, #1d4ed8 55%, #0ea5e9 100%); box-shadow: 0 18px 42px rgba(29, 78, 216, .22); }
    .hub-hero::after { content: ''; position: absolute; width: 290px; height: 290px; right: -75px; top: -115px; border-radius: 50%; background: rgba(255,255,255,.12); }
    .hub-hero::before { content: ''; position: absolute; width: 430px; height: 170px; right: 80px; bottom: -125px; border: 1px solid rgba(255,255,255,.17); border-radius: 50%; transform: rotate(-18deg); }
    .hub-hero-content { position: relative; z-index: 1; }
    .hub-eyebrow { letter-spacing: .12em; font-size: .7rem; font-weight: 700; opacity: .75; }
    .hub-mode { display: inline-flex; align-items: center; gap: .45rem; padding: .45rem .75rem; border: 1px solid rgba(255,255,255,.3); border-radius: 999px; background: rgba(255,255,255,.13); font-size: .78rem; }
    .hub-overview { margin-top: -1rem; position: relative; z-index: 2; }
    .hub-metric { min-height: 116px; padding: 1rem; border: 1px solid #e3eaf4; border-radius: 16px; background: #fff; box-shadow: 0 9px 25px rgba(15, 23, 42, .07); }
    .hub-metric-icon { width: 44px; height: 44px; flex: 0 0 44px; display: inline-flex; align-items: center; justify-content: center; border-radius: 13px; background: #eff6ff; color: #2563eb; font-size: 1.1rem; }
    .hub-metric-icon.success { background: #ecfdf5; color: #059669; }
    .hub-metric-icon.warning { background: #fff7ed; color: #ea580c; }
    .hub-metric-icon.violet { background: #f5f3ff; color: #7c3aed; }
    .hub-metric-label { color: #64748b; font-size: .72rem; font-weight: 700; letter-spacing: .04em; text-transform: uppercase; }
    .hub-metric-value { margin-top: .22rem; color: #13294b; font-size: 1.3rem; font-weight: 800; line-height: 1.15; }
    .hub-metric-help { margin-top: .25rem; color: #94a3b8; font-size: .74rem; }
    .hub-workspace { padding: 1.25rem; border: 1px solid #e3eaf4; border-radius: 18px; background: rgba(255,255,255,.82); box-shadow: 0 10px 28px rgba(15, 23, 42, .055); }
    .hub-actions { position: relative; z-index: 1; }
    .hub-action { min-height: 92px; border: 1px solid #e3eaf4; border-radius: 14px; background: #fff; color: #1e293b; box-shadow: 0 5px 16px rgba(15, 23, 42, .045); transition: transform .18s ease, box-shadow .18s ease, border-color .18s ease; }
    .hub-action:hover { color: #1d4ed8; transform: translateY(-3px); border-color: #bfdbfe; box-shadow: 0 13px 25px rgba(37, 99, 235, .12); }
    .hub-action-icon { width: 42px; height: 42px; flex: 0 0 42px; display: inline-flex; align-items: center; justify-content: center; border-radius: 12px; background: #eff6ff; color: #2563eb; font-size: 1.05rem; }
    .hub-action-label { display: block; margin: 0; font-size: .87rem; font-weight: 750; }
    .hub-action { display: flex !important; flex-direction: row !important; align-items: center !important; justify-content: flex-start !important; gap: .75rem; text-align: left; }
    .hub-section-title { color: #172b4d; font-size: 1.05rem; font-weight: 800; }
    .hub-section-help { color: #64748b; font-size: .82rem; }
    .hub-insight { height: 100%; padding: 1rem; border: 1px solid #e3eaf4; border-radius: 14px; background: #f8fbff; }
    .hub-insight-label { color: #64748b; font-size: .69rem; font-weight: 750; letter-spacing: .08em; text-transform: uppercase; }
    .hub-insight-value { color: #172b4d; font-weight: 750; }
    @media (max-width: 767.98px) {
        .hub-page { padding: 1rem !important; }
        .hub-hero { padding: 1.35rem; border-radius: 18px; }
        .hub-hero h2 { font-size: 1.55rem; }
        .hub-overview { margin-top: 0; }
        .hub-workspace { padding: 1rem; border-radius: 15px; }
        .hub-action { min-height: 76px; }
    }
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

    <div class="row row-cols-1 row-cols-sm-2 row-cols-xl-4 g-3 mb-4 hub-overview">
        <div class="col"><div class="hub-metric d-flex align-items-center gap-3"><span class="hub-metric-icon success"><i class="fa-solid fa-chart-line"></i></span><div><div class="hub-metric-label">Recorded order value</div><div class="hub-metric-value text-success">₱{{ number_format((float) $totalSales, 2) }}</div><div class="hub-metric-help">For this store hub</div></div></div></div>
        <div class="col"><div class="hub-metric d-flex align-items-center gap-3"><span class="hub-metric-icon violet"><i class="fa-solid fa-boxes-stacked"></i></span><div><div class="hub-metric-label">Catalog products</div><div class="hub-metric-value">{{ number_format($totalProducts) }}</div><div class="hub-metric-help">Items available to manage</div></div></div></div>
        <div class="col"><div class="hub-metric d-flex align-items-center gap-3"><span class="hub-metric-icon warning"><i class="fa-solid fa-clipboard-check"></i></span><div><div class="hub-metric-label">Pending verification</div><div class="hub-metric-value">{{ number_format($pendingSalesCount) }}</div><div class="hub-metric-help">Sales and replacements to review</div></div></div></div>
        <div class="col"><div class="hub-metric d-flex align-items-center gap-3"><span class="hub-metric-icon"><i class="fa-solid fa-share-nodes"></i></span><div><div class="hub-metric-label">Workspace channels</div><div class="hub-metric-value">{{ $workspaceChannelCount }}</div><div class="hub-metric-help">{{ $hub->is_head_office ? 'Multi-channel workspace' : 'Walk-in workspace' }}</div></div></div></div>
    </div>

    <section class="hub-workspace mb-4" aria-labelledby="hubQuickActionsTitle">
        <div class="d-flex flex-wrap justify-content-between align-items-end gap-2 mb-3">
            <div>
                <h3 id="hubQuickActionsTitle" class="hub-section-title mb-1">Quick actions</h3>
                <div class="hub-section-help">Common tasks for sales, inventory, and verification.</div>
            </div>
            <div class="small text-muted"><i class="fa-solid fa-bolt text-primary me-1"></i> Choose a workspace action</div>
        </div>
    <div class="row row-cols-1 row-cols-sm-2 row-cols-lg-3 row-cols-xl-4 g-3 hub-actions">
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
    </section>

    <div class="row g-3 mb-2">
        <div class="col-lg-7"><div class="hub-insight"><div class="hub-insight-label mb-2">Workspace status</div><div class="hub-insight-value"><i class="fa-solid fa-circle-check text-success me-2"></i>Your {{ $hub->is_head_office ? 'multi-channel' : 'walk-in' }} workspace is ready for daily operations.</div><div class="small text-muted mt-2">Use Quick actions to record sales, manage inventory, and review pending verification tasks.</div></div></div>
        <div class="col-lg-5"><div class="hub-insight"><div class="hub-insight-label mb-2">Latest product import</div><div class="hub-insight-value">{{ $latestImport ? ($latestImport->file_name ?: 'Product import') : 'No product import recorded' }}</div><div class="small text-muted mt-2">{{ $latestImport?->created_at ? 'Recorded '.$latestImport->created_at->format('M d, Y · g:i A') : 'Import activity will appear here after a CSV import.' }}</div></div></div>
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
