@php
    $eventSaleOptions = $saleEvents->map(fn ($event) => [
        'id' => $event->id,
        'label' => $event->source.' — '.$event->reference,
        'hub' => $event->storeHub?->name,
        'lines' => $event->sale_lines->map(fn ($line) => [
            'id' => $line['id'],
            'label' => ($line['product']?->item_id ? $line['product']->item_id.' - ' : '').($line['product']?->name ?? 'Unknown product').' - '.ucfirst($line['condition']),
            'condition' => ucfirst($line['condition']),
            'available' => $line['available'],
            'unit_price' => $line['unit_price'],
        ])->values(),
    ])->values();
@endphp
<style>
    .event-sale-modal .modal-header { background: linear-gradient(135deg, #eff6ff, #fff); border-bottom-color: #dbeafe; }
    .event-sale-modal .modal-body { background: #f8fafc; }
    .event-sale-section { border: 1px solid #e2e8f0; border-radius: .85rem; background: #fff; }
    .event-sale-section-title { color: #64748b; font-size: .72rem; font-weight: 800; letter-spacing: .06em; text-transform: uppercase; }
    .event-sale-item-row { border-bottom: 1px solid #e2e8f0; padding: .85rem 0; }
    .event-sale-item-row:last-child { border-bottom: 0; }
    .event-sale-summary { border: 1px solid #bfdbfe; border-radius: .8rem; background: #eff6ff; }
</style>

<div class="modal fade event-sale-modal" id="recordEventSaleModal" tabindex="-1" aria-labelledby="recordEventSaleModalLabel" aria-hidden="true" data-bs-backdrop="static">
    <div class="modal-dialog modal-xl modal-dialog-centered modal-dialog-scrollable">
        <div class="modal-content shadow-lg">
            <form method="POST" id="eventSalePopupForm" data-store-base="{{ url('/event-inventory') }}">
                @csrf
                <input type="hidden" name="sale_form" value="1">
                <div class="modal-header">
                    <div><div class="small fw-bold text-primary text-uppercase">Event sales workspace</div><h5 class="modal-title fw-bold" id="recordEventSaleModalLabel"><i class="fa-solid fa-cart-shopping me-2"></i>Record Event Sale</h5></div>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body p-3 p-lg-4">
                    @if($errors->any() && old('sale_form'))
                        <div class="alert alert-danger"><strong>Please review the sale details.</strong><ul class="mb-0 mt-2">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>
                    @endif

                    <section class="event-sale-section p-3 mb-3">
                        <div class="event-sale-section-title mb-3">Order details</div>
                        <div class="row g-3">
                            <div class="col-lg-5"><label class="form-label fw-semibold" for="popupEventId">Event</label><select name="event_pullout_id" id="popupEventId" class="form-select" required><option value="">-- Select open event --</option>@foreach($saleEvents as $event)<option value="{{ $event->id }}" @selected((int) old('event_pullout_id', request('sale_event_id')) === (int) $event->id)>{{ $event->source }} — {{ $event->reference }}</option>@endforeach</select><div class="form-text" id="popupEventHub">Select the event whose stock will be sold.</div></div>
                            <div class="col-lg-2 col-md-4"><label class="form-label fw-semibold" for="popupOrderDate">Date</label><input type="date" name="order_date" id="popupOrderDate" value="{{ old('order_date', now()->toDateString()) }}" class="form-control" required></div>
                            <div class="col-lg-2 col-md-4"><label class="form-label fw-semibold">Channel</label><input class="form-control bg-light fw-semibold" value="Event" readonly></div>
                            <div class="col-lg-3 col-md-4"><label class="form-label fw-semibold">Order Number</label><input class="form-control bg-light" value="Generated automatically" readonly></div>
                            <div class="col-md-4"><label class="form-label fw-semibold" for="popupCustomerName">Customer Name</label><input name="customer_name" id="popupCustomerName" value="{{ old('customer_name') }}" class="form-control" placeholder="Customer name"></div>
                            <div class="col-md-3"><label class="form-label fw-semibold" for="popupContactNumber">Contact Number</label><input name="contact_number" id="popupContactNumber" value="{{ old('contact_number') }}" class="form-control" placeholder="Optional"></div>
                            <div class="col-md-5"><label class="form-label fw-semibold" for="popupAddress">Address</label><input name="address" id="popupAddress" value="{{ old('address') }}" class="form-control" placeholder="Optional customer address"></div>
                        </div>
                    </section>

                    <section class="event-sale-section p-3 mb-3">
                        <div class="d-flex justify-content-between align-items-center gap-2 mb-2"><div class="event-sale-section-title">Order items</div><button type="button" class="btn btn-sm btn-outline-primary" id="addPopupEventItem"><i class="fa-solid fa-plus me-1"></i>Add item</button></div>
                        <div class="row g-2 small fw-bold text-muted d-none d-lg-flex px-1"><div class="col-lg-4">Product Description</div><div class="col-lg-1">Condition</div><div class="col-lg-1">Available</div><div class="col-lg-1">Qty</div><div class="col-lg-2">Regular Price</div><div class="col-lg-1">Disc %</div><div class="col-lg-1">Total</div><div class="col-lg-1">Action</div></div>
                        <div id="popupEventItemRows"><div class="text-center text-muted py-4">Select an event to load its available products.</div></div>
                    </section>

                    <section class="event-sale-section p-3">
                        <div class="event-sale-section-title mb-3">Payment and order summary</div>
                        <div class="row g-3">
                            <div class="col-lg-7"><div class="row g-3">
                                <div class="col-md-6"><label class="form-label fw-semibold" for="popupEventMop">Mode of Payment</label><select name="mode_of_payment" id="popupEventMop" class="form-select" required><option value="">-- Select MOP --</option>@foreach(['CASH' => 'Cash', 'GCASH' => 'GCash', 'BANK_TRANSFER' => 'Bank Transfer', 'CREDIT_DEBIT_CARD' => 'Credit/Debit Card', 'OTHERS' => 'Others'] as $value => $label)<option value="{{ $value }}" @selected(old('mode_of_payment') === $value)>{{ $label }}</option>@endforeach</select></div>
                                <div class="col-md-3"><label class="form-label fw-semibold" for="popupPaymentStatus">Payment Status</label><select name="payment_status" id="popupPaymentStatus" class="form-select"><option value="paid" @selected(old('payment_status', 'paid') === 'paid')>Paid</option><option value="partial" @selected(old('payment_status') === 'partial')>Partial</option><option value="unpaid" @selected(old('payment_status') === 'unpaid')>Unpaid</option></select></div>
                                <div class="col-md-3"><label class="form-label fw-semibold" for="popupAmountPaid">Amount Paid</label><input type="number" name="amount_paid" id="popupAmountPaid" min="0" step="0.01" value="{{ old('amount_paid', 0) }}" class="form-control"></div>
                                <div class="col-12"><label class="form-label fw-semibold" for="popupSaleNote">Remarks</label><textarea name="note" id="popupSaleNote" class="form-control" rows="3" maxlength="2000">{{ old('note') }}</textarea></div>
                            </div></div>
                            <div class="col-lg-5"><div class="event-sale-summary p-3"><div class="d-flex justify-content-between mb-3"><span>Items subtotal</span><strong id="popupEventSubtotal">&#8369;0.00</strong></div><div class="row g-2 align-items-center mb-3"><div class="col"><label class="form-label mb-0">Additional Discount</label></div><div class="col-5"><div class="input-group"><input type="number" name="additional_discount_percentage" id="popupAdditionalDiscount" min="0" max="100" step="0.01" value="{{ old('additional_discount_percentage', 0) }}" class="form-control text-end"><span class="input-group-text">%</span></div></div></div><div class="border-top pt-3 d-flex justify-content-between align-items-end"><strong>Grand Total</strong><strong class="fs-4 text-success" id="popupEventGrandTotal">&#8369;0.00</strong></div></div></div>
                        </div>
                    </section>
                </div>
                <div class="modal-footer"><button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button><button class="btn btn-primary px-4" id="savePopupEventSale" disabled><i class="fa-solid fa-check me-1"></i>Save Event Sale</button></div>
            </form>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', () => {
    const events = @json($eventSaleOptions);
    const oldItems = @json(old('items', []));
    const shouldOpen = @json(($errors->any() && old('sale_form')) || request()->filled('sale_event_id'));
    const modalElement = document.getElementById('recordEventSaleModal');
    const form = document.getElementById('eventSalePopupForm');
    const eventSelect = document.getElementById('popupEventId');
    const rows = document.getElementById('popupEventItemRows');
    const addButton = document.getElementById('addPopupEventItem');
    const saveButton = document.getElementById('savePopupEventSale');
    const additionalDiscount = document.getElementById('popupAdditionalDiscount');
    const paymentStatus = document.getElementById('popupPaymentStatus');
    const amountPaid = document.getElementById('popupAmountPaid');
    let nextIndex = 0;
    let activeLines = [];
    const money = value => new Intl.NumberFormat('en-PH', { style: 'currency', currency: 'PHP' }).format(Number(value) || 0);

    function recalculate() {
        let subtotal = 0;
        rows.querySelectorAll('.event-sale-item-row').forEach(row => {
            const qty = Number(row.querySelector('.popup-event-qty').value) || 0;
            const price = Number(row.querySelector('.popup-event-price').value) || 0;
            const discount = Math.min(100, Math.max(0, Number(row.querySelector('.popup-event-discount').value) || 0));
            const total = qty * price * (1 - discount / 100);
            row.querySelector('.popup-event-line-total').value = money(total);
            subtotal += total;
        });
        const orderDiscount = Math.min(100, Math.max(0, Number(additionalDiscount.value) || 0));
        const grandTotal = subtotal * (1 - orderDiscount / 100);
        document.getElementById('popupEventSubtotal').textContent = money(subtotal);
        document.getElementById('popupEventGrandTotal').textContent = money(grandTotal);
        if (paymentStatus.value === 'paid') amountPaid.value = grandTotal.toFixed(2);
        if (paymentStatus.value === 'unpaid') amountPaid.value = '0.00';
    }

    function selectedLineIds() {
        return [...rows.querySelectorAll('.popup-event-line')].map(select => select.value).filter(Boolean);
    }

    function refreshOptions() {
        const selected = selectedLineIds();
        rows.querySelectorAll('.popup-event-line').forEach(select => [...select.options].forEach(option => {
            option.disabled = option.value !== select.value && selected.includes(option.value);
        }));
    }

    function updateRow(row, preservePrice = false) {
        const selected = activeLines.find(line => String(line.id) === row.querySelector('.popup-event-line').value);
        row.querySelector('.popup-event-condition').value = selected?.condition ?? '—';
        row.querySelector('.popup-event-available').value = selected?.available ?? '—';
        row.querySelector('.popup-event-qty').max = selected?.available ?? 0;
        if (!preservePrice) row.querySelector('.popup-event-price').value = Number(selected?.unit_price ?? 0).toFixed(2);
        refreshOptions(); recalculate();
    }

    function addRow(values = {}) {
        const index = nextIndex++;
        const row = document.createElement('div');
        row.className = 'row g-2 align-items-end event-sale-item-row';
        row.innerHTML = `<div class="col-lg-4"><label class="form-label fw-semibold d-lg-none">Product Description</label><select name="items[${index}][event_pullout_item_id]" class="form-select popup-event-line" required><option value="">-- Select event item --</option></select></div><div class="col-6 col-lg-1"><label class="form-label fw-semibold d-lg-none">Condition</label><input class="form-control bg-light popup-event-condition" value="—" readonly></div><div class="col-6 col-lg-1"><label class="form-label fw-semibold d-lg-none">Available</label><input class="form-control bg-light popup-event-available" value="—" readonly></div><div class="col-6 col-lg-1"><label class="form-label fw-semibold d-lg-none">Qty</label><input type="number" name="items[${index}][quantity]" class="form-control popup-event-qty" min="1" value="${values.quantity ?? 1}" required></div><div class="col-6 col-lg-2"><label class="form-label fw-semibold d-lg-none">Regular Price</label><input type="number" name="items[${index}][unit_price]" class="form-control popup-event-price" min="0" step="0.01" value="${values.unit_price ?? 0}" required></div><div class="col-6 col-lg-1"><label class="form-label fw-semibold d-lg-none">Disc %</label><input type="number" name="items[${index}][discount_percentage]" class="form-control popup-event-discount" min="0" max="100" step="0.01" value="${values.discount_percentage ?? 0}"></div><div class="col-6 col-lg-1"><label class="form-label fw-semibold d-lg-none">Total</label><input class="form-control bg-light popup-event-line-total" value="${money(0)}" readonly></div><div class="col-lg-1"><button type="button" class="btn btn-outline-danger w-100 popup-remove-event-row"><i class="fa-solid fa-trash"></i></button></div>`;
        const select = row.querySelector('.popup-event-line');
        activeLines.forEach(line => select.add(new Option(`${line.label} (${line.available} available)`, line.id)));
        rows.appendChild(row);
        select.value = String(values.event_pullout_item_id ?? '');
        select.addEventListener('change', () => updateRow(row));
        row.querySelectorAll('.popup-event-qty, .popup-event-price, .popup-event-discount').forEach(input => input.addEventListener('input', recalculate));
        row.querySelector('.popup-remove-event-row').addEventListener('click', () => { if (rows.children.length > 1) { row.remove(); refreshOptions(); recalculate(); } });
        updateRow(row, values.unit_price !== undefined);
    }

    function loadEvent(useOldItems = false) {
        const event = events.find(item => String(item.id) === eventSelect.value);
        activeLines = event?.lines ?? [];
        rows.innerHTML = '';
        nextIndex = 0;
        form.action = event ? `${form.dataset.storeBase}/${event.id}/sales` : '';
        document.getElementById('popupEventHub').textContent = event ? `${event.hub} · ${activeLines.reduce((sum, line) => sum + Number(line.available), 0)} item(s) available` : 'Select the event whose stock will be sold.';
        saveButton.disabled = !event || activeLines.length === 0;
        addButton.disabled = !event || activeLines.length === 0;
        if (!event) { rows.innerHTML = '<div class="text-center text-muted py-4">Select an event to load its available products.</div>'; return; }
        const initialItems = useOldItems && oldItems.length ? oldItems : [{}];
        initialItems.forEach(addRow);
        recalculate();
    }

    eventSelect.addEventListener('change', () => loadEvent(false));
    addButton.addEventListener('click', () => addRow());
    additionalDiscount.addEventListener('input', recalculate);
    paymentStatus.addEventListener('change', () => { amountPaid.readOnly = paymentStatus.value !== 'partial'; recalculate(); });
    amountPaid.readOnly = paymentStatus.value !== 'partial';
    loadEvent(true);
    if (shouldOpen && window.bootstrap) bootstrap.Modal.getOrCreateInstance(modalElement).show();
});
</script>
