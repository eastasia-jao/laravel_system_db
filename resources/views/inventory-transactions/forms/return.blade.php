@extends('layouts.app')

@section('content')
@php
    $currentUser = auth()->user();
    $visibleHubs = $currentUser?->role === 'admin'
        ? $hubs
        : $hubs->whereIn('id', $currentUser?->accessibleStoreHubIds() ?? [])->values();
    $hubSelectionLocked = $currentUser?->role !== 'admin' && $visibleHubs->count() <= 1;
    $channelSelectionLocked = $currentUser?->role === 'sales_associate'
        || ($selectedHub && ! $selectedHub->is_head_office);
@endphp
<style>
    .transaction-page-heading { display:flex; align-items:center; gap:1rem; padding:1.25rem 1.5rem; border:1px solid #dcfce7; border-radius:1rem; background:linear-gradient(110deg,#f0fdf4 0%,#fff 72%); }
    .transaction-page-heading-icon { display:grid; flex:0 0 3rem; width:3rem; height:3rem; place-items:center; border-radius:.875rem; background:#dcfce7; color:#16a34a; font-size:1.25rem; }
    .transaction-page-heading h3 { font-size:clamp(1.15rem,2vw,1.5rem); }
    .transaction-form-card { border:1px solid #e2e8f0 !important; }
    #returnItems { max-height: 430px; overflow-y: auto; overflow-x: hidden; padding: .5rem; border: 1px solid #dee2e6; border-radius: .375rem; }
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
    @media (max-width: 991.98px) { .transaction-page-heading { align-items:flex-start; padding:1rem; } .transaction-page-heading-icon { flex-basis:2.5rem; width:2.5rem; height:2.5rem; } .return-entry-layout { grid-template-columns: 1fr; } .return-notes-panel { position: static; } .return-actions { grid-column: 1; justify-content:flex-end; } .return-item { grid-template-columns: minmax(220px, 1.5fr) repeat(2, minmax(145px, 1fr)); } .return-refund-control { grid-column: auto; padding-left: .5rem; border-left: 1px solid #cbd5e1; border-top: 0; } }
    @media (max-width: 575.98px) { .transaction-page-heading { align-items:flex-start; padding:1rem; } .return-item { grid-template-columns: 1fr; } .return-refund-control { grid-column: auto; } .return-actions { justify-content:flex-end; } .return-actions > * { flex:1 1 auto; } }
</style>
<div class="p-5">
    <header class="transaction-page-heading mb-4">
        <span class="transaction-page-heading-icon" aria-hidden="true"><i class="fa-solid fa-rotate-left"></i></span>
        <div>
            <div class="small text-uppercase fw-bold text-success mb-1">Inventory Adjustment</div>
            <h3 class="fw-bold mb-1">Return Items</h3>
            <p class="text-muted small mb-0">Record returned products and classify each item as good or damaged.</p>
        </div>
    </header>

    <form method="POST" action="{{ route('inventory-transactions.return.store') }}" class="card transaction-form-card shadow-sm rounded-4 p-4">
        @csrf
        <input type="hidden" name="type" value="return">
        <input type="hidden" name="sales_transaction_id" id="salesTransactionId">
        <div class="row g-3">
            <div class="col-md-4"><label class="form-label">Store Hub</label><select name="store_hub_id" class="form-select" required @disabled($hubSelectionLocked || $visibleHubs->isEmpty()) onchange="window.location.href='{{ route('inventory-transactions.return.create') }}?hub_id='+this.value">
                @foreach($visibleHubs as $hub)<option value="{{ $hub->id }}" @selected((int) $hubId === (int) $hub->id)>{{ $hub->name }}</option>@endforeach
            </select></div>
            @if($hubSelectionLocked && $visibleHubs->isNotEmpty())
                <input type="hidden" name="store_hub_id" value="{{ $hubId }}">
            @endif
            @if($visibleHubs->isEmpty())
                <div class="col-12"><div class="alert alert-warning mb-0">Your account has no assigned store. Contact an administrator before recording return items.</div></div>
            @endif
            <div class="col-md-4"><label class="form-label">Return Date</label><input type="date" name="occurred_on" id="returnDate" value="{{ old('occurred_on', now()->toDateString()) }}" class="form-control" required></div>
            <div class="col-md-4"><label class="form-label">Sales Channel</label><select name="channel" id="returnChannel" class="form-select" required @disabled($channelSelectionLocked)>
                @if($channelSelectionLocked)
                    <option value="walk_in" selected>Walk-In</option>
                @else
                    <option value="">Select channel</option><option value="shopee">Shopee</option><option value="lazada">Lazada</option><option value="tiktok">TikTok</option><option value="online">Online</option><option value="walk_in">Walk-in</option><option value="wholesale">Wholesale</option><option value="fully_booked">Fully Booked</option>
                @endif
            </select>
            @if($channelSelectionLocked)
                <input type="hidden" name="channel" value="walk_in">
            @endif
            </div>
            <div class="col-md-4" id="returnCustomerField"><label class="form-label">Customer <span id="customerOptionalLabel" class="text-muted fw-normal">(required)</span></label><select id="returnCustomer" class="form-select" required disabled><option value="">Select return date and sales channel first</option></select></div>
            <div class="col-md-4"><label class="form-label">Sales Reference</label><select name="reference" id="returnOrder" class="form-select" required disabled><option value="">Select customer first</option></select></div>
            <input type="hidden" name="source" id="returnSource">
        </div>
        <div id="returnAttachmentPreview" class="mt-3" hidden></div>
        <div class="d-flex justify-content-between align-items-center mt-4 mb-2">
            <h5 class="fw-bold mb-0">Returned Items</h5>
        </div>
        <div class="return-entry-layout">
            <div id="returnItems"><div class="text-muted p-3">Select a date, customer, and sales reference to load the purchased products.</div></div>
            <div class="return-notes-panel"><label class="form-label" for="returnNotes">Notes</label><textarea id="returnNotes" name="notes" class="form-control" rows="6" maxlength="120" placeholder="Add notes about the returned items"></textarea><div class="form-text text-end"><span id="returnNotesCount">0</span>/120</div></div>
            <div class="return-actions"><button class="btn btn-primary"><i class="fa-solid fa-check me-1" aria-hidden="true"></i>Save Return Items</button><a href="{{ route('inventory-transactions.index', ['hub_id' => $hubId]) }}" class="btn btn-outline-secondary">Cancel</a></div>
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
    const container = document.getElementById('returnItems');
    const attachmentPreview = document.getElementById('returnAttachmentPreview');
    const notes = document.getElementById('returnNotes');
    const notesCount = document.getElementById('returnNotesCount');
    notes?.addEventListener('input', () => { notesCount.textContent = notes.value.length; });
    const setOptions = (select, items, placeholder, label) => {
        select.replaceChildren(new Option(placeholder, ''));
        items.forEach(item => select.add(new Option(label(item), item.id ?? item)));
        select.disabled = !items.length;
    };
    const updateCustomerState = () => {
        const fullyBooked = channel.value === 'fully_booked';
        customer.required = !fullyBooked;
        document.getElementById('customerOptionalLabel').textContent = fullyBooked ? '' : '(required)';
        document.getElementById('returnCustomerField').classList.toggle('d-none', fullyBooked);
        if (fullyBooked) {
            customer.value = '';
            document.getElementById('returnSource').value = '';
        }
    };
    const resetOrder = placeholder => {
        order.replaceChildren(new Option(placeholder, ''));
        order.disabled = true;
        transactionId.value = '';
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
    const load = async params => {
        const url = new URL(endpoint, window.location.origin);
        Object.entries({ hub_id: hubId, channel: channel.value, date: returnDate.value, ...params }).forEach(([key, value]) => url.searchParams.set(key, value));
        const response = await fetch(url, { headers: { Accept: 'application/json' } });
        if (!response.ok) throw new Error('Unable to load sales');
        return response.json();
    };
    channel.addEventListener('change', async () => {
        customer.disabled = true; resetOrder('Loading sales references...'); container.innerHTML = '<div class="text-muted p-3">Loading customers...</div>';
        try {
            updateCustomerState();
            const data = await load({});
            if (channel.value === 'fully_booked') {
                setOptions(order, data.orders || [], 'Select order number', value => value.order_number);
                container.innerHTML = data.orders?.length
                    ? '<div class="text-muted p-3">Select an order number to load its purchased products.</div>'
                    : '<div class="alert alert-warning mb-0">No Fully Booked orders were found on the selected date.</div>';
                return;
            }
            setOptions(customer, data.customers || [], 'Select customer', value => value);
            resetOrder('Select customer first');
            container.innerHTML = data.customers?.length
                ? '<div class="text-muted p-3">Select a customer to load sales references.</div>'
                : '<div class="alert alert-warning mb-0">No customers have sales on the selected date.</div>';
        } catch { window.AppAlert?.show('Could not load sales for this channel.', 'error'); }
    });
    customer.addEventListener('change', async () => {
        document.getElementById('returnSource').value = customer.value;
        order.disabled = true; transactionId.value = ''; container.innerHTML = '<div class="text-muted p-3">Loading orders...</div>';
        try {
            const data = await load({ customer: customer.value });
            setOptions(order, data.orders || [], 'Select sales reference', value => `${value.order_number} (${value.order_date || 'undated'})`);
            container.innerHTML = '<div class="text-muted p-3">Select a sales reference to load its purchased products.</div>';
        } catch { window.AppAlert?.show('Could not load orders for this customer.', 'error'); }
    });
    order.addEventListener('change', async () => {
        transactionId.value = order.value;
        attachmentPreview.replaceChildren();
        attachmentPreview.hidden = true;
        container.innerHTML = '<div class="text-muted p-3">Loading ordered products...</div>';
        try {
            const data = await load({ transaction_id: order.value });
            if (channel.value === 'fully_booked') {
                showFullyBookedAttachment(data.transaction?.fully_booked_attachment);
            }
            const items = data.transaction?.items || [];
            const supportsAutoRefund = !['fully_booked', 'tiktok'].includes(channel.value);
            container.innerHTML = items.length ? items.map((item, index) => {
                const remaining = Math.max(0, item.quantity - (item.returned_quantity || 0));
                return `
                <div class="return-item ${supportsAutoRefund ? '' : 'return-item-no-refund'}" data-unit-price="${Number(item.unit_price || 0)}" data-discount="${Number(item.discount_percentage || 0)}">
                    <div><label class="form-label d-block">${item.item_id || ''}${item.replacement_id ? ' (Replacement)' : ''}</label><input class="form-control" value="${item.name}" readonly><input type="hidden" name="items[${index}][product_id]" value="${item.product_id}"><input type="hidden" name="items[${index}][transaction_item_id]" value="${item.id || ''}"><input type="hidden" name="items[${index}][replacement_id]" value="${item.replacement_id || ''}"></div>
                    <div><label class="form-label">Good Qty <span class="fw-normal">(remaining: ${remaining})</span></label><input type="number" name="items[${index}][good_quantity]" min="0" max="${remaining}" value="0" class="form-control" ${remaining ? '' : 'disabled'}></div>
                    <div><label class="form-label">Damaged Qty</label><input type="number" name="items[${index}][damaged_quantity]" min="0" max="${remaining}" value="0" class="form-control" ${remaining ? '' : 'disabled'}></div>
                    ${supportsAutoRefund ? '<div class="return-refund-control"><label class="form-label">Auto refund</label><input type="number" name="items[' + index + '][refund_amount]" min="0" step="0.01" value="0" class="form-control refund-amount" readonly><div class="form-text refund-help">Calculated from returned quantity</div></div>' : ''}
                </div>`;
            }).join('') : '<div class="alert alert-warning">This order has no products.</div>';
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
    if (channel.value === 'walk_in') channel.dispatchEvent(new Event('change'));
})();
</script>
@endpush
