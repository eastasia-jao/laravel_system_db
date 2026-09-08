@extends('layouts.app')
@section('content')
<style>
    .sales-dashboard { max-width: 1600px; margin: auto; color: #243247; }
    .sales-dashboard .panel { background: #fff; border: 1px solid #e5eaf1; border-radius: 14px; box-shadow: 0 2px 4px #182b4910; }
    .sales-dashboard .metric { padding: 18px; border-left: 3px solid var(--accent); }
    .sales-dashboard .metric-label { font-size: .72rem; color: #64748b; text-transform: uppercase; font-weight: 600; }
    .sales-dashboard .metric-value { font-size: 1.3rem; font-weight: 700; margin-top: 8px; font-variant-numeric: tabular-nums; }
    .sales-dashboard h2 { font-size: 1.4rem; font-weight: 700; }
    .sales-dashboard h3 { font-size: .95rem; font-weight: 650; }
    .sales-dashboard .table { font-size: .82rem; }
    .sales-dashboard .table th { color: #64748b; background: #f8fafc; font-size: .7rem; text-transform: uppercase; white-space: nowrap; }
    .sales-dashboard .table td, .sales-dashboard .table th { padding: .85rem .7rem; vertical-align: middle; }
    .sales-dashboard .scroll-panel { max-height: 370px; overflow: auto; }
    .sales-dashboard .scroll-panel th { position: sticky; top: 0; z-index: 1; }
    .sales-dashboard .empty-state { padding: 3rem 1rem; text-align: center; color: #64748b; font-size: .85rem; }
    .sales-dashboard .rank { background: #f1f5f9; color: #64748b; border-radius: 8px; min-width: 30px; padding: 5px; text-align: center; }
</style>
<div class="sales-dashboard">
    @php($isInventoryStaffDashboard = auth()->user()?->role === 'inventory_staff')
    <div class="d-flex flex-wrap justify-content-between align-items-end gap-3 mb-4">
        <div><h2 class="mb-1">Sales Management Hub</h2><p class="text-muted small mb-0">Sales and inventory overview · {{ $scopeName }}</p></div>
        <form method="GET" action="{{ route('dashboard') }}" class="d-flex flex-wrap gap-2 align-items-end" id="dashboardFilters">
            <div><label for="dashboardHub" class="form-label small text-muted mb-1">Store</label><select name="hub_id" id="dashboardHub" class="form-select form-select-sm" onchange="this.form.submit()">@if(auth()->user()?->role === 'admin')<option value="">All accessible stores</option>@endif @foreach($dashboardHubs as $store)<option value="{{ $store->id }}" @selected((int) $hubId === $store->id)>{{ $store->name }}</option>@endforeach</select></div>
            <div><label for="dashboardDate" class="form-label small text-muted mb-1">As of date</label><input type="date" id="dashboardDate" name="date" value="{{ $asOf->toDateString() }}" class="form-control form-control-sm" required></div>
            <button class="btn btn-primary btn-sm">Apply filters</button>
        </form>
    </div>
    @if($errors->any())<div class="alert alert-danger">{{ $errors->first() }}</div>@endif
    @unless($isInventoryStaffDashboard)
    <div class="row row-cols-1 row-cols-sm-2 row-cols-xl-5 g-3 mb-3">
        @foreach($salesTotals as $period => $amount)
            <div class="col"><div class="panel metric h-100" style="--accent: {{ ['Daily'=>'#6366f1','Weekly'=>'#64748b','Monthly'=>'#10b981','Quarterly'=>'#f59e0b','Yearly'=>'#06b6d4'][$period] }}"><div class="metric-label">{{ $period }} Sales</div><div class="metric-value">₱{{ number_format($amount, 2) }}</div></div></div>
        @endforeach
    </div>
    <p class="small text-muted mb-4">Confirmed sales, using total order amounts. Each period runs through {{ $asOf->format('M d, Y') }}; weeks start Monday. Pending and cancelled orders are excluded.</p>
    <section class="mb-4">
        <div class="d-flex justify-content-between align-items-center mb-2">
            <div><h3 class="mb-1">Sales Channels</h3><p class="small text-muted mb-0">Head Office channels and store walk-in sales for the selected scope.</p></div>
        </div>
        <div class="row row-cols-1 row-cols-sm-2 row-cols-lg-3 row-cols-xl-{{ $channelSummaries->count() >= 6 ? '6' : ($channelSummaries->count() > 2 ? '5' : '1') }} g-3">
            @foreach($channelSummaries as $summary)
                <div class="col">
                    <div class="panel h-100 p-3">
                        <div class="d-flex justify-content-between align-items-start mb-3">
                            <span class="fw-semibold">{{ $summary['label'] }}</span>
                            <span class="rounded-circle bg-{{ $summary['color'] }} text-white p-2"><i class="fa-solid {{ $summary['icon'] }}"></i></span>
                        </div>
                        <div class="fs-4 fw-bold">₱{{ number_format($summary['total'], 2) }}</div>
                        <div class="small text-muted">{{ number_format($summary['transactions']) }} completed transaction{{ $summary['transactions'] === 1 ? '' : 's' }}</div>
                    </div>
                </div>
            @endforeach
        </div>
    </section>
    @endunless
    <div class="row g-4 mb-4">
        @unless($isInventoryStaffDashboard)
        <div class="col-xl-6"><section class="panel p-3 p-lg-4 h-100">
            <h3 class="mb-1"><i class="fa-regular fa-credit-card text-secondary me-2"></i>Branch Transaction Matrix</h3>
            <p class="small text-muted mb-3">Month to date · Sales by recorded payment method</p>
            <div class="scroll-panel"><table class="table mb-0"><thead><tr><th>Store Branch</th>@foreach($paymentColumns as $column)<th class="text-end">{{ $column }}</th>@endforeach</tr></thead><tbody>
                @forelse($matrix as $row)<tr><td class="fw-semibold">{{ $row['name'] }}</td>@foreach($row['amounts'] as $amount)<td class="text-end text-nowrap">₱{{ number_format($amount, 2) }}</td>@endforeach</tr>
                @empty<tr><td colspan="7" class="empty-state">No stores available.</td></tr>@endforelse
            </tbody></table></div>
        </section></div>
        @endunless
        <div class="{{ $isInventoryStaffDashboard ? 'col-xl-6' : 'col-xl-3' }}"><section class="panel p-3 p-lg-4 h-100">
            <h3 class="mb-1"><i class="fa-solid fa-trophy text-warning me-2"></i>Top 10 Products</h3><p class="small text-muted mb-3">Month to date · Ranked by units sold</p>
            <div class="scroll-panel">@forelse($topProducts as $product)<div class="d-flex align-items-center gap-2 py-2 border-bottom small"><span class="rank">{{ $loop->iteration }}</span><span class="flex-grow-1">{{ $product['name'] }}</span><span class="text-muted text-nowrap">{{ number_format($product['units']) }} sold</span></div>@empty<div class="empty-state">No completed sales for this period.</div>@endforelse</div>
        </section></div>
        <div class="{{ $isInventoryStaffDashboard ? 'col-xl-6' : 'col-xl-3' }}"><section class="panel p-3 p-lg-4 h-100">
            <h3 class="mb-1"><i class="fa-solid fa-hourglass-half text-secondary me-2"></i>Slow-Moving Items</h3><p class="small text-muted mb-3">Month to date · Ranked by lowest units sold</p>
            <div class="scroll-panel">@forelse($slowProducts as $product)<div class="d-flex align-items-center gap-2 py-2 border-bottom small"><span class="rank">{{ $loop->iteration }}</span><span class="flex-grow-1">{{ $product['name'] }}</span><span class="text-muted text-nowrap">{{ number_format($product['units']) }} sold</span></div>@empty<div class="empty-state">No active products for this period.</div>@endforelse</div>
        </section></div>
    </div>
    <section class="panel p-3 p-lg-4">
        <div class="d-flex justify-content-between gap-2">                <div><h3 class="mb-1"><i class="fa-solid fa-triangle-exclamation text-danger me-2"></i>Live Inventory Alerts</h3><p class="small text-muted mb-3">Physical stock · Red: 0–10 pcs · Orange: 11–30 pcs · {{ $alertCount }} alerts</p></div></div>
        @if($alerts->isEmpty())<div class="empty-state">No active products in the selected stores.</div>@else
        <div class="scroll-panel"><table class="table mb-0"><thead><tr><th>Product</th><th>Store</th><th class="text-end">Stock</th><th>Status</th></tr></thead><tbody>@foreach($alerts as $product)@php($stockStatus = $product->stock <= 10 ? ['class' => 'bg-danger', 'label' => 'Critical stock'] : ['class' => 'bg-warning text-dark', 'label' => 'Low stock'])<tr><td class="fw-semibold">{{ $product->name }}<span class="d-block small text-muted">{{ $product->item_id }}</span></td><td>{{ $product->storeHub?->name }}</td><td class="text-end">{{ $product->stock }} pcs</td><td><span class="badge {{ $stockStatus['class'] }}">{{ $stockStatus['label'] }}</span></td></tr>@endforeach</tbody></table></div>
        @if($alertCount > 30)<p class="small text-muted mt-2 mb-0">Showing the 30 lowest-stock products. View Store Hub inventory for the full list.</p>@endif
        @endif
    </section>
    @if(auth()->user()?->role === 'admin')
    <section class="panel p-3 p-lg-4 mt-4">
        <div class="d-flex flex-wrap justify-content-between align-items-center gap-3">
            <div>
                <h3 class="mb-1"><i class="fa-solid fa-clipboard-check text-warning me-2"></i>Pending Inventory Verification</h3>
                <p class="small text-muted mb-0">Sales records waiting for inventory staff confirmation.</p>
            </div>
            <a href="{{ route('sales.pending') }}" class="btn btn-outline-warning btn-sm">
                Review {{ number_format($pendingVerificationCount) }} pending {{ \Illuminate\Support\Str::plural('record', $pendingVerificationCount) }}
            </a>
        </div>
    </section>
    @endif
</div>
@endsection
