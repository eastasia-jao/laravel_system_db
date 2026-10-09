@extends('layouts.app')

@section('content')
@php
    $channelLabels = [
        'all' => 'All Channels',
        'shopee' => 'Shopee',
        'lazada' => 'Lazada',
        'tiktok' => 'TikTok',
        'online' => 'Online',
        'wholesale' => 'Wholesale',
        'walk_in' => 'Walk-In',
    ];
    $reportChannelLabels = [
        'all' => 'All Channels',
        'online' => 'Online',
        'wholesale' => 'Wholesale',
        'walk_in' => 'Walk-In',
    ];
    $activeLabel = $channelLabels[$channel] ?? 'All Channels';
    $filterParams = array_filter([
        'date_from' => $dateFrom,
        'date_to' => $dateTo,
        'transaction_state' => $transactionState ?? null,
        'search' => $search ?? null,
    ]);
    $visibleChannels = ! $hub->is_head_office
        ? ['walk_in' => $reportChannelLabels['walk_in']]
        : (in_array(auth()->user()?->role, ['sales_associate', 'sales_marketing_staff'], true)
        ? collect($reportChannelLabels)->filter(fn ($label, $value) => $value !== 'all' && auth()->user()->hasSalesChannel($value))->all()
        : $reportChannelLabels);
@endphp

<style>
    @media print {
        @page {
            size: landscape;
            margin: 0.45in;
        }

        body:has(.sales-report-page) {
            background: #fff !important;
        }

        body:has(.sales-report-page) .sidebar {
            display: none !important;
        }

        body:has(.sales-report-page) main {
            margin-left: 0 !important;
            width: 100% !important;
        }

        .sales-report-page {
            padding: 0 !important;
        }

        .sales-report-page .no-print,
        .sales-report-page .pagination,
        .sales-report-page .modal {
            display: none !important;
        }

        .sales-report-page .card {
            box-shadow: none !important;
            border: 1px solid #dee2e6 !important;
        }

        .sales-report-page .table-responsive {
            overflow: visible !important;
        }

        .sales-report-page .wholesale-report-card {
            display: none !important;
        }

        .sales-report-page .sales-report-print-only {
            display: block !important;
        }

        .sales-report-page .report-metrics-row {
            display: none !important;
        }

        .sales-report-page .row {
            --bs-gutter-x: .75rem;
        }

        .sales-report-page .card {
            break-inside: avoid;
        }

        .sales-report-page .report-print-order {
            break-inside: avoid;
            page-break-inside: avoid;
        }
    }

    .sales-report-print-only { display: none; }
    .wholesale-preview-canvas { min-width: 980px; background: #fff; color: #212529; }
    .wholesale-preview-canvas .sales-report-print-only { display: block; }
    .online-preview-canvas { width: 100%; max-width: 100%; background: #fff; color: #212529; }
    .walk-in-preview-canvas { width: 100%; max-width: 100%; min-width: 0; overflow: hidden; background: #fff; color: #212529; }
    .walk-in-preview-canvas .walk-in-report-card { width: 100%; max-width: 100%; }
    .walk-in-preview-canvas .walk-in-report-summary { grid-template-columns: repeat(4, minmax(0, 1fr)); }
    .walk-in-preview-canvas .walk-in-report-summary-card { min-width: 0; }
    @media (max-width: 900px) {
        .walk-in-preview-canvas .walk-in-report-summary { grid-template-columns: repeat(2, minmax(0, 1fr)); }
    }
    @media (max-width: 520px) {
        .walk-in-preview-canvas .walk-in-report-summary { grid-template-columns: 1fr; }
    }
    .tiktok-preview-canvas { width: 1100px; max-width: 100%; background: #fff; color: #212529; }
    .marketplace-preview-canvas { width: 1100px; max-width: 100%; background: #fff; color: #212529; }
    .sales-summary-panel {
        padding: 1rem;
        border: 1px solid #e5e7eb;
        border-radius: 1rem;
        background: linear-gradient(145deg, #f8fbff, #fff 58%);
        box-shadow: 0 6px 18px rgba(15, 23, 42, .06);
    }
    .sales-summary-heading {
        display: flex;
        flex-wrap: wrap;
        align-items: center;
        justify-content: space-between;
        gap: .75rem;
        margin-bottom: .9rem;
    }
    .sales-summary-heading h5 { margin: 0; font-size: .95rem; font-weight: 700; }
    .sales-summary-heading p { margin: .15rem 0 0; color: #64748b; font-size: .75rem; }
    .sales-summary-grid {
        display: grid;
        grid-template-columns: repeat(4, minmax(0, 1fr));
        gap: .65rem;
    }
    .sales-summary-card {
        display: flex;
        align-items: center;
        gap: .7rem;
        min-width: 0;
        min-height: 76px;
        padding: .75rem;
        border: 1px solid #e8edf5;
        border-radius: .8rem;
        background: #fff;
        box-shadow: 0 2px 8px rgba(15, 23, 42, .035);
    }
    .sales-summary-icon {
        display: inline-flex;
        flex: 0 0 2.15rem;
        width: 2.15rem;
        height: 2.15rem;
        align-items: center;
        justify-content: center;
        border-radius: .7rem;
        background: #eff6ff;
        color: #2563eb;
    }
    .sales-summary-copy { min-width: 0; }
    .sales-summary-card .label { color: #64748b; font-size: .68rem; line-height: 1.25; }
    .sales-summary-card .value { margin-top: .25rem; color: #0f172a; font-size: 1.2rem; font-weight: 750; line-height: 1.15; overflow-wrap: anywhere; }
    .sales-summary-card.is-danger { border-top: 3px solid #dc2626; }
    .sales-summary-card.is-warning { border-top: 3px solid #d97706; }
    .sales-summary-card.is-success { border-top: 3px solid #059669; }
    .sales-summary-card.is-primary { border-top: 3px solid #2563eb; }
    .sales-summary-card.is-info { border-top: 3px solid #0891b2; }
    .sales-summary-card.is-danger .sales-summary-icon { background: #fef2f2; color: #dc2626; }
    .sales-summary-card.is-warning .sales-summary-icon { background: #fffbeb; color: #d97706; }
    .sales-summary-card.is-success .sales-summary-icon { background: #ecfdf5; color: #059669; }
    .sales-summary-card.is-primary .sales-summary-icon { background: #eff6ff; color: #2563eb; }
    .sales-summary-card.is-info .sales-summary-icon { background: #ecfeff; color: #0891b2; }
    .sales-metric-icon {
        display: inline-flex;
        flex: 0 0 2.35rem;
        width: 2.35rem;
        height: 2.35rem;
        align-items: center;
        justify-content: center;
        border-radius: .75rem;
        background: #f1f5f9;
        font-size: 1rem;
    }
    .sales-channel-section-heading { padding: .25rem 0 .1rem; }
    .sales-channel-section-heading h5 { margin: 0; font-size: .9rem; font-weight: 700; color: #1e293b; }
    .sales-channel-section-heading p { margin: .15rem 0 0; color: #64748b; font-size: .75rem; }
    .sales-preview-canvas { width: 1100px; max-width: 100%; background: #fff; color: #212529; }
    .sales-preview-canvas .sales-summary-grid { grid-template-columns: repeat(4, minmax(0, 1fr)); }
    .sales-preview-canvas .sales-summary-card:last-child:nth-child(4n + 1) { grid-column: 1 / -1; }
    .all-channels-preview-breakdown { overflow: hidden; border: 1px solid #e2e8f0; border-radius: .8rem; }
    .all-channels-preview-breakdown th { background: #eff6ff; color: #1e3a8a; font-size: .72rem; letter-spacing: .04em; text-transform: uppercase; }
    .all-channels-preview-breakdown td, .all-channels-preview-breakdown th { padding: .7rem .85rem; }
    @media (max-width: 991.98px) {
        .sales-summary-grid { grid-template-columns: repeat(3, minmax(0, 1fr)); }
        .sales-preview-canvas .sales-summary-grid { grid-template-columns: repeat(3, minmax(0, 1fr)); }
    }
    @media (max-width: 767.98px) {
        .sales-summary-grid, .sales-preview-canvas .sales-summary-grid { grid-template-columns: repeat(2, minmax(0, 1fr)); }
    }
    @media (max-width: 479.98px) {
        .sales-summary-grid, .sales-preview-canvas .sales-summary-grid { grid-template-columns: 1fr; }
    }
    .tiktok-overview-panel {
        padding: 1rem;
        border: 1px solid #e5e7eb;
        border-radius: 1rem;
        background: linear-gradient(145deg, #f8fbff, #ffffff 58%);
        box-shadow: 0 6px 18px rgba(15, 23, 42, .06);
    }
    .tiktok-overview-heading {
        display: flex;
        flex-wrap: wrap;
        align-items: center;
        justify-content: space-between;
        gap: .75rem;
        margin-bottom: .9rem;
    }
    .tiktok-overview-heading h5 { margin: 0; font-size: .95rem; font-weight: 700; }
    .tiktok-overview-heading p { margin: .15rem 0 0; color: #64748b; font-size: .75rem; }
    .tiktok-overview-grid {
        display: grid;
        grid-template-columns: repeat(5, minmax(0, 1fr));
        gap: .65rem;
    }
    .tiktok-overview-card {
        display: flex;
        align-items: center;
        gap: .65rem;
        min-width: 0;
        min-height: 76px;
        padding: .7rem;
        border: 1px solid #e8edf5;
        border-radius: .8rem;
        background: #fff;
        box-shadow: 0 2px 8px rgba(15, 23, 42, .035);
    }
    .tiktok-overview-icon {
        display: inline-flex;
        flex: 0 0 2.15rem;
        width: 2.15rem;
        height: 2.15rem;
        align-items: center;
        justify-content: center;
        border-radius: .7rem;
        background: #eff6ff;
        color: #2563eb;
    }
    .tiktok-overview-card.is-danger .tiktok-overview-icon { background: #fef2f2; color: #dc2626; }
    .tiktok-overview-card.is-warning .tiktok-overview-icon { background: #fffbeb; color: #d97706; }
    .tiktok-overview-card.is-success .tiktok-overview-icon { background: #ecfdf5; color: #059669; }
    .tiktok-overview-copy { min-width: 0; }
    .tiktok-overview-label { color: #64748b; font-size: .68rem; line-height: 1.25; }
    .tiktok-overview-value { margin-top: .15rem; color: #0f172a; font-size: 1.2rem; font-weight: 750; line-height: 1.1; }
    .tiktok-overview-status {
        flex: 0 0 auto;
        padding: .35rem .6rem;
        border: 1px solid #a7f3d0;
        border-radius: 999px;
        background: #ecfdf5;
        color: #047857;
        font-size: .68rem;
        font-weight: 700;
    }
    .report-metrics-row > [class*="col-"] > .card {
        overflow: hidden;
        border: 1px solid #e8edf5 !important;
        border-radius: .8rem;
        background: linear-gradient(145deg, #fff, #f8fbff);
        box-shadow: 0 3px 12px rgba(15, 23, 42, .055) !important;
    }
    .report-metrics-row > [class*="col-"] > .card .card-body { padding: .9rem; }
    .report-metrics-row > [class*="col-"] > .card .fs-4 { font-size: 1.35rem !important; }
    .tiktok-preview-canvas .tiktok-overview-grid { grid-template-columns: repeat(2, minmax(0, 1fr)); }
    .tiktok-preview-canvas .tiktok-overview-card:last-child:nth-child(odd) { grid-column: 1 / -1; }
    @media (max-width: 1199.98px) {
        .tiktok-overview-grid { grid-template-columns: repeat(3, minmax(0, 1fr)); }
    }
    @media (max-width: 767.98px) {
        .tiktok-overview-grid { grid-template-columns: repeat(2, minmax(0, 1fr)); }
    }
    @media (max-width: 479.98px) {
        .tiktok-overview-grid { grid-template-columns: 1fr; }
    }
    .online-overview-panel {
        padding: 1.25rem;
        border: 1px solid #e5e7eb;
        border-radius: 1rem;
        background: linear-gradient(145deg, #f8fbff, #ffffff 58%);
        box-shadow: 0 6px 18px rgba(15, 23, 42, .06);
    }
    .online-overview-heading {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 1rem;
        margin-bottom: 1rem;
    }
    .online-overview-heading > i {
        width: 2.25rem;
        height: 2.25rem;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        border-radius: .75rem;
        background: #eaf2ff;
    }
    .online-overview-panel .card {
        border: 1px solid #edf0f5 !important;
        box-shadow: 0 2px 8px rgba(15, 23, 42, .04) !important;
    }
    .online-overview-metrics {
        display: grid;
        grid-template-columns: repeat(4, minmax(0, 1fr));
        gap: .85rem;
        margin: 0;
    }
    .online-overview-metrics > [class*="col-"] {
        width: auto;
        max-width: none;
        padding: 0;
    }
    .online-overview-metrics .card {
        min-height: 100%;
    }
    .online-overview-metrics .card-body {
        padding: 1rem;
    }
    .online-overview-metrics .fs-4 {
        font-size: clamp(1.05rem, 1.25vw, 1.35rem) !important;
        white-space: nowrap;
    }
    @media (min-width: 768px) {
        .online-overview-primary-metrics > :first-child {
            grid-column: span 2;
        }
        .online-overview-primary-metrics > :first-child .card {
            background: linear-gradient(135deg, #f0fdf4, #ffffff 78%);
            border-left: 3px solid #059669 !important;
        }
        .online-overview-primary-metrics > :first-child .fs-4 {
            font-size: clamp(1.35rem, 1.8vw, 1.8rem) !important;
        }
        .online-overview-reconciliation { grid-template-columns: repeat(3, minmax(0, 1fr)); }
    }
    @media (max-width: 767.98px) {
        .online-overview-metrics { grid-template-columns: repeat(2, minmax(0, 1fr)); }
    }
    @media (max-width: 479.98px) {
        .online-overview-metrics { grid-template-columns: 1fr; }
        .online-overview-primary-metrics > :first-child { grid-column: auto; }
    }
    .online-overview-divider {
        display: flex;
        align-items: center;
        gap: .75rem;
        margin: 1.25rem 0 .75rem;
        color: #64748b;
        font-size: .7rem;
        font-weight: 700;
        letter-spacing: .08em;
        text-transform: uppercase;
    }
    .online-overview-divider::after {
        content: "";
        height: 1px;
        flex: 1;
        background: #dbe3ef;
    }
    .tiktok-preview-summary { border: 1px solid #e9ecef; border-radius: .75rem; overflow: hidden; }
    .tiktok-preview-summary-row { display: flex; justify-content: space-between; gap: 1rem; padding: .8rem 1rem; }
    .tiktok-preview-summary-row + .tiktok-preview-summary-row { border-top: 1px solid #e9ecef; }
    .tiktok-preview-summary-row.highlight { background: #f0fdf4; }
    .tiktok-preview-summary-row.total { background: #eff6ff; font-size: 1.05rem; font-weight: 700; }
    .new-customer-badge { padding: .2em .55em; color: #047857; background: #ecfdf5; border: 1px solid #a7f3d0; font-size: .58rem; letter-spacing: .04em; vertical-align: middle; }
</style>

<div class="container-fluid p-4 sales-report-page">

    <x-page-header class="mb-4" eyebrow="Reporting workspace" :title="$activeLabel.' Sales Report'" :description="$hub->name.' · Verified sales only'" icon="fa-chart-line">
        <x-slot:actions><div class="d-flex flex-wrap gap-2 no-print">
            @if($channel === 'wholesale')
                <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#wholesaleReportPreviewModal">
                    <i class="fa-solid fa-image me-1"></i> Preview / Save PNG
                </button>
            @elseif($channel === 'tiktok')
                <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#tiktokReportPreviewModal">
                    <i class="fa-solid fa-image me-1"></i> Preview / Save PNG
                </button>
            @elseif($channel === 'online')
                <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#onlineReportPreviewModal">
                    <i class="fa-solid fa-image me-1"></i> Preview / Save PNG
                </button>
            @elseif($channel === 'walk_in')
                <button type="button" class="btn btn-warning" data-bs-toggle="modal" data-bs-target="#walkInReportPreviewModal">
                    <i class="fa-solid fa-image me-1"></i> Preview / Save PNG
                </button>
            @elseif(in_array($channel, ['shopee', 'lazada'], true))
                <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#marketplaceReportPreviewModal">
                    <i class="fa-solid fa-image me-1"></i> Preview / Save PNG
                </button>
            @elseif($channel === 'all')
                <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#allChannelsReportPreviewModal">
                    <i class="fa-solid fa-image me-1"></i> Preview / Save PNG
                </button>
            @else
                <button type="button" class="btn btn-primary" onclick="window.print()">
                    <i class="fa-solid fa-file-pdf me-1"></i> Print / Save PDF
                </button>
            @endif
            <a href="{{ route('hub.dashboard', $hub->id) }}" class="btn btn-outline-secondary">
                <i class="fa-solid fa-arrow-left me-1"></i> Back to Dashboard
            </a>
        </div></x-slot:actions>
    </x-page-header>

    <div class="card shadow-sm border-0 mb-4 no-print">
        <div class="card-body">
            <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
                <div class="d-flex flex-wrap gap-2">
                @foreach($visibleChannels as $value => $label)
                    <a href="{{ route('hub.report', ['hub' => $hub->id, 'channel' => $value] + $filterParams) }}"
                       class="btn btn-sm {{ $channel === $value ? 'btn-primary' : 'btn-outline-primary' }}">
                        {{ $label }}
                    </a>
                @endforeach
                </div>
                @if(! $hub->is_head_office)
                    <div class="small fw-semibold text-secondary" data-branch-report-today>
                        Today: {{ now()->format('M d, Y') }}
                    </div>
                @endif
            </div>

            <form method="GET" action="{{ route('hub.report', $hub->id) }}" class="row g-2 align-items-end">
                <input type="hidden" name="channel" value="{{ $channel }}">
                @if($channel === 'wholesale')
                    <input type="hidden" name="transaction_state" value="{{ $transactionState }}">
                @endif
                <div class="col-sm-4 col-md-3">
                    <label class="form-label small fw-semibold">{{ in_array($channel, ['shopee', 'lazada'], true) ? 'Date of Arrangement From' : 'Date From' }}</label>
                    <input type="date" name="date_from" value="{{ $dateFrom }}" class="form-control form-control-sm">
                </div>
                <div class="col-sm-4 col-md-3">
                    <label class="form-label small fw-semibold">{{ in_array($channel, ['shopee', 'lazada'], true) ? 'Date of Arrangement To' : 'Date To' }}</label>
                    <input type="date" name="date_to" value="{{ $dateTo }}" class="form-control form-control-sm">
                </div>
                @if($channel === 'online')
                    <div class="col-sm-8 col-md-5">
                        <label class="form-label small fw-semibold">Search order</label>
                        <input type="search" name="search" value="{{ $search ?? '' }}" class="form-control form-control-sm" placeholder="Customer, order, remarks">
                    </div>
                @endif
                <div class="col-sm-4 col-md-3 d-flex gap-2">
                    <button class="btn btn-sm btn-primary" type="submit"><i class="fa-solid fa-filter me-1"></i> Apply</button>
                    @unless($channel === 'wholesale' || ($hub->is_head_office && in_array($channel, ['online', 'walk_in'], true)))
                        <a class="btn btn-sm btn-outline-secondary" href="{{ route('hub.report', ['hub' => $hub->id, 'channel' => $channel]) }}">Reset</a>
                    @endunless
                </div>
            </form>
        </div>
    </div>

    <div class="row g-3 mb-4 report-metrics-row">
        @if($channel === 'tiktok')
            <div class="col-12"><div class="tiktok-live-section-label">Financial overview</div></div>
            @include('hubs.reports.metric', ['label' => 'Gross Sales: Total Price after Discount', 'value' => $metrics['total_sales'], 'color' => 'success', 'columnClass' => 'col-sm-6 col-xl-4'])
            @include('hubs.reports.metric', ['label' => 'Total Shipping Fee 5%', 'value' => $metrics['shipping_service_fees'], 'color' => 'info', 'columnClass' => 'col-sm-6 col-xl-4'])
            @include('hubs.reports.metric', ['label' => 'Total Sales After Transactions Fee', 'value' => $metrics['net_platform_payout'], 'color' => 'success', 'columnClass' => 'col-sm-6 col-xl-4'])
            <div class="col-12 mt-2">
                <section class="tiktok-overview-panel" aria-label="TikTok activity overview">
                    <div class="tiktok-overview-heading">
                        <div><h5>Activity overview</h5><p>Order and customer activity for the selected date range</p></div>
                        <span class="tiktok-overview-status">Verified sales only</span>
                    </div>
                    <div class="tiktok-overview-grid">
                        <div class="tiktok-overview-card">
                            <span class="tiktok-overview-icon" aria-hidden="true"><i class="fa-solid fa-bag-shopping"></i></span>
                            <div class="tiktok-overview-copy"><div class="tiktok-overview-label">Total orders</div><div class="tiktok-overview-value">{{ $totalTransactions }}</div></div>
                        </div>
                        <div class="tiktok-overview-card is-danger">
                            <span class="tiktok-overview-icon" aria-hidden="true"><i class="fa-solid fa-rotate-left"></i></span>
                            <div class="tiktok-overview-copy"><div class="tiktok-overview-label">Refunded items</div><div class="tiktok-overview-value">{{ $metrics['refund_count'] }}</div></div>
                        </div>
                        <div class="tiktok-overview-card is-warning">
                            <span class="tiktok-overview-icon" aria-hidden="true"><i class="fa-solid fa-arrows-rotate"></i></span>
                            <div class="tiktok-overview-copy"><div class="tiktok-overview-label">Approved item replacements</div><div class="tiktok-overview-value">{{ $metrics['replacement_count'] }}</div></div>
                        </div>
                        <div class="tiktok-overview-card">
                            <span class="tiktok-overview-icon" aria-hidden="true"><i class="fa-solid fa-users"></i></span>
                            <div class="tiktok-overview-copy"><div class="tiktok-overview-label">Total customers</div><div class="tiktok-overview-value">{{ number_format($customerMetrics['total']) }}</div></div>
                        </div>
                        <div class="tiktok-overview-card is-success">
                            <span class="tiktok-overview-icon" aria-hidden="true"><i class="fa-solid fa-user-plus"></i></span>
                            <div class="tiktok-overview-copy"><div class="tiktok-overview-label">New customers</div><div class="tiktok-overview-value">{{ number_format($customerMetrics['new']) }}</div></div>
                        </div>
                    </div>
                </section>
            </div>
        @elseif($channel === 'wholesale')
            <div class="col-12"><div class="sales-channel-section-heading"><h5>Activity overview</h5><p>Sales, returns, payment, and customer summary</p></div></div>
            @include('hubs.reports.metric', ['label' => 'Total Sales', 'value' => $wholesaleCollectedSales, 'color' => 'success', 'description' => 'Collected from paid and partially paid orders; unpaid orders are excluded.'])
            @include('hubs.reports.metric', ['label' => 'Total Shipping Fee', 'value' => $metrics['shipping_fees'], 'color' => 'info', 'description' => 'Shipping is shown separately and is not included in Total Sales.'])
            @include('hubs.reports.metric', ['label' => 'Replacement Requests', 'value' => $metrics['replacement_count'], 'money' => false, 'color' => 'warning'])
            @include('hubs.reports.metric', ['label' => 'Wholesale Return Entries', 'value' => $metrics['return_count'], 'money' => false, 'color' => 'info'])
            @include('hubs.reports.metric', ['label' => 'Total Refund Cost', 'value' => $metrics['refund_total'], 'color' => 'danger'])
            @include('hubs.reports.metric', ['label' => 'Total Customers', 'value' => $customerMetrics['total'], 'money' => false, 'color' => 'primary'])
            @include('hubs.reports.metric', ['label' => 'New Customers', 'value' => $customerMetrics['new'], 'money' => false, 'color' => 'success'])
        @elseif($channel === 'online')
            <div class="col-12">
                <section class="online-overview-panel">
                    <div class="online-overview-heading">
                        <div>
                            <div class="small text-primary fw-bold text-uppercase">Online sales overview</div>
                            <div class="text-muted small">A quick summary of verified orders and payment reconciliation.</div>
                        </div>
                        <i class="fa-solid fa-chart-line text-primary" aria-hidden="true"></i>
                    </div>
                    <div class="online-overview-metrics online-overview-primary-metrics">
                        @include('hubs.reports.metric', ['label' => 'Total Sales', 'value' => $metrics['total_sales'], 'color' => 'success'])
                        @include('hubs.reports.metric', ['label' => 'No. of Transactions', 'value' => $totalTransactions, 'money' => false, 'color' => 'dark'])
                        @include('hubs.reports.metric', ['label' => 'No. of Returns', 'value' => $metrics['return_count'], 'money' => false, 'color' => 'danger'])
                        @include('hubs.reports.metric', ['label' => 'No. of Replacements', 'value' => $metrics['replacement_count'], 'money' => false, 'color' => 'warning'])
                        @include('hubs.reports.metric', ['label' => 'Total Refund Cost', 'value' => $metrics['refund_total'], 'color' => 'danger'])
                        @include('hubs.reports.metric', ['label' => 'Total Customers', 'value' => $customerMetrics['total'], 'money' => false, 'color' => 'primary'])
                        @include('hubs.reports.metric', ['label' => 'New Customers', 'value' => $customerMetrics['new'], 'money' => false, 'color' => 'success'])
                    </div>
                    <div class="online-overview-divider">
                        <span>Payment reconciliation</span>
                    </div>
                    <div class="online-overview-metrics online-overview-reconciliation">
                        @include('hubs.reports.metric', ['label' => 'Shipping Fees', 'value' => $metrics['shipping_fees'], 'color' => 'info'])
                        @include('hubs.reports.metric', ['label' => 'Proof Amount', 'value' => $metrics['proof_amount'], 'color' => 'primary'])
                        @include('hubs.reports.metric', ['label' => 'Reconciliation Difference', 'value' => $metrics['difference'], 'color' => abs($metrics['difference']) < 0.01 ? 'success' : 'danger'])
                    </div>
                </section>
            </div>
        @elseif(in_array($channel, ['shopee', 'lazada'], true))
            <div class="col-12"><div class="sales-channel-section-heading"><h5>Activity overview</h5><p>Sales, returns, and customer summary</p></div></div>
            @include('hubs.reports.metric', ['label' => 'Total Gross Sales', 'value' => $metrics['gross_sales'], 'color' => 'primary', 'columnClass' => 'col-sm-3'])
            @include('hubs.reports.metric', ['label' => 'Total Discount', 'value' => $metrics['discounts'], 'color' => 'danger', 'columnClass' => 'col-sm-3'])
            @include('hubs.reports.metric', ['label' => 'Total Transactions', 'value' => $totalTransactions, 'money' => false, 'color' => 'dark', 'columnClass' => 'col-sm-3'])
            @include('hubs.reports.metric', ['label' => 'Total Return Items', 'value' => $metrics['return_quantity'], 'money' => false, 'color' => 'danger', 'columnClass' => 'col-sm-3'])
            @include('hubs.reports.metric', ['label' => 'Total Refund Cost', 'value' => $metrics['refund_total'], 'color' => 'danger', 'columnClass' => 'col-sm-4'])
            @include('hubs.reports.metric', ['label' => 'Total Customers', 'value' => $customerMetrics['total'], 'money' => false, 'color' => 'primary', 'columnClass' => 'col-sm-4'])
            @include('hubs.reports.metric', ['label' => 'New Customers', 'value' => $customerMetrics['new'], 'money' => false, 'color' => 'success', 'columnClass' => 'col-sm-4'])
        @elseif($channel === 'walk_in' && ! $hub->is_head_office)
            <div class="col-12"><div class="sales-channel-section-heading"><h5>Activity overview</h5><p>Today’s sales and counter activity</p></div></div>
            @include('hubs.reports.metric', ['label' => 'Total Gross Sales', 'value' => $metrics['gross_sales'], 'color' => 'primary', 'columnClass' => 'col-sm-6 col-xl'])
            @include('hubs.reports.metric', ['label' => 'Total Discounts', 'value' => $metrics['discounts'], 'color' => 'danger', 'columnClass' => 'col-sm-6 col-xl'])
            <div class="col-sm-6 col-xl">
                <div class="card shadow-sm border-0 h-100">
                    <div class="card-body d-flex align-items-center gap-3">
                        <span class="sales-metric-icon text-success" aria-hidden="true"><i class="fa-solid fa-chart-line"></i></span>
                        <div class="min-w-0">
                            <div class="small text-muted fw-semibold mb-1">Daily Total Sales</div>
                            <div class="fs-4 fw-bold text-success">₱{{ number_format((float) $totalSales, 2) }}</div>
                        </div>
                    </div>
                    <div class="card-body pt-0">
                        <div class="small text-muted mt-2" data-branch-daily-mop>
                            @forelse($paymentBreakdown as $payment)
                                <div><span class="fw-semibold">{{ $payment->payment_method }}</span>: ₱{{ number_format($payment->total, 2) }}</div>
                            @empty
                                <div>No payment records</div>
                            @endforelse
                        </div>
                    </div>
                </div>
            </div>
            @include('hubs.reports.metric', ['label' => 'Daily Transactions', 'value' => $totalTransactions, 'money' => false, 'color' => 'dark', 'columnClass' => 'col-sm-6 col-xl'])
            @include('hubs.reports.metric', ['label' => 'Daily Total Purchased Items', 'value' => $totalPurchasedItems, 'money' => false, 'color' => 'warning', 'columnClass' => 'col-sm-6 col-xl'])
        @else
            <div class="col-12"><div class="sales-channel-section-heading"><h5>Activity overview</h5><p>{{ $channel === 'all' ? 'Verified sales summary across channels' : 'Sales and customer summary' }}</p></div></div>
            @include('hubs.reports.metric', ['label' => 'Gross Sales', 'value' => $metrics['gross_sales'], 'color' => 'primary'])
            @include('hubs.reports.metric', ['label' => 'Discounts', 'value' => $metrics['discounts'], 'color' => 'danger'])
            @include('hubs.reports.metric', ['label' => 'Total Sales', 'value' => $totalSales, 'color' => 'success'])
            @include('hubs.reports.metric', ['label' => 'Transactions', 'value' => $totalTransactions, 'money' => false, 'color' => 'dark'])
        @endif
    </div>

    @if($channel === 'all')
        <div class="modal fade" id="allChannelsReportPreviewModal" tabindex="-1" aria-labelledby="allChannelsReportPreviewLabel" aria-hidden="true">
            <div class="modal-dialog modal-xl modal-dialog-scrollable">
                <div class="modal-content">
                    <div class="modal-header">
                        <div><h5 class="modal-title" id="allChannelsReportPreviewLabel">All Channels Sales Report Preview</h5><small class="text-muted">This preview is exactly what will be saved as PNG.</small></div>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body overflow-auto bg-secondary-subtle">
                        <div id="allChannelsReportPreviewCanvas" class="sales-preview-canvas shadow-sm mx-auto p-4">
                            <div class="d-flex flex-wrap justify-content-between align-items-start gap-3 border-bottom pb-3 mb-4">
                                <div>
                                    <h2 class="fw-bold text-primary mb-1">All Channels Sales Report</h2>
                                    <div>{{ $hub->name }} · Verified sales only</div>
                                    <small class="text-muted">{{ $dateFrom ?: 'All dates' }} to {{ $dateTo ?: now()->format('Y-m-d') }}</small>
                                </div>
                                <div class="text-end"><strong>Generated</strong><br><small>{{ now()->format('M d, Y h:i A') }}</small></div>
                            </div>
                            <section class="sales-summary-panel mb-4">
                                <div class="sales-summary-heading">
                                    <div><h5>Activity overview</h5><p>Combined sales performance across Online, Wholesale, and Walk-In for the selected period</p></div>
                                    <span class="badge rounded-pill text-bg-success">Verified sales</span>
                                </div>
                                <div class="sales-summary-grid">
                                    <div class="sales-summary-card is-primary"><span class="sales-summary-icon" aria-hidden="true"><i class="fa-solid fa-chart-line"></i></span><div class="sales-summary-copy"><div class="label">Gross sales</div><div class="value text-primary">₱{{ number_format($metrics['gross_sales'], 2) }}</div></div></div>
                                    <div class="sales-summary-card is-danger"><span class="sales-summary-icon" aria-hidden="true"><i class="fa-solid fa-tag"></i></span><div class="sales-summary-copy"><div class="label">Discounts</div><div class="value text-danger">₱{{ number_format($metrics['discounts'], 2) }}</div></div></div>
                                    <div class="sales-summary-card is-success"><span class="sales-summary-icon" aria-hidden="true"><i class="fa-solid fa-sack-dollar"></i></span><div class="sales-summary-copy"><div class="label">Total sales</div><div class="value text-success">₱{{ number_format($totalSales, 2) }}</div></div></div>
                                    <div class="sales-summary-card"><span class="sales-summary-icon" aria-hidden="true"><i class="fa-solid fa-receipt"></i></span><div class="sales-summary-copy"><div class="label">Transactions</div><div class="value">{{ number_format($totalTransactions) }}</div></div></div>
                                </div>
                            </section>
                            <section>
                                <div class="d-flex align-items-center gap-2 mb-3">
                                    <span class="sales-summary-icon text-primary" aria-hidden="true"><i class="fa-solid fa-layer-group"></i></span>
                                    <div><h5 class="fw-bold mb-0">Sales by channel</h5><small class="text-muted">Verified transaction totals</small></div>
                                </div>
                                <div class="all-channels-preview-breakdown">
                                    <table class="table align-middle mb-0">
                                        <thead><tr><th>Channel</th><th class="text-end">Transactions</th><th class="text-end">Total sales</th></tr></thead>
                                        <tbody>
                                            @forelse($salesByChannel as $row)
                                                <tr>
                                                    <td class="fw-semibold">{{ strtoupper(str_replace('_', ' ', $row->channel_type)) }}</td>
                                                    <td class="text-end">{{ number_format($row->transaction_count) }}</td>
                                                    <td class="text-end fw-bold text-success">₱{{ number_format($row->total, 2) }}</td>
                                                </tr>
                                            @empty
                                                <tr><td colspan="3" class="text-center text-muted py-4">No verified sales found.</td></tr>
                                            @endforelse
                                        </tbody>
                                    </table>
                                </div>
                            </section>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Close</button>
                        <button type="button" class="btn btn-success" id="saveAllChannelsPng"><i class="fa-solid fa-download me-1"></i> Save as PNG</button>
                    </div>
                </div>
            </div>
        </div>
    @endif

    @include('hubs.reports.'.$channel)

    @if(in_array($channel, ['shopee', 'lazada'], true))
        <div class="modal fade" id="marketplaceReportPreviewModal" tabindex="-1" aria-labelledby="marketplaceReportPreviewLabel" aria-hidden="true">
            <div class="modal-dialog modal-xl modal-dialog-scrollable"><div class="modal-content">
                <div class="modal-header"><div><h5 class="modal-title" id="marketplaceReportPreviewLabel">{{ $activeLabel }} Sales Report Preview</h5><small class="text-muted">This preview is exactly what will be saved as PNG.</small></div><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
                <div class="modal-body overflow-auto bg-secondary-subtle"><div id="marketplaceReportPreviewCanvas" class="sales-preview-canvas marketplace-preview-canvas shadow-sm mx-auto p-4">
                    <div class="d-flex justify-content-between border-bottom pb-3 mb-3"><div><h2 class="text-primary mb-1">{{ $activeLabel }} Sales Report</h2><div>{{ $hub->name }} · Verified sales only</div><small class="text-muted">{{ $dateFrom ?: 'All dates' }} to {{ $dateTo ?: now()->format('Y-m-d') }}</small></div><div class="text-end"><strong>Generated</strong><br><small>{{ now()->format('M d, Y h:i A') }}</small></div></div>
                    <section class="sales-summary-panel">
                        <div class="sales-summary-heading">
                            <div><h5>Activity overview</h5><p>Sales, returns, and customer activity for the selected period</p></div>
                            <span class="badge rounded-pill text-bg-success">Verified sales</span>
                        </div>
                        <div class="sales-summary-grid">
                            <div class="sales-summary-card is-primary"><span class="sales-summary-icon" aria-hidden="true"><i class="fa-solid fa-chart-line"></i></span><div class="sales-summary-copy"><div class="label">Total gross sales</div><div class="value text-primary">₱{{ number_format($metrics['gross_sales'], 2) }}</div></div></div>
                            <div class="sales-summary-card is-danger"><span class="sales-summary-icon" aria-hidden="true"><i class="fa-solid fa-tag"></i></span><div class="sales-summary-copy"><div class="label">Total discount</div><div class="value text-danger">₱{{ number_format($metrics['discounts'], 2) }}</div></div></div>
                            <div class="sales-summary-card"><span class="sales-summary-icon" aria-hidden="true"><i class="fa-solid fa-receipt"></i></span><div class="sales-summary-copy"><div class="label">Total transactions</div><div class="value">{{ number_format($totalTransactions) }}</div></div></div>
                            <div class="sales-summary-card is-danger"><span class="sales-summary-icon" aria-hidden="true"><i class="fa-solid fa-rotate-left"></i></span><div class="sales-summary-copy"><div class="label">Total return items</div><div class="value text-danger">{{ number_format($metrics['return_quantity']) }}</div></div></div>
                            <div class="sales-summary-card is-danger"><span class="sales-summary-icon" aria-hidden="true"><i class="fa-solid fa-money-bill-transfer"></i></span><div class="sales-summary-copy"><div class="label">Total refund cost</div><div class="value text-danger">₱{{ number_format($metrics['refund_total'], 2) }}</div></div></div>
                            <div class="sales-summary-card is-primary"><span class="sales-summary-icon" aria-hidden="true"><i class="fa-solid fa-users"></i></span><div class="sales-summary-copy"><div class="label">Total customers</div><div class="value">{{ number_format($customerMetrics['total']) }}</div></div></div>
                            <div class="sales-summary-card is-success"><span class="sales-summary-icon" aria-hidden="true"><i class="fa-solid fa-user-plus"></i></span><div class="sales-summary-copy"><div class="label">New customers</div><div class="value text-success">{{ number_format($customerMetrics['new']) }}</div></div></div>
                        </div>
                    </section>
                </div></div>
                <div class="modal-footer"><button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Close</button><button type="button" class="btn btn-success" id="saveMarketplacePng"><i class="fa-solid fa-download me-1"></i>Save as PNG</button></div>
            </div></div>
        </div>
    @endif

    @if($channel === 'wholesale')
        <div class="modal fade" id="wholesaleReportPreviewModal" tabindex="-1" aria-labelledby="wholesaleReportPreviewLabel" aria-hidden="true">
            <div class="modal-dialog modal-xl">
                <div class="modal-content">
                    <div class="modal-header">
                        <div><h5 class="modal-title" id="wholesaleReportPreviewLabel">Wholesale Report Preview</h5><small class="text-muted">This is the image that will be saved.</small></div>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body overflow-hidden bg-secondary-subtle">
                        <div id="wholesalePreviewCanvas" class="wholesale-preview-canvas shadow-sm mx-auto p-4"></div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Close</button>
                        <button type="button" class="btn btn-success" id="saveWholesalePng"><i class="fa-solid fa-download me-1"></i> Save as PNG</button>
                    </div>
                </div>
            </div>
        </div>
    @endif

    @if($channel === 'tiktok')
        <div class="modal fade" id="tiktokReportPreviewModal" tabindex="-1" aria-labelledby="tiktokReportPreviewLabel" aria-hidden="true">
            <div class="modal-dialog modal-xl modal-dialog-scrollable">
                <div class="modal-content">
                    <div class="modal-header">
                        <div><h5 class="modal-title" id="tiktokReportPreviewLabel">TikTok Sales Report Preview</h5><small class="text-muted">This preview is exactly what will be saved as PNG.</small></div>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body overflow-auto bg-secondary-subtle">
                        <div id="tiktokReportPreviewCanvas" class="tiktok-preview-canvas shadow-sm mx-auto p-4">
                            <div class="d-flex justify-content-between align-items-start border-bottom pb-3 mb-3">
                                <div><h2 class="fw-bold text-primary mb-1">TikTok Sales Report</h2><div>{{ $hub->name }} · Verified sales only</div><small class="text-muted">{{ $dateFrom ?: 'All dates' }} to {{ $dateTo ?: now()->format('Y-m-d') }}</small></div>
                                <div class="text-end"><strong>Generated</strong><br><small>{{ now()->format('M d, Y h:i A') }}</small></div>
                            </div>
                            <div class="row g-4">
                                <div class="col-lg-7">
                                    <h5 class="fw-bold mb-3">Financial summary</h5>
                                    <div class="tiktok-preview-summary">
                                        <div class="tiktok-preview-summary-row"><span>Gross Sales: Total Price after Discount</span><strong>₱{{ number_format($metrics['total_sales'], 2) }}</strong></div>
                                        <div class="tiktok-preview-summary-row"><span>Total Shipping Fee 5%</span><strong>₱{{ number_format($metrics['shipping_service_fees'], 2) }}</strong></div>
                                        <div class="tiktok-preview-summary-row highlight"><span>Total Sales After Transactions Fee</span><strong class="text-success">₱{{ number_format($metrics['net_platform_payout'], 2) }}</strong></div>
                                        <div class="tiktok-preview-summary-row"><span>Refund Cost</span><strong class="text-danger">₱{{ number_format($metrics['refund_total'], 2) }}</strong></div>
                                    </div>
                                </div>
                                <div class="col-lg-5">
                                    <section class="tiktok-overview-panel h-100" aria-label="TikTok operations summary">
                                        <div class="tiktok-overview-heading">
                                            <div><h5>Operations summary</h5><p>Activity within this report</p></div>
                                            <span class="tiktok-overview-status">Verified</span>
                                        </div>
                                        <div class="tiktok-overview-grid">
                                            <div class="tiktok-overview-card">
                                                <span class="tiktok-overview-icon" aria-hidden="true"><i class="fa-solid fa-bag-shopping"></i></span>
                                                <div class="tiktok-overview-copy"><div class="tiktok-overview-label">Total orders</div><div class="tiktok-overview-value">{{ $totalTransactions }}</div></div>
                                            </div>
                                            <div class="tiktok-overview-card is-danger">
                                                <span class="tiktok-overview-icon" aria-hidden="true"><i class="fa-solid fa-rotate-left"></i></span>
                                                <div class="tiktok-overview-copy"><div class="tiktok-overview-label">Refunded items</div><div class="tiktok-overview-value">{{ $metrics['refund_count'] }}</div></div>
                                            </div>
                                            <div class="tiktok-overview-card is-warning">
                                                <span class="tiktok-overview-icon" aria-hidden="true"><i class="fa-solid fa-arrows-rotate"></i></span>
                                                <div class="tiktok-overview-copy"><div class="tiktok-overview-label">Approved item replacements</div><div class="tiktok-overview-value">{{ $metrics['replacement_count'] }}</div></div>
                                            </div>
                                            <div class="tiktok-overview-card">
                                                <span class="tiktok-overview-icon" aria-hidden="true"><i class="fa-solid fa-users"></i></span>
                                                <div class="tiktok-overview-copy"><div class="tiktok-overview-label">Total customers</div><div class="tiktok-overview-value">{{ number_format($customerMetrics['total']) }}</div></div>
                                            </div>
                                            <div class="tiktok-overview-card is-success">
                                                <span class="tiktok-overview-icon" aria-hidden="true"><i class="fa-solid fa-user-plus"></i></span>
                                                <div class="tiktok-overview-copy"><div class="tiktok-overview-label">New customers</div><div class="tiktok-overview-value">{{ number_format($customerMetrics['new']) }}</div></div>
                                            </div>
                                        </div>
                                    </section>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Close</button>
                        <button type="button" class="btn btn-success" id="saveTiktokPng"><i class="fa-solid fa-download me-1"></i> Save as PNG</button>
                    </div>
                </div>
            </div>
        </div>
    @endif

    @if(in_array($channel, ['shopee', 'lazada'], true))
        @push('scripts')
        <script>
            (() => {
                const preview = document.getElementById('marketplaceReportPreviewCanvas');
                const saveButton = document.getElementById('saveMarketplacePng');
                if (!preview || !saveButton) return;
                saveButton.addEventListener('click', async () => {
                    if (typeof window.html2canvas !== 'function') { window.AppAlert?.show('The PNG generator is still loading. Please try again.', 'error'); return; }
                    saveButton.disabled = true;
                    const original = saveButton.innerHTML;
                    saveButton.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span>Creating PNG...';
                    try {
                        const canvas = await window.html2canvas(preview, { backgroundColor: '#ffffff', scale: 2, useCORS: true, logging: false });
                        const link = document.createElement('a');
                        link.download = @json($channel.'-sales-report-'.$hub->code.'-'.($dateFrom ?: 'all').'-'.($dateTo ?: now()->format('Y-m-d')).'.png');
                        link.href = canvas.toDataURL('image/png');
                        link.click();
                    } catch (error) { window.AppAlert?.show('Could not create the PNG. Please try again.', 'error'); }
                    finally { saveButton.disabled = false; saveButton.innerHTML = original; }
                });
            })();
        </script>
        @endpush
    @endif

    @if($channel === 'online')
        <div class="modal fade" id="onlineReportPreviewModal" tabindex="-1" aria-labelledby="onlineReportPreviewLabel" aria-hidden="true">
            <div class="modal-dialog modal-xl">
                <div class="modal-content">
                    <div class="modal-header">
                        <div><h5 class="modal-title" id="onlineReportPreviewLabel">Online Sales Report Preview</h5><small class="text-muted">This preview is exactly what will be saved as PNG.</small></div>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body overflow-hidden bg-secondary-subtle">
                        <div id="onlineReportPreviewCanvas" class="online-preview-canvas shadow-sm mx-auto p-4"></div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Close</button>
                        <button type="button" class="btn btn-success" id="saveOnlinePng"><i class="fa-solid fa-download me-1"></i> Save as PNG</button>
                    </div>
                </div>
            </div>
        </div>
    @endif

    @if($channel === 'walk_in')
        <div class="modal fade" id="walkInReportPreviewModal" tabindex="-1" aria-labelledby="walkInReportPreviewLabel" aria-hidden="true">
            <div class="modal-dialog modal-xl modal-dialog-scrollable"><div class="modal-content">
                <div class="modal-header bg-warning-subtle"><div><h5 class="modal-title" id="walkInReportPreviewLabel">Walk-In Sales Report Preview</h5><small class="text-muted">This preview is exactly what will be saved as PNG.</small></div><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
                <div class="modal-body overflow-auto bg-secondary-subtle"><div id="walkInReportPreviewCanvas" class="walk-in-preview-canvas shadow-sm mx-auto p-3"></div></div>
                <div class="modal-footer"><button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Close</button><button type="button" class="btn btn-warning" id="saveWalkInPng"><i class="fa-solid fa-download me-1"></i> Save as PNG</button></div>
            </div></div>
        </div>
    @endif

    @if($channel !== 'all')
        <div class="mt-3">{{ $transactions->appends($filterParams)->links() }}</div>
    @endif
</div>
@endsection

@if($channel === 'all')
    @push('scripts')
    <script>
        (() => {
            const preview = document.getElementById('allChannelsReportPreviewCanvas');
            const saveButton = document.getElementById('saveAllChannelsPng');
            if (!preview || !saveButton) return;

            saveButton.addEventListener('click', async () => {
                if (typeof window.html2canvas !== 'function') {
                    window.AppAlert?.show('The PNG generator is still loading. Please try again.', 'error');
                    return;
                }
                saveButton.disabled = true;
                const original = saveButton.innerHTML;
                saveButton.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span> Creating PNG...';
                try {
                    const canvas = await window.html2canvas(preview, { backgroundColor: '#ffffff', scale: 2, useCORS: true, logging: false });
                    const link = document.createElement('a');
                    link.download = @json('all-channels-sales-report-'.$hub->code.'-'.($dateFrom ?: 'all').'-'.($dateTo ?: now()->format('Y-m-d')).'.png');
                    link.href = canvas.toDataURL('image/png');
                    link.click();
                } catch (error) {
                    window.AppAlert?.show('Could not create the PNG. Please close the preview and try again.', 'error');
                } finally {
                    saveButton.disabled = false;
                    saveButton.innerHTML = original;
                }
            });
        })();
    </script>
    @endpush
@endif

@if($channel === 'wholesale')
    @push('scripts')
    <script>
        (() => {
            const modal = document.getElementById('wholesaleReportPreviewModal');
            const preview = document.getElementById('wholesalePreviewCanvas');
            const source = document.getElementById('wholesaleReportImageSource');
            const saveButton = document.getElementById('saveWholesalePng');
            if (!modal || !preview || !source || !saveButton) return;

            const refreshPreview = () => {
                preview.replaceChildren(source.cloneNode(true));
                preview.querySelector('#wholesaleReportImageSource')?.removeAttribute('id');
            };
            modal.addEventListener('show.bs.modal', refreshPreview);

            saveButton.addEventListener('click', async () => {
                if (typeof window.html2canvas !== 'function') {
                    window.AppAlert?.show('The PNG generator is still loading. Please try again.', 'error');
                    return;
                }
                saveButton.disabled = true;
                const original = saveButton.innerHTML;
                saveButton.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span> Creating PNG...';
                try {
                    const canvas = await window.html2canvas(preview, {
                        backgroundColor: '#ffffff',
                        scale: 2,
                        useCORS: true,
                        logging: false,
                    });
                    const link = document.createElement('a');
                    link.download = @json('wholesale-report-'.$hub->code.'-'.($dateFrom ?: 'all').'-'.($dateTo ?: now()->format('Y-m-d')).'.png');
                    link.href = canvas.toDataURL('image/png');
                    link.click();
                } catch (error) {
                    window.AppAlert?.show('Could not create the PNG. Please close the preview and try again.', 'error');
                } finally {
                    saveButton.disabled = false;
                    saveButton.innerHTML = original;
                }
            });
        })();
    </script>
    @endpush
@endif

@if($channel === 'online')
    @push('scripts')
    <script>
        (() => {
            const modal = document.getElementById('onlineReportPreviewModal');
            const preview = document.getElementById('onlineReportPreviewCanvas');
            const source = document.getElementById('onlineReportImageSource');
            const saveButton = document.getElementById('saveOnlinePng');
            if (!modal || !preview || !source || !saveButton) return;

            const refreshPreview = () => {
                const report = source.cloneNode(true);
                report.removeAttribute('id');
                report.querySelectorAll('[data-online-image-only]').forEach(section => section.classList.remove('d-none'));
                report.querySelectorAll('[data-online-preview-only]').forEach(section => section.classList.remove('d-none'));
                report.querySelectorAll('[data-online-screen-only]').forEach(element => element.remove());
                report.querySelectorAll('[data-online-preview-remove]').forEach(element => element.remove());
                report.querySelector('[data-online-total-label]')?.setAttribute('colspan', '3');
                report.querySelectorAll('[data-online-delivery-form]').forEach(form => {
                    const status = form.querySelector('select')?.selectedOptions[0]?.textContent || 'Unspecified';
                    const badge = document.createElement('span');
                    badge.className = 'badge text-bg-light border';
                    badge.textContent = status;
                    form.replaceWith(badge);
                });
                preview.replaceChildren(report);
            };
            modal.addEventListener('show.bs.modal', refreshPreview);

            saveButton.addEventListener('click', async () => {
                if (typeof window.html2canvas !== 'function') {
                    window.AppAlert?.show('The PNG generator is still loading. Please try again.', 'error');
                    return;
                }
                saveButton.disabled = true;
                const original = saveButton.innerHTML;
                saveButton.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span> Creating PNG...';
                try {
                    const canvas = await window.html2canvas(preview, { backgroundColor: '#ffffff', scale: 2, useCORS: true, logging: false });
                    const link = document.createElement('a');
                    link.download = @json('online-sales-report-'.$hub->code.'-'.($dateFrom ?: 'all').'-'.($dateTo ?: now()->format('Y-m-d')).'.png');
                    link.href = canvas.toDataURL('image/png');
                    link.click();
                } catch (error) {
                    window.AppAlert?.show('Could not create the PNG. Please close the preview and try again.', 'error');
                } finally {
                    saveButton.disabled = false;
                    saveButton.innerHTML = original;
                }
            });
        })();
    </script>
    @endpush
@endif

@if($channel === 'walk_in')
    @push('scripts')
    <script>
        (() => {
            const modal = document.getElementById('walkInReportPreviewModal');
            const preview = document.getElementById('walkInReportPreviewCanvas');
            const source = document.getElementById('walkInReportImageSource');
            const saveButton = document.getElementById('saveWalkInPng');
            if (!modal || !preview || !source || !saveButton) return;
            modal.addEventListener('show.bs.modal', () => {
                const report = source.cloneNode(true); report.removeAttribute('id');
                report.querySelector('[data-walk-in-preview-only]')?.classList.remove('d-none');
                report.querySelector('[data-walk-in-detail-table]')?.remove();
                report.querySelectorAll('[data-walk-in-screen-only]').forEach(element => element.remove());
                report.querySelectorAll('[data-walk-in-preview-remove]').forEach(element => element.remove());
                report.querySelector('[data-walk-in-total-label]')?.setAttribute('colspan', '2');
                preview.replaceChildren(report);
            });
            saveButton.addEventListener('click', async () => {
                if (typeof window.html2canvas !== 'function') { window.AppAlert?.show('The PNG generator is still loading. Please try again.', 'error'); return; }
                saveButton.disabled = true; const original = saveButton.innerHTML; saveButton.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span> Creating PNG...';
                try {
                    const canvas = await window.html2canvas(preview, { backgroundColor: '#ffffff', scale: 2, useCORS: true, logging: false });
                    const link = document.createElement('a'); link.download = @json('walk-in-sales-report-'.$hub->code.'-'.($dateFrom ?: 'all').'-'.($dateTo ?: now()->format('Y-m-d')).'.png'); link.href = canvas.toDataURL('image/png'); link.click();
                } catch (error) { window.AppAlert?.show('Could not create the PNG. Please close the preview and try again.', 'error'); }
                finally { saveButton.disabled = false; saveButton.innerHTML = original; }
            });
        })();
    </script>
    @endpush
@endif

    @if($channel === 'tiktok')
        @push('scripts')
        <script>
            (() => {
                const preview = document.getElementById('tiktokReportPreviewCanvas');
                const saveButton = document.getElementById('saveTiktokPng');
                if (!preview || !saveButton) return;
                saveButton.addEventListener('click', async () => {
                    if (typeof window.html2canvas !== 'function') {
                        window.AppAlert?.show('The PNG generator is still loading. Please try again.', 'error');
                        return;
                    }
                    saveButton.disabled = true;
                    const original = saveButton.innerHTML;
                    saveButton.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span> Creating PNG...';
                    try {
                        const canvas = await window.html2canvas(preview, { backgroundColor: '#ffffff', scale: 2, useCORS: true, logging: false });
                        const link = document.createElement('a');
                        link.download = @json('tiktok-sales-report-'.$hub->code.'-'.($dateFrom ?: 'all').'-'.($dateTo ?: now()->format('Y-m-d')).'.png');
                        link.href = canvas.toDataURL('image/png');
                        link.click();
                    } catch (error) {
                        window.AppAlert?.show('Could not create the PNG. Please close the preview and try again.', 'error');
                    } finally {
                        saveButton.disabled = false;
                        saveButton.innerHTML = original;
                    }
                });
            })();
        </script>
        @endpush
    @endif
