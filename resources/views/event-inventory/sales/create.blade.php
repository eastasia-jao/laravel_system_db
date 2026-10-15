@extends('layouts.app')

@section('content')
<style>
    .event-sale-card { border: 1px solid #e2e8f0; border-radius: 1rem; }
    .event-sale-section { border: 1px solid #e2e8f0; border-radius: .85rem; background: #fff; }
    .event-sale-heading { color: #64748b; font-size: .72rem; font-weight: 800; letter-spacing: .06em; text-transform: uppercase; }
    .event-product-row { border-bottom: 1px solid #e2e8f0; padding: 1rem 0; }
    .event-product-row:last-child { border-bottom: 0; }
    .event-total-card { background: #f8fafc; border: 1px solid #dbeafe; border-radius: .85rem; }
    .event-total-value { color: #15803d; font-size: 1.45rem; font-weight: 800; }
</style>

<div class="workspace-page event-sale-page">
    <x-page-header class="mb-3" eyebrow="Event sales workspace" title="Record Event Sale" description="Record customer and payment details using only the remaining stock assigned to this event." icon="fa-cart-shopping" />

    <div class="d-flex flex-wrap gap-2 mb-3">
        <a href="{{ route('event-inventory.index', ['hub_id' => $eventPullout->store_hub_id]) }}" class="btn btn-outline-secondary"><i class="fa-solid fa-arrow-left me-1"></i>Event Inventory</a>
        <a href="{{ route('event-inventory.transactions', ['hub_id' => $eventPullout->store_hub_id, 'event_id' => $eventPullout->id]) }}" class="btn btn-outline-primary"><i class="fa-solid fa-clock-rotate-left me-1"></i>Event Transaction Log</a>
    </div>

    @if($errors->any())
        <div class="alert alert-danger"><strong>Please review the sale details.</strong><ul class="mb-0 mt-2">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>
    @endif

    <div class="card event-sale-card border-0 shadow-sm p-3 p-lg-4 mb-3">
        <div class="d-flex flex-wrap justify-content-between gap-3">
            <div><div class="event-sale-heading">Selected event</div><h4 class="mb-1">{{ $eventPullout->source }}</h4><div class="text-muted">{{ $eventPullout->reference }} &middot; {{ $eventPullout->storeHub?->name }} &middot; {{ $eventPullout->occurred_on?->format('M d, Y') }}</div></div>
            <div class="text-lg-end"><div class="event-sale-heading">Available event stock</div><div class="fs-3 fw-bold text-primary">{{ $saleLines->sum('available') }}</div><div class="small text-muted">Good and damaged items</div></div>
        </div>
    </div>

    @if($saleLines->isEmpty())
        <div class="alert alert-info">No Event stock remains available for sale.</div>
    @else
    <form method="POST" action="{{ route('event-inventory.sales.store', $eventPullout) }}" id="eventSaleForm">
        @csrf
        <section class="event-sale-section shadow-sm p-3 p-lg-4 mb-3">
            <div class="event-sale-heading mb-3"><i class="fa-solid fa-receipt me-1"></i>Order details</div>
            <div class="row g-3">
                <div class="col-md-3"><label class="form-label fw-semibold">Sales Channel</label><input class="form-control bg-light fw-semibold" value="Event" readonly></div>
                <div class="col-md-3"><label class="form-label fw-semibold" for="eventOrderDate">Date</label><input type="date" name="order_date" id="eventOrderDate" value="{{ old('order_date', now()->toDateString()) }}" class="form-control" required></div>
                <div class="col-md-3"><label class="form-label fw-semibold">Order Number</label><input class="form-control bg-light" value="Generated automatically" readonly></div>
                <div class="col-md-3"><label class="form-label fw-semibold" for="eventCustomerName">Customer Name</label><input name="customer_name" id="eventCustomerName" value="{{ old('customer_name') }}" class="form-control" placeholder="Customer name"></div>
                <div class="col-md-4"><label class="form-label fw-semibold" for="eventContactNumber">Contact Number</label><input name="contact_number" id="eventContactNumber" value="{{ old('contact_number') }}" class="form-control" placeholder="Optional"></div>
                <div class="col-md-8"><label class="form-label fw-semibold" for="eventAddress">Address</label><input name="address" id="eventAddress" value="{{ old('address') }}" class="form-control" placeholder="Optional customer address"></div>
            </div>
        </section>

        <section class="event-sale-section shadow-sm p-3 p-lg-4 mb-3">
            <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-2">
                <div class="event-sale-heading"><i class="fa-solid fa-boxes-stacked me-1"></i>Order items</div>
                <button type="button" class="btn btn-sm btn-outline-primary" id="addEventItem"><i class="fa-solid fa-plus me-1"></i>Add item</button>
            </div>
            <div class="row g-2 small fw-bold text-muted d-none d-lg-flex px-1">
                <div class="col-lg-4">Product Description</div><div class="col-lg-1">Condition</div><div class="col-lg-1">Available</div><div class="col-lg-1">Qty</div><div class="col-lg-2">Regular Price</div><div class="col-lg-1">Disc %</div><div class="col-lg-1">Total</div><div class="col-lg-1">Action</div>
            </div>
            <div id="eventItemRows"></div>
        </section>

        <section class="event-sale-section shadow-sm p-3 p-lg-4 mb-3">
            <div class="event-sale-heading mb-3"><i class="fa-solid fa-credit-card me-1"></i>Payment and order summary</div>
            <div class="row g-3 align-items-start">
                <div class="col-lg-7">
                    <div class="row g-3">
                        <div class="col-md-6"><label class="form-label fw-semibold" for="eventMop">Mode of Payment</label><select name="mode_of_payment" id="eventMop" class="form-select" required><option value="">-- Select MOP --</option>@foreach(['CASH' => 'Cash', 'GCASH' => 'GCash', 'BANK_TRANSFER' => 'Bank Transfer', 'CREDIT_DEBIT_CARD' => 'Credit/Debit Card', 'OTHERS' => 'Others'] as $value => $label)<option value="{{ $value }}" @selected(old('mode_of_payment') === $value)>{{ $label }}</option>@endforeach</select></div>
                        <div class="col-md-3"><label class="form-label fw-semibold" for="eventPaymentStatus">Payment Status</label><select name="payment_status" id="eventPaymentStatus" class="form-select"><option value="paid" @selected(old('payment_status', 'paid') === 'paid')>Paid</option><option value="partial" @selected(old('payment_status') === 'partial')>Partial</option><option value="unpaid" @selected(old('payment_status') === 'unpaid')>Unpaid</option></select></div>
                        <div class="col-md-3"><label class="form-label fw-semibold" for="eventAmountPaid">Amount Paid</label><input type="number" name="amount_paid" id="eventAmountPaid" min="0" step="0.01" value="{{ old('amount_paid', 0) }}" class="form-control"></div>
                        <div class="col-12"><label class="form-label fw-semibold" for="eventSaleNote">Remarks</label><textarea name="note" id="eventSaleNote" class="form-control" rows="3" maxlength="2000" placeholder="Optional order notes">{{ old('note') }}</textarea></div>
                    </div>
                </div>
                <div class="col-lg-5">
                    <div class="event-total-card p-3">
                        <div class="d-flex justify-content-between mb-3"><span class="text-muted">Items subtotal</span><strong id="eventSubtotal">&#8369;0.00</strong></div>
                        <div class="row g-2 align-items-center mb-3"><div class="col"><label class="form-label mb-0 text-muted" for="eventAdditionalDiscount">Additional Discount</label></div><div class="col-5"><div class="input-group"><input type="number" name="additional_discount_percentage" id="eventAdditionalDiscount" min="0" max="100" step="0.01" value="{{ old('additional_discount_percentage', 0) }}" class="form-control text-end"><span class="input-group-text">%</span></div></div></div>
                        <div class="border-top pt-3 d-flex justify-content-between align-items-end"><span class="fw-bold">Grand Total</span><span class="event-total-value" id="eventGrandTotal">&#8369;0.00</span></div>
                    </div>
                </div>
            </div>
        </section>

        <div class="d-flex justify-content-end gap-2 mb-4"><a href="{{ route('event-inventory.index', ['hub_id' => $eventPullout->store_hub_id]) }}" class="btn btn-outline-secondary">Cancel</a><button class="btn btn-primary px-4"><i class="fa-solid fa-check me-1"></i>Save Event Sale</button></div>
    </form>
    @endif
</div>

@if($saleLines->isNotEmpty())
@php
    $saleLineOptions = $saleLines->map(fn ($line) => [
        'id' => $line['id'],
        'label' => ($line['product']?->item_id ? $line['product']->item_id.' - ' : '').($line['product']?->name ?? 'Unknown product').' - '.ucfirst($line['condition']),
        'condition' => ucfirst($line['condition']),
        'available' => $line['available'],
        'unit_price' => $line['unit_price'],
    ])->values();
@endphp
<script>
document.addEventListener('DOMContentLoaded', () => {
    const lines = @json($saleLineOptions);
    const oldItems = @json(old('items', []));
    const rows = document.getElementById('eventItemRows');
    const addButton = document.getElementById('addEventItem');
    const discount = document.getElementById('eventAdditionalDiscount');
    const paymentStatus = document.getElementById('eventPaymentStatus');
    const amountPaid = document.getElementById('eventAmountPaid');
    let nextIndex = 0;

    const money = value => new Intl.NumberFormat('en-PH', { style: 'currency', currency: 'PHP' }).format(Number(value) || 0);
    const selectedLineIds = () => [...rows.querySelectorAll('.event-line-select')].map(select => select.value).filter(Boolean);

    function recalculate() {
        let subtotal = 0;
        rows.querySelectorAll('.event-product-row').forEach(row => {
            const quantity = Number(row.querySelector('.event-qty').value) || 0;
            const price = Number(row.querySelector('.event-price').value) || 0;
            const lineDiscount = Math.min(100, Math.max(0, Number(row.querySelector('.event-discount').value) || 0));
            const total = quantity * price * (1 - lineDiscount / 100);
            row.querySelector('.event-line-total').value = money(total);
            subtotal += total;
        });
        const additional = Math.min(100, Math.max(0, Number(discount.value) || 0));
        const grandTotal = subtotal * (1 - additional / 100);
        document.getElementById('eventSubtotal').textContent = money(subtotal);
        document.getElementById('eventGrandTotal').textContent = money(grandTotal);
        if (paymentStatus.value === 'paid') amountPaid.value = grandTotal.toFixed(2);
        if (paymentStatus.value === 'unpaid') amountPaid.value = '0.00';
    }

    function refreshOptions() {
        const selected = selectedLineIds();
        rows.querySelectorAll('.event-line-select').forEach(select => {
            [...select.options].forEach(option => {
                option.disabled = option.value !== select.value && selected.includes(option.value);
            });
        });
    }

    function updateRow(row, preservePrice = false) {
        const select = row.querySelector('.event-line-select');
        const line = lines.find(item => String(item.id) === select.value);
        const quantity = row.querySelector('.event-qty');
        row.querySelector('.event-condition').value = line?.condition ?? '—';
        row.querySelector('.event-available').value = line?.available ?? '—';
        quantity.max = line?.available ?? 0;
        if (!preservePrice) row.querySelector('.event-price').value = Number(line?.unit_price ?? 0).toFixed(2);
        refreshOptions();
        recalculate();
    }

    function addRow(values = {}) {
        const index = nextIndex++;
        const row = document.createElement('div');
        row.className = 'row g-2 align-items-end event-product-row';
        row.innerHTML = `
            <div class="col-lg-4"><label class="form-label fw-semibold d-lg-none">Product Description</label><select name="items[${index}][event_pullout_item_id]" class="form-select event-line-select" required><option value="">-- Select event item --</option></select></div>
            <div class="col-6 col-lg-1"><label class="form-label fw-semibold d-lg-none">Condition</label><input class="form-control bg-light event-condition" value="—" readonly></div>
            <div class="col-6 col-lg-1"><label class="form-label fw-semibold d-lg-none">Available</label><input class="form-control bg-light event-available" value="—" readonly></div>
            <div class="col-6 col-lg-1"><label class="form-label fw-semibold d-lg-none">Qty</label><input type="number" name="items[${index}][quantity]" class="form-control event-qty" min="1" value="${values.quantity ?? 1}" required></div>
            <div class="col-6 col-lg-2"><label class="form-label fw-semibold d-lg-none">Regular Price</label><input type="number" name="items[${index}][unit_price]" class="form-control event-price" min="0" step="0.01" value="${values.unit_price ?? 0}" required></div>
            <div class="col-6 col-lg-1"><label class="form-label fw-semibold d-lg-none">Disc %</label><input type="number" name="items[${index}][discount_percentage]" class="form-control event-discount" min="0" max="100" step="0.01" value="${values.discount_percentage ?? 0}"></div>
            <div class="col-6 col-lg-1"><label class="form-label fw-semibold d-lg-none">Total</label><input class="form-control bg-light event-line-total" value="${money(0)}" readonly></div>
            <div class="col-lg-1"><label class="form-label fw-semibold d-lg-none">Action</label><button type="button" class="btn btn-outline-danger w-100 event-remove-row" title="Remove item"><i class="fa-solid fa-trash"></i></button></div>`;
        const select = row.querySelector('.event-line-select');
        lines.forEach(line => select.add(new Option(`${line.label} (${line.available} available)`, line.id)));
        rows.appendChild(row);
        select.value = String(values.event_pullout_item_id ?? '');
        select.addEventListener('change', () => updateRow(row));
        row.querySelectorAll('.event-qty, .event-price, .event-discount').forEach(input => input.addEventListener('input', recalculate));
        row.querySelector('.event-remove-row').addEventListener('click', () => {
            if (rows.children.length === 1) return;
            row.remove(); refreshOptions(); recalculate();
        });
        updateRow(row, values.unit_price !== undefined);
    }

    addButton.addEventListener('click', () => addRow());
    discount.addEventListener('input', recalculate);
    paymentStatus.addEventListener('change', () => {
        amountPaid.readOnly = paymentStatus.value !== 'partial';
        recalculate();
    });
    (oldItems.length ? oldItems : [{}]).forEach(addRow);
    amountPaid.readOnly = paymentStatus.value !== 'partial';
    recalculate();
});
</script>
@endif
@endsection
