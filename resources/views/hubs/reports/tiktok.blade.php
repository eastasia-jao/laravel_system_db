@php
    $canEditDetails = auth()->user()->role === 'admin' || (in_array(auth()->user()->role, ['sales_associate', 'sales_marketing_staff']) && auth()->user()->hasSalesChannel('tiktok'));
    $returnLabels = ['none' => 'No return', 'requested' => 'Return requested', 'received' => 'Return received', 'rejected' => 'Return rejected', 'refund_only' => 'Refund only — no item return'];
    $refundLabels = ['none' => 'No refund', 'pending' => 'Refund pending', 'completed' => 'Refund completed', 'rejected' => 'Refund rejected'];
@endphp
<div class="mb-3">
    <h5 class="fw-bold mb-1">TikTok orders</h5>
    <p class="text-muted small">Choose an order to edit its payout or record a return. Totals follow the order date filter.</p>
    @if($metrics['pending_payouts'])
        <div class="alert alert-warning py-2">{{ $metrics['pending_payouts'] }} order(s) awaiting payout entry. Payout totals include entered settlements only.</div>
    @endif
</div>
<div class="card border-0 shadow-sm"><div class="table-responsive">
    <table class="table align-middle mb-0">
        <thead class="table-light"><tr><th class="ps-3">Order / Date</th><th>Customer</th><th>Items</th><th class="text-end">Order total</th><th class="text-end">Net payout</th><th>Status</th><th></th></tr></thead>
        <tbody>
        @forelse($transactions as $transaction)
            <tr>
                <td class="ps-3"><div class="fw-semibold">#{{ $transaction->order_number }}</div><small class="text-muted">{{ optional($transaction->order_date)->format('M d, Y') }}</small></td>
                <td>{{ $transaction->customer_name }}</td>
                <td>{{ $transaction->items->count() }} products<div class="small text-muted">{{ $transaction->items->sum('quantity') }} units</div></td>
                <td class="text-end">₱{{ number_format($transaction->items->sum('line_total'), 2) }}</td>
                <td class="text-end fw-semibold text-primary">{{ $transaction->netTikTokPayout() === null ? 'Not entered' : '₱'.number_format($transaction->netTikTokPayout(), 2) }}</td>
                <td><span class="badge {{ $transaction->sales_after_transaction_fee === null ? 'bg-warning text-dark' : 'bg-success' }}">{{ $transaction->sales_after_transaction_fee === null ? 'Awaiting payout' : 'Payout recorded' }}</span></td>
                <td class="text-end pe-3"><button type="button" class="btn btn-sm btn-outline-primary text-nowrap" data-bs-toggle="modal" data-bs-target="#tiktok-order-{{ $transaction->id }}">View order</button></td>
            </tr>
        @empty
            <tr><td colspan="7" class="text-center text-muted p-5">No TikTok orders found. Try a different date range.</td></tr>
        @endforelse
        </tbody>
    </table>
</div></div>
@foreach($transactions as $transaction)
<div class="modal fade" id="tiktok-order-{{ $transaction->id }}" tabindex="-1" aria-labelledby="tiktok-order-title-{{ $transaction->id }}" aria-hidden="true" @if(old('editing_order') == $transaction->id) data-reopen-order @endif>
    <div class="modal-dialog modal-xl modal-dialog-scrollable modal-fullscreen-sm-down"><div class="modal-content">
        <div class="modal-header"><h5 class="modal-title" id="tiktok-order-title-{{ $transaction->id }}">Order #{{ $transaction->order_number }}</h5><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close order"></button></div>
        <div class="modal-body">
        @if(old('editing_order') == $transaction->id && $errors->any())
            <div class="alert alert-danger" role="alert">{{ $errors->first() }}</div>
        @endif
    <article>
        <div class="card-header bg-white p-3 d-flex flex-wrap justify-content-between gap-2 align-items-center">
            <div><h5 class="fw-bold mb-1">Order #{{ $transaction->order_number }}</h5><span class="text-muted small">{{ $transaction->customer_name }} &middot; {{ optional($transaction->order_date)->format('M d, Y') }}</span></div>
            <span class="badge {{ $transaction->sales_after_transaction_fee === null ? 'bg-warning text-dark' : 'bg-success' }}">{{ $transaction->sales_after_transaction_fee === null ? 'Awaiting payout' : 'Payout recorded' }}</span>
        </div>
        <div class="card-body">
            <div class="row g-3 mb-3">
                @foreach(['Order total' => $transaction->items->sum('line_total'), 'Refunded' => $transaction->completedCustomerRefunds(), 'Net payout' => $transaction->netTikTokPayout()] as $label => $amount)
                    <div class="col-6 col-lg"><div class="rounded bg-light p-3 h-100"><div class="small text-muted mb-1">{{ $label }}</div><div class="fw-bold {{ $label === 'Net payout' ? 'text-primary' : '' }}">{{ $amount === null ? 'Not entered' : '₱'.number_format($amount, 2) }}</div></div></div>
                @endforeach
            </div>
            <div class="mb-3"><label class="form-label small" for="item-search-{{ $transaction->id }}">Find an item</label><input type="search" id="item-search-{{ $transaction->id }}" data-item-search class="form-control" placeholder="Type a product name…"></div>
            <div class="table-responsive">
                <table class="table align-middle">
                    <thead class="table-light"><tr><th>Item</th><th class="text-center">Sold / Returned</th><th class="text-end">Item total</th><th>Return / Refund</th><th>Action</th></tr></thead>
                    <tbody>
                    @foreach($transaction->items as $item)
                        <tr data-order-item data-product-name="{{ $item->product?->name ?? 'Product #'.$item->product_id }}">
                            <td><div class="fw-semibold">{{ $item->product?->name ?? 'Product #'.$item->product_id }}</div><div class="small text-muted">₱{{ number_format($item->unit_price, 2) }} each &middot; {{ number_format($item->discount_percentage, 2) }}% discount</div></td>
                            <td class="text-center">{{ $item->quantity }} / {{ $item->return_status === 'received' ? $item->returned_quantity : 0 }}</td>
                            <td class="text-end fw-semibold">₱{{ number_format($item->line_total, 2) }}</td>
                            <td><div class="small">{{ $returnLabels[$item->return_status ?? 'none'] }}{{ $item->return_condition ? ' · '.ucfirst($item->return_condition) : '' }}</div><div class="small text-muted">{{ $refundLabels[$item->refund_status ?? 'none'] }}{{ $item->customer_refund_amount > 0 ? ' · ₱'.number_format($item->customer_refund_amount, 2) : '' }}</div></td>
                            <td><button type="button" class="btn btn-sm btn-outline-primary" data-edit-return="return-row-{{ $item->id }}" aria-controls="return-row-{{ $item->id }}" aria-expanded="{{ old('editing_item') == $item->id ? 'true' : 'false' }}">Return / refund</button></td>
                        </tr>
                        <tr id="return-row-{{ $item->id }}" data-return-row @if(old('editing_item') != $item->id) hidden @endif><td colspan="5">@include('hubs.reports.tiktok-return-form')</td></tr>
                    @endforeach
                    <tr data-no-items hidden><td colspan="5" class="text-muted text-center p-3">No matching items. Try another product name.</td></tr>
                    </tbody>
                </table>
            </div>
            <details @if(old('editing_order') == $transaction->id && !old('editing_item')) open @endif>
                <summary class="text-primary fw-semibold py-2">Edit payout &amp; delivery</summary>
                @if($canEditDetails)
                <form method="POST" action="{{ route('hub.report.tiktok.update', ['hub' => $hub->id, 'transaction' => $transaction->id]) }}" class="bg-light rounded p-3 my-3">
                    @csrf @method('PATCH')
                    <input type="hidden" name="editing_order" value="{{ $transaction->id }}">
                    <h6 class="fw-bold">Payout &amp; delivery</h6>
                    <div class="row g-3">
                        @foreach(['sales_after_transaction_fee' => ['Entered TikTok payout (₱)', 'number', $transaction->sales_after_transaction_fee], 'refund_shipping_fee' => ['Return shipping fees (₱)', 'number', $transaction->refund_shipping_fee], 'drop_off_date' => ['Drop-off date', 'date', optional($transaction->drop_off_date)->format('Y-m-d')], 'courier' => ['Courier', 'text', $transaction->courier], 'note' => ['Order notes', 'text', $transaction->note]] as $field => [$label, $type, $value])
                        <div class="col-md-6 col-xl-4"><label class="form-label small" for="{{ $field }}-{{ $transaction->id }}">{{ $label }}</label><input id="{{ $field }}-{{ $transaction->id }}" name="{{ $field }}" type="{{ $type }}" @if($type === 'number') step="0.01" @endif @if($field === 'refund_shipping_fee') min="0" @endif value="{{ old('editing_order') == $transaction->id && !old('editing_item') ? old($field, $value) : $value }}" class="form-control"></div>
                        @endforeach
                        <div class="col-md-6 col-xl-4"><label class="form-label small" for="payout-basis-{{ $transaction->id }}">Were refunds and return fees already deducted?</label><select name="payout_includes_refunds" id="payout-basis-{{ $transaction->id }}" class="form-select"><option value="0" @selected(!(old('editing_order') == $transaction->id && !old('editing_item') ? old('payout_includes_refunds', $transaction->payout_includes_refunds) : $transaction->payout_includes_refunds))>No — subtract refunds and return fees</option><option value="1" @selected(old('editing_order') == $transaction->id && !old('editing_item') ? old('payout_includes_refunds', $transaction->payout_includes_refunds) : $transaction->payout_includes_refunds)>Yes — final settlement, already deducted</option></select></div>
                    </div>
                    <p class="small text-muted mt-3">Copy the payout from TikTok. Leave it blank if you are still waiting.</p>
                    <button class="btn btn-primary" type="submit">Save order details</button>
                </form>
                @endif
            </details>

        </div>
    </article>
        </div>
        <div class="modal-footer"><button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button></div>
    </div></div>
</div>
@endforeach
@push('scripts')
<script>
document.querySelectorAll('[data-edit-return]').forEach(button => {
    button.addEventListener('click', () => {
        const row = document.getElementById(button.dataset.editReturn);
        row.hidden = !row.hidden;
        button.setAttribute('aria-expanded', String(!row.hidden));
        if (!row.hidden) row.querySelector('select:not(:disabled)')?.focus();
    });
});
document.querySelectorAll('[data-item-search]').forEach(input => {
    input.addEventListener('input', () => {
        const term = input.value.trim().toLocaleLowerCase();
        input.closest('.modal-body').querySelectorAll('[data-order-item]').forEach(row => {
            row.hidden = !row.dataset.productName.toLocaleLowerCase().includes(term);
            const editor = row.nextElementSibling;
            const button = row.querySelector('[data-edit-return]');
            editor.hidden = row.hidden || button.getAttribute('aria-expanded') !== 'true';
        });
        input.closest('.modal-body').querySelector('[data-no-items]').hidden =
            !!input.closest('.modal-body').querySelector('[data-order-item]:not([hidden])');
    });
});
const reopenOrder = document.querySelector('[data-reopen-order]');
if (reopenOrder) bootstrap.Modal.getOrCreateInstance(reopenOrder).show();
document.querySelectorAll('[data-return-form]').forEach(form => {
    const status = form.querySelector('select[name="return_status"]');
    const condition = form.querySelector('select[name="return_condition"]');
    const quantity = form.querySelector('[name="returned_quantity"]');
    const refundStatus = form.querySelector('select[name="refund_status"]');
    const amount = form.querySelector('[name="customer_refund_amount"]');
    const show = (input, visible) => { input.closest('.col-md-4').hidden = !visible; };
    const update = (changed = false) => {
        const returning = ['requested', 'received', 'rejected'].includes(status.value);
        const refunding = ['pending', 'completed'].includes(refundStatus.value);
        show(condition, status.value === 'received');
        show(quantity, returning);
        show(amount, refunding);
        if (changed) {
            if (!returning && !quantity.readOnly) quantity.value = 0;
            if (!refunding) amount.value = 0;
        }
    };
    status.addEventListener('change', () => update(true));
    refundStatus.addEventListener('change', () => update(true));
    update();
});
</script>
@endpush
