<style>
    .marketplace-report-card { border-radius: 14px; overflow: hidden; }
    .marketplace-order-modal .modal-header { background: linear-gradient(135deg, #2563eb, #1d4ed8); color: #fff; }
    .marketplace-order-modal .modal-header .btn-close { filter: brightness(0) invert(1); }
    .marketplace-order-summary { background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 12px; }
</style>
<div class="card shadow-sm border-0 marketplace-report-card">
    <div class="card-header bg-white p-3">
        <span class="fw-bold">{{ $activeLabel }} Order Sales</span>
        <small class="text-muted d-block">Verified marketplace orders and item totals.</small>
    </div>
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead class="table-light"><tr><th>Date of Arrangement</th><th>Invoice No.</th><th>Customer</th><th class="text-center">Items</th><th class="text-end">Order Total</th><th class="text-end">Action</th></tr></thead>
            <tbody>
                @forelse($transactions as $transaction)
                    @php
                        $total = $transaction->netOrderTotal();
                    @endphp
                    <tr>
                        <td>{{ optional($transaction->date_of_arrangement)->format('M d, Y') ?: '—' }}</td>
                        <td class="fw-semibold font-monospace">{{ $transaction->order_number }}</td>
                        <td>{{ $transaction->customer_name ?: 'Marketplace Customer' }}@include('hubs.reports._new-customer-badge')</td>
                        <td class="text-center">{{ number_format($transaction->items->sum(fn ($item) => (int) $item->quantity - $item->returnedQuantity())) }}</td>
                        <td class="text-end fw-bold text-success">₱{{ number_format($total, 2) }}</td>
                        <td class="text-end"><button type="button" class="btn btn-sm btn-primary" data-bs-toggle="modal" data-bs-target="#marketplace-order-{{ $transaction->id }}"><i class="fa-solid fa-eye me-1"></i>View Order</button></td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="text-center text-muted py-5">No {{ $activeLabel }} sales found.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>

@foreach($transactions as $transaction)
    @php
        $gross = (float) $transaction->items->sum(fn ($item) => (float) $item->unit_price * ((int) $item->quantity - $item->returnedQuantity()));
        $itemNet = (float) $transaction->items->sum(fn ($item) => max(0, (float) $item->line_total - $item->returnedNetAmount()));
        $discount = max(0, $gross - $itemNet);
        $total = $transaction->netOrderTotal();
    @endphp
    <div class="modal fade" id="marketplace-order-{{ $transaction->id }}" tabindex="-1" aria-labelledby="marketplace-order-title-{{ $transaction->id }}" aria-hidden="true">
        <div class="modal-dialog modal-lg modal-dialog-scrollable"><div class="modal-content marketplace-order-modal">
            <div class="modal-header"><div><h5 class="modal-title" id="marketplace-order-title-{{ $transaction->id }}">{{ $activeLabel }} Order Details</h5><small>Invoice No. {{ $transaction->order_number }}</small></div><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button></div>
            <div class="modal-body">
                <div class="marketplace-order-summary p-3 mb-3">
                    <div class="row row-cols-2 row-cols-lg-5 g-3">
                        <div class="col"><div class="small text-muted text-uppercase">Invoice No.</div><div class="fw-semibold font-monospace">{{ $transaction->order_number }}</div></div>
                        <div class="col"><div class="small text-muted text-uppercase">Customer</div><div class="fw-semibold">{{ $transaction->customer_name ?: 'Marketplace Customer' }}@include('hubs.reports._new-customer-badge')</div></div>
                        <div class="col"><div class="small text-muted text-uppercase">Order date</div><div class="fw-semibold">{{ optional($transaction->order_date)->format('M d, Y') ?: '—' }}</div></div>
                        <div class="col"><div class="small text-muted text-uppercase">Date of arrangement</div><div class="fw-semibold">{{ optional($transaction->date_of_arrangement)->format('M d, Y') ?: '—' }}</div></div>
                        <div class="col"><div class="small text-muted text-uppercase">Mode of payment</div><div class="fw-semibold">{{ str_replace('_', ' ', $transaction->mode_of_payment ?: '—') }}</div></div>
                    </div>
                </div>
                <div class="table-responsive border rounded mb-3"><table class="table align-middle mb-0">
                    <thead class="table-light"><tr><th>Product</th><th>Status</th><th class="text-center">Qty</th><th class="text-end">Unit Price</th><th class="text-end">Discount</th><th class="text-end">Total</th></tr></thead>
                    <tbody>@foreach($transaction->items as $item)
                    @php
                        $returnedQuantity = max((int) ($item->returned_quantity ?? 0), (int) $item->inventoryReturns->sum('quantity'));
                        $isReturned = $returnedQuantity > 0 || in_array($item->return_status, ['received', 'refund_only'], true);
                        $fullyReturned = $isReturned && $returnedQuantity >= (int) $item->quantity;
                        $refundCost = $item->refundCostAmount();
                        $refundStatus = $item->refund_status && $item->refund_status !== 'none'
                            ? ucfirst(str_replace('_', ' ', $item->refund_status))
                            : ($refundCost > 0 ? 'Recorded' : null);
                        $goodReturned = (int) $item->inventoryReturns->where('condition', 'good')->sum('quantity');
                        $damagedReturned = (int) $item->inventoryReturns->where('condition', 'damaged')->sum('quantity');
                        if ($item->inventoryReturns->isEmpty() && $returnedQuantity > 0) {
                            $goodReturned = $item->return_condition === 'good' ? $returnedQuantity : 0;
                            $damagedReturned = $item->return_condition === 'damaged' ? $returnedQuantity : 0;
                        }
                    @endphp
                    <tr>
                        <td>
                            <div class="fw-semibold">{{ $item->product?->name ?? 'Product #'.$item->product_id }} @if($isReturned)<span class="badge {{ $fullyReturned ? 'bg-info text-dark' : 'bg-warning text-dark' }} ms-1">{{ $fullyReturned ? 'RETURNED' : 'PARTIALLY RETURNED' }}</span>@endif</div>
                            @if($refundStatus)<span class="badge rounded-pill bg-danger-subtle text-danger-emphasis mt-1">Refund {{ $refundStatus }} · ₱{{ number_format($refundCost, 2) }}</span>@endif
                        </td>
                        <td>@if($isReturned)
                            <div class="small fw-semibold {{ $fullyReturned ? 'text-info' : 'text-warning' }}">{{ number_format($returnedQuantity) }} of {{ number_format($item->quantity) }} returned</div>
                            <div class="d-flex flex-wrap gap-1 mt-1">
                                @if($goodReturned > 0)<span class="badge bg-success">Good: {{ number_format($goodReturned) }}</span>@endif
                                @if($damagedReturned > 0)<span class="badge bg-danger">Damaged: {{ number_format($damagedReturned) }}</span>@endif
                                @if($goodReturned === 0 && $damagedReturned === 0)<span class="badge bg-secondary">Condition not recorded</span>@endif
                            </div>
                        @else<span class="badge bg-success">SOLD</span>@endif</td>
                        <td class="text-center">{{ number_format($item->quantity) }}</td>
                        <td class="text-end">₱{{ number_format($item->unit_price, 2) }}</td>
                        <td class="text-end">{{ number_format($item->discount_percentage ?? 0, 2) }}%</td>
                        <td class="text-end fw-semibold">₱{{ number_format(max(0, (float) $item->line_total - $item->returnedNetAmount()), 2) }}</td>
                    </tr>@endforeach</tbody>
                </table></div>
                <div class="row justify-content-end"><div class="col-sm-6 col-lg-5">
                    <div class="d-flex justify-content-between py-1"><span class="text-muted">Gross</span><strong>₱{{ number_format($gross, 2) }}</strong></div>
                    <div class="d-flex justify-content-between py-1"><span class="text-muted">Discount</span><strong class="text-danger">−₱{{ number_format($discount, 2) }}</strong></div>
                    <div class="d-flex justify-content-between py-2 border-top mt-1"><span class="fw-bold">Order Total</span><strong class="text-success fs-5">₱{{ number_format($total, 2) }}</strong></div>
                </div></div>
            </div>
            <div class="modal-footer"><button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Close</button></div>
        </div></div>
    </div>
@endforeach
