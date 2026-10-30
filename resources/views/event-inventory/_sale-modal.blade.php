@php
    $eventSaleOptions = $saleEvents->map(fn ($event) => [
        'id' => $event->id,
        'label' => $event->source.' — '.$event->reference,
        'hub' => $event->storeHub?->name,
        'order_number' => $event->sale_order_number,
        'lines' => $event->sale_lines->map(fn ($line) => [
            'id' => $line['id'],
            'name' => $line['product']?->name ?? 'Unknown product',
            'description' => $line['product']?->description ?? '',
            'item_id' => $line['product']?->item_id ?? 'N/A',
            'barcode' => $line['barcode'] ?? '',
            'condition' => strtolower($line['condition']),
            'available' => $line['available'],
            'unit_price' => $line['unit_price'],
        ])->values(),
    ])->values();
@endphp
<style>
    .event-sale-modal .modal-dialog { height: calc(100vh - 2rem); margin-top: 1rem; margin-bottom: 1rem; }
    .event-sale-modal .modal-content { height: 100%; max-height: 100%; overflow: hidden; }
    .event-sale-modal form { display: flex; flex: 1 1 auto; flex-direction: column; min-height: 0; }
    .event-sale-modal .modal-header, .event-sale-modal .modal-footer { flex: 0 0 auto; }
    .event-sale-modal .modal-header { background: linear-gradient(135deg, #eff6ff, #fff); border-bottom-color: #dbeafe; }
    .event-sale-modal .modal-body { flex: 1 1 auto; min-height: 0; overflow-y: auto; overscroll-behavior: contain; background: #f8fafc; }
    .event-sale-modal .modal-footer { position: relative; z-index: 3; background: #fff; box-shadow: 0 -8px 20px rgba(15, 23, 42, .06); }
    .event-sale-section { border: 1px solid #e2e8f0; border-radius: .9rem; background: #fff; box-shadow: 0 2px 8px rgba(15, 23, 42, .025); }
    .event-sale-section-title { color: #1e3a8a; font-size: .78rem; font-weight: 800; letter-spacing: .055em; text-transform: uppercase; }
    .event-sale-section-description { color: #64748b; font-size: .82rem; }
    .event-sale-items-grid { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: .5rem; align-items: end; }
    .event-sale-items-grid > [class*="col-"] { width: auto; max-width: none; padding: 0; }
    .event-sale-items-grid > :first-child { grid-column: 1 / -1; }
    .event-sale-items-header { display: none; align-items: end; padding-bottom: .35rem; }
    .event-sale-items-header > div { display: flex; align-items: flex-end; min-height: 2rem; line-height: 1.15; }
    .event-sale-items-header > div:last-child { justify-content: center; }
    .popup-event-price-display { font-weight: 500; }
    .event-sale-item-row { border-bottom: 1px solid #e2e8f0; padding: .85rem 0; }
    .event-sale-item-row:last-child { border-bottom: 0; }
    .event-sale-summary { border: 1px solid #bfdbfe; border-radius: .8rem; background: #eff6ff; }
    .event-sale-preview { width: 92px; max-width: 100%; }
    .event-sale-preview-button { display: block; width: 92px; max-width: 100%; padding: 0; border: 0; border-radius: .65rem; background: transparent; }
    .event-sale-preview-button:focus-visible { outline: 3px solid rgba(37, 99, 235, .3); outline-offset: 2px; }
    .event-sale-preview img { display: block; width: 92px; height: 72px; max-width: 100%; border: 1px solid #dbe3ee; border-radius: .65rem; object-fit: cover; background: #f8fafc; cursor: zoom-in; }
    .event-sale-preview-name { overflow: hidden; color: #64748b; font-size: .7rem; text-overflow: ellipsis; white-space: nowrap; }
    .event-sale-attachment-card { height: 100%; border: 1px solid #e2e8f0; border-radius: .75rem; background: #f8fafc; padding: .9rem; }
    .event-sale-attachment-card .form-text { min-height: 2.5rem; }
    .event-sale-attachment-card-title { color: #1e293b; }
    .event-sale-attachment-card input[type="file"] { font-size: .82rem; }
    .event-sale-summary { box-shadow: 0 4px 14px rgba(37, 99, 235, .06); }
    .event-sale-file-preview { display: grid; width: 92px; height: 72px; place-content: center; gap: .15rem; border: 1px solid #dbe3ee; border-radius: .65rem; background: #fff; text-align: center; }
    .event-sale-remarks { resize: none; }
    .event-sale-image-viewer { position: fixed; inset: 0; z-index: 2100; display: grid; place-items: center; padding: 1rem; background: rgba(15, 23, 42, .86); }
    .event-sale-image-viewer[hidden] { display: none; }
    .event-sale-image-stage { position: relative; display: flex; align-items: center; justify-content: center; width: min(980px, 100%); height: min(82vh, 760px); }
    .event-sale-image-stage img { max-width: calc(100% - 7rem); max-height: calc(100% - 4rem); border-radius: .75rem; object-fit: contain; box-shadow: 0 20px 60px rgba(0, 0, 0, .4); }
    .event-sale-viewer-control { position: absolute; display: grid; place-items: center; width: 2.75rem; height: 2.75rem; border: 1px solid rgba(255, 255, 255, .45); border-radius: 50%; color: #fff; background: rgba(15, 23, 42, .68); }
    .event-sale-viewer-control[hidden] { display: none; }
    .event-sale-viewer-close { top: 0; right: 0; }
    .event-sale-viewer-previous { left: .25rem; }
    .event-sale-viewer-next { right: .25rem; }
    .event-sale-viewer-caption { position: absolute; right: 4rem; bottom: 0; left: 4rem; color: #fff; text-align: center; }
    @media (min-width: 1200px) {
        .event-sale-items-grid { grid-template-columns: minmax(220px, 1fr) 116px 78px 70px 100px 78px 92px 72px; }
        .event-sale-items-grid > :first-child { grid-column: auto; }
        .event-sale-items-header { display: grid; }
    }
    @media (min-width: 992px) and (max-width: 1199.98px) {
        .event-sale-items-grid .d-lg-none { display: block !important; }
    }
</style>

<div class="modal fade event-sale-modal" id="recordEventSaleModal" tabindex="-1" aria-labelledby="recordEventSaleModalLabel" aria-hidden="true" data-bs-backdrop="static">
    <div class="modal-dialog modal-xl modal-dialog-centered modal-dialog-scrollable">
        <div class="modal-content shadow-lg">
            <form method="POST" enctype="multipart/form-data" id="eventSalePopupForm" data-store-base="{{ url('/event-inventory') }}">
                @csrf
                <input type="hidden" name="sale_form" value="1">
                <input type="hidden" name="order_number" id="popupOrderNumberInput" value="{{ old('order_number') }}">
                <div class="modal-header">
                    <div class="d-flex align-items-center gap-3"><span class="transaction-page-heading-icon"><i class="fa-solid fa-cart-shopping"></i></span><div><div class="small fw-bold text-primary text-uppercase">Event sales workspace</div><h5 class="modal-title fw-bold mb-0" id="recordEventSaleModalLabel">Record Event Sale</h5><div class="small text-muted">Add the sold items, apply any promotion, and attach supporting files.</div></div></div>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body p-3 p-lg-4">
                    @if($errors->any() && old('sale_form'))
                        <div class="alert alert-danger"><strong>Please review the sale details.</strong><ul class="mb-0 mt-2">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>
                    @endif

                    <section class="event-sale-section p-3 p-lg-4 mb-3">
                        <div class="d-flex align-items-center justify-content-between gap-3 mb-3"><div><div class="event-sale-section-title">01 · Order details</div><div class="event-sale-section-description">Choose the event and enter customer information.</div></div><i class="fa-regular fa-clipboard text-primary fs-4"></i></div>
                        <div class="row g-3">
                            <div class="col-lg-5"><label class="form-label fw-semibold" for="popupEventId">Event</label><select name="event_pullout_id" id="popupEventId" class="form-select" required><option value="">-- Select open event --</option>@foreach($saleEvents as $event)<option value="{{ $event->id }}" @selected((int) old('event_pullout_id', request('sale_event_id')) === (int) $event->id)>{{ $event->source }} — {{ $event->reference }}</option>@endforeach</select><div class="form-text" id="popupEventHub">Select the event whose stock will be sold.</div></div>
                            <div class="col-lg-2 col-md-4"><label class="form-label fw-semibold" for="popupOrderDate">Date</label><input type="date" name="order_date" id="popupOrderDate" value="{{ old('order_date', now()->toDateString()) }}" class="form-control" required></div>
                            <div class="col-lg-2 col-md-4"><label class="form-label fw-semibold">Channel</label><input class="form-control bg-light fw-semibold" value="Event" readonly></div>
                            <div class="col-lg-3 col-md-4"><label class="form-label fw-semibold" for="popupOrderNumber">Order Number</label><input id="popupOrderNumber" class="form-control bg-light" value="{{ old('order_number') }}" placeholder="Select an event" readonly></div>
                            <div class="col-md-4"><label class="form-label fw-semibold" for="popupCustomerName">Customer Name</label><input name="customer_name" id="popupCustomerName" value="{{ old('customer_name') }}" class="form-control" placeholder="Customer name" autocomplete="name"></div>
                            <div class="col-md-3"><label class="form-label fw-semibold" for="popupContactNumber">Contact Number</label><input type="tel" name="contact_number" id="popupContactNumber" value="{{ old('contact_number') }}" class="form-control" placeholder="+639XXXXXXXXX" inputmode="numeric" maxlength="13" pattern="\+639[0-9]{9}" title="Enter a Philippine mobile number such as +639171234567"></div>
                            <div class="col-md-5"><label class="form-label fw-semibold" for="popupAddress">Address</label><input name="address" id="popupAddress" value="{{ old('address') }}" class="form-control" maxlength="150" data-character-count placeholder="Optional customer address"><div class="form-text text-end"><span data-character-count-current>{{ strlen((string) old('address')) }}</span>/150</div></div>
                        </div>
                    </section>

                    <section class="event-sale-section p-3 p-lg-4 mb-3">
                        <div class="d-flex flex-wrap justify-content-between align-items-end gap-3 mb-3">
                            <div><div class="event-sale-section-title mb-1">02 · Order items</div><div class="event-sale-section-description">Choose Good or Damaged, then search by item code, barcode, or description.</div></div>
                            <button type="button" class="btn btn-sm btn-outline-primary" id="addPopupEventItem"><i class="fa-solid fa-plus me-1"></i>Add item</button>
                        </div>
                        <div class="event-sale-items-grid event-sale-items-header small fw-bold text-muted"><div>Search Product Item</div><div>Condition</div><div>Available</div><div>Qty</div><div>Regular Price</div><div>Disc %</div><div>Total</div><div>Action</div></div>
                        <div id="popupEventItemRows"><div class="text-center text-muted py-4">Select an event to load its available products.</div></div>
                    </section>

                    <section class="event-sale-section p-3 p-lg-4 mb-3">
                        <div class="d-flex align-items-center justify-content-between gap-3 mb-3"><div><div class="event-sale-section-title">03 · Payment and totals</div><div class="event-sale-section-description">Choose a payment method, apply discounts, and review the total.</div></div><i class="fa-solid fa-receipt text-primary fs-4"></i></div>
                        <div class="row g-3">
                            <div class="col-lg-6"><div class="row g-3">
                                <div class="col-12" id="popupEventMopWrap"><label class="form-label fw-semibold" for="popupEventMop">Mode of Payment</label><select name="mode_of_payment" id="popupEventMop" class="form-select" required><option value="">-- Select MOP --</option>@foreach(['CASH' => 'Cash', 'GCASH' => 'GCash', 'BDO' => 'BDO', 'BPI' => 'BPI', 'PAYMAYA' => 'PayMaya', 'OTHERS' => 'Others'] as $value => $label)<option value="{{ $value }}" @selected(old('mode_of_payment') === $value)>{{ $label }}</option>@endforeach</select></div>
                                <div class="col-12 col-md-6" id="popupCustomMopWrap" hidden><label class="form-label fw-semibold" for="popupCustomMop">Specify Payment Method</label><input name="custom_mop" id="popupCustomMop" value="{{ old('custom_mop') }}" class="form-control" maxlength="100" placeholder="Enter payment method"></div>
                                <div class="col-12"><label class="form-label fw-semibold" for="popupSaleNote">Remarks</label><textarea name="note" id="popupSaleNote" class="form-control event-sale-remarks" rows="3" maxlength="150">{{ old('note') }}</textarea><div class="form-text">Maximum 150 characters.</div></div>
                            </div></div>
                            <div class="col-lg-6">
                                <div class="event-sale-summary p-3 h-100">
                                    <div class="d-flex justify-content-between mb-3"><span>Items subtotal</span><strong id="popupEventSubtotal">&#8369;0.00</strong></div>
                                    <div class="row g-2 align-items-center mb-3">
                                        <div class="col"><label class="form-label mb-0" for="popupAdditionalDiscount">Additional Discount</label></div>
                                        <div class="col-5"><div class="input-group"><input type="number" name="additional_discount_percentage" id="popupAdditionalDiscount" min="0" max="100" step="0.01" value="{{ old('additional_discount_percentage', 0) }}" class="form-control text-end"><span class="input-group-text">%</span></div></div>
                                    </div>
                                    <div class="border-top pt-3 mb-3">
                                        <div class="fw-semibold mb-2">Coupon / Voucher</div>
                                        <div class="row g-2">
                                            <div class="col-md-4"><label class="form-label small" for="popupEventPromotionType">Promotion</label><select name="event_promotion_type" id="popupEventPromotionType" class="form-select"><option value="">None</option><option value="coupon" @selected(old('event_promotion_type') === 'coupon')>Coupon</option><option value="voucher" @selected(old('event_promotion_type') === 'voucher')>Voucher</option></select></div>
                                            <div class="col-md-4"><label class="form-label small" for="popupEventPromotionValueType">Value Type</label><select name="event_promotion_value_type" id="popupEventPromotionValueType" class="form-select"><option value="amount" @selected(old('event_promotion_value_type', 'amount') === 'amount')>Fixed Amount</option><option value="percentage" @selected(old('event_promotion_value_type') === 'percentage')>Percentage</option></select></div>
                                            <div class="col-md-4"><label class="form-label small" for="popupEventPromotionValue">Value</label><div class="input-group"><span class="input-group-text" id="popupEventPromotionValuePrefix">&#8369;</span><input type="number" name="event_promotion_value" id="popupEventPromotionValue" min="0" step="0.01" value="{{ old('event_promotion_value', 0) }}" class="form-control text-end"><span class="input-group-text" id="popupEventPromotionValueSuffix" hidden>%</span></div></div>
                                        </div>
                                        <div class="form-text text-danger" id="popupEventPromotionUnavailable" hidden>Coupon/Voucher cannot be combined with a product item discount.</div>
                                        <div class="d-flex justify-content-between mt-2 text-danger" id="popupEventPromotionDiscountRow" hidden><span id="popupEventPromotionDiscountLabel">Promotion deduction</span><strong id="popupEventPromotionDiscount">-&#8369;0.00</strong></div>
                                    </div>
                                    <div class="border-top pt-3 d-flex justify-content-between align-items-end"><strong>Grand Total</strong><strong class="fs-4 text-success" id="popupEventGrandTotal">&#8369;0.00</strong></div>
                                </div>
                            </div>
                        </div>
                    </section>
                    <section class="event-sale-section p-3 p-lg-4">
                        <div class="d-flex align-items-center justify-content-between gap-3 mb-3"><div><div class="event-sale-section-title">04 · Attachments</div><div class="event-sale-section-description">Add supporting documents for the order, promotion, or payment.</div></div><i class="fa-solid fa-paperclip text-primary fs-4"></i></div>
                        <div class="row g-3">
                            <div class="col-md-6 col-xl-3"><div class="event-sale-attachment-card"><div class="event-sale-attachment-card-title fw-semibold mb-2"><i class="fa-regular fa-file-lines text-primary me-1"></i>Order Slip</div><input type="file" name="order_slip_image" id="popupOrderSlipImage" class="form-control" accept="image/jpeg,image/png,image/webp"><div class="form-text">One JPG, PNG, or WebP image; maximum 2 MB.</div><div id="popupOrderSlipPreview" class="d-flex flex-wrap gap-2 mt-2" aria-live="polite"></div></div></div>
                            <div class="col-md-6 col-xl-3"><div class="event-sale-attachment-card"><div class="event-sale-attachment-card-title fw-semibold mb-2"><i class="fa-regular fa-images text-primary me-1"></i>Item Images</div><input type="file" name="item_images[]" id="popupItemImages" class="form-control" accept="image/jpeg,image/png,image/webp" multiple><div class="form-text">Up to 5 images, maximum 2 MB each.</div><div id="popupItemImagePreviews" class="d-flex flex-wrap gap-2 mt-2" aria-live="polite"></div></div></div>
                            <div class="col-md-6 col-xl-3"><div class="event-sale-attachment-card"><div class="event-sale-attachment-card-title fw-semibold mb-2"><i class="fa-solid fa-ticket text-primary me-1"></i>Coupon / Voucher</div><input type="file" name="event_promotion_attachment" id="popupEventPromotionAttachment" class="form-control" accept="image/jpeg,image/png,image/webp,application/pdf"><div class="form-text">One image or PDF, maximum 5 MB.</div><div id="popupEventPromotionAttachmentPreview" class="d-flex flex-wrap gap-2 mt-2" aria-live="polite"></div></div></div>
                            <div class="col-md-6 col-xl-3"><div class="event-sale-attachment-card"><div class="event-sale-attachment-card-title fw-semibold mb-2"><i class="fa-regular fa-credit-card text-primary me-1"></i>Proof of Payment</div><input type="file" name="proof_of_payment_image" id="popupProofOfPaymentImage" class="form-control" accept="image/jpeg,image/png,image/webp"><div class="form-text">One JPG, PNG, or WebP image; maximum 2 MB.</div><div id="popupProofOfPaymentPreview" class="d-flex flex-wrap gap-2 mt-2" aria-live="polite"></div></div></div>
                        </div>
                    </section>
                </div>
                <div class="modal-footer"><button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button><button class="btn btn-primary px-4" id="savePopupEventSale" disabled><i class="fa-solid fa-check me-1"></i>Save Event Sale</button></div>
            </form>
        </div>
    </div>
</div>

<div class="event-sale-image-viewer" id="eventSaleImageViewer" role="dialog" aria-modal="true" aria-labelledby="eventSaleImageViewerCaption" hidden>
    <div class="event-sale-image-stage">
        <button type="button" class="event-sale-viewer-control event-sale-viewer-close" id="eventSaleViewerClose" aria-label="Close image preview"><i class="fa-solid fa-xmark"></i></button>
        <button type="button" class="event-sale-viewer-control event-sale-viewer-previous" id="eventSaleViewerPrevious" aria-label="Previous image"><i class="fa-solid fa-chevron-left"></i></button>
        <img id="eventSaleViewerImage" src="" alt="Selected attachment preview">
        <button type="button" class="event-sale-viewer-control event-sale-viewer-next" id="eventSaleViewerNext" aria-label="Next image"><i class="fa-solid fa-chevron-right"></i></button>
        <div class="event-sale-viewer-caption"><div id="eventSaleImageViewerCaption" class="fw-semibold"></div><div id="eventSaleViewerCounter" class="small text-white-50"></div></div>
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
    const rows = document.getElementById('popupEventItemRows');
    const addButton = document.getElementById('addPopupEventItem');
    const saveButton = document.getElementById('savePopupEventSale');
    const additionalDiscount = document.getElementById('popupAdditionalDiscount');
    const promotionType = document.getElementById('popupEventPromotionType');
    const promotionValueType = document.getElementById('popupEventPromotionValueType');
    const promotionValue = document.getElementById('popupEventPromotionValue');
    const promotionValuePrefix = document.getElementById('popupEventPromotionValuePrefix');
    const promotionValueSuffix = document.getElementById('popupEventPromotionValueSuffix');
    const promotionUnavailable = document.getElementById('popupEventPromotionUnavailable');
    const promotionDiscountRow = document.getElementById('popupEventPromotionDiscountRow');
    const promotionDiscountLabel = document.getElementById('popupEventPromotionDiscountLabel');
    const promotionDiscount = document.getElementById('popupEventPromotionDiscount');
    const orderNumber = document.getElementById('popupOrderNumber');
    const orderNumberInput = document.getElementById('popupOrderNumberInput');
    const customerName = document.getElementById('popupCustomerName');
    const contactNumber = document.getElementById('popupContactNumber');
    const paymentMethod = document.getElementById('popupEventMop');
    const paymentMethodWrap = document.getElementById('popupEventMopWrap');
    const customPaymentWrap = document.getElementById('popupCustomMopWrap');
    const customPayment = document.getElementById('popupCustomMop');
    const attachmentGroups = [
        { key: 'order_slip', label: 'Order Slip Image', input: document.getElementById('popupOrderSlipImage'), previews: document.getElementById('popupOrderSlipPreview'), maximum: 1 },
        { key: 'item_images', label: 'Item Image', input: document.getElementById('popupItemImages'), previews: document.getElementById('popupItemImagePreviews'), maximum: 5 },
        { key: 'promotion_proof', label: 'Coupon or Voucher Attachment', input: document.getElementById('popupEventPromotionAttachment'), previews: document.getElementById('popupEventPromotionAttachmentPreview'), maximum: 1, mimeTypes: ['image/jpeg', 'image/png', 'image/webp', 'application/pdf'], maxSize: 5 * 1024 * 1024 },
        { key: 'proof_of_payment', label: 'Proof of Payment', input: document.getElementById('popupProofOfPaymentImage'), previews: document.getElementById('popupProofOfPaymentPreview'), maximum: 1 },
    ];
    const productDataList = document.createElement('datalist');
    productDataList.id = 'popupEventProductOptions';
    document.body.appendChild(productDataList);
    const imageViewer = document.getElementById('eventSaleImageViewer');
    const viewerImage = document.getElementById('eventSaleViewerImage');
    const viewerCaption = document.getElementById('eventSaleImageViewerCaption');
    const viewerCounter = document.getElementById('eventSaleViewerCounter');
    const viewerPrevious = document.getElementById('eventSaleViewerPrevious');
    const viewerNext = document.getElementById('eventSaleViewerNext');
    let nextIndex = 0;
    let eventLines = [];
    let viewerGroup = null;
    let viewerIndex = 0;
    const attachmentPreviewState = {};
    const money = value => new Intl.NumberFormat('en-PH', { style: 'currency', currency: 'PHP' }).format(Number(value) || 0);
    const escapeHtml = value => String(value ?? '').replace(/[&<>'"]/g, char => ({'&':'&amp;','<':'&lt;','>':'&gt;',"'":'&#039;','"':'&quot;'}[char]));
    const matchingLines = condition => eventLines.filter(line => line.condition === condition);

    function recalculate() {
        let subtotal = 0;
        let hasDiscountedItem = false;
        rows.querySelectorAll('.event-sale-item-row').forEach(row => {
            const qty = Number(row.querySelector('.popup-event-qty').value) || 0;
            const price = Number(row.querySelector('.popup-event-price').value) || 0;
            const discount = Math.min(100, Math.max(0, Number(row.querySelector('.popup-event-discount').value) || 0));
            hasDiscountedItem ||= discount > 0;
            const total = qty * price * (1 - discount / 100);
            row.querySelector('.popup-event-line-total').value = money(total);
            subtotal += total;
        });
        promotionType.disabled = hasDiscountedItem;
        promotionUnavailable.hidden = !hasDiscountedItem;
        if (hasDiscountedItem) promotionType.value = '';
        syncPromotionFields();
        const orderDiscount = Math.min(100, Math.max(0, Number(additionalDiscount.value) || 0));
        const discountedSubtotal = subtotal * (1 - orderDiscount / 100);
        const promotionInputValue = Math.max(0, Number(promotionValue.value) || 0);
        const promotionDeduction = promotionType.value
            ? Math.min(discountedSubtotal, promotionValueType.value === 'percentage'
                ? discountedSubtotal * (Math.min(100, promotionInputValue) / 100)
                : promotionInputValue)
            : 0;
        document.getElementById('popupEventSubtotal').textContent = money(subtotal);
        promotionDiscountRow.hidden = !promotionType.value;
        promotionDiscountLabel.textContent = promotionType.value
            ? `${promotionType.options[promotionType.selectedIndex].text} deduction`
            : 'Promotion deduction';
        promotionDiscount.textContent = `-${money(promotionDeduction)}`;
        document.getElementById('popupEventGrandTotal').textContent = money(Math.max(0, discountedSubtotal - promotionDeduction));
    }

    function syncPromotionFields() {
        const hasPromotion = !promotionType.disabled && Boolean(promotionType.value);
        promotionValueType.disabled = !hasPromotion;
        promotionValue.disabled = !hasPromotion;
        promotionValuePrefix.hidden = !hasPromotion || promotionValueType.value === 'percentage';
        promotionValueSuffix.hidden = !hasPromotion || promotionValueType.value !== 'percentage';
        if (promotionValueType.value === 'percentage') {
            promotionValue.max = '100';
        } else {
            promotionValue.removeAttribute('max');
        }
    }

    function selectedLineIds(excludedRow = null) {
        return new Set([...rows.querySelectorAll('.event-sale-item-row')]
            .filter(row => row !== excludedRow)
            .map(row => row.querySelector('.popup-event-line').value)
            .filter(Boolean));
    }

    function productLineLabel(line) {
        return `${line.item_id} — ${line.name}${line.barcode ? ` · ${line.barcode}` : ''} (stock: ${line.available})`;
    }

    function renderResults(row, term = '') {
        const selected = selectedLineIds(row);
        const keywords = term.toLowerCase().split(/\s+/).filter(Boolean);
        const condition = row.querySelector('.popup-event-condition-select').value;
        const matches = matchingLines(condition).filter(line => {
            const haystack = `${line.name} ${line.description} ${line.item_id} ${line.barcode}`.toLowerCase();

            return !selected.has(String(line.id)) && keywords.every(keyword => haystack.includes(keyword));
        }).slice(0, 100);
        productDataList.replaceChildren(...matches.map(line => {
            const option = document.createElement('option');
            option.value = productLineLabel(line);

            return option;
        }));
    }

    function updateRow(row, line) {
        const search = row.querySelector('.popup-event-product-search');
        const hidden = row.querySelector('.popup-event-line');
        hidden.value = line ? String(line.id) : '';
        search.value = line ? productLineLabel(line) : '';
        search.setCustomValidity('');
        row.querySelector('.popup-event-available').value = line?.available ?? '—';
        row.querySelector('.popup-event-qty').max = line?.available ?? 0;
        const priceInput = row.querySelector('.popup-event-price');
        priceInput.value = Number(line?.unit_price ?? 0).toFixed(2);
        row.querySelector('.popup-event-price-display').value = money(priceInput.value);
        recalculate();
    }

    function addRow(values = {}) {
        const index = nextIndex++;
        const row = document.createElement('div');
        row.className = 'event-sale-items-grid event-sale-item-row';
        row.innerHTML = `<div class="col-lg-4"><label class="form-label fw-semibold d-lg-none">Search Product Item</label><input type="search" class="form-control popup-event-product-search" list="popupEventProductOptions" placeholder="Search item code, barcode, or description" autocomplete="off" required><input type="hidden" name="items[${index}][event_pullout_item_id]" class="popup-event-line"></div><div class="col-6 col-lg-1"><label class="form-label fw-semibold d-lg-none">Condition</label><select name="items[${index}][stock_condition]" class="form-select popup-event-condition-select" required><option value="good">Good</option><option value="damaged">Damaged</option></select></div><div class="col-6 col-lg-1"><label class="form-label fw-semibold d-lg-none">Available</label><input class="form-control bg-light popup-event-available" value="—" readonly></div><div class="col-6 col-lg-1"><label class="form-label fw-semibold d-lg-none">Qty</label><input type="number" name="items[${index}][quantity]" class="form-control popup-event-qty" min="1" value="${values.quantity ?? 1}" required></div><div class="col-6 col-lg-2"><label class="form-label fw-semibold d-lg-none">Regular Price</label><input type="hidden" name="items[${index}][unit_price]" class="popup-event-price" value="${values.unit_price ?? 0}"><input class="form-control bg-light popup-event-price-display" value="${money(values.unit_price ?? 0)}" readonly></div><div class="col-6 col-lg-1"><label class="form-label fw-semibold d-lg-none">Disc %</label><input type="number" name="items[${index}][discount_percentage]" class="form-control popup-event-discount" min="0" max="100" step="0.01" value="${values.discount_percentage ?? 0}"></div><div class="col-6 col-lg-1"><label class="form-label fw-semibold d-lg-none">Total</label><input class="form-control bg-light popup-event-line-total" value="${money(0)}" readonly></div><div class="col-6 col-lg-1"><button type="button" class="btn btn-outline-danger w-100 popup-remove-event-row" aria-label="Remove item"><i class="fa-solid fa-trash"></i></button></div>`;
        rows.appendChild(row);
        const search = row.querySelector('.popup-event-product-search');
        const condition = row.querySelector('.popup-event-condition-select');
        condition.value = values.stock_condition ?? (eventLines.some(line => line.condition === 'good') ? 'good' : 'damaged');
        const selected = eventLines.find(line => String(line.id) === String(values.event_pullout_item_id ?? '') && line.condition === condition.value);
        updateRow(row, selected);
        search.addEventListener('focus', () => renderResults(row, search.value && !row.querySelector('.popup-event-line').value ? search.value.trim() : ''));
        search.addEventListener('input', () => {
            row.querySelector('.popup-event-line').value = '';
            search.setCustomValidity('');
            renderResults(row, search.value.trim());
        });
        search.addEventListener('change', () => {
            const line = matchingLines(condition.value)
                .find(item => productLineLabel(item).toLowerCase() === search.value.trim().toLowerCase());
            if (line && !selectedLineIds(row).has(String(line.id))) {
                updateRow(row, line);
            } else {
                row.querySelector('.popup-event-line').value = '';
                search.setCustomValidity('Select a product from the search suggestions.');
            }
        });
        condition.addEventListener('change', () => {
            updateRow(row, null);
            renderResults(row);
            search.focus();
        });
        row.querySelectorAll('.popup-event-qty, .popup-event-discount').forEach(input => input.addEventListener('input', recalculate));
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
        if (!eventLines.length) {
            rows.innerHTML = '<div class="text-center text-muted py-4">No Good or Damaged products remain for this event.</div>';
            return;
        }
        values.forEach(addRow);
    }

    function loadEvent(useOldValues = false) {
        const event = events.find(item => String(item.id) === eventSelect.value);
        eventLines = event?.lines ?? [];
        form.action = event ? `${form.dataset.storeBase}/${event.id}/sales` : '';
        const visibleAvailable = eventLines.reduce((sum, line) => sum + Number(line.available), 0);
        document.getElementById('popupEventHub').textContent = event ? `${event.hub} · ${visibleAvailable} Good/Damaged item(s) available` : 'Select the event whose stock will be sold.';
        const displayedOrderNumber = event ? (useOldValues && oldOrderNumber ? oldOrderNumber : event.order_number) : '';
        orderNumber.value = displayedOrderNumber;
        orderNumberInput.value = displayedOrderNumber;
        saveButton.disabled = !event || eventLines.length === 0;
        addButton.disabled = !event || eventLines.length === 0;
        if (!event) {
            rows.innerHTML = '<div class="text-center text-muted py-4">Select an event to load its available products.</div>';
            return;
        }
        resetRows(useOldValues && oldItems.length ? oldItems : [{}]);
        recalculate();
    }

    function updateImageViewer() {
        const images = attachmentPreviewState[viewerGroup] ?? [];
        if (!images.length) return closeImageViewer();
        viewerIndex = (viewerIndex + images.length) % images.length;
        const selected = images[viewerIndex];
        viewerImage.src = selected.url;
        viewerImage.alt = `Preview of ${selected.name}`;
        viewerCaption.textContent = selected.name;
        viewerCounter.textContent = images.length > 1 ? `${viewerIndex + 1} of ${images.length}` : '';
        viewerPrevious.hidden = images.length < 2;
        viewerNext.hidden = images.length < 2;
    }

    function openImageViewer(group, index) {
        viewerGroup = group;
        viewerIndex = index;
        imageViewer.hidden = false;
        updateImageViewer();
        document.getElementById('eventSaleViewerClose').focus();
    }

    function closeImageViewer() {
        imageViewer.hidden = true;
        viewerImage.src = '';
    }

    function renderAttachmentPreviews(group) {
        (attachmentPreviewState[group.key] ?? []).forEach(image => {
            if (image.url) URL.revokeObjectURL(image.url);
        });
        group.previews.innerHTML = '';
        const files = [...group.input.files];
        const allowedMimeTypes = group.mimeTypes ?? ['image/jpeg', 'image/png', 'image/webp'];
        const maxSize = group.maxSize ?? 2 * 1024 * 1024;
        const invalid = files.length > group.maximum || files.some(file => file.size > maxSize || !allowedMimeTypes.includes(file.type));
        const countDescription = group.maximum === 1 ? 'one supported file' : `up to ${group.maximum} supported files`;
        group.input.setCustomValidity(invalid ? `Choose ${countDescription} in a supported format within the file size limit.` : '');
        attachmentPreviewState[group.key] = files.slice(0, group.maximum).map(file => ({
            name: file.name,
            url: file.type === 'application/pdf' ? null : URL.createObjectURL(file),
            type: file.type,
        }));
        attachmentPreviewState[group.key].forEach((image, index) => {
            const preview = document.createElement('div');
            preview.className = 'event-sale-preview';
            if (image.type === 'application/pdf') {
                preview.innerHTML = `<div class="event-sale-file-preview"><i class="fa-solid fa-file-pdf text-danger fs-2"></i><span class="small text-muted">PDF</span></div><div class="event-sale-preview-name mt-1" title="${escapeHtml(image.name)}">${escapeHtml(image.name)}</div>`;
            } else {
                preview.innerHTML = `<button type="button" class="event-sale-preview-button" aria-label="Open ${escapeHtml(group.label)} ${index + 1}"><img src="${image.url}" alt="Preview of ${escapeHtml(image.name)}"></button><div class="event-sale-preview-name mt-1" title="${escapeHtml(image.name)}">${escapeHtml(image.name)}</div>`;
                preview.querySelector('button').addEventListener('click', () => openImageViewer(group.key, index));
            }
            group.previews.appendChild(preview);
        });
    }

    function updateCustomPaymentMethod() {
        const isOther = paymentMethod.value === 'OTHERS';
        paymentMethodWrap.classList.toggle('col-md-6', isOther);
        customPaymentWrap.hidden = !isOther;
        customPayment.disabled = !isOther;
        customPayment.required = isOther;
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
    attachmentGroups.forEach(group => group.input.addEventListener('change', () => renderAttachmentPreviews(group)));
    paymentMethod.addEventListener('change', updateCustomPaymentMethod);
    document.getElementById('eventSaleViewerClose').addEventListener('click', closeImageViewer);
    viewerPrevious.addEventListener('click', () => { viewerIndex--; updateImageViewer(); });
    viewerNext.addEventListener('click', () => { viewerIndex++; updateImageViewer(); });
    imageViewer.addEventListener('click', event => { if (event.target === imageViewer) closeImageViewer(); });
    document.addEventListener('keydown', event => {
        if (imageViewer.hidden) return;
        if (event.key === 'Escape') closeImageViewer();
        if (event.key === 'ArrowLeft' && !viewerPrevious.hidden) { viewerIndex--; updateImageViewer(); }
        if (event.key === 'ArrowRight' && !viewerNext.hidden) { viewerIndex++; updateImageViewer(); }
    });
    eventSelect.addEventListener('change', () => loadEvent(false));
    addButton.addEventListener('click', () => addRow());
    additionalDiscount.addEventListener('input', recalculate);
    promotionType.addEventListener('change', () => { syncPromotionFields(); recalculate(); });
    promotionValueType.addEventListener('change', () => { syncPromotionFields(); recalculate(); });
    promotionValue.addEventListener('input', recalculate);
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
    modalElement.addEventListener('hidden.bs.modal', closeImageViewer);
    updateCustomPaymentMethod();
    syncPromotionFields();
    loadEvent(true);
    if (shouldOpen && window.bootstrap) bootstrap.Modal.getOrCreateInstance(modalElement).show();
});
</script>
