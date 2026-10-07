<style>
    .online-order-card { width: 100%; border: 0; box-shadow: 0 4px 14px rgba(15, 23, 42, .07); font-size: .86rem; }
    .online-order-card .order-heading { background: linear-gradient(135deg, #ecfeff, #eff6ff); padding: .75rem 1rem !important; }
    .online-order-card .order-label { color: #64748b; font-size: .62rem; text-transform: uppercase; letter-spacing: .04em; }
    .online-order-card .order-value { font-weight: 600; }
    .online-order-card .card-body { padding: .75rem 1rem !important; }
    .online-order-card .card-footer { padding: 0 1rem .75rem !important; }
    .online-order-card .fs-5 { font-size: 1rem !important; }
    .online-order-card .row { --bs-gutter-y: .55rem; }
    .online-order-card [data-online-delivery-form] { max-width: 260px; }
</style>
<div id="onlineReportImageSource">
    <div class="d-none mb-4" data-online-image-only>
        <div class="d-flex justify-content-between align-items-start border-bottom pb-3 mb-3">
            <div>
                <h2 class="fw-bold text-primary mb-1">Online Sales Report</h2>
                <div>{{ $hub->name }} · Verified sales only</div>
                <small class="text-muted">{{ $dateFrom ?: 'All dates' }} to {{ $dateTo ?: now()->format('Y-m-d') }}</small>
            </div>
            <div class="text-end"><strong>Generated</strong><br><small>{{ now()->format('M d, Y h:i A') }}</small></div>
        </div>
        <section class="sales-summary-panel">
            <div class="sales-summary-heading">
                <div><h5>Activity overview</h5><p>Online sales, delivery, and customer activity for the selected period</p></div>
                <span class="badge rounded-pill text-bg-success">Verified sales</span>
            </div>
            <div class="sales-summary-grid">
                <div class="sales-summary-card is-success"><span class="sales-summary-icon" aria-hidden="true"><i class="fa-solid fa-chart-line"></i></span><div class="sales-summary-copy"><div class="label">Total sales (META &amp; IG)</div><div class="value text-success">₱{{ number_format($metrics['total_sales'], 2) }}</div></div></div>
                <div class="sales-summary-card is-info"><span class="sales-summary-icon" aria-hidden="true"><i class="fa-solid fa-truck"></i></span><div class="sales-summary-copy"><div class="label">Total shipping fee</div><div class="value text-info">₱{{ number_format($metrics['shipping_fees'], 2) }}</div></div></div>
                <div class="sales-summary-card"><span class="sales-summary-icon" aria-hidden="true"><i class="fa-solid fa-receipt"></i></span><div class="sales-summary-copy"><div class="label">No. of transactions</div><div class="value">{{ number_format($totalTransactions) }}</div></div></div>
                <div class="sales-summary-card is-danger"><span class="sales-summary-icon" aria-hidden="true"><i class="fa-solid fa-rotate-left"></i></span><div class="sales-summary-copy"><div class="label">No. of returns</div><div class="value text-danger">{{ number_format($metrics['return_count']) }}</div></div></div>
                <div class="sales-summary-card is-warning"><span class="sales-summary-icon" aria-hidden="true"><i class="fa-solid fa-arrows-rotate"></i></span><div class="sales-summary-copy"><div class="label">No. of replacements</div><div class="value text-warning">{{ number_format($metrics['replacement_count']) }}</div></div></div>
                <div class="sales-summary-card is-danger"><span class="sales-summary-icon" aria-hidden="true"><i class="fa-solid fa-money-bill-transfer"></i></span><div class="sales-summary-copy"><div class="label">Total refund cost</div><div class="value text-danger">₱{{ number_format($metrics['refund_total'], 2) }}</div></div></div>
                <div class="sales-summary-card is-primary"><span class="sales-summary-icon" aria-hidden="true"><i class="fa-solid fa-users"></i></span><div class="sales-summary-copy"><div class="label">Total customers</div><div class="value">{{ number_format($customerMetrics['total']) }}</div></div></div>
                <div class="sales-summary-card is-success"><span class="sales-summary-icon" aria-hidden="true"><i class="fa-solid fa-user-plus"></i></span><div class="sales-summary-copy"><div class="label">New customers</div><div class="value text-success">{{ number_format($customerMetrics['new']) }}</div></div></div>
            </div>
        </section>
    </div>
    <div class="row g-3 mt-4 d-none" data-online-preview-only>
        <div class="col-12">
            <div class="card shadow-sm border-0">
                <div class="card-header bg-white">
                    <span class="fw-bold">Sales by Payment Method</span>
                    <small class="text-muted d-block">Totals use the payment method, bank, and custom values entered on the sale form.</small>
                </div>
                <div class="row g-2 p-3">
                    @forelse($onlinePaymentDisplay as $method => $amount)
                        <div class="col-6 col-md-4 col-xl-3">
                            <div class="border rounded-3 p-2 h-100 text-center">
                                <div class="small fw-semibold text-break">{{ $method === 'OTHERS' ? 'Others' : $method }}</div>
                                <div class="fw-bold text-primary">₱{{ number_format($amount, 2) }}</div>
                                <div class="small text-muted">No. of Transactions: {{ number_format($onlinePaymentDisplayCounts->get($method, 0)) }}</div>
                            </div>
                        </div>
                    @empty
                        <div class="col-12 text-center text-muted py-3">No payment transactions recorded.</div>
                    @endforelse
                </div>
            </div>
        </div>
    </div>
    <div class="mt-4" data-online-preview-remove>
        <div class="row g-3">
            @forelse($transactions as $transaction)
                    @php
                        $saleAmount = $transaction->netOrderTotal((float) ($transaction->sub_total ?: $transaction->items->sum('line_total')));
                        $saleReplacements = $transaction->items->flatMap(fn ($item) => $item->replacements);
                        $replacementDeliveryFee = (float) $saleReplacements
                            ->where('status', 'approved')
                            ->sum('replacement_shipping_fee_amount');
                        $shippingFee = (float) $transaction->shipping_fee_amount;
                        $difference = $transaction->proof_amount === null
                            ? null
                            : (float) $transaction->proof_amount
                                - (float) ($transaction->sub_total ?: $saleAmount);
                    @endphp
                    <div class="col-12">
                        <article class="card online-order-card overflow-hidden">
                            <div class="order-heading p-3">
                                <div class="row align-items-start g-3">
                                <div class="col-12 col-md-4">
                                    <div class="order-label">Client / Company</div>
                                    <div class="fs-5 fw-bold">{{ $transaction->customer_name }}@include('hubs.reports._new-customer-badge')</div>
                                    <div class="small text-muted">{{ optional($transaction->order_date)->format('M d, Y') }} · Order #{{ $transaction->order_number }}</div>
                                    @foreach($saleReplacements->pluck('status')->unique() as $replacementStatus)
                                        <span class="badge mt-1 {{ $replacementStatus === 'approved' ? 'bg-success' : ($replacementStatus === 'pending' ? 'bg-warning text-dark' : 'bg-secondary') }}">Replacement: {{ strtoupper($replacementStatus) }}</span>
                                    @endforeach
                                </div>
                                <div class="col-12 col-md-4 text-md-center">
                                    <div class="order-label">Payment / Bank</div>
                                    <div class="order-value">{{ strtoupper(str_replace('_', ' ', $transaction->mode_of_payment ?: $transaction->custom_mop ?: '—')) }}{{ ($transaction->bank_name ?: $transaction->custom_bank_name) ? ' / '.strtoupper($transaction->bank_name ?: $transaction->custom_bank_name) : '' }}</div>
                                </div>
                                <div class="col-12 col-md-4 text-md-end">
                                    <button type="button" class="btn btn-sm btn-outline-primary text-nowrap" data-bs-toggle="modal" data-bs-target="#online-order-{{ $transaction->id }}"><i class="fa-solid fa-eye me-1"></i> View details</button>
                                </div>
                                </div>
                            </div>
                            <div class="card-body p-3">
                                <div class="row g-3">
                                    <div class="col-6 col-md-2"><div class="order-label">Product subtotal</div><div class="order-value">₱{{ number_format($saleAmount, 2) }}</div><div class="small text-muted">Sale amount after returns</div></div>
                                    <div class="col-6 col-md-2"><div class="order-label">Shipping fee</div><div class="order-value">₱{{ number_format($shippingFee, 2) }}</div>@if($replacementDeliveryFee > 0)<div class="small text-muted">Includes ₱{{ number_format($replacementDeliveryFee, 2) }} replacement shipping</div>@endif</div>
                                    <div class="col-6 col-md-2"><div class="order-label">Proof amount</div><div class="order-value">{{ $transaction->proof_amount !== null ? '₱'.number_format($transaction->proof_amount, 2) : 'Not entered' }}</div></div>
                                    <div class="col-6 col-md-2">
                                        <div class="order-label">Difference</div>
                                        <div class="order-value {{ $difference !== null && abs($difference) >= 0.01 ? 'text-danger' : 'text-success' }}">{{ $difference !== null ? '₱'.number_format($difference, 2) : '—' }}</div>
                                    </div>
                                    <div class="col-12 col-md-2"><div class="order-label">Remarks</div><div class="order-value">{{ $transaction->note ?: '—' }}</div></div>
                                    <div class="col-12 col-md-2">
                                        <div class="order-label mb-1">Delivery status</div>
                                        @can('manage-sales-status')
                                            <form action="{{ route('sales.status.update', $transaction->id) }}" method="POST" class="d-flex flex-wrap gap-2" data-online-delivery-form>
                                                @csrf
                                                @method('PATCH')
                                                <select name="delivery_status" class="form-select form-select-sm" aria-label="Delivery status for order {{ $transaction->order_number }}">
                                                    @foreach(['pending', 'preparing', 'shipped', 'delivered', 'cancelled'] as $status)
                                                        <option value="{{ $status }}" @selected(($transaction->delivery_status ?: 'pending') === $status)>{{ ucfirst($status) }}</option>
                                                    @endforeach
                                                </select>
                                                <button type="submit" class="btn btn-sm btn-outline-primary">Save</button>
                                            </form>
                                        @else
                                            <span class="badge text-bg-light border">{{ ucfirst(str_replace('_', ' ', strtolower($transaction->delivery_status ?: 'Unspecified'))) }}</span>
                                        @endcan
                                    </div>
                                </div>
                            </div>
                        </article>
                    </div>
            @empty
                <div class="col-12"><div class="card border-0 shadow-sm text-center text-muted py-4">No online sales found.</div></div>
            @endforelse
        </div>
    </div>
    {{-- The detailed order modals remain below the cards. --}}
    @if(false)
                @forelse($transactions as $transaction)
                    @php
                        $saleAmount = (float) ($transaction->sub_total ?: $transaction->items->sum('line_total'));
                        $difference = $transaction->proof_amount === null ? null : (float) $transaction->proof_amount - (float) ($transaction->sub_total ?: $saleAmount);
                    @endphp
                    <tr>
                        <td class="fw-semibold" data-online-preview-remove>{{ $transaction->customer_name }}@include('hubs.reports._new-customer-badge')</td>
                        <td>{{ optional($transaction->order_date)->format('m/d/Y') }}</td>
                        <td>{{ $transaction->order_number }}</td>
                        <td>{{ strtoupper($transaction->mode_of_payment ?: '—') }}{{ ($transaction->bank_name ?: $transaction->custom_bank_name) ? ' / '.strtoupper($transaction->bank_name ?: $transaction->custom_bank_name) : '' }}</td>
                        <td data-online-preview-remove>
                            @can('manage-sales-status')
                                <form action="{{ route('sales.status.update', $transaction->id) }}" method="POST" class="d-flex gap-1" data-online-delivery-form>
                                    @csrf
                                    @method('PATCH')
                                    <select name="delivery_status" class="form-select form-select-sm" aria-label="Delivery status for order {{ $transaction->order_number }}">
                                        @foreach(['pending', 'preparing', 'shipped', 'delivered', 'cancelled'] as $status)
                                            <option value="{{ $status }}" @selected(($transaction->delivery_status ?: 'pending') === $status)>{{ ucfirst($status) }}</option>
                                        @endforeach
                                    </select>
                                    <button type="submit" class="btn btn-sm btn-outline-primary">Save</button>
                                </form>
                            @else
                                <span class="badge text-bg-light border">{{ ucfirst(str_replace('_', ' ', strtolower($transaction->delivery_status ?: 'Unspecified'))) }}</span>
                            @endcan
                        </td>
                        <td class="text-end">₱{{ number_format($saleAmount, 2) }}</td>
                        <td class="text-end">₱{{ number_format($transaction->shipping_fee_amount, 2) }}</td>
                        <td class="text-end">{{ $transaction->proof_amount !== null ? '₱'.number_format($transaction->proof_amount, 2) : 'Not entered' }}</td>
                        <td class="text-end fw-bold {{ $difference !== null && abs($difference) >= 0.01 ? 'text-danger' : 'text-success' }}">
                            {{ $difference !== null ? '₱'.number_format($difference, 2) : '—' }}
                        </td>
                        <td class="text-end">{{ number_format($transaction->items->sum(fn ($item) => $item->inventoryReturns->count())) }}</td>
                        <td class="text-end">{{ number_format($transaction->replacements->count()) }}</td>
                        <td>{{ $transaction->note ?: '—' }}</td>
                        <td data-online-screen-only><button type="button" class="btn btn-sm btn-outline-primary text-nowrap" data-bs-toggle="modal" data-bs-target="#online-order-{{ $transaction->id }}"><i class="fa-solid fa-eye me-1"></i> View details</button></td>
                    </tr>
                @empty
                    <tr><td colspan="13" class="text-center text-muted py-4">No online sales found.</td></tr>
                @endforelse
            </tbody>
            <tfoot class="table-light fw-bold">
                <tr><td colspan="5" data-online-total-label>TOTAL SALES / {{ number_format($totalTransactions) }} TRANSACTIONS</td><td class="text-end">₱{{ number_format($metrics['total_sales'], 2) }}</td><td class="text-end">₱{{ number_format($metrics['shipping_fees'], 2) }}</td><td class="text-end">₱{{ number_format($metrics['proof_amount'], 2) }}</td><td class="text-end">{{ number_format($metrics['return_count']) }}</td><td class="text-end">{{ number_format($metrics['replacement_count']) }}</td><td></td><td data-online-screen-only></td></tr>
            </tfoot>
        </table>
    </div>
    @endif
</div>

@foreach($transactions as $transaction)
    @php
        $detailSaleAmount = $transaction->netOrderTotal((float) ($transaction->sub_total ?: $transaction->items->sum('line_total')));
        $detailShippingFee = (float) $transaction->shipping_fee_amount;
        $detailOrderTotal = $transaction->netOrderTotal((float) ($transaction->grand_total ?: $transaction->total_amount ?: ($detailSaleAmount + $detailShippingFee)));
        $detailReplacementDeliveryFee = (float) $transaction->items
            ->flatMap(fn ($item) => $item->replacements)
            ->where('status', 'approved')
            ->sum('replacement_shipping_fee_amount');
        $detailRefundTotal = $transaction->items->sum(fn ($item) => $item->refundCostAmount())
            + $transaction->inventoryReturns->whereNull('transaction_item_id')->sum('refund_amount');
        $detailPaymentLabel = strtoupper($transaction->mode_of_payment ?: 'Unspecified');
        if ($transaction->custom_mop) {
            $detailPaymentLabel .= ' / '.strtoupper($transaction->custom_mop);
        }
        $detailBankLabel = $transaction->bank_name ?: $transaction->custom_bank_name;
    @endphp
    <div class="modal fade" id="online-order-{{ $transaction->id }}" data-bs-backdrop="static" data-bs-keyboard="false" tabindex="-1" aria-labelledby="online-order-title-{{ $transaction->id }}" aria-hidden="true">
        <div class="modal-dialog modal-xl modal-dialog-scrollable modal-fullscreen-sm-down">
            <div class="modal-content">
                <div class="modal-header">
                    <div>
                        <h5 class="modal-title" id="online-order-title-{{ $transaction->id }}">Online Order #{{ $transaction->order_number }}</h5>
                        <small class="text-muted">{{ $transaction->customer_name }}@include('hubs.reports._new-customer-badge') · {{ optional($transaction->order_date)->format('F d, Y') }} · {{ $detailPaymentLabel }}@if($detailBankLabel) / {{ strtoupper($detailBankLabel) }}@endif</small>
                    </div>
                </div>
                <div class="modal-body">
                    <div class="row g-3 mb-4 align-items-stretch">
                        <div class="col-12 col-lg-6">
                            @include('hubs.reports._payment-proof', ['paymentRecord' => $transaction])
                        </div>
                        <div class="col-12 col-lg-6">
                            <div class="row g-3 h-100">
                                <div class="col-6"><div class="rounded bg-light p-3 h-100"><div class="small text-muted">Product subtotal</div><div class="fs-5 fw-bold">₱{{ number_format($detailSaleAmount, 2) }}</div></div></div>
                                <div class="col-6"><div class="rounded bg-light p-3 h-100"><div class="small text-muted">Shipping fee</div><div class="fs-5 fw-bold">₱{{ number_format($detailShippingFee, 2) }}</div>@if($detailReplacementDeliveryFee > 0)<div class="small text-muted">Includes ₱{{ number_format($detailReplacementDeliveryFee, 2) }} replacement shipping</div>@endif</div></div>
                                <div class="col-6"><div class="rounded bg-light p-3 h-100"><div class="small text-muted">Total cost</div><div class="fs-5 fw-bold text-success">₱{{ number_format($detailOrderTotal, 2) }}</div></div></div>
                                <div class="col-6"><div class="rounded bg-light p-3 h-100"><div class="small text-muted">Return/refund cost</div><div class="fs-5 fw-bold text-danger">₱{{ number_format($detailRefundTotal, 2) }}</div></div></div>
                            </div>
                        </div>
                    </div>

                    <h6 class="fw-bold mb-2">Product items</h6>
                    <div class="table-responsive">
                        <table class="table table-bordered align-middle mb-0">
                            <thead class="table-light"><tr><th>Product</th><th class="text-center">Quantity</th><th class="text-end">Unit price</th><th class="text-end">Item total</th><th>Return / refund</th><th>Replacement status</th><th>Action</th></tr></thead>
                            <tbody>
                                @foreach($transaction->items as $item)
                                    @php
                                        $detailReturns = $item->inventoryReturns;
                                        $detailReturnedQuantity = $item->returnedQuantity();
                                        $detailReturnStatus = $detailReturns->isNotEmpty() ? 'received' : ($item->return_status ?: 'none');
                                        $detailAvailableReplacementQuantity = min(
                                            (int) $item->remaining_replaceable_quantity,
                                            (int) $item->remaining_returned_replaceable_quantity
                                        );
                                        $detailReturnLabel = match ($detailReturnStatus) {
                                            'requested' => 'RETURN REQUESTED',
                                            'received' => 'RETURN RECEIVED',
                                            'rejected' => 'RETURN REJECTED',
                                            'refund_only' => 'REFUND ONLY',
                                            default => 'NO RETURN',
                                        };
                                        $detailReplacements = $item->replacements->sortByDesc('created_at');
                                        $displayQuantity = max(0, (int) $item->quantity - $detailReturnedQuantity);
                                        $displayUnitPrice = (float) $item->unit_price;
                                        $displayItemTotal = max(0, (float) $item->line_total - $item->returnedNetAmount());
                                    @endphp
                                    <tr>
                                        <td><div class="fw-semibold">{{ $item->product?->name ?? 'Product #'.$item->product_id }}</div><small class="text-muted">Item ID: {{ $item->product?->item_id ?? '—' }}</small></td>
                                        <td class="text-center fw-semibold">{{ number_format($displayQuantity) }}</td>
                                        <td class="text-end">₱{{ number_format($displayUnitPrice, 2) }}</td>
                                        <td class="text-end fw-bold">₱{{ number_format($displayItemTotal, 2) }}</td>
                                        <td>
                                            <span class="badge {{ $detailReturnStatus === 'received' ? 'bg-info text-dark' : ($detailReturnStatus === 'requested' ? 'bg-warning text-dark' : ($detailReturnStatus === 'rejected' ? 'bg-danger' : 'bg-secondary')) }}">{{ $detailReturnLabel }}</span>
                                            @if($detailReturnedQuantity > 0)<div class="small mt-1">Quantity returned: {{ $detailReturnedQuantity }}</div>@endif
                                            @if($item->return_condition)<div class="small">Condition: {{ ucfirst($item->return_condition) }}</div>@endif
                                            @if($item->refund_status && $item->refund_status !== 'none')<div class="small">Refund: {{ ucfirst($item->refund_status) }} · ₱{{ number_format($item->customer_refund_amount ?? 0, 2) }}</div>@endif
                                            @foreach($detailReturns as $return)
                                                <div class="small text-muted mt-1">{{ number_format($return->quantity) }} {{ $return->condition ?: 'unclassified' }} · {{ optional($return->occurred_on)->format('M d, Y') }}@if((float) $return->refund_amount > 0) · Refund ₱{{ number_format($return->refund_amount, 2) }}@endif</div>
                                            @endforeach
                                        </td>
                                        <td>
                                            @forelse($detailReplacements as $replacement)
                                                <div class="p-2 rounded mb-1 {{ $replacement->status === 'approved' ? 'bg-success-subtle' : ($replacement->status === 'rejected' ? 'bg-danger-subtle' : 'bg-warning-subtle') }}">
                                                    <span class="badge {{ $replacement->status === 'approved' ? 'bg-success' : ($replacement->status === 'rejected' ? 'bg-danger' : 'bg-warning text-dark') }}">{{ strtoupper($replacement->status) }}</span>
                                                    @if($replacement->inventoryReturns->isNotEmpty())
                                                        <span class="badge bg-info text-dark ms-1">REPLACEMENT RETURNED</span>
                                                    @endif
                                                    <div class="small fw-semibold mt-1">{{ $replacement->replacementProduct?->name ?? 'Product #'.$replacement->replacement_product_id }}</div>
                                                    <div class="small">Original returned {{ $replacement->quantity }} · Replacement qty {{ $replacement->replacement_quantity ?: $replacement->quantity }}</div>
                                                    <div class="small">Unit cost ₱{{ number_format((float) $replacement->replacement_unit_price * (1 - ((float) ($replacement->replacement_discount_percentage ?? 0) / 100)), 2) }}{{ (float) ($replacement->replacement_discount_percentage ?? 0) > 0 ? ' after '.number_format($replacement->replacement_discount_percentage, 2).'% discount' : '' }}</div>
                                                    @foreach($replacement->inventoryReturns as $replacementReturn)
                                                        <div class="small text-info-emphasis mt-1">
                                                            Replacement item returned: {{ number_format($replacementReturn->quantity) }} {{ $replacementReturn->condition ?: 'unclassified' }} · {{ optional($replacementReturn->occurred_on)->format('M d, Y') }}
                                                            @if((float) $replacementReturn->refund_amount > 0) · Refund ₱{{ number_format($replacementReturn->refund_amount, 2) }}@endif
                                                        </div>
                                                    @endforeach
                                                    @if($replacement->reason)<div class="small text-muted">{{ $replacement->reason }}</div>@endif
                                                    @if($replacement->rejection_reason)<div class="small text-danger">{{ $replacement->rejection_reason }}</div>@endif
                                                </div>
                                            @empty
                                                <span class="badge bg-secondary">NONE</span>
                                            @endforelse
                                        </td>
                                        <td>
                                            @can('manage-sales-status')
                                                @if($detailAvailableReplacementQuantity > 0 && in_array($transaction->status, ['confirmed', 'completed'], true))
                                                    <button type="button" class="btn btn-sm btn-outline-success online-replace-button" data-replacement-target="#online-replacement-{{ $item->id }}"><i class="fa-solid fa-arrow-right-arrow-left me-1"></i> Replacement</button>
                                                @elseif($item->remaining_replaceable_quantity > 0 && in_array($transaction->status, ['confirmed', 'completed'], true))
                                                    <button type="button" class="btn btn-sm btn-outline-success" disabled title="Available after inventory receives the returned item"><i class="fa-solid fa-arrow-right-arrow-left me-1"></i> Replacement</button>
                                                @else
                                                    <span class="text-muted small">Unavailable</span>
                                                @endif
                                            @endcan
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
                <div class="modal-footer"><button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button></div>
            </div>
        </div>
    </div>
@endforeach

@can('manage-sales-status')
    @foreach($transactions as $transaction)
        @foreach($transaction->items as $item)
            @php
                $availableReplacementQuantity = min(
                    (int) $item->remaining_replaceable_quantity,
                    (int) $item->remaining_returned_replaceable_quantity
                );
            @endphp
            @if($availableReplacementQuantity > 0 && in_array($transaction->status, ['confirmed', 'completed'], true))
                <div class="modal fade" id="online-replacement-{{ $item->id }}" data-online-replacement-modal data-original-product-id="{{ $item->product_id }}" data-original-net-unit-price="{{ round((float) $item->unit_price * (1 - ((float) ($item->discount_percentage ?? 0) / 100)), 2) }}" tabindex="-1" aria-hidden="true">
                    <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable">
                        <form method="POST" enctype="multipart/form-data" action="{{ route('hub.report.online.replacement.store', ['hub' => $hub->id, 'transaction' => $transaction->id, 'item' => $item->id]) }}" class="modal-content">
                            @csrf
                            <div class="modal-header"><h5 class="modal-title">Online Replacement / Exchange</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
                            <div class="modal-body">
                                <div class="alert alert-info small">The request goes to Inventory Verification. Stock and the order total change only after approval.</div>
                                <div class="small text-muted mb-3">Original item: <strong>{{ $item->product?->name ?? 'Product #'.$item->product_id }}</strong></div>
                                @php
                                    $originalUnitPrice = (float) ($item->unit_price ?? 0);
                                    $originalDiscountPercentage = (float) ($item->discount_percentage ?? 0);
                                    $originalDiscountAmount = $originalUnitPrice * ($originalDiscountPercentage / 100);
                                    $originalNetUnitPrice = $originalUnitPrice - $originalDiscountAmount;
                                @endphp
                                <div class="row g-2 mb-3">
                                    <div class="col-4">
                                        <div class="small text-muted">Original price</div>
                                        <div class="fw-semibold">₱{{ number_format($originalUnitPrice, 2) }}</div>
                                    </div>
                                    <div class="col-4">
                                        <div class="small text-muted">Item discount</div>
                                        <div class="fw-semibold text-danger">{{ number_format($originalDiscountPercentage, 2) }}% (₱{{ number_format($originalDiscountAmount, 2) }})</div>
                                    </div>
                                    <div class="col-4">
                                        <div class="small text-muted">Net replacement credit</div>
                                        <div class="fw-semibold text-success">₱{{ number_format($originalNetUnitPrice, 2) }}</div>
                                    </div>
                                </div>
                                <label class="form-label fw-semibold">Replacement product</label>
                                <input type="text" class="form-control" data-replacement-search list="onlineReplacementOptions-{{ $item->id }}" autocomplete="off" placeholder="Type an Item ID, barcode, or product name" required>
                                <input type="hidden" name="replacement_product_id">
                                <datalist id="onlineReplacementOptions-{{ $item->id }}"></datalist>
                                <div class="form-text" data-replacement-stock>Search by Item ID, barcode, or product name. Online allocation is checked during verification.</div>
                                <div class="small text-success fw-semibold mt-1" data-replacement-price></div>
                                <div class="row g-3 mt-1">
                                    <div class="col-6"><label class="form-label fw-semibold">Original returned</label><input type="number" name="quantity" class="form-control" min="1" max="{{ $availableReplacementQuantity }}" value="1" required><div class="form-text">Maximum {{ $availableReplacementQuantity }} returned item(s)</div></div>
                                    <div class="col-6"><label class="form-label fw-semibold">Replacement quantity</label><input type="number" name="replacement_quantity" class="form-control" min="1" value="1" required></div>
                                    <div class="col-12">
                                        <label class="form-label fw-semibold text-danger">Replacement discount (%)</label>
                                        <input type="number" name="replacement_discount_percentage" class="form-control" min="0" max="100" step="0.01" value="0.00">
                                        <div class="form-text">Optional discount applied to the replacement product price after approval.</div>
                                    </div>
                                </div>
                                <div class="row g-2 mt-2">
                                    <div class="col-6">
                                        <label class="form-label fw-semibold">Replacement delivery</label>
                                        <select name="replacement_shipping_fee_type" class="form-select" data-online-replacement-shipping-type>
                                            <option value="Free">Free delivery</option>
                                            <option value="Custom Amount">Custom amount</option>
                                        </select>
                                    </div>
                                    <div class="col-6 d-none" data-online-replacement-shipping-amount-wrap>
                                        <label class="form-label fw-semibold">Shipping fee (₱)</label>
                                        <input type="number" name="replacement_shipping_fee_amount" class="form-control" min="0" step="0.01" value="0.00" disabled>
                                    </div>
                                </div>
                                <div class="card border-0 bg-light mt-3" data-online-exchange-summary>
                                    <div class="card-body p-3">
                                        <div class="d-flex align-items-center justify-content-between mb-2"><strong><i class="fa-solid fa-calculator text-primary me-2"></i>Exchange calculation</strong><span class="badge bg-secondary" data-online-exchange-status>Select a replacement</span></div>
                                        <div class="row g-2 small">
                                            <div class="col-6 col-md-3"><span class="text-muted d-block">Exchange credit</span><strong data-online-exchange-credit>₱0.00</strong></div>
                                            <div class="col-6 col-md-3"><span class="text-muted d-block">Main replacement</span><strong data-online-exchange-main-total>₱0.00</strong></div>
                                            <div class="col-6 col-md-3"><span class="text-muted d-block">Additional products</span><strong data-online-exchange-extra-total>₱0.00</strong></div>
                                            <div class="col-6 col-md-3"><span class="text-muted d-block">Replacement basket</span><strong data-online-exchange-basket-total>₱0.00</strong></div>
                                            <div class="col-6 col-md-3"><span class="text-muted d-block">Replacement shipping</span><strong data-online-exchange-shipping>₱0.00</strong></div>
                                        </div>
                                        <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 border-top mt-3 pt-3">
                                            <span class="text-muted small" data-online-exchange-message>Choose replacement products to calculate.</span>
                                            <span class="fw-bold text-danger" data-online-exchange-amount-due>Additional payment: ₱0.00</span>
                                        </div>
                                    </div>
                                </div>
                                @include('hubs.reports._exchange-additional-items', ['exchangePrefix' => 'online-'.$item->id, 'exchangeChannel' => 'online'])
                                <label class="form-label fw-semibold mt-3">Reason (optional)</label>
                                <textarea name="reason" class="form-control" rows="3" maxlength="2000"></textarea>
                            </div>
                            <div class="modal-footer"><button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button><button class="btn btn-success">Submit for Verification</button></div>
                        </form>
                    </div>
                </div>
            @endif
        @endforeach
    @endforeach
@endcan

@push('scripts')
<script>
(() => {
    const endpoint = @json(route('hub.products.search.ajax', $hub->id));

    document.querySelectorAll('.online-replace-button').forEach(button => {
        button.addEventListener('click', event => {
            event.preventDefault();
            const target = document.querySelector(button.dataset.replacementTarget);
            if (!target) return;
            if (target.parentElement !== document.body) document.body.appendChild(target);
            const current = button.closest('.modal');
            const showReplacement = () => bootstrap.Modal.getOrCreateInstance(target).show();
            if (current) {
                current.addEventListener('hidden.bs.modal', showReplacement, { once: true });
                bootstrap.Modal.getOrCreateInstance(current).hide();
            } else {
                showReplacement();
            }
        });
    });

    document.querySelectorAll('[data-online-replacement-modal]').forEach(modal => {
        const form = modal.querySelector('form');
        const search = modal.querySelector('[data-replacement-search]');
        const productId = modal.querySelector('[name="replacement_product_id"]');
        const options = modal.querySelector('datalist');
        const stockHelp = modal.querySelector('[data-replacement-stock]');
        const replacementPrice = modal.querySelector('[data-replacement-price]');
        const discountInput = modal.querySelector('[name="replacement_discount_percentage"]');
        const replacementQuantity = modal.querySelector('[name="replacement_quantity"]');
        const originalReturnedQuantity = modal.querySelector('[name="quantity"]');
        const paymentAmount = modal.querySelector('[name="exchange_payment_amount"]');
        const exchangeSummary = modal.querySelector('[data-online-exchange-summary]');
        const shippingType = modal.querySelector('[data-online-replacement-shipping-type]');
        const shippingAmountInput = modal.querySelector('[name="replacement_shipping_fee_amount"]');
        const shippingAmountWrap = modal.querySelector('[data-online-replacement-shipping-amount-wrap]');
        const originalProductId = modal.dataset.originalProductId;
        const products = new Map();
        let timer;
        let searchVersion = 0;

        modal.addEventListener('show.bs.modal', () => {
            clearTimeout(timer);
            searchVersion++;
            search.value = '';
            productId.value = '';
            search.setCustomValidity('');
            options.replaceChildren();
            products.clear();
            stockHelp.textContent = 'Search by Item ID, barcode, or product name. Online allocation is checked during verification.';
            replacementPrice.textContent = '';
            paymentAmount.value = '0.00';
            shippingType.value = 'Free';
            shippingAmountInput.value = '0.00';
            shippingAmountInput.disabled = true;
            shippingAmountWrap.classList.add('d-none');
            modal.querySelector('[data-exchange-line-list]')?.replaceChildren();
            updateExchangeCalculation();
        });
        const money = value => `₱${Number(value || 0).toLocaleString('en-PH', { minimumFractionDigits: 2, maximumFractionDigits: 2 })}`;
        const roundMoney = value => Math.round((value + Number.EPSILON) * 100) / 100;
        const updateExchangeCalculation = () => {
            const selected = products.get(search.value);
            const originalUnitPrice = Number(modal.dataset.originalNetUnitPrice || 0);
            const credit = roundMoney(originalUnitPrice * Math.max(1, Number(originalReturnedQuantity.value || 1)));
            const mainPrice = Number(selected?.sales_price || 0);
            const discount = Math.min(100, Math.max(0, Number(discountInput.value || 0)));
            const mainTotal = roundMoney(mainPrice * (1 - discount / 100) * Math.max(1, Number(replacementQuantity.value || 1)));
            let extraTotal = 0;
            modal.querySelectorAll('[data-exchange-line]').forEach(line => {
                const unitPrice = Number(line.querySelector('[data-exchange-product]')?.dataset.unitPrice || 0);
                const quantity = Math.max(1, Number(line.querySelector('[data-exchange-quantity]')?.value || 1));
                const lineDiscount = Math.min(100, Math.max(0, Number(line.querySelector('[data-exchange-discount]')?.value || 0)));
                extraTotal += roundMoney(unitPrice * (1 - lineDiscount / 100) * quantity);
            });
            extraTotal = roundMoney(extraTotal);
            const shippingFee = shippingType.value === 'Custom Amount'
                ? roundMoney(Math.max(0, Number(shippingAmountInput.value || 0)))
                : 0;
            const basketTotal = roundMoney(mainTotal + extraTotal + shippingFee);
            const amountDue = roundMoney(Math.max(0, basketTotal - credit));
            const shortfall = roundMoney(Math.max(0, credit - basketTotal));
            exchangeSummary.querySelector('[data-online-exchange-credit]').textContent = money(credit);
            exchangeSummary.querySelector('[data-online-exchange-main-total]').textContent = money(mainTotal);
            exchangeSummary.querySelector('[data-online-exchange-extra-total]').textContent = money(extraTotal);
            exchangeSummary.querySelector('[data-online-exchange-basket-total]').textContent = money(basketTotal);
            exchangeSummary.querySelector('[data-online-exchange-shipping]').textContent = money(shippingFee);
            exchangeSummary.querySelector('[data-online-exchange-amount-due]').textContent = `Additional payment: ${money(amountDue)}`;
            const message = exchangeSummary.querySelector('[data-online-exchange-message]');
            message.textContent = shortfall > 0
                ? 'No additional payment is due. The original proof amount stays unchanged.'
                : (amountDue > 0 ? `Customer adds ${money(amountDue)}.` : 'Replacement basket matches the exchange credit.');
            const status = exchangeSummary.querySelector('[data-online-exchange-status]');
            status.className = `badge ${amountDue > 0 ? 'bg-danger' : 'bg-success'}`;
            status.textContent = amountDue > 0 ? `Additional payment ${money(amountDue)}` : 'No additional payment';
            paymentAmount.value = amountDue.toFixed(2);
        };
        const updateReplacementShipping = () => {
            const custom = shippingType.value === 'Custom Amount';
            shippingAmountWrap.classList.toggle('d-none', !custom);
            shippingAmountInput.disabled = !custom;
            if (!custom) shippingAmountInput.value = '0.00';
            updateExchangeCalculation();
        };
        shippingType.addEventListener('change', updateReplacementShipping);
        shippingAmountInput.addEventListener('input', updateExchangeCalculation);
        const updateReplacementPrice = () => {
            const selected = products.get(search.value);
            if (!selected) {
                replacementPrice.textContent = '';
                return;
            }
            const price = Number(selected.sales_price || 0);
            const discount = Math.min(100, Math.max(0, Number(discountInput.value || 0)));
            const netPrice = price * (1 - discount / 100);
            replacementPrice.textContent = `Replacement price after discount: ₱${netPrice.toLocaleString('en-PH', { minimumFractionDigits: 2, maximumFractionDigits: 2 })}`;
            updateExchangeCalculation();
        };
        discountInput.addEventListener('input', updateReplacementPrice);
        replacementQuantity.addEventListener('input', updateExchangeCalculation);
        originalReturnedQuantity.addEventListener('input', updateExchangeCalculation);
        form.addEventListener('exchange:changed', updateExchangeCalculation);
        search.addEventListener('input', () => {
            clearTimeout(timer);
            const version = ++searchVersion;
            productId.value = '';
            search.setCustomValidity('');
            const selected = products.get(search.value);
            if (selected) {
                productId.value = selected.id;
                stockHelp.textContent = `${selected.channel_available_stock} Online unit(s) available · Retail price ₱${Number(selected.sales_price || 0).toLocaleString('en-PH', { minimumFractionDigits: 2, maximumFractionDigits: 2 })}${selected.barcode ? ` · Barcode ${selected.barcode}` : ''}`;
                updateReplacementPrice();
                return;
            }
            const query = search.value.trim();
            options.replaceChildren();
            products.clear();
            updateExchangeCalculation();
            stockHelp.textContent = query ? 'Searching products...' : 'Search by Item ID, barcode, or product name.';
            if (!query) return;
            timer = setTimeout(async () => {
                try {
                    const url = new URL(endpoint, location.origin);
                    url.searchParams.set('q', query);
                    url.searchParams.set('active_only', '1');
                    url.searchParams.set('stock_channel', 'online');
                    const response = await fetch(url, { headers: { Accept: 'application/json' } });
                    if (!response.ok) throw new Error('Search unavailable');
                    const results = await response.json();
                    if (version !== searchVersion) return;
                    options.replaceChildren();
                    products.clear();
                    results.filter(product => String(product.id) !== String(originalProductId) && Number(product.stock) > 0).forEach(product => {
                        const barcode = product.barcode ? ` · Barcode: ${product.barcode}` : '';
                        const label = `${product.item_id || product.id} — ${product.name || 'Unnamed product'}${barcode} (Online stock: ${product.channel_available_stock})`;
                        products.set(label, product);
                        const option = document.createElement('option');
                        option.value = label;
                        options.append(option);
                    });
                    stockHelp.textContent = products.size
                        ? 'Select a product from the suggestions. Online allocation is checked during verification.'
                        : 'No matching products with available stock. Try another Item ID, barcode, or product name.';
                } catch (error) {
                    if (version !== searchVersion) return;
                    stockHelp.textContent = 'Product search is unavailable. Please try again.';
                    window.AppAlert?.show('Product search is unavailable. Please try again.', 'error');
                }
            }, 250);
        });
        form.addEventListener('submit', event => {
            if (!productId.value) {
                event.preventDefault();
                search.setCustomValidity('Select a replacement product from the suggestions.');
                search.reportValidity();
            }
        });
    });
})();
</script>
@endpush
