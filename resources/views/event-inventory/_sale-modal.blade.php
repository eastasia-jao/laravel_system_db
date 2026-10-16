@php
    $eventSaleOptions = $saleEvents->map(fn ($event) => [
        'id' => $event->id,
        'label' => $event->source.' — '.$event->reference,
        'hub' => $event->storeHub?->name,
        'order_number' => $event->sale_order_number,
        'lines' => $event->sale_lines->map(fn ($line) => [
            'id' => $line['id'],
            'name' => $line['product']?->name ?? 'Unknown product',
            'item_id' => $line['product']?->item_id ?? 'N/A',
            'condition' => strtolower($line['condition']),
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
    .event-product-results { position: fixed; z-index: 2000; max-height: 240px; overflow-y: auto; border: 1px solid #dbe3ee; border-radius: .65rem; background: #fff; box-shadow: 0 12px 28px rgba(15, 23, 42, .16); }
    .event-product-result { display: block; width: 100%; padding: .65rem .75rem; border: 0; border-bottom: 1px solid #edf1f6; color: #172033; background: #fff; text-align: left; }
    .event-product-result:last-child { border-bottom: 0; }
    .event-product-result:hover, .event-product-result:focus { background: #eff6ff; }
    .event-product-result small { display: block; color: #64748b; }
    .event-sale-preview { width: 112px; max-width: 100%; }
    .event-sale-preview img { display: block; width: 112px; height: 86px; max-width: 100%; border: 1px solid #dbe3ee; border-radius: .65rem; object-fit: cover; background: #f8fafc; }
    .event-sale-preview-name { overflow: hidden; color: #64748b; font-size: .7rem; text-overflow: ellipsis; white-space: nowrap; }
</style>

<div class="modal fade event-sale-modal" id="recordEventSaleModal" tabindex="-1" aria-labelledby="recordEventSaleModalLabel" aria-hidden="true" data-bs-backdrop="static">
    <div class="modal-dialog modal-xl modal-dialog-centered modal-dialog-scrollable">
        <div class="modal-content shadow-lg">
            <form method="POST" enctype="multipart/form-data" id="eventSalePopupForm" data-store-base="{{ url('/event-inventory') }}">
                @csrf
                <input type="hidden" name="sale_form" value="1">
                <input type="hidden" name="order_number" id="popupOrderNumberInput" value="{{ old('order_number') }}">
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
                            <div class="col-lg-3 col-md-4"><label class="form-label fw-semibold" for="popupOrderNumber">Order Number</label><input id="popupOrderNumber" class="form-control bg-light" value="{{ old('order_number') }}" placeholder="Select an event" readonly></div>
                            <div class="col-md-4"><label class="form-label fw-semibold" for="popupCustomerName">Customer Name</label><input name="customer_name" id="popupCustomerName" value="{{ old('customer_name') }}" class="form-control" placeholder="Customer name" autocomplete="name"></div>
                            <div class="col-md-3"><label class="form-label fw-semibold" for="popupContactNumber">Contact Number</label><input type="tel" name="contact_number" id="popupContactNumber" value="{{ old('contact_number') }}" class="form-control" placeholder="+639XXXXXXXXX" inputmode="numeric" maxlength="13" pattern="\+639[0-9]{9}" title="Enter a Philippine mobile number such as +639171234567"></div>
                            <div class="col-md-5"><label class="form-label fw-semibold" for="popupAddress">Address</label><input name="address" id="popupAddress" value="{{ old('address') }}" class="form-control" placeholder="Optional customer address"></div>
                        </div>
                    </section>

                    <section class="event-sale-section p-3 mb-3">
                        <div class="d-flex flex-wrap justify-content-between align-items-end gap-3 mb-3">
                            <div><div class="event-sale-section-title mb-1">Order items</div><div class="small text-muted">Select a condition, then search by product name or Item ID.</div></div>
                            <div class="d-flex flex-wrap align-items-end gap-2">
                                <div><label class="form-label small fw-semibold mb-1" for="popupStockCondition">Product Condition</label><select name="stock_condition" id="popupStockCondition" class="form-select form-select-sm" required><option value="good" @selected(old('stock_condition', 'good') === 'good')>Good Products</option><option value="damaged" @selected(old('stock_condition') === 'damaged')>Damaged Products</option></select></div>
                                <button type="button" class="btn btn-sm btn-outline-primary" id="addPopupEventItem"><i class="fa-solid fa-plus me-1"></i>Add item</button>
                            </div>
                        </div>
                        <div class="row g-2 small fw-bold text-muted d-none d-lg-flex px-1"><div class="col-lg-4">Search Product Item</div><div class="col-lg-1">Available</div><div class="col-lg-1">Qty</div><div class="col-lg-2">Regular Price</div><div class="col-lg-1">Disc %</div><div class="col-lg-2">Total</div><div class="col-lg-1">Action</div></div>
                        <div id="popupEventItemRows"><div class="text-center text-muted py-4">Select an event to load its available products.</div></div>
                    </section>

                    <section class="event-sale-section p-3">
                        <div class="event-sale-section-title mb-3">Order summary and attachments</div>
                        <div class="row g-3">
                            <div class="col-lg-7"><div class="row g-3">
                                <div class="col-md-6"><label class="form-label fw-semibold" for="popupEventMop">Mode of Payment</label><select name="mode_of_payment" id="popupEventMop" class="form-select" required><option value="">-- Select MOP --</option>@foreach(['CASH' => 'Cash', 'GCASH' => 'GCash', 'BANK_TRANSFER' => 'Bank Transfer', 'CREDIT_DEBIT_CARD' => 'Credit/Debit Card', 'OTHERS' => 'Others'] as $value => $label)<option value="{{ $value }}" @selected(old('mode_of_payment') === $value)>{{ $label }}</option>@endforeach</select></div>
                                <div class="col-md-6"><label class="form-label fw-semibold" for="popupEventAttachments">Image Attachments</label><input type="file" name="event_attachments[]" id="popupEventAttachments" class="form-control" accept="image/jpeg,image/png,image/webp" multiple><div class="form-text">Up to 3 images; maximum 2 MB per image.</div></div>
                                <div class="col-12"><div id="popupEventAttachmentPreviews" class="d-flex flex-wrap gap-2" aria-live="polite"></div></div>
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
    const events = {{ Illuminate\Support\Js::from($eventSaleOptions) }};
    const oldItems = {{ Illuminate\Support\Js::from(old('items', [])) }};
    const oldOrderNumber = {{ Illuminate\Support\Js::from(old('order_number')) }};
    const shouldOpen = {{ Illuminate\Support\Js::from(($errors->any() && old('sale_form')) || request()->filled('sale_event_id')) }};
    const modalElement = document.getElementById('recordEventSaleModal');
    const form = document.getElementById('eventSalePopupForm');
    const eventSelect = document.getElementById('popupEventId');
    const conditionSelect = document.getElementById('popupStockCondition');
    const rows = document.getElementById('popupEventItemRows');
    const addButton = document.getElementById('addPopupEventItem');
    const saveButton = document.getElementById('savePopupEventSale');
    const additionalDiscount = document.getElementById('popupAdditionalDiscount');
    const orderNumber = document.getElementById('popupOrderNumber');
    const orderNumberInput = document.getElementById('popupOrderNumberInput');
    const customerName = document.getElementById('popupCustomerName');
    const contactNumber = document.getElementById('popupContactNumber');
    const attachmentInput = document.getElementById('popupEventAttachments');
    const attachmentPreviews = document.getElementById('popupEventAttachmentPreviews');
    let nextIndex = 0;
    let eventLines = [];
    let previewUrls = [];
    const money = value => new Intl.NumberFormat('en-PH', { style: 'currency', currency: 'PHP' }).format(Number(value) || 0);
    const escapeHtml = value => String(value ?? '').replace(/[&<>'"]/g, char => ({'&':'&amp;','<':'&lt;','>':'&gt;',"'":'&#039;','"':'&quot;'}[char]));
    const matchingLines = () => eventLines.filter(line => line.condition === conditionSelect.value);
    const hideResults = () => document.querySelectorAll('.event-product-results').forEach(result => result.hidden = true);

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
        document.getElementById('popupEventSubtotal').textContent = money(subtotal);
        document.getElementById('popupEventGrandTotal').textContent = money(subtotal * (1 - orderDiscount / 100));
    }

    function selectedLineIds(excludedRow = null) {
        return new Set([...rows.querySelectorAll('.event-sale-item-row')]
            .filter(row => row !== excludedRow)
            .map(row => row.querySelector('.popup-event-line').value)
            .filter(Boolean));
    }

    function renderResults(row, term = '') {
        const results = row.querySelector('.event-product-results');
        const search = row.querySelector('.popup-event-product-search');
        const selected = selectedLineIds(row);
        const normalizedTerm = term.toLowerCase();
        const matches = matchingLines().filter(line => {
            const haystack = `${line.name} ${line.item_id}`.toLowerCase();
            return !selected.has(String(line.id)) && haystack.includes(normalizedTerm);
        }).slice(0, 20);
        results.innerHTML = matches.map(line => `<button type="button" class="event-product-result" data-line-id="${line.id}"><strong>${escapeHtml(line.name)}</strong><small>Item ID: ${escapeHtml(line.item_id)} · ${escapeHtml(line.condition.charAt(0).toUpperCase() + line.condition.slice(1))} · Available: ${line.available}</small></button>`).join('');
        const bounds = search.getBoundingClientRect();
        results.style.left = `${bounds.left}px`;
        results.style.top = `${bounds.bottom + 4}px`;
        results.style.width = `${bounds.width}px`;
        results.hidden = matches.length === 0;
    }

    function updateRow(row, line, preservePrice = false) {
        const search = row.querySelector('.popup-event-product-search');
        const hidden = row.querySelector('.popup-event-line');
        hidden.value = line ? String(line.id) : '';
        search.value = line ? `${line.name} (${line.item_id})` : '';
        search.setCustomValidity('');
        row.querySelector('.popup-event-available').value = line?.available ?? '—';
        row.querySelector('.popup-event-qty').max = line?.available ?? 0;
        if (!preservePrice) row.querySelector('.popup-event-price').value = Number(line?.unit_price ?? 0).toFixed(2);
        row.querySelector('.event-product-results').hidden = true;
        recalculate();
    }

    function addRow(values = {}) {
        const index = nextIndex++;
        const row = document.createElement('div');
        row.className = 'row g-2 align-items-end event-sale-item-row';
        row.innerHTML = `<div class="col-lg-4"><label class="form-label fw-semibold d-lg-none">Search Product Item</label><div class="position-relative"><input type="search" class="form-control popup-event-product-search" placeholder="Search product name or Item ID" autocomplete="off" required><input type="hidden" name="items[${index}][event_pullout_item_id]" class="popup-event-line"><div class="event-product-results" hidden></div></div></div><div class="col-6 col-lg-1"><label class="form-label fw-semibold d-lg-none">Available</label><input class="form-control bg-light popup-event-available" value="—" readonly></div><div class="col-6 col-lg-1"><label class="form-label fw-semibold d-lg-none">Qty</label><input type="number" name="items[${index}][quantity]" class="form-control popup-event-qty" min="1" value="${values.quantity ?? 1}" required></div><div class="col-6 col-lg-2"><label class="form-label fw-semibold d-lg-none">Regular Price</label><input type="number" name="items[${index}][unit_price]" class="form-control popup-event-price" min="0" step="0.01" value="${values.unit_price ?? 0}" required></div><div class="col-6 col-lg-1"><label class="form-label fw-semibold d-lg-none">Disc %</label><input type="number" name="items[${index}][discount_percentage]" class="form-control popup-event-discount" min="0" max="100" step="0.01" value="${values.discount_percentage ?? 0}"></div><div class="col-6 col-lg-2"><label class="form-label fw-semibold d-lg-none">Total</label><input class="form-control bg-light popup-event-line-total" value="${money(0)}" readonly></div><div class="col-6 col-lg-1"><button type="button" class="btn btn-outline-danger w-100 popup-remove-event-row" aria-label="Remove item"><i class="fa-solid fa-trash"></i></button></div>`;
        rows.appendChild(row);
        const search = row.querySelector('.popup-event-product-search');
        const results = row.querySelector('.event-product-results');
        const selected = eventLines.find(line => String(line.id) === String(values.event_pullout_item_id ?? '') && line.condition === conditionSelect.value);
        updateRow(row, selected, values.unit_price !== undefined);
        search.addEventListener('focus', () => renderResults(row, search.value && !row.querySelector('.popup-event-line').value ? search.value.trim() : ''));
        search.addEventListener('input', () => {
            row.querySelector('.popup-event-line').value = '';
            search.setCustomValidity('');
            renderResults(row, search.value.trim());
        });
        results.addEventListener('click', event => {
            const button = event.target.closest('[data-line-id]');
            if (!button) return;
            const line = matchingLines().find(item => String(item.id) === button.dataset.lineId);
            if (line) updateRow(row, line);
        });
        row.querySelectorAll('.popup-event-qty, .popup-event-price, .popup-event-discount').forEach(input => input.addEventListener('input', recalculate));
        row.querySelector('.popup-remove-event-row').addEventListener('click', () => {
            if (rows.querySelectorAll('.event-sale-item-row').length > 1) {
                row.remove();
                recalculate();
            }
        });
    }

    function resetRows(values = [{}]) {
        rows.innerHTML = '';
        nextIndex = 0;
        if (!matchingLines().length) {
            rows.innerHTML = `<div class="text-center text-muted py-4">No ${escapeHtml(conditionSelect.options[conditionSelect.selectedIndex].text.toLowerCase())} remain for this event.</div>`;
            return;
        }
        values.forEach(addRow);
    }

    function loadEvent(useOldValues = false) {
        const event = events.find(item => String(item.id) === eventSelect.value);
        eventLines = event?.lines ?? [];
        form.action = event ? `${form.dataset.storeBase}/${event.id}/sales` : '';
        const visibleAvailable = matchingLines().reduce((sum, line) => sum + Number(line.available), 0);
        document.getElementById('popupEventHub').textContent = event ? `${event.hub} · ${visibleAvailable} ${conditionSelect.value} item(s) available` : 'Select the event whose stock will be sold.';
        const displayedOrderNumber = event ? (useOldValues && oldOrderNumber ? oldOrderNumber : event.order_number) : '';
        orderNumber.value = displayedOrderNumber;
        orderNumberInput.value = displayedOrderNumber;
        saveButton.disabled = !event || matchingLines().length === 0;
        addButton.disabled = !event || matchingLines().length === 0;
        if (!event) {
            rows.innerHTML = '<div class="text-center text-muted py-4">Select an event to load its available products.</div>';
            return;
        }
        resetRows(useOldValues && oldItems.length ? oldItems : [{}]);
        recalculate();
    }

    function renderAttachmentPreviews() {
        previewUrls.forEach(url => URL.revokeObjectURL(url));
        previewUrls = [];
        attachmentPreviews.innerHTML = '';
        const files = [...attachmentInput.files];
        const invalid = files.length > 3 || files.some(file => file.size > 2 * 1024 * 1024 || !['image/jpeg', 'image/png', 'image/webp'].includes(file.type));
        attachmentInput.setCustomValidity(invalid ? 'Choose up to 3 JPG, PNG, or WebP images, no larger than 2 MB each.' : '');
        files.slice(0, 3).forEach(file => {
            const url = URL.createObjectURL(file);
            previewUrls.push(url);
            const preview = document.createElement('div');
            preview.className = 'event-sale-preview';
            preview.innerHTML = `<img src="${url}" alt="Preview of ${escapeHtml(file.name)}"><div class="event-sale-preview-name mt-1" title="${escapeHtml(file.name)}">${escapeHtml(file.name)}</div>`;
            attachmentPreviews.appendChild(preview);
        });
    }

    customerName.addEventListener('input', () => {
        const position = customerName.selectionStart;
        customerName.value = customerName.value.toLowerCase().replace(/(^|\s)(\p{L})/gu, match => match.toUpperCase());
        customerName.setSelectionRange(position, position);
    });
    contactNumber.addEventListener('focus', () => { if (!contactNumber.value) contactNumber.value = '+63'; });
    contactNumber.addEventListener('blur', () => { if (contactNumber.value === '+63') contactNumber.value = ''; });
    contactNumber.addEventListener('input', () => {
        let digits = contactNumber.value.replace(/\D/g, '');
        if (digits.startsWith('63')) digits = digits.slice(2);
        if (digits.startsWith('0')) digits = digits.slice(1);
        contactNumber.value = `+63${digits.slice(0, 10)}`;
    });
    attachmentInput.addEventListener('change', renderAttachmentPreviews);
    eventSelect.addEventListener('change', () => loadEvent(false));
    conditionSelect.addEventListener('change', () => loadEvent(false));
    addButton.addEventListener('click', () => addRow());
    additionalDiscount.addEventListener('input', recalculate);
    form.addEventListener('submit', event => {
        const missingProduct = [...rows.querySelectorAll('.event-sale-item-row')].find(row => !row.querySelector('.popup-event-line').value);
        if (missingProduct) {
            event.preventDefault();
            const search = missingProduct.querySelector('.popup-event-product-search');
            search.setCustomValidity('Select a product from the search results.');
            search.reportValidity();
            search.focus();
        }
    });
    document.addEventListener('click', event => { if (!event.target.closest('.position-relative')) hideResults(); });
    document.addEventListener('scroll', hideResults, true);
    modalElement.addEventListener('hidden.bs.modal', hideResults);
    loadEvent(true);
    if (shouldOpen && window.bootstrap) bootstrap.Modal.getOrCreateInstance(modalElement).show();
});
</script>
