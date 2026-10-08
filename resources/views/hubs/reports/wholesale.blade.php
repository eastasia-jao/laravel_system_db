<style>
    .wholesale-report-card { border: 0; border-radius: 16px; overflow: hidden; box-shadow: 0 10px 28px rgba(30, 64, 175, .08); }
    .wholesale-report-card .card-header { background: linear-gradient(135deg, #eff6ff, #f8fafc); border-bottom: 1px solid #dbeafe; padding: 1rem 1.25rem; }
    .wholesale-report-card .table { min-width: 820px; width: 100%; table-layout: fixed; }
    .wholesale-report-card th:nth-child(1) { width: 30%; }
    .wholesale-report-card th:nth-child(2) { width: 17%; }
    .wholesale-report-card th:nth-child(4), .wholesale-report-card th:nth-child(5) { width: 14%; }
    .wholesale-report-card th:nth-child(8), .wholesale-report-card th:nth-child(10) { width: 12.5%; }
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
    .wholesale-report-card th:nth-child(6), .wholesale-report-card td:nth-child(6),
    .wholesale-report-card th:nth-child(7), .wholesale-report-card td:nth-child(7),
    .wholesale-report-card th:nth-child(9), .wholesale-report-card td:nth-child(9),
    .wholesale-report-card th:nth-child(11), .wholesale-report-card td:nth-child(11),
    .wholesale-report-card th:nth-child(12), .wholesale-report-card td:nth-child(12),
    .wholesale-report-card th:nth-child(13), .wholesale-report-card td:nth-child(13),
    .wholesale-report-card th:nth-child(14), .wholesale-report-card td:nth-child(14),
    .wholesale-report-card th:nth-child(15), .wholesale-report-card td:nth-child(15),
    .wholesale-report-card th:nth-child(16), .wholesale-report-card td:nth-child(16) { display: none; }
    .wholesale-report-card tbody td { padding: .65rem .6rem; }
    .wholesale-customer-summary { min-width: 0; }
    .wholesale-customer-summary .customer-copy { min-width: 0; }
    .wholesale-customer-summary .wholesale-view-details { flex: 0 0 auto; white-space: nowrap; }
    .wholesale-table-status { display: inline-flex; align-items: center; min-width: 76px; justify-content: center; padding: .32rem .55rem; border-radius: 999px; font-size: .68rem; font-weight: 700; letter-spacing: .02em; }
    .wholesale-detail-grid { display: flex; flex-direction: column; gap: .8rem; }
    .wholesale-detail-groups { display: grid; grid-template-columns: repeat(3, minmax(0, 1fr)); gap: .8rem; align-items: start; }
    .wholesale-info-panel { overflow: hidden; border: 1px solid #dbe4f0; border-radius: 12px; background: #fff; }
    .wholesale-info-panel-title { margin: 0; padding: .7rem .85rem; border-bottom: 1px solid #dbe4f0; background: #eef5ff; color: #1e3a8a; font-size: .78rem; font-weight: 700; }
    .wholesale-info-panel-body { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); }
    .wholesale-info-panel .wholesale-detail-section { min-height: 70px; padding: .7rem .85rem; border: 0; border-right: 1px solid #e5e7eb; border-bottom: 1px solid #e5e7eb; border-radius: 0; background: #fff; }
    .wholesale-financial-panel .wholesale-info-panel-body { grid-template-columns: repeat(3, minmax(0, 1fr)); }
    .wholesale-financial-panel .wholesale-detail-section { min-height: auto; padding: .8rem 1rem; }
    .wholesale-financial-panel .wholesale-detail-section:nth-child(1) { border-left: 4px solid #2563eb; }
    .wholesale-financial-panel .wholesale-detail-section:nth-child(2) { border-left: 4px solid #059669; }
    .wholesale-financial-panel .wholesale-detail-section:nth-child(3) { border-left: 4px solid #d97706; }
    .wholesale-status-panel .wholesale-info-panel-body { display: block; }
    .wholesale-status-panel .wholesale-detail-section { border-right: 0; border-bottom: 0; }
    .wholesale-status-panel .wholesale-detail-label { display: none; }
    .wholesale-detail-section { padding: .85rem; border: 1px solid #e2e8f0; border-radius: 12px; background: #f8fafc; }
    .wholesale-detail-label { margin-bottom: .35rem; color: #64748b; font-size: .7rem; font-weight: 700; letter-spacing: .04em; text-transform: uppercase; }
    .wholesale-detail-items details > div { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: .65rem; }
    .wholesale-detail-items .item-line { margin: 0; padding: .7rem; border: 1px solid #dbe4f0 !important; border-radius: 10px; background: #fff; }
    .wholesale-detail-payment details .border { display: grid; grid-template-columns: minmax(180px, 1fr) minmax(300px, 520px); column-gap: 1rem; align-items: start; }
    .wholesale-detail-payment details .border > .fw-semibold { grid-column: 1; grid-row: 1; }
    .wholesale-detail-payment details .border > .small { grid-column: 1; grid-row: 2; }
    .wholesale-detail-payment details .border > .payment-proof-empty { grid-column: 1; grid-row: 3; margin-top: 0 !important; }
    .wholesale-detail-payment details .border > .mt-3 { grid-column: 2; grid-row: 1 / span 2; margin-top: 0 !important; }
    .wholesale-detail-payment details .border > .mt-3 a { height: 180px !important; }
    .wholesale-detail-status .status-update-form { display: flex; flex-direction: column; gap: .55rem; }
    .wholesale-detail-status .status-update-form .input-group { margin-bottom: 0 !important; }
    @media (max-width: 1199.98px) { .wholesale-detail-groups { grid-template-columns: repeat(2, minmax(0, 1fr)); } .wholesale-status-panel { grid-column: 1 / -1; } }
    @media (max-width: 767.98px) { .wholesale-detail-groups { grid-template-columns: 1fr; } .wholesale-status-panel { grid-column: auto; } }
    @media (max-width: 767.98px) { .wholesale-detail-items details > div, .wholesale-detail-payment details .border, .wholesale-detail-status .status-update-form { display: block; } .wholesale-detail-payment details .border > .mt-3 { margin-top: 1rem !important; } }
    @media (max-width: 575.98px) { .wholesale-info-panel-body, .wholesale-financial-panel .wholesale-info-panel-body { grid-template-columns: 1fr; } }
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
            ] as $state => $definition)
                <a href="{{ route('hub.report', ['hub' => $hub->id, 'channel' => 'wholesale', 'transaction_state' => $state, 'date_from' => $dateFrom, 'date_to' => $dateTo]) }}"
                   class="btn btn-sm {{ $transactionState === $state ? 'btn-'.$definition['color'] : 'btn-outline-'.$definition['color'] }} mb-3">
                    {{ $definition['label'] }}
                    <span class="badge {{ $transactionState === $state ? 'text-bg-light' : 'text-bg-secondary' }} ms-1">{{ number_format($stateCounts[$state]) }}</span>
                </a>
            @endforeach
        </div>
        <div class="small text-muted mb-3"><strong>Completed</strong> means fully paid and delivered. Everything else remains <strong>Open</strong>.</div>
    </div>
    <div class="table-responsive">
        <table class="table table-bordered table-sm align-middle mb-0">
            <thead class="table-success">
                <tr><th>Company/Customer</th><th>Purchase Date</th><th>MOP</th><th class="text-end">Order Value</th><th class="text-end">Collected</th><th>Withholding Tax</th><th>Invoice #</th><th>Payment</th><th>Order</th><th>Delivery</th><th>Delivery/Pickup Date</th><th>Type</th><th class="text-end">Shipping Amount</th><th>Courier</th><th>Check Date</th><th>Update Status</th></tr>
            </thead>
            <tbody>
                @forelse($transactions as $transaction)
                    @php
                        $total = (float) ($transaction->grand_total ?: $transaction->total_amount ?: $transaction->items->sum('line_total'));
                        $paymentStatus = $transaction->payment_status ?? 'unpaid';
                        $deliveryStatus = $transaction->delivery_status ?? 'pending';
                        $collected = min($total, max(0, (float) ($transaction->amount_paid ?? 0)));
                        $paymentLocked = $paymentStatus === 'paid';
                    @endphp
                    <tr data-wholesale-order-row="{{ $transaction->id }}">
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
                                        <div class="item-line">
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
                        <td>{{ $transaction->withholding_tax ? number_format($transaction->withholding_tax, 2).'%' : '—' }} {{ $transaction->withholding_tax_amount ? '(₱'.number_format($transaction->withholding_tax_amount, 2).')' : '' }}</td>
                        <td>{{ $transaction->order_number }}</td>
                        <td><span class="wholesale-table-status {{ $paymentStatus === 'paid' ? 'bg-success-subtle text-success' : ($paymentStatus === 'partial' ? 'bg-warning-subtle text-warning-emphasis' : 'bg-secondary-subtle text-secondary-emphasis') }}">{{ strtoupper($paymentStatus) }}</span></td>
                        <td>{{ strtoupper($transaction->status ?? 'confirmed') }}</td>
                        <td><span class="wholesale-table-status {{ $deliveryStatus === 'delivered' ? 'bg-success-subtle text-success' : ($deliveryStatus === 'cancelled' ? 'bg-danger-subtle text-danger' : 'bg-info-subtle text-info-emphasis') }}">{{ strtoupper($deliveryStatus) }}</span></td>
                        <td>{{ optional($transaction->delivery_date ?? $transaction->date_of_arrangement)->format('F d, Y') ?? '—' }}</td>
                        <td>{{ strtoupper($transaction->shipping_fee_type ?: '—') }}</td>
                        <td class="text-end">₱{{ number_format($transaction->shipping_fee_amount, 2) }}</td>
                        <td>{{ $transaction->courier ?: '—' }}</td>
                        <td>{{ optional($transaction->check_date)->format('F d, Y') ?? '—' }}</td>
                        <td class="wholesale-status">
                            <form action="{{ route('sales.status.update', $transaction->id) }}" method="POST" class="status-update-form">
                                @csrf
                                @method('PATCH')
                                <div class="input-group input-group-sm mb-1">
                                    <select name="payment_status" class="form-select" aria-label="Payment status" @disabled($paymentLocked)>
                                        <option value="unpaid" @selected($paymentStatus === 'unpaid')>Unpaid</option>
                                        <option value="partial" @selected($paymentStatus === 'partial')>Partial</option>
                                        <option value="paid" @selected($paymentStatus === 'paid')>Paid</option>
                                    </select>
                                    @if($paymentLocked)
                                        <input type="hidden" name="payment_status" value="paid">
                                    @endif
                                    <select name="delivery_status" class="form-select" aria-label="Delivery status">
                                        @foreach(['pending', 'preparing', 'shipped', 'delivered', 'cancelled'] as $status)
                                            <option value="{{ $status }}" @selected($deliveryStatus === $status)>{{ ucfirst($status) }}</option>
                                        @endforeach
                                    </select>
                                </div>
                                <div class="input-group input-group-sm">
                                    <span class="input-group-text">₱</span>
                                    <input type="number" name="amount_paid" class="form-control amount-paid-input" min="0" max="{{ $total }}" step="0.01" value="{{ $transaction->amount_paid ?? 0 }}" placeholder="Amount paid" data-order-total="{{ $total }}" @disabled($paymentLocked)>
                                    @if($paymentLocked)
                                        <input type="hidden" name="amount_paid" value="{{ $transaction->amount_paid ?? $total }}">
                                    @endif
                                    <button type="submit" class="btn btn-primary">Save</button>
                                </div>
                                <small class="text-muted payment-balance-help">{{ $paymentLocked ? 'Payment is fully paid and locked. Delivery status remains editable.' : 'For Partial, enter an amount below ₱'.number_format($total, 2).'.' }}</small>
                                <small class="text-danger d-none payment-amount-error">An unpaid order cannot have an amount paid. Enter 0.00 or select Partial.</small>
                            </form>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="16" class="text-center text-muted py-4">No wholesale sales found.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
<div class="modal fade" id="wholesaleDetailsModal" tabindex="-1" aria-labelledby="wholesaleDetailsLabel" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-centered modal-dialog-scrollable">
        <div class="modal-content border-0 shadow">
            <div class="modal-header bg-primary text-white">
                <div>
                    <h5 class="modal-title" id="wholesaleDetailsLabel"><i class="fa-solid fa-receipt me-2"></i>Wholesale Order Details</h5>
                    <div class="small opacity-75" id="wholesaleDetailsSubtitle"></div>
                </div>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body bg-light">
                <div class="wholesale-detail-grid" id="wholesaleDetailsContent"></div>
            </div>
            <div class="modal-footer bg-white">
                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>
@can('manage-sales-status')
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
                            <div class="col-6 col-md-3"><span class="text-muted d-block">Original exchange credit</span><strong data-exchange-credit>₱0.00</strong></div>
                            <div class="col-6 col-md-3"><span class="text-muted d-block">Main replacement total</span><strong data-exchange-main-total>₱0.00</strong></div>
                            <div class="col-6 col-md-3"><span class="text-muted d-block">Additional products</span><strong data-exchange-extra-total>₱0.00</strong></div>
                            <div class="col-6 col-md-3"><span class="text-muted d-block">Replacement basket total</span><strong data-exchange-basket-total>₱0.00</strong></div>
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
                $kpiOrderValue = $allTransactions->sum(fn ($transaction) => (float) ($transaction->grand_total ?: $transaction->total_amount ?: $transaction->items->sum('line_total')));
                $kpiCollected = $wholesaleCollectedSales;
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
            if (!modalElement || !content) return;

            const labels = ['Company / Customer', 'Purchase Date', 'Mode of Payment', 'Order Value', 'Collected', 'Withholding Tax', 'Invoice Number', 'Payment Status', 'Order Status', 'Delivery Status', 'Delivery / Pickup Date', 'Shipping Type', 'Shipping Amount', 'Courier', 'Check Date', 'Update Status'];

            modalElement.addEventListener('show.bs.modal', event => {
                const button = event.relatedTarget;
                const row = document.querySelector(`[data-wholesale-order-row="${button?.dataset.orderId}"]`);
                if (!row) return;

                subtitle.textContent = `${button.dataset.customer} · Order #${button.dataset.orderNumber}`;
                content.replaceChildren();

                const buildSection = (cell, index) => {
                    const section = document.createElement('section');
                    section.className = 'wholesale-detail-section';
                    if (index === 0) section.classList.add('wholesale-detail-items');
                    if (index === 2) section.classList.add('wholesale-detail-payment');
                    if (index === 15) section.classList.add('wholesale-detail-status');
                    const label = document.createElement('div');
                    label.className = 'wholesale-detail-label';
                    label.textContent = labels[index];
                    const value = document.createElement('div');
                    value.append(...[...cell.childNodes].map(node => node.cloneNode(true)));
                    value.querySelector('.wholesale-view-details')?.remove();
                    value.querySelectorAll('details').forEach(details => details.open = true);
                    section.append(label, value);
                    return section;
                };

                const cells = [...row.cells];
                content.append(buildSection(cells[0], 0));
                content.append(buildSection(cells[2], 2));

                const buildPanel = (title, icon, indexes, extraClass = '') => {
                    const panel = document.createElement('section');
                    panel.className = `wholesale-info-panel ${extraClass}`;
                    const heading = document.createElement('h6');
                    heading.className = 'wholesale-info-panel-title';
                    heading.innerHTML = `<i class="fa-solid ${icon} me-2"></i>${title}`;
                    const body = document.createElement('div');
                    body.className = 'wholesale-info-panel-body';
                    indexes.forEach(index => body.append(buildSection(cells[index], index)));
                    panel.append(heading, body);
                    return panel;
                };

                content.append(buildPanel('Financial Summary', 'fa-chart-line', [3, 4, 5], 'wholesale-financial-panel'));
                const groups = document.createElement('div');
                groups.className = 'wholesale-detail-groups';
                groups.append(
                    buildPanel('Order Information', 'fa-file-invoice', [1, 6, 7, 8]),
                    buildPanel('Fulfillment Details', 'fa-truck', [9, 10, 11, 12, 13, 14]),
                    buildPanel('Update Status', 'fa-sliders', [15], 'wholesale-status-panel')
                );
                content.append(groups);
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
            if (!paymentSelect || !amountInput || !help || amountInput.disabled) return true;

            const total = Number(amountInput.dataset.orderTotal || 0);
            const paid = Math.max(0, Number(amountInput.value || 0));
            const invalidUnpaidAmount = paymentSelect.value === 'unpaid' && paid > 0;
            error?.classList.toggle('d-none', !invalidUnpaidAmount);
            amountInput.setCustomValidity(invalidUnpaidAmount
                ? 'An unpaid order cannot have an amount paid. Enter 0.00 or select Partial.'
                : '');

            if (paymentSelect.value === 'partial') {
                const remaining = Math.max(0, total - paid);
                help.textContent = `Remaining balance after this payment: ₱${remaining.toLocaleString('en-PH', { minimumFractionDigits: 2, maximumFractionDigits: 2 })}.`;
            } else if (paymentSelect.value === 'paid' && amountInput.readOnly) {
                help.textContent = 'Paid selected. Amount is set to the full order total.';
            } else if (paymentSelect.value === 'unpaid') {
                help.textContent = `For Unpaid, the amount paid must be ₱0.00.`;
            }

            return !invalidUnpaidAmount;
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
        });
        document.addEventListener('input', event => {
            if (event.target.matches('.status-update-form .amount-paid-input')) {
                updatePaymentAmountState(event.target.form);
            }
        });
        document.addEventListener('submit', event => {
            if (event.target.matches('.status-update-form') && !updatePaymentAmountState(event.target)) {
                event.preventDefault();
                event.target.querySelector('.amount-paid-input')?.reportValidity();
            }
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
            let timer;
            const money = value => `₱${Number(value || 0).toLocaleString('en-PH', { minimumFractionDigits: 2, maximumFractionDigits: 2 })}`;
            const calculateExchange = () => {
                const credit = originalUnitPrice * Math.max(1, Number(quantity.value || 1));
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
                const amountDue = Math.max(0, basketTotal - credit);
                const shortfall = Math.max(0, credit - basketTotal);
                summary.querySelector('[data-exchange-credit]').textContent = money(credit);
                summary.querySelector('[data-exchange-main-total]').textContent = money(mainTotal);
                summary.querySelector('[data-exchange-extra-total]').textContent = money(extraTotal);
                summary.querySelector('[data-exchange-basket-total]').textContent = money(basketTotal);
                summary.querySelector('[data-exchange-shipping]').textContent = money(shippingFee);
                summary.querySelector('[data-exchange-amount-due]').textContent = `Customer adds ${money(amountDue)}`;
                summary.querySelector('[data-exchange-formula]').textContent = mainUnitPrice > 0
                    ? `${money(mainUnitPrice)} × ${mainQuantity} less ${mainDiscount.toFixed(2)}% discount, plus additional products.`
                    : 'Choose a replacement product to calculate the total.';
                const status = summary.querySelector('[data-exchange-calculation-status]');
                status.className = `badge ${shortfall > 0 ? 'bg-warning text-dark' : (amountDue > 0 ? 'bg-danger' : 'bg-success')}`;
                status.textContent = shortfall > 0 ? `Add ${money(shortfall)} more` : (amountDue > 0 ? `Additional payment ${money(amountDue)}` : 'Credit fully used');
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
