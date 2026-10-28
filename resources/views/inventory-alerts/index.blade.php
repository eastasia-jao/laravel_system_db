@extends('layouts.app')

@section('content')
@php
    $baseParams = array_filter([
        'hub_id' => $hubId,
        'channel' => $selectedChannel,
    ], fn ($value) => $value !== null && $value !== '');
    $severityLabels = [
        'alerts' => 'Needs attention',
        'out_of_stock' => 'Out of stock',
        'critical' => 'Critical stock',
        'low' => 'Low stock',
        'healthy' => 'Healthy',
        'all' => 'All products',
    ];
@endphp
<style>
    .inventory-alert-page { width: 100%; max-width: none; }
    .inventory-alert-summary { display: grid; grid-template-columns: repeat(4, minmax(0, 1fr)); gap: .85rem; }
    .inventory-alert-summary-card { padding: 1rem; border: 1px solid var(--ac-border); border-left: 4px solid var(--accent); border-radius: 12px; color: var(--ac-text); background: var(--ac-surface); text-decoration: none; }
    .inventory-alert-summary-card:hover { border-color: #93c5fd; box-shadow: var(--ac-shadow); }
    .inventory-alert-summary-label { color: var(--ac-muted); font-size: .7rem; font-weight: 750; letter-spacing: .04em; text-transform: uppercase; }
    .inventory-alert-summary-value { margin-top: .25rem; font-size: 1.45rem; font-weight: 800; font-variant-numeric: tabular-nums; }
    .inventory-alert-table-scroll { max-height: 60vh; overflow: auto; }
    .inventory-alert-table th { position: sticky; top: 0; z-index: 1; white-space: nowrap; background: #f1f5f9; }
    @media (max-width: 767.98px) { .inventory-alert-summary { grid-template-columns: repeat(2, minmax(0, 1fr)); } }
</style>

<div class="inventory-alert-page">
    <x-page-header eyebrow="Inventory monitoring" title="Inventory Alerts" :description="$scopeName.' · '.$stockLabel" icon="fa-heart-pulse" class="mb-4">
        <x-slot:actions><a class="btn btn-outline-secondary" href="{{ route('dashboard', $baseParams) }}"><i class="fa-solid fa-arrow-left me-1"></i>Dashboard</a></x-slot:actions>
    </x-page-header>

    <div class="inventory-alert-summary mb-4">
        <a class="inventory-alert-summary-card" style="--accent:#991b1b" href="{{ route('dashboard.inventory-alerts', array_merge($baseParams, ['severity' => 'out_of_stock'])) }}"><div class="inventory-alert-summary-label">Out of stock</div><div class="inventory-alert-summary-value">{{ number_format($inventoryHealth['out_of_stock']) }}</div></a>
        <a class="inventory-alert-summary-card" style="--accent:#dc2626" href="{{ route('dashboard.inventory-alerts', array_merge($baseParams, ['severity' => 'critical'])) }}"><div class="inventory-alert-summary-label">Critical stock</div><div class="inventory-alert-summary-value">{{ number_format($inventoryHealth['critical']) }}</div></a>
        <a class="inventory-alert-summary-card" style="--accent:#f59e0b" href="{{ route('dashboard.inventory-alerts', array_merge($baseParams, ['severity' => 'low'])) }}"><div class="inventory-alert-summary-label">Low stock</div><div class="inventory-alert-summary-value">{{ number_format($inventoryHealth['low']) }}</div></a>
        <a class="inventory-alert-summary-card" style="--accent:#16a34a" href="{{ route('dashboard.inventory-alerts', array_merge($baseParams, ['severity' => 'healthy'])) }}"><div class="inventory-alert-summary-label">Healthy</div><div class="inventory-alert-summary-value">{{ number_format($inventoryHealth['healthy']) }}</div></a>
    </div>

    <section class="panel p-3 p-lg-4">
        <form method="GET" action="{{ route('dashboard.inventory-alerts') }}" class="row g-3 align-items-end mb-4">
            @if($dashboardHubs->count() > 1)
            <div class="col-lg-3 col-md-6">
                <label class="form-label small fw-semibold" for="alertHub">Store</label>
                <select class="form-select" id="alertHub" name="hub_id">
                    @if(auth()->user()?->role === 'admin')<option value="">All accessible stores</option>@endif
                    @foreach($dashboardHubs as $hub)<option value="{{ $hub->id }}" @selected((int) $hubId === $hub->id)>{{ $hub->name }}</option>@endforeach
                </select>
            </div>
            @elseif($hubId)
                <input type="hidden" name="hub_id" value="{{ $hubId }}">
            @endif
            @if($assignedChannels->count() > 1)
            <div class="col-lg-2 col-md-6">
                <label class="form-label small fw-semibold" for="alertChannel">Channel</label>
                <select class="form-select" id="alertChannel" name="channel">@foreach($assignedChannels as $channel)<option value="{{ $channel }}" @selected($selectedChannel === $channel)>{{ str($channel)->headline() }}</option>@endforeach</select>
            </div>
            @endif
            <div class="col-lg-2 col-md-6">
                <label class="form-label small fw-semibold" for="alertSeverity">Stock status</label>
                <select class="form-select" id="alertSeverity" name="severity">@foreach($severityLabels as $value => $label)<option value="{{ $value }}" @selected($severity === $value)>{{ $label }}</option>@endforeach</select>
            </div>
            <div class="col-lg col-md-6">
                <label class="form-label small fw-semibold" for="alertSearch">Find product</label>
                <input class="form-control" id="alertSearch" type="search" name="search" value="{{ request('search') }}" placeholder="Name, Item ID, barcode or brand">
            </div>
            <div class="col-lg-auto d-flex gap-2">
                <button class="btn btn-primary"><i class="fa-solid fa-filter me-1"></i>Apply</button>
                <a class="btn btn-outline-secondary" href="{{ route('dashboard.inventory-alerts', $baseParams) }}">Clear</a>
            </div>
        </form>

        <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
            <div><h3 class="h5 mb-1">{{ $severityLabels[$severity] }}</h3><p class="small text-muted mb-0">{{ number_format($products->total()) }} {{ \Illuminate\Support\Str::plural('product', $products->total()) }}</p></div>
        </div>
        <div class="table-responsive inventory-alert-table-scroll">
            <table class="table inventory-alert-table align-middle mb-0">
                <thead><tr><th>Product</th><th>Item ID</th><th>Store</th><th class="text-end">{{ $stockLabel }}</th><th>Status</th></tr></thead>
                <tbody>
                    @forelse($products as $product)
                        @php
                            $status = (int) $product->alert_stock === 0
                                ? ['class' => 'bg-dark', 'label' => 'Out of stock']
                                : ((int) $product->alert_stock <= 10
                                    ? ['class' => 'bg-danger', 'label' => 'Critical']
                                    : ((int) $product->alert_stock <= 30
                                        ? ['class' => 'bg-warning text-dark', 'label' => 'Low stock']
                                        : ['class' => 'bg-success', 'label' => 'Healthy']));
                        @endphp
                        <tr><td class="fw-semibold">{{ $product->name }}</td><td>{{ $product->item_id }}</td><td>{{ $product->storeHub?->name }}</td><td class="text-end fw-semibold">{{ number_format($product->alert_stock) }} pcs</td><td><span class="badge {{ $status['class'] }}">{{ $status['label'] }}</span></td></tr>
                    @empty
                        <tr><td colspan="5"><div class="empty-state">No products match these filters.</div></td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if($products->hasPages())<div class="mt-3">{{ $products->onEachSide(1)->links() }}</div>@endif
    </section>
</div>
@endsection
