@extends('layouts.app')

@section('content')
<style>
    .transaction-page-heading {
        display: flex;
        align-items: center;
        gap: 1rem;
        padding: 1.25rem 1.5rem;
        border: 1px solid #ede9fe;
        border-radius: 1rem;
        background: linear-gradient(110deg, #f5f3ff 0%, #fff 72%);
    }
    .transaction-page-heading-icon {
        display: grid;
        flex: 0 0 3rem;
        width: 3rem;
        height: 3rem;
        place-items: center;
        border-radius: .875rem;
        background: #ede9fe;
        color: #7c3aed;
        font-size: 1.25rem;
    }
    .transaction-page-heading h3 { font-size: clamp(1.15rem, 2vw, 1.5rem); }
    .transaction-form-card { border: 1px solid #e2e8f0 !important; }
    .fully-booked-review-card { border: 1px solid #e2e8f0; }
    .fully-booked-request-remarks { max-width: 34rem; }
    .fully-booked-product-search .product-search-results {
        z-index: 20;
        max-height: 260px;
        overflow-y: auto;
    }
    .fully-booked-pullout-rows {
        max-height: 320px;
        overflow-y: auto;
        overflow-x: hidden;
        padding: .5rem;
        border: 1px solid #dee2e6;
        border-radius: .375rem;
    }
    .fully-booked-attachment-preview {
        min-height: 220px;
        display: flex;
        align-items: center;
        justify-content: center;
        padding: .75rem;
        border: 1px solid #e2e8f0;
        border-radius: .75rem;
        background: #f8fafc;
    }
    @media (max-width: 575.98px) {
        .transaction-page-heading { align-items: flex-start; padding: 1rem; }
        .transaction-page-heading-icon { flex-basis: 2.5rem; width: 2.5rem; height: 2.5rem; }
    }
    #sponsorItems {
        max-height: 430px;
        overflow-y: auto;
        overflow-x: hidden;
        padding: 0.75rem;
        border: 1px solid #dee2e6;
        border-radius: 0.375rem;
    }

    #sponsorItemsHeader {
        padding: 0 0.75rem 0.5rem;
        border-bottom: 1px solid #dee2e6;
    }

    #sponsorItems .sponsor-item + .sponsor-item {
        border-top: 1px solid #edf0f2;
        padding-top: 0.75rem;
    }

    @media (max-width: 767.98px) {
        #sponsorItemsHeader {
            display: none;
        }
    }
</style>
<div class="p-5">
    @php($isFullyBookedPage = $activityType === 'fully_booked')
    <header class="transaction-page-heading mb-4">
        <span class="transaction-page-heading-icon" aria-hidden="true"><i id="activityHeadingIcon" class="fa-solid {{ $isFullyBookedPage ? 'fa-book-open' : 'fa-people-group' }}"></i></span>
        <div>
            <div id="activityHeadingLabel" class="small text-uppercase fw-bold mb-1" style="color:#7c3aed">{{ $isFullyBookedPage ? 'Event / Fully Booked' : 'Event' }}</div>
            <h3 id="activityHeadingTitle" class="fw-bold mb-1">{{ $isFullyBookedPage ? 'Event / Fully Booked' : 'Event' }}</h3>
            <p id="activityHeadingDescription" class="text-muted small mb-0">{{ $isFullyBookedPage ? 'Review Fully Booked orders and update the selected store inventory.' : 'Record event inventory usage or submit a Fully Booked order attachment for review.' }}</p>
        </div>
    </header>
    
    <form id="eventFullyBookedForm" method="POST" action="{{ $activityType === 'fully_booked' ? route('fully-booked-orders.store') : route('inventory-transactions.store') }}" enctype="multipart/form-data" data-event-action="{{ route('inventory-transactions.store') }}" data-fully-booked-action="{{ route('fully-booked-orders.store') }}" data-can-manage-event="{{ $canManageEvent ? '1' : '0' }}" class="card transaction-form-card shadow-sm rounded-4 p-4">
        @csrf
        @if($canManageEvent && $activityType !== 'fully_booked')
            <div class="row g-3 mb-4">
                <div class="col-md-6 col-lg-4">
                    <label class="form-label fw-semibold" for="activityType">Type</label>
                    <select id="activityType" name="activity_type" class="form-select" required>
                        <option value="event" @selected(old('activity_type', $activityType) === 'event')>Event</option>
                        @if($canUseFullyBookedForm)
                            <option value="fully_booked" @selected(old('activity_type', $activityType) === 'fully_booked')>Fully Booked</option>
                        @endif
                    </select>
                </div>
            </div>
        @else
            <input type="hidden" id="activityType" name="activity_type" value="fully_booked">
        @endif
        <section id="eventFields" @if(!$canManageEvent || old('activity_type', $activityType) !== 'event') hidden @endif>
            @if($canManageEvent)
                @include('inventory-transactions.forms.bulk-items')
                <input type="hidden" name="type" value="sponsor_workshop">
                <div class="row g-3">
                    <div class="col-md-4"><label class="form-label">Store Hub</label><select name="store_hub_id" class="form-select" required onchange="window.location.href='{{ route('inventory-transactions.sponsor.create') }}?hub_id='+this.value">
                        @foreach($hubs->filter(fn ($hub) => $hub->status === 'active') as $hub)<option value="{{ $hub->id }}" @selected((int) $hubId === $hub->id)>{{ $hub->name }}</option>@endforeach
                    </select></div>
                    <div class="col-md-4"><label class="form-label">Date</label><input type="date" name="occurred_on" value="{{ old('occurred_on', now()->toDateString()) }}" class="form-control" required></div>
                    <div class="col-md-4"><label class="form-label">Event / Recipient</label><input name="source" class="form-control" required></div>
                </div>
                <div class="d-flex justify-content-between align-items-center mt-4 mb-2">
                    <h5 class="fw-bold mb-0">Items to Pull Out</h5>
                    <button type="button" class="btn btn-outline-primary btn-sm" id="addSponsorItem"><i class="fa-solid fa-plus me-1"></i>Add Item</button>
                </div>
                <div id="sponsorItemsHeader" class="row g-2 fw-semibold text-muted small">
                    <div class="col-md-9">Product</div>
                    <div class="col-md-2">Quantity</div>
                    <div class="col-md-1">Action</div>
                </div>
                <div id="sponsorItems">
                    <div class="row g-2 align-items-center sponsor-item py-2">
                        <div class="col-md-9"><input type="search" class="form-control product-search" list="sponsorProductOptions" placeholder="Search item code, barcode, or description" autocomplete="off" aria-label="Product" required>
                            <input type="hidden" name="items[0][product_id]" class="product-id"></div>
                        <div class="col-md-2"><input type="number" name="items[0][quantity]" min="1" class="form-control" aria-label="Quantity" placeholder="Quantity" required></div>
                        <div class="col-md-1"><button type="button" class="btn btn-outline-danger remove-sponsor-item" aria-label="Remove item" disabled><i class="fa-solid fa-trash"></i></button></div>
                    </div>
                </div>
                <div class="row g-3 mt-2">
                    <div class="col-md-4"><label class="form-label">Reference Document No.</label><input name="reference" class="form-control" value="{{ old('reference', $sponsorReference) }}" readonly aria-readonly="true"></div>
                    <div class="col-md-8"><label class="form-label">Remarks</label><textarea name="notes" class="form-control" rows="2"></textarea></div>
                </div>
                <div class="d-flex justify-content-end gap-2 mt-4">
                    <button class="btn btn-primary"><i class="fa-solid fa-check me-1" aria-hidden="true"></i>Save Event Items</button>
                    <a href="{{ route('inventory-transactions.index', ['hub_id' => $hubId]) }}" class="btn btn-outline-secondary">Cancel</a>
                </div>
            @endif
        </section>
        @if($canUseFullyBookedForm)
        <section id="fullyBookedFields" @if(old('activity_type', $activityType) !== 'fully_booked') hidden @endif>
            @if($canSubmitFullyBooked)
            <div class="row g-3">
                <div class="col-md-6">
                    <label class="form-label fw-semibold" for="fullyBookedOrderNumber">Fully Booked Order Number</label>
                    <input id="fullyBookedOrderNumber" class="form-control" value="Generated automatically when submitted" readonly>
                </div>
                <div class="col-md-6">
                    <label class="form-label fw-semibold" for="fullyBookedStaff">Sales / Marketing Staff</label>
                    <input id="fullyBookedStaff" class="form-control" value="{{ auth()->user()->name }}{{ auth()->user()->storeHub ? ' — '.auth()->user()->storeHub->name : '' }}" readonly>
                </div>
                <div class="col-md-6">
                    <label class="form-label fw-semibold" for="fullyBookedStore">Store</label>
                    <select id="fullyBookedStore" name="store_selection" class="form-select" required>
                        <option value="">Select a store</option>
                        @foreach($fullyBookedStores as $store)
                            <option value="{{ $store->id }}" @selected(old('store_selection', (string) auth()->user()->hub_id) === (string) $store->id)>{{ $store->name }}</option>
                        @endforeach
                        <option value="other" @selected(old('store_selection') === 'other')>Other</option>
                    </select>
                </div>
                <div class="col-md-6" id="fullyBookedOtherStoreField" @if(old('store_selection') !== 'other') hidden @endif>
                    <label class="form-label fw-semibold" for="fullyBookedOtherStore">Specify store</label>
                    <input id="fullyBookedOtherStore" name="other_store_name" class="form-control" value="{{ old('other_store_name') }}" maxlength="255" @required(old('store_selection') === 'other')>
                </div>
                <div class="col-12">
                    <label class="form-label fw-semibold" for="fullyBookedAttachment">Order Attachment</label>
                    <input id="fullyBookedAttachment" name="attachment" type="file" class="form-control" accept="image/jpeg,image/png,image/webp,application/pdf" required>
                    <div class="form-text">Upload a JPEG, PNG, WebP, or PDF file (maximum 10 MB). Your order number will be generated automatically. Inventory Staff can preview it for review.</div>
                </div>
                <div class="col-12">
                    <label class="form-label fw-semibold" for="fullyBookedRemarks">Remarks</label>
                    <textarea id="fullyBookedRemarks" name="remarks" class="form-control" rows="3" maxlength="2000" placeholder="Add any details or instructions for Inventory Staff">{{ old('remarks') }}</textarea>
                </div>
                <div class="col-12">
                    <div id="fullyBookedPreview" class="border rounded-3 p-2 bg-light" hidden>
                        <div class="fw-semibold small text-muted mb-2">Attachment Preview</div>
                        <img id="fullyBookedImagePreview" class="img-fluid rounded" alt="Selected Fully Booked order attachment preview" style="max-height:560px" hidden>
                        <iframe id="fullyBookedPdfPreview" title="Selected Fully Booked order PDF preview" class="w-100 border rounded bg-white" style="height:560px" hidden></iframe>
                    </div>
                </div>
            </div>
            <div class="d-flex justify-content-end gap-2 mt-4">
                <button id="submitFullyBooked" class="btn btn-primary" @disabled($fullyBookedStaff->isEmpty())><i class="fa-solid fa-paper-plane me-1" aria-hidden="true"></i>Send for Review</button>
                @if(auth()->user()->role === 'sales_marketing_staff')
                    <a href="{{ route('hub.dashboard', auth()->user()->hub_id) }}" class="btn btn-outline-secondary">Cancel</a>
                @else
                    <a href="{{ route('inventory-transactions.fully-booked.index') }}" class="btn btn-outline-secondary">Cancel</a>
                @endif
            </div>
            @elseif($canManageEvent)
                <p class="small text-muted mb-0">Select an order below to preview its attachment, choose the products to pull out, and deduct stock.</p>
            @endif
        </section>
        @endif
    </form>
    @if($canSubmitFullyBooked && $fullyBookedOrders->isNotEmpty())
        <section class="mt-4">
            <div class="d-flex flex-wrap justify-content-between align-items-end gap-2 mb-3">
                <div>
                    <div class="small text-uppercase fw-bold text-primary">Order tracking</div>
                    <h5 class="fw-bold mb-0">My Fully Booked Orders</h5>
                </div>
                <span class="small text-muted">Showing {{ $fullyBookedOrders->firstItem() ?? 0 }}–{{ $fullyBookedOrders->lastItem() ?? 0 }} of {{ $fullyBookedOrders->total() }} submission(s)</span>
            </div>
            <div class="row g-3">
                @foreach($fullyBookedOrders as $order)
                    <div class="col-12 col-xl-6">
                        <article class="card border-0 shadow-sm rounded-4 p-3 h-100">
                            <div class="d-flex flex-wrap justify-content-between align-items-start gap-2">
                                <div>
                                    <strong>{{ $order->order_number }}</strong>
                                    <div class="small text-muted">Store: {{ $order->store_name ?? '—' }} · Submitted {{ $order->created_at?->format('M d, Y h:i A') }}</div>
                                </div>
                                <span class="badge rounded-pill {{ $order->pulled_out_at ? 'text-bg-success' : 'text-bg-warning' }}">
                                    {{ $order->pulled_out_at ? 'Stock updated' : 'Pending inventory pull-out' }}
                                </span>
                            </div>
                            @if($order->pulled_out_at)
                                <div class="small text-muted mt-2">
                                    {{ $order->items->count() }} item line(s) · {{ $order->items->sum('quantity') }} total updated
                                    · Completed {{ $order->pulled_out_at->format('M d, Y h:i A') }}
                                </div>
                            @else
                                <div class="small text-muted mt-2">Inventory Staff will review the attachment and update the stock for this order.</div>
                            @endif
                            @if($order->remarks)
                                <div class="small mt-2"><strong>Remarks:</strong> {{ $order->remarks }}</div>
                            @endif
                            <div class="d-flex gap-2 mt-3">
                                <a class="btn btn-sm btn-outline-primary" href="{{ route('inventory-transactions.fully-booked.attachment', $order) }}" target="_blank" rel="noopener">View Attachment</a>
                                <a class="btn btn-sm btn-outline-secondary" href="{{ route('inventory-transactions.fully-booked.attachment', ['fullyBookedOrder' => $order, 'download' => 1]) }}">Download</a>
                            </div>
                        </article>
                    </div>
                @endforeach
            </div>
            <div class="mt-3">
                {{ $fullyBookedOrders->links() }}
            </div>
        </section>
    @endif
    @if($canManageEvent)
        <section id="fullyBookedOrders" class="mt-4" @if(old('activity_type', $activityType) !== 'fully_booked') hidden @endif>
            <div class="d-flex flex-wrap justify-content-between align-items-end gap-2 mb-3">
                <div>
                    <div class="small text-uppercase fw-bold text-primary">Inventory processing</div>
                    <h5 class="fw-bold mb-0">Orders Awaiting Inventory Pull-Out</h5>
                </div>
                <div class="d-flex align-items-center gap-2">
                    <span class="badge rounded-pill text-bg-warning">{{ $fullyBookedOrders->count() }} pending</span>
                    <a id="fullyBookedPageCancel" class="btn btn-sm btn-outline-secondary" href="{{ route('inventory-transactions.index', ['hub_id' => $hubId]) }}">Cancel</a>
                </div>
            </div>
            @forelse($fullyBookedOrders as $order)
                <article class="card border-0 shadow-sm rounded-4 p-3 p-lg-4 mb-3 fully-booked-review-card">
                    <div class="d-flex flex-wrap justify-content-between align-items-start gap-3 border-bottom pb-3 mb-3">
                        <div class="d-flex align-items-start gap-3">
                            <span class="transaction-page-heading-icon" aria-hidden="true"><i class="fa-solid fa-file-circle-check"></i></span>
                            <div>
                                <div class="d-flex flex-wrap align-items-center gap-2">
                                    <h6 class="fw-bold mb-0">{{ $order->order_number }}</h6>
                                    <span class="badge rounded-pill text-bg-warning">Awaiting stock update</span>
                                </div>
                                <div class="small text-muted mt-1">{{ $order->salesStaff?->name ?? 'Deleted staff' }} · {{ $order->storeHub?->name ?? '—' }} · Order store: {{ $order->store_name ?? '—' }} · Submitted {{ $order->created_at?->format('M d, Y h:i A') }}</div>
                            </div>
                        </div>
                        @if($order->remarks)
                            <div class="alert alert-light border small mb-0 fully-booked-request-remarks">
                                <strong>Request remarks:</strong>
                                <div class="mt-1" style="white-space:pre-wrap">{{ $order->remarks }}</div>
                            </div>
                        @endif
                    </div>
                    <div class="row g-3 align-items-stretch">
                        <div class="col-lg-5">
                            <div class="fully-booked-attachment-preview">
                                @if(str_starts_with($order->mime_type, 'image/'))
                                    <img src="{{ route('inventory-transactions.fully-booked.attachment', $order) }}" alt="Attachment for Fully Booked order {{ $order->order_number }}" class="img-fluid rounded" style="max-height:330px;object-fit:contain">
                                @else
                                    <iframe src="{{ route('inventory-transactions.fully-booked.attachment', $order) }}" title="Attachment for Fully Booked order {{ $order->order_number }}" class="w-100 border rounded bg-white" style="height:330px"></iframe>
                                @endif
                            </div>
                            <div class="d-flex flex-wrap gap-2 mt-2">
                                <a class="btn btn-sm btn-outline-primary" href="{{ route('inventory-transactions.fully-booked.attachment', $order) }}" target="_blank" rel="noopener"><i class="fa-solid fa-eye me-1"></i>Preview Attachment</a>
                                <a class="btn btn-sm btn-outline-secondary" href="{{ route('inventory-transactions.fully-booked.attachment', ['fullyBookedOrder' => $order, 'download' => 1]) }}"><i class="fa-solid fa-download me-1"></i>Download</a>
                            </div>
                        </div>
                        <div class="col-lg-7">
                            <form method="POST" action="{{ route('inventory-transactions.fully-booked.pull-out', $order) }}" class="fully-booked-pullout-form" data-order-number="{{ $order->order_number }}" data-endpoint="{{ route('hub.products.search.ajax', $order->store_hub_id) }}">
                                @csrf
                                <h6 class="fw-bold mb-1">Items for this order</h6>
                                <p class="small text-muted mb-3">Choose the products and quantities from {{ $order->storeHub?->name ?? 'this hub' }}. Stock will be updated when you complete the order.</p>
                                <div class="fully-booked-pullout-rows">
                                    <div class="row g-2 align-items-center fully-booked-pullout-row mb-2">
                                        <div class="col-md-8">
                                            <div class="position-relative fully-booked-product-search">
                                                <input type="search" class="form-control product-search" placeholder="Type product name, item code, or barcode" autocomplete="off" aria-label="Search products" aria-autocomplete="list" aria-expanded="false" required>
                                                <div class="product-search-results list-group position-absolute top-100 start-0 end-0 shadow-sm" role="listbox" hidden></div>
                                            </div>
                                            <input type="hidden" name="items[0][product_id]" class="product-id">
                                        </div>
                                        <div class="col-md-3"><input type="number" name="items[0][quantity]" min="1" class="form-control" placeholder="Quantity" aria-label="Quantity" required></div>
                                        <div class="col-md-1"><button type="button" class="btn btn-outline-danger remove-fully-booked-row" disabled aria-label="Remove item"><i class="fa-solid fa-trash"></i></button></div>
                                    </div>
                                </div>
                                <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mt-3">
                                    <div class="d-flex flex-wrap gap-2">
                                        <button type="button" class="btn btn-sm btn-outline-primary add-fully-booked-row"><i class="fa-solid fa-plus me-1"></i>Add Item</button>
                                        <button type="button" class="btn btn-sm btn-outline-success export-fully-booked-csv"><i class="fa-solid fa-file-csv me-1"></i>Export CSV</button>
                                    </div>
                                    <button class="btn btn-success"><i class="fa-solid fa-circle-check me-1"></i>Complete Order & Update Stock</button>
                                </div>
                            </form>
                        </div>
                    </div>
                </article>
            @empty
                <div class="alert alert-info mb-0">There are no Fully Booked attachments awaiting pull-out.</div>
            @endforelse
        </section>
    @endif
</div>
@endsection

@push('scripts')
<script src="{{ asset('js/product-suggestions.js') }}"></script>
<script src="{{ asset('js/fully-booked-pullout.js') }}"></script>
<script>
(() => {
    const container = document.getElementById('sponsorItems');
    const addButton = document.getElementById('addSponsorItem');
    if (!container || !addButton) return;
    const productOptions = @json($sponsorProductOptions);
    const dataList = document.createElement('datalist');
    dataList.id = 'sponsorProductOptions';
    productOptions.forEach(product => {
        const option = document.createElement('option');
        option.value = product.label;
        dataList.appendChild(option);
    });
    document.body.appendChild(dataList);
    setupTransactionSuggestions(container, dataList, productOptions, @json(route('hub.products.search.ajax', $hubId ?? 0)));
    let itemIndex = 1;

    const updateRemoveButtons = () => {
        const rows = container.querySelectorAll('.sponsor-item');
        rows.forEach(row => {
            row.querySelector('.remove-sponsor-item').disabled = rows.length <= 1;
        });
    };

    const bindProductSearch = row => {
        const input = row.querySelector('.product-search');
        const hidden = row.querySelector('.product-id');
        input.addEventListener('input', () => {
            const value = input.value.trim().toLowerCase();
            const product = productOptions.find(item =>
                item.label.toLowerCase() === value
            );
            hidden.value = product ? product.id : '';
            input.setCustomValidity(product ? '' : 'Select a product from the search suggestions.');
        });
    };

    bindProductSearch(container.querySelector('.sponsor-item'));
    addButton.addEventListener('click', () => {
        const row = document.createElement('div');
        row.className = 'row g-2 align-items-center sponsor-item py-2';
        row.innerHTML = `<div class="col-md-9"><input type="search" class="form-control product-search" list="sponsorProductOptions" placeholder="Search item code, barcode, or description" autocomplete="off" aria-label="Product" required>
            <input type="hidden" name="items[${itemIndex}][product_id]" class="product-id"></div>
            <div class="col-md-2"><input type="number" name="items[${itemIndex}][quantity]" min="1" class="form-control" aria-label="Quantity" placeholder="Quantity" required></div>
            <div class="col-md-1"><button type="button" class="btn btn-outline-danger remove-sponsor-item" aria-label="Remove item"><i class="fa-solid fa-trash"></i></button></div>`;
        container.appendChild(row);
        bindProductSearch(row);
        updateRemoveButtons();
        itemIndex++;
    });
    container.addEventListener('click', event => {
        const button = event.target.closest('.remove-sponsor-item');
        if (button && !button.disabled) {
            button.closest('.sponsor-item').remove();
            updateRemoveButtons();
        }
    });
})();
</script>
@endpush
@push('scripts')
<script>
(() => {
    const form = document.getElementById('eventFullyBookedForm');
    const selector = document.getElementById('activityType');
    const eventFields = document.getElementById('eventFields');
    const fullyBookedFields = document.getElementById('fullyBookedFields');
    const fullyBookedOrders = document.getElementById('fullyBookedOrders');
    const headingLabel = document.getElementById('activityHeadingLabel');
    const headingTitle = document.getElementById('activityHeadingTitle');
    const headingDescription = document.getElementById('activityHeadingDescription');
    const headingIcon = document.getElementById('activityHeadingIcon');
    if (!form || !selector) return;

    const setSectionState = (section, active) => {
        if (!section) return;
        section.hidden = !active;
        section.querySelectorAll('input, select, textarea, button').forEach(control => {
            if (control.dataset.originalRequired === undefined) {
                control.dataset.originalRequired = control.required ? 'true' : 'false';
            }
            control.disabled = !active;
            control.required = active && control.dataset.originalRequired === 'true';
        });
    };
    const updateActivityType = () => {
        const isFullyBooked = selector.value === 'fully_booked';
        form.action = isFullyBooked ? form.dataset.fullyBookedAction : form.dataset.eventAction;
        setSectionState(eventFields, !isFullyBooked);
        setSectionState(fullyBookedFields, isFullyBooked);
        if (fullyBookedOrders) fullyBookedOrders.hidden = !isFullyBooked;
        if (headingLabel) headingLabel.textContent = isFullyBooked ? 'Event / Fully Booked' : 'Event';
        if (headingTitle) headingTitle.textContent = isFullyBooked ? 'Event / Fully Booked' : 'Event';
        if (headingDescription) {
            headingDescription.textContent = isFullyBooked
                ? 'Review Fully Booked orders and update the selected store inventory.'
                : 'Record event inventory usage or submit a Fully Booked order attachment for review.';
        }
        if (headingIcon) {
            headingIcon.classList.toggle('fa-book-open', isFullyBooked);
            headingIcon.classList.toggle('fa-people-group', !isFullyBooked);
        }
    };

    selector.addEventListener('change', updateActivityType);
    form.addEventListener('submit', event => {
        if (form.dataset.canManageEvent === '1' && selector.value === 'fully_booked') {
            event.preventDefault();
        }
    });
    updateActivityType();

    const storeSelection = document.getElementById('fullyBookedStore');
    const otherStoreField = document.getElementById('fullyBookedOtherStoreField');
    const otherStoreInput = document.getElementById('fullyBookedOtherStore');
    const updateOtherStore = () => {
        const isOther = storeSelection?.value === 'other';
        if (otherStoreField) otherStoreField.hidden = !isOther;
        if (otherStoreInput) {
            otherStoreInput.required = Boolean(isOther);
            otherStoreInput.disabled = !isOther;
        }
    };
    storeSelection?.addEventListener('change', updateOtherStore);
    updateOtherStore();

    const fileInput = document.getElementById('fullyBookedAttachment');
    const preview = document.getElementById('fullyBookedPreview');
    const image = document.getElementById('fullyBookedImagePreview');
    const pdf = document.getElementById('fullyBookedPdfPreview');
    let previewUrl = null;
    fileInput?.addEventListener('change', () => {
        if (previewUrl) URL.revokeObjectURL(previewUrl);
        previewUrl = null;
        image.hidden = true;
        pdf.hidden = true;
        preview.hidden = true;
        image.removeAttribute('src');
        pdf.removeAttribute('src');
        const file = fileInput.files?.[0];
        if (!file) return;
        const extension = file.name.toLowerCase().split('.').pop();
        const isImage = ['image/jpeg', 'image/png', 'image/webp'].includes(file.type)
            || ['jpg', 'jpeg', 'png', 'webp'].includes(extension);
        const isPdf = file.type === 'application/pdf' || extension === 'pdf';
        if (file.size > 10 * 1024 * 1024 || (!isImage && !isPdf)) {
            fileInput.setCustomValidity('Choose a JPEG, PNG, WebP, or PDF attachment no larger than 10 MB.');
            fileInput.reportValidity();
            fileInput.value = '';
            return;
        }
        fileInput.setCustomValidity('');
        previewUrl = URL.createObjectURL(file);
        preview.hidden = false;
        if (isImage) {
            image.src = previewUrl;
            image.hidden = false;
        } else {
            pdf.src = previewUrl;
            pdf.hidden = false;
        }
    });
    window.addEventListener('beforeunload', () => {
        if (previewUrl) URL.revokeObjectURL(previewUrl);
    });
})();
</script>
@endpush
