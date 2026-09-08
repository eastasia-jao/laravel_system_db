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
    $activeLabel = $channelLabels[$channel] ?? 'All Channels';
    $filterParams = array_filter(['date_from' => $dateFrom, 'date_to' => $dateTo]);
    $visibleChannels = ! $hub->is_head_office
        ? ['walk_in' => $channelLabels['walk_in']]
        : (in_array(auth()->user()?->role, ['sales_associate', 'sales_marketing_staff'], true)
        ? collect($channelLabels)->filter(fn ($label, $value) => $value !== 'all' && auth()->user()->hasSalesChannel($value))->all()
        : $channelLabels);
@endphp

<div class="container-fluid p-4">
    @if($errors->any())
        <div class="alert alert-danger shadow-sm">{{ $errors->first() }}</div>
    @endif
    @if(session('success'))
        <div class="alert alert-success shadow-sm">{{ session('success') }}</div>
    @endif

    <div class="d-flex flex-wrap justify-content-between align-items-center gap-3 mb-4">
        <div>
            <h2 class="fw-bold text-primary mb-1">{{ $activeLabel }} Sales Report</h2>
            <p class="text-muted mb-0">{{ $hub->name }} · Verified sales only</p>
        </div>
        <a href="{{ route('hub.dashboard', $hub->id) }}" class="btn btn-outline-secondary">
            <i class="fa-solid fa-arrow-left me-1"></i> Back to Dashboard
        </a>
    </div>

    <div class="card shadow-sm border-0 mb-4">
        <div class="card-body">
            <div class="d-flex flex-wrap gap-2 mb-3">
                @foreach($visibleChannels as $value => $label)
                    <a href="{{ route('hub.report', ['hub' => $hub->id, 'channel' => $value] + $filterParams) }}"
                       class="btn btn-sm {{ $channel === $value ? 'btn-primary' : 'btn-outline-primary' }}">
                        {{ $label }}
                    </a>
                @endforeach
            </div>

            <form method="GET" action="{{ route('hub.report', $hub->id) }}" class="row g-2 align-items-end">
                <input type="hidden" name="channel" value="{{ $channel }}">
                <div class="col-sm-4 col-md-3">
                    <label class="form-label small fw-semibold">Date From</label>
                    <input type="date" name="date_from" value="{{ $dateFrom }}" class="form-control form-control-sm">
                </div>
                <div class="col-sm-4 col-md-3">
                    <label class="form-label small fw-semibold">Date To</label>
                    <input type="date" name="date_to" value="{{ $dateTo }}" class="form-control form-control-sm">
                </div>
                <div class="col-sm-4 col-md-3 d-flex gap-2">
                    <button class="btn btn-sm btn-primary" type="submit"><i class="fa-solid fa-filter me-1"></i> Apply</button>
                    <a class="btn btn-sm btn-outline-secondary" href="{{ route('hub.report', ['hub' => $hub->id, 'channel' => $channel]) }}">Reset</a>
                </div>
            </form>
        </div>
    </div>

    <div class="row g-3 mb-4">
        @if($channel === 'tiktok')
            @include('hubs.reports.metric', ['label' => 'Total Sales', 'value' => $metrics['total_sales'], 'color' => 'success'])
            @include('hubs.reports.metric', ['label' => 'Net Payout After Refunds', 'value' => $metrics['net_platform_payout'], 'color' => 'success'])
            @include('hubs.reports.metric', ['label' => 'Customer Refunds', 'value' => $metrics['customer_refund_amount'], 'color' => 'danger'])
            @include('hubs.reports.metric', ['label' => 'Transactions', 'value' => $totalTransactions, 'money' => false, 'color' => 'dark'])
        @elseif($channel === 'online')
            @include('hubs.reports.metric', ['label' => 'Sales Amount', 'value' => $metrics['total_sales'], 'color' => 'success'])
            @include('hubs.reports.metric', ['label' => 'Shipping Fees', 'value' => $metrics['shipping_fees'], 'color' => 'info'])
            @include('hubs.reports.metric', ['label' => 'Proof Amount', 'value' => $metrics['proof_amount'], 'color' => 'primary'])
            @include('hubs.reports.metric', ['label' => 'Reconciliation Difference', 'value' => $metrics['difference'], 'color' => abs($metrics['difference']) < 0.01 ? 'success' : 'danger'])
        @else
            @include('hubs.reports.metric', ['label' => 'Gross Sales', 'value' => $metrics['gross_sales'], 'color' => 'primary'])
            @include('hubs.reports.metric', ['label' => 'Discounts', 'value' => $metrics['discounts'], 'color' => 'danger'])
            @include('hubs.reports.metric', ['label' => 'Total Sales', 'value' => $totalSales, 'color' => 'success'])
            @include('hubs.reports.metric', ['label' => 'Transactions', 'value' => $totalTransactions, 'money' => false, 'color' => 'dark'])
        @endif
    </div>

    @include('hubs.reports.'.$channel)

    @if($channel !== 'all')
        <div class="mt-3">{{ $transactions->links() }}</div>
    @endif
</div>
@endsection
