@extends('layouts.app')

@section('content')
<style>
    .inventory-log-table { font-size: .875rem; }
    .inventory-log-table th, .inventory-log-table td { padding: .55rem .65rem; white-space: nowrap; }
    .inventory-log-table td:nth-child(3), .inventory-log-table td:nth-child(8) { white-space: normal; }
    .fully-booked-preview { display: block; max-width: min(100%, 680px); max-height: 560px; margin-top: .75rem; border: 1px solid #e2e8f0; border-radius: .75rem; background: #f8fafc; }
    .fully-booked-pdf { width: min(100%, 760px); height: 560px; margin-top: .75rem; border: 1px solid #e2e8f0; border-radius: .75rem; }
    .return-detail-hero { background: linear-gradient(135deg, #eff6ff, #f8fafc); border-bottom: 1px solid #dbeafe; }
    .return-detail-kpi { border: 1px solid #e5e7eb; border-radius: .75rem; padding: .85rem 1rem; background: #fff; }
    .return-detail-kpi .value { font-size: 1.35rem; font-weight: 700; line-height: 1.1; }
    .return-detail-kpi.good .value { color: #198754; }
    .return-detail-kpi.damaged .value { color: #dc3545; }
    .return-detail-kpi.refund .value { color: #b02a37; }
    .return-detail-items { border: 1px solid #e5e7eb; border-radius: .75rem; overflow: hidden; }
    .return-detail-item { padding: .85rem 1rem; background: #fff; }
    .return-detail-item + .return-detail-item { border-top: 1px solid #e5e7eb; }
    .return-detail-meta { color: #6c757d; font-size: .82rem; }
    .replacement-detail-hero { border: 1px solid #bfdbfe; border-radius: 14px; padding: 1rem; background: linear-gradient(135deg, #eff6ff, #f8fafc); }
    .replacement-detail-hero .item-card { height: 100%; border: 1px solid #dbeafe; border-radius: 10px; padding: .85rem; background: #fff; }
    .replacement-detail-hero .item-card .label, .replacement-detail-section .label { color: #64748b; font-size: .72rem; font-weight: 800; letter-spacing: .04em; text-transform: uppercase; }
    .replacement-detail-hero .item-card .name { font-weight: 700; margin-top: .25rem; }
    .replacement-detail-section { border: 1px solid #e5e7eb; border-radius: 12px; padding: .9rem; background: #fff; height: 100%; }
    .replacement-detail-section .value { margin-top: .2rem; font-weight: 600; }
    .replacement-detail-section .status { display: inline-flex; align-items: center; gap: .4rem; }
    .replacement-detail-notes { border-left: 4px solid #f59e0b; border-radius: 8px; padding: .75rem .9rem; background: #fffbeb; }
    .log-filter-card { border: 1px solid #e2e8f0; background: linear-gradient(135deg, #ffffff, #f8fafc); }
    .log-filter-card .form-label { letter-spacing: .05em; font-size: .7rem; }
    .log-table-card { border: 1px solid #e2e8f0; }
    .inventory-log-table thead th { background: #eff6ff; color: #1e3a8a; border-bottom: 2px solid #bfdbfe; font-size: .72rem; letter-spacing: .04em; }
    .inventory-log-table tbody tr { transition: background .15s ease, transform .15s ease; }
    .inventory-log-table tbody tr:hover { background: #f8fbff; }
    .log-type-badge { display: inline-flex; align-items: center; gap: .35rem; border-radius: 999px; padding: .3rem .6rem; font-size: .7rem; font-weight: 700; white-space: nowrap; }
    .log-type-badge.replacement { color: #1d4ed8; background: #dbeafe; }
    .log-type-badge.return { color: #92400e; background: #fef3c7; }
    .log-type-badge.sold { color: #166534; background: #dcfce7; }
    .log-type-badge.transfer { color: #6b21a8; background: #f3e8ff; }
    .log-type-badge.other { color: #475569; background: #e2e8f0; }
    .log-detail-title { max-width: 360px; }
    .log-detail-title strong { display: block; overflow: hidden; text-overflow: ellipsis; }
    .log-view-button { border-radius: 999px; }
    .inventory-log-modal .modal-header { background: linear-gradient(135deg, #eff6ff, #ffffff); border-bottom: 1px solid #dbeafe; }
    .inventory-log-modal .modal-title { color: #1e3a8a; font-weight: 700; }
    .inventory-log-modal .modal-body { background: #f8fafc; }
    .inventory-log-modal .modal-footer { background: #fff; border-top: 1px solid #e2e8f0; }
    .inventory-log-modal .modal-body > .row { background: transparent; }
    .inventory-log-modal .modal-body > .row > [class*="col-"] > strong { display: block; color: #64748b; font-size: .7rem; letter-spacing: .05em; text-transform: uppercase; }
    .inventory-log-modal .modal-body > .row > [class*="col-"] > strong + div { margin-top: .2rem; font-weight: 600; }
    .sold-detail-hero { border: 1px solid #bbf7d0; border-radius: 16px; padding: 1.15rem; background: linear-gradient(135deg, #f0fdf4, #ffffff); }
    .sold-detail-hero .eyebrow { color: #15803d; font-size: .7rem; font-weight: 800; letter-spacing: .08em; text-transform: uppercase; }
    .sold-detail-hero h4 { color: #14532d; font-weight: 800; }
    .sold-detail-order { color: #166534; font-size: .82rem; }
    .sold-detail-kpi { height: 100%; border: 1px solid #e2e8f0; border-radius: 12px; padding: .85rem 1rem; background: #fff; }
    .sold-detail-kpi .label { color: #64748b; font-size: .7rem; font-weight: 800; letter-spacing: .06em; text-transform: uppercase; }
    .sold-detail-kpi .value { color: #0f172a; font-size: 1.35rem; font-weight: 800; line-height: 1.2; }
    .sold-detail-section { border: 1px solid #e2e8f0; border-radius: 14px; background: #fff; overflow: hidden; }
    .sold-detail-section-header { display: flex; align-items: center; justify-content: space-between; gap: .75rem; padding: .8rem 1rem; background: #f8fafc; border-bottom: 1px solid #e2e8f0; }
    .sold-detail-section-title { color: #334155; font-size: .72rem; font-weight: 800; letter-spacing: .07em; text-transform: uppercase; }
    .sold-detail-item { display: flex; align-items: center; justify-content: space-between; gap: 1rem; padding: .85rem 1rem; }
    .sold-detail-item + .sold-detail-item { border-top: 1px solid #eef2f7; }
    .sold-detail-item-name { color: #1e293b; font-weight: 700; }
    .sold-detail-item-id { color: #64748b; font-size: .78rem; margin-top: .15rem; }
    .sold-detail-qty { min-width: 72px; color: #166534; font-weight: 800; text-align: right; }
    .sold-review-card { border-radius: 12px; padding: .9rem 1rem; background: #f8fafc; border: 1px solid #e2e8f0; }
    .sold-review-card.approved { border-color: #bbf7d0; background: #f0fdf4; }
    .sold-review-card.pending { border-color: #cbd5e1; background: #f8fafc; }
    .sold-replacement-item { display: flex; justify-content: space-between; gap: 1rem; padding: .8rem 1rem; }
    .sold-replacement-item + .sold-replacement-item { border-top: 1px solid #eef2f7; }
    .sold-activity-context { border-top: 1px dashed #cbd5e1; padding-top: 1rem; }
    .transfer-detail-hero { border: 1px solid #ddd6fe; border-radius: 16px; padding: 1.15rem; background: linear-gradient(135deg, #f5f3ff, #ffffff); }
    .transfer-detail-hero .eyebrow { color: #6d28d9; font-size: .7rem; font-weight: 800; letter-spacing: .08em; text-transform: uppercase; }
    .transfer-detail-hero h4 { color: #4c1d95; font-weight: 800; }
    .transfer-detail-reference { color: #6d28d9; font-size: .82rem; }
    .transfer-detail-kpi { height: 100%; border: 1px solid #e2e8f0; border-radius: 12px; padding: .85rem 1rem; background: #fff; }
    .transfer-detail-kpi .label { color: #64748b; font-size: .7rem; font-weight: 800; letter-spacing: .06em; text-transform: uppercase; }
    .transfer-detail-kpi .value { color: #0f172a; font-size: 1.35rem; font-weight: 800; line-height: 1.2; }
    .transfer-detail-route { height: 100%; border: 1px solid #c4b5fd; border-radius: 12px; padding: .85rem 1rem; background: #faf5ff; }
    .transfer-detail-route .label { color: #6d28d9; font-size: .7rem; font-weight: 800; letter-spacing: .06em; text-transform: uppercase; }
    .transfer-detail-route .route { display: flex; align-items: center; gap: .65rem; margin-top: .45rem; color: #4c1d95; font-weight: 700; }
    .transfer-detail-route .route span { min-width: 0; }
    .transfer-detail-route .route i { color: #8b5cf6; flex: 0 0 auto; }
    .transfer-detail-section { border: 1px solid #e2e8f0; border-radius: 14px; background: #fff; overflow: hidden; }
    .transfer-detail-section-header { display: flex; align-items: center; justify-content: space-between; gap: .75rem; padding: .8rem 1rem; background: #f8fafc; border-bottom: 1px solid #e2e8f0; }
    .transfer-detail-section-title { color: #334155; font-size: .72rem; font-weight: 800; letter-spacing: .07em; text-transform: uppercase; }
    .transfer-detail-item { display: flex; align-items: center; justify-content: space-between; gap: 1rem; padding: .85rem 1rem; }
    .transfer-detail-item + .transfer-detail-item { border-top: 1px solid #eef2f7; }
    .transfer-detail-item-name { color: #1e293b; font-weight: 700; }
    .transfer-detail-item-id { color: #64748b; font-size: .78rem; margin-top: .15rem; }
    .transfer-detail-qty { min-width: 90px; color: #6d28d9; font-weight: 800; text-align: right; }
    .transfer-activity-context { border-top: 1px dashed #cbd5e1; padding-top: 1rem; }
</style>

<div class="p-5">
    <x-page-header class="mb-4" eyebrow="Inventory workspace" title="Inventory Transaction Logs" :description="auth()->user()?->role === 'sales_associate' ? 'Branch-to-branch stock transfers for '.($hubs->first()?->name ?? 'your designated branch').'.' : 'Monthly item movement for sold items, transfers, events, Fully Booked orders, restocks, and returns.'" icon="fa-clipboard-list" />

    @can('manage-inventory')
        @php
            $inventoryActions = [
                ['route' => 'inventory-transactions.transfer.create', 'icon' => 'fa-truck-ramp-box', 'title' => 'Stock Transfer', 'text' => 'HO ↔ Branch'],
                ['route' => 'inventory-transactions.sponsor.create', 'icon' => 'fa-people-group', 'title' => 'Event / Fully Booked', 'text' => 'Events and orders', 'pending' => $fullyBookedPulloutCount],
                ['route' => 'inventory-transactions.restock.create', 'icon' => 'fa-boxes-stacked', 'title' => 'Restock / Added from Request (Warehouse HO)', 'text' => 'Warehouse request'],
                ['route' => 'inventory-transactions.return.create', 'icon' => 'fa-rotate-left', 'title' => 'Return Items', 'text' => 'Good or damaged'],
            ];
            if (auth()->user()->can('manage-inventory') || auth()->user()->can('submit-branch-transfers')) {
                array_splice($inventoryActions, 1, 0, [[
                    'route' => 'inventory-transactions.branch-transfer.create',
                    'icon' => 'fa-right-left',
                    'title' => 'Stock Transfer',
                    'text' => 'BRANCH to BRANCH',
                ]]);
            }
        @endphp
        <div class="row row-cols-1 row-cols-md-2 row-cols-xl-{{ count($inventoryActions) }} g-3 mb-4">
            @foreach($inventoryActions as $action)
                <div class="col"><a href="{{ route($action['route'], ['hub_id' => $hubId]) }}" class="card border-0 shadow-sm rounded-4 p-3 text-decoration-none h-100 position-relative">
                    <i class="fa-solid {{ $action['icon'] }} text-primary fs-4 mb-2"></i><strong class="text-dark">{{ $action['title'] }}</strong><small class="text-muted">{{ $action['text'] }}</small>
                    @if(($action['pending'] ?? 0) > 0)
                        <span class="badge rounded-pill text-bg-danger position-absolute top-0 end-0 m-3" aria-label="{{ $action['pending'] }} Fully Booked {{ $action['pending'] === 1 ? 'order' : 'orders' }} not yet processed">{{ $action['pending'] }}</span>
                    @endif
                </a></div>
            @endforeach
        </div>
    @endcan

    @can('submit-branch-transfers')
        @cannot('manage-inventory')
            <div class="row row-cols-1 row-cols-md-2 g-3 mb-4">
                <div class="col"><a href="{{ route('inventory-transactions.branch-transfer.create', ['hub_id' => $hubId]) }}" class="card border-0 shadow-sm rounded-4 p-3 text-decoration-none h-100">
                    <i class="fa-solid fa-right-left text-primary fs-4 mb-2"></i><strong class="text-dark">Stock Transfer</strong><small class="text-muted">BRANCH to BRANCH</small>
                </a></div>
            </div>
        @endcannot
    @endcan

    <div class="card log-filter-card shadow-sm rounded-4 p-3 mb-4">
        <form class="row g-2 align-items-end">
            <div class="col-md-3"><label class="small fw-bold text-muted">STORE HUB</label>
                <select name="hub_id" class="form-select"><option value="">All stores</option>
                    @foreach($hubs as $hub)<option value="{{ $hub->id }}" @selected((int) $hubId === $hub->id)>{{ $hub->name }}</option>@endforeach
                </select>
            </div>
            <div class="col-md-3"><label class="small fw-bold text-muted">LOG TYPE</label>
                <select name="type" class="form-select">
                    @if(auth()->user()?->role !== 'sales_associate')<option value="">All types</option>@endif
                    @php
                        $logTypes = auth()->user()?->role === 'sales_associate'
                            ? []
                            : ['sold'=>'Sold Items','stock_transfer'=>'Stock Transfer (HO ↔ Branch)','sponsor_workshop'=>'Event','fully_booked'=>'Fully Booked Orders','restock'=>'Restock / Added from Request (Warehouse HO)','return'=>'Return Items','replacement'=>'Replacement'];
                    @endphp
                    @foreach($logTypes as $value => $label)
                        <option value="{{ $value }}" @selected(request('type') === $value)>{{ $label }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-2"><label class="small fw-bold text-muted">TRANSFER STATUS</label>
                <select name="status" class="form-select">
                    <option value="">All statuses</option>
                    @foreach(['pending' => 'Pending review', 'approved' => 'Approved', 'rejected' => 'Rejected', 'reviewed' => 'Reviewed'] as $value => $label)
                        <option value="{{ $value }}" @selected(request('status') === $value)>{{ $label }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-2"><label class="small fw-bold text-muted">MONTH</label><input type="month" name="month" value="{{ request('month') }}" class="form-control"></div>
            <div class="col-md-2"><button class="btn btn-primary w-100"><i class="fa-solid fa-filter me-1"></i>Filter logs</button></div>
        </form>
    </div>

    <div class="card log-table-card shadow-sm rounded-4 overflow-hidden">
        <div class="table-responsive"><table class="table table-hover align-middle mb-0 inventory-log-table">
            <thead class="table-light small text-uppercase"><tr><th>Date</th><th>Type</th><th>Transaction Details</th><th>Hub</th><th>Channel / Source</th><th class="text-end">Qty</th><th>Transfer</th><th>User</th><th>Actions</th></tr></thead>
            <tbody>
            @forelse($transactions as $groupKey => $group)
                @php
                    $transaction = $group->first();
                    $fullyBookedOrder = $transaction->relationLoaded('fullyBookedOrder')
                        ? $transaction->getRelation('fullyBookedOrder')
                        : null;
                    $isFullyBookedGroup = $transaction->type === 'fully_booked'
                        || ($transaction->type === 'sponsor_workshop' && $transaction->channel === 'fully_booked' && $fullyBookedOrder);
                    $fullyBookedOrder = $isFullyBookedGroup ? $fullyBookedOrder : null;
                    $isReturnGroup = $transaction->type === 'return' && $transaction->salesTransaction;
                    $fullyBookedReturnOrder = $isReturnGroup && $transaction->salesTransaction?->channel_type === 'fully_booked'
                        ? $transaction->getRelation('fullyBookedOrder')
                        : null;
                    $canPreviewFullyBookedReturn = $fullyBookedReturnOrder && (
                        in_array(auth()->user()?->role, ['admin', 'inventory_staff'], true)
                        || (auth()->user()?->role === 'sales_marketing_staff'
                            && auth()->user()->hasSalesChannel('fully_booked')
                            && ((int) $fullyBookedReturnOrder->submitted_by === (int) auth()->id()
                                || (int) $fullyBookedReturnOrder->sales_staff_id === (int) auth()->id()))
                    );
                    $isSoldOrder = $transaction->type === 'sold' && $transaction->salesTransaction;
                    $isTransferGroup = in_array($transaction->type, ['stock_transfer', 'branch_transfer'], true);
                    $isSponsorGroup = $transaction->type === 'sponsor_workshop';
                    $isRestockGroup = $transaction->type === 'restock';
                    $isReplacementGroup = $transaction->productReplacement !== null && ! $isReturnGroup;
                    $typeLabel = match (true) {
                        $isFullyBookedGroup => 'Fully Booked',
                        $isReturnGroup => 'Return',
                        $isReplacementGroup => 'Replacement',
                        $isTransferGroup => $transaction->type === 'branch_transfer'
                            ? 'Stock Transfer (BRANCH to BRANCH)'
                            : ($transaction->sourceHub?->is_head_office ? 'Stock Transfer (HO to BRANCH)' : 'Stock Transfer (BRANCH to HO)'),
                        $isSponsorGroup => 'Event',
                        default => ucwords(str_replace('_', ' ', $transaction->type)),
                    };
                    $modalId = 'transactionModal'.str_replace(['-', ' '], '', $groupKey);
                    $displayUser = $isSoldOrder ? $transaction->salesTransaction?->user : $transaction->creator;
                    $sale = $transaction->salesTransaction;
                    $replacementReviews = $sale?->items
                        ? $sale->items->flatMap->replacements->filter(fn ($replacement) => $replacement->status !== 'pending')
                        : collect();
                @endphp
                <tr>
                    <td>{{ $transaction->occurred_on->format('M d, Y') }}</td>
                    @php
                        $typeClass = $isReplacementGroup ? 'replacement' : ($isReturnGroup ? 'return' : ($isSoldOrder ? 'sold' : ($isTransferGroup ? 'transfer' : 'other')));
                        $typeIcon = $isFullyBookedGroup ? 'fa-file-circle-check' : ($isReplacementGroup ? 'fa-arrow-right-arrow-left' : ($isReturnGroup ? 'fa-rotate-left' : ($isSoldOrder ? 'fa-cart-shopping' : ($isTransferGroup ? 'fa-truck-ramp-box' : 'fa-box'))));
                    @endphp
                    <td><span class="log-type-badge {{ $typeClass }}"><i class="fa-solid {{ $typeIcon }}"></i>{{ $typeLabel }}</span>
                        @if($transaction->type === 'branch_transfer')
                            <span class="badge mt-1 d-block {{ $transaction->status === 'approved' ? 'text-bg-success' : ($transaction->status === 'rejected' ? 'text-bg-danger' : 'text-bg-warning') }}">{{ strtoupper($transaction->status ?? 'approved') }}</span>
                        @elseif($isFullyBookedGroup && $fullyBookedOrder?->status === 'rejected')
                            <span class="badge mt-1 d-block text-bg-danger">REJECTED</span>
                        @endif
                    </td>
                    <td class="log-detail-title">
                        @if($isFullyBookedGroup)
                            <strong>{{ $fullyBookedOrder->order_number }}</strong>
                            <small class="d-block text-muted">{{ $fullyBookedOrder->salesStaff?->name ?? 'Deleted staff' }} · {{ $fullyBookedOrder->status === 'rejected' ? 'Order rejected' : ($fullyBookedOrder->pulled_out_at ? $group->count().' item line(s) · '.$group->sum('quantity').' total pulled out' : 'Attachment submitted for review') }}</small>
                        @elseif($isReplacementGroup)
                            <strong>{{ $transaction->productReplacement->originalProduct?->name ?? 'Original item' }} → {{ $transaction->productReplacement->replacementProduct?->name ?? 'Replacement item' }}</strong>
                            <small class="d-block text-muted">Returned {{ $transaction->productReplacement->quantity }} · Replaced with {{ $transaction->productReplacement->replacement_quantity ?: $transaction->productReplacement->quantity }}</small>
                        @elseif($isReturnGroup)
                            <strong>Return order {{ $transaction->salesTransaction?->order_number ?? $transaction->reference ?? '—' }}</strong>
                            <small class="d-block text-muted">
                                {{ $transaction->salesTransaction?->customer_name ?? 'Customer —' }}
                                · {{ $group->where('condition', 'good')->sum('quantity') }} good
                                · {{ $group->where('condition', 'damaged')->sum('quantity') }} damaged
                                @if($group->sum('refund_amount') > 0) · Refund ₱{{ number_format($group->sum('refund_amount'), 2) }} @endif
                            </small>
                        @elseif($isSoldOrder)
                            <strong>Sold order {{ $transaction->salesTransaction?->order_number ?? $transaction->reference ?? '—' }}</strong>
                            <small class="d-block text-muted">{{ $group->count() }} item line(s) · {{ $group->sum('quantity') }} total sold</small>
                        @elseif($isSponsorGroup)
                            <strong>{{ $transaction->reference ?: '—' }}</strong>
                            <small class="d-block text-muted">{{ $group->count() }} item line(s) · {{ $group->sum('quantity') }} total pulled out{{ $transaction->source ? ' · '.$transaction->source : '' }}</small>
                        @elseif($isRestockGroup)
                            <strong>{{ $transaction->reference ?: 'Restock' }}</strong>
                            <small class="d-block text-muted">{{ $group->count() }} item line(s) · {{ $group->sum('quantity') }} total added{{ $transaction->source ? ' · '.ucwords(str_replace('_', ' ', $transaction->source)) : '' }}</small>
                        @else
                            <strong>{{ $transaction->product?->name ?? 'Unknown product' }}</strong>
                            <small class="d-block text-muted">{{ $transaction->product?->item_id ?? '—' }}</small>
                        @endif
                    </td>
                    <td>{{ $transaction->storeHub?->name ?? '—' }}</td>
                    <td>{{ $transaction->channel ? ucwords(str_replace('_', ' ', $transaction->channel)) : ($transaction->source ?: '—') }}</td>
                    <td class="text-end fw-bold">{{ $isFullyBookedGroup && ! $fullyBookedOrder->pulled_out_at ? '—' : $group->sum('quantity') }}</td>
                    <td>{{ $transaction->sourceHub?->name }}{{ $transaction->targetHub ? ' → '.$transaction->targetHub->name : '' }}</td>
                    <td>{{ $displayUser?->name ?? $displayUser?->username ?? 'System' }}</td>
                    <td><button type="button" class="btn btn-sm btn-outline-primary log-view-button" data-bs-toggle="modal" data-bs-target="#{{ $modalId }}"><i class="fa-solid fa-eye me-1"></i>View details</button></td>
                </tr>
                <div class="modal fade" id="{{ $modalId }}" tabindex="-1" aria-hidden="true">
                    <div class="modal-dialog modal-lg modal-dialog-centered"><div class="modal-content inventory-log-modal">
                        <div class="modal-header"><h5 class="modal-title">{{ $isFullyBookedGroup ? 'Fully Booked Order Details' : ($isReplacementGroup ? 'Replacement Details' : ($isReturnGroup ? 'Return Order Details' : ($isSoldOrder ? 'Sold Order Details' : ($isTransferGroup ? $typeLabel.' Details' : $typeLabel.' Details')))) }}</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
                        <div class="modal-body"><div class="row g-3">
                            @if($isFullyBookedGroup)
                                <div class="col-md-6"><strong class="small text-muted">Fully Booked Order Number</strong><div class="fw-semibold">{{ $fullyBookedOrder?->order_number ?? $transaction->reference }}</div></div>
                                <div class="col-md-6"><strong class="small text-muted">Sales / Marketing Staff</strong><div class="fw-semibold">{{ $fullyBookedOrder?->salesStaff?->name ?? 'Deleted staff' }}</div></div>
                                <div class="col-md-6"><strong class="small text-muted">Submitted By</strong><div class="fw-semibold">{{ $fullyBookedOrder?->submitter?->name ?? 'Deleted user' }}</div></div>
                                <div class="col-md-6"><strong class="small text-muted">Store Hub</strong><div class="fw-semibold">{{ $fullyBookedOrder?->storeHub?->name ?? '—' }}</div></div>
                                <div class="col-md-6"><strong class="small text-muted">Submitted</strong><div class="fw-semibold">{{ $fullyBookedOrder?->created_at?->format('M d, Y h:i A') }}</div></div>
                                @if($fullyBookedOrder?->status === 'rejected')
                                    <div class="col-md-6"><strong class="small text-muted">Rejected By</strong><div class="fw-semibold">{{ $fullyBookedOrder->reviewer?->name ?? 'Inventory staff' }}</div></div>
                                    <div class="col-md-6"><strong class="small text-muted">Rejected At</strong><div class="fw-semibold">{{ $fullyBookedOrder->reviewed_at?->format('M d, Y h:i A') ?? '—' }}</div></div>
                                    <div class="col-12"><strong class="small text-muted">Rejection Reason</strong><div class="fw-semibold text-danger" style="white-space:pre-wrap">{{ $fullyBookedOrder->rejection_reason ?: '—' }}</div></div>
                                @endif
                                @if($fullyBookedOrder?->remarks)
                                    <div class="col-12"><strong class="small text-muted">Remarks</strong><div class="fw-semibold" style="white-space:pre-wrap">{{ $fullyBookedOrder->remarks }}</div></div>
                                @endif
                                <div class="col-md-6"><strong class="small text-muted">Attachment</strong><div class="d-flex gap-2">
                                    <a class="btn btn-sm btn-outline-primary" href="{{ route('inventory-transactions.fully-booked.attachment', $fullyBookedOrder) }}" target="_blank" rel="noopener">Preview</a>
                                    <a class="btn btn-sm btn-outline-secondary" href="{{ route('inventory-transactions.fully-booked.attachment', ['fullyBookedOrder' => $fullyBookedOrder, 'download' => 1]) }}">Download</a>
                                </div></div>
                                <div class="col-12">
                                    @if(str_starts_with($fullyBookedOrder?->mime_type ?? '', 'image/'))
                                        <img src="{{ route('inventory-transactions.fully-booked.attachment', $fullyBookedOrder) }}" alt="Attachment for Fully Booked order {{ $fullyBookedOrder->order_number }}" class="fully-booked-preview">
                                    @else
                                        <iframe src="{{ route('inventory-transactions.fully-booked.attachment', $fullyBookedOrder) }}" title="Attachment for Fully Booked order {{ $fullyBookedOrder->order_number }}" class="fully-booked-pdf"></iframe>
                                    @endif
                                    <div class="small text-muted mt-2">{{ $fullyBookedOrder?->original_filename }}</div>
                                </div>
                                @if($fullyBookedOrder?->pulled_out_at)
                                    <div class="col-12">
                                    <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-2">
                                        <strong>Items Pulled Out</strong>
                                        <button type="button" class="btn btn-sm btn-outline-success export-fully-booked-details-csv" data-order-number="{{ $fullyBookedOrder->order_number }}"><i class="fa-solid fa-file-csv me-1"></i>Export CSV</button>
                                    </div>
                                    <div class="list-group">
                                        @foreach($fullyBookedOrder->items as $item)
                                            <div class="list-group-item d-flex justify-content-between gap-3 fully-booked-export-item"
                                                data-item-id="{{ $item->item_id }}"
                                                data-description="{{ $item->product?->description ?: $item->product_name }}"
                                                data-barcode="{{ $item->product?->barcode ?? '' }}"
                                                data-stock="{{ $item->product?->stock ?? '' }}"
                                                data-quantity="{{ $item->quantity }}">
                                                <span>{{ $item->product_name }} <small class="text-muted">({{ $item->item_id ?? '—' }})</small></span>
                                                <strong>Qty {{ $item->quantity }}</strong>
                                            </div>
                                            @endforeach
                                        </div>
                                        <div class="small text-muted mt-2">Recorded by {{ $fullyBookedOrder->pullOutBy?->name ?? 'Inventory staff' }} · {{ $fullyBookedOrder->pulled_out_at->format('M d, Y h:i A') }}</div>
                                    </div>
                                @else
                                    <div class="col-12">
                                        <p class="small text-muted mb-0">Record the pull-out from the Event / Fully Booked form after reviewing the attachment.</p>
                                        <a class="btn btn-sm btn-primary mt-2" href="{{ route('inventory-transactions.sponsor.create', ['hub_id' => $fullyBookedOrder->store_hub_id, 'activity_type' => 'fully_booked']) }}">Open Event / Fully Booked</a>
                                    </div>
                                @endif
                            @elseif($isReplacementGroup)
                                @php
                                    $replacement = $transaction->productReplacement;
                                    $channelLabel = ucwords(str_replace(['_', '-'], ' ', (string) ($replacement->transaction?->channel_type ?? $transaction->channel ?? 'sales')));
                                @endphp
                                <div class="col-12"><div class="replacement-detail-hero"><div class="text-uppercase small fw-bold text-primary mb-2"><i class="fa-solid fa-arrow-right-arrow-left me-1"></i>Product exchange <span class="ms-2 text-muted">· {{ $channelLabel }} channel pricing</span></div><div class="row g-3"><div class="col-md-6"><div class="item-card"><div class="label">Original item returned</div><div class="name">{{ $replacement->originalProduct?->name ?? '—' }}</div><div class="small text-muted mt-1">Quantity returned: <strong>{{ $replacement->quantity }}</strong></div><div class="small text-primary mt-2">Original {{ $channelLabel }} price: <strong>₱{{ number_format((float) $replacement->original_unit_price, 2) }}</strong> / unit</div></div></div><div class="col-md-6"><div class="item-card"><div class="label">Replacement item released</div><div class="name">{{ $replacement->replacementProduct?->name ?? '—' }}</div><div class="small text-muted mt-1">Quantity released: <strong>{{ $replacement->replacement_quantity ?: $replacement->quantity }}</strong></div><div class="small text-primary mt-2">Replacement {{ $channelLabel }} price: <strong>₱{{ number_format((float) $replacement->replacement_unit_price, 2) }}</strong> / unit</div></div></div></div></div></div>
                                <div class="col-md-6"><div class="replacement-detail-section"><div class="label">Customer name</div><div class="value">{{ $replacement->transaction?->customer_name ?: '—' }}</div></div></div>
                                <div class="col-md-6"><div class="replacement-detail-section"><div class="label">Order number</div><div class="value">{{ $replacement->transaction?->order_number ?: ($transaction->reference ?: '—') }}</div></div></div>
                                <div class="col-md-4"><div class="replacement-detail-section"><div class="label">Status</div><div class="value status"><span class="badge {{ $replacement->status === 'approved' ? 'bg-success' : ($replacement->status === 'rejected' ? 'bg-danger' : 'bg-warning text-dark') }}">{{ strtoupper($replacement->status) }}</span></div></div></div>
                                <div class="col-md-4"><div class="replacement-detail-section"><div class="label">Reviewed by</div><div class="value">{{ $replacement->reviewer?->name ?? $replacement->reviewer?->username ?? 'Not reviewed' }}</div></div></div>
                                <div class="col-md-4"><div class="replacement-detail-section"><div class="label">Reviewed at</div><div class="value">{{ optional($replacement->reviewed_at)->format('M d, Y h:i A') ?? 'Not reviewed' }}</div></div></div>
                                @if($replacement->rejection_reason)<div class="col-12"><div class="replacement-detail-notes"><strong>Rejection reason</strong><div class="text-danger mt-1">{{ $replacement->rejection_reason }}</div></div></div>@endif
                            @elseif($isReturnGroup)
                                <div class="col-12">
                                    <div class="return-detail-hero rounded-3 p-3 mb-3">
                                        <div class="d-flex flex-wrap justify-content-between align-items-start gap-2">
                                            <div>
                                                <div class="text-uppercase small fw-bold text-primary">Return record</div>
                                                <h5 class="mb-1">{{ $transaction->salesTransaction?->customer_name ?? 'Customer —' }}</h5>
                                                <div class="return-detail-meta">Order {{ $transaction->salesTransaction?->order_number ?? $transaction->reference ?? '—' }}</div>
                                            </div>
                                            <span class="badge text-bg-info">RETURN ITEMS</span>
                                        </div>
                                    </div>
                                    @php
                                        $returnChannel = strtolower(str_replace(['-', ' '], '_', (string) ($transaction->salesTransaction?->channel_type ?? $transaction->channel ?? '')));
                                        $showReturnRefund = ! $isFullyBookedGroup
                                            && ! in_array($returnChannel, ['shopee', 'lazada', 'tiktok', 'fully_booked'], true);
                                    @endphp
                                    <div class="row g-2 mb-3">
                                        <div class="{{ $showReturnRefund ? 'col-md-4' : 'col-md-6' }}"><div class="return-detail-kpi good"><div class="small text-muted">Good quantity</div><div class="value">{{ $group->where('condition', 'good')->sum('quantity') }}</div></div></div>
                                        <div class="{{ $showReturnRefund ? 'col-md-4' : 'col-md-6' }}"><div class="return-detail-kpi damaged"><div class="small text-muted">Damaged quantity</div><div class="value">{{ $group->where('condition', 'damaged')->sum('quantity') }}</div></div></div>
                                        @if($showReturnRefund)
                                            <div class="col-md-4"><div class="return-detail-kpi refund"><div class="small text-muted">Refund total</div><div class="value">₱{{ number_format($group->sum('refund_amount'), 2) }}</div></div></div>
                                        @endif
                                    </div>
                                </div>
                                @if($canPreviewFullyBookedReturn)
                                    <div class="col-12">
                                        <div class="transfer-detail-section">
                                            <div class="transfer-detail-section-header"><span class="transfer-detail-section-title"><i class="fa-solid fa-paperclip me-2 text-primary"></i>Fully Booked attachment</span></div>
                                            <a class="btn btn-sm btn-outline-primary mb-3" href="{{ route('inventory-transactions.fully-booked.attachment', $fullyBookedReturnOrder) }}" target="_blank" rel="noopener">Open attachment</a>
                                            @if(str_starts_with($fullyBookedReturnOrder->mime_type ?? '', 'image/'))
                                                <img src="{{ route('inventory-transactions.fully-booked.attachment', $fullyBookedReturnOrder) }}" alt="Attachment for Fully Booked order {{ $fullyBookedReturnOrder->order_number }}" class="fully-booked-preview">
                                            @else
                                                <iframe src="{{ route('inventory-transactions.fully-booked.attachment', $fullyBookedReturnOrder) }}" title="Attachment for Fully Booked order {{ $fullyBookedReturnOrder->order_number }}" class="fully-booked-pdf"></iframe>
                                            @endif
                                            <div class="small text-muted mt-2">{{ $fullyBookedReturnOrder->original_filename }}</div>
                                        </div>
                                    </div>
                                @endif
                                <div class="col-12"><strong class="d-block mb-2">Returned items</strong><div class="return-detail-items">
                                    @foreach($group as $entry)
                                        <div class="return-detail-item d-flex justify-content-between align-items-center gap-3">
                                            <div><strong>{{ $entry->product?->name ?? 'Unknown product' }}</strong><div class="return-detail-meta">Item ID: {{ $entry->product?->item_id ?? '—' }}</div></div>
                                            <div class="text-end">
                                                <span class="badge {{ $entry->condition === 'good' ? 'text-bg-success' : ($entry->condition === 'damaged' ? 'text-bg-danger' : 'text-bg-secondary') }}">{{ strtoupper($entry->condition ?? 'refund only') }}</span>
                                                <div class="small mt-1">Qty <strong>{{ $entry->quantity }}</strong>@if((float) $entry->refund_amount > 0) · Refund <strong>₱{{ number_format($entry->refund_amount, 2) }}</strong>@endif</div>
                                            </div>
                                        </div>
                                    @endforeach
                                </div></div>
                            @elseif($isSoldOrder)
                                <div class="col-12">
                                    <div class="sold-detail-hero">
                                        <div class="d-flex flex-wrap justify-content-between align-items-start gap-3">
                                            <div>
                                                <div class="eyebrow"><i class="fa-solid fa-cart-shopping me-1"></i>Completed sales record</div>
                                                <h4 class="mb-1 mt-2">Sold order</h4>
                                                <div class="sold-detail-order">Order {{ $sale->order_number ?? $transaction->reference ?? '—' }}</div>
                                            </div>
                                            <span class="badge text-bg-success rounded-pill px-3 py-2"><i class="fa-solid fa-check me-1"></i>SOLD</span>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-12">
                                    <div class="row g-2">
                                        <div class="col-md-4"><div class="sold-detail-kpi"><div class="label">Item lines</div><div class="value">{{ $group->count() }}</div></div></div>
                                        <div class="col-md-4"><div class="sold-detail-kpi"><div class="label">Total sold</div><div class="value">{{ $group->sum('quantity') }}</div></div></div>
                                        <div class="col-md-4"><div class="sold-detail-kpi"><div class="label">Sales channel</div><div class="value fs-5">{{ ucwords(str_replace('_', ' ', $transaction->channel ?: $transaction->source ?: '—')) }}</div></div></div>
                                    </div>
                                </div>
                                <div class="col-12">
                                    <div class="sold-detail-section">
                                        <div class="sold-detail-section-header"><span class="sold-detail-section-title"><i class="fa-solid fa-box-open me-2 text-success"></i>Sold items</span><span class="small text-muted">{{ $group->count() }} line(s)</span></div>
                                        @foreach($group as $entry)
                                            @php($soldItem = $sale->items->first(fn ($item) => (int) $item->product_id === (int) $entry->product_id))
                                            <div class="sold-detail-item">
                                                <div><div class="sold-detail-item-name">{{ $entry->product?->name ?? 'Unknown product' }}</div><div class="sold-detail-item-id">Item ID: {{ $entry->product?->item_id ?? '—' }}</div><div class="small text-primary mt-1">Channel price: <strong>₱{{ number_format((float) ($soldItem?->unit_price ?? 0), 2) }}</strong> / unit{{ $soldItem?->line_total !== null ? ' · Line total ₱'.number_format((float) $soldItem->line_total, 2) : '' }}</div></div>
                                                <div class="sold-detail-qty">Qty {{ $entry->quantity }}</div>
                                            </div>
                                        @endforeach
                                    </div>
                                </div>
                                <div class="col-12">
                                    <div class="sold-detail-section">
                                        <div class="sold-detail-section-header"><span class="sold-detail-section-title"><i class="fa-solid fa-clipboard-check me-2 text-primary"></i>Order review</span></div>
                                        <div class="p-3">
                                            @if($sale->confirmedBy)
                                                <div class="sold-review-card approved d-flex flex-wrap justify-content-between align-items-center gap-2">
                                                    <div><span class="badge text-bg-success rounded-pill">APPROVED</span><span class="ms-2 fw-semibold">{{ $sale->confirmedBy->name ?? $sale->confirmedBy->username }}</span></div>
                                                    @if($sale->confirmed_at)<span class="small text-muted">{{ $sale->confirmed_at->format('M d, Y h:i A') }}</span>@endif
                                                </div>
                                            @else
                                                <div class="sold-review-card pending"><span class="badge text-bg-secondary rounded-pill">NOT REVIEWED</span><span class="small text-muted ms-2">This order has not been reviewed yet.</span></div>
                                            @endif
                                        </div>
                                    </div>
                                </div>
                                @if($replacementReviews->isNotEmpty())
                                    <div class="col-12"><div class="sold-detail-section"><div class="sold-detail-section-header"><span class="sold-detail-section-title"><i class="fa-solid fa-arrow-right-arrow-left me-2 text-primary"></i>Replacement reviews</span><span class="small text-muted">{{ $replacementReviews->count() }} review(s)</span></div>
                                        @foreach($replacementReviews as $replacement)
                                            <div class="sold-replacement-item"><div><div class="fw-semibold">{{ $replacement->originalProduct?->name ?? 'Original item' }} <span class="text-muted">→</span> {{ $replacement->replacementProduct?->name ?? 'Replacement item' }}</div><div class="small text-muted mt-1">Reviewer: {{ $replacement->reviewer?->name ?? $replacement->reviewer?->username ?? 'Unknown reviewer' }}</div></div><span class="badge {{ $replacement->status === 'approved' ? 'text-bg-success' : 'text-bg-danger' }} align-self-start">{{ strtoupper($replacement->status) }}</span></div>
                                        @endforeach
                                    </div></div>
                                @endif
                            @elseif($isSponsorGroup)
                                <div class="col-12">
                                    <div class="transfer-detail-hero">
                                        <div class="eyebrow"><i class="fa-solid fa-people-group me-1"></i>Event record</div>
                                        <h4 class="mb-1 mt-2">{{ $transaction->source ?: 'Event or recipient not specified' }}</h4>
                                        <div class="transfer-detail-reference">Reference {{ $transaction->reference ?? '—' }}</div>
                                    </div>
                                </div>
                                <div class="col-12">
                                    <div class="transfer-detail-section">
                                        <div class="transfer-detail-section-header"><span class="transfer-detail-section-title"><i class="fa-solid fa-boxes-stacked me-2" style="color:#6d28d9;"></i>Items pulled out</span><span class="small text-muted">{{ $group->count() }} line(s) · {{ $group->sum('quantity') }} total</span></div>
                                        @foreach($group as $entry)
                                            <div class="transfer-detail-item">
                                                <div><div class="transfer-detail-item-name">{{ $entry->product?->name ?? 'Unknown product' }}</div><div class="transfer-detail-item-id">Item ID: {{ $entry->product?->item_id ?? '—' }}</div></div>
                                                <div class="transfer-detail-qty">Qty {{ $entry->quantity }}</div>
                                            </div>
                                        @endforeach
                                    </div>
                                </div>
                            @elseif($isTransferGroup)
                                <div class="col-12">
                                    <div class="transfer-detail-hero">
                                        <div class="d-flex flex-wrap justify-content-between align-items-start gap-3">
                                            <div>
                                                <div class="eyebrow"><i class="fa-solid fa-truck-ramp-box me-1"></i>Inventory movement record</div>
                                                <h4 class="mb-1 mt-2">{{ $transaction->type === 'branch_transfer' ? 'Branch-to-branch transfer' : 'Stock transfer' }}</h4>
                                                <div class="transfer-detail-reference">Reference {{ $transaction->reference ?? '—' }}</div>
                                            </div>
                                            <span class="badge rounded-pill px-3 py-2 {{ $transaction->status === 'approved' ? 'text-bg-success' : ($transaction->status === 'rejected' ? 'text-bg-danger' : 'text-bg-warning') }}"><i class="fa-solid {{ $transaction->status === 'approved' ? 'fa-check' : ($transaction->status === 'rejected' ? 'fa-xmark' : 'fa-hourglass-half') }} me-1"></i>{{ strtoupper($transaction->status ?? 'approved') }}</span>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-12">
                                    <div class="row g-2">
                                        <div class="col-md-4"><div class="transfer-detail-kpi"><div class="label">Item lines</div><div class="value">{{ $group->count() }}</div></div></div>
                                        <div class="col-md-4"><div class="transfer-detail-kpi"><div class="label">Total quantity</div><div class="value">{{ $group->sum('quantity') }}</div></div></div>
                                        <div class="col-md-4"><div class="transfer-detail-kpi"><div class="label">Recorded by</div><div class="value fs-5">{{ $displayUser?->name ?? $displayUser?->username ?? 'System' }}</div></div></div>
                                    </div>
                                </div>
                                <div class="col-12">
                                    <div class="transfer-detail-route">
                                        <div class="label"><i class="fa-solid fa-route me-1"></i>Transfer route</div>
                                        <div class="route">
                                            <span class="text-truncate">{{ $transaction->sourceHub?->name ?? 'Source hub —' }}</span>
                                            <i class="fa-solid fa-arrow-right"></i>
                                            <span class="text-truncate">{{ $transaction->targetHub?->name ?? 'Destination hub —' }}</span>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-12">
                                    <div class="transfer-detail-section">
                                        <div class="transfer-detail-section-header"><span class="transfer-detail-section-title"><i class="fa-solid fa-boxes-stacked me-2" style="color:#6d28d9;"></i>{{ $transaction->status === 'pending' ? 'Requested items' : 'Transferred items' }}</span><span class="small text-muted">{{ $group->count() }} line(s)</span></div>
                                        @foreach($group as $entry)
                                            <div class="transfer-detail-item">
                                                <div><div class="transfer-detail-item-name">{{ $entry->product?->name ?? 'Unknown product' }}</div><div class="transfer-detail-item-id">Item ID: {{ $entry->product?->item_id ?? '—' }}</div></div>
                                                <div class="transfer-detail-qty">Qty {{ $entry->quantity }}</div>
                                            </div>
                                        @endforeach
                                    </div>
                                </div>
                                @if($transaction->type === 'branch_transfer')
                                    <div class="col-md-6"><div class="transfer-detail-section p-3"><strong class="d-block">Reviewed by</strong><div>{{ $transaction->reviewer?->name ?? $transaction->reviewer?->username ?? 'Pending review' }}</div></div></div>
                                    <div class="col-md-6"><div class="transfer-detail-section p-3"><strong class="d-block">Reviewed at</strong><div>{{ $transaction->reviewed_at?->format('M d, Y h:i A') ?? 'Pending review' }}</div></div></div>
                                    @if($transaction->rejection_reason)
                                        <div class="col-12"><div class="alert alert-danger mb-0"><strong>Rejection reason</strong><div>{{ $transaction->rejection_reason }}</div></div></div>
                                    @endif
                                    @if($transaction->status === 'pending' && auth()->user()?->can('verify-inventory') && (int) $transaction->created_by !== (int) auth()->id())
                                        <div class="col-12 border-top pt-3 mt-2">
                                            <div class="fw-bold mb-2">Review transfer request</div>
                                            <div class="d-flex flex-wrap align-items-end gap-2">
                                                <form method="POST" action="{{ route('inventory-transactions.branch-transfer.review', ['batch' => $transaction->transfer_batch_id]) }}">
                                                    @csrf
                                                    <input type="hidden" name="decision" value="approved">
                                                    <button class="btn btn-success"><i class="fa-solid fa-check me-1"></i>Approve and transfer stock</button>
                                                </form>
                                                <form method="POST" action="{{ route('inventory-transactions.branch-transfer.review', ['batch' => $transaction->transfer_batch_id]) }}" class="d-flex flex-wrap align-items-end gap-2">
                                                    @csrf
                                                    <input type="hidden" name="decision" value="rejected">
                                                    <div><label class="form-label small mb-1">Rejection reason</label><input name="rejection_reason" class="form-control" maxlength="2000" required></div>
                                                    <button class="btn btn-outline-danger"><i class="fa-solid fa-xmark me-1"></i>Reject request</button>
                                                </form>
                                            </div>
                                        </div>
                                    @endif
                                @endif
                            @elseif($isRestockGroup)
                                <div class="col-12">
                                    <div class="transfer-detail-hero">
                                        <div class="eyebrow"><i class="fa-solid fa-boxes-stacked me-1"></i>Restock / Added from Request (Warehouse HO)</div>
                                        <h4 class="mb-1 mt-2">{{ $transaction->reference ?: 'Restock record' }}</h4>
                                    </div>
                                </div>
                                <div class="col-12">
                                    <div class="transfer-detail-section">
                                        <div class="transfer-detail-section-header"><span class="transfer-detail-section-title"><i class="fa-solid fa-box-open me-2 text-primary"></i>Items added</span><span class="small text-muted">{{ $group->count() }} line(s) · {{ $group->sum('quantity') }} total</span></div>
                                        @foreach($group as $entry)
                                            <div class="transfer-detail-item">
                                                <div><div class="transfer-detail-item-name">{{ $entry->product?->name ?? 'Unknown product' }}</div><div class="transfer-detail-item-id">Item ID: {{ $entry->product?->item_id ?? '—' }}</div></div>
                                                <div class="transfer-detail-qty">Qty {{ $entry->quantity }}</div>
                                            </div>
                                        @endforeach
                                    </div>
                                </div>
                            @else
                                <div class="col-md-6"><strong>Product</strong><div>{{ $transaction->product?->name ?? 'Unknown product' }} ({{ $transaction->product?->item_id ?? '—' }})</div></div>
                                <div class="col-md-3"><strong>Quantity</strong><div>{{ $transaction->quantity }}</div></div>
                            @endif
                            @if(!$isReplacementGroup && !$isFullyBookedGroup)
                                @if($isSponsorGroup)
                                    <div class="col-12">
                                        <div class="row g-2">
                                            <div class="col-md-6"><div class="transfer-detail-section p-3 h-100"><div class="small text-muted mb-1">Date</div><strong>{{ $transaction->occurred_on->format('M d, Y') }}</strong></div></div>
                                            <div class="col-md-6"><div class="transfer-detail-section p-3 h-100"><div class="small text-muted mb-1">Recorded by</div><strong>{{ $displayUser?->name ?? $displayUser?->username ?? 'System' }}</strong></div></div>
                                            <div class="col-md-6"><div class="transfer-detail-section p-3 h-100"><div class="small text-muted mb-1">Store Hub</div><strong>{{ $transaction->storeHub?->name ?? '—' }}</strong></div></div>
                                            <div class="col-md-6"><div class="transfer-detail-section p-3 h-100"><div class="small text-muted mb-1">Event / Recipient</div><strong>{{ $transaction->source ?: '—' }}</strong></div></div>
                                            @if($transaction->notes)
                                                <div class="col-12"><div class="transfer-detail-section p-3"><div class="small text-muted mb-1">Notes</div><div>{{ $transaction->notes }}</div></div></div>
                                            @endif
                                        </div>
                                    </div>
                                @elseif($isSoldOrder)
                                    <div class="col-12">
                                        <div class="row g-2">
                                            <div class="col-md-6"><div class="transfer-detail-section p-3 h-100"><div class="small text-muted mb-1">Date</div><strong>{{ $transaction->occurred_on->format('M d, Y') }}</strong></div></div>
                                            <div class="col-md-6"><div class="transfer-detail-section p-3 h-100"><div class="small text-muted mb-1">Submitted by</div><strong>{{ $displayUser?->name ?? $displayUser?->username ?? 'System' }}</strong></div></div>
                                            <div class="col-md-6"><div class="transfer-detail-section p-3 h-100"><div class="small text-muted mb-1">Store Hub</div><strong>{{ $transaction->storeHub?->name ?? '—' }}</strong></div></div>
                                            <div class="col-md-6"><div class="transfer-detail-section p-3 h-100"><div class="small text-muted mb-1">Sales Channel</div><strong>{{ ucwords(str_replace('_', ' ', $transaction->channel ?: $transaction->source ?: '—')) }}</strong></div></div>
                                            @if($transaction->notes)
                                                <div class="col-12"><div class="transfer-detail-section p-3"><div class="small text-muted mb-1">Notes</div><div>{{ $transaction->notes }}</div></div></div>
                                            @endif
                                        </div>
                                    </div>
                                @elseif($isReturnGroup)
                                    <div class="col-12">
                                        <div class="text-uppercase small fw-bold text-primary mb-2">Return record</div>
                                        <div class="row g-2">
                                            <div class="col-md-6"><div class="transfer-detail-section p-3 h-100"><div class="small text-muted mb-1">Date</div><strong>{{ $transaction->occurred_on->format('M d, Y') }}</strong></div></div>
                                            <div class="col-md-6"><div class="transfer-detail-section p-3 h-100"><div class="small text-muted mb-1">Recorded by</div><strong>{{ $displayUser?->name ?? $displayUser?->username ?? 'System' }}</strong></div></div>
                                            <div class="col-md-6"><div class="transfer-detail-section p-3 h-100"><div class="small text-muted mb-1">Store Hub</div><strong>{{ $transaction->storeHub?->name ?? '—' }}</strong></div></div>
                                            <div class="col-md-6"><div class="transfer-detail-section p-3 h-100"><div class="small text-muted mb-1">Channel</div><strong>{{ $transaction->channel ? ucwords(str_replace('_', ' ', $transaction->channel)) : '—' }}</strong></div></div>
                                            @if($transaction->notes)
                                                <div class="col-12"><div class="transfer-detail-section p-3"><div class="small text-muted mb-1">Notes</div><div>{{ $transaction->notes }}</div></div></div>
                                            @endif
                                        </div>
                                    </div>
                                @elseif($isRestockGroup)
                                    <div class="col-12">
                                        <div class="row g-2">
                                            <div class="col-md-6"><div class="transfer-detail-section p-3 h-100"><div class="small text-muted mb-1">Date</div><strong>{{ $transaction->occurred_on->format('M d, Y') }}</strong></div></div>
                                            <div class="col-md-6"><div class="transfer-detail-section p-3 h-100"><div class="small text-muted mb-1">Recorded by</div><strong>{{ $displayUser?->name ?? $displayUser?->username ?? 'System' }}</strong></div></div>
                                            <div class="col-md-6"><div class="transfer-detail-section p-3 h-100"><div class="small text-muted mb-1">Receiving Store Hub</div><strong>{{ $transaction->storeHub?->name ?? '—' }}</strong></div></div>
                                            <div class="col-md-6"><div class="transfer-detail-section p-3 h-100"><div class="small text-muted mb-1">Added From</div><strong>{{ $transaction->source ? ucwords(str_replace('_', ' ', $transaction->source)) : '—' }}</strong></div></div>
                                            @if($transaction->sourceHub)
                                                <div class="col-md-6"><div class="transfer-detail-section p-3 h-100"><div class="small text-muted mb-1">Source Store Hub</div><strong>{{ $transaction->sourceHub->name }}</strong></div></div>
                                            @endif
                                            @if($transaction->notes)
                                                <div class="col-12"><div class="transfer-detail-section p-3"><div class="small text-muted mb-1">Notes</div><div>{{ $transaction->notes }}</div></div></div>
                                            @endif
                                        </div>
                                    </div>
                                @elseif($isTransferGroup)
                                    <div class="col-12">
                                        <div class="row g-2">
                                            <div class="col-md-6"><div class="transfer-detail-section p-3 h-100"><div class="small text-muted mb-1">Date</div><strong>{{ $transaction->occurred_on->format('M d, Y') }}</strong></div></div>
                                            <div class="col-md-6"><div class="transfer-detail-section p-3 h-100"><div class="small text-muted mb-1">Recorded by</div><strong>{{ $displayUser?->name ?? $displayUser?->username ?? 'System' }}</strong></div></div>
                                            <div class="col-md-6"><div class="transfer-detail-section p-3 h-100"><div class="small text-muted mb-1">Source Hub</div><strong>{{ $transaction->sourceHub?->name ?? '—' }}</strong></div></div>
                                            <div class="col-md-6"><div class="transfer-detail-section p-3 h-100"><div class="small text-muted mb-1">Destination Hub</div><strong>{{ $transaction->targetHub?->name ?? '—' }}</strong></div></div>
                                            @if($transaction->notes)
                                                <div class="col-12"><div class="transfer-detail-section p-3"><div class="small text-muted mb-1">Notes</div><div>{{ $transaction->notes }}</div></div></div>
                                            @endif
                                        </div>
                                    </div>
                                @else
                                    <div class="col-12 @if($isSoldOrder) sold-activity-context @elseif($isTransferGroup) transfer-activity-context @endif"><div class="row g-3">
                                    <div class="col-md-3"><strong>Date</strong><div>{{ $transaction->occurred_on->format('M d, Y') }}</div></div>
                                    <div class="col-md-6"><strong>Type</strong><div>{{ $typeLabel }}</div></div>
                                    <div class="col-md-6"><strong>{{ $isSoldOrder ? 'Submitted by' : 'Recorded by' }}</strong><div>{{ $displayUser?->name ?? $displayUser?->username ?? 'System' }}</div></div>
                                    <div class="col-md-6"><strong>Hub</strong><div>{{ $transaction->storeHub?->name ?? '—' }}</div></div>
                                    <div class="col-md-6"><strong>Transfer</strong><div>{{ $transaction->sourceHub?->name }}{{ $transaction->targetHub ? ' → '.$transaction->targetHub->name : '—' }}</div></div>
                                    <div class="col-md-6"><strong>Channel / Source</strong><div>{{ $transaction->channel ?: $transaction->source ?: '—' }}</div></div>
                                    @if(!$isReturnGroup)
                                        <div class="col-md-6"><strong>Condition</strong><div>{{ $transaction->condition ? ucfirst($transaction->condition) : '—' }}</div></div>
                                    @endif
                                    <div class="col-12"><strong>Reference / Notes</strong><div>{{ $transaction->reference ?: '—' }}{{ $transaction->notes ? ' — '.$transaction->notes : '' }}</div></div>
                                    </div></div>
                                @endif
                            @endif
                        </div></div>
                    </div></div>
                </div>
            @empty
                <tr><td colspan="9" class="text-center text-muted py-5">No transaction logs found.</td></tr>
            @endforelse
            </tbody>
        </table></div>
        <div class="p-3">{{ $transactions->links() }}</div>
    </div>
</div>
@endsection

@push('scripts')
<script src="{{ asset('js/fully-booked-pullout.js') }}"></script>
@endpush
