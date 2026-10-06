@php
    $canEditDetails = auth()->user()->role === 'admin' || (in_array(auth()->user()->role, ['sales_associate', 'sales_marketing_staff']) && auth()->user()->hasSalesChannel('tiktok'));
    $canReportReturns = $canEditDetails;
    $remainingQuantity = static function ($item): int {
        $returnedQuantity = max(
            (int) ($item->returned_quantity ?? 0),
            (int) $item->inventoryReturns->sum('quantity')
        );

        return max(0, (int) $item->quantity - $returnedQuantity);
    };
    $latestItemTotal = static function ($item) use ($remainingQuantity): float {
        $remainingTotal = $remainingQuantity($item) * (float) $item->unit_price * (1 - ((float) $item->discount_percentage / 100));
        $replacementTotal = $item->replacements
            ->where('status', 'approved')
            ->sum(fn ($replacement) => (float) $replacement->replacement_unit_price * (int) ($replacement->replacement_quantity ?: $replacement->quantity));

        return round($remainingTotal + $replacementTotal, 2);
    };
    $itemUnitsWithCustomer = static function ($item): int {
        $approvedReplacements = $item->replacements->where('status', 'approved');
        $returnedQuantity = max(
            (int) ($item->returned_quantity ?? 0),
            (int) $item->inventoryReturns->sum('quantity')
        );
        $originalRemaining = max(0,
            (int) $item->quantity
            - $returnedQuantity
            - (int) $approvedReplacements->sum('quantity')
        );
        $replacementRemaining = $approvedReplacements->sum(fn ($replacement) => max(0,
            (int) ($replacement->replacement_quantity ?: $replacement->quantity)
            - (int) $replacement->inventoryReturns->sum('quantity')
        ));

        return $originalRemaining + $replacementRemaining;
    };
@endphp
<style>
    .tiktok-report-table { table-layout: fixed; min-width: 980px; }
    .tiktok-report-table th:nth-child(1), .tiktok-report-table td:nth-child(1) { width: 17%; }
    .tiktok-report-table th:nth-child(2), .tiktok-report-table td:nth-child(2) { width: 14%; }
    .tiktok-report-table th:nth-child(3), .tiktok-report-table td:nth-child(3) { width: 11%; }
    .tiktok-report-table th:nth-child(4), .tiktok-report-table td:nth-child(4) { width: 16%; }
    .tiktok-report-table th:nth-child(5), .tiktok-report-table td:nth-child(5) { width: 19%; }
    .tiktok-report-table th:nth-child(6), .tiktok-report-table td:nth-child(6) { width: 14%; }
    .tiktok-report-table th:nth-child(7), .tiktok-report-table td:nth-child(7) { width: 9%; }
    .tiktok-report-table th, .tiktok-report-table td { white-space: nowrap; }
    .tiktok-report-table td:nth-child(1) { white-space: normal; }
    .tiktok-report-table th:nth-child(n+4), .tiktok-report-table td:nth-child(n+4) { text-align: center !important; }
    .tiktok-order-modal .modal-header { background: linear-gradient(135deg, #2563eb, #1d4ed8); color: #fff; border-bottom: 0; }
    .tiktok-order-modal .modal-header .btn-close { filter: brightness(0) invert(1); }
    .tiktok-order-modal .modal-body { background: #f8fafc; }
    .tiktok-order-hero { padding: 1rem; border: 1px solid #bfdbfe; border-radius: 14px; background: linear-gradient(135deg, #eff6ff, #fff); }
    .tiktok-order-hero .eyebrow { color: #2563eb; font-size: .7rem; font-weight: 700; letter-spacing: .08em; text-transform: uppercase; }
    .tiktok-order-summary .rounded { border: 1px solid #e2e8f0; background: #fff !important; }
    .tiktok-order-items { overflow: hidden; border: 1px solid #dbe4f0; border-radius: 12px; background: #fff; }
    .tiktok-order-items .table { margin-bottom: 0; }
</style>
<div class="mb-3">
    <h5 class="fw-bold mb-1">TikTok orders</h5>
    <p class="text-muted small">Choose an order to review its payout, products, and return information. Totals follow the order date filter.</p>
    @if($metrics['pending_payouts'])
        <div class="alert alert-warning py-2">{{ $metrics['pending_payouts'] }} order(s) awaiting sales-after-transactions entry. Totals include entered values only.</div>
    @endif
</div>
<div class="card border-0 shadow-sm"><div class="table-responsive">
    <table class="table align-middle mb-0 tiktok-report-table">
        <thead class="table-light"><tr><th class="ps-3">Order / Date</th><th>Customer</th><th>Items</th><th class="text-end">Total Sales</th><th class="text-end">Recalculated Sales After Transactions</th><th>Status</th><th></th></tr></thead>
        <tbody>
        @forelse($transactions as $transaction)
            @php
                $transactionRefundTotal = $transaction->items->sum(fn ($item) => (float) $item->inventoryReturns->sum('refund_amount'));
                $adjustedOrderTotal = $transaction->items->sum(fn ($item) => $latestItemTotal($item));
                $recalculatedPayout = $transaction->netTikTokPayout();
                $hasReturn = $transaction->items->contains(fn ($item) => $item->return_status !== 'none' || $item->inventoryReturns->isNotEmpty());
                $recalculatedPayout = $hasReturn
                    ? $transaction->tiktok_recalculated_payout
                    : $recalculatedPayout;
            @endphp
            <tr>
                <td class="ps-3"><div class="fw-semibold">#{{ $transaction->order_number }}</div><small class="text-muted">{{ optional($transaction->order_date)->format('M d, Y') }}</small></td>
                <td>{{ $transaction->customer_name }}@include('hubs.reports._new-customer-badge')</td>
                <td>{{ $transaction->items->count() }} products<div class="small text-muted">{{ $transaction->items->sum(fn ($item) => $remainingQuantity($item)) }} remaining units</div></td>
                <td class="text-end">₱{{ number_format($adjustedOrderTotal, 2) }}</td>
                <td class="text-end fw-semibold text-primary">{{ $recalculatedPayout === null ? 'Not entered' : '₱'.number_format($recalculatedPayout, 2) }}</td>
                <td>
                    <span class="badge {{ $hasReturn && $transaction->tiktok_recalculated_payout_entered ? 'bg-success' : ($hasReturn ? 'bg-info text-dark' : ($transaction->sales_after_transaction_fee === null ? 'bg-warning text-dark' : 'bg-success')) }}">{{ $hasReturn ? ($transaction->tiktok_recalculated_payout_entered ? 'Recalculated payout recorded' : 'Return recalculated') : ($transaction->sales_after_transaction_fee === null ? 'Awaiting sales total' : 'Sales total recorded') }}</span>
                </td>
                <td class="text-end pe-3">
                    @if($canReportReturns)
                        <button type="button" class="btn btn-sm btn-outline-warning text-nowrap mb-1" data-bs-toggle="modal" data-bs-target="#tiktok-order-{{ $transaction->id }}">
                            <i class="fa-solid fa-rotate-left me-1"></i>Return items
                        </button>
                    @endif
                    <button type="button" class="btn btn-sm btn-outline-primary text-nowrap" data-bs-toggle="modal" data-bs-target="#tiktok-order-{{ $transaction->id }}">View order</button>
                </td>
            </tr>
        @empty
            <tr><td colspan="7" class="text-center text-muted p-5">No TikTok orders found. Try a different date range.</td></tr>
        @endforelse
        </tbody>
    </table>
</div></div>
@foreach($transactions as $transaction)
@php
    $reportedOrderTotal = $transaction->items->sum(fn ($item) => $latestItemTotal($item));
    $transactionRefundTotal = $transaction->items->sum(fn ($item) => (float) $item->inventoryReturns->sum('refund_amount'));
    $hasReturn = $transaction->items->contains(fn ($item) => $item->return_status !== 'none' || $item->inventoryReturns->isNotEmpty());
    $allItemsReturned = $transaction->items->isNotEmpty()
        && $transaction->items->sum(fn ($item) => $itemUnitsWithCustomer($item)) === 0;
@endphp
<div class="modal fade" id="tiktok-order-{{ $transaction->id }}" tabindex="-1" aria-labelledby="tiktok-order-title-{{ $transaction->id }}" aria-hidden="true" @if(old('editing_order') == $transaction->id) data-reopen-order @endif>
    <div class="modal-dialog modal-xl modal-dialog-scrollable modal-fullscreen-sm-down"><div class="modal-content tiktok-order-modal">
        <div class="modal-header"><h5 class="modal-title" id="tiktok-order-title-{{ $transaction->id }}">TikTok Order Details</h5><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close order"></button></div>
        <div class="modal-body">
    <article>
        <div class="tiktok-order-hero d-flex flex-wrap justify-content-between gap-3 align-items-center mb-3">
            <div><div class="eyebrow">TikTok order summary</div>
            <div><h5 class="fw-bold mb-1">Order #{{ $transaction->order_number }}</h5><span class="text-muted small">{{ $transaction->customer_name }}@include('hubs.reports._new-customer-badge') &middot; {{ optional($transaction->order_date)->format('M d, Y') }}</span></div>
            </div>
            <span class="badge {{ $hasReturn && $transaction->tiktok_recalculated_payout_entered ? 'bg-success' : ($hasReturn ? 'bg-info text-dark' : ($transaction->sales_after_transaction_fee === null ? 'bg-warning text-dark' : 'bg-success')) }}">{{ $hasReturn ? ($transaction->tiktok_recalculated_payout_entered ? 'Recalculated payout recorded' : 'Return recalculated') : ($transaction->sales_after_transaction_fee === null ? 'Awaiting sales total' : 'Sales total recorded') }}</span>
        </div>
        <div class="card-body">
            <div class="row g-3 mb-3 tiktok-order-summary">
                @php
                    $recalculatedPayout = $hasReturn
                        ? $transaction->tiktok_recalculated_payout
                        : $transaction->netTikTokPayout();
                @endphp
                @foreach(['Total Sales' => $reportedOrderTotal, 'Recorded Sales After Transactions' => $transaction->sales_after_transaction_fee, 'Recalculated Sales After Transactions' => $recalculatedPayout, 'Return/refund cost' => $transactionRefundTotal] as $label => $amount)
                    <div class="col-6 col-lg"><div class="rounded bg-light p-3 h-100"><div class="small text-muted mb-1">{{ $label }}</div><div class="fw-bold {{ $label === 'Sales After Transactions Total' ? 'text-primary' : '' }}">{{ $amount === null ? 'Not entered' : '₱'.number_format($amount, 2) }}</div></div></div>
                @endforeach
            </div>
            @if($hasReturn && ! $allItemsReturned && ! $transaction->tiktok_recalculated_payout_entered)
                <div class="alert alert-info small">
            This order has a return or refund. Enter the updated TikTok payout below to complete the recalculated total.
                </div>
            @endif
            <div class="table-responsive tiktok-order-items">
                <table class="table align-middle">
                    <thead class="table-light"><tr><th>Item</th><th class="text-center">Quantity</th><th class="text-end">Original item total</th><th class="text-end">Latest item total</th><th class="text-end">Total Shipping Fee 5%</th></tr></thead>
                    <tbody>
                    @foreach($transaction->items as $item)
                        <tr data-order-item data-product-name="{{ $item->product?->name ?? 'Product #'.$item->product_id }}">
                            @php
                                $replacementStatus = $item->replacements
                                    ->sortByDesc('created_at')
                                    ->first()?->status;
                                $returnStatus = $item->return_status ?? 'none';
                                $isReturned = $returnStatus === 'received' || $item->inventoryReturns->isNotEmpty();
                                $goodReturned = (int) $item->inventoryReturns->where('condition', 'good')->sum('quantity');
                                $damagedReturned = (int) $item->inventoryReturns->where('condition', 'damaged')->sum('quantity');
                                $itemRefundTotal = (float) $item->inventoryReturns->sum('refund_amount');
                                $itemRemainingQuantity = $remainingQuantity($item);
                                $itemReturnedQuantity = max((int) ($item->returned_quantity ?? 0), (int) $item->inventoryReturns->sum('quantity'));
                                $approvedReplacement = $item->replacements
                                    ->sortByDesc('created_at')
                                    ->firstWhere('status', 'approved');
                                $currentItemTotal = $latestItemTotal($item);
                                $itemShippingFee = round($currentItemTotal * 0.05, 2);
                            @endphp
                            <td>
                                <div class="fw-semibold">
                                    {{ $item->product?->name ?? 'Product #'.$item->product_id }}
                                    @if($isReturned)
                                        <span class="badge bg-info text-dark ms-1">RETURNED</span>
                                    @elseif($returnStatus === 'requested')
                                        <span class="badge bg-warning text-dark ms-1">RETURN REQUESTED</span>
                                    @elseif($returnStatus === 'refund_only')
                                        <span class="badge bg-secondary ms-1">REFUND ONLY</span>
                                    @elseif($returnStatus === 'rejected')
                                        <span class="badge bg-danger ms-1">RETURN REJECTED</span>
                                    @endif
                                    @if($item->refund_status === 'completed')
                                        <span class="badge bg-success ms-1">REFUNDED</span>
                                    @endif
                                    @if($replacementStatus === 'approved')
                                        <span class="badge bg-success ms-1">REPLACED</span>
                                    @elseif($replacementStatus === 'pending')
                                        <span class="badge bg-warning text-dark ms-1">REPLACEMENT PENDING</span>
                                    @elseif($replacementStatus === 'rejected')
                                        <span class="badge bg-danger ms-1">REPLACEMENT REJECTED</span>
                                    @endif
                                </div>
                                <div class="small text-muted">₱{{ number_format($item->unit_price, 2) }} each &middot; {{ number_format($item->discount_percentage, 2) }}% discount</div>
                                @if($isReturned || $itemRefundTotal > 0)
                                    <div class="small text-info mt-1">
                                        Return details:
                                        {{ $goodReturned }} good · {{ $damagedReturned }} damaged
                                    </div>
                                @endif
                                @if($itemRefundTotal > 0)
                                    <div class="small text-danger mt-1">Refund cost: ₱{{ number_format($itemRefundTotal, 2) }}</div>
                                @endif
                                @foreach($item->replacements->sortByDesc('created_at') as $replacement)
                                    <div class="mt-2 p-2 rounded {{ $replacement->status === 'approved' ? 'bg-success-subtle text-success-emphasis' : ($replacement->status === 'rejected' ? 'bg-danger-subtle text-danger-emphasis' : 'bg-warning-subtle text-warning-emphasis') }}">
                                        <div class="fw-semibold">
                                            Replacement item:
                                            {{ $replacement->replacementProduct?->name ?? 'Product #'.$replacement->replacement_product_id }}
                                            <span class="badge ms-1 {{ $replacement->status === 'approved' ? 'bg-success' : ($replacement->status === 'rejected' ? 'bg-danger' : 'bg-warning text-dark') }}">{{ strtoupper($replacement->status) }}</span>
                                        </div>
                                        <div class="small">
                                            Original returned: {{ $replacement->quantity }} · Replacement released: {{ $replacement->replacement_quantity ?: $replacement->quantity }}
                                            · TikTok retail price: ₱{{ number_format($replacement->replacement_unit_price, 2) }}
                                            @if((float) $replacement->price_adjustment !== 0)
                                                · Price adjustment: ₱{{ number_format($replacement->price_adjustment, 2) }}
                                            @endif
                                        </div>
                                        <div class="small text-muted">
                                            Requested {{ optional($replacement->created_at)->format('M d, Y h:i A') }}
                                            @if($replacement->reason)
                                                · {{ $replacement->reason }}
                                            @endif
                                        </div>
                                    </div>
                                @endforeach
                            </td>
                            <td class="text-center">
                                <strong>{{ $itemRemainingQuantity }}</strong>
                                @if($itemReturnedQuantity > 0)
                                    <small class="d-block text-danger">{{ $itemReturnedQuantity }} returned</small>
                                    <small class="d-block text-muted">Original: {{ $item->quantity }}</small>
                                @endif
                            </td>
                            <td class="text-end fw-semibold">
                                <span class="d-block">₱{{ number_format($item->line_total, 2) }}</span>
                                @if($item->replacements->isNotEmpty())
                                    <small class="text-muted">Original item total</small>
                                @endif
                            </td>
                            <td class="text-end fw-semibold {{ $approvedReplacement || $itemReturnedQuantity > 0 ? 'text-success' : '' }}">
                                <span class="d-block">₱{{ number_format($currentItemTotal, 2) }}</span>
                                @if($approvedReplacement)
                                    <small class="text-success">After replacement</small>
                                @elseif($itemReturnedQuantity > 0)
                                    <small class="text-success">After return</small>
                                @else
                                    <small class="text-muted">Current item total</small>
                                @endif
                            </td>
                            <td class="text-end text-danger">₱{{ number_format($itemShippingFee, 2) }}</td>
                        </tr>
                    @endforeach
                    </tbody>
                </table>
            </div>
            @if($canReportReturns)
                <section class="card border-warning-subtle mt-3" aria-label="TikTok return items">
                    <div class="card-body">
                        <h6 class="fw-bold mb-1"><i class="fa-solid fa-rotate-left text-warning me-2"></i>Report returned items</h6>
                        <p class="small text-muted mb-3">Record which items the customer says they are returning. Mark an item as received only after it physically arrives at the hub.</p>
                        @foreach($transaction->items as $item)
                            @include('hubs.reports.tiktok-return-form', ['hub' => $hub, 'transaction' => $transaction, 'item' => $item])
                        @endforeach
                    </div>
                </section>
            @endif
            @if($transaction->replacements->where('status', 'approved')->isNotEmpty())
                <div class="alert alert-info small mt-3 mb-0">
                    The item rows preserve the original sale for audit history. The <strong>Adjusted order total</strong> above includes approved replacement price adjustments.
                </div>
            @endif
            @if($allItemsReturned)
                <div class="alert alert-secondary d-flex align-items-start gap-2 mt-3 mb-0">
                    <i class="fa-solid fa-lock mt-1"></i>
                    <div><strong>Edit payout &amp; delivery locked</strong><div class="small">Every item in this order has been returned. There are no remaining units to deliver, so these order details can no longer be changed.</div></div>
                </div>
            @else
            <details @if(old('editing_order') == $transaction->id && !old('editing_item')) open @endif>
                <summary class="text-primary fw-semibold py-2">Edit payout &amp; delivery</summary>
                @if($canEditDetails)
                <form method="POST" action="{{ route('hub.report.tiktok.update', ['hub' => $hub->id, 'transaction' => $transaction->id]) }}" class="bg-light rounded p-3 my-3">
                    @csrf @method('PATCH')
                    <input type="hidden" name="editing_order" value="{{ $transaction->id }}">
                    <h6 class="fw-bold">Sales total &amp; delivery</h6>
                    @if($transaction->sales_after_transaction_fee === null && $transaction->replacements->where('status', 'approved')->isNotEmpty())
                        <div class="alert alert-warning small">A replacement was approved for this order. Enter the updated Sales After Transactions Total from TikTok after the replacement transaction is reflected.</div>
                    @endif
                    <div class="row g-3">
                        @php($payoutValue = $hasReturn ? $transaction->tiktok_recalculated_payout : $transaction->sales_after_transaction_fee)
                        @foreach(['sales_after_transaction_fee' => [$hasReturn ? 'Updated TikTok payout (₱)' : 'Entered TikTok payout (₱)', 'number', $payoutValue], 'drop_off_date' => ['Drop-off date', 'date', optional($transaction->drop_off_date)->format('Y-m-d')], 'courier' => ['Courier', 'text', $transaction->courier], 'note' => ['Order notes', 'text', $transaction->note]] as $field => [$label, $type, $value])
                        <div class="col-md-6 col-xl-4"><label class="form-label small" for="{{ $field }}-{{ $transaction->id }}">{{ $label }}</label><input id="{{ $field }}-{{ $transaction->id }}" name="{{ $field }}" type="{{ $type }}" @if($type === 'number') step="0.01" @endif @if($field === 'refund_shipping_fee') min="0" @endif value="{{ old('editing_order') == $transaction->id && !old('editing_item') ? old($field, $value) : $value }}" class="form-control"></div>
                        @endforeach
                    </div>
                    <p class="small text-muted mt-3">{{ $hasReturn ? 'After a return, enter the updated payout from TikTok. Leave it blank if you are still waiting.' : 'Copy the payout from TikTok. Leave it blank if you are still waiting.' }}</p>
                    <button class="btn btn-primary" type="submit">Save order details</button>
                </form>
                @endif
            </details>
            @endif

        </div>
    </article>
        </div>
        <div class="modal-footer"><button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button></div>
    </div></div>
</div>
@endforeach
@push('scripts')
<script>
const reopenOrder = document.querySelector('[data-reopen-order]');
if (reopenOrder) bootstrap.Modal.getOrCreateInstance(reopenOrder).show();

</script>
@endpush
