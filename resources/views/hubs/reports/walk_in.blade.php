<style>
    .walk-in-report-card { border: 0; border-radius: 16px; overflow: hidden; box-shadow: 0 10px 28px rgba(146, 64, 14, .08); }
    .walk-in-report-card .card-header { background: linear-gradient(135deg, #fff7ed, #fffbeb); border-bottom: 1px solid #fed7aa; }
    .walk-in-report-card thead th { background: #ffedd5; color: #9a3412; white-space: nowrap; }
    .walk-in-order-summary { border-left: 4px solid #f59e0b; background: #fffbeb; }
    .walk-in-order-summary-section { border: 1px solid #e7e5e4; border-radius: 12px; background: #fff; padding: .85rem; height: 100%; }
    .walk-in-order-summary-section .section-title { color: #78716c; font-size: .72rem; font-weight: 800; letter-spacing: .06em; text-transform: uppercase; margin-bottom: .65rem; }
    .walk-in-order-summary-section .walk-in-order-summary { min-height: 78px; }
    .walk-in-order-summary-section .walk-in-order-summary .small { font-size: .72rem; }
    .walk-in-order-totals { display: flex; flex-direction: column; }
    .walk-in-order-totals-list { display: grid; flex: 1; grid-template-rows: repeat(2, minmax(78px, 1fr)); gap: .5rem; }
    .walk-in-replacement-impact-list { grid-template-rows: minmax(78px, 1fr); }
    .walk-in-order-totals-list .walk-in-order-summary { display: flex; flex-direction: column; justify-content: center; margin: 0 !important; }
    .walk-in-order-modal .modal-header { background: linear-gradient(135deg, #fff7ed, #fffbeb); border-bottom: 1px solid #fed7aa; }
    .walk-in-order-modal .modal-body { background: #fafaf9; }
    .walk-in-order-modal .walk-in-order-items { display: grid; gap: .85rem; }
    .walk-in-order-modal .walk-in-item-card { border: 1px solid #e7e5e4; border-radius: 12px; background: #fff; padding: .65rem .75rem; }
    .walk-in-order-modal .walk-in-item-name { font-weight: 700; line-height: 1.4; overflow-wrap: anywhere; }
    .walk-in-order-modal .walk-in-item-meta { color: #78716c; font-size: .76rem; }
    .walk-in-order-modal .walk-in-item-stat { height: 100%; padding: .35rem .45rem; border: 1px solid #e7e5e4; border-radius: 8px; background: #fafaf9; text-align: center; }
    .walk-in-order-modal .walk-in-return-stat { display: flex; flex-direction: column; align-items: flex-start; gap: .2rem; min-width: 0; padding: .55rem .65rem; text-align: left; font-size: .74rem; line-height: 1.3; }
    .walk-in-order-modal .walk-in-return-stat .walk-in-item-stat-label { margin-bottom: .1rem; }
    .walk-in-order-modal .walk-in-return-stat .badge { max-width: 100%; white-space: normal; text-align: left; }
    .walk-in-order-modal .walk-in-return-stat .small { overflow-wrap: anywhere; }
    .walk-in-order-modal .walk-in-item-stat-label { color: #78716c; font-size: .68rem; font-weight: 700; text-transform: uppercase; }
    .walk-in-order-modal .walk-in-item-stat-value { font-weight: 700; }
    .walk-in-order-modal .walk-in-replacements { border-top: 1px solid #e7e5e4; margin-top: .85rem; padding-top: .85rem; }
    .walk-in-order-modal .walk-in-replacement-table { min-width: 760px; }
    .walk-in-order-modal .walk-in-replacement-table th { background: #f5f5f4; color: #57534e; font-size: .68rem; text-transform: uppercase; white-space: nowrap; }
    .walk-in-order-modal .walk-in-replacement-table td { vertical-align: middle; font-size: .78rem; }
    .walk-in-order-modal .walk-in-replacement-table .replacement-product { min-width: 190px; font-weight: 600; }
    .walk-in-order-modal .walk-in-replacement-table .replacement-notes { min-width: 170px; }
    .walk-in-order-modal .walk-in-order-total { background: #ecfdf5; border: 1px solid #86efac; border-radius: 10px; color: #166534; padding: .85rem 1rem; }
    .walk-in-order-modal .replacement-detail { border-left: 3px solid #22c55e; }
    .walk-in-order-modal .modal-footer { background: #fff; border-top: 1px solid #e7e5e4; }
    .walk-in-report-summary { display: grid; grid-template-columns: repeat(4, minmax(0, 1fr)); gap: .65rem; padding: 1rem; background: #fffaf0; border-bottom: 1px solid #fed7aa; }
    .walk-in-report-summary-heading { grid-column: 1 / -1; display: flex; flex-wrap: wrap; align-items: center; justify-content: space-between; gap: .75rem; }
    .walk-in-report-summary-heading h5 { margin: 0; font-size: .95rem; font-weight: 700; }
    .walk-in-report-summary-heading p { margin: .15rem 0 0; color: #78716c; font-size: .75rem; }
    .walk-in-report-summary-card { min-height: 82px; padding: .75rem .85rem; border: 1px solid #fed7aa; border-radius: 10px; background: #fff; box-shadow: 0 2px 8px rgba(120, 53, 15, .04); }
    .walk-in-report-summary-card .label { color: #78716c; font-size: .68rem; font-weight: 700; text-transform: uppercase; letter-spacing: .04em; }
    .walk-in-report-summary-card .value { margin-top: .15rem; color: #1c1917; font-size: 1.1rem; font-weight: 700; line-height: 1.2; }
    .walk-in-report-summary-card .subvalue { color: #78716c; font-size: .72rem; }
    .walk-in-report-mop { display: flex; flex-wrap: wrap; gap: .3rem .8rem; margin-top: .35rem; color: #57534e; font-size: .72rem; }
    .walk-in-report-card { max-width: 100%; }
    .walk-in-replacement-modal .modal-dialog { max-width: min(1140px, calc(100vw - 1.5rem)); }
    .walk-in-replacement-modal .modal-content { border: 0; border-radius: 12px; }
    .walk-in-replacement-modal .modal-header,
    .walk-in-replacement-modal .modal-footer { padding: .65rem 1rem; }
    .walk-in-replacement-modal .modal-body { padding: .75rem 1rem; }
    .walk-in-replacement-modal .modal-body > .alert { margin-bottom: .65rem; padding: .5rem .75rem; }
    .walk-in-replacement-modal .replacement-form-section { border: 1px solid #e7e5e4; border-radius: 10px; padding: .75rem; background: #fff; }
    .walk-in-replacement-modal .replacement-form-section-title { margin-bottom: .65rem; color: #57534e; font-size: .78rem; font-weight: 800; letter-spacing: .035em; text-transform: uppercase; }
    .walk-in-replacement-modal .exchange-total-card { border: 1px solid #dbeafe; border-radius: 10px; background: #eff6ff; padding: .75rem; }
    .walk-in-replacement-modal .exchange-total-card .exchange-total-value { color: #1d4ed8; font-size: 1.1rem; font-weight: 800; }
    .walk-in-replacement-modal .exchange-total-card .exchange-total-label { color: #64748b; font-size: .7rem; font-weight: 700; text-transform: uppercase; }
    .walk-in-replacement-modal [data-replacement-price-panel] { padding: .55rem .75rem !important; }
    .walk-in-replacement-modal [data-walk-in-exchange-summary] { margin-top: .65rem !important; }
    .walk-in-replacement-modal [data-walk-in-exchange-summary] .card-body { padding: .7rem .85rem; }
    .walk-in-replacement-modal [data-exchange-lines] { margin-top: .65rem !important; }
    .walk-in-replacement-modal [data-exchange-lines] > .alert { margin-bottom: .5rem !important; padding: .45rem .75rem; }
    .walk-in-replacement-modal [data-exchange-lines] .card-body { padding: .65rem .8rem; }
    .walk-in-replacement-modal [data-exchange-lines] .card { margin-top: .5rem !important; }
    .walk-in-replacement-modal [data-exchange-lines] p { margin-bottom: .5rem !important; }
    .walk-in-replacement-modal .form-label { margin-bottom: .25rem; }
    .walk-in-replacement-modal .form-text { font-size: .72rem; }
    .walk-in-replacement-modal textarea { resize: vertical; }
    @media (max-width: 900px) { .walk-in-report-summary { grid-template-columns: repeat(2, minmax(0, 1fr)); } }
    @media (max-width: 520px) { .walk-in-report-summary { grid-template-columns: 1fr; } }
</style>

<div id="walkInReportImageSource">
    <div class="card walk-in-report-card">
        <div class="card-header p-3 d-flex justify-content-between align-items-center gap-3">
            <div><div class="fw-bold text-warning-emphasis"><i class="fa-solid fa-cash-register me-2"></i>Walk-In Sales</div><div class="small text-muted">Counter sales, discounts, returns, refunds, and replacements.</div><div class="small fw-semibold text-secondary mt-1">Report period: {{ $dateFrom ? \Illuminate\Support\Carbon::parse($dateFrom)->format('M d, Y') : 'All dates' }}{{ $dateTo ? ' - '.\Illuminate\Support\Carbon::parse($dateTo)->format('M d, Y') : ($dateFrom ? ' - '.\Illuminate\Support\Carbon::parse($dateFrom)->format('M d, Y') : '') }}</div></div>
            <span class="badge rounded-pill text-bg-light">{{ number_format($totalTransactions) }} transaction(s)</span>
        </div>
        <div class="walk-in-report-summary d-none" data-walk-in-preview-only>
            <div class="walk-in-report-summary-heading"><div><h5>Activity overview</h5><p>Sales, returns, and customer activity for this period</p></div><span class="badge rounded-pill text-bg-success">Verified sales</span></div>
            <div class="walk-in-report-summary-card"><div class="label"><i class="fa-solid fa-chart-line me-1 text-primary" aria-hidden="true"></i>Total gross sales</div><div class="value">₱{{ number_format($metrics['gross_sales'], 2) }}</div><div class="subvalue">{{ number_format($totalTransactions) }} transaction(s)</div></div>
            <div class="walk-in-report-summary-card"><div class="label"><i class="fa-solid fa-tag me-1 text-danger" aria-hidden="true"></i>Total discounts</div><div class="value text-danger">₱{{ number_format($metrics['discounts'], 2) }}</div></div>
            <div class="walk-in-report-summary-card"><div class="label"><i class="fa-solid fa-sack-dollar me-1 text-success" aria-hidden="true"></i>Total Amount after discounts</div><div class="value text-success">₱{{ number_format($metrics['total_sales'], 2) }}</div></div>
            <div class="walk-in-report-summary-card"><div class="label"><i class="fa-solid fa-rotate-left me-1 text-danger" aria-hidden="true"></i>Total of Return</div><div class="value">{{ number_format($metrics['return_quantity'] ?? 0) }} item(s)</div><div class="subvalue">{{ number_format($metrics['return_count'] ?? 0) }} return record(s)</div></div>
            <div class="walk-in-report-summary-card"><div class="label"><i class="fa-solid fa-arrows-rotate me-1 text-warning" aria-hidden="true"></i>Total Replacement</div><div class="value">{{ number_format($metrics['replacement_count'] ?? 0) }}</div><div class="subvalue">replacement request(s)</div></div>
            <div class="walk-in-report-summary-card"><div class="label"><i class="fa-solid fa-money-bill-transfer me-1 text-danger" aria-hidden="true"></i>Refund cost</div><div class="value text-danger">₱{{ number_format($metrics['refund_total'] ?? 0, 2) }}</div><div class="subvalue">recorded return/refund amount</div></div>
            <div class="walk-in-report-summary-card"><div class="label"><i class="fa-solid fa-users me-1 text-primary" aria-hidden="true"></i>Total customers</div><div class="value">{{ number_format($customerMetrics['total']) }}</div></div>
            <div class="walk-in-report-summary-card"><div class="label"><i class="fa-solid fa-user-plus me-1 text-success" aria-hidden="true"></i>New customers</div><div class="value text-success">{{ number_format($customerMetrics['new']) }}</div></div>
            <div class="walk-in-report-summary-card" style="grid-column: 1 / -1;"><div class="label"><i class="fa-solid fa-credit-card me-1 text-primary" aria-hidden="true"></i>Sales by payment method</div><div class="walk-in-report-mop">@forelse($paymentBreakdown as $payment)<span><strong>{{ $payment->payment_method }}</strong>: ₱{{ number_format($payment->total, 2) }}</span>@empty<span>No payment records</span>@endforelse</div></div>
        </div>
        <div class="table-responsive" data-walk-in-detail-table><table class="table table-hover align-middle mb-0">
            <thead>
                @if($hub->is_head_office)
                    <tr><th>Order Date</th><th>Order ID</th><th data-walk-in-preview-remove>Customer Name</th><th data-walk-in-preview-remove>Items</th><th data-walk-in-preview-remove>Payment</th><th data-walk-in-screen-only>Action</th></tr>
                @else
                    <tr><th>Date</th><th data-walk-in-preview-remove>Customer</th><th data-walk-in-preview-remove>Items</th><th>MOP</th><th data-walk-in-preview-remove>Payment</th><th class="text-end">Gross</th><th class="text-end">Discount</th><th class="text-end">Net Sales</th><th data-walk-in-screen-only>Action</th></tr>
                @endif
            </thead>
            <tbody>
                @forelse($transactions as $transaction)
                    @php
                        $approvedReplacements = $transaction->replacements->where('status', 'approved');
                        $walkInGross = $transaction->items->sum(fn ($item) => (float) $item->unit_price * max(0, (int) $item->quantity - $item->returnedQuantity()))
                            + $approvedReplacements->sum(fn ($replacement) => (float) $replacement->replacement_unit_price * (int) ($replacement->replacement_quantity ?: $replacement->quantity));
                        $walkInNet = $transaction->netOrderTotal((float) ($transaction->grand_total ?: $transaction->sub_total ?: $transaction->items->sum('line_total')));
                    @endphp
                    <tr>
                        <td>{{ optional($transaction->order_date)->format('m/d/Y') }}</td>
                        @if($hub->is_head_office)
                            <td class="fw-semibold">{{ $transaction->order_number ?: '—' }}</td>
                            <td data-walk-in-preview-remove class="fw-semibold">{{ $transaction->customer_name ?: 'Walk-In Customer' }}@include('hubs.reports._new-customer-badge')</td>
                            <td data-walk-in-preview-remove>{{ $transaction->items->count() }} product(s)<small class="d-block text-muted">{{ $transaction->items->sum(fn ($item) => (int) $item->quantity - $item->returnedQuantity()) }} remaining unit(s)</small></td>
                            <td data-walk-in-preview-remove><div>{{ strtoupper($transaction->mode_of_payment ?: '—') }}</div><span class="badge mt-1 {{ ($transaction->payment_status ?? 'paid') === 'paid' ? 'bg-success' : 'bg-warning text-dark' }}">{{ strtoupper($transaction->payment_status ?? 'paid') }}</span></td>
                        @else
                            <td data-walk-in-preview-remove class="fw-semibold">{{ $transaction->customer_name ?: 'Walk-In Customer' }}@include('hubs.reports._new-customer-badge')</td>
                            <td data-walk-in-preview-remove>{{ $transaction->items->count() }} product(s)<small class="d-block text-muted">{{ $transaction->items->sum(fn ($item) => (int) $item->quantity - $item->returnedQuantity()) }} remaining unit(s)</small></td>
                            <td>{{ strtoupper($transaction->mode_of_payment ?: '—') }}</td><td data-walk-in-preview-remove><span class="badge {{ ($transaction->payment_status ?? 'paid') === 'paid' ? 'bg-success' : 'bg-warning text-dark' }}">{{ strtoupper($transaction->payment_status ?? 'paid') }}</span></td>
                            <td class="text-end">₱{{ number_format($walkInGross, 2) }}</td><td class="text-end text-danger">₱{{ number_format(max(0, $walkInGross - $walkInNet), 2) }}</td><td class="text-end fw-bold text-success">₱{{ number_format($walkInNet, 2) }}</td>
                        @endif
                        <td data-walk-in-screen-only><button type="button" class="btn btn-sm btn-outline-warning text-nowrap" data-bs-toggle="modal" data-bs-target="#walk-in-order-{{ $transaction->id }}"><i class="fa-solid fa-receipt me-1"></i> View order</button></td>
                    </tr>
                @empty<tr><td colspan="{{ $hub->is_head_office ? 6 : 9 }}" class="text-center text-muted py-4">No walk-in sales found.</td></tr>@endforelse
            </tbody>
            @unless($hub->is_head_office)
                <tfoot class="table-light fw-bold"><tr><td colspan="5" data-walk-in-total-label>TOTAL GROSS SALES / {{ number_format($totalTransactions) }} TRANSACTIONS</td><td class="text-end">₱{{ number_format($metrics['gross_sales'], 2) }}</td><td class="text-end text-danger">₱{{ number_format($metrics['discounts'], 2) }}</td><td class="text-end text-success">₱{{ number_format($metrics['total_sales'], 2) }}</td><td data-walk-in-screen-only></td></tr></tfoot>
            @endunless
        </table></div>
    </div>
</div>

@foreach($transactions as $transaction)
    @php
        $approvedWalkInReplacements = $transaction->replacements->where('status', 'approved');
        $walkInReplacementShippingFee = round((float) $approvedWalkInReplacements->sum('replacement_shipping_fee_amount'), 2);
        $walkInGross = $transaction->items->sum(fn ($item) => (float) $item->unit_price * max(0, (int) $item->quantity - $item->returnedQuantity()))
            + $approvedWalkInReplacements->sum(fn ($replacement) => (float) $replacement->replacement_unit_price * (int) ($replacement->replacement_quantity ?: $replacement->quantity));
        $walkInNet = $transaction->netOrderTotal((float) ($transaction->grand_total ?: $transaction->sub_total ?: $transaction->items->sum('line_total')));
        $walkInPaid = (float) ($transaction->amount_paid ?? 0);
        $walkInBalance = max(0, round($walkInNet - $walkInPaid, 2));
        $walkInReturnedQty = $transaction->items->sum(fn ($item) => $item->returnedQuantity());
        $walkInRefund = max(
            (float) $transaction->items->sum(fn ($item) => $item->refundCostAmount()),
            (float) $transaction->inventoryReturns->whereNull('transaction_item_id')->sum('refund_amount')
        );
        $walkInReplacementCredit = $approvedWalkInReplacements->sum(fn ($replacement) => (float) $replacement->original_unit_price * (int) $replacement->quantity);
        $walkInReplacementCharge = $approvedWalkInReplacements->sum(fn ($replacement) => (float) $replacement->replacement_unit_price
            * (1 - ((float) ($replacement->replacement_discount_percentage ?? 0) / 100))
            * (int) ($replacement->replacement_quantity ?: $replacement->quantity));
        $walkInReplacementAdjustment = max(0, round($walkInReplacementCharge - $walkInReplacementCredit, 2));
    @endphp
    <div class="modal fade" id="walk-in-order-{{ $transaction->id }}" data-bs-backdrop="static" data-bs-keyboard="false" tabindex="-1" aria-labelledby="walk-in-title-{{ $transaction->id }}" aria-hidden="true">
        <div class="modal-dialog modal-xl modal-dialog-scrollable modal-fullscreen-sm-down"><div class="modal-content walk-in-order-modal">
            <div class="modal-header bg-warning-subtle"><div><h5 class="modal-title" id="walk-in-title-{{ $transaction->id }}"><i class="fa-solid fa-receipt me-2"></i>Walk-In Order #{{ $transaction->order_number ?: $transaction->id }}</h5><small class="text-muted">{{ $transaction->customer_name ?: 'Walk-In Customer' }}@include('hubs.reports._new-customer-badge') · {{ optional($transaction->order_date)->format('M d, Y') }} · {{ strtoupper($transaction->mode_of_payment ?: '—') }}</small></div></div>
            <div class="modal-body">
                <div class="row g-3 mb-4"><div class="col-12">@include('hubs.reports._payment-proof', ['paymentRecord' => $transaction])</div></div>
                <div class="row g-3 mb-4">
                    <div class="col-12 col-lg-4">
                        <div class="walk-in-order-summary-section walk-in-order-totals">
                            <div class="section-title"><i class="fa-solid fa-receipt me-1"></i>Order totals</div>
                            <div class="walk-in-order-totals-list">
                                <div class="walk-in-order-summary rounded p-3"><div class="small text-muted">Current gross amount</div><div class="fs-5 fw-bold">₱{{ number_format($walkInGross, 2) }}</div></div>
                                <div class="walk-in-order-summary rounded p-3"><div class="small text-muted">Total discounts</div><div class="fs-5 fw-bold text-danger">₱{{ number_format(max(0, $walkInGross - $walkInNet), 2) }}</div></div>
                            </div>
                        </div>
                    </div>
                    <div class="col-12 col-lg-4">
                        <div class="walk-in-order-summary-section walk-in-order-totals">
                            <div class="section-title"><i class="fa-solid fa-arrow-right-arrow-left me-1"></i>Replacement impact</div>
                            <div class="walk-in-order-totals-list walk-in-replacement-impact-list">
                                <div class="walk-in-order-summary rounded p-3"><div class="small text-muted">Replacement adjustment</div><div class="fs-5 fw-bold {{ $walkInReplacementAdjustment > 0 ? 'text-danger' : 'text-success' }}">{{ $walkInReplacementAdjustment >= 0 ? '+' : '' }}₱{{ number_format($walkInReplacementAdjustment, 2) }}</div></div>
                            </div>
                        </div>
                    </div>
                    <div class="col-12 col-lg-4">
                        <div class="walk-in-order-summary-section walk-in-order-totals">
                            <div class="section-title"><i class="fa-solid fa-money-check-dollar me-1"></i>Payment status</div>
                            <div class="walk-in-order-totals-list">
                                <div class="walk-in-order-summary rounded p-3"><div class="small text-muted">Amount paid</div><div class="fs-5 fw-bold text-primary">₱{{ number_format($walkInPaid, 2) }}</div></div>
                                <div class="walk-in-order-summary rounded p-3 border-success"><div class="small text-muted">Total Returns (Qty) / Refunds</div><div class="fs-5 fw-bold text-success">{{ number_format($walkInReturnedQty) }} item(s)</div><div class="small text-muted mt-1">Refunds: ₱{{ number_format($walkInRefund, 2) }}</div></div>
                            </div>
                        </div>
                    </div>
                </div>
                @if($walkInBalance > 0 && $approvedWalkInReplacements->isNotEmpty())
                    <div class="alert alert-warning d-flex flex-wrap align-items-center justify-content-between gap-3 mb-4">
                        <div><strong>Replacement balance is still unpaid.</strong><div class="small">Record the additional payment of ₱{{ number_format($walkInBalance, 2) }} to mark this order as paid.</div></div>
                        <form method="POST" enctype="multipart/form-data" action="{{ route('hub.report.walk-in.replacement-payment.store', ['hub' => $hub->id, 'transaction' => $transaction->id]) }}" class="d-flex flex-wrap gap-2 align-items-end">
                            @csrf
                            <div><label class="form-label small mb-1">Payment amount</label><input type="number" name="amount" class="form-control form-control-sm" min="0.01" max="{{ number_format($walkInBalance, 2, '.', '') }}" step="0.01" value="{{ number_format($walkInBalance, 2, '.', '') }}" required></div>
                            <div><label class="form-label small mb-1">Payment method</label><select name="mode_of_payment" class="form-select form-select-sm" required><option value="CASH">Cash</option><option value="GCASH">GCash</option><option value="MAYA">Maya</option><option value="BDO">BDO</option><option value="METROBANK">Metrobank</option><option value="BPI">BPI</option><option value="DATED_CHECK">Dated check</option><option value="POST_DATED_CHECK">Post-dated check</option><option value="OTHERS">Other</option></select></div>
                            <div class="d-none" data-replacement-custom-mop><label class="form-label small mb-1">Specify other MOP</label><input type="text" name="custom_mop" class="form-control form-control-sm" placeholder="Enter payment method"></div>
                            <div><label class="form-label small mb-1">Proof of payment <span class="text-muted" data-replacement-proof-label>(required)</span></label><input type="file" name="walkin_payment_proofs[]" class="form-control form-control-sm" accept="image/jpeg,image/png,image/webp,application/pdf" multiple></div>
                            <div class="d-none" data-replacement-check-field><label class="form-label small mb-1">Check number</label><input type="text" name="check_number" class="form-control form-control-sm" placeholder="For checks"></div>
                            <div class="d-none" data-replacement-check-field><label class="form-label small mb-1">Check date</label><input type="date" name="check_date" class="form-control form-control-sm"></div>
                            <button class="btn btn-sm btn-warning">Record payment</button>
                        </form>
                    </div>
                @endif
                @if($transaction->paymentRecords->isNotEmpty())
                    <div class="border rounded-3 bg-white p-3 mb-4">
                        <div class="fw-semibold mb-2"><i class="fa-solid fa-clock-rotate-left me-1"></i>Replacement payment history</div>
                        <div class="table-responsive"><table class="table table-sm align-middle mb-0"><thead><tr><th>Date</th><th>Method</th><th>Amount</th><th>Check details</th><th>Proof</th></tr></thead><tbody>
                            @foreach($transaction->paymentRecords as $payment)
                                <tr><td>{{ optional($payment->created_at)->format('M d, Y h:i A') }}</td><td>{{ strtoupper($payment->mode_of_payment ?: $payment->custom_mop ?: '—') }}</td><td>₱{{ number_format($payment->amount, 2) }}</td><td>{{ $payment->check_number ?: '—' }}@if($payment->check_date)<small class="d-block text-muted">{{ $payment->check_date->format('M d, Y') }}</small>@endif</td><td>@foreach($payment->walkin_payment_proofs ?? [] as $proof)<a href="{{ \Illuminate\Support\Facades\Storage::disk('public')->url($proof) }}" target="_blank" rel="noopener" class="d-block">Open proof</a>@endforeach</td></tr>
                            @endforeach
                        </tbody></table></div>
                    </div>
                @endif
                <div class="walk-in-order-items">
                    @forelse($transaction->items as $item)
                        @php
                            $walkReturns = $item->inventoryReturns;
                            $walkReturnedQty = max((int) ($item->returned_quantity ?? 0), (int) $walkReturns->sum('quantity'));
                            $walkReturnStatus = $walkReturns->isNotEmpty() ? 'received' : ($item->return_status ?: 'none');
                            $walkReturnLabel = match ($walkReturnStatus) { 'requested' => 'RETURN REQUESTED', 'received' => 'RETURN RECEIVED', 'rejected' => 'RETURN REJECTED', 'refund_only' => 'REFUND ONLY', default => 'NO RETURN' };
                            $walkReplacements = $item->replacements->sortByDesc('created_at');
                            $latestWalkReturn = $walkReturns->first();
                            $walkReturnDetails = [];
                            if ($walkReturnedQty > 0) {
                                $walkReturnDetails[] = $walkReturnedQty.' returned';
                                $returnCondition = $latestWalkReturn?->condition ?: $item->return_condition;
                                if ($returnCondition) {
                                    $walkReturnDetails[] = ucfirst($returnCondition);
                                }
                                if ($latestWalkReturn?->occurred_on) {
                                    $walkReturnDetails[] = $latestWalkReturn->occurred_on->format('M d, Y');
                                }
                                if ($walkReturns->count() > 1) {
                                    $walkReturnDetails[] = '+'.($walkReturns->count() - 1).' more';
                                }
                            } elseif ($item->return_condition) {
                                $walkReturnDetails[] = ucfirst($item->return_condition);
                            }
                        @endphp
                        <article class="walk-in-item-card">
                            <div class="row g-2 align-items-center">
                                <div class="col-12 col-lg-3">
                                    <div class="walk-in-item-name">{{ $item->product?->name ?? 'Product #'.$item->product_id }}</div>
                                    <div class="walk-in-item-meta">Item ID: {{ $item->product?->item_id ?? '—' }}</div>
                                </div>
                                <div class="col-4 col-lg-1">
                                    <div class="walk-in-item-stat"><div class="walk-in-item-stat-label">Qty</div><div class="walk-in-item-stat-value">{{ $item->quantity }}</div></div>
                                </div>
                                <div class="col-4 col-lg-2">
                                    <div class="walk-in-item-stat"><div class="walk-in-item-stat-label">Unit price</div><div class="walk-in-item-stat-value">₱{{ number_format($item->unit_price, 2) }}</div></div>
                                </div>
                                <div class="col-4 col-lg-2">
                                    <div class="walk-in-item-stat"><div class="walk-in-item-stat-label">Item total</div><div class="walk-in-item-stat-value">₱{{ number_format($item->line_total, 2) }}</div></div>
                                </div>
                                <div class="col-8 col-lg-2">
                                    <div class="walk-in-item-stat walk-in-return-stat">
                                        <div class="walk-in-item-stat-label">Return / refund</div>
                                        <span class="badge {{ $walkReturnStatus === 'received' ? 'bg-info text-dark' : ($walkReturnStatus === 'requested' ? 'bg-warning text-dark' : ($walkReturnStatus === 'rejected' ? 'bg-danger' : 'bg-secondary')) }}">{{ $walkReturnLabel }}</span>
                                        @if($walkReturnDetails)
                                            <div class="small mt-1">{{ implode(' · ', $walkReturnDetails) }}</div>
                                        @endif
                                        @if($item->refund_status && $item->refund_status !== 'none')<div class="small text-danger">Refund {{ ucfirst($item->refund_status) }} · ₱{{ number_format($item->customer_refund_amount ?? 0, 2) }}</div>@endif
                                        @foreach($walkReturns as $return)
                                            @if((float) $return->refund_amount > 0)
                                                <div class="small text-danger">Refund ₱{{ number_format($return->refund_amount, 2) }}</div>
                                            @endif
                                        @endforeach
                                    </div>
                                </div>
                                <div class="col-4 col-lg-2 d-flex justify-content-center align-items-center text-center">
                                    @can('request-walk-in-replacements')
                                        @if($hub->is_head_office)
                                            @if($item->remaining_returned_replaceable_quantity > 0 && $item->remaining_replaceable_quantity > 0 && in_array($transaction->status, ['confirmed', 'completed'], true) && auth()->user()?->role !== 'sales_associate')
                                                <button type="button" class="btn btn-sm btn-warning text-nowrap walk-in-replace-button" data-replacement-target="#walk-in-replacement-{{ $item->id }}" aria-label="Replace {{ $item->product?->name ?? 'item' }}" title="Request a replacement for this item"><i class="fa-solid fa-arrow-right-arrow-left me-1"></i> Replace item</button>
                                            @elseif($item->remaining_replaceable_quantity > 0 && in_array($transaction->status, ['confirmed', 'completed'], true) && auth()->user()?->role !== 'sales_associate')
                                                <button type="button" class="btn btn-sm btn-warning text-nowrap" disabled title="Available after inventory receives the returned item"><i class="fa-solid fa-arrow-right-arrow-left me-1"></i> Replace item</button>
                                            @else
                                                <span class="small text-muted">Unavailable</span>
                                            @endif
                                        @elseif($walkReturnStatus !== 'received' && $walkReturns->isEmpty() && $item->remaining_replaceable_quantity > 0 && in_array($transaction->status, ['confirmed', 'completed'], true))
                                            <button type="button" class="btn btn-sm btn-warning text-nowrap walk-in-replace-button" data-replacement-target="#walk-in-replacement-{{ $item->id }}" aria-label="Replace {{ $item->product?->name ?? 'item' }}" title="Request a replacement for this item"><i class="fa-solid fa-arrow-right-arrow-left me-1"></i> Replace item</button>
                                        @else
                                            <span class="small text-muted">Unavailable</span>
                                        @endif
                                    @endcan
                                </div>
                            </div>
                            @if($walkReplacements->isNotEmpty())
                                <div class="walk-in-replacements">
                                    <div class="small text-uppercase fw-bold text-muted mb-2">Replacement history</div>
                                    <div class="table-responsive">
                                        <table class="table table-sm table-hover align-middle mb-0 walk-in-replacement-table">
                                            <thead>
                                                <tr>
                                                    <th>Status</th>
                                                    <th>Replacement product</th>
                                                    <th class="text-center">Returned</th>
                                                    <th class="text-center">Qty</th>
                                                    <th class="text-end">Price / total</th>
                                                    <th>Notes / files</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                        @foreach($walkReplacements as $replacement)
                                            @php
                                                $replacementBadgeClass = $replacement->status === 'approved' ? 'bg-success' : ($replacement->status === 'rejected' ? 'bg-danger' : 'bg-warning text-dark');
                                                $replacementTotal = (float) $replacement->replacement_unit_price
                                                    * (1 - ((float) ($replacement->replacement_discount_percentage ?? 0) / 100))
                                                    * (int) ($replacement->replacement_quantity ?: $replacement->quantity);
                                            @endphp
                                            <tr>
                                                <td><span class="badge {{ $replacementBadgeClass }}">{{ strtoupper($replacement->status) }}</span></td>
                                                <td class="replacement-product">{{ $replacement->replacementProduct?->name ?? 'Product #'.$replacement->replacement_product_id }}</td>
                                                <td class="text-center">{{ $replacement->quantity }}</td>
                                                <td class="text-center">{{ $replacement->replacement_quantity ?: $replacement->quantity }}</td>
                                                <td class="text-end text-nowrap">
                                                    <div>₱{{ number_format($replacement->replacement_unit_price, 2) }} each</div>
                                                    @if((float) ($replacement->replacement_discount_percentage ?? 0) > 0)
                                                        <div class="small text-danger">{{ number_format($replacement->replacement_discount_percentage, 2) }}% discount</div>
                                                    @endif
                                                    @if($replacement->status === 'approved')
                                                        <div class="small fw-semibold text-success">Total ₱{{ number_format($replacementTotal, 2) }}</div>
                                                        <div class="small {{ $replacement->price_adjustment > 0 ? 'text-danger' : 'text-success' }}">Adjustment {{ $replacement->price_adjustment >= 0 ? '+' : '' }}₱{{ number_format($replacement->price_adjustment, 2) }}</div>
                                                    @endif
                                                </td>
                                                <td class="replacement-notes">
                                                    @if($replacement->reason)<div>{{ $replacement->reason }}</div>@endif
                                                    @if($replacement->rejection_reason)<div class="text-danger">{{ $replacement->rejection_reason }}</div>@endif
                                                    @if($replacement->replacement_order_slip)
                                                        <a href="{{ route('hub.report.replacement-attachment', ['hub' => $hub->id, 'replacement' => $replacement->id, 'type' => 'replacement-slip', 'index' => 0]) }}" target="_blank" rel="noopener" class="d-block">View order slip</a>
                                                    @endif
                                                    @if(!$replacement->reason && !$replacement->rejection_reason && !$replacement->replacement_order_slip)
                                                        <span class="text-muted">—</span>
                                                    @endif
                                                </td>
                                            </tr>
                                        @endforeach
                                            </tbody>
                                        </table>
                                    </div>
                                </div>
                            @endif
                        </article>
                    @empty
                        <div class="text-center text-muted py-4">No items recorded.</div>
                    @endforelse
                    <div class="walk-in-order-total d-flex flex-wrap justify-content-between align-items-center gap-2">
                        <div class="fw-bold text-uppercase">Net order total <span class="d-block small fw-normal">After discounts and approved replacements</span>@if($walkInReplacementShippingFee > 0)<span class="d-block small fw-normal text-muted">Includes ₱{{ number_format($walkInReplacementShippingFee, 2) }} replacement shipping fee</span>@endif</div>
                        <div class="fs-5 fw-bold">₱{{ number_format($walkInNet, 2) }}</div>
                    </div>
                </div>
                @if($transaction->note)<div class="alert alert-light border mt-3 mb-0"><strong>Remarks:</strong> {{ $transaction->note }}</div>@endif
            </div><div class="modal-footer"><button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button></div>
        </div></div>
    </div>
@endforeach

@can('request-walk-in-replacements')
@foreach($transactions as $transaction) @foreach($transaction->items as $item)
    @php
        $walkInReplacementLimit = min(
            (int) $item->remaining_replaceable_quantity,
            $hub->is_head_office
                ? (int) $item->remaining_returned_replaceable_quantity
                : (int) $item->remaining_replaceable_quantity
        );
        $walkInReplacementAvailable = $hub->is_head_office
            ? $walkInReplacementLimit > 0 && auth()->user()?->role !== 'sales_associate'
            : ($item->return_status ?? 'none') !== 'received' && $item->inventoryReturns->isEmpty() && $walkInReplacementLimit > 0;
    @endphp
    @if($walkInReplacementAvailable && in_array($transaction->status, ['confirmed', 'completed'], true))
        <div class="modal fade walk-in-replacement-modal" id="walk-in-replacement-{{ $item->id }}" data-walk-in-replacement-modal data-bs-backdrop="static" data-bs-keyboard="false" data-original-product-id="{{ $item->product_id }}" data-original-unit-price="{{ $item->unit_price }}" data-original-discount="{{ $item->discount_percentage ?? 0 }}" tabindex="-1" aria-hidden="true"><div class="modal-dialog modal-xl modal-dialog-centered"><form method="POST" enctype="multipart/form-data" action="{{ route('hub.report.walk-in.replacement.store', ['hub' => $hub->id, 'transaction' => $transaction->id, 'item' => $item->id]) }}" class="modal-content">
            @csrf<div class="modal-header bg-warning-subtle"><h5 class="modal-title">Walk-In Replacement / Exchange</h5><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close replacement form"></button></div>
            <div class="modal-body">
                <div class="alert alert-warning small">Inventory must verify this request before stock and totals change. @if($hub->is_head_office) Only received returned items can be replaced. @endif</div>
                <div class="replacement-form-section mb-3">
                    <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-2">
                        <div><div class="replacement-form-section-title mb-0">Returned item</div><div class="small text-muted">{{ $item->product?->name ?? 'Product #'.$item->product_id }}</div></div>
                        <span class="badge rounded-pill text-bg-light">Up to {{ $walkInReplacementLimit }} returned</span>
                    </div>
                    <div class="row g-2 align-items-end">
                        <div class="col-12">
                            <label class="form-label fw-semibold">Replacement product</label>
                        </div>
                        <div class="col-12">
                        <input type="text" class="form-control" data-replacement-search list="walkInReplacementOptions-{{ $item->id }}" autocomplete="off" placeholder="Search by Item ID, barcode, or product name" required>
                        <input type="hidden" name="replacement_product_id">
                        <datalist id="walkInReplacementOptions-{{ $item->id }}"></datalist>
                        <div class="form-text" data-replacement-stock>Search by Item ID, barcode, or product name. Physical stock is checked during verification.</div>
                        <div class="border rounded-3 bg-light p-3 mt-2 d-none" data-replacement-price-panel>
                            <div class="row g-2 small">
                                <div class="col-6"><span class="text-muted d-block">Item price</span><strong data-replacement-base-price>—</strong></div>
                                <div class="col-6"><span class="text-muted d-block">Discounted unit price</span><strong class="text-success" data-replacement-net-price>—</strong></div>
                                <div class="col-6"><span class="text-muted d-block">Discount amount</span><strong class="text-danger" data-replacement-discount-amount>—</strong></div>
                                <div class="col-6"><span class="text-muted d-block">Estimated total</span><strong data-replacement-total>—</strong></div>
                            </div>
                        </div>
                        </div>
                    </div>
                    <div class="row g-2 mt-1">
                        <div class="col-6 col-md-3"><label class="form-label fw-semibold">Original returned</label><input type="number" name="quantity" class="form-control" min="1" max="{{ $walkInReplacementLimit }}" value="1" required></div>
                        <div class="col-6 col-md-3"><label class="form-label fw-semibold">Replacement quantity</label><input type="number" name="replacement_quantity" class="form-control" min="1" value="1" required></div>
                        <div class="col-6 col-md-3"><label class="form-label fw-semibold text-danger">Discount (%)</label><input type="number" name="replacement_discount_percentage" class="form-control" min="0" max="100" step="0.01" value="0.00"></div>
                        <div class="col-6 col-md-3">
                            <label class="form-label fw-semibold">Replacement delivery</label>
                            <select name="replacement_shipping_fee_type" class="form-select" data-walk-in-replacement-shipping-type>
                                <option value="Free">Free delivery</option>
                                <option value="Custom Amount">Custom amount</option>
                            </select>
                        </div>
                        <div class="col-6 d-none" data-walk-in-replacement-shipping-amount-wrap>
                            <label class="form-label fw-semibold">Shipping fee (₱)</label>
                            <input type="number" name="replacement_shipping_fee_amount" class="form-control" min="0" step="0.01" value="0.00" disabled>
                        </div>
                    </div>
                </div>
                <div class="exchange-total-card mb-3" data-walk-in-exchange-summary>
                    <div class="d-flex align-items-center gap-2 mb-2 fw-semibold"><i class="fa-solid fa-calculator"></i>Exchange total</div>
                    <div class="row g-2 align-items-center">
                        <div class="col-6 col-md-4"><div class="exchange-total-label">Exchange credit</div><div class="fw-bold" data-walk-in-credit>₱0.00</div></div>
                        <div class="col-6 col-md-4"><div class="exchange-total-label">Replacement total</div><div class="fw-bold" data-walk-in-total>₱0.00</div><div class="small text-muted" data-walk-in-shipping-note>Delivery: Free</div></div>
                        <div class="col-12 col-md-4"><div class="exchange-total-label">Additional payment</div><div class="exchange-total-value" data-walk-in-amount-due>₱0.00</div></div>
                    </div>
                    <div class="small mt-2" data-walk-in-exchange-status>Select a replacement to calculate the exchange total.</div>
                </div>
                @include('hubs.reports._exchange-additional-items', ['exchangePrefix' => 'walk-in-'.$item->id, 'exchangeChannel' => 'walk_in'])
                <label class="form-label fw-semibold mt-2">Reason (optional)</label>
                <textarea name="reason" class="form-control" rows="2" maxlength="2000"></textarea>
            </div>
            <div class="modal-footer"><button class="btn btn-warning">Submit for Verification</button></div>
        </form></div></div>
    @endif
@endforeach @endforeach
@endcan

@push('scripts')
<script>
(() => {
    const endpoint = @json(route('hub.products.search.ajax', $hub->id));
    document.querySelectorAll('.walk-in-replace-button').forEach(button => button.addEventListener('click', event => {
        event.preventDefault(); const target = document.querySelector(button.dataset.replacementTarget); if (!target) return;
        if (target.parentElement !== document.body) document.body.appendChild(target);
        const current = button.closest('.modal'); const show = () => bootstrap.Modal.getOrCreateInstance(target).show();
        if (current) { current.addEventListener('hidden.bs.modal', show, { once: true }); bootstrap.Modal.getOrCreateInstance(current).hide(); } else show();
    }));
    document.querySelectorAll('[data-walk-in-replacement-modal]').forEach(modal => {
        const form = modal.querySelector('form'), search = modal.querySelector('[data-replacement-search]'), productId = modal.querySelector('[name="replacement_product_id"]'), options = modal.querySelector('datalist'), help = modal.querySelector('[data-replacement-stock]'), pricePanel = modal.querySelector('[data-replacement-price-panel]'), basePrice = modal.querySelector('[data-replacement-base-price]'), netPrice = modal.querySelector('[data-replacement-net-price]'), discountAmount = modal.querySelector('[data-replacement-discount-amount]'), estimatedTotal = modal.querySelector('[data-replacement-total]'), discountInput = modal.querySelector('[name="replacement_discount_percentage"]'), quantityInput = modal.querySelector('[name="replacement_quantity"]'), originalId = modal.dataset.originalProductId;
        const products = new Map(); let timer; let searchVersion = 0; let controller;
        const shippingType = modal.querySelector('[data-walk-in-replacement-shipping-type]');
        const shippingAmountInput = modal.querySelector('[name="replacement_shipping_fee_amount"]');
        const shippingAmountWrap = modal.querySelector('[data-walk-in-replacement-shipping-amount-wrap]');
        modal.addEventListener('show.bs.modal', () => { clearTimeout(timer); controller?.abort(); searchVersion++; search.value = ''; productId.value = ''; search.setCustomValidity(''); options.replaceChildren(); products.clear(); help.textContent = 'Search by Item ID, barcode, or product name. Physical stock is checked during verification.'; pricePanel.classList.add('d-none'); shippingType.value = 'Free'; shippingAmountInput.value = '0.00'; shippingAmountInput.disabled = true; shippingAmountWrap.classList.add('d-none'); modal.querySelector('[data-exchange-line-list]')?.replaceChildren(); updateExchangeCalculation(); });
        const money = value => `₱${value.toLocaleString('en-PH', { minimumFractionDigits: 2, maximumFractionDigits: 2 })}`;
        const roundMoney = value => Math.round((value + Number.EPSILON) * 100) / 100;
        const exchangeSummary = modal.querySelector('[data-walk-in-exchange-summary]');
        const paymentAmount = modal.querySelector('[name="exchange_payment_amount"]');
        const updateExchangeCalculation = () => {
            const selected = products.get(search.value);
            const mainPrice = Number(selected?.sales_price || 0);
            const mainDiscount = Math.min(100, Math.max(0, Number(discountInput.value || 0)));
            const mainQuantity = Math.max(1, Number(quantityInput.value || 1));
            const mainTotal = roundMoney(mainPrice * (1 - mainDiscount / 100) * mainQuantity);
            const originalUnitPrice = roundMoney(Number(modal.dataset.originalUnitPrice || 0) * (1 - Number(modal.dataset.originalDiscount || 0) / 100));
            const credit = roundMoney(originalUnitPrice * Math.max(1, Number(form.querySelector('[name="quantity"]').value || 1)));
            let extraTotal = 0;
            modal.querySelectorAll('[data-exchange-line]').forEach(line => {
                const unitPrice = Number(line.querySelector('[data-exchange-product]')?.dataset.unitPrice || 0);
                const quantity = Math.max(1, Number(line.querySelector('[data-exchange-quantity]')?.value || 1));
                const discount = Math.min(100, Math.max(0, Number(line.querySelector('[data-exchange-discount]')?.value || 0)));
                extraTotal += roundMoney(unitPrice * (1 - discount / 100) * quantity);
            });
            extraTotal = roundMoney(extraTotal);
            const shippingFee = shippingType.value === 'Custom Amount'
                ? roundMoney(Math.max(0, Number(shippingAmountInput.value || 0)))
                : 0;
            const basketTotal = roundMoney(mainTotal + extraTotal + shippingFee);
            const amountDue = roundMoney(Math.max(0, basketTotal - credit));
            exchangeSummary.querySelector('[data-walk-in-credit]').textContent = money(credit);
            exchangeSummary.querySelector('[data-walk-in-total]').textContent = money(basketTotal);
            exchangeSummary.querySelector('[data-walk-in-shipping-note]').textContent = shippingFee > 0
                ? `Includes ${money(shippingFee)} replacement delivery`
                : 'Delivery: Free';
            exchangeSummary.querySelector('[data-walk-in-amount-due]').textContent = money(amountDue);
            modal.querySelector('[data-walk-in-additional-payment]')?.classList.toggle('d-none', amountDue <= 0);
            const status = exchangeSummary.querySelector('[data-walk-in-exchange-status]');
            status.textContent = amountDue > 0
                ? `Only the excess over the exchange credit is collected. Enter ${money(amountDue)} as additional payment.`
                : 'No additional payment is due. The original sale total remains unchanged.';
            status.classList.toggle('text-danger', amountDue > 0);
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
        const updateReplacementPrice = () => { const selected = products.get(search.value); if (!selected) { pricePanel.classList.add('d-none'); updateExchangeCalculation(); return; } const price = Number(selected.sales_price || 0); const discount = Math.min(100, Math.max(0, Number(discountInput.value || 0))); const quantity = Math.max(1, Number(quantityInput.value || 1)); const discountValue = price * discount / 100; const discountedPrice = price - discountValue; basePrice.textContent = money(price); netPrice.textContent = money(discountedPrice); discountAmount.textContent = `${money(discountValue)} (${discount.toFixed(2)}%)`; estimatedTotal.textContent = money(discountedPrice * quantity); pricePanel.classList.remove('d-none'); updateExchangeCalculation(); };
        discountInput.addEventListener('input', updateReplacementPrice);
        quantityInput.addEventListener('input', updateReplacementPrice);
        form.querySelector('[name="quantity"]').addEventListener('input', updateExchangeCalculation);
        modal.addEventListener('exchange:changed', updateExchangeCalculation);
        updateExchangeCalculation();
        search.addEventListener('input', () => {
            clearTimeout(timer); controller?.abort(); const version = ++searchVersion; productId.value = ''; search.setCustomValidity(''); const selected = products.get(search.value);
            if (selected) {
                if (String(selected.id) === String(originalId)) {
                    productId.value = '';
                    help.textContent = 'The original product cannot be selected as its own replacement.';
                    pricePanel.classList.add('d-none');
                    updateExchangeCalculation();
                    return;
                }
                productId.value = selected.id;
                help.textContent = `${selected.channel_available_stock} unit(s) of physical stock available · Retail price ${money(Number(selected.sales_price || 0))}`;
                updateReplacementPrice();
                return;
            }
            const query = search.value.trim(); options.replaceChildren(); products.clear(); help.textContent = query ? 'Searching products...' : 'Search by Item ID, barcode, or product name. Physical stock is checked during verification.'; pricePanel.classList.add('d-none'); updateExchangeCalculation(); if (!query) return;
            timer = setTimeout(async () => {
                controller = new AbortController();
                let timedOut = false;
                const timeout = setTimeout(() => { timedOut = true; controller.abort(); }, 10000);
                try {
                    const url = new URL(endpoint, location.origin);
                    url.searchParams.set('q', query);
                    url.searchParams.set('active_only', '1');
                    url.searchParams.set('stock_channel', 'walk_in');
                    const response = await fetch(url, { signal: controller.signal, headers: { Accept: 'application/json' } });
                    if (!response.ok) throw new Error(`Search request failed (${response.status})`);
                    const payload = await response.json();
                    if (version !== searchVersion) return;
                    const results = Array.isArray(payload) ? payload : (Array.isArray(payload.data) ? payload.data : []);
                    options.replaceChildren();
                    products.clear();
                    results
                        .forEach(product => {
                            const availableStock = Number(product.channel_available_stock ?? product.stock ?? 0);
                            const barcode = product.barcode ? ` · Barcode: ${product.barcode}` : '';
                            const label = `${product.item_id || product.id} — ${product.name || 'Unnamed product'}${barcode} (physical stock: ${availableStock})`;
                            products.set(label, { ...product, channel_available_stock: availableStock });
                            const option = document.createElement('option');
                            option.value = label;
                            options.append(option);
                        });
                    help.textContent = products.size
                        ? 'Select a product from the suggestions. Products with insufficient physical stock will be rejected during verification.'
                        : 'No matching products with available physical stock. Try another Item ID, barcode, or product name.';
                } catch (error) {
                    if (version !== searchVersion || (error.name === 'AbortError' && !timedOut)) return;
                    help.textContent = 'Product search is unavailable. Please try again.';
                    window.AppAlert?.show('Product search is unavailable. Please try again.', 'error');
                } finally {
                    clearTimeout(timeout);
                }
            }, 250);
        });
        form.addEventListener('submit', event => { if (!productId.value) { event.preventDefault(); search.setCustomValidity('Select a replacement product from the suggestions.'); search.reportValidity(); } });
    });
    document.querySelectorAll('form[action*="replacement-payment"]').forEach(form => {
        const method = form.querySelector('[name="mode_of_payment"]');
        const checkFields = form.querySelectorAll('[data-replacement-check-field]');
        const checkInputs = form.querySelectorAll('[name="check_number"], [name="check_date"]');
        const updateCheckFields = () => {
            const isCheck = ['DATED_CHECK', 'POST_DATED_CHECK'].includes(method.value);
            checkFields.forEach(field => field.classList.toggle('d-none', !isCheck));
            checkInputs.forEach(input => {
                input.required = isCheck;
                if (!isCheck) input.value = '';
            });
        };
        const customMopField = form.querySelector('[data-replacement-custom-mop]');
        const customMopInput = form.querySelector('[name="custom_mop"]');
        const proofInput = form.querySelector('[name="walkin_payment_proofs[]"]');
        const proofLabel = form.querySelector('[data-replacement-proof-label]');
        const updateCustomMop = () => {
            const isOther = method.value === 'OTHERS';
            customMopField.classList.toggle('d-none', !isOther);
            customMopInput.required = isOther;
            if (!isOther) customMopInput.value = '';
        };
        const updateProofRequirement = () => {
            const isCash = method.value === 'CASH';
            proofInput.required = !isCash;
            proofLabel.textContent = isCash ? '(optional for cash)' : '(required)';
        };
        method.addEventListener('change', updateCheckFields);
        method.addEventListener('change', updateCustomMop);
        method.addEventListener('change', updateProofRequirement);
        updateCheckFields();
        updateCustomMop();
        updateProofRequirement();
    });
})();
</script>
@endpush
