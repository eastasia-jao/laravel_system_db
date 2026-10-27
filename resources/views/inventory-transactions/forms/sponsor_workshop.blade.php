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
    .product-search-results {
        position: fixed;
        z-index: 1080;
        max-height: 260px;
        overflow-y: auto;
        box-shadow: 0 .25rem .75rem rgba(0, 0, 0, .15);
    }
    .fully-booked-pullout-rows {
        overflow-y: visible;
        overflow-x: hidden;
        padding: .5rem;
        border: 1px solid #dee2e6;
        border-radius: .375rem;
    }
    .fully-booked-pullout-rows.is-scrollable {
        max-height: 220px;
        overflow-y: auto;
        overscroll-behavior: contain;
    }
    .fully-booked-attachment-preview {
        min-height: 220px;
        display: flex;
        align-items: flex-start;
        padding: .75rem;
        border: 1px solid #e2e8f0;
        border-radius: .75rem;
        background: #f8fafc;
    }
    .fully-booked-attachment-gallery { display: flex; flex-wrap: wrap; gap: .65rem; }
    .fully-booked-attachment-thumb { position: relative; display: grid; width: 150px; height: 112px; place-items: center; overflow: hidden; padding: .25rem; border: 1px solid #dbe3ee; border-radius: .65rem; color: #64748b; background: #fff; cursor: zoom-in; }
    .fully-booked-attachment-thumb img { width: 100%; height: 100%; border-radius: .4rem; object-fit: cover; }
    .fully-booked-attachment-thumb .fa-magnifying-glass-plus { position: absolute; right: .4rem; bottom: .4rem; display: grid; width: 1.8rem; height: 1.8rem; place-items: center; border-radius: 50%; color: #fff; background: rgba(15, 23, 42, .78); }
    .fully-booked-upload-list { display: flex; flex-wrap: wrap; gap: .65rem; margin-top: .75rem; }
    .fully-booked-viewer { position: relative; width: fit-content; max-width: calc(100vw - 2rem); max-height: calc(100vh - 2rem); padding: 2.75rem 3.5rem 1rem; border: 0; border-radius: 14px; background: #0f172a; box-shadow: 0 24px 70px rgba(0, 0, 0, .4); }
    .fully-booked-viewer::backdrop { background: rgba(15, 23, 42, .88); }
    .fully-booked-viewer img, .fully-booked-viewer iframe { display: block; width: auto; max-width: calc(100vw - 9rem); height: auto; max-height: calc(100vh - 6rem); border: 0; object-fit: contain; background: #fff; }
    .fully-booked-viewer iframe { width: min(1000px, calc(100vw - 9rem)); height: min(800px, calc(100vh - 6rem)); }
    .fully-booked-viewer [hidden] { display: none !important; }
    .fully-booked-viewer-close, .fully-booked-viewer-nav { position: absolute; z-index: 1; display: grid; width: 2.5rem; height: 2.5rem; place-items: center; border: 0; border-radius: 50%; color: #fff; background: rgba(255, 255, 255, .18); }
    .fully-booked-viewer-close { top: .5rem; right: .5rem; }
    .fully-booked-viewer-nav { top: 50%; transform: translateY(-50%); }
    .fully-booked-viewer-previous { left: .5rem; }
    .fully-booked-viewer-next { right: .5rem; }
    .fully-booked-viewer-close:hover, .fully-booked-viewer-nav:hover { background: #2563eb; }
    .fully-booked-viewer-count { position: absolute; top: 1.25rem; left: 50%; color: #fff; font-size: .85rem; transform: translateX(-50%); }
    .fully-booked-tracking-items {
        max-height: min(52vh, 420px);
        overflow-x: hidden;
        overflow-y: auto;
        overscroll-behavior-y: contain;
        scrollbar-gutter: stable;
        scrollbar-width: thin;
        scrollbar-color: #94a3b8 #f1f5f9;
        border: 1px solid #e2e8f0;
        border-radius: .75rem;
    }
    .fully-booked-tracking-items::-webkit-scrollbar { width: 8px; }
    .fully-booked-tracking-items::-webkit-scrollbar-track { background: #f1f5f9; }
    .fully-booked-tracking-items::-webkit-scrollbar-thumb { border-radius: 999px; background: #94a3b8; }
    .fully-booked-tracking-items::-webkit-scrollbar-thumb:hover { background: #64748b; }
    .fully-booked-tracking-item { display: flex; align-items: center; justify-content: space-between; gap: 1rem; padding: .85rem 1rem; background: #fff; }
    .fully-booked-tracking-item + .fully-booked-tracking-item { border-top: 1px solid #eef2f7; }
    .fully-booked-tracking-item-name { color: #1e293b; font-weight: 700; }
    .fully-booked-tracking-item-id { margin-top: .15rem; color: #64748b; font-size: .78rem; }
    .fully-booked-tracking-item-qty { flex: 0 0 auto; border-radius: 999px; padding: .3rem .65rem; color: #1d4ed8; background: #eff6ff; font-size: .78rem; font-weight: 800; }
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
<div class="workspace-page inventory-entry-page">
    @php
        $isFullyBookedPage = $activityType === 'fully_booked';
        $eventReturnContext = request('return_to') === 'transaction_logs' ? 'transaction_logs' : 'event_inventory';
        $eventCancelUrl = $eventReturnContext === 'transaction_logs'
            ? route('inventory-transactions.index', ['hub_id' => $hubId])
            : route('event-inventory.index', ['hub_id' => $hubId]);
    @endphp
    <header class="page-hero mb-3">
        <div class="page-hero-main">
            <span class="page-hero-icon" aria-hidden="true"><i id="activityHeadingIcon" class="fa-solid {{ $isFullyBookedPage ? 'fa-book-open' : 'fa-people-group' }}"></i></span>
            <div class="page-hero-copy">
                <h2 id="activityHeadingTitle" class="page-hero-title">{{ $isFullyBookedPage ? 'Fully Booked' : 'Event' }}</h2>
                <p id="activityHeadingDescription" class="page-hero-description">{{ $isFullyBookedPage ? 'Review Fully Booked orders and update the selected store inventory.' : 'Record inventory pulled out for off-site events.' }}</p>
            </div>
        </div>
    </header>
    
    <form id="eventFullyBookedForm" method="POST" action="{{ $activityType === 'fully_booked' ? route('fully-booked-orders.store') : route('inventory-transactions.store') }}" enctype="multipart/form-data" data-event-action="{{ route('inventory-transactions.store') }}" data-fully-booked-action="{{ route('fully-booked-orders.store') }}" data-can-manage-event="{{ $canManageEvent ? '1' : '0' }}" data-can-submit-fully-booked="{{ $canSubmitFullyBooked ? '1' : '0' }}" class="card transaction-form-card shadow-sm rounded-4 p-4">
        @csrf
        <input type="hidden" id="activityType" name="activity_type" value="{{ $activityType }}">
        <section id="eventFields" @if(!$canManageEvent || old('activity_type', $activityType) !== 'event') hidden @endif>
            @if($canManageEvent)
                @include('inventory-transactions.forms.bulk-items')
                <input type="hidden" name="type" value="sponsor_workshop">
                <div class="row g-3">
                    <div class="col-md-4"><label class="form-label">Store Hub</label><select name="store_hub_id" class="form-select" required onchange="const url = new URL(window.location.href); url.searchParams.set('hub_id', this.value); window.location.href = url.toString();">
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
                    <div class="col-md-5">Product</div>
                    <div class="col-md-2">Condition</div>
                    <div class="col-md-2">Requested Qty</div>
                    <div class="col-md-2">Actual Released Qty</div>
                    <div class="col-md-1">Action</div>
                </div>
                <div id="sponsorItems">
                    <div class="row g-2 align-items-center sponsor-item py-2">
                        <div class="col-md-5"><input type="search" class="form-control product-search" list="sponsorProductOptions" placeholder="Search product name or Item ID" autocomplete="off" aria-label="Product" required>
                            <input type="hidden" name="items[0][product_id]" class="product-id"></div>
                        <div class="col-md-2"><select name="items[0][stock_condition]" class="form-select" aria-label="Stock condition"><option value="good">Good</option><option value="damaged">Damaged</option></select></div>
                        <div class="col-md-2"><input type="number" name="items[0][pullout_qty]" min="0" step="1" class="form-control" aria-label="Requested Qty (record only)" placeholder="Requested Qty"></div>
                        <div class="col-md-2"><input type="number" name="items[0][quantity]" min="1" class="form-control" aria-label="Actual Released Qty" placeholder="Actual Released Qty" required></div>
                        <div class="col-md-1"><button type="button" class="btn btn-outline-danger remove-sponsor-item" aria-label="Remove item" disabled><i class="fa-solid fa-trash"></i></button></div>
                    </div>
                </div>
                <div class="row g-3 mt-2">
                    <div class="col-md-4"><label class="form-label">Reference Document No.</label><input name="reference" class="form-control" value="{{ old('reference', $sponsorReference) }}" readonly aria-readonly="true"></div>
                    <div class="col-md-8"><label class="form-label">Remarks</label><textarea name="notes" class="form-control" rows="2" maxlength="150"></textarea></div>
                </div>
                <div class="d-flex justify-content-end gap-2 mt-4">
                    <button class="btn btn-primary"><i class="fa-solid fa-check me-1" aria-hidden="true"></i>Save Event Items</button>
                    <a href="{{ $eventCancelUrl }}" class="btn btn-outline-secondary">Cancel</a>
                </div>
            @endif
        </section>
        @if($canUseFullyBookedForm)
        <section id="fullyBookedFields" @if(old('activity_type', $activityType) !== 'fully_booked') hidden @endif>
            @if($canSubmitFullyBooked)
            <input type="hidden" name="inventory_hub_id" value="{{ $hubId }}">
            <div class="row g-3">
                <div class="col-md-8">
                    <label class="form-label fw-semibold" for="fullyBookedOrderNumber">Fully Booked Order Number</label>
                    <input id="fullyBookedOrderNumber" class="form-control" value="Generated automatically when submitted" readonly>
                </div>
                <div class="col-md-8">
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
                    <label class="form-label fw-semibold" for="fullyBookedAttachment">Order Attachments</label>
                    <input id="fullyBookedAttachment" name="attachments[]" type="file" class="form-control" accept="image/jpeg,image/png,image/webp,application/pdf" multiple required>
                    <div class="form-text">Upload up to 10 JPEG, PNG, WebP, or PDF files (maximum 10 MB each). Your order number will be generated automatically.</div>
                    <div id="fullyBookedUploadList" class="fully-booked-upload-list" aria-live="polite"></div>
                </div>
                <div class="col-12">
                    <label class="form-label fw-semibold" for="fullyBookedRemarks">Remarks</label>
                    <textarea id="fullyBookedRemarks" name="remarks" class="form-control" rows="3" maxlength="150" placeholder="Add any details or instructions for Inventory Staff">{{ old('remarks') }}</textarea>
                </div>
            </div>
            <div class="d-flex justify-content-end gap-2 mt-4">
                <button id="submitFullyBooked" class="btn btn-primary"><i class="fa-solid fa-paper-plane me-1" aria-hidden="true"></i>Send for Review</button>
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
    <dialog class="fully-booked-viewer" id="fullyBookedAttachmentViewer" aria-label="Fully Booked attachment preview">
        <button type="button" class="fully-booked-viewer-close" aria-label="Close attachment preview"><i class="fa-solid fa-xmark"></i></button>
        <button type="button" class="fully-booked-viewer-nav fully-booked-viewer-previous" aria-label="Previous attachment"><i class="fa-solid fa-chevron-left"></i></button>
        <span class="fully-booked-viewer-count" aria-live="polite"></span>
        <img alt="" hidden>
        <iframe title="Fully Booked attachment preview" hidden></iframe>
        <button type="button" class="fully-booked-viewer-nav fully-booked-viewer-next" aria-label="Next attachment"><i class="fa-solid fa-chevron-right"></i></button>
    </dialog>
    @if($canSubmitFullyBooked && $fullyBookedOrders->isNotEmpty())
        <section class="mt-4">
            <div class="d-flex flex-wrap justify-content-between align-items-end gap-2 mb-3">
                <div>
                    <div class="small text-uppercase fw-bold text-primary">Order tracking</div>
                    <h5 class="fw-bold mb-0">My Fully Booked Orders</h5>
                </div>
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
                                <span class="badge rounded-pill {{ $order->status === 'rejected' ? 'text-bg-danger' : ($order->pulled_out_at ? 'text-bg-success' : 'text-bg-warning') }}">
                                    {{ $order->status === 'rejected' ? 'Rejected' : ($order->pulled_out_at ? 'Stock updated' : 'Pending inventory pull-out') }}
                                </span>
                            </div>
                            @if($order->status === 'rejected')
                                <div class="small text-muted mt-2">
                                    Rejected by {{ $order->reviewer?->name ?? 'Inventory staff' }}{{ $order->reviewed_at ? ' · '.$order->reviewed_at->format('M d, Y h:i A') : '' }}
                                </div>
                                @if($order->rejection_reason)
                                    <div class="small text-danger mt-2"><strong>Reason:</strong> {{ $order->rejection_reason }}</div>
                                @endif
                            @elseif($order->pulled_out_at)
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
                            <div class="fully-booked-attachment-gallery mt-3" aria-label="Attachments for {{ $order->order_number }}">
                                @foreach($order->attachments as $attachment)
                                    @php($attachmentUrl = route('inventory-transactions.fully-booked.attachment', ['fullyBookedOrder' => $order, 'attachment_id' => $attachment->id]))
                                    <div>
                                        <button type="button" class="fully-booked-attachment-thumb" data-fully-booked-preview data-preview-url="{{ $attachmentUrl }}" data-preview-type="{{ str_starts_with($attachment->mime_type, 'image/') ? 'image' : 'pdf' }}" data-preview-title="{{ $attachment->original_filename }}" aria-label="Magnify {{ $attachment->original_filename }}">
                                            @if(str_starts_with($attachment->mime_type, 'image/'))
                                                <img src="{{ $attachmentUrl }}" alt="" loading="lazy">
                                            @else
                                                <i class="fa-solid fa-file-pdf fa-2x text-danger" aria-hidden="true"></i>
                                            @endif
                                            <i class="fa-solid fa-magnifying-glass-plus" aria-hidden="true"></i>
                                        </button>
                                        <div class="d-flex align-items-center justify-content-between gap-1 mt-1">
                                            <span class="small text-muted text-truncate" style="max-width:115px" title="{{ $attachment->original_filename }}">{{ $attachment->original_filename }}</span>
                                            <a class="small text-nowrap" href="{{ route('inventory-transactions.fully-booked.attachment', ['fullyBookedOrder' => $order, 'attachment_id' => $attachment->id, 'download' => 1]) }}"><i class="fa-solid fa-download" aria-hidden="true"></i><span class="visually-hidden">Download {{ $attachment->original_filename }}</span></a>
                                        </div>
                                    </div>
                                @endforeach
                            </div>
                            <div class="d-flex gap-2 mt-3">
                                @if($order->pulled_out_at && $order->items->isNotEmpty())
                                    <button type="button" class="btn btn-sm btn-primary" data-bs-toggle="modal" data-bs-target="#fullyBookedItemsModal{{ $order->id }}"><i class="fa-solid fa-boxes-stacked me-1"></i>View Items</button>
                                @endif
                                <div class="fully-booked-attachment-gallery" aria-label="Order attachments">
                                    @foreach($order->attachments as $attachment)
                                        @php($attachmentUrl = route('inventory-transactions.fully-booked.attachment', ['fullyBookedOrder' => $order, 'attachment_id' => $attachment->id]))
                                        <div>
                                            <button type="button" class="fully-booked-attachment-thumb" data-fully-booked-preview data-preview-url="{{ $attachmentUrl }}" data-preview-type="{{ str_starts_with($attachment->mime_type, 'image/') ? 'image' : 'pdf' }}" data-preview-title="{{ $attachment->original_filename }}" aria-label="Magnify {{ $attachment->original_filename }}">
                                                @if(str_starts_with($attachment->mime_type, 'image/'))
                                                    <img src="{{ $attachmentUrl }}" alt="" loading="lazy">
                                                @else
                                                    <i class="fa-solid fa-file-pdf fa-2x text-danger" aria-hidden="true"></i>
                                                @endif
                                                <i class="fa-solid fa-magnifying-glass-plus" aria-hidden="true"></i>
                                            </button>
                                            <div class="small text-muted text-truncate mt-1" style="max-width:150px" title="{{ $attachment->original_filename }}">{{ $attachment->original_filename }}</div>
                                        </div>
                                    @endforeach
                                </div>
                            </div>
                        </article>

                        @if($order->pulled_out_at && $order->items->isNotEmpty())
                            <div class="modal fade" id="fullyBookedItemsModal{{ $order->id }}" tabindex="-1" aria-labelledby="fullyBookedItemsModalTitle{{ $order->id }}" aria-hidden="true">
                                <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable">
                                    <div class="modal-content">
                                        <div class="modal-header">
                                            <div>
                                                <div class="small text-uppercase fw-bold text-primary">Fully Booked order</div>
                                                <h5 class="modal-title fw-bold" id="fullyBookedItemsModalTitle{{ $order->id }}">Items for {{ $order->order_number }}</h5>
                                            </div>
                                            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                        </div>
                                        <div class="modal-body">
                                            <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
                                                <div class="small text-muted">{{ $order->items->count() }} item line(s) Â· {{ $order->items->sum('quantity') }} total quantity</div>
                                                <span class="badge rounded-pill text-bg-success">Stock updated</span>
                                            </div>
                                            <div class="fully-booked-tracking-items" aria-label="Items pulled out for {{ $order->order_number }}">
                                                @foreach($order->items->sortBy('item_id', SORT_NATURAL | SORT_FLAG_CASE) as $item)
                                                    <div class="fully-booked-tracking-item">
                                                        <div>
                                                            <div class="fully-booked-tracking-item-name">{{ $item->product_name }}</div>
                                                            <div class="fully-booked-tracking-item-id">Item ID: {{ $item->item_id ?? 'â€”' }}</div>
                                                        </div>
                                                        <span class="fully-booked-tracking-item-qty">Qty {{ $item->quantity }}</span>
                                                    </div>
                                                @endforeach
                                            </div>
                                            <div class="small text-muted mt-3"><i class="fa-solid fa-clock-rotate-left me-1"></i>Completed {{ $order->pulled_out_at->format('M d, Y h:i A') }}</div>
                                        </div>
                                        <div class="modal-footer">
                                            <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Close</button>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        @endif
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
                <span class="badge rounded-pill text-bg-warning">{{ $fullyBookedOrders->count() }} pending</span>
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
                                <div class="fully-booked-attachment-gallery" aria-label="Order attachments for {{ $order->order_number }}">
                                    @foreach($order->attachments as $attachment)
                                        @php($attachmentUrl = route('inventory-transactions.fully-booked.attachment', ['fullyBookedOrder' => $order, 'attachment_id' => $attachment->id]))
                                        <div>
                                            <button type="button" class="fully-booked-attachment-thumb" data-fully-booked-preview data-preview-url="{{ $attachmentUrl }}" data-preview-type="{{ str_starts_with($attachment->mime_type, 'image/') ? 'image' : 'pdf' }}" data-preview-title="{{ $attachment->original_filename }}" aria-label="Magnify {{ $attachment->original_filename }}">
                                                @if(str_starts_with($attachment->mime_type, 'image/'))
                                                    <img src="{{ $attachmentUrl }}" alt="" loading="lazy">
                                                @else
                                                    <i class="fa-solid fa-file-pdf fa-2x text-danger" aria-hidden="true"></i>
                                                @endif
                                                <i class="fa-solid fa-magnifying-glass-plus" aria-hidden="true"></i>
                                            </button>
                                            <div class="d-flex align-items-center justify-content-between gap-1 mt-1">
                                                <span class="small text-muted text-truncate" style="max-width:115px" title="{{ $attachment->original_filename }}">{{ $attachment->original_filename }}</span>
                                                <a class="small text-nowrap" href="{{ route('inventory-transactions.fully-booked.attachment', ['fullyBookedOrder' => $order, 'attachment_id' => $attachment->id, 'download' => 1]) }}"><i class="fa-solid fa-download" aria-hidden="true"></i><span class="visually-hidden">Download {{ $attachment->original_filename }}</span></a>
                                            </div>
                                        </div>
                                    @endforeach
                                </div>
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
                                                <div class="product-search-results list-group" role="listbox" hidden></div>
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
                                        <button type="button" class="btn btn-sm btn-outline-success export-fully-booked-xlsx" data-export-url="{{ route('inventory-transactions.fully-booked.export-worksheet', $order) }}"><i class="fa-solid fa-file-excel me-1"></i>Export Excel</button>
                                    </div>
                                    <div class="d-flex flex-wrap gap-2">
                                        <button type="button" class="btn btn-outline-danger" data-bs-toggle="modal" data-bs-target="#rejectFullyBookedOrder{{ $order->id }}"><i class="fa-solid fa-circle-xmark me-1"></i>Reject Order</button>
                                        <button class="btn btn-success"><i class="fa-solid fa-circle-check me-1"></i>Complete Order & Update Stock</button>
                                    </div>
                                </div>
                            </form>
                        </div>
                    </div>
                </article>
                <div class="modal fade" id="rejectFullyBookedOrder{{ $order->id }}" tabindex="-1" aria-labelledby="rejectFullyBookedOrderTitle{{ $order->id }}" aria-hidden="true">
                    <div class="modal-dialog modal-dialog-centered">
                        <div class="modal-content">
                            <form method="POST" action="{{ route('inventory-transactions.fully-booked.reject', $order) }}">
                                @csrf
                                @method('PATCH')
                                <div class="modal-header">
                                    <h5 class="modal-title fw-bold" id="rejectFullyBookedOrderTitle{{ $order->id }}">Reject Fully Booked Order</h5>
                                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                </div>
                                <div class="modal-body">
                                    <p class="small text-muted">Reject order <strong>{{ $order->order_number }}</strong>? The submitting staff member will be notified.</p>
                                    <label class="form-label fw-semibold" for="rejectionReason{{ $order->id }}">Reason for rejection</label>
                                    <textarea id="rejectionReason{{ $order->id }}" name="rejection_reason" class="form-control" rows="4" maxlength="2000" required placeholder="Explain why this order is being rejected"></textarea>
                                </div>
                                <div class="modal-footer">
                                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                                    <button type="submit" class="btn btn-danger"><i class="fa-solid fa-circle-xmark me-1"></i>Reject Order</button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
            @empty
                <div class="alert alert-info mb-0">There are no Fully Booked attachments awaiting pull-out.</div>
            @endforelse
        </section>
    @endif
</div>
@endsection

@push('scripts')
<script src="{{ asset('js/product-suggestions.js') }}"></script>
<script src="{{ asset('js/fully-booked-pullout.js') }}?v={{ filemtime(public_path('js/fully-booked-pullout.js')) }}"></script>
<script>
(() => {
    const container = document.getElementById('sponsorItems');
    const addButton = document.getElementById('addSponsorItem');
    if (!container || !addButton) return;
    const productOptions = @json($sponsorProductOptions);
    const dataList = document.createElement('datalist');
    dataList.id = 'sponsorProductOptions';
    const stockForCondition = (product, condition) => condition === 'damaged'
        ? Number(product.damaged_stock_available ?? product.damaged_stock ?? 0)
        : Number(product.good_stock_available ?? product.stock ?? 0);
    const labelForCondition = (product, condition) =>
        window.transactionProductLabel(product, true, condition);
    const updateSuggestions = row => {
        const condition = row.querySelector('[name$="[stock_condition]"]').value;
        dataList.replaceChildren();
        productOptions
            .filter(product => stockForCondition(product, condition) > 0)
            .forEach(product => {
                const option = document.createElement('option');
                option.value = labelForCondition(product, condition);
                dataList.appendChild(option);
            });
    };
    document.body.appendChild(dataList);
    setupTransactionSuggestions(
        container,
        dataList,
        productOptions,
        @json(route('hub.products.search.ajax', $hubId ?? 0)),
        true,
        (product, input) => labelForCondition(
            product,
            input.closest('.sponsor-item').querySelector('[name$="[stock_condition]"]').value
        ),
        (product, input) => stockForCondition(
            product,
            input.closest('.sponsor-item').querySelector('[name$="[stock_condition]"]').value
        ) > 0
    );
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
        const condition = row.querySelector('[name$="[stock_condition]"]');
        input.addEventListener('input', () => {
            const value = input.value.trim().toLowerCase();
            const product = productOptions.find(item =>
                labelForCondition(item, condition.value).toLowerCase() === value
            );
            hidden.value = product ? product.id : '';
            input.setCustomValidity(product ? '' : 'Select a product from the search suggestions.');
        });
    };

    bindProductSearch(container.querySelector('.sponsor-item'));
    container.addEventListener('focusin', event => {
        if (!event.target.matches('.product-search')) return;
        updateSuggestions(event.target.closest('.sponsor-item'));
    });
    container.addEventListener('change', event => {
        if (!event.target.matches('[name$="[stock_condition]"]')) return;
        const row = event.target.closest('.sponsor-item');
        const input = row.querySelector('.product-search');
        const productId = row.querySelector('.product-id').value;
        const product = productOptions.find(item => String(item.id) === productId)
            || window.transactionProducts.get(String(productId));
        if (product) input.value = labelForCondition(product, event.target.value);
        updateSuggestions(row);
    });
    addButton.addEventListener('click', () => {
        const row = document.createElement('div');
        row.className = 'row g-2 align-items-center sponsor-item py-2';
        row.innerHTML = `<div class="col-md-5"><input type="search" class="form-control product-search" list="sponsorProductOptions" placeholder="Search product name or Item ID" autocomplete="off" aria-label="Product" required>
            <input type="hidden" name="items[${itemIndex}][product_id]" class="product-id"></div>
            <div class="col-md-2"><select name="items[${itemIndex}][stock_condition]" class="form-select" aria-label="Stock condition"><option value="good">Good</option><option value="damaged">Damaged</option></select></div>
            <div class="col-md-2"><input type="number" name="items[${itemIndex}][pullout_qty]" min="0" step="1" class="form-control" aria-label="Requested Qty (record only)" placeholder="Requested Qty"></div>
            <div class="col-md-2"><input type="number" name="items[${itemIndex}][quantity]" min="1" class="form-control" aria-label="Actual Released Qty" placeholder="Actual Released Qty" required></div>
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
        if (headingTitle) headingTitle.textContent = isFullyBooked ? 'Fully Booked' : 'Event';
        if (headingDescription) {
            headingDescription.textContent = isFullyBooked
                ? 'Review Fully Booked orders and update the selected store inventory.'
                : 'Record inventory pulled out for off-site events.';
        }
        if (headingIcon) {
            headingIcon.classList.toggle('fa-book-open', isFullyBooked);
            headingIcon.classList.toggle('fa-people-group', !isFullyBooked);
        }
    };

    selector.addEventListener('change', updateActivityType);
    form.addEventListener('submit', event => {
        if (form.dataset.canManageEvent === '1'
            && form.dataset.canSubmitFullyBooked !== '1'
            && selector.value === 'fully_booked') {
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
    const uploadList = document.getElementById('fullyBookedUploadList');
    const viewer = document.getElementById('fullyBookedAttachmentViewer');
    const viewerImage = viewer?.querySelector('img');
    const viewerFrame = viewer?.querySelector('iframe');
    const viewerCount = viewer?.querySelector('.fully-booked-viewer-count');
    const viewerPrevious = viewer?.querySelector('.fully-booked-viewer-previous');
    const viewerNext = viewer?.querySelector('.fully-booked-viewer-next');
    const viewerClose = viewer?.querySelector('.fully-booked-viewer-close');
    let uploadPreviewUrls = [];
    let activePreviewButtons = [];
    let activePreviewIndex = 0;
    const renderAttachmentPreview = index => {
        if (!activePreviewButtons.length) return;
        activePreviewIndex = (index + activePreviewButtons.length) % activePreviewButtons.length;
        const button = activePreviewButtons[activePreviewIndex];
        const isPdf = button.dataset.previewType === 'pdf';
        viewerImage.hidden = isPdf;
        viewerFrame.hidden = !isPdf;
        viewerCount.textContent = `${activePreviewIndex + 1} / ${activePreviewButtons.length} · ${button.dataset.previewTitle}`;
        viewerPrevious.hidden = activePreviewButtons.length < 2;
        viewerNext.hidden = activePreviewButtons.length < 2;
        if (isPdf) {
            viewerImage.removeAttribute('src');
            viewerFrame.src = button.dataset.previewUrl;
        } else {
            viewerFrame.removeAttribute('src');
            viewerImage.src = button.dataset.previewUrl;
            viewerImage.alt = button.dataset.previewTitle;
        }
    };
    document.addEventListener('click', event => {
        const trigger = event.target.closest('[data-fully-booked-preview]');
        if (!trigger || !viewer) return;
        activePreviewButtons = [...trigger.closest('.fully-booked-attachment-gallery').querySelectorAll('[data-fully-booked-preview]')];
        renderAttachmentPreview(activePreviewButtons.indexOf(trigger));
        viewer.showModal();
    });
    viewerClose?.addEventListener('click', () => viewer.close());
    viewerPrevious?.addEventListener('click', () => renderAttachmentPreview(activePreviewIndex - 1));
    viewerNext?.addEventListener('click', () => renderAttachmentPreview(activePreviewIndex + 1));
    viewer?.addEventListener('click', event => {
        if (event.target === viewer) viewer.close();
    });
    viewer?.addEventListener('keydown', event => {
        if (event.key === 'ArrowLeft' && activePreviewButtons.length > 1) renderAttachmentPreview(activePreviewIndex - 1);
        if (event.key === 'ArrowRight' && activePreviewButtons.length > 1) renderAttachmentPreview(activePreviewIndex + 1);
    });
    viewer?.addEventListener('close', () => {
        viewerImage.removeAttribute('src');
        viewerImage.alt = '';
        viewerFrame.removeAttribute('src');
        activePreviewButtons = [];
    });
    const clearUploadPreviews = () => {
        uploadPreviewUrls.forEach(url => URL.revokeObjectURL(url));
        uploadPreviewUrls = [];
        uploadList?.replaceChildren();
    };
    fileInput?.addEventListener('change', () => {
        clearUploadPreviews();
        const files = [...(fileInput.files || [])];
        if (!files.length) return;
        if (files.length > 10) {
            fileInput.setCustomValidity('Choose no more than 10 attachments.');
            fileInput.reportValidity();
            fileInput.value = '';
            return;
        }
        const validFiles = files.every(file => {
            const extension = file.name.toLowerCase().split('.').pop();
            const isImage = ['image/jpeg', 'image/png', 'image/webp'].includes(file.type)
                || ['jpg', 'jpeg', 'png', 'webp'].includes(extension);
            const isPdf = file.type === 'application/pdf' || extension === 'pdf';
            return file.size <= 10 * 1024 * 1024 && (isImage || isPdf);
        });
        if (!validFiles) {
            fileInput.setCustomValidity('Choose JPEG, PNG, WebP, or PDF attachments no larger than 10 MB each.');
            fileInput.reportValidity();
            fileInput.value = '';
            return;
        }
        fileInput.setCustomValidity('');
        const gallery = document.createElement('div');
        gallery.className = 'fully-booked-attachment-gallery';
        uploadList.appendChild(gallery);
        files.forEach(file => {
            const url = URL.createObjectURL(file);
            uploadPreviewUrls.push(url);
            const isPdf = file.type === 'application/pdf' || file.name.toLowerCase().endsWith('.pdf');
            const item = document.createElement('div');
            const button = document.createElement('button');
            button.type = 'button';
            button.className = 'fully-booked-attachment-thumb';
            button.dataset.fullyBookedPreview = '';
            button.dataset.previewUrl = url;
            button.dataset.previewType = isPdf ? 'pdf' : 'image';
            button.dataset.previewTitle = file.name;
            button.setAttribute('aria-label', `Magnify ${file.name}`);
            if (isPdf) {
                const icon = document.createElement('i');
                icon.className = 'fa-solid fa-file-pdf fa-2x text-danger';
                icon.setAttribute('aria-hidden', 'true');
                button.appendChild(icon);
            } else {
                const image = document.createElement('img');
                image.src = url;
                image.alt = '';
                button.appendChild(image);
            }
            const magnifier = document.createElement('i');
            magnifier.className = 'fa-solid fa-magnifying-glass-plus';
            magnifier.setAttribute('aria-hidden', 'true');
            button.appendChild(magnifier);
            const filename = document.createElement('div');
            filename.className = 'small text-muted text-truncate mt-1';
            filename.style.maxWidth = '150px';
            filename.title = file.name;
            filename.textContent = file.name;
            item.append(button, filename);
            gallery.appendChild(item);
        });
    });
    window.addEventListener('beforeunload', () => {
        clearUploadPreviews();
    });
})();
</script>
@endpush
