@extends('layouts.app')

@section('content')
<style>
    .event-ledger-card { border: 1px solid #e2e8f0; }
    .event-ledger-kpi { min-width: 105px; padding: .7rem .85rem; border: 1px solid #e2e8f0; border-radius: .75rem; background: #f8fafc; }
    .event-ledger-kpi .label { color: #64748b; font-size: .68rem; font-weight: 800; letter-spacing: .05em; text-transform: uppercase; }
    .event-ledger-kpi .value { color: #0f172a; font-size: 1.2rem; font-weight: 800; }
    .event-ledger-table th { position: sticky; top: 0; z-index: 1; background: #f8f9fa; white-space: nowrap; color: #64748b; font-size: .72rem; text-transform: uppercase; }
    .event-ledger-table td { vertical-align: middle; }
    .event-ledger-product-search { max-width: 420px; }
    .event-ledger-items { max-height: min(48vh, 430px); overflow: auto; scrollbar-gutter: stable; }
    .event-ledger-items tr[hidden] { display: none; }
    .event-workspace-tabs { border: 1px solid #dbeafe; border-radius: .9rem; background: #f8fafc; padding: .35rem; }
    .event-workspace-tabs .nav-link { border-radius: .7rem; color: #475569; font-weight: 700; }
    .event-workspace-tabs .nav-link.active { background: #2563eb; color: #fff; box-shadow: 0 4px 12px rgba(37, 99, 235, .2); }
</style>

<div class="workspace-page event-inventory-page">
    <x-page-header class="mb-3" eyebrow="Off-site inventory" title="Event Inventory" description="Track pulled-out stock, Event sales, returns, and closeout shortages without deducting Head Office stock twice." icon="fa-tents" />

    <div class="d-flex flex-wrap gap-2 mb-3">
        <a href="{{ route('inventory-transactions.sponsor.create', ['hub_id' => $hubId]) }}" class="btn btn-primary"><i class="fa-solid fa-plus me-1"></i>New Event Pull-Out</a>
        <button type="button" class="btn btn-success" data-bs-toggle="modal" data-bs-target="#recordEventSaleModal" @disabled($saleEvents->isEmpty())><i class="fa-solid fa-cart-plus me-1"></i>Record Event Sale</button>
        <a href="{{ route('inventory-transactions.return.create', ['hub_id' => $hubId]) }}" class="btn btn-outline-primary"><i class="fa-solid fa-rotate-left me-1"></i>Return Items</a>
    </div>

    <nav class="nav nav-pills event-workspace-tabs mb-3" aria-label="Event Inventory sections">
        <a class="nav-link flex-fill text-center {{ $section === 'overview' ? 'active' : '' }}" href="{{ route('event-inventory.index', ['hub_id' => $hubId, 'status' => $status, 'section' => 'overview']) }}"><i class="fa-solid fa-boxes-stacked me-1"></i>Inventory Overview</a>
        <a class="nav-link flex-fill text-center {{ $section === 'transactions' ? 'active' : '' }}" href="{{ route('event-inventory.index', ['hub_id' => $hubId, 'section' => 'transactions']) }}"><i class="fa-solid fa-clock-rotate-left me-1"></i>Event Transaction Logs</a>
    </nav>

    @if($section === 'overview')
    <div class="card border-0 shadow-sm rounded-4 p-3 mb-3">
        <form class="row g-2 align-items-end">
            <input type="hidden" name="section" value="overview">
            <div class="col-md-4">
                <label class="small fw-bold text-muted">STORE HUB</label>
                <select name="hub_id" class="form-select">
                    @foreach($hubs as $hub)
                        <option value="{{ $hub->id }}" @selected((int) $hubId === (int) $hub->id)>{{ $hub->name }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-3">
                <label class="small fw-bold text-muted">STATUS</label>
                <select name="status" class="form-select">
                    <option value="open" @selected($status === 'open')>Open events</option>
                    <option value="closed" @selected($status === 'closed')>Closed events</option>
                    <option value="all" @selected($status === 'all')>All events</option>
                </select>
            </div>
            <div class="col-md-3">
                <label class="small fw-bold text-muted">REFERENCE OR EVENT</label>
                <input name="search" value="{{ request('search') }}" class="form-control" placeholder="Search">
            </div>
            <div class="col-md-2"><button class="btn btn-primary w-100"><i class="fa-solid fa-filter me-1"></i>Filter</button></div>
        </form>
    </div>

    @forelse($events as $event)
        <article class="card event-ledger-card border-0 shadow-sm rounded-4 mb-3 overflow-hidden">
            <div class="p-3 p-lg-4 border-bottom">
                <div class="d-flex flex-wrap justify-content-between gap-3">
                    <div>
                        <div class="d-flex flex-wrap align-items-center gap-2 mb-1">
                            <h4 class="mb-0">{{ $event->source }}</h4>
                            <span class="badge rounded-pill {{ $event->status === 'closed' ? 'text-bg-secondary' : 'text-bg-success' }}">{{ strtoupper($event->status) }}</span>
                        </div>
                        <div class="text-muted"><strong>{{ $event->reference }}</strong> &middot; {{ $event->storeHub?->name ?? 'Unknown hub' }} &middot; {{ $event->occurred_on?->format('M d, Y') }}</div>
                        <div class="small text-muted mt-1">Created by {{ $event->creator?->name ?? $event->creator?->username ?? 'System' }}@if($event->closed_at) &middot; Closed {{ $event->closed_at->format('M d, Y h:i A') }}@endif</div>
                    </div>
                    <div class="d-flex flex-wrap gap-2">
                        @foreach([
                            ['Product Items', $event->product_item_count],
                            ['Pulled Items', $event->pulled_total],
                            ['Sold Items', $event->sold_total],
                            ['Returned Items', $event->returned_total],
                            ['Remaining Items', $event->remaining_total],
                            ['Missing Items', $event->missing_total],
                        ] as [$label, $value])
                            <div class="event-ledger-kpi"><div class="label">{{ $label }}</div><div class="value">{{ $value }}</div></div>
                        @endforeach
                    </div>
                </div>
                @if($event->notes)<div class="small mt-3"><strong>Remarks:</strong> {{ $event->notes }}</div>@endif
            </div>

            <div class="px-3 pt-3">
                <label class="form-label small fw-bold text-muted mb-1" for="eventProductSearch{{ $event->id }}">SEARCH PRODUCT ITEMS</label>
                <div class="input-group event-ledger-product-search">
                    <span class="input-group-text"><i class="fa-solid fa-magnifying-glass" aria-hidden="true"></i></span>
                    <input type="search" id="eventProductSearch{{ $event->id }}" class="form-control event-ledger-product-search-input" placeholder="Search product name or Item ID" autocomplete="off">
                </div>
            </div>
            <div class="table-responsive event-ledger-items">
                <table class="table table-hover mb-0 event-ledger-table">
                    <thead class="table-light"><tr><th>Product</th><th>Pull-Out</th><th>Sold</th><th>Returned</th><th>Remaining</th><th>Missing</th></tr></thead>
                    <tbody>
                    @foreach($event->summary_rows as $row)
                        <tr data-event-product-row data-product-search="{{ strtolower(($row['product']?->name ?? 'Unknown product').' '.($row['product']?->item_id ?? 'N/A')) }}">
                            <td><strong>{{ $row['product']?->name ?? 'Unknown product' }}</strong><div class="small text-muted">{{ $row['product']?->item_id ?? 'N/A' }}</div></td>
                            <td><strong>{{ $row['pulled_total'] }}</strong><div class="small text-muted">Good {{ $row['pulled_good'] }} &middot; Damaged {{ $row['pulled_damaged'] }}</div></td>
                            <td><strong>{{ $row['sold_total'] }}</strong><div class="small text-muted">Good {{ $row['sold_good'] }} &middot; Damaged {{ $row['sold_damaged'] }}</div></td>
                            <td><strong>{{ $row['returned_total'] }}</strong><div class="small text-muted">Good returned {{ $row['returned_good'] }} &middot; Damaged returned {{ $row['returned_damaged'] }}</div></td>
                            <td>
                                <span class="badge {{ $row['remaining'] > 0 ? 'text-bg-warning' : 'text-bg-success' }}">{{ $row['remaining'] }}</span>
                                @if($event->status === 'open')
                                    <div class="small text-muted">Good {{ $row['remaining_good'] }} &middot; Damaged {{ $row['remaining_damaged'] }}</div>
                                @endif
                            </td>
                            <td><span class="badge {{ $row['missing_total'] > 0 ? 'text-bg-danger' : 'text-bg-light' }}">{{ $row['missing_total'] }}</span></td>
                        </tr>
                    @endforeach
                    </tbody>
                </table>
            </div>
            <div class="event-ledger-no-match small text-muted px-3 py-2" hidden>No products match this search.</div>

            @if($event->salesTransactions->isNotEmpty())
                <div class="p-3 border-top bg-light">
                    <div class="fw-semibold mb-2">Event sales</div>
                    <div class="d-flex flex-wrap gap-2">
                        @foreach($event->salesTransactions->sortByDesc('created_at') as $sale)
                            <span class="badge text-bg-light border p-2">{{ $sale->order_number }} &middot; {{ $sale->order_date?->format('M d, Y') }} &middot; {{ $sale->items->sum('quantity') }} item(s) &middot; &#8369;{{ number_format((float) $sale->grand_total, 2) }}</span>
                        @endforeach
                    </div>
                </div>
            @endif

            <div class="p-3 border-top d-flex flex-wrap gap-2">
                @if($event->status === 'open')
                    <form method="POST" action="{{ route('event-inventory.close', $event) }}" class="ms-lg-auto" data-event-close-form>
                        @csrf
                        @method('PATCH')
                        <button class="btn btn-sm btn-outline-danger"><i class="fa-solid fa-lock me-1"></i>Reconcile & Close</button>
                    </form>
                @endif
            </div>
        </article>
    @empty
        <div class="card border-0 shadow-sm rounded-4 p-5 text-center">
            <i class="fa-solid fa-tents fs-1 text-muted mb-3"></i>
            <h4>No {{ $status === 'all' ? '' : $status }} events found</h4>
            <p class="text-muted mb-3">Create an Event Pull-Out to begin tracking off-site stock.</p>
        </div>
    @endforelse

    <div class="modal fade" id="closeEventConfirmModal" tabindex="-1" aria-labelledby="closeEventConfirmTitle" aria-describedby="closeEventConfirmDescription" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content border-0 shadow">
                <div class="modal-header border-0 pb-0">
                    <div class="d-flex align-items-center gap-3">
                        <span class="d-flex align-items-center justify-content-center rounded-circle bg-danger-subtle text-danger" style="width: 44px; height: 44px;">
                            <i class="fa-solid fa-lock" aria-hidden="true"></i>
                        </span>
                        <h5 class="modal-title fw-bold mb-0" id="closeEventConfirmTitle">Close this event?</h5>
                    </div>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cancel"></button>
                </div>
                <div class="modal-body text-secondary pt-3" id="closeEventConfirmDescription">
                    Any remaining quantity will be recorded as missing. After closing, no further sales or returns will be allowed.
                </div>
                <div class="modal-footer border-0 pt-0">
                    <button type="button" class="btn btn-light border" data-bs-dismiss="modal">Cancel</button>
                    <button type="button" class="btn btn-danger" id="confirmCloseEventButton">
                        <i class="fa-solid fa-lock me-1" aria-hidden="true"></i>Close Event
                    </button>
                </div>
            </div>
        </div>
    </div>

    <div class="mt-3">{{ $events->links() }}</div>
    @else
        @include('event-inventory._transactions')
    @endif
</div>

@include('event-inventory._sale-modal')
@endsection

@push('scripts')
<script>
document.querySelectorAll('.event-ledger-product-search-input').forEach(input => {
    input.addEventListener('input', () => {
        const term = input.value.trim().toLowerCase();
        const card = input.closest('.event-ledger-card');
        let visibleCount = 0;

        card.querySelectorAll('[data-event-product-row]').forEach(row => {
            const matches = term === '' || row.dataset.productSearch.includes(term);
            row.hidden = !matches;
            if (matches) visibleCount++;
        });

        card.querySelector('.event-ledger-no-match').hidden = visibleCount > 0;
    });
});

const closeEventModal = document.getElementById('closeEventConfirmModal');
if (closeEventModal && window.bootstrap) {
    let pendingCloseForm = null;
    const modal = bootstrap.Modal.getOrCreateInstance(closeEventModal);

    document.querySelectorAll('[data-event-close-form]').forEach(form => {
        form.addEventListener('submit', event => {
            event.preventDefault();
            pendingCloseForm = form;
            modal.show();
        });
    });

    document.getElementById('confirmCloseEventButton').addEventListener('click', () => {
        if (!pendingCloseForm) return;
        pendingCloseForm.submit();
    });

    closeEventModal.addEventListener('hidden.bs.modal', () => {
        pendingCloseForm = null;
    });
}
</script>
@endpush
