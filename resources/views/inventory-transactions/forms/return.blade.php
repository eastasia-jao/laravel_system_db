@extends('layouts.app')

@section('content')
@php
    $currentUser = auth()->user();
    $visibleHubs = in_array($currentUser?->role, ['admin', 'inventory_staff'], true)
        ? $hubs
        : $hubs->whereIn('id', $currentUser?->accessibleStoreHubIds() ?? [])->values();
    $hubSelectionLocked = ! in_array($currentUser?->role, ['admin', 'inventory_staff'], true) && $visibleHubs->count() <= 1;
    $staffCanSelectEventReturn = in_array($currentUser?->role, ['admin', 'inventory_staff'], true)
        && $selectedHub && ! $selectedHub->is_head_office;
    $channelSelectionLocked = $currentUser?->role === 'sales_associate'
        || ($selectedHub && ! $selectedHub->is_head_office && ! $staffCanSelectEventReturn);
    $selectedReturnChannel = old('channel', request('channel', $channelSelectionLocked ? 'walk_in' : ''));
    $returnContext = request('return_to') === 'event_inventory' ? 'event_inventory' : 'transaction_logs';
    $returnSection = request('return_section') === 'transactions' ? 'transactions' : 'overview';
    $cancelUrl = $returnContext === 'event_inventory'
        ? route('event-inventory.index', ['hub_id' => $hubId, 'section' => $returnSection])
        : route('inventory-transactions.index', ['hub_id' => $hubId]);
@endphp
<style>
    .transaction-form-card { border:1px solid #e2e8f0 !important; }
    #returnItems { max-height: min(48vh, 430px); overflow-y: auto; overflow-x: hidden; padding: .5rem; border: 1px solid #dee2e6; border-radius: .375rem; scrollbar-gutter: stable; }
    .return-item-search { max-width: 540px; }
    .return-item[hidden] { display: none; }
    .return-item { display: grid; grid-template-columns: minmax(260px, 1.75fr) minmax(175px, 1fr) minmax(145px, .9fr) minmax(175px, 1fr); gap: .6rem; align-items: start; padding: .65rem .75rem; margin: 0 0 .5rem; border: 1px solid #e2e8f0; border-radius: .65rem; background: #f8fafc; }
    .return-item.return-item-no-refund { grid-template-columns: minmax(260px, 1.75fr) minmax(175px, 1fr) minmax(145px, .9fr); }
    .return-item:last-child { margin-bottom: 0; }
    .return-item .form-label { display: block; margin-bottom: .35rem; font-size: .78rem; font-weight: 600; line-height: 1.2; color: #475569; white-space: nowrap; }
    .return-item > div:not(:first-child) .form-control { width: 100%; max-width: 150px; }
    .return-item > div:nth-child(3) .form-control { max-width: 125px; }
    .return-refund-control .form-control { max-width: 150px; }
    .return-refund-control { padding-left: .65rem; border-left: 1px solid #cbd5e1; }
    .return-refund-heading { display: flex; align-items: center; justify-content: space-between; gap: .4rem; min-height: 1.5rem; }
    .return-refund-heading .form-label { margin-bottom: 0; }
    .return-refund-control .form-check { min-height: 1.5rem; margin-bottom: 0; }
    .return-refund-control .form-check-label { font-size: .8rem; color: #475569; }
    .return-entry-layout { display: grid; grid-template-columns: minmax(0, 1fr) minmax(220px, .32fr); gap: 1rem; align-items: start; }
    .return-notes-panel { position: sticky; top: 1rem; padding: .85rem; border: 1px solid #e2e8f0; border-radius: .65rem; background: #f8fafc; }
    .return-notes-panel .form-label { font-weight: 600; color: #475569; }
    .return-actions { grid-column: 2; display: flex; justify-content: flex-start; flex-wrap: wrap; gap: .5rem; }
    @media (max-width: 991.98px) { .return-entry-layout { grid-template-columns: 1fr; } .return-notes-panel { position: static; } .return-actions { grid-column: 1; justify-content:flex-end; } .return-item { grid-template-columns: minmax(220px, 1.5fr) repeat(2, minmax(145px, 1fr)); } .return-refund-control { grid-column: auto; padding-left: .5rem; border-left: 1px solid #cbd5e1; border-top: 0; } }
    @media (max-width: 575.98px) { .return-item { grid-template-columns: 1fr; } .return-refund-control { grid-column: auto; } .return-actions { justify-content:flex-end; } .return-actions > * { flex:1 1 auto; } }
</style>
<div class="workspace-page inventory-entry-page">
    <x-page-header class="mb-3" eyebrow="Inventory adjustment" title="Return Items" description="Record returned products and classify each item as good or damaged." icon="fa-rotate-left" />

    <form method="POST" action="{{ route('inventory-transactions.return.store') }}" class="card transaction-form-card shadow-sm rounded-4 p-4">
        @csrf
        <input type="hidden" name="type" value="return">
        <input type="hidden" name="sales_transaction_id" id="salesTransactionId">
        <input type="hidden" name="national_pullout_id" id="nationalPulloutId">
        <input type="hidden" name="event_reference" id="eventReference">
        <div class="row g-3">
            <div class="col-md-4"><label class="form-label">Store Hub</label><select name="store_hub_id" class="form-select" required @disabled($hubSelectionLocked || $visibleHubs->isEmpty()) onchange="const url = new URL(window.location.href); url.searchParams.set('hub_id', this.value); window.location.href = url.toString();">
                @foreach($visibleHubs as $hub)<option value="{{ $hub->id }}" @selected((int) $hubId === (int) $hub->id)>{{ $hub->name }}</option>@endforeach
            </select></div>
            @if($hubSelectionLocked && $visibleHubs->isNotEmpty())
                <input type="hidden" name="store_hub_id" value="{{ $hubId }}">
            @endif
            @if($visibleHubs->isEmpty())
                <div class="col-12"><div class="alert alert-warning mb-0">Your account has no assigned store. Contact an administrator before recording return items.</div></div>
            @endif
            <div class="col-md-4"><label class="form-label">Return Date</label><input type="date" name="occurred_on" id="returnDate" value="{{ old('occurred_on', now()->toDateString()) }}" class="form-control" required></div>
            <div class="col-md-4"><label class="form-label">Return Source</label><select name="channel" id="returnChannel" class="form-select" required @disabled($channelSelectionLocked)>
                @if($channelSelectionLocked)
                    <option value="walk_in" selected>Walk-In</option>
                @elseif($staffCanSelectEventReturn)
                    <option value="walk_in"{{ $selectedReturnChannel === 'walk_in' ? ' selected' : '' }}>Walk-in</option><option value="event"{{ $selectedReturnChannel === 'event' ? ' selected' : '' }}>Event</option>
                @else
                    <option value="" @selected($selectedReturnChannel === '')>Select return source</option><option value="shopee" @selected($selectedReturnChannel === 'shopee')>Shopee</option><option value="lazada" @selected($selectedReturnChannel === 'lazada')>Lazada</option><option value="tiktok" @selected($selectedReturnChannel === 'tiktok')>TikTok</option><option value="online" @selected($selectedReturnChannel === 'online')>Online</option><option value="walk_in" @selected($selectedReturnChannel === 'walk_in')>Walk-in</option><option value="wholesale" @selected($selectedReturnChannel === 'wholesale')>Wholesale</option><option value="fully_booked" @selected($selectedReturnChannel === 'fully_booked')>Fully Booked</option><option value="national_bookstore" @selected($selectedReturnChannel === 'national_bookstore')>National Bookstore</option><option value="event" @selected($selectedReturnChannel === 'event')>Event</option>
                @endif
            </select>
            @if($channelSelectionLocked)
                <input type="hidden" name="channel" value="walk_in">
            @endif
            </div>
            <div class="col-md-4" id="returnCustomerField"><label class="form-label" id="returnCustomerLabel">Customer <span id="customerOptionalLabel" class="text-muted fw-normal">(required)</span></label><select id="returnCustomer" class="form-select" required disabled><option value="">Select return date and return source first</option></select></div>
            <div class="col-md-4"><label class="form-label" id="returnReferenceLabel">Sales Reference</label><select name="reference" id="returnOrder" class="form-select" required disabled><option value="">Select customer first</option></select></div>
            <input type="hidden" name="source" id="returnSource">
        </div>
        <div id="returnAttachmentPreview" class="mt-3" hidden></div>
        <div class="d-flex justify-content-between align-items-center mt-4 mb-2">
            <h5 class="fw-bold mb-0">Returned Items</h5>
        </div>
        <div id="returnItemSearchWrap" class="return-item-search mb-2" hidden>
            <label class="form-label fw-semibold" for="returnItemSearch">Search Product Items</label>
            <div class="input-group"><span class="input-group-text"><i class="fa-solid fa-magnifying-glass" aria-hidden="true"></i></span><input type="search" id="returnItemSearch" class="form-control" placeholder="Search product name or Item ID" autocomplete="off"></div>
        </div>
        <div class="return-entry-layout">
            <div id="returnItems"><div class="text-muted p-3">Select a return source and reference to load its products.</div></div>
            <div class="return-notes-panel"><label class="form-label" for="returnNotes">Notes</label><textarea id="returnNotes" name="notes" class="form-control" rows="6" maxlength="150" placeholder="Add notes about the returned items">{{ old('notes') }}</textarea><div class="form-text text-end"><span id="returnNotesCount">0</span>/150</div></div>
            <div class="return-actions"><button class="btn btn-primary"><i class="fa-solid fa-check me-1" aria-hidden="true"></i>Save Return Items</button><a href="{{ $cancelUrl }}" class="btn btn-outline-secondary">Cancel</a></div>
        </div>
    </form>

</div>
@endsection

@push('scripts')
<script>
(() => {
    const endpoint = @json(route('inventory-transactions.return.sales'));
    const hubId = @json($hubId);
    const channel = document.getElementById('returnChannel');
    const returnDate = document.getElementById('returnDate');
    const customer = document.getElementById('returnCustomer');
    const order = document.getElementById('returnOrder');
    const transactionId = document.getElementById('salesTransactionId');
    const nationalPulloutId = document.getElementById('nationalPulloutId');
    const eventReference = document.getElementById('eventReference');
    const referenceLabel = document.getElementById('returnReferenceLabel');
    const container = document.getElementById('returnItems');
    const attachmentPreview = document.getElementById('returnAttachmentPreview');
    const notes = document.getElementById('returnNotes');
    const notesCount = document.getElementById('returnNotesCount');
    const itemSearchWrap = document.getElementById('returnItemSearchWrap');
    const itemSearch = document.getElementById('returnItemSearch');
    const restoreState = {
        channel: @json(old('channel', request('channel'))),
        source: @json(old('source')),
        reference: @json(old('reference')),
        transactionId: @json(old('sales_transaction_id')),
        nationalPulloutId: @json(old('national_pullout_id')),
        eventReference: @json(old('event_reference')),
        items: @json(old('items', [])),
    };
    const presetChannel = restoreState.channel;
    let restoreSelection = Boolean(restoreState.channel);
    let restoreReference = false;
    const escapeHtml = value => String(value ?? '').replace(/[&<>'"]/g, character => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', "'": '&#039;', '"': '&quot;' })[character]);
    const resetItemSearch = () => {
        itemSearch.value = '';
        itemSearchWrap.hidden = true;
    };
    itemSearch.addEventListener('input', () => {
        const term = itemSearch.value.trim().toLowerCase();
        container.querySelectorAll('.return-item').forEach(row => {
            row.hidden = term !== '' && !row.dataset.returnSearch.includes(term);
        });
    });
    notes?.addEventListener('input', () => { notesCount.textContent = notes.value.length; });
    const setOptions = (select, items, placeholder, label) => {
        select.replaceChildren(new Option(placeholder, ''));
        items.forEach(item => select.add(new Option(label(item), item.id ?? item)));
        select.disabled = !items.length;
    };
    const updateCustomerState = () => {
        const referenceOnly = ['fully_booked', 'national_bookstore', 'event'].includes(channel.value);
        customer.required = !referenceOnly;
        document.getElementById('customerOptionalLabel').textContent = referenceOnly ? '' : '(required)';
        document.getElementById('returnCustomerField').classList.toggle('d-none', referenceOnly);
        referenceLabel.textContent = channel.value === 'national_bookstore' ? 'P.O. #' : channel.value === 'event' ? 'Event Reference' : channel.value === 'fully_booked' ? 'Order Reference' : 'Sales Reference';
        if (referenceOnly) {
            customer.value = '';
            document.getElementById('returnSource').value = '';
        }
    };
    const resetOrder = placeholder => {
        order.replaceChildren(new Option(placeholder, ''));
        order.disabled = true;
        transactionId.value = '';
        nationalPulloutId.value = '';
        eventReference.value = '';
        attachmentPreview.replaceChildren();
        attachmentPreview.hidden = true;
    };
    const showFullyBookedAttachment = attachment => {
        attachmentPreview.replaceChildren();
        attachmentPreview.hidden = !attachment;
        if (!attachment) return;

        const heading = document.createElement('h6');
        heading.className = 'fw-bold mb-2';
        heading.textContent = 'Fully Booked Attachment';
        const link = document.createElement('a');
        link.href = attachment.url;
        link.target = '_blank';
        link.rel = 'noopener';
        link.textContent = `Preview ${attachment.file_name}`;
        const preview = document.createElement(attachment.mime_type?.startsWith('image/') ? 'img' : 'iframe');
        preview.src = attachment.url;
        preview.className = 'd-block mt-2 border rounded bg-white';
        preview.style.width = '100%';
        preview.style.maxHeight = '420px';
        if (preview instanceof HTMLImageElement) {
            preview.alt = `Fully Booked attachment: ${attachment.file_name}`;
            preview.style.objectFit = 'contain';
        } else {
            preview.title = `Fully Booked attachment: ${attachment.file_name}`;
            preview.style.height = '420px';
        }
        attachmentPreview.append(heading, link, preview);
    };
    const restoreItemValues = () => {
        const unusedItems = [...restoreState.items];
        container.querySelectorAll('.return-item').forEach(row => {
            const productId = row.querySelector('[name$="[product_id]"]')?.value;
            const transactionItemId = row.querySelector('[name$="[transaction_item_id]"]')?.value;
            const replacementId = row.querySelector('[name$="[replacement_id]"]')?.value;
            const nationalItemId = row.querySelector('[name$="[national_pullout_item_id]"]')?.value;
            const savedIndex = unusedItems.findIndex(item => item.national_pullout_item_id
                ? String(item.national_pullout_item_id) === String(nationalItemId)
                : item.product_id
                    && String(item.product_id) === String(productId)
                    && (!item.transaction_item_id || String(item.transaction_item_id) === String(transactionItemId))
                    && (!item.replacement_id || String(item.replacement_id) === String(replacementId)));
            if (savedIndex < 0) return;
            const [saved] = unusedItems.splice(savedIndex, 1);
            const good = row.querySelector('[name$="[good_quantity]"]');
            const damaged = row.querySelector('[name$="[damaged_quantity]"]');
            if (good && saved.good_quantity !== undefined) good.value = saved.good_quantity;
            if (damaged && saved.damaged_quantity !== undefined) damaged.value = saved.damaged_quantity;
        });
    };
    const load = async params => {
        const url = new URL(endpoint, window.location.origin);
        Object.entries({ hub_id: hubId, channel: channel.value, date: returnDate.value, ...params }).forEach(([key, value]) => url.searchParams.set(key, value));
        const response = await fetch(url, { headers: { Accept: 'application/json' } });
        if (!response.ok) throw new Error('Unable to load sales');
        return response.json();
    };
    channel.addEventListener('change', async () => {
        const shouldRestoreSelection = restoreSelection && channel.value === restoreState.channel;
        restoreSelection = false;
        customer.disabled = true; resetOrder('Loading references...'); resetItemSearch(); container.innerHTML = '<div class="text-muted p-3">Loading return sources...</div>';
        try {
            updateCustomerState();
            const data = await load({});
            if (['fully_booked', 'national_bookstore', 'event'].includes(channel.value)) {
                const isNational = channel.value === 'national_bookstore';
                const isEvent = channel.value === 'event';
                setOptions(order, data.orders || [], isNational ? 'Select P.O. number' : isEvent ? 'Select Event reference' : 'Select order number', value => isEvent ? `${value.order_number} (${value.source || 'Event'} · ${value.order_date || 'undated'})` : value.order_number);
                container.innerHTML = data.orders?.length
                    ? `<div class="text-muted p-3">Select ${isNational ? 'a P.O. number' : isEvent ? 'an Event reference' : 'an order number'} to load its ${isEvent ? 'pulled-out' : 'ordered'} products.</div>`
                    : `<div class="alert alert-warning mb-0">No ${isNational ? 'returnable National Bookstore records' : isEvent ? 'returnable Event pullouts' : 'Fully Booked orders'} were found${isNational ? '.' : ' on the selected date.'}</div>`;
                const referenceToRestore = isEvent
                    ? (restoreState.eventReference || restoreState.reference)
                    : isNational
                        ? (restoreState.nationalPulloutId || restoreState.reference)
                        : (restoreState.transactionId || restoreState.reference);
                if (shouldRestoreSelection && referenceToRestore && [...order.options].some(option => option.value === String(referenceToRestore))) {
                    order.value = String(referenceToRestore);
                    order.dispatchEvent(new Event('change'));
                } else if (isEvent && presetChannel === 'event' && restoreState.eventReference && [...order.options].some(option => option.value === restoreState.eventReference)) {
                    order.value = restoreState.eventReference;
                    order.dispatchEvent(new Event('change'));
                }
                return;
            }
            setOptions(customer, data.customers || [], 'Select customer', value => value);
            resetOrder('Select customer first');
            container.innerHTML = data.customers?.length
                ? '<div class="text-muted p-3">Select a customer to load sales references.</div>'
                : '<div class="alert alert-warning mb-0">No customers have sales on the selected date.</div>';
            if (shouldRestoreSelection && restoreState.source && [...customer.options].some(option => option.value === restoreState.source)) {
                customer.value = restoreState.source;
                restoreReference = true;
                customer.dispatchEvent(new Event('change'));
            }
        } catch { window.AppAlert?.show('Could not load sales for this channel.', 'error'); }
    });
    customer.addEventListener('change', async () => {
        const shouldRestoreReference = restoreReference;
        restoreReference = false;
        document.getElementById('returnSource').value = customer.value;
        resetItemSearch();
        order.disabled = true; transactionId.value = ''; container.innerHTML = '<div class="text-muted p-3">Loading orders...</div>';
        try {
            const data = await load({ customer: customer.value });
            setOptions(order, data.orders || [], 'Select sales reference', value => `${value.order_number} (${value.order_date || 'undated'})`);
            container.innerHTML = '<div class="text-muted p-3">Select a sales reference to load its purchased products.</div>';
            if (shouldRestoreReference && restoreState.transactionId && [...order.options].some(option => option.value === String(restoreState.transactionId))) {
                order.value = String(restoreState.transactionId);
                order.dispatchEvent(new Event('change'));
            }
        } catch { window.AppAlert?.show('Could not load orders for this customer.', 'error'); }
    });
    order.addEventListener('change', async () => {
        const isNational = channel.value === 'national_bookstore';
        const isEvent = channel.value === 'event';
        transactionId.value = isNational || isEvent ? '' : order.value;
        nationalPulloutId.value = isNational ? order.value : '';
        eventReference.value = isEvent ? order.value : '';
        attachmentPreview.replaceChildren();
        attachmentPreview.hidden = true;
        resetItemSearch();
        container.innerHTML = '<div class="text-muted p-3">Loading ordered products...</div>';
        try {
            const data = await load(isEvent ? { event_reference: order.value } : { transaction_id: order.value });
            if (channel.value === 'fully_booked') {
                showFullyBookedAttachment(data.transaction?.fully_booked_attachment);
            }
            const items = data.transaction?.items || [];
            const partialWholesaleReturn = channel.value === 'wholesale' && data.transaction?.payment_status === 'partial';
            const supportsAutoRefund = !['fully_booked', 'tiktok', 'shopee', 'lazada', 'national_bookstore', 'event'].includes(channel.value) && !partialWholesaleReturn;
            container.innerHTML = items.length ? items.map((item, index) => {
                const remaining = Math.max(0, item.quantity - (item.sold_quantity || 0) - (item.returned_quantity || 0));
                const goodRemaining = isEvent ? Number(item.remaining_good_quantity || 0) : remaining;
                const damagedRemaining = isEvent ? Number(item.remaining_damaged_quantity || 0) : remaining;
                const searchValue = `${item.item_id || ''} ${item.name || ''}`.toLowerCase();
                return `
                <div class="return-item ${supportsAutoRefund ? '' : 'return-item-no-refund'}" data-return-search="${escapeHtml(searchValue)}" data-unit-price="${Number(item.unit_price || 0)}" data-discount="${Number(item.discount_percentage || 0)}">
                    <div><label class="form-label d-block">${escapeHtml(item.item_id || '')}${item.replacement_id ? ' (Replacement)' : ''}</label><input class="form-control" value="${escapeHtml(item.name)}" readonly>${isEvent ? `<div class="form-text">Good available ${goodRemaining} · Damaged available ${damagedRemaining} · Total returnable ${remaining}</div>` : ''}${isNational
                        ? `<input type="hidden" name="items[${index}][national_product_id]" value="${item.national_product_id}"><input type="hidden" name="items[${index}][national_pullout_item_id]" value="${item.national_pullout_item_id}">`
                        : isEvent
                            ? `<input type="hidden" name="items[${index}][product_id]" value="${item.product_id}">`
                        : `<input type="hidden" name="items[${index}][product_id]" value="${item.product_id}"><input type="hidden" name="items[${index}][transaction_item_id]" value="${item.id || ''}"><input type="hidden" name="items[${index}][replacement_id]" value="${item.replacement_id || ''}">`}</div>
                    <div><label class="form-label">Good Qty</label><input type="number" name="items[${index}][good_quantity]" min="0" max="${goodRemaining}" value="0" class="form-control" ${goodRemaining ? '' : 'disabled'}></div>
                    <div><label class="form-label">Damaged Qty</label><input type="number" name="items[${index}][damaged_quantity]" min="0" max="${damagedRemaining}" value="0" class="form-control" ${damagedRemaining ? '' : 'disabled'}></div>
                    ${supportsAutoRefund ? '<div class="return-refund-control"><label class="form-label">Auto refund</label><input type="number" name="items[' + index + '][refund_amount]" min="0" step="0.01" value="0" class="form-control refund-amount" readonly><div class="form-text refund-help">Calculated from returned quantity</div></div>' : ''}
                </div>`;
            }).join('') : '<div class="alert alert-warning">This order has no products.</div>';
            itemSearchWrap.hidden = !items.length;
            restoreItemValues();
            if (partialWholesaleReturn && items.length) {
                container.insertAdjacentHTML('afterbegin', '<div class="alert alert-info small mb-3">This is a partially paid wholesale order. The item can be returned to stock, but no automatic cash refund is recorded. Its existing partial payment remains on the order.</div>');
            }
            container.querySelectorAll('.return-item').forEach(row => {
                if (!supportsAutoRefund) return;
                const toggle = row.querySelector('.refund-toggle');
                const amount = row.querySelector('.refund-amount');
                const help = row.querySelector('.refund-help');
                const good = row.querySelector('[name$="[good_quantity]"]');
                const damaged = row.querySelector('[name$="[damaged_quantity]"]');
                const updateRefund = () => {
                    const quantity = Number(good?.value || 0) + Number(damaged?.value || 0);
                    const unitPrice = Number(row.dataset.unitPrice || 0);
                    const discount = Number(row.dataset.discount || 0);
                    const refund = Math.round(unitPrice * (1 - (discount / 100)) * quantity * 100) / 100;
                    amount.value = refund.toFixed(2);
                    help.textContent = `₱${refund.toLocaleString('en-PH', {minimumFractionDigits: 2})}`;
                };
                toggle?.addEventListener('change', updateRefund);
                good?.addEventListener('input', updateRefund);
                damaged?.addEventListener('input', updateRefund);
                updateRefund();
            });
        } catch { window.AppAlert?.show('Could not load the order items.', 'error'); }
    });
    returnDate.addEventListener('change', () => channel.dispatchEvent(new Event('change')));
    if (presetChannel && [...channel.options].some(option => option.value === presetChannel)) channel.value = presetChannel;
    if (channel.value) channel.dispatchEvent(new Event('change'));
})();
</script>
@endpush
