@extends('layouts.app')
@section('content')
<style>
    .sales-dashboard { width: 100%; max-width: none; margin: 0; color: #243247; }
    .sales-dashboard .panel { background: #fff; border: 1px solid #dfe7f1; border-radius: 10px; box-shadow: 0 2px 7px rgba(24, 43, 73, .06); }
    .sales-dashboard .metric { padding: 13px 15px; border-left: 3px solid var(--accent); min-height: 72px; }
    .sales-dashboard .metric-label { font-size: .72rem; color: #64748b; text-transform: uppercase; font-weight: 600; }
    .sales-dashboard .metric-value { font-size: 1.05rem; font-weight: 700; margin-top: 5px; font-variant-numeric: tabular-nums; color: #17345d; }
    .sales-dashboard h2 { font-size: 1.15rem; font-weight: 750; color: #17345d; }
    .sales-dashboard h3 { font-size: .95rem; font-weight: 650; }
    .sales-dashboard .table { font-size: .82rem; }
    .sales-dashboard .table th { color: #64748b; background: #f8fafc; font-size: .7rem; text-transform: uppercase; white-space: nowrap; }
    .sales-dashboard .table td, .sales-dashboard .table th { padding: .85rem .7rem; vertical-align: middle; }
    .sales-dashboard .scroll-panel { max-height: 370px; overflow: auto; }
    .sales-dashboard .transaction-matrix-scroll { overflow-x: auto; }
    .sales-dashboard .transaction-matrix { min-width: 850px; }
    .sales-dashboard .scroll-panel th { position: sticky; top: 0; z-index: 1; }
    .sales-dashboard .empty-state { padding: 3rem 1rem; text-align: center; color: #64748b; font-size: .85rem; }
    .sales-dashboard .rank { background: #f1f5f9; color: #64748b; border-radius: 8px; min-width: 30px; padding: 5px; text-align: center; }
    .sales-dashboard .filter-panel { padding: 12px 14px; }
    .sales-dashboard .filter-title { font-size: .78rem; font-weight: 700; color: #334155; }
    .sales-dashboard .filter-help { font-size: .75rem; color: #64748b; }
    .sales-dashboard .filter-actions { white-space: nowrap; }
    .sales-dashboard .sold-items-summary { width: 230px; min-width: 230px; min-height: 72px; padding: .55rem .75rem; border: 1px solid #bfdbfe; border-radius: 10px; background: #eff6ff; color: #17345d; }
    .sales-dashboard .sold-items-summary-label { color: #475569; font-size: .65rem; font-weight: 750; letter-spacing: .03em; text-transform: uppercase; }
    .sales-dashboard .sold-items-summary-value { color: #2563eb; font-size: 1.3rem; font-weight: 800; line-height: 1.1; font-variant-numeric: tabular-nums; }
    .sales-dashboard .sold-items-summary-help { display: block; max-width: 174px; overflow: hidden; color: #64748b; font-size: .65rem; text-overflow: ellipsis; white-space: nowrap; }
    .sales-dashboard .sold-items-summary-compact { width: 150px; min-width: 150px; min-height: 58px; padding: .35rem .45rem; }
    .sales-dashboard .sold-items-summary-compact .sold-items-summary-label { font-size: .56rem; }
    .sales-dashboard .sold-items-summary-compact .sold-items-summary-value { font-size: 1.05rem; }
    .sales-dashboard .sold-items-summary-compact .sold-items-summary-help { max-width: 88px; font-size: .56rem; }
    .sales-dashboard .marketplace-mop-panel { background: linear-gradient(145deg, #ffffff, #f7faff); }
    .sales-dashboard .marketplace-mop-total { min-width: 155px; padding: .75rem 1rem; border-radius: 12px; background: #eff6ff; color: #1d4ed8; text-align: right; }
    .sales-dashboard .marketplace-mop-card { padding: .85rem; border: 1px solid #e2e8f0; border-radius: 12px; background: #fff; min-height: 92px; }
    .sales-dashboard .marketplace-mop-name { min-height: 2rem; color: #64748b; font-size: .72rem; font-weight: 700; text-transform: uppercase; }
    .sales-dashboard .marketplace-mop-amount { font-size: 1rem; font-weight: 750; color: #0f172a; }
    .sales-dashboard .marketplace-mop-count { color: #64748b; font-size: .72rem; }
    .sales-dashboard .channel-card { padding: 13px 16px; border-left: 3px solid var(--channel); }
    .sales-dashboard .channel-card .channel-icon { width: 30px; height: 30px; display: inline-flex; align-items: center; justify-content: center; border-radius: 50%; color: #fff; background: var(--channel); }
    .sales-dashboard .all-store-channel-section { padding: 1rem; background: linear-gradient(145deg, #fff, #f8fbff); border-color: #d6e2f2; }
    .sales-dashboard .all-store-channel-grid > .col { display: flex; }
    .sales-dashboard .all-store-channel-grid .channel-card { width: 100%; }
    .sales-dashboard .head-office-channel-section .marketplace-comparison-grid .marketplace-sales-chart { height: 108px; }
    .sales-dashboard .head-office-channel-section .marketplace-comparison-grid .marketplace-sales-bar { max-height: 48px; }
    @media (min-width: 1200px) {
        .sales-dashboard .channel-card-grid-five { display: grid; grid-template-columns: repeat(6, minmax(0, 1fr)); gap: 1rem; }
        .sales-dashboard .channel-card-grid-five > .col { width: auto; grid-column: span 2; }
        .sales-dashboard .channel-card-grid-five > .col:nth-child(n+4) { grid-column: span 3; }
    }
    .sales-dashboard .channel-card-layout { display: grid; grid-template-columns: minmax(0, 1fr); gap: .75rem; align-items: stretch; height: 100%; }
    .sales-dashboard .channel-card-layout.channel-card-layout-selected { grid-template-columns: minmax(0, 1fr); }
    .sales-dashboard .channel-card-details { min-width: 0; }
    .sales-dashboard .marketplace-sales-chart { height: 128px; display: flex; align-items: end; justify-content: center; gap: 2rem; padding: 12px 1rem 0; border-top: 1px solid #e2e8f0; margin-top: 12px; }
    .sales-dashboard .marketplace-sales-bar-group { width: min(34%, 90px); height: 100%; display: flex; flex-direction: column; align-items: center; justify-content: end; gap: 4px; }
    .sales-dashboard .marketplace-sales-bar-value { color: #334155; font-size: .68rem; font-weight: 700; white-space: nowrap; }
    .sales-dashboard .marketplace-sales-bar { width: 100%; min-height: 2px; border-radius: 6px 6px 0 0; transition: height .2s ease; }
    .sales-dashboard .marketplace-sales-bar-label { padding: 5px 0 2px; color: #64748b; font-size: .72rem; font-weight: 700; }
    .sales-dashboard .marketplace-comparison-grid { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: .75rem; align-items: center; min-width: 0; border-top: 1px solid #e2e8f0; padding-top: .75rem; }
    .sales-dashboard .marketplace-comparison-title { color: #64748b; font-size: .67rem; font-weight: 700; text-align: center; }
    .sales-dashboard .marketplace-comparison-grid .marketplace-sales-chart { height: 118px; gap: .75rem; padding: 7px .2rem 0; border-top: 0; margin-top: 0; }
    .sales-dashboard .marketplace-comparison-grid .marketplace-sales-bar-label { min-height: 1.7rem; font-size: .58rem; line-height: 1.1; text-align: center; white-space: normal; }
    .sales-dashboard .marketplace-comparison-grid .marketplace-sales-bar-value { font-size: .54rem; }
    .sales-dashboard #slowProductsResults nav p { display: none; }
    .sales-dashboard .section-kicker { color: #17345d; font-size: .78rem; font-weight: 750; letter-spacing: .02em; }
    .sales-dashboard .dashboard-subtitle { color: #64748b; font-size: .72rem; }
    .sales-dashboard .product-panel { min-height: 300px; }
    .sales-dashboard .branch-snapshot { padding: 1rem; border-left: 3px solid #2563eb; background: linear-gradient(145deg, #fff, #f8fbff); }
    .sales-dashboard .branch-snapshot-total { color: #1d4ed8; font-size: 1.65rem; font-weight: 800; font-variant-numeric: tabular-nums; }
    .sales-dashboard .branch-snapshot-stat { padding: .7rem; border: 1px solid #e2e8f0; border-radius: 10px; background: #fff; }
    .sales-dashboard .branch-snapshot-stat-label { color: #64748b; font-size: .67rem; font-weight: 700; text-transform: uppercase; }
    .sales-dashboard .branch-snapshot-stat-value { margin-top: .25rem; font-size: 1rem; font-weight: 750; color: #17345d; }
    .sales-dashboard .branch-payment-card { min-height: 86px; padding: .8rem; border: 1px solid #e2e8f0; border-radius: 10px; background: #fff; }
    .sales-dashboard .branch-payment-name { min-height: 1.7rem; color: #64748b; font-size: .67rem; font-weight: 700; text-transform: uppercase; }
    .sales-dashboard .branch-payment-amount { color: #17345d; font-size: 1rem; font-weight: 750; }
    .sales-dashboard .all-store-overview { padding: 1.1rem; background: linear-gradient(135deg, #fff, #f4f8ff); border-color: #d6e2f2; }
    .sales-dashboard .all-store-total { color: #1d4ed8; font-size: 1.6rem; font-weight: 800; font-variant-numeric: tabular-nums; }
    .sales-dashboard .all-store-stat { padding: .65rem .8rem; border: 1px solid #e2e8f0; border-radius: 10px; background: #fff; }
    .sales-dashboard .all-store-stat-label { color: #64748b; font-size: .66rem; font-weight: 700; text-transform: uppercase; }
    .sales-dashboard .all-store-stat-value { margin-top: .15rem; color: #17345d; font-size: 1rem; font-weight: 750; }
    .sales-dashboard .store-sales-chart { overflow-x: auto; padding: .5rem .25rem .25rem; }
    .sales-dashboard .store-sales-chart-grid {
        display: grid;
        grid-template-columns: repeat(var(--store-count), minmax(96px, 1fr));
        gap: .65rem;
        min-width: max(100%, calc(var(--store-count) * 112px));
    }
    .sales-dashboard .store-sales-column { min-width: 0; text-align: center; }
    .sales-dashboard .store-sales-value { min-height: 1.5rem; color: #17345d; font-size: .72rem; font-weight: 750; font-variant-numeric: tabular-nums; white-space: nowrap; }
    .sales-dashboard .store-sales-bar-area {
        display: flex;
        height: 180px;
        align-items: flex-end;
        justify-content: center;
        margin: .35rem 0 .65rem;
        border-bottom: 1px solid #94a3b8;
        background: repeating-linear-gradient(to top, transparent 0 calc(25% - 1px), #e7edf5 calc(25% - 1px) 25%);
    }
    .sales-dashboard .store-sales-bar {
        width: min(56px, 58%);
        min-height: 2px;
        border-radius: 7px 7px 0 0;
        background: linear-gradient(180deg, #38bdf8, #2563eb);
        box-shadow: 0 3px 8px rgba(37, 99, 235, .15);
    }
    .sales-dashboard .store-sales-bar.is-branch { background: linear-gradient(180deg, #34d399, #059669); box-shadow: 0 3px 8px rgba(5, 150, 105, .15); }
    .sales-dashboard .store-sales-bar.is-zero { background: #94a3b8; box-shadow: none; }
    .sales-dashboard .store-sales-name { min-height: 2.5rem; color: #334155; font-size: .72rem; font-weight: 650; line-height: 1.2; overflow-wrap: anywhere; }
    .sales-dashboard .store-sales-meta { margin-top: .35rem; color: #64748b; font-size: .66rem; }
    .sales-dashboard .store-sales-meta .badge { font-size: .6rem; }
    @media (max-width: 767.98px) { .sales-dashboard { padding: 0 .1rem; } .sales-dashboard .metric { min-height: 68px; } .sales-dashboard .marketplace-comparison-grid { gap: .35rem; } }
</style>
<div class="sales-dashboard">
    @php
        $isInventoryStaffDashboard = auth()->user()?->role === 'inventory_staff';
        $isMarketingDashboard = auth()->user()?->role === 'sales_marketing_staff';
        $selectedDashboardHub = $hubId ? $dashboardHubs->firstWhere('id', (int) $hubId) : null;
        $isHeadOfficeAdminDashboard = auth()->user()?->role === 'admin' && $selectedDashboardHub?->is_head_office;
        $channelCardColumns = ($isAllStoresAdminDashboard || $isHeadOfficeAdminDashboard) ? '3' : ($channelSummaries->count() > 1 ? '2' : '1');
        $channelCardGridClass = $channelSummaries->count() === 5 ? 'channel-card-grid-five' : '';
        $allStoreChannelGridClass = $isAllStoresAdminDashboard ? 'all-store-channel-grid' : '';
        $headOfficeChannelGridClass = $isHeadOfficeAdminDashboard ? 'head-office-channel-grid' : '';
        $isTikTokSideBySideDashboard = $isTikTokDashboard && ! $isBranchDashboard && ! $isInventoryStaffDashboard;
        $isMarketplaceSideBySideDashboard = in_array($dashboardChannel, ['shopee', 'lazada'], true)
            && $marketplaceOverviews->count() === 1
            && ! $isBranchDashboard
            && ! $isInventoryStaffDashboard;
        $isOperationalSideBySideDashboard = $channelPaymentOverview !== null
            && ! $isBranchDashboard
            && ! $isInventoryStaffDashboard;
        $isChannelSideBySideDashboard = $isTikTokSideBySideDashboard
            || $isMarketplaceSideBySideDashboard
            || $isOperationalSideBySideDashboard;
    @endphp
    <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
        <div><h2 class="mb-1">Sales Management Hub</h2><p class="dashboard-subtitle mb-0">Sales and inventory overview · {{ $scopeName }}</p></div>
        @if(auth()->user()?->role === 'sales_marketing_staff')
            <span class="badge rounded-pill text-primary bg-primary-subtle px-3 py-2"><i class="fa-solid fa-bullhorn me-1"></i>Marketing workspace</span>
        @endif
    </div>
    <div class="panel filter-panel mb-4">
        <form method="GET" action="{{ route('dashboard') }}" class="row g-3 align-items-end" id="dashboardFilters">
            <div class="col-12 col-md-5 {{ $isMarketingDashboard ? 'col-xl-3' : 'col-xl-4' }}">
                @if($dashboardHubs->count() === 1)
                    <span class="form-label filter-title mb-1 d-block">Store location</span>
                    <div class="form-control bg-light">{{ $dashboardHubs->first()->name }}</div>
                    <input type="hidden" name="hub_id" value="{{ $dashboardHubs->first()->id }}">
                @else
                <label for="dashboardHub" class="form-label filter-title mb-1">Store location</label>
                <select name="hub_id" id="dashboardHub" class="form-select" onchange="submitDashboardFilters(this.form)">
                    @if(auth()->user()?->role === 'admin')<option value="">All accessible stores</option>@endif 
                    @foreach($dashboardHubs as $store)
                        <option value="{{ $store->id }}" @selected((int) $hubId === $store->id)>{{ $store->name }}</option>
                    @endforeach
                </select>
                @endif
            </div>
            @if(auth()->user()?->role === 'sales_marketing_staff' && $dashboardChannelOptions->isNotEmpty())
            <div class="col-12 col-md-4 {{ $isMarketingDashboard ? 'col-xl-2' : 'col-xl-3' }}">
                <label for="dashboardChannel" class="form-label filter-title mb-1">Sales channel</label>
                <select name="channel" id="dashboardChannel" class="form-select" @disabled($dashboardChannelOptions->count() === 1) onchange="submitDashboardFilters(this.form)">
                    @foreach($dashboardChannelOptions as $channelOption)
                        <option value="{{ $channelOption }}" @selected($dashboardChannel === $channelOption)>{{ ['shopee' => 'Shopee', 'lazada' => 'Lazada', 'tiktok' => 'TikTok', 'online' => 'Online', 'wholesale' => 'Wholesale', 'walk_in' => 'Walk-In'][$channelOption] }}</option>
                    @endforeach
                </select>
            </div>
            @endif
            <div class="col-12 col-md-3 col-xl-2">
                <label for="dashboardFrom" class="form-label filter-title mb-1">From</label>
                <input type="date" id="dashboardFrom" name="from" value="{{ $fromDate->toDateString() }}" max="{{ $asOf->toDateString() }}" class="form-control" onchange="updateDashboardDateBounds(this.form)" required>
            </div>
            <div class="col-12 col-md-3 col-xl-2">
                <label for="dashboardTo" class="form-label filter-title mb-1">To</label>
                <input type="date" id="dashboardTo" name="to" value="{{ $asOf->toDateString() }}" min="{{ $fromDate->toDateString() }}" class="form-control" onchange="updateDashboardDateBounds(this.form)" required>
            </div>
            <div class="{{ $isMarketingDashboard ? 'col-12 col-xl-auto' : 'col-12 col-md-auto' }} filter-actions d-flex gap-2">
                <button class="btn btn-primary px-3"><i class="fa-solid fa-filter me-1"></i>Apply</button>
            </div>
            @if($isMarketingDashboard)
            <div class="col-12 col-xl d-flex justify-content-xl-end">
                <div class="sold-items-summary sold-items-summary-compact d-flex align-items-center gap-1">
                    <i class="fa-solid fa-cart-flatbed fs-5 text-primary"></i>
                    <div>
                        <div class="sold-items-summary-label">Total sold items</div>
                        <div class="sold-items-summary-value">{{ number_format($soldItemCount) }}</div>
                        <div class="sold-items-summary-help" title="{{ $scopeName }}">{{ $scopeName }}</div>
                    </div>
                </div>
            </div>
            @endif
            @unless($isMarketingDashboard)
            <div class="col-12 col-xl d-flex justify-content-xl-end">
                <div class="sold-items-summary d-flex align-items-center gap-2">
                    <i class="fa-solid fa-cart-flatbed fs-5 text-primary"></i>
                    <div>
                        <div class="sold-items-summary-label">Total sold items</div>
                        <div class="sold-items-summary-value">{{ number_format($soldItemCount) }}</div>
                        <div class="sold-items-summary-help" title="{{ $scopeName }}">{{ $scopeName }}</div>
                    </div>
                </div>
            </div>
            @endunless
        </form>
    </div>
    
    @unless($isInventoryStaffDashboard)
    <div class="row row-cols-1 row-cols-sm-2 row-cols-xl-5 g-3 mb-3">
        @foreach($salesTotals as $period => $amount)
            <div class="col"><div class="panel metric h-100" style="--accent: {{ ['Daily'=>'#6366f1','Weekly'=>'#64748b','Monthly'=>'#10b981','Quarterly'=>'#f59e0b','Yearly'=>'#06b6d4'][$period] }}"><div class="metric-label">{{ $period }} Sales</div><div class="metric-value">₱{{ number_format($amount, 2) }}</div></div></div>
        @endforeach
    </div>
    @unless($isTikTokDashboard)
    <p class="small text-muted mb-4">{{ $isBranchDashboard ? 'Branch Walk-In sales from '.$fromDate->format('M d, Y').' through '.$asOf->format('M d, Y').'. Payment totals are grouped by the recorded payment method.' : 'Confirmed sales from '.$fromDate->format('M d, Y').' through '.$asOf->format('M d, Y').'; weeks start Monday. Wholesale uses the collected amount for partial payments, excludes unpaid orders, and counts an order as completed only when it is paid and delivered.' }}</p>
    @endunless
    @if($isAllStoresAdminDashboard)
        @php
            $allStoreSalesTotal = (float) $storeSalesOverview->sum('total');
            $allStoreTransactionTotal = (int) $storeSalesOverview->sum('transactions');
            $allStoreHeadOfficeCount = $storeSalesOverview->where('mode', 'Head Office')->count();
            $allStoreBranchCount = $storeSalesOverview->where('mode', 'Branch')->count();
            $allStoreMaxSales = max(1, (float) $storeSalesOverview->max('total'));
        @endphp
        <section class="panel all-store-overview p-3 p-lg-4 mb-4">
            <div class="d-flex flex-wrap justify-content-between align-items-start gap-3 mb-3">
                <div>
                    <div class="section-kicker"><i class="fa-solid fa-chart-column text-primary me-2"></i>All-Store Performance</div>
                    <p class="small text-muted mb-0">Head Office and non-Head Office branches · {{ $fromDate->format('M d, Y') }} – {{ $asOf->format('M d, Y') }}</p>
                </div>
                <div class="text-end"><div class="small text-muted">Combined sales</div><div class="all-store-total">₱{{ number_format($allStoreSalesTotal, 2) }}</div></div>
            </div>
            <div class="row g-2 mb-4">
                <div class="col-6 col-lg-3"><div class="all-store-stat h-100"><div class="all-store-stat-label">Accessible stores</div><div class="all-store-stat-value">{{ number_format($storeSalesOverview->count()) }}</div></div></div>
                <div class="col-6 col-lg-3"><div class="all-store-stat h-100"><div class="all-store-stat-label">Head Offices</div><div class="all-store-stat-value">{{ number_format($allStoreHeadOfficeCount) }}</div></div></div>
                <div class="col-6 col-lg-3"><div class="all-store-stat h-100"><div class="all-store-stat-label">Branches</div><div class="all-store-stat-value">{{ number_format($allStoreBranchCount) }}</div></div></div>
                <div class="col-6 col-lg-3"><div class="all-store-stat h-100"><div class="all-store-stat-label">Transactions</div><div class="all-store-stat-value">{{ number_format($allStoreTransactionTotal) }}</div></div></div>
            </div>
            <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
                <div><h3 class="mb-1">Sales by store</h3><p class="small text-muted mb-0">Net sales across all channels, ranked by sales volume.</p></div>
                <div class="small text-muted"><span class="badge me-1" style="background:#2563eb"> </span>Head Office <span class="badge ms-2 me-1" style="background:#059669"> </span>Branch</div>
            </div>
            @if($storeSalesOverview->isEmpty())
                <div class="empty-state">No accessible stores found.</div>
            @else
                @if($allStoreSalesTotal <= 0)
                    <div class="empty-state py-4"><i class="fa-solid fa-chart-column d-block fs-3 mb-2"></i>No sales were recorded for these stores during the selected date range.</div>
                @else
                    <div class="store-sales-chart" role="img" aria-label="Vertical bar chart comparing net sales across accessible stores">
                        <div class="store-sales-chart-grid" style="--store-count: {{ $storeSalesOverview->count() }}">
                            @foreach($storeSalesOverview as $storeSales)
                                @php($storeBarHeight = $storeSales['total'] > 0 ? max(2, ($storeSales['total'] / $allStoreMaxSales) * 100) : 0)
                                <div class="store-sales-column" title="{{ $storeSales['name'] }}: ₱{{ number_format($storeSales['total'], 2) }}, {{ number_format($storeSales['transactions']) }} transactions">
                                    <div class="store-sales-value">₱{{ number_format($storeSales['total'], 2) }}</div>
                                    <div class="store-sales-bar-area" aria-hidden="true">
                                        <div class="store-sales-bar {{ $storeSales['mode'] === 'Branch' ? 'is-branch' : '' }} {{ $storeSales['total'] <= 0 ? 'is-zero' : '' }}" style="height: {{ $storeBarHeight }}%"></div>
                                    </div>
                                    <div class="store-sales-name">{{ $storeSales['name'] }}</div>
                                    <div class="store-sales-meta">
                                        <span class="badge {{ $storeSales['mode'] === 'Branch' ? 'text-bg-success' : 'text-bg-primary' }}">{{ $storeSales['mode'] }}</span>
                                        <span class="d-block mt-1">{{ number_format($storeSales['transactions']) }} trans.</span>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    </div>
                @endif
            @endif
        </section>
    @endif
    @if($isBranchDashboard)
        @php($branchSummary = $channelSummaries->first())
        @php($branchPayment = $matrix->first())
        <div class="row g-3 align-items-stretch mb-4">
            <div class="col-xl-4">
                <section class="panel branch-snapshot h-100">
                    <div class="d-flex justify-content-between align-items-start mb-3">
                        <div><div class="section-kicker">Branch Sales Snapshot</div><div class="small text-muted">{{ $scopeName }} · {{ $fromDate->format('M d, Y') }} – {{ $asOf->format('M d, Y') }}</div></div>
                        <span class="channel-icon" style="--channel:#64748b"><i class="fa-solid fa-cash-register"></i></span>
                    </div>
                    <div class="small text-muted">Walk-In sales in selected range</div>
                    <div class="branch-snapshot-total">₱{{ number_format($branchSummary['total'] ?? 0, 2) }}</div>
                    <div class="row row-cols-2 g-2 mt-1">
                        <div class="col"><div class="branch-snapshot-stat"><div class="branch-snapshot-stat-label">Transactions</div><div class="branch-snapshot-stat-value">{{ number_format($branchSummary['transactions'] ?? 0) }}</div></div></div>
                        <div class="col"><div class="branch-snapshot-stat"><div class="branch-snapshot-stat-label">Customers</div><div class="branch-snapshot-stat-value">{{ number_format($branchSummary['total_customers'] ?? 0) }}</div></div></div>
                        <div class="col"><div class="branch-snapshot-stat"><div class="branch-snapshot-stat-label">New customers</div><div class="branch-snapshot-stat-value text-success">{{ number_format($branchSummary['new_customers'] ?? 0) }}</div></div></div>
                        <div class="col"><div class="branch-snapshot-stat"><div class="branch-snapshot-stat-label">Refund cost</div><div class="branch-snapshot-stat-value text-danger">₱{{ number_format($branchSummary['refund_total'] ?? 0, 2) }}</div></div></div>
                    </div>
                </section>
            </div>
            <div class="col-xl-8">
                <section class="panel marketplace-mop-panel p-3 p-lg-4 h-100">
                    <div class="d-flex flex-wrap justify-content-between align-items-start gap-2 mb-3">
                        <div><h3 class="mb-1"><i class="fa-regular fa-credit-card text-primary me-2"></i>Payment Methods</h3><p class="small text-muted mb-0">{{ $scopeName }} · {{ $fromDate->format('M d, Y') }} – {{ $asOf->format('M d, Y') }}</p></div>
                        <div class="marketplace-mop-total"><div class="small text-uppercase fw-bold">Total collected</div><div class="fs-5 fw-bold">₱{{ number_format(array_sum($branchPayment['amounts'] ?? []), 2) }}</div></div>
                    </div>
                    <div class="row row-cols-2 row-cols-md-4 g-2">
                        @foreach($branchPayment['amounts'] ?? [] as $method => $amount)
                            <div class="col"><div class="branch-payment-card h-100"><div class="branch-payment-name">{{ $method }}</div><div class="branch-payment-amount">₱{{ number_format($amount, 2) }}</div><div class="marketplace-mop-count">{{ number_format($branchPayment['counts'][$method] ?? 0) }} transaction{{ ($branchPayment['counts'][$method] ?? 0) === 1 ? '' : 's' }}</div></div></div>
                        @endforeach
                    </div>
                </section>
            </div>
        </div>
    @else
    @if($isChannelSideBySideDashboard)
    <div class="row g-4 align-items-stretch mb-4">
        <div class="col-xl-6">
    @endif
    @if($channelSummaries->isNotEmpty())
    <section class="{{ $isChannelSideBySideDashboard ? 'panel p-3 p-lg-4 h-100' : ($isAllStoresAdminDashboard ? 'panel all-store-channel-section mb-4' : ($isHeadOfficeAdminDashboard ? 'panel head-office-channel-section mb-4' : 'mb-4')) }}">
        <div>
            <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
                <div>
                    <h3 class="mb-1">Sales Channels</h3>
                    @if($isAllStoresAdminDashboard)
                        <p class="small text-muted mb-0">Combined channel performance across all accessible stores · {{ $fromDate->format('M d, Y') }} – {{ $asOf->format('M d, Y') }}</p>
                    @endif
                </div>
            </div>
            <div class="row row-cols-1 row-cols-sm-2 row-cols-lg-{{ $channelCardColumns }} g-3 {{ $channelCardGridClass }} {{ $allStoreChannelGridClass }} {{ $headOfficeChannelGridClass }}">
            @foreach($channelSummaries as $summary)
                <div class="col">
                    <div class="panel channel-card h-100" style="--channel: var(--bs-{{ $summary['color'] }});">
                        <div class="channel-card-layout {{ $dashboardChannel ? 'channel-card-layout-selected' : '' }}">
                            <div class="channel-card-details">
                        <div class="d-flex justify-content-between align-items-start mb-3">
                            <span class="fw-semibold">{{ $summary['label'] }}</span>
                            <span class="channel-icon"><i class="fa-solid {{ $summary['icon'] }}"></i></span>
                        </div>
                        <div class="fs-5 fw-bold text-primary">₱{{ number_format($summary['total'], 2) }}</div>
                        @if($summary['key'] === 'wholesale')
                            <div class="small text-muted">
                                {{ number_format($summary['completed_transactions']) }} completed
                                @if($summary['partial_transactions'] > 0) · {{ number_format($summary['partial_transactions']) }} partial @endif
                                @if($summary['open_transactions'] > $summary['partial_transactions']) · {{ number_format($summary['open_transactions'] - $summary['partial_transactions']) }} other open @endif
                            </div>
                        @else
                            <div class="small text-muted">{{ number_format($summary['transactions']) }} completed transaction{{ $summary['transactions'] === 1 ? '' : 's' }}</div>
                        @endif
                        <div class="d-flex flex-wrap gap-3 border-top mt-2 pt-2 small">
                            <span class="text-muted">Total customers <strong class="text-dark">{{ number_format($summary['total_customers']) }}</strong></span>
                            <span class="text-muted">New customers <strong class="text-success">{{ number_format($summary['new_customers']) }}</strong></span>
                        </div>
                        <div class="small text-danger mt-1">Refund cost ₱{{ number_format($summary['refund_total'], 2) }}</div>
                            </div>
                        @if($marketplaceSalesComparison->has($summary['key']))
                            @php($channelSalesComparison = $marketplaceSalesComparison->get($summary['key']))
                            @php($channelCustomerComparison = $marketplaceCustomerComparison->get($summary['key']))
                            @php($comparisonBars = ['last_year' => ['label' => 'Last Year', 'color' => '#94a3b8'], 'this_year' => ['label' => 'This Year', 'color' => '#2563eb']])
                            <div class="marketplace-comparison-grid">
                                @foreach([
                                    ['title' => 'Sales · selected range', 'values' => $channelSalesComparison, 'money' => true, 'description' => 'sales'],
                                    ['title' => 'No. of Customers', 'values' => $channelCustomerComparison, 'money' => false, 'description' => 'unique customers'],
                                ] as $comparison)
                                    @php($maxComparisonValue = max(1, (float) $comparison['values']->max()))
                                    <div>
                                        <div class="marketplace-comparison-title">{{ $comparison['title'] }}</div>
                                        <div class="marketplace-sales-chart" role="img" aria-label="{{ $summary['label'] }} {{ $comparison['description'] }} comparison: last year and this year for the selected date range">
                                            @foreach($comparisonBars as $marketplaceKey => $marketplaceStyle)
                                                @php($comparisonValue = (float) $comparison['values']->get($marketplaceKey, 0))
                                                @php($barHeight = $comparisonValue > 0 ? max(4, ($comparisonValue / $maxComparisonValue) * 58) : 2)
                                                <div class="marketplace-sales-bar-group">
                                                    <span class="marketplace-sales-bar-value">{{ $comparison['money'] ? '₱'.number_format($comparisonValue, 2) : number_format($comparisonValue) }}</span>
                                                    <div class="marketplace-sales-bar" style="height: {{ $barHeight }}px; background: {{ $marketplaceStyle['color'] }};" aria-hidden="true"></div>
                                                    <span class="marketplace-sales-bar-label">{{ $marketplaceKey === 'last_year' ? 'Last Yr' : 'This Yr' }}<br>({{ $marketplaceKey === 'last_year' ? $asOf->copy()->subYear()->year : $asOf->year }})</span>
                                                </div>
                                            @endforeach
                                        </div>
                                    </div>
                                @endforeach
                            </div>
                        @endif
                        </div>
                    </div>
                </div>
            @endforeach
            </div>
        </div>
    </section>
    @endif
    @if($isChannelSideBySideDashboard)
        </div>
        <div class="col-xl-6">
            @if($isTikTokSideBySideDashboard)
                @include('dashboard._tiktok-settlement-overview')
            @elseif($isMarketplaceSideBySideDashboard)
                @include('dashboard._marketplace-payment-overviews')
            @else
                <section class="panel marketplace-mop-panel p-3 p-lg-4 h-100">
                    <div class="d-flex flex-wrap justify-content-between align-items-start gap-3 mb-3">
                        <h3 class="mb-1"><i class="fa-regular fa-credit-card text-primary me-2"></i>{{ $channelPaymentOverview['label'] }}</h3>
                        <div class="marketplace-mop-total">
                            <div class="small text-uppercase fw-bold">Total collected</div>
                            <div class="fs-5 fw-bold">₱{{ number_format($channelPaymentOverview['total'], 2) }}</div>
                            <div class="marketplace-mop-count">{{ number_format($channelPaymentOverview['transactions']) }} transactions</div>
                        </div>
                    </div>
                    @php($activePaymentMethods = collect($channelPaymentOverview['amounts'])->filter(fn ($amount, $method) => $channelPaymentOverview['counts'][$method] > 0))
                    @if($activePaymentMethods->isNotEmpty())
                        <div class="row row-cols-2 row-cols-md-3 g-2">
                            @foreach($activePaymentMethods as $method => $amount)
                                <div class="col">
                                    <div class="marketplace-mop-card h-100">
                                        <div class="marketplace-mop-name">{{ $method }}</div>
                                        <div class="marketplace-mop-amount">₱{{ number_format($amount, 2) }}</div>
                                        <div class="marketplace-mop-count">{{ number_format($channelPaymentOverview['counts'][$method]) }} transactions</div>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    @else
                        <div class="empty-state py-4">No payments recorded for this channel in the selected date range.</div>
                    @endif
                </section>
            @endif
        </div>
    </div>
    @endif
    @endif
    @endunless
    <div class="row g-4 mb-4">
        @unless($isInventoryStaffDashboard)
        @if($isTikTokDashboard && ! $isTikTokSideBySideDashboard)
        <div class="col-12">@include('dashboard._tiktok-settlement-overview')</div>
        @else
        @if($isBranchDashboard)
        @elseif($isChannelSideBySideDashboard)
        @elseif($marketplaceOverviews->isNotEmpty())
        <div class="col-12"><section class="panel p-3 p-lg-4 h-100 {{ $marketplaceOverviews->isNotEmpty() ? 'marketplace-mop-panel' : '' }}">
            <div class="row g-4">
            @foreach($marketplaceOverviews as $overview)
            @php($overviewChannel = $overview['channel'])
            <div class="{{ $marketplaceOverviews->count() > 1 ? 'col-xl-6' : 'col-12' }}">
                <div class="d-flex flex-wrap justify-content-between align-items-start gap-3 mb-3">
                    <div><h3 class="mb-1"><i class="fa-solid {{ $overviewChannel === 'shopee' ? 'fa-bag-shopping text-warning' : 'fa-store text-info' }} me-2"></i>{{ ucfirst($overviewChannel) }} Payment Overview</h3></div>
                    <div class="marketplace-mop-total"><div class="small text-uppercase fw-bold">Total collected</div><div class="fs-5 fw-bold">₱{{ number_format($overview['total'], 2) }}</div></div>
                </div>
                @foreach($overview['rows'] as $row)
                    @if($overview['rows']->count() > 1)<div class="small fw-bold text-muted mb-2">{{ $row['name'] }}</div>@endif
                    <div class="row row-cols-2 row-cols-md-3 g-2 mb-3">
                        @foreach($row['amounts'] as $method => $amount)
                            <div class="col"><div class="marketplace-mop-card h-100"><div class="marketplace-mop-name">{{ $method }}</div><div class="marketplace-mop-amount">₱{{ number_format($amount, 2) }}</div><div class="marketplace-mop-count">{{ number_format($row['counts'][$method]) }} transaction{{ $row['counts'][$method] === 1 ? '' : 's' }}</div></div></div>
                        @endforeach
                    </div>
                @endforeach
            </div>
            @endforeach
            </div>
        </section></div>
        @endif
        @endif
        @endunless
        <div class="col-xl-6"><section class="panel product-panel p-3 p-lg-4 h-100">
            <h3 class="mb-1"><i class="fa-solid fa-trophy text-warning me-2"></i>Top 10 Products</h3>
            <p class="small text-muted mb-3">{{ $fromDate->format('M d, Y') }} – {{ $asOf->format('M d, Y') }} · Ranked by units sold</p>
            <div class="top-products-list">@forelse($topProducts as $product)<div class="d-flex align-items-center gap-2 py-2 border-bottom small"><span class="rank">{{ $loop->iteration }}</span><span class="flex-grow-1">{{ $product['name'] }}</span><span class="text-muted text-nowrap">{{ number_format($product['units']) }} sold</span></div>@empty<div class="empty-state">No completed sales for this period.</div>@endforelse</div>
        </section></div>
        <div class="col-xl-6"><section class="panel product-panel p-3 p-lg-4 h-100">
            <h3 class="mb-1"><i class="fa-solid fa-hourglass-half text-secondary me-2"></i>Slow-Moving Items</h3><p class="small text-muted mb-3">Selected date range · Ranked by lowest units sold</p>
            <div id="slowProductsResults" aria-live="polite">
                <div>@forelse($slowProducts as $product)<div class="d-flex align-items-center gap-2 py-2 border-bottom small"><span class="rank">{{ $slowProducts->firstItem() + $loop->index }}</span><span class="flex-grow-1">{{ $product['name'] }}</span><span class="text-muted text-nowrap">{{ number_format($product['stock']) }} pcs in stock · {{ number_format($product['units']) }} sold</span></div>@empty<div class="empty-state">No active products with stock.</div>@endforelse</div>
                @if($slowProducts->hasPages())
                    <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mt-3">
                        <small class="text-muted">Showing {{ $slowProducts->firstItem() }}–{{ $slowProducts->lastItem() }} of {{ $slowProducts->total() }} products</small>
                        {{ $slowProducts->onEachSide(1)->links() }}
                    </div>
                @endif
            </div>
        </section></div>
    </div>
    @if($isBranchDashboard || !in_array(auth()->user()?->role, ['sales_marketing_staff', 'sales_associate'], true))
    <section class="panel p-3 p-lg-4">
        <div class="d-flex justify-content-between gap-2"><div><h3 class="mb-1"><i class="fa-solid fa-triangle-exclamation text-danger me-2"></i>Live Inventory Alerts</h3><p class="small text-muted mb-3">{{ $isBranchDashboard ? $scopeName.' · ' : '' }}Physical stock only; channel allocations are excluded · Red: 0–10 pcs · Orange: 11–30 pcs · {{ $alertCount }} alerts</p></div></div>
        <div id="inventoryAlertResults" aria-live="polite">
            @if($alerts->isEmpty())<div class="empty-state">No active products in the selected stores.</div>@else
            <div class="table-responsive"><table class="table mb-0"><thead><tr><th>Product</th><th>Store</th><th class="text-end">Stock</th><th>Status</th></tr></thead><tbody>@foreach($alerts as $product)@php($stockStatus = $product->stock <= 10 ? ['class' => 'bg-danger', 'label' => 'Critical stock'] : ['class' => 'bg-warning text-dark', 'label' => 'Low stock'])<tr><td class="fw-semibold">{{ $product->name }}<span class="d-block small text-muted">{{ $product->item_id }}</span></td><td>{{ $product->storeHub?->name }}</td><td class="text-end">{{ $product->stock }} pcs</td><td><span class="badge {{ $stockStatus['class'] }}">{{ $stockStatus['label'] }}</span></td></tr>@endforeach</tbody></table></div>
            @if($alerts->hasPages())
                <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mt-3">
                    <small class="text-muted">Showing {{ $alerts->firstItem() }}–{{ $alerts->lastItem() }} of {{ $alertCount }} alerts</small>
                    {{ $alerts->onEachSide(1)->links() }}
                </div>
            @endif
        @endif
        </div>
    </section>
    @endif
    @if(auth()->user()?->role === 'admin')
    <section class="panel p-3 p-lg-4 mt-4">
        <div class="d-flex flex-wrap justify-content-between align-items-center gap-3">
            <div>
                <h3 class="mb-1"><i class="fa-solid fa-clipboard-check text-warning me-2"></i>For Inventory Verification</h3>
                <p class="small text-muted mb-0">Sales records waiting for inventory staff confirmation.</p>
            </div>
            <a href="{{ route('sales.pending') }}" class="btn btn-outline-warning btn-sm">
                Review {{ number_format($pendingVerificationCount) }} {{ \Illuminate\Support\Str::plural('record', $pendingVerificationCount) }} awaiting verification
            </a>
        </div>
    </section>
    @endif
</div>
<script>
    function updateDashboardDateBounds(form) {
        const from = form.elements.namedItem('from');
        const to = form.elements.namedItem('to');
        if (from instanceof HTMLInputElement && to instanceof HTMLInputElement) {
            from.max = to.value;
            to.min = from.value;
        }
    }

    function submitDashboardFilters(form) {
        ['from', 'to'].forEach(name => {
            const date = form.elements.namedItem(name);
            if (date instanceof HTMLInputElement) {
                date.value = date.defaultValue;
            }
        });
        updateDashboardDateBounds(form);
        form.submit();
    }
</script>
<script>
    (() => {
        const resultsContainers = ['inventoryAlertResults', 'slowProductsResults']
            .map(id => document.getElementById(id))
            .filter(element => element instanceof HTMLElement);
        if (!resultsContainers.length) return;

        let loading = false;
        const loadDashboardResults = async (url, updateHistory = true) => {
            if (loading) return;
            loading = true;
            resultsContainers.forEach(results => results.setAttribute('aria-busy', 'true'));

            try {
                const response = await fetch(url, {
                    credentials: 'same-origin',
                    headers: { Accept: 'text/html', 'X-Requested-With': 'XMLHttpRequest' },
                });
                if (!response.ok) throw new Error(`Dashboard request failed with status ${response.status}.`);

                const document = new DOMParser().parseFromString(await response.text(), 'text/html');
                resultsContainers.forEach(results => {
                    const nextResults = document.getElementById(results.id);
                    if (!nextResults) throw new Error(`Dashboard results were missing: ${results.id}.`);
                    results.innerHTML = nextResults.innerHTML;
                });
                if (updateHistory) window.history.pushState({}, '', response.url || url);
            } catch (error) {
                console.error('Could not load dashboard product results.', error);
                if (window.AppAlert?.show) {
                    window.AppAlert.show('Could not load dashboard products. Please try again.', 'error');
                } else {
                    window.alert('Could not load dashboard products. Please try again.');
                }
            } finally {
                loading = false;
                resultsContainers.forEach(results => results.removeAttribute('aria-busy'));
            }
        };

        document.addEventListener('click', event => {
            const link = event.target instanceof Element ? event.target.closest('a') : null;
            if (!link || !resultsContainers.some(results => results.contains(link)) || link.target || event.ctrlKey || event.metaKey || event.shiftKey || event.altKey) return;
            event.preventDefault();
            loadDashboardResults(link.href);
        });

        window.addEventListener('popstate', () => loadDashboardResults(window.location.href, false));
    })();
</script>
@endsection
