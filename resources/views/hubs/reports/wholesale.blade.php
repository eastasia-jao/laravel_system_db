<style>
    .wholesale-report-card { border: 0; border-radius: 16px; overflow: hidden; box-shadow: 0 10px 28px rgba(30, 64, 175, .08); }
    .wholesale-report-card .card-header { background: linear-gradient(135deg, #eff6ff, #f8fafc); border-bottom: 1px solid #dbeafe; padding: 1rem 1.25rem; }
    .wholesale-report-card .table { min-width: 1120px; width: 100%; table-layout: fixed; }
    .wholesale-report-card th:nth-child(1) { width: 25%; }
    .wholesale-report-card th:nth-child(2) { width: 14%; }
    .wholesale-report-card th:nth-child(4), .wholesale-report-card th:nth-child(5) { width: 11%; }
    .wholesale-report-card th:nth-child(6) { width: 13%; }
    .wholesale-report-card th:nth-child(7) { width: 10%; }
    .wholesale-report-card th:nth-child(8) { width: 14%; }
    .wholesale-report-card th:nth-child(9), .wholesale-report-card th:nth-child(11) { width: 10%; }
    .wholesale-report-card thead th { background: #dbeafe; color: #1e3a8a; border-color: #bfdbfe; font-size: .72rem; letter-spacing: .03em; text-transform: uppercase; white-space: nowrap; }
    .wholesale-report-card tbody td { border-color: #e5e7eb; font-size: .82rem; vertical-align: middle; }
    .wholesale-report-card tbody tr:hover { background: #f8fbff; }
    .wholesale-items { min-width: 0; max-width: none; }
    .wholesale-items summary { font-weight: 600; color: #2563eb; cursor: pointer; }
    .wholesale-items .item-line { padding: .45rem 0; border-bottom: 1px solid #e5e7eb; }
    .wholesale-items .item-line:last-child { border-bottom: 0; }
    .wholesale-report-card .wholesale-items > details { display: none; }
    .wholesale-status { min-width: 260px; }
    .wholesale-report-card th:nth-child(3), .wholesale-report-card td:nth-child(3),
    .wholesale-report-card th:nth-child(10), .wholesale-report-card td:nth-child(10),
    .wholesale-report-card th:nth-child(12), .wholesale-report-card td:nth-child(12),
    .wholesale-report-card th:nth-child(13), .wholesale-report-card td:nth-child(13),
    .wholesale-report-card th:nth-child(14), .wholesale-report-card td:nth-child(14),
    .wholesale-report-card th:nth-child(15), .wholesale-report-card td:nth-child(15),
    .wholesale-report-card th:nth-child(16), .wholesale-report-card td:nth-child(16),
    .wholesale-report-card th:nth-child(17), .wholesale-report-card td:nth-child(17) { display: none; }
    .wholesale-report-card .wholesale-month-subtotal td { display: table-cell !important; }
    .wholesale-report-card tbody td { padding: .65rem .6rem; }
    .wholesale-customer-summary { min-width: 0; }
    .wholesale-customer-summary .customer-copy { min-width: 0; }
    .wholesale-customer-summary .wholesale-view-details { flex: 0 0 auto; white-space: nowrap; }
    .wholesale-table-status { display: inline-flex; align-items: center; min-width: 76px; justify-content: center; padding: .32rem .55rem; border-radius: 999px; font-size: .68rem; font-weight: 700; letter-spacing: .02em; }
    #wholesaleDetailsModal { --wholesale-blue: #2563eb; --wholesale-ink: #1f2937; --wholesale-muted: #64748b; --wholesale-border: #e2e8f0; }
    #wholesaleDetailsModal .modal-dialog { width: min(1320px, calc(100vw - 2rem)); max-width: 1320px; }
    #wholesaleDetailsModal .modal-content { max-height: calc(100vh - 2rem); border-radius: 12px; overflow: hidden; }
    #wholesaleDetailsModal .modal-header, #wholesaleDetailsModal .modal-footer { position: sticky; z-index: 5; background: #fff; }
    #wholesaleDetailsModal .modal-header { top: 0; min-height: 76px; padding: .85rem 1.1rem; color: #fff; background: var(--wholesale-blue); border: 0; }
    #wholesaleDetailsModal .modal-footer { bottom: 0; justify-content: space-between; padding: .65rem 1rem; border-top: 1px solid var(--wholesale-border); }
    #wholesaleDetailsModal .modal-body { overflow-y: auto; padding: 1rem; background: #f5f7fa; }
    #wholesaleDetailsModal :focus-visible { outline: 3px solid rgba(37, 99, 235, .45); outline-offset: 2px; }
    .wholesale-modal-title { font-size: 1rem; font-weight: 700; line-height: 1.25; }
    .wholesale-modal-subtitle { margin-top: .2rem; font-size: .82rem; color: rgba(255,255,255,.84); }
    .wholesale-modal-header-main { min-width: 0; }
    .wholesale-modal-header-statuses { display: flex; flex-wrap: wrap; justify-content: flex-end; gap: .4rem; margin-left: auto; padding: 0 .75rem; }
    .wholesale-modal-chip { display: inline-flex; align-items: center; gap: .3rem; padding: .28rem .55rem; border: 1px solid rgba(255,255,255,.25); border-radius: 999px; background: rgba(255,255,255,.15); font-size: .7rem; font-weight: 700; white-space: nowrap; }
    .wholesale-modal-chip.is-success { color: #dcfce7; background: rgba(22,101,52,.25); }
    .wholesale-modal-chip.is-pending { color: #fef3c7; background: rgba(146,64,14,.28); }
    .wholesale-modal-chip.is-danger { color: #fee2e2; background: rgba(153,27,27,.3); }
    .wholesale-detail-grid { display: flex; flex-direction: column; gap: 16px; font-family: Inter, system-ui, -apple-system, "Segoe UI", sans-serif; color: var(--wholesale-ink); }
    .wholesale-info-panel { min-width: 0; overflow: hidden; border: 1px solid var(--wholesale-border); border-radius: 10px; background: #fff; box-shadow: 0 2px 7px rgba(15,23,42,.035); }
    .wholesale-info-panel-title { display: flex; align-items: center; gap: .45rem; margin: 0; padding: .65rem .8rem; border-bottom: 1px solid var(--wholesale-border); color: #334155; background: #fff; font-size: .88rem; font-weight: 650; }
    .wholesale-info-panel-body { padding: 16px; }
    .wholesale-financial-panel .wholesale-info-panel-body { display: grid; grid-template-columns: repeat(4, minmax(0, 1fr)); gap: .6rem; padding: .7rem; }
    .wholesale-kpi { min-width: 0; padding: .65rem .75rem; border: 1px solid var(--wholesale-border); border-radius: 8px; background: #fff; }
    .wholesale-kpi-label, .wholesale-detail-label { display: block; margin-bottom: .3rem; color: var(--wholesale-muted); font-size: .68rem; font-weight: 700; letter-spacing: .045em; text-transform: uppercase; }
    .wholesale-kpi-value { color: #111827; font-size: 1.15rem; font-weight: 750; line-height: 1.2; text-align: right; font-variant-numeric: tabular-nums; }
    .wholesale-kpi-subtitle { margin-top: .2rem; color: var(--wholesale-muted); font-size: .72rem; }
    .wholesale-balance-line { display: flex; align-items: center; justify-content: space-between; gap: .75rem; padding: 0 .8rem .7rem; color: var(--wholesale-muted); font-size: .78rem; }
    .wholesale-balance-line .is-paid { color: #15803d; font-weight: 700; }
    .wholesale-balance-line .is-due { color: #b45309; font-weight: 700; }
    .wholesale-balance-track { height: 4px; margin: 0 .8rem .75rem; overflow: hidden; border-radius: 999px; background: #e5e7eb; }
    .wholesale-balance-track span { display: block; height: 100%; border-radius: inherit; background: #16a34a; }
    .wholesale-detail-main { display: grid; grid-template-columns: 1.6fr 1fr; gap: 16px; align-items: stretch; }
    .wholesale-detail-column { display: flex; flex-direction: column; gap: 16px; min-width: 0; }
    .wholesale-detail-column > .wholesale-info-panel { display: flex; flex: 1 1 auto; flex-direction: column; }
    .wholesale-detail-column > .wholesale-info-panel > .wholesale-info-panel-body { flex: 1 1 auto; }
    .wholesale-detail-kv-grid { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: .65rem .8rem; }
    .wholesale-detail-kv { min-width: 0; padding-bottom: .35rem; border-bottom: 1px solid #f1f5f9; }
    .wholesale-detail-kv:last-child, .wholesale-detail-kv:nth-last-child(2):nth-child(odd) { border-bottom: 0; }
    .wholesale-status-chip { display: inline-flex; align-items: center; gap: .25rem; padding: .2rem .5rem; border-radius: 999px; font-size: .72rem; font-weight: 700; line-height: 1.2; }
    .wholesale-status-chip.is-success { color: #166534; background: #dcfce7; }
    .wholesale-status-chip.is-pending { color: #92400e; background: #fef3c7; }
    .wholesale-status-chip.is-danger { color: #991b1b; background: #fee2e2; }
    .wholesale-status-chip.is-info { color: #1e40af; background: #dbeafe; }
    .wholesale-detail-value { overflow-wrap: anywhere; color: #1f2937; font-size: .86rem; font-weight: 550; }
    .wholesale-items-table { width: 100%; margin: 0; border-collapse: separate; border-spacing: 0; }
    .wholesale-items-table th { padding: .55rem .6rem; border-bottom: 1px solid var(--wholesale-border); color: var(--wholesale-muted); background: #f8fafc; font-size: .66rem; font-weight: 700; letter-spacing: .04em; text-align: left; text-transform: uppercase; white-space: nowrap; }
    .wholesale-items-table th:first-child, .wholesale-items-table td:first-child { width: 34%; }
    .wholesale-items-table th:last-child, .wholesale-items-table td:last-child { width: 13%; }
    .wholesale-items-table td { padding: .55rem .6rem; border-bottom: 1px solid #f1f5f9; color: #334155; font-size: .78rem; vertical-align: middle; }
    .wholesale-items-table td.text-end, .wholesale-items-table th.text-end { text-align: right; font-variant-numeric: tabular-nums; }
    .wholesale-item-name { color: #111827; font-size: .82rem; font-weight: 650; }
    .wholesale-item-status { display: block; margin-top: .2rem; color: var(--wholesale-muted); font-size: .7rem; }
    .wholesale-item-footer { display: flex; flex-wrap: wrap; justify-content: space-between; gap: .45rem .85rem; padding: .7rem .8rem; background: #f8fafc; font-size: .76rem; }
    .wholesale-item-list { max-height: 340px; overflow: auto; overscroll-behavior: contain; }
    .wholesale-items-table thead { position: sticky; top: 0; z-index: 1; }
    .wholesale-item-list.is-collapsed .wholesale-item-row:nth-child(n+6) { display: none; }
    .wholesale-show-all { margin: .55rem .7rem; padding: 0; border: 0; color: var(--wholesale-blue); background: none; font-size: .78rem; font-weight: 650; }
    .wholesale-payment-proof { min-width: 0; }
    .wholesale-payment-layout { display: grid; grid-template-columns: minmax(0, 1fr) minmax(0, 1fr); align-items: center; gap: 16px; height: 100%; }
    .wholesale-payment-method strong { color: #111827; font-size: 1rem; }
    .wholesale-payment-proof-slot { display: flex; min-width: 0; min-height: 80px; align-items: center; justify-content: center; gap: 10px; padding: 8px; border: 1px solid #e2e8f0; border-radius: 8px; background: #f8fafc; }
    .wholesale-proof-empty-note { color: var(--wholesale-muted); font-size: .76rem; text-align: center; }
    .wholesale-proof-thumbnail { display: block; width: 88px; height: 64px; border-radius: 5px; object-fit: cover; }
    .wholesale-proof-document { color: var(--wholesale-blue); font-size: 2rem; }
    .wholesale-payment-proof-slot .wholesale-proof-upload { width: 100%; }
    .wholesale-payment-proof-slot .wholesale-proof-dropzone { min-height: 80px; }
    .wholesale-detail-payment .payment-proof-empty { display: none; }
    .wholesale-proof-upload { margin: 0; }
    .wholesale-proof-dropzone { display: flex; min-height: 80px; align-items: center; justify-content: center; gap: .75rem; padding: .65rem; border: 1.5px dashed #94a3b8; border-radius: 9px; color: #475569; background: #f8fafc; cursor: pointer; transition: border-color .15s ease, background .15s ease; }
    .wholesale-proof-dropzone:hover, .wholesale-proof-dropzone.is-dragging { border-color: var(--wholesale-blue); background: #eff6ff; }
    .wholesale-proof-dropzone > i { color: var(--wholesale-blue); font-size: 1.25rem; }
    .wholesale-proof-dropzone strong, .wholesale-proof-dropzone small { display: block; }
    .wholesale-proof-dropzone small { margin-top: .15rem; color: var(--wholesale-muted); font-size: .72rem; }
    .wholesale-proof-input { position: absolute; width: 1px; height: 1px; overflow: hidden; clip: rect(0,0,0,0); white-space: nowrap; }
    .wholesale-detail-status .wholesale-info-panel-body { padding: 16px; }
    .wholesale-detail-status .wholesale-detail-section { padding: 0; border: 0; background: transparent; }
    .wholesale-detail-status .wholesale-detail-label { display: none; }
    .wholesale-detail-status .wholesale-detail-section > div:last-child { display: block; }
    .wholesale-detail-status .status-update-form { display: grid; gap: 16px; margin: 0; }
    .wholesale-detail-status .wholesale-status-fields { display: grid; grid-template-columns: repeat(3, minmax(0, 1fr)); gap: 16px; }
    .wholesale-detail-status .wholesale-status-field { min-width: 0; }
    .wholesale-detail-status .wholesale-status-field label { display: block; margin: 0 0 .35rem; color: var(--wholesale-muted); font-size: .68rem; font-weight: 700; letter-spacing: .04em; text-transform: uppercase; }
    .wholesale-detail-status .wholesale-status-field .form-control,
    .wholesale-detail-status .wholesale-status-field .form-select,
    .wholesale-detail-status .wholesale-status-field .input-group,
    .wholesale-detail-status .wholesale-status-field .input-group-text { height: 38px; min-height: 38px; }
    .wholesale-detail-status .wholesale-status-fields > .row { grid-column: 1 / -1; display: contents; margin: 0; }
    .wholesale-detail-status .wholesale-status-fields > .row > div { min-width: 0; }
    .wholesale-detail-status .wholesale-status-note,
    .wholesale-detail-status .wholesale-status-help { color: var(--wholesale-muted); font-size: .76rem; }
    .wholesale-status-actions { display: flex; align-items: center; justify-content: space-between; gap: 16px; }
    .wholesale-status-banner { flex: 1 1 auto; padding: .6rem .75rem; border: 1px solid #bfdbfe; border-radius: 8px; color: #1e3a8a; background: #eff6ff; font-size: .76rem; }
    .wholesale-status-banner .payment-amount-error { display: block; margin-top: .25rem; }
    .wholesale-detail-status .wholesale-save-button { flex: 0 0 auto; min-width: 150px; }
    .wholesale-detail-status .wholesale-save-button:disabled { opacity: .55; cursor: not-allowed; }
    .wholesale-detail-status .wholesale-danger-zone { display: flex; align-items: center; justify-content: space-between; gap: 12px; margin-top: 16px; padding: .45rem .65rem; border: 1px solid #fecaca; border-radius: 9px; background: #fffafa; }
    .wholesale-danger-title { margin: 0; color: #b91c1c; font-size: .75rem; font-weight: 750; text-transform: uppercase; }
    .wholesale-detail-status .wholesale-refund-form { margin: 0 !important; }
    .wholesale-detail-status .wholesale-refund-form button { min-height: 34px; }
    .wholesale-detail-status .wholesale-refund-form small { font-size: .75rem; }
    #wholesaleDetailsFooterActions .wholesale-refund-form { margin: 0; }
    #wholesaleDetailsFooterActions .wholesale-refund-form button { min-height: 34px; white-space: nowrap; }
    .wholesale-order-update-meta { color: #64748b; font-size: .74rem; }
    .wholesale-toast { z-index: 1090; }
    @media (max-width: 991.98px) {
        .wholesale-detail-main { grid-template-columns: 1fr; }
        .wholesale-detail-column > .wholesale-info-panel { flex: 0 0 auto; }
        .wholesale-detail-status .wholesale-status-fields { grid-template-columns: 1fr; }
    }
    @media (max-width: 767.98px) {
        #wholesaleDetailsModal .modal-dialog { width: calc(100vw - 1rem); margin: .5rem auto; }
        #wholesaleDetailsModal .modal-content { max-height: calc(100vh - 1rem); }
        #wholesaleDetailsModal .modal-body { padding: .65rem; }
        .wholesale-modal-header-statuses { display: flex; flex-basis: 100%; order: 3; justify-content: flex-start; margin: 0; padding: .15rem 0 0; }
        .wholesale-modal-chip { padding: .2rem .45rem; font-size: .65rem; }
        .wholesale-financial-panel .wholesale-info-panel-body { grid-template-columns: repeat(2, minmax(0, 1fr)); }
        .wholesale-detail-kv-grid { grid-template-columns: repeat(2, minmax(0, 1fr)); }
        .wholesale-payment-layout { grid-template-columns: 1fr; }
        .wholesale-items-table thead { display: none; }
        .wholesale-items-table, .wholesale-items-table tbody, .wholesale-items-table tr, .wholesale-items-table td { display: block; width: 100%; }
        .wholesale-items-table tr { padding: .55rem .7rem; border-bottom: 1px solid var(--wholesale-border); }
        .wholesale-items-table td { display: flex; justify-content: space-between; gap: .75rem; padding: .18rem 0; border: 0; text-align: right; }
        .wholesale-items-table td::before { content: attr(data-label); color: var(--wholesale-muted); font-size: .67rem; font-weight: 700; text-align: left; text-transform: uppercase; }
        .wholesale-items-table td:first-child { display: block; text-align: left; }
        .wholesale-items-table td:first-child::before { content: none; }
    }
    @media (max-width: 575.98px) {
        .wholesale-financial-panel .wholesale-info-panel-body,
        .wholesale-detail-kv-grid,
        .wholesale-detail-status .wholesale-status-fields { grid-template-columns: 1fr; }
        .wholesale-detail-status .wholesale-save-button { width: 100%; }
        .wholesale-status-actions, .wholesale-detail-status .wholesale-danger-zone { align-items: stretch; flex-direction: column; }
        .wholesale-detail-status .wholesale-refund-form { align-self: stretch; }
        .wholesale-item-footer { display: grid; grid-template-columns: 1fr 1fr; }
        .wholesale-order-update-meta { max-width: 58%; }
    }
</style>
<div class="card wholesale-report-card">
    <div class="card-header d-flex flex-wrap align-items-center justify-content-between gap-2">
        <div><div class="fw-bold text-primary"><i class="fa-solid fa-truck-ramp-box me-2"></i>Wholesale Orders</div><div class="small text-muted">Review ordered items, payment collection, and delivery status.</div></div>
        <span class="badge rounded-pill text-bg-light">{{ number_format($totalTransactions) }} order(s)</span>
    </div>
    <div class="px-3 pt-3 bg-white border-bottom">
        <div class="d-flex flex-wrap gap-2" role="navigation" aria-label="Wholesale transaction status">
            @foreach([
                'all' => ['label' => 'All Transactions', 'color' => 'primary'],
                'open' => ['label' => 'Open Transactions', 'color' => 'warning'],
                'completed' => ['label' => 'Completed Transactions', 'color' => 'success'],
                'refunded' => ['label' => 'Refunded Orders', 'color' => 'danger'],
            ] as $state => $definition)
                <a href="{{ route('hub.report', ['hub' => $hub->id, 'channel' => 'wholesale', 'transaction_state' => $state, 'date_from' => $dateFrom, 'date_to' => $dateTo]) }}"
                   class="btn btn-sm {{ $transactionState === $state ? 'btn-'.$definition['color'] : 'btn-outline-'.$definition['color'] }} mb-3">
                    {{ $definition['label'] }}
                    <span class="badge {{ $transactionState === $state ? 'text-bg-light' : 'text-bg-secondary' }} ms-1">{{ number_format($stateCounts[$state]) }}</span>
                </a>
            @endforeach
        </div>
        <div class="small text-muted mb-3"><strong>Completed</strong> means fully paid and delivered. Refunded orders are shown separately and locked.</div>
    </div>
    <div class="table-responsive">
        <table class="table table-bordered table-sm align-middle mb-0">
            <thead class="table-success">
                <tr><th>Company/Customer</th><th>Purchase Date</th><th>MOP</th><th class="text-end">Order Value</th><th class="text-end">Collected</th><th class="text-end">Shipping Fee</th><th>Type of Delivery</th><th>Invoice #</th><th>Payment</th><th>Order</th><th>Delivery</th><th>Delivery/Pickup Date</th><th>Type</th><th>Courier</th><th>Check Date</th><th class="text-end">Withholding Tax</th><th>Update Status</th></tr>
            </thead>
            <tbody>
                @php
                    $pagedTransactions = $transactions->getCollection()->values();
                @endphp
                @forelse($pagedTransactions as $transaction)
                    @php
                        $invoiceTotal = (float) ($transaction->grand_total ?: $transaction->total_amount ?: $transaction->items->sum('line_total'));
                        $shippingFee = (float) ($transaction->shipping_fee_amount ?? 0);
                        $paymentStatus = $transaction->payment_status ?? 'unpaid';
                        $isRefunded = $transaction->status === 'refunded';
                        $deliveryStatus = $transaction->delivery_status ?? 'pending';
                        $withholdingAmount = $transaction->withholding_tax_amount === null ? null : (float) $transaction->withholding_tax_amount;
                        $withholdingTax = (float) ($transaction->wholesale_withholding_tax ?? 0) > 0
                            ? (float) $transaction->wholesale_withholding_tax
                            : null;
                        // Withholding Tax is a reporting reference. Payment balance uses
                        // the full order total and does not deduct the separate shipping fee.
                        $paymentTotal = max(0, $invoiceTotal);
                        // Collected is always the amount actually paid by the customer.
                        // It must not be replaced or capped by the withholding breakdown.
                        $collected = $isRefunded ? 0 : min($paymentTotal, max(0, (float) ($transaction->amount_paid ?? 0)));
                        $total = $isRefunded ? 0 : $invoiceTotal;
                        $approvedReplacementShipping = (float) $transaction->replacements
                            ->where('status', 'approved')
                            ->sum('replacement_shipping_fee_amount');
                        $paymentLocked = $paymentStatus === 'paid' || $isRefunded;
                        $refundItemsComplete = $transaction->items->isNotEmpty()
                            && $transaction->items->every(fn ($item) => $item->returnedQuantity() >= (int) $item->quantity);
                        $refundHasActiveReplacement = $transaction->items->contains(
                            fn ($item) => $item->replacements->contains(fn ($replacement) => in_array($replacement->status, ['pending', 'approved'], true))
                        );
                        $refundEligible = ! $isRefunded && in_array($transaction->status, ['confirmed', 'completed'], true)
                            && $refundItemsComplete && ! $refundHasActiveReplacement;
                    @endphp
                    <tr data-wholesale-order-row="{{ $transaction->id }}"
                        data-order-number="{{ $transaction->order_number }}"
                        data-customer="{{ $transaction->customer_name }}"
                        data-customer-new="{{ in_array((int) $transaction->id, $newCustomerSaleIds, true) ? 'true' : 'false' }}"
                        data-payment-status="{{ $paymentStatus }}"
                        data-order-status="{{ $transaction->status ?? 'confirmed' }}"
                        data-delivery-status="{{ $deliveryStatus }}"
                        data-order-value="{{ $total }}"
                        data-collected="{{ $collected }}"
                        data-balance="{{ max(0, $paymentTotal - $collected) }}"
                        data-shipping-fee="{{ $shippingFee }}"
                        data-withholding-amount="{{ $withholdingAmount ?? '' }}"
                        data-withholding-tax="{{ $withholdingTax ?? '' }}"
                        data-subtotal="{{ (float) ($transaction->sub_total ?: $transaction->items->sum('line_total')) }}"
                        data-updated-at="{{ optional($transaction->updated_at)->timezone('Asia/Manila')->format('M d, Y h:i A') }}"
                        data-updated-by="{{ $transaction->lastUpdatedBy?->name ?? $transaction->user?->name ?? '—' }}">
                        <td class="wholesale-items">
                            <div class="d-flex align-items-center justify-content-between gap-2 wholesale-customer-summary">
                                <div class="customer-copy text-truncate">
                                    <div class="fw-semibold text-truncate">{{ $transaction->customer_name }}@include('hubs.reports._new-customer-badge')</div>
                                    <div class="small text-muted text-truncate">Order #{{ $transaction->order_number }}</div>
                                </div>
                                <button type="button" class="btn btn-outline-primary btn-sm wholesale-view-details"
                                    data-bs-toggle="modal" data-bs-target="#wholesaleDetailsModal"
                                    data-order-id="{{ $transaction->id }}"
                                    data-customer="{{ $transaction->customer_name }}"
                                    data-order-number="{{ $transaction->order_number }}">
                                    <i class="fa-regular fa-eye me-1"></i> Details
                                </button>
                            </div>
                            <details class="mt-1">
                                <summary class="small text-primary" style="cursor: pointer;">View ordered items</summary>
                                <div class="small mt-2">
                                    @foreach($transaction->items as $item)
                                        @php
                                            $itemName = $item->product?->name ?? 'Product #'.$item->product_id;
                                            $returnRecord = $wholesaleReturns->get($transaction->order_number.'|'.$item->product_id)?->first();
                                            $replacementStatus = $item->replacements
                                                ->sortByDesc('created_at')
                                                ->first()?->status;
                                            $replacementReturn = $item->replacements
                                                ->flatMap(fn ($replacement) => $replacement->inventoryReturns)
                                                ->sortByDesc('occurred_on')
                                                ->first();
                                        @endphp
                                        <div class="item-line"
                                            data-item-name="{{ $itemName }}"
                                            data-item-quantity="{{ (int) $item->quantity }}"
                                            data-item-unit-price="{{ (float) $item->unit_price }}"
                                            data-item-discount="{{ (float) ($item->discount_percentage ?? 0) }}"
                                            data-item-line-total="{{ (float) $item->line_total }}"
                                            data-item-good-returned="{{ (int) $item->inventoryReturns->where('condition', 'good')->sum('quantity') }}"
                                            data-item-damaged-returned="{{ (int) $item->inventoryReturns->where('condition', 'damaged')->sum('quantity') }}">
                                            <div class="fw-semibold">
                                                {{ $itemName }}
                                                @if($item->inventoryReturns->isNotEmpty())
                                                    <span class="badge bg-info text-dark ms-1">RETURNED</span>
                                                @elseif($replacementReturn)
                                                    <span class="badge bg-info text-dark ms-1">REPLACEMENT RETURNED</span>
                                                @elseif($replacementStatus === 'approved')
                                                    <span class="badge bg-success ms-1">REPLACED</span>
                                                @elseif($replacementStatus === 'pending')
                                                    <span class="badge bg-warning text-dark ms-1">REPLACEMENT PENDING</span>
                                                @elseif($returnRecord)
                                                    <span class="badge bg-info text-dark ms-1">RETURNED</span>
                                                @endif
                                            </div>
                                            <div class="text-muted">
                                                Qty {{ $item->quantity }} · ₱{{ number_format($item->unit_price, 2) }} each
                                                · Item discount {{ number_format($item->discount_percentage ?? 0, 2) }}%
                                                · Line total ₱{{ number_format($item->line_total, 2) }}
                                                @if($item->inventoryReturns->isNotEmpty())
                                                    · Returned {{ $item->inventoryReturns->sum('quantity') }} on {{ optional($item->inventoryReturns->sortByDesc('occurred_on')->first()->occurred_on)->format('M d, Y') }}
                                                    · Good {{ $item->inventoryReturns->where('condition', 'good')->sum('quantity') }} / Damaged {{ $item->inventoryReturns->where('condition', 'damaged')->sum('quantity') }}
                                                @elseif($returnRecord)
                                                    · Returned {{ $returnRecord->quantity }} on {{ optional($returnRecord->occurred_on)->format('M d, Y') }}
                                                @endif
                                            </div>
                                            @foreach($item->replacements as $replacement)
                                                <div class="mt-1 p-2 rounded {{ $replacement->status === 'approved' ? 'bg-success-subtle text-success-emphasis' : ($replacement->status === 'rejected' ? 'bg-danger-subtle text-danger-emphasis' : 'bg-warning-subtle text-warning-emphasis') }}">
                                                    <i class="fa-solid fa-arrow-right-arrow-left me-1"></i>
                                                    Replaced/returned: <strong>{{ $replacement->quantity }}</strong> · Replacement quantity: <strong>{{ $replacement->replacement_quantity ?: $replacement->quantity }}</strong> · <strong>{{ $replacement->replacementProduct?->name ?? 'Product #'.$replacement->replacement_product_id }}</strong>
                                                    · Unit price: <strong>₱{{ number_format((float) $replacement->replacement_unit_price, 2) }}</strong>
                                                    · Line total: <strong>₱{{ number_format((float) $replacement->replacement_unit_price * (int) ($replacement->replacement_quantity ?: $replacement->quantity) * (1 - ((float) ($replacement->replacement_discount_percentage ?? 0) / 100)), 2) }}</strong>
                                                    <span class="badge ms-1 {{ $replacement->status === 'approved' ? 'bg-success' : ($replacement->status === 'rejected' ? 'bg-danger' : 'bg-warning text-dark') }}">{{ strtoupper($replacement->status) }}</span>
                                                    <span class="d-block text-muted">{{ optional($replacement->created_at)->format('M d, Y h:i A') }}{{ $replacement->reason ? ' · '.$replacement->reason : '' }}</span>
                                                    @if((float) ($replacement->replacement_discount_percentage ?? 0) > 0)
                                                        <span class="d-block text-muted">Discount: {{ number_format((float) $replacement->replacement_discount_percentage, 2) }}%</span>
                                                    @endif
                                                    @if($replacement->exchange_payment_amount > 0)
                                                        <span class="d-block text-info">Additional payment: ₱{{ number_format((float) $replacement->exchange_payment_amount, 2) }}{{ $replacement->exchange_payment_method ? ' · '.$replacement->exchange_payment_method : '' }}</span>
                                                        @if($replacement->exchange_payment_reference)
                                                            <span class="d-block text-muted">Payment reference: {{ $replacement->exchange_payment_reference }}</span>
                                                        @endif
                                                        @if($replacement->exchange_payment_proofs)
                                                            <span class="d-block text-muted">Payment proof attachments:</span>
                                                            @foreach($replacement->exchange_payment_proofs as $proofIndex => $proof)
                                                                <a href="{{ route('hub.report.replacement-attachment', ['hub' => $hub->id, 'replacement' => $replacement->id, 'type' => 'payment-proof', 'index' => $proofIndex]) }}" target="_blank" rel="noopener" class="d-block">Open attachment {{ $proofIndex + 1 }}</a>
                                                            @endforeach
                                                        @endif
                                                        @if($replacement->replacement_order_slip)
                                                            <a href="{{ route('hub.report.replacement-attachment', ['hub' => $hub->id, 'replacement' => $replacement->id, 'type' => 'replacement-slip', 'index' => 0]) }}" target="_blank" rel="noopener" class="d-block">Open replacement order slip</a>
                                                        @endif
                                                    @endif
                                                    @if($replacement->inventoryReturns->isNotEmpty())
                                                        <span class="d-block text-info">Returned {{ $replacement->inventoryReturns->sum('quantity') }} · Good {{ $replacement->inventoryReturns->where('condition', 'good')->sum('quantity') }} / Damaged {{ $replacement->inventoryReturns->where('condition', 'damaged')->sum('quantity') }}</span>
                                                    @endif
                                                </div>
                                            @endforeach
                                            @php
                                                $wholesaleReplacementLimit = min(
                                                    (int) $item->remaining_replaceable_quantity,
                                                    (int) $item->remaining_returned_replaceable_quantity
                                                );
                                            @endphp
                                            @can('manage-sales-status')
                                                @if($wholesaleReplacementLimit > 0 && in_array($transaction->status, ['confirmed', 'completed'], true))
                                                    <button type="button" class="btn btn-outline-success btn-sm mt-2 wholesale-replace-button"
                                                        data-bs-toggle="modal" data-bs-target="#wholesaleReplacementModal"
                                                        data-action="{{ route('hub.report.wholesale.replace', [$hub->id, $transaction->id, $item->id]) }}"
                                                        data-product="{{ $item->product?->name ?? 'Product #'.$item->product_id }}"
                                                        data-original-product-id="{{ $item->product_id }}"
                                                        data-original-unit-price="{{ round((float) $item->unit_price * (1 - ((float) ($item->discount_percentage ?? 0) / 100)), 2) }}"
                                                        data-payment-status="{{ $paymentStatus }}"
                                                        data-order-subtotal="{{ (float) ($transaction->sub_total ?: $transaction->items->sum('line_total')) }}"
                                                        data-order-shipping="{{ (float) ($transaction->shipping_fee_amount ?? 0) }}"
                                                        data-order-discount="{{ (float) ($transaction->additional_discount_percentage ?? 0) }}"
                                                        data-order-withholding="{{ (float) ($transaction->withholding_tax ?? 0) }}"
                                                        data-amount-paid="{{ (float) ($transaction->amount_paid ?? 0) }}"
                                                        data-remaining="{{ $wholesaleReplacementLimit }}">
                                                        <i class="fa-solid fa-arrow-right-arrow-left me-1"></i> Replace item
                                                    </button>
                                                @elseif($item->remaining_replaceable_quantity > 0 && in_array($transaction->status, ['confirmed', 'completed'], true))
                                                    <button type="button" class="btn btn-outline-success btn-sm mt-2" disabled title="Available after inventory receives the returned item">
                                                        <i class="fa-solid fa-arrow-right-arrow-left me-1"></i> Replace item
                                                    </button>
                                                @endif
                                            @endcan
                                        </div>
                                    @endforeach
                                    @if((float) ($transaction->additional_discount_percentage ?? 0) > 0)
                                        <div class="text-danger pt-1">Order discount: {{ number_format($transaction->additional_discount_percentage, 2) }}%</div>
                                    @endif
                                </div>
                            </details>
                        </td>
                        <td>{{ optional($transaction->order_date)->format('F d, Y') }}</td>
                        <td>
                            {{ $transaction->mode_of_payment ?: '—' }}
                            <details class="mt-1">
                                <summary class="small text-primary" style="cursor: pointer;">Payment proof</summary>
                                <div class="mt-2" style="min-width: 260px;">@include('hubs.reports._payment-proof', ['paymentRecord' => $transaction])</div>
                            </details>
                        </td>
                        <td class="text-end fw-bold">₱{{ number_format($total, 2) }}</td>
                        <td class="text-end fw-bold text-success">₱{{ number_format($collected, 2) }}</td>
                        <td class="text-end">₱{{ number_format($shippingFee, 2) }}@if($approvedReplacementShipping > 0)<small class="d-block text-muted">Includes ₱{{ number_format($approvedReplacementShipping, 2) }} replacement shipping</small>@endif</td>
                        <td>{{ strtoupper($transaction->shipping_fee_type ?: '—') }}</td>
                        <td>{{ $transaction->invoice_number ?: '—' }}</td>
                        <td class="text-center"><span class="wholesale-table-status {{ $paymentStatus === 'paid' ? 'bg-success-subtle text-success' : ($paymentStatus === 'partial' ? 'bg-warning-subtle text-warning-emphasis' : 'bg-secondary-subtle text-secondary-emphasis') }}">{{ strtoupper($paymentStatus) }}</span></td>
                        <td>{{ strtoupper($transaction->status ?? 'confirmed') }}</td>
                        <td class="text-center"><span class="wholesale-table-status {{ $deliveryStatus === 'delivered' ? 'bg-success-subtle text-success' : ($deliveryStatus === 'cancelled' ? 'bg-danger-subtle text-danger' : 'bg-info-subtle text-info-emphasis') }}">{{ strtoupper($deliveryStatus) }}</span></td>
                        <td>{{ optional($transaction->delivery_date ?? $transaction->date_of_arrangement)->format('F d, Y') ?? '—' }}</td>
                        <td>{{ strtoupper($transaction->shipping_fee_type ?: '—') }}</td>
                        <td>{{ $transaction->courier ?: '—' }}</td>
                        <td>{{ optional($transaction->check_date)->format('F d, Y') ?? '—' }}</td>
                        <td class="text-end">{{ $withholdingTax === null ? '—' : '₱'.number_format($withholdingTax, 2) }}</td>
                        <td class="wholesale-status">
                            @if($isRefunded)
                                <div class="alert alert-secondary py-2 px-3 mb-0">
                                    <div class="fw-bold"><i class="fa-solid fa-lock me-1"></i>Refunded and locked</div>
                                    <div class="small">Refund: ₱{{ number_format($transaction->wholesale_refund_amount, 2) }}
                                        @if($transaction->refunded_at) · {{ $transaction->refunded_at->format('M d, Y h:i A') }}@endif
                                        @if($transaction->refundedBy) · by {{ $transaction->refundedBy->name }}@endif
                                    </div>
                                </div>
                            @else
                            <form action="{{ route('sales.status.update', $transaction->id) }}" method="POST" enctype="multipart/form-data" class="status-update-form" id="wholesale-status-form-{{ $transaction->id }}">
                                @csrf
                                @method('PATCH')
                                <div class="wholesale-status-fields">
                                    <div class="wholesale-status-field">
                                        <label for="wholesale-payment-status-{{ $transaction->id }}">Payment Status @if($paymentLocked)<i class="fa-solid fa-lock ms-1 text-muted" title="Payment is already fully paid and locked." aria-label="Locked"></i>@endif</label>
                                        <select id="wholesale-payment-status-{{ $transaction->id }}" name="payment_status" class="form-select form-select-sm" aria-label="Payment status" title="{{ $paymentLocked ? 'Payment status is locked because this order is fully paid.' : '' }}" @disabled($paymentLocked)>
                                            <option value="unpaid" @selected($paymentStatus === 'unpaid')>Unpaid</option>
                                            <option value="partial" @selected($paymentStatus === 'partial')>Partial</option>
                                            <option value="paid" @selected($paymentStatus === 'paid')>Paid</option>
                                        </select>
                                        @if($paymentLocked)
                                            <input type="hidden" name="payment_status" value="paid">
                                        @endif
                                    </div>
                                    <div class="wholesale-status-field">
                                        <label for="wholesale-delivery-status-{{ $transaction->id }}">Delivery Status</label>
                                        <select id="wholesale-delivery-status-{{ $transaction->id }}" name="delivery_status" class="form-select form-select-sm" aria-label="Delivery status">
                                            @foreach(['pending', 'preparing', 'shipped', 'delivered', 'cancelled'] as $status)
                                                <option value="{{ $status }}" @selected($deliveryStatus === $status)>{{ ucfirst($status) }}</option>
                                            @endforeach
                                        </select>
                                    </div>
                                    <div class="wholesale-status-field">
                                        <label for="wholesale-amount-paid-{{ $transaction->id }}">Amount Collected @if($paymentLocked)<i class="fa-solid fa-lock ms-1 text-muted" title="Amount is locked because this order is fully paid." aria-label="Locked"></i>@endif</label>
                                        <div class="input-group input-group-sm">
                                            <span class="input-group-text">₱</span>
                                            <input id="wholesale-amount-paid-{{ $transaction->id }}" type="number" name="amount_paid" class="form-control amount-paid-input" min="0" max="{{ $paymentTotal }}" step="0.01" value="{{ number_format($collected, 2, '.', '') }}" placeholder="Amount paid" data-order-total="{{ $paymentTotal }}" title="{{ $paymentLocked ? 'Amount is locked because this order is fully paid.' : '' }}" @disabled($paymentLocked)>
                                            @if($paymentLocked)
                                                <input type="hidden" name="amount_paid" value="{{ number_format($collected, 2, '.', '') }}">
                                            @endif
                                        </div>
                                    </div>
                                    <div class="wholesale-status-field">
                                        <label for="wholesale-invoice-number-{{ $transaction->id }}">Invoice Number</label>
                                        <input id="wholesale-invoice-number-{{ $transaction->id }}" type="text" name="invoice_number" class="form-control form-control-sm" value="{{ $transaction->invoice_number }}" placeholder="Add invoice number">
                                    </div>
                                    <div class="wholesale-status-field">
                                        <label for="wholesale-withholding-amount-{{ $transaction->id }}">Withholding Tax Amount</label>
                                        <input id="wholesale-withholding-amount-{{ $transaction->id }}" type="number" name="withholding_tax_amount" class="form-control form-control-sm" min="0" step="0.01" value="{{ $withholdingAmount === null ? '' : number_format($withholdingAmount, 2, '.', '') }}" placeholder="Amount">
                                    </div>
                                    <div class="wholesale-status-field">
                                        <label for="wholesale-wholesale-tax-{{ $transaction->id }}">Withholding Tax</label>
                                        <input id="wholesale-wholesale-tax-{{ $transaction->id }}" type="number" name="wholesale_withholding_tax" class="form-control form-control-sm" min="0" step="0.01" value="{{ $withholdingTax === null ? '' : number_format($withholdingTax, 2, '.', '') }}" placeholder="Tax">
                                    </div>
                                    <div class="wholesale-status-note">
                                        <i class="fa-solid fa-circle-info me-1" data-bs-toggle="tooltip" title="This is a reporting reference only; it does not change the customer order value or amount collected."></i>
                                        Withholding tax is a reporting reference and does not change the customer order value or Collected.
                                    </div>
                                    <div class="wholesale-status-help">
                                        @if($paymentLocked)
                                            <div class="alert alert-info py-2 px-3 mb-0"><i class="fa-solid fa-lock me-1"></i>Payment is fully paid and locked. Delivery status remains editable.</div>
                                        @else
                                            <small class="text-muted payment-balance-help">{{ $paymentStatus === 'partial' ? 'For Partial, enter an amount below ₱'.number_format($paymentTotal, 2).'.' : 'Enter the amount collected. Set payment status to Paid to collect the full order value.' }}</small>
                                        @endif
                                        <small class="text-danger d-none payment-amount-error">An unpaid order cannot have an amount paid. Enter 0.00 or select Partial.</small>
                                    </div>
                                </div>
                                <div class="wholesale-proof-upload d-none" data-upload-form="{{ 'wholesale-status-form-'.$transaction->id }}">
                                    <label class="wholesale-proof-dropzone" for="wholesale-payment-proof-{{ $transaction->id }}">
                                        <i class="fa-solid fa-cloud-arrow-up"></i>
                                        <span><strong>Upload payment proof</strong><small>Drag &amp; drop or browse · JPG, PNG, WEBP or PDF (max 2 MB)</small></span>
                                    </label>
                                    <input id="wholesale-payment-proof-{{ $transaction->id }}" class="wholesale-proof-input" type="file" name="payment_proof" accept=".jpg,.jpeg,.png,.webp,.pdf">
                                    <div class="wholesale-proof-filename small text-muted mt-1" aria-live="polite"></div>
                                </div>
                                <button type="submit" class="btn btn-primary wholesale-save-button" disabled>
                                    <i class="fa-solid fa-check me-1"></i>Save changes
                                </button>
                            </form>
                            @can('manage-sales-status')
                                <div class="wholesale-danger-zone">
                                    <div class="wholesale-danger-title"><i class="fa-solid fa-triangle-exclamation me-1"></i>Danger zone</div>
                                <form action="{{ route('hub.report.wholesale.refund', [$hub->id, $transaction->id]) }}" method="POST" class="wholesale-refund-form mt-2"
                                    data-order-number="{{ $transaction->order_number }}"
                                    data-refund-amount="{{ number_format($collected, 2, '.', '') }}">
                                    @csrf
                                    @if($refundEligible)
                                        <button type="submit" class="btn btn-outline-danger btn-sm w-100">
                                            <i class="fa-solid fa-lock me-1"></i>Refund &amp; Lock Order
                                        </button>
                                    @else
                                        <button type="button" class="btn btn-outline-secondary btn-sm w-100" disabled
                                            title="{{ $refundHasActiveReplacement ? 'Resolve all pending or approved replacements first.' : ($refundItemsComplete ? 'Only verified wholesale orders can be refunded.' : 'Refund unlocks after every ordered unit is recorded as returned.') }}">
                                            <i class="fa-solid fa-lock me-1"></i>Refund Locked
                                        </button>
                                        <small class="text-muted d-block mt-1">
                                            {{ $refundHasActiveReplacement ? 'Resolve pending or approved replacements before refunding.' : ($refundItemsComplete ? 'Refund is available only for verified wholesale orders.' : 'Refund is available after every ordered unit is recorded as returned.') }}
                                        </small>
                                    @endif
                                </form>
                            @endcan
                            </div>
                            @endif
                        </td>
                    </tr>
                    @php
                        $monthKey = optional($transaction->order_date)->format('Y-m') ?? 'undated';
                        $nextTransaction = $pagedTransactions->get($loop->index + 1);
                        $nextMonthKey = optional($nextTransaction?->order_date)->format('Y-m') ?? 'undated';
                    @endphp
                    @if($loop->last || $monthKey !== $nextMonthKey)
                        @php
                            $monthTransactions = $pagedTransactions->filter(
                                fn ($sale) => (optional($sale->order_date)->format('Y-m') ?? 'undated') === $monthKey
                            );
                            $monthOrderValue = $monthTransactions->sum(function ($sale) {
                                if ($sale->status === 'refunded') {
                                    return 0;
                                }

                                return max(0, (float) ($sale->grand_total ?: $sale->total_amount ?: $sale->items->sum('line_total')));
                            });
                            $monthShippingFees = $monthTransactions->sum('shipping_fee_amount');
                        @endphp
                        <tr class="table-primary-subtle fw-semibold wholesale-month-subtotal">
                            <td colspan="2"></td>
                            <td class="text-end">
                                {{ $monthKey === 'undated' ? 'Undated' : \Carbon\Carbon::createFromFormat('Y-m', $monthKey)->format('F Y') }} subtotal: ₱{{ number_format($monthOrderValue, 2) }}
                            </td>
                            <td colspan="14"></td>
                        </tr>
                    @endif
                @empty
                    <tr><td colspan="17" class="text-center text-muted py-4">No wholesale sales found.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
<div class="modal fade" id="wholesaleDetailsModal" tabindex="-1" aria-labelledby="wholesaleDetailsLabel" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-centered modal-dialog-scrollable">
        <div class="modal-content border-0 shadow">
            <div class="modal-header bg-primary text-white">
                <div class="wholesale-modal-header-main">
                    <h5 class="modal-title wholesale-modal-title" id="wholesaleDetailsLabel"><i class="fa-solid fa-receipt me-2"></i><span id="wholesaleModalOrderNumber">Wholesale Order Details</span></h5>
                    <div class="wholesale-modal-subtitle" id="wholesaleDetailsSubtitle"></div>
                </div>
                <div class="wholesale-modal-header-statuses" id="wholesaleDetailsStatusChips" aria-label="Order status"></div>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body bg-light">
                <div class="wholesale-detail-grid" id="wholesaleDetailsContent"></div>
            </div>
            <div class="modal-footer bg-white">
                <div class="wholesale-order-update-meta" id="wholesaleDetailsUpdated"></div>
                <div class="d-flex align-items-center gap-2" id="wholesaleDetailsFooterActions">
                    <button type="button" class="btn btn-primary btn-sm" data-bs-dismiss="modal">Close</button>
                </div>
            </div>
        </div>
    </div>
</div>
@can('manage-sales-status')
<div class="modal fade" id="wholesaleRefundConfirmModal" tabindex="-1" aria-labelledby="wholesaleRefundConfirmTitle" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-sm">
        <div class="modal-content border-0 shadow">
            <div class="modal-header border-0 pb-0">
                <h5 class="modal-title fs-6" id="wholesaleRefundConfirmTitle">
                    <i class="fa-solid fa-triangle-exclamation text-danger me-2"></i>Confirm wholesale refund
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cancel"></button>
            </div>
            <div class="modal-body">
                <div class="alert alert-warning d-flex gap-2 align-items-start mb-3" role="alert">
                    <i class="fa-solid fa-circle-exclamation mt-1"></i>
                    <div>This will refund the amount collected and permanently lock the order.</div>
                </div>
                <dl class="row small mb-0">
                    <dt class="col-6 text-muted">Order</dt>
                    <dd class="col-6 text-end fw-semibold" id="wholesaleRefundOrder"></dd>
                    <dt class="col-6 text-muted mb-0">Refund amount</dt>
                    <dd class="col-6 text-end fw-bold text-danger mb-0" id="wholesaleRefundAmount"></dd>
                </dl>
                <div class="small text-muted mt-3">Order Value and Collected will both be set to ₱0.00. This cannot be undone.</div>
            </div>
            <div class="modal-footer border-0 pt-0">
                <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-danger" id="wholesaleRefundConfirmButton">
                    <i class="fa-solid fa-lock me-1"></i>Refund &amp; Lock
                </button>
            </div>
        </div>
    </div>
</div>
<div class="modal fade" id="wholesaleReplacementModal" tabindex="-1" aria-labelledby="wholesaleReplacementLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable">
        <form method="POST" enctype="multipart/form-data" class="modal-content" id="wholesaleReplacementForm">
            @csrf
            <div class="modal-header">
                <div><h5 class="modal-title" id="wholesaleReplacementLabel">Wholesale Replacement / Exchange</h5><small class="text-muted" id="wholesaleOriginalProduct"></small></div>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div class="alert alert-info small">This request goes to Inventory Verification first. Stock and the order total change only after approval, using the quantities and wholesale prices below.</div>
                <div class="alert alert-warning small d-none" data-wholesale-partial-payment-note>For a partially paid order, the payment already received is applied once to the revised order. The customer pays only the remaining balance, including a replacement delivery fee when it is not Free.</div>
                <div class="mb-3">
                    <label class="form-label fw-semibold" for="wholesaleReplacementSearch">Replacement product</label>
                    <input type="text" id="wholesaleReplacementSearch" class="form-control" list="wholesaleReplacementOptions" autocomplete="off" placeholder="Type at least 1 character or an Item ID" required>
                    <input type="hidden" name="replacement_product_id" id="wholesaleReplacementProductId">
                    <datalist id="wholesaleReplacementOptions"></datalist>
                    <div class="form-text" id="wholesaleReplacementStock">Choose an active product from this hub.</div>
                </div>
                <div class="row g-3 mb-3">
                    <div class="col-6">
                        <label class="form-label fw-semibold" for="wholesaleReplacementQuantity">Original qty returned</label>
                        <input type="number" name="quantity" id="wholesaleReplacementQuantity" class="form-control" min="1" value="1" required>
                        <div class="form-text" id="wholesaleReplacementLimit"></div>
                    </div>
                    <div class="col-6">
                        <label class="form-label fw-semibold" for="wholesaleNewQuantity">Replacement qty requested</label>
                        <input type="number" name="replacement_quantity" id="wholesaleNewQuantity" class="form-control" min="1" value="1" required>
                        <div class="form-text">Enter how many units the customer wants.</div>
                    </div>
                    <div class="col-12">
                        <label class="form-label fw-semibold" for="wholesaleReplacementDiscount">Custom discount for replacement (%)</label>
                        <input type="number" name="replacement_discount_percentage" id="wholesaleReplacementDiscount" class="form-control" min="0" max="100" step="0.01" value="0.00">
                        <div class="form-text">This discount applies only to the main replacement product.</div>
                    </div>
                </div>
                <div class="row g-2 mb-3">
                    <div class="col-6">
                        <label class="form-label fw-semibold">Replacement delivery</label>
                        <select name="replacement_shipping_fee_type" class="form-select" data-wholesale-replacement-shipping-type>
                            <option value="Free">Free delivery</option>
                            <option value="Custom Amount">Custom amount</option>
                        </select>
                    </div>
                    <div class="col-6 d-none" data-wholesale-replacement-shipping-amount-wrap>
                        <label class="form-label fw-semibold">Shipping fee (₱)</label>
                        <input type="number" name="replacement_shipping_fee_amount" class="form-control" min="0" step="0.01" value="0.00" disabled>
                    </div>
                </div>
                <div class="card border-0 bg-light mb-3" id="wholesaleExchangeSummary">
                    <div class="card-body p-3">
                        <div class="d-flex align-items-center justify-content-between mb-2"><strong><i class="fa-solid fa-calculator text-primary me-2"></i>Exchange calculation</strong><span class="badge bg-secondary" data-exchange-calculation-status>Select a replacement</span></div>
                        <div class="row g-2 small">
                            <div class="col-6 col-md-3"><span class="text-muted d-block">Replacement shipping</span><strong data-exchange-shipping>₱0.00</strong></div>
                            <div class="col-6 col-md-3"><span class="text-muted d-block" data-exchange-credit-label>Original exchange credit</span><strong data-exchange-credit>₱0.00</strong></div>
                            <div class="col-6 col-md-3"><span class="text-muted d-block">Main replacement total</span><strong data-exchange-main-total>₱0.00</strong></div>
                            <div class="col-6 col-md-3"><span class="text-muted d-block">Additional products</span><strong data-exchange-extra-total>₱0.00</strong></div>
                            <div class="col-6 col-md-3"><span class="text-muted d-block">Replacement basket total</span><strong data-exchange-basket-total>₱0.00</strong></div>
                            <div class="col-6 col-md-3"><span class="text-muted d-block">Remaining store credit</span><strong data-exchange-store-credit>₱0.00</strong></div>
                        </div>
                        <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 border-top mt-3 pt-3">
                            <span class="text-muted small" data-exchange-formula>Choose a replacement product to calculate the total.</span>
                            <span class="fw-bold fs-5 text-danger" data-exchange-amount-due>Customer adds ₱0.00</span>
                        </div>
                    </div>
                </div>
                @include('hubs.reports._exchange-additional-items', ['exchangePrefix' => 'wholesale', 'exchangeChannel' => 'wholesale'])
                <div>
                    <label class="form-label fw-semibold" for="wholesaleReplacementReason">Reason (optional)</label>
                    <textarea name="reason" id="wholesaleReplacementReason" class="form-control" rows="3" maxlength="2000" placeholder="Why is this item being replaced?"></textarea>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="submit" class="btn btn-success">Submit for Verification</button>
            </div>
        </form>
    </div>
</div>
@endcan
    <div class="sales-report-print-only" id="wholesaleReportImageSource">
            @php
                $wholesaleOrderValue = function ($transaction): float {
                    if ($transaction->status === 'refunded') {
                        return 0;
                    }

                    $invoiceTotal = (float) ($transaction->grand_total ?: $transaction->total_amount ?: $transaction->items->sum('line_total'));

                    return max(0, $invoiceTotal);
                };
                $kpiOrderValue = $allTransactions->sum($wholesaleOrderValue);
                $kpiCollected = $wholesaleCollectedSales;
                $kpiShippingFees = $allTransactions->sum('shipping_fee_amount');
                $kpiOutstanding = max(0, $kpiOrderValue - $kpiCollected);
                $kpiCollectionRate = $kpiOrderValue > 0 ? ($kpiCollected / $kpiOrderValue) * 100 : 0;
                $kpiPaidOrders = $allTransactions->where('payment_status', 'paid')->count();
                $kpiPartialOrders = $allTransactions->where('payment_status', 'partial')->count();
                $kpiUnpaidOrders = $allTransactions->whereIn('payment_status', [null, 'unpaid'])->count();
            @endphp
            <div class="d-flex justify-content-between align-items-end border-bottom border-3 border-primary pb-3 mb-4">
                <div>
                    <div class="text-primary fw-bold" style="font-size: 2rem; letter-spacing: -.03em;">ART CARAVAN PH</div>
                    <div class="text-uppercase text-dark fw-bold" style="font-size: 1.1rem; letter-spacing: .12em;">Wholesale Performance Scorecard</div>
                </div>
                <div class="text-end small">
                    <div class="fw-bold fs-5">{{ $hub->name }}</div>
                    <div class="text-muted">Verified sales only · {{ $dateFrom ?: 'All dates' }}{{ $dateTo ? ' to '.$dateTo : '' }}</div>
                </div>
            </div>
            <section class="sales-summary-panel mb-4">
                <div class="sales-summary-heading">
                    <div><h5>Activity overview</h5><p>Sales, collections, returns, and customer activity</p></div>
                    <span class="badge rounded-pill text-bg-success">Verified sales</span>
                </div>
                <div class="sales-summary-grid">
                @foreach([
                    ['label' => 'Discounts', 'value' => $metrics['discounts'], 'class' => 'text-danger', 'accent' => '#dc2626', 'icon' => 'fa-tag'],
                    ['label' => 'Total Sales', 'value' => $kpiCollected, 'class' => 'text-success', 'accent' => '#059669', 'icon' => 'fa-credit-card'],
                    ['label' => 'Shipping Fees', 'value' => $kpiShippingFees, 'class' => 'text-primary', 'accent' => '#2563eb', 'icon' => 'fa-truck'],
                    ['label' => 'Outstanding', 'value' => $kpiOutstanding, 'class' => 'text-warning-emphasis', 'accent' => '#d97706', 'icon' => 'fa-hourglass-half'],
                    ['label' => 'Refund Cost', 'value' => $metrics['refund_total'], 'class' => 'text-danger', 'accent' => '#dc2626', 'icon' => 'fa-money-bill-transfer'],
                    ['label' => 'Total Customers', 'value' => $customerMetrics['total'], 'class' => 'text-primary', 'accent' => '#2563eb', 'money' => false, 'icon' => 'fa-users'],
                    ['label' => 'New Customers', 'value' => $customerMetrics['new'], 'class' => 'text-success', 'accent' => '#059669', 'money' => false, 'icon' => 'fa-user-plus'],
                ] as $metric)
                    <div class="sales-summary-card" style="border-top: 3px solid {{ $metric['accent'] }};">
                        <span class="sales-summary-icon" style="background: {{ $metric['accent'] }}15; color: {{ $metric['accent'] }};" aria-hidden="true"><i class="fa-solid {{ $metric['icon'] }}"></i></span>
                        <div class="sales-summary-copy"><div class="label">{{ $metric['label'] }}</div>
                        <div class="value {{ $metric['class'] }}">{{ ($metric['money'] ?? true) ? '₱' : '' }}{{ number_format($metric['value'], ($metric['money'] ?? true) ? 2 : 0) }}</div></div>
                    </div>
                @endforeach
                </div>
            </section>
            <div class="row g-3">
                <div class="col-7">
                    <div class="border rounded-3 p-4 h-100">
                        <div class="text-uppercase text-muted small fw-bold mb-3">Revenue Overview</div>
                        <div class="d-flex justify-content-between align-items-end mb-2">
                            <span class="fw-semibold">Collected against order value</span>
                            <span class="text-success fw-bold fs-4">{{ number_format($kpiCollectionRate, 1) }}%</span>
                        </div>
                        <div class="progress" style="height: 18px;">
                            <div class="progress-bar bg-success" style="width: {{ min(100, $kpiCollectionRate) }}%;"></div>
                        </div>
                        <div class="d-flex justify-content-between small text-muted mt-2">
                            <span>Collected: ₱{{ number_format($kpiCollected, 2) }}</span>
                            <span>Order value: ₱{{ number_format($kpiOrderValue, 2) }}</span>
                        </div>
                    </div>
                </div>
                <div class="col-5">
                    <div class="border rounded-3 p-4 h-100">
                        <div class="text-uppercase text-muted small fw-bold mb-3">Payment Status</div>
                        <div class="row text-center g-2">
                            <div class="col-4"><div class="text-success fw-bold fs-3">{{ $kpiPaidOrders }}</div><div class="small text-muted">Paid</div></div>
                            <div class="col-4"><div class="text-warning fw-bold fs-3">{{ $kpiPartialOrders }}</div><div class="small text-muted">Partial</div></div>
                            <div class="col-4"><div class="text-danger fw-bold fs-3">{{ $kpiUnpaidOrders }}</div><div class="small text-muted">Unpaid</div></div>
                        </div>
                        <div class="border-top mt-3 pt-3 small text-muted">{{ number_format($totalTransactions) }} wholesale transaction(s) recorded in this reporting period.</div>
                    </div>
                </div>
            </div>
            <div class="text-center text-muted small mt-4">
                Prepared from verified wholesale sales · Generated {{ now()->timezone('Asia/Manila')->format('M d, Y h:i A') }}
            </div>
    </div>
    @push('scripts')
    <script>
        (() => {
            const modalElement = document.getElementById('wholesaleDetailsModal');
            const content = document.getElementById('wholesaleDetailsContent');
            const subtitle = document.getElementById('wholesaleDetailsSubtitle');
            const title = document.getElementById('wholesaleModalOrderNumber');
            const statusChips = document.getElementById('wholesaleDetailsStatusChips');
            const updated = document.getElementById('wholesaleDetailsUpdated');
            if (!modalElement || !content) return;

            const money = value => `₱${Number(value || 0).toLocaleString('en-PH', { minimumFractionDigits: 2, maximumFractionDigits: 2 })}`;
            const cleanValue = value => (value || '').replace(/\s+/g, ' ').trim();
            const createPanel = (titleText, icon, className = '') => {
                const panel = document.createElement('section');
                panel.className = `wholesale-info-panel ${className}`;
                const heading = document.createElement('h6');
                heading.className = 'wholesale-info-panel-title';
                const headingIcon = document.createElement('i');
                headingIcon.className = `fa-solid ${icon}`;
                const headingText = document.createElement('span');
                headingText.textContent = titleText;
                heading.append(headingIcon, headingText);
                const body = document.createElement('div');
                body.className = 'wholesale-info-panel-body';
                panel.append(heading, body);
                return { panel, body };
            };
            const chipClass = (status, type) => {
                const value = (status || '').toLowerCase();
                if (value === 'refunded' || value === 'cancelled' || value === 'rejected') return 'is-danger';
                if (type === 'payment' && value === 'paid') return 'is-success';
                if (type === 'delivery' && value === 'delivered') return 'is-success';
                if (value === 'pending' || value === 'unpaid' || value === 'partial' || value === 'preparing') return 'is-pending';
                return '';
            };
            const addStatusChip = (label, type) => {
                const chip = document.createElement('span');
                chip.className = `wholesale-modal-chip ${chipClass(label, type)}`;
                const icon = document.createElement('i');
                icon.className = `fa-solid ${label.toLowerCase() === 'paid' || label.toLowerCase() === 'delivered' ? 'fa-circle-check' : label.toLowerCase() === 'refunded' ? 'fa-circle-xmark' : 'fa-circle-info'}`;
                const text = document.createElement('span');
                text.textContent = label;
                chip.append(icon, text);
                statusChips.append(chip);
            };
            const makeKpi = (label, value, subtitleText = '', accent = '') => {
                const card = document.createElement('div');
                card.className = 'wholesale-kpi';
                if (accent) card.style.borderTop = `3px solid ${accent}`;
                const heading = document.createElement('div');
                heading.className = 'wholesale-kpi-label';
                heading.textContent = label;
                const amount = document.createElement('div');
                amount.className = 'wholesale-kpi-value';
                amount.textContent = value;
                card.append(heading, amount);
                if (subtitleText) {
                    const detail = document.createElement('div');
                    detail.className = 'wholesale-kpi-subtitle';
                    detail.textContent = subtitleText;
                    card.append(detail);
                }
                return card;
            };
            const panelValue = (cell, index) => {
                const value = document.createElement('div');
                value.className = 'wholesale-detail-value';
                if (index === 2) {
                    const source = cell.cloneNode(true);
                    const details = source.querySelector('details');
                    const method = cleanValue([...source.childNodes]
                        .filter(node => node.nodeType === Node.TEXT_NODE)
                        .map(node => node.textContent).join(' ')) || 'Not specified';
                    const methodLine = document.createElement('div');
                    methodLine.textContent = method;
                    methodLine.className = 'fw-semibold mb-2';
                    value.append(methodLine);
                    if (details) {
                        details.open = true;
                        value.append(details);
                    }
                    return value;
                }
                value.append(...[...cell.childNodes].map(node => node.cloneNode(true)));
                value.querySelector('.wholesale-view-details')?.remove();
                value.querySelectorAll('details').forEach(details => details.open = true);
                return value;
            };
            const addKeyValue = (body, labelText, valueNode, valueText) => {
                const text = cleanValue(valueText);
                if (!valueNode && (!text || ['—', '-', 'N/A'].includes(text.toUpperCase()))) return null;
                const wrapper = document.createElement('div');
                wrapper.className = 'wholesale-detail-kv';
                const label = document.createElement('span');
                label.className = 'wholesale-detail-label';
                label.textContent = labelText;
                const value = valueNode || document.createElement('div');
                value.classList.add('wholesale-detail-value');
                if (!valueNode) value.textContent = text;
                wrapper.append(label, value);
                body.append(wrapper);
                return wrapper;
            };

            modalElement.addEventListener('show.bs.modal', event => {
                const button = event.relatedTarget;
                const row = document.querySelector(`[data-wholesale-order-row="${button?.dataset.orderId}"]`);
                if (!row) return;

                const footerActions = document.getElementById('wholesaleDetailsFooterActions');
                footerActions?.querySelectorAll('.wholesale-refund-form').forEach(form => form.remove());
                content.replaceChildren();
                const cells = [...row.cells];
                const customer = row.dataset.customer || button.dataset.customer || 'Customer';
                const orderNumber = row.dataset.orderNumber || button.dataset.orderNumber || '';
                const paymentStatus = row.dataset.paymentStatus || 'unpaid';
                const orderStatus = row.dataset.orderStatus || 'confirmed';
                const deliveryStatus = row.dataset.deliveryStatus || 'pending';
                const amountCollected = Number(row.dataset.collected || 0);
                const orderValue = Number(row.dataset.orderValue || 0);
                const balance = Number(row.dataset.balance || 0);
                const shippingFee = Number(row.dataset.shippingFee || 0);
                title.textContent = `Wholesale Order #${orderNumber}`;
                subtitle.replaceChildren();
                subtitle.append(document.createTextNode(customer));
                if (row.dataset.customerNew === 'true') {
                    const newTag = document.createElement('span');
                    newTag.className = 'badge rounded-pill text-bg-light text-primary ms-2';
                    newTag.textContent = 'NEW';
                    subtitle.append(newTag);
                }
                statusChips.replaceChildren();
                addStatusChip(paymentStatus.charAt(0).toUpperCase() + paymentStatus.slice(1), 'payment');
                addStatusChip(orderStatus.charAt(0).toUpperCase() + orderStatus.slice(1), 'order');
                addStatusChip(deliveryStatus.charAt(0).toUpperCase() + deliveryStatus.slice(1), 'delivery');
                updated.textContent = `Last updated by ${row.dataset.updatedBy || '—'} · ${row.dataset.updatedAt || 'Date unavailable'}`;

                const financial = createPanel('Financial Summary', 'fa-chart-line', 'wholesale-financial-panel');
                const withholdingAmount = Number(row.dataset.withholdingAmount || 0);
                const withholdingTax = Number(row.dataset.withholdingTax || 0);
                const noWithholding = withholdingAmount <= 0 && withholdingTax <= 0;
                financial.body.append(
                    makeKpi('Order Value', money(orderValue), '', '#2563eb'),
                    makeKpi('Collected', money(amountCollected), '', '#16a34a'),
                    makeKpi('Shipping Fee', shippingFee > 0 ? money(shippingFee) : 'Free shipping', '', '#d97706'),
                    makeKpi(
                        'Withholding Tax',
                        noWithholding ? 'No withholding tax' : money(withholdingAmount || withholdingTax),
                        noWithholding ? '' : `Tax reference ${money(withholdingTax)}`,
                        '#64748b'
                    )
                );
                const balanceLine = document.createElement('div');
                balanceLine.className = 'wholesale-balance-line';
                const balanceText = document.createElement('span');
                const paidInFull = paymentStatus.toLowerCase() === 'paid' || balance <= 0;
                balanceText.className = paidInFull ? 'is-paid' : 'is-due';
                balanceText.innerHTML = paidInFull
                    ? '<i class="fa-solid fa-circle-check me-1"></i>Fully paid'
                    : `<i class="fa-solid fa-clock me-1"></i>Balance due ${money(balance)}`;
                const collectedText = document.createElement('span');
                collectedText.textContent = `${money(amountCollected)} collected`;
                balanceLine.append(balanceText, collectedText);
                const track = document.createElement('div');
                track.className = 'wholesale-balance-track';
                const progress = document.createElement('span');
                progress.style.width = `${paidInFull ? 100 : Math.min(100, orderValue > 0 ? (amountCollected / orderValue) * 100 : 0)}%`;
                track.append(progress);
                financial.panel.append(balanceLine, track);
                content.append(financial.panel);

                const main = document.createElement('div');
                main.className = 'wholesale-detail-main';
                const items = createPanel('Ordered Items', 'fa-boxes-stacked', 'wholesale-ordered-items');
                const sourceItems = row.querySelectorAll('.item-line[data-item-name]');
                const table = document.createElement('table');
                table.className = 'wholesale-items-table';
                const tableHead = document.createElement('thead');
                tableHead.innerHTML = '<tr><th>Item</th><th class="text-end">Qty</th><th class="text-end">Unit Price</th><th class="text-end">Discount</th><th class="text-end">Line Total</th><th>Status / Action</th></tr>';
                const tableBody = document.createElement('tbody');
                const list = document.createElement('div');
                list.className = 'wholesale-item-list is-collapsed';
                let subtotal = 0;
                sourceItems.forEach(sourceItem => {
                    const tr = document.createElement('tr');
                    tr.className = 'wholesale-item-row';
                    const nameCell = document.createElement('td');
                    const itemName = document.createElement('div');
                    itemName.className = 'wholesale-item-name';
                    itemName.textContent = sourceItem.dataset.itemName || 'Inventory item';
                    nameCell.append(itemName);
                    const sourceBadge = sourceItem.querySelector('.fw-semibold .badge');
                    const itemStatus = document.createElement('span');
                    itemStatus.className = 'wholesale-item-status';
                    const good = Number(sourceItem.dataset.itemGoodReturned || 0);
                    const damaged = Number(sourceItem.dataset.itemDamagedReturned || 0);
                    const statusText = sourceBadge?.textContent.trim() || (good + damaged > 0 ? 'RETURNED' : 'Active');
                    const statusBadge = document.createElement('span');
                    statusBadge.className = `badge ${statusText.toLowerCase().includes('pending') ? 'text-bg-warning' : statusText.toLowerCase().includes('rejected') ? 'text-bg-danger' : statusText.toLowerCase().includes('return') ? 'text-bg-info' : 'text-bg-light'}`;
                    statusBadge.textContent = statusText;
                    itemStatus.append(statusBadge);
                    if (good + damaged > 0) {
                        const conditionLine = document.createElement('span');
                        conditionLine.className = 'd-block mt-1';
                        const goodLabel = document.createElement('span');
                        goodLabel.className = 'text-success';
                        goodLabel.textContent = `Good ${good}`;
                        const damagedLabel = document.createElement('span');
                        damagedLabel.className = 'text-danger ms-2';
                        damagedLabel.innerHTML = `<i class="fa-solid fa-triangle-exclamation me-1"></i>Damaged ${damaged}`;
                        conditionLine.append(goodLabel, damagedLabel);
                        itemStatus.append(conditionLine);
                    }
                    nameCell.append(itemStatus);
                    const replacementDetails = [...sourceItem.children].filter(child => child.classList.contains('mt-1'));
                    replacementDetails.forEach(detail => {
                        const clone = detail.cloneNode(true);
                        clone.classList.add('small', 'mt-1');
                        nameCell.append(clone);
                    });
                    const qty = Number(sourceItem.dataset.itemQuantity || 0);
                    const unitPrice = Number(sourceItem.dataset.itemUnitPrice || 0);
                    const discount = Number(sourceItem.dataset.itemDiscount || 0);
                    const lineTotal = Number(sourceItem.dataset.itemLineTotal || 0);
                    subtotal += lineTotal;
                    const addCell = (text, label, right = false) => {
                        const td = document.createElement('td');
                        td.dataset.label = label;
                        if (right) td.className = 'text-end';
                        td.textContent = text;
                        tr.append(td);
                    };
                    tr.append(nameCell);
                    addCell(String(qty), 'Qty', true);
                    addCell(money(unitPrice), 'Unit Price', true);
                    addCell(`${discount.toFixed(2)}%`, 'Discount', true);
                    addCell(money(lineTotal), 'Line Total', true);
                    const actionCell = document.createElement('td');
                    actionCell.dataset.label = 'Status / Action';
                    const replace = sourceItem.querySelector('.wholesale-replace-button');
                    if (replace) {
                        const replaceClone = replace.cloneNode(true);
                        replaceClone.classList.add('btn-sm');
                        actionCell.append(replaceClone);
                    } else {
                        const statusBadge = sourceItem.querySelector('.fw-semibold .badge');
                        if (statusBadge) actionCell.append(statusBadge.cloneNode(true));
                        else actionCell.textContent = '—';
                    }
                    tr.append(actionCell);
                    tableBody.append(tr);
                });
                table.append(tableHead, tableBody);
                list.append(table);
                items.body.append(list);
                const itemCount = sourceItems.length;
                if (itemCount > 5) {
                    const showAll = document.createElement('button');
                    showAll.type = 'button';
                    showAll.className = 'wholesale-show-all';
                    showAll.textContent = `Show all ${itemCount} items`;
                    showAll.addEventListener('click', () => {
                        const expanded = list.classList.toggle('is-collapsed') === false;
                        showAll.textContent = expanded ? 'Show fewer items' : `Show all ${itemCount} items`;
                    });
                    items.body.append(showAll);
                } else {
                    list.classList.remove('is-collapsed');
                }
                const itemFooter = document.createElement('div');
                itemFooter.className = 'wholesale-item-footer';
                itemFooter.innerHTML = `<span><strong>${itemCount}</strong> product item(s)</span><span>Subtotal <strong>${money(Number(row.dataset.subtotal || subtotal))}</strong></span><span>Shipping <strong>${shippingFee > 0 ? money(shippingFee) : 'Free'}</strong></span><span>Grand total <strong>${money(orderValue)}</strong></span>`;
                items.panel.append(itemFooter);
                const leftColumn = document.createElement('div');
                leftColumn.className = 'wholesale-detail-column wholesale-detail-column-left';
                const rightColumn = document.createElement('div');
                rightColumn.className = 'wholesale-detail-column wholesale-detail-column-right';
                const orderInfo = createPanel('Order Information', 'fa-file-invoice');
                orderInfo.body.classList.add('wholesale-detail-kv-grid');
                const statusValue = text => {
                    const label = cleanValue(text);
                    const chip = document.createElement('span');
                    const normalized = label.toLowerCase();
                    const displayLabel = normalized.charAt(0).toUpperCase() + normalized.slice(1);
                    const tone = ['paid', 'confirmed', 'delivered'].includes(normalized)
                        ? 'is-success'
                        : ['unpaid', 'partial', 'pending', 'preparing', 'shipped'].includes(normalized)
                            ? 'is-pending'
                            : ['refunded', 'cancelled', 'rejected'].includes(normalized)
                                ? 'is-danger'
                                : 'is-info';
                    chip.className = `wholesale-status-chip ${tone}`;
                    const icon = document.createElement('i');
                    icon.className = `fa-solid ${tone === 'is-success' ? 'fa-circle-check' : tone === 'is-danger' ? 'fa-circle-xmark' : 'fa-circle-info'}`;
                    const value = document.createElement('span');
                    value.textContent = displayLabel || 'Not specified';
                    chip.append(icon, value);
                    return chip;
                };
                [
                    ['Purchase Date', 1],
                    ['Invoice Number', 7],
                    ['Payment Status', 8],
                    ['Order Status', 9],
                ].forEach(([label, index]) => {
                    const text = cleanValue(cells[index]?.textContent);
                    if (index === 7 && (!text || text === '—')) {
                        const link = document.createElement('button');
                        link.type = 'button';
                        link.className = 'btn btn-link btn-sm p-0';
                        link.textContent = 'Add invoice number';
                        link.addEventListener('click', () => {
                            const input = content.querySelector('.status-update-form [name="invoice_number"]');
                            input?.focus();
                        });
                        addKeyValue(orderInfo.body, label, link, '');
                    } else if (label === 'Payment Status' || label === 'Order Status') {
                        addKeyValue(orderInfo.body, label, statusValue(text), '');
                    } else {
                        addKeyValue(orderInfo.body, label, null, text);
                    }
                });
                const fulfillment = createPanel('Fulfillment', 'fa-truck-fast');
                fulfillment.body.classList.add('wholesale-detail-kv-grid');
                const deliveryText = cleanValue(cells[10]?.textContent);
                addKeyValue(fulfillment.body, 'Delivery Status', statusValue(deliveryText), '');
                [
                    ['Delivery Date', 11],
                    ['Shipping Type', 12],
                    ['Courier', 13],
                ].forEach(([label, index]) => addKeyValue(fulfillment.body, label, null, cells[index]?.textContent));
                const checkDate = cleanValue(cells[14]?.textContent);
                if (checkDate && !['—', '-', 'N/A'].includes(checkDate.toUpperCase())) {
                    const deliveryDate = fulfillment.body.querySelector('.wholesale-detail-kv:nth-child(2) .wholesale-detail-value');
                    if (deliveryDate) deliveryDate.append(document.createTextNode(` · Check date: ${checkDate}`));
                }

                const payment = createPanel('Payment', 'fa-credit-card');
                const paymentValue = panelValue(cells[2], 2);
                const proofDetails = paymentValue.querySelector('details');
                const paymentLayout = document.createElement('div');
                paymentLayout.className = 'wholesale-payment-layout';
                const paymentMethod = document.createElement('div');
                paymentMethod.className = 'wholesale-payment-method';
                const paymentMethodLabel = document.createElement('span');
                paymentMethodLabel.className = 'wholesale-detail-label';
                paymentMethodLabel.textContent = 'Mode of Payment';
                const paymentMethodValue = document.createElement('strong');
                paymentMethodValue.textContent = cleanValue([...paymentValue.childNodes]
                    .filter(node => node.nodeType === Node.TEXT_NODE)
                    .map(node => node.textContent).join(' ')).toUpperCase() || 'NOT SPECIFIED';
                paymentMethod.append(paymentMethodLabel, paymentMethodValue);
                const proofSlot = document.createElement('div');
                proofSlot.className = 'wholesale-payment-proof-slot';
                const proofLink = proofDetails?.querySelector('a[href]');
                if (proofLink) {
                    const image = proofLink.querySelector('img');
                    if (image) {
                        const thumbnail = image.cloneNode(true);
                        thumbnail.className = 'wholesale-proof-thumbnail';
                        proofSlot.append(thumbnail);
                    } else {
                        const documentIcon = document.createElement('i');
                        documentIcon.className = 'fa-solid fa-file-lines wholesale-proof-document';
                        proofSlot.append(documentIcon);
                    }
                    const viewProof = document.createElement('a');
                    viewProof.className = 'btn btn-sm btn-outline-primary';
                    viewProof.href = proofLink.href;
                    viewProof.target = '_blank';
                    viewProof.rel = 'noopener';
                    viewProof.innerHTML = '<i class="fa-solid fa-eye me-1"></i>View';
                    proofSlot.append(viewProof);
                } else {
                    const proofNote = document.createElement('span');
                    proofNote.className = 'wholesale-proof-empty-note';
                    proofNote.innerHTML = '<i class="fa-regular fa-circle-xmark me-1"></i>No payment proof attached';
                    paymentMethod.append(proofNote);
                }
                paymentLayout.append(paymentMethod, proofSlot);
                payment.body.append(paymentLayout);

                const statusPanel = createPanel('Update Status', 'fa-sliders', 'wholesale-detail-status');
                const statusSourceForm = cells[16].querySelector('.status-update-form');
                const statusForm = statusSourceForm?.cloneNode(true);
                let refundForm = cells[16].querySelector('.wholesale-refund-form')?.cloneNode(true);
                let uploadPanel = null;
                if (statusForm) {
                    statusForm.id = 'wholesaleStatusModalForm';
                    statusForm.querySelectorAll('[id]').forEach(element => {
                        const oldId = element.id;
                        element.id = `${oldId}-modal`;
                        statusForm.querySelectorAll(`label[for="${oldId}"]`).forEach(label => label.htmlFor = element.id);
                    });
                    const upload = statusForm.querySelector('.wholesale-proof-upload');
                    if (upload) {
                        uploadPanel = upload;
                        upload.querySelector('input[type="file"]')?.setAttribute('form', statusForm.id);
                    }
                    const statusFields = statusForm.querySelector('.wholesale-status-fields');
                    const statusNote = statusFields?.querySelector('.wholesale-status-note');
                    const statusHelp = statusFields?.querySelector('.wholesale-status-help');
                    const actionRow = document.createElement('div');
                    actionRow.className = 'wholesale-status-actions';
                    const statusBanner = document.createElement('div');
                    statusBanner.className = 'wholesale-status-banner';
                    const bannerIcon = document.createElement('i');
                    bannerIcon.className = `fa-solid ${paymentStatus === 'paid' ? 'fa-lock' : 'fa-circle-info'} me-1`;
                    bannerIcon.dataset.bsToggle = 'tooltip';
                    bannerIcon.title = 'Withholding tax is a reporting reference only; it does not change the customer order value or amount collected.';
                    statusBanner.append(bannerIcon);
                    const bannerText = document.createElement('span');
                    const paymentHelpText = cleanValue(statusHelp?.querySelector('.payment-balance-help')?.textContent);
                    bannerText.textContent = [
                        paymentStatus === 'paid'
                            ? 'Payment is fully paid and locked. Delivery remains editable.'
                            : paymentHelpText,
                        'Withholding tax is a reporting reference and does not change the customer order value or Collected.',
                    ].filter(Boolean).join(' ');
                    statusBanner.append(bannerText);
                    const amountError = statusHelp?.querySelector('.payment-amount-error');
                    if (amountError) statusBanner.append(amountError);
                    statusNote?.remove();
                    statusHelp?.remove();
                    const saveButton = statusForm.querySelector('.wholesale-save-button');
                    if (saveButton) {
                        saveButton.disabled = true;
                        actionRow.append(statusBanner, saveButton);
                        statusFields?.after(actionRow);
                    }
                    statusPanel.body.append(statusForm);
                }
                if (refundForm) {
                    refundForm.classList.remove('mt-2');
                    refundForm.querySelector('button')?.classList.remove('w-100');
                    refundForm.querySelector('small')?.remove();
                    footerActions?.prepend(refundForm);
                }
                if (proofDetails) {
                    proofDetails.open = true;
                    proofDetails.classList.add('d-block');
                }
                if (uploadPanel && !proofLink) {
                    uploadPanel.classList.remove('d-none');
                    uploadPanel.querySelector('.wholesale-proof-input')?.setAttribute('form', statusForm.id);
                    proofSlot.replaceChildren(uploadPanel);
                }
                leftColumn.append(items.panel, payment.panel);
                rightColumn.append(orderInfo.panel, fulfillment.panel);
                main.append(leftColumn, rightColumn);
                if (!statusForm) {
                    const statusMessage = document.createElement('div');
                    statusMessage.className = 'alert alert-secondary mb-0';
                    const locked = orderStatus.toLowerCase() === 'refunded';
                    statusMessage.innerHTML = `<i class="fa-solid ${locked ? 'fa-lock' : 'fa-circle-info'} me-1"></i>`;
                    statusMessage.append(document.createTextNode(
                        locked
                            ? 'This order is refunded and locked. Payment and status changes are unavailable.'
                            : 'Status updates are unavailable for this order.'
                    ));
                    statusPanel.body.append(statusMessage);
                }
                content.append(main, statusPanel.panel);

                content.querySelectorAll('[data-bs-toggle="tooltip"]').forEach(element => {
                    if (window.bootstrap?.Tooltip) bootstrap.Tooltip.getOrCreateInstance(element);
                });
                setupStatusDirtyState(statusForm);
                setupProofDropzone(uploadPanel);
            });

            content.addEventListener('click', event => {
                const replacementButton = event.target.closest('.wholesale-replace-button');
                if (!replacementButton) return;
                event.preventDefault();
                event.stopPropagation();
                const detailsModal = bootstrap.Modal.getInstance(modalElement);
                modalElement.addEventListener('hidden.bs.modal', () => {
                    bootstrap.Modal.getOrCreateInstance(document.getElementById('wholesaleReplacementModal')).show(replacementButton);
                }, { once: true });
                detailsModal?.hide();
            });
        })();

        const updatePaymentAmountState = (form) => {
            const paymentSelect = form.querySelector('[name="payment_status"]');
            const amountInput = form.querySelector('.amount-paid-input');
            const help = form.querySelector('.payment-balance-help');
            const error = form.querySelector('.payment-amount-error');
            if (!paymentSelect || !amountInput || amountInput.disabled) return true;

            const total = Number(amountInput.dataset.orderTotal || 0);
            const paid = Math.max(0, Number(amountInput.value || 0));
            const invalidUnpaidAmount = paymentSelect.value === 'unpaid' && paid > 0;
            error?.classList.toggle('d-none', !invalidUnpaidAmount);
            amountInput.setCustomValidity(invalidUnpaidAmount
                ? 'An unpaid order cannot have an amount paid. Enter 0.00 or select Partial.'
                : '');

            if (help && paymentSelect.value === 'partial') {
                const remaining = Math.max(0, total - paid);
                help.textContent = `Remaining balance after this payment: ₱${remaining.toLocaleString('en-PH', { minimumFractionDigits: 2, maximumFractionDigits: 2 })}.`;
            } else if (help && paymentSelect.value === 'paid' && amountInput.readOnly) {
                help.textContent = 'Paid selected. Amount is set to the full order total.';
            } else if (help && paymentSelect.value === 'unpaid') {
                help.textContent = 'For Unpaid, the amount paid must be ₱0.00.';
            }

            return !invalidUnpaidAmount;
        };

        const statusFormValues = (form) => [...form.elements]
            .filter(element => element.name && !['_token', '_method'].includes(element.name))
            .map(element => {
                if (element.type === 'file') {
                    return `${element.name}:${[...element.files].map(file => `${file.name}:${file.size}`).join(',')}`;
                }
                if (element.type === 'checkbox' || element.type === 'radio') {
                    return `${element.name}:${element.checked ? element.value : ''}`;
                }

                return `${element.name}:${element.value}`;
            })
            .join('|');
        const updateStatusDirtyState = (form) => {
            if (!form || form.id !== 'wholesaleStatusModalForm') return;
            const button = form.querySelector('.wholesale-save-button');
            if (!button) return;
            const changed = form.dataset.initialValues !== statusFormValues(form);
            button.disabled = !changed;
            button.title = changed ? 'Save the changes made to this order.' : 'No changes to save.';
        };
        const setupStatusDirtyState = (form) => {
            if (!form) return;
            form.dataset.initialValues = statusFormValues(form);
            updateStatusDirtyState(form);
        };
        const setupProofDropzone = (wrapper) => {
            if (!wrapper) return;
            const input = wrapper.querySelector('.wholesale-proof-input');
            const zone = wrapper.querySelector('.wholesale-proof-dropzone');
            const filename = wrapper.querySelector('.wholesale-proof-filename');
            if (!input || !zone) return;
            const updateFilename = () => {
                filename.textContent = input.files.length ? `Selected: ${input.files[0].name}` : '';
                updateStatusDirtyState(input.form);
            };
            input.addEventListener('change', updateFilename);
            ['dragenter', 'dragover'].forEach(type => zone.addEventListener(type, event => {
                event.preventDefault();
                zone.classList.add('is-dragging');
            }));
            ['dragleave', 'drop'].forEach(type => zone.addEventListener(type, event => {
                event.preventDefault();
                zone.classList.remove('is-dragging');
            }));
            zone.addEventListener('drop', event => {
                const file = event.dataTransfer?.files?.[0];
                if (!file) return;
                const transfer = new DataTransfer();
                transfer.items.add(file);
                input.files = transfer.files;
                input.dispatchEvent(new Event('change', { bubbles: true }));
            });
        };

        document.addEventListener('change', event => {
            if (event.target.matches('.status-update-form [name="payment_status"]')) {
                const form = event.target.form;
                const amountInput = form.querySelector('.amount-paid-input');
                if (amountInput && !amountInput.disabled) {
                    if (event.target.value === 'paid') {
                        if (!amountInput.readOnly) {
                            amountInput.dataset.previousPartialAmount = amountInput.value;
                        }
                        amountInput.value = Number(amountInput.dataset.orderTotal || 0).toFixed(2);
                        amountInput.readOnly = true;
                    } else if (amountInput.readOnly) {
                        amountInput.readOnly = false;
                        amountInput.value = amountInput.dataset.previousPartialAmount || '';
                        delete amountInput.dataset.previousPartialAmount;
                    }
                }
                updatePaymentAmountState(form);
            }
            if (event.target.closest('.status-update-form')) {
                updateStatusDirtyState(event.target.form);
            }
        });
        document.addEventListener('input', event => {
            if (event.target.matches('.status-update-form .amount-paid-input')) {
                updatePaymentAmountState(event.target.form);
            }
            if (event.target.closest('.status-update-form')) {
                updateStatusDirtyState(event.target.form);
            }
        });
        let pendingRefundForm = null;
        const refundConfirmModalElement = document.getElementById('wholesaleRefundConfirmModal');
        const refundConfirmModal = refundConfirmModalElement && window.bootstrap
            ? bootstrap.Modal.getOrCreateInstance(refundConfirmModalElement)
            : null;
        const refundConfirmButton = document.getElementById('wholesaleRefundConfirmButton');

        document.addEventListener('submit', event => {
            const refundForm = event.target.closest('.wholesale-refund-form');
            if (refundForm) {
                if (refundForm.dataset.confirmed === 'true') {
                    delete refundForm.dataset.confirmed;
                } else {
                    event.preventDefault();
                    pendingRefundForm = refundForm;
                    document.getElementById('wholesaleRefundOrder').textContent = `#${refundForm.dataset.orderNumber}`;
                    document.getElementById('wholesaleRefundAmount').textContent =
                        `₱${Number(refundForm.dataset.refundAmount || 0).toLocaleString('en-PH', { minimumFractionDigits: 2, maximumFractionDigits: 2 })}`;
                    refundConfirmModal?.show();
                    return;
                }
            }

            if (event.target.matches('.status-update-form')) {
                if (!updatePaymentAmountState(event.target)) {
                    event.preventDefault();
                    event.target.querySelector('.amount-paid-input')?.reportValidity();
                    return;
                }
                if (event.target.id === 'wholesaleStatusModalForm' && event.target.querySelector('.wholesale-save-button')?.disabled) {
                    event.preventDefault();
                }
            }
        });
        refundConfirmButton?.addEventListener('click', () => {
            if (!pendingRefundForm) return;

            const form = pendingRefundForm;
            pendingRefundForm = null;
            form.dataset.confirmed = 'true';
            refundConfirmButton.disabled = true;
            form.requestSubmit();
        });
        refundConfirmModalElement?.addEventListener('hidden.bs.modal', () => {
            pendingRefundForm = null;
            if (refundConfirmButton) refundConfirmButton.disabled = false;
        });
        document.querySelectorAll('.status-update-form').forEach(updatePaymentAmountState);
        (() => {
            const modal = document.getElementById('wholesaleReplacementModal');
            if (!modal) return;
            const form = document.getElementById('wholesaleReplacementForm');
            const search = document.getElementById('wholesaleReplacementSearch');
            const productId = document.getElementById('wholesaleReplacementProductId');
            const optionsList = document.getElementById('wholesaleReplacementOptions');
            const quantity = document.getElementById('wholesaleReplacementQuantity');
            const replacementQuantity = document.getElementById('wholesaleNewQuantity');
            const stockHelp = document.getElementById('wholesaleReplacementStock');
            const productHelp = document.getElementById('wholesaleOriginalProduct');
            const limitHelp = document.getElementById('wholesaleReplacementLimit');
            const discount = document.getElementById('wholesaleReplacementDiscount');
            const summary = document.getElementById('wholesaleExchangeSummary');
            const paymentAmount = form.querySelector('[name="exchange_payment_amount"]');
            const shippingType = form.querySelector('[data-wholesale-replacement-shipping-type]');
            const shippingAmount = form.querySelector('[name="replacement_shipping_fee_amount"]');
            const shippingAmountWrap = form.querySelector('[data-wholesale-replacement-shipping-amount-wrap]');
            const endpoint = @json(route('hub.products.search.ajax', $hub->id));
            let products = new Map();
            let originalProductId = null;
            let originalUnitPrice = 0;
            let remainingQuantity = 1;
            let usesExchangeCredit = true;
            let isPartialWholesale = false;
            let orderSubTotal = 0;
            let orderShipping = 0;
            let orderDiscount = 0;
            let orderWithholding = 0;
            let amountAlreadyPaid = 0;
            let timer;
            const money = value => `₱${Number(value || 0).toLocaleString('en-PH', { minimumFractionDigits: 2, maximumFractionDigits: 2 })}`;
            const calculateExchange = () => {
                const credit = usesExchangeCredit ? originalUnitPrice * Math.max(1, Number(quantity.value || 1)) : 0;
                const mainUnitPrice = Number(productId.dataset.unitPrice || 0);
                const mainDiscount = Math.min(100, Math.max(0, Number(discount.value || 0)));
                const mainQuantity = Math.max(1, Number(replacementQuantity.value || 1));
                const mainTotal = mainUnitPrice * (1 - (mainDiscount / 100)) * mainQuantity;
                let extraTotal = 0;
                form.querySelectorAll('[data-exchange-line]').forEach(row => {
                    const unitPrice = Number(row.querySelector('[data-exchange-product]')?.dataset.unitPrice || 0);
                    const rowQuantity = Math.max(1, Number(row.querySelector('[data-exchange-quantity]')?.value || 1));
                    const rowDiscount = Math.min(100, Math.max(0, Number(row.querySelector('[data-exchange-discount]')?.value || 0)));
                    extraTotal += unitPrice * (1 - (rowDiscount / 100)) * rowQuantity;
                });
                const shippingFee = shippingType.value === 'Custom Amount'
                    ? Math.max(0, Number(shippingAmount.value || 0))
                    : 0;
                const basketTotal = mainTotal + extraTotal + shippingFee;
                const revisedSubTotal = Math.max(0, orderSubTotal - (originalUnitPrice * Math.max(1, Number(quantity.value || 1))) + mainTotal + extraTotal);
                const revisedOrderTotal = Math.max(0, revisedSubTotal - (revisedSubTotal * (orderDiscount / 100)) - (revisedSubTotal * (orderWithholding / 100)) + orderShipping + shippingFee);
                const amountDue = isPartialWholesale
                    ? Math.max(0, revisedOrderTotal - amountAlreadyPaid)
                    : Math.max(0, basketTotal - credit);
                const remainingStoreCredit = isPartialWholesale ? 0 : Math.max(0, credit - basketTotal);
                const appliedCredit = isPartialWholesale ? Math.max(0, basketTotal - amountDue) : credit - remainingStoreCredit;
                summary.querySelector('[data-exchange-credit]').textContent = money(appliedCredit);
                summary.querySelector('[data-exchange-main-total]').textContent = money(mainTotal);
                summary.querySelector('[data-exchange-extra-total]').textContent = money(extraTotal);
                summary.querySelector('[data-exchange-basket-total]').textContent = money(basketTotal);
                summary.querySelector('[data-exchange-shipping]').textContent = money(shippingFee);
                summary.querySelector('[data-exchange-store-credit]').textContent = money(remainingStoreCredit);
                summary.querySelector('[data-exchange-amount-due]').textContent = remainingStoreCredit > 0
                    ? `Store credit remains ${money(remainingStoreCredit)}`
                    : `Customer adds ${money(amountDue)}`;
                summary.querySelector('[data-exchange-formula]').textContent = mainUnitPrice > 0
                    ? (isPartialWholesale
                        ? `Revised order total ${money(revisedOrderTotal)} less payment already received ${money(amountAlreadyPaid)}.`
                        : `${money(mainUnitPrice)} × ${mainQuantity} less ${mainDiscount.toFixed(2)}% discount, plus additional products.`)
                    : 'Choose a replacement product to calculate the total.';
                const status = summary.querySelector('[data-exchange-calculation-status]');
                status.className = `badge ${remainingStoreCredit > 0 ? 'bg-info text-dark' : (amountDue > 0 ? 'bg-danger' : 'bg-success')}`;
                status.textContent = remainingStoreCredit > 0 ? `Store credit ${money(remainingStoreCredit)}` : (amountDue > 0 ? `Additional payment ${money(amountDue)}` : 'Credit fully used');
                paymentAmount.value = amountDue.toFixed(2);
            };

            const updateReplacementShipping = () => {
                const custom = shippingType.value === 'Custom Amount';
                shippingAmountWrap.classList.toggle('d-none', !custom);
                shippingAmount.disabled = !custom;
                if (!custom) shippingAmount.value = '0.00';
                calculateExchange();
            };

            modal.addEventListener('show.bs.modal', event => {
                const button = event.relatedTarget;
                form.action = button.dataset.action;
                originalProductId = String(button.dataset.originalProductId);
                originalUnitPrice = Number(button.dataset.originalUnitPrice || 0);
                isPartialWholesale = button.dataset.paymentStatus === 'partial';
                usesExchangeCredit = !isPartialWholesale;
                orderSubTotal = Number(button.dataset.orderSubtotal || 0);
                orderShipping = Number(button.dataset.orderShipping || 0);
                orderDiscount = Number(button.dataset.orderDiscount || 0);
                orderWithholding = Number(button.dataset.orderWithholding || 0);
                amountAlreadyPaid = Number(button.dataset.amountPaid || 0);
                form.querySelector('[data-wholesale-partial-payment-note]').classList.toggle('d-none', !isPartialWholesale);
                summary.querySelector('[data-exchange-credit-label]').textContent = isPartialWholesale ? 'Prior payment applied' : 'Original exchange credit';
                remainingQuantity = Number(button.dataset.remaining || 1);
                productHelp.textContent = `Original item: ${button.dataset.product}`;
                quantity.max = remainingQuantity;
                quantity.value = 1;
                replacementQuantity.value = 1;
                discount.value = '0.00';
                limitHelp.textContent = `${remainingQuantity} unit(s) still available for replacement.`;
                search.value = '';
                productId.value = '';
                search.setCustomValidity('');
                optionsList.replaceChildren();
                products.clear();
                productId.dataset.unitPrice = '0';
                shippingType.value = 'Free';
                shippingAmount.value = '0.00';
                shippingAmount.disabled = true;
                shippingAmountWrap.classList.add('d-none');
                form.querySelector('[data-exchange-line-list]')?.replaceChildren();
                stockHelp.textContent = 'Choose an active product from this hub.';
                form.querySelector('[name="reason"]').value = '';
                calculateExchange();
            });

            search.addEventListener('input', () => {
                productId.value = '';
                productId.dataset.unitPrice = '0';
                calculateExchange();
                search.setCustomValidity('');
                stockHelp.textContent = 'Choose an active product from this hub.';
                const selected = products.get(search.value);
                if (selected) {
                    productId.value = selected.id;
                    productId.dataset.unitPrice = Number(selected.wholesale_price || selected.sales_price || 0);
                    stockHelp.textContent = `${selected.stock} unit(s) available · Wholesale price ₱${Number(selected.wholesale_price || selected.sales_price || 0).toLocaleString('en-PH', { minimumFractionDigits: 2, maximumFractionDigits: 2 })}`;
                    calculateExchange();
                    return;
                }
                quantity.max = remainingQuantity;
                clearTimeout(timer);
                const query = search.value.trim();
                if (query.length < 1) return;
                timer = setTimeout(async () => {
                    try {
                        const url = new URL(endpoint, location.origin);
                        url.searchParams.set('q', query);
                        url.searchParams.set('active_only', '1');
                        const response = await fetch(url, { headers: { Accept: 'application/json' } });
                        if (!response.ok) throw new Error('Search unavailable');
                        const results = await response.json();
                        optionsList.replaceChildren();
                        products.clear();
                        results.filter(product => String(product.id) !== originalProductId && Number(product.stock) > 0).forEach(product => {
                            const barcode = product.barcode ? ` · Barcode: ${product.barcode}` : '';
                            const label = `${product.item_id || product.id} — ${product.name || 'Unnamed product'}${barcode} (stock: ${product.stock})`;
                            products.set(label, product);
                            const option = document.createElement('option');
                            option.value = label;
                            optionsList.append(option);
                        });
                    } catch (error) {
                        window.AppAlert?.show('Product search is unavailable. Please try again.', 'error');
                    }
                }, 250);
            });

            [quantity, replacementQuantity, discount].forEach(input => input.addEventListener('input', calculateExchange));
            shippingType.addEventListener('change', updateReplacementShipping);
            shippingAmount.addEventListener('input', calculateExchange);
            form.addEventListener('exchange:changed', calculateExchange);

            form.addEventListener('submit', event => {
                if (!productId.value) {
                    event.preventDefault();
                    search.setCustomValidity('Select a replacement product from the suggestions.');
                    search.reportValidity();
                }
            });
        })();
    </script>
    @endpush
