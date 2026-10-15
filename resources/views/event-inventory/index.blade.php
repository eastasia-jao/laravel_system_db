@extends('layouts.app')

@section('content')
<style>
    .event-ledger-card { border: 1px solid #e2e8f0; }
    .event-ledger-kpi { min-width: 105px; padding: .7rem .85rem; border: 1px solid #e2e8f0; border-radius: .75rem; background: #f8fafc; }
    .event-ledger-kpi .label { color: #64748b; font-size: .68rem; font-weight: 800; letter-spacing: .05em; text-transform: uppercase; }
    .event-ledger-kpi .value { color: #0f172a; font-size: 1.2rem; font-weight: 800; }
    .event-ledger-table th { white-space: nowrap; color: #64748b; font-size: .72rem; text-transform: uppercase; }
    .event-ledger-table td { vertical-align: middle; }
    .event-sale-panel { border: 1px solid #bfdbfe; border-radius: .85rem; background: #eff6ff; }
    .event-sale-items { max-height: 340px; overflow-y: auto; }
    .event-sale-row { display: grid; grid-template-columns: minmax(240px, 1.7fr) minmax(120px, .55fr) minmax(125px, .65fr) minmax(140px, .7fr); gap: .75rem; align-items: end; padding: .75rem; border-bottom: 1px solid #dbeafe; }
    .event-sale-row:last-child { border-bottom: 0; }
    @media (max-width: 767.98px) { .event-sale-row { grid-template-columns: 1fr 1fr; } }
</style>

<div class="workspace-page event-inventory-page">
    <x-page-header class="mb-3" eyebrow="Off-site inventory" title="Event Inventory" description="Track pulled-out stock, Event sales, returns, and closeout shortages without deducting Head Office stock twice." icon="fa-tents" />

    <div class="d-flex flex-wrap gap-2 mb-3">
        <a href="{{ route('inventory-transactions.sponsor.create', ['hub_id' => $hubId]) }}" class="btn btn-primary"><i class="fa-solid fa-plus me-1"></i>New Event Pull-Out</a>
        <a href="{{ route('inventory-transactions.return.create', ['hub_id' => $hubId]) }}" class="btn btn-outline-primary"><i class="fa-solid fa-rotate-left me-1"></i>Return Items</a>
        <a href="{{ route('inventory-transactions.index', ['hub_id' => $hubId]) }}" class="btn btn-outline-secondary"><i class="fa-solid fa-clipboard-list me-1"></i>Transaction Logs</a>
    </div>

    <div class="card border-0 shadow-sm rounded-4 p-3 mb-3">
        <form class="row g-2 align-items-end">
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
                            ['Pulled', $event->pulled_total],
                            ['Sold', $event->sold_total],
                            ['Returned', $event->returned_total],
                            ['Remaining', $event->remaining_total],
                            ['Missing', $event->missing_total],
                        ] as [$label, $value])
                            <div class="event-ledger-kpi"><div class="label">{{ $label }}</div><div class="value">{{ $value }}</div></div>
                        @endforeach
                    </div>
                </div>
                @if($event->notes)<div class="small mt-3"><strong>Remarks:</strong> {{ $event->notes }}</div>@endif
            </div>

            <div class="table-responsive">
                <table class="table table-hover mb-0 event-ledger-table">
                    <thead class="table-light"><tr><th>Product</th><th>Pull-Out</th><th>Sold</th><th>Returned</th><th>Remaining</th><th>Missing</th></tr></thead>
                    <tbody>
                    @foreach($event->summary_rows as $row)
                        <tr>
                            <td><strong>{{ $row['product']?->name ?? 'Unknown product' }}</strong><div class="small text-muted">{{ $row['product']?->item_id ?? 'N/A' }}</div></td>
                            <td><strong>{{ $row['pulled_total'] }}</strong><div class="small text-muted">Good {{ $row['pulled_good'] }} &middot; Damaged {{ $row['pulled_damaged'] }}</div></td>
                            <td><strong>{{ $row['sold_total'] }}</strong><div class="small text-muted">Good {{ $row['sold_good'] }} &middot; Damaged {{ $row['sold_damaged'] }}</div></td>
                            <td><strong>{{ $row['returned_total'] }}</strong><div class="small text-muted">Good {{ $row['returned_good'] }} &middot; Damaged {{ $row['returned_damaged'] }}</div></td>
                            <td><span class="badge {{ $row['remaining'] > 0 ? 'text-bg-warning' : 'text-bg-success' }}">{{ $row['remaining'] }}</span></td>
                            <td><span class="badge {{ $row['missing_total'] > 0 ? 'text-bg-danger' : 'text-bg-light' }}">{{ $row['missing_total'] }}</span></td>
                        </tr>
                    @endforeach
                    </tbody>
                </table>
            </div>

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

            @if($event->status === 'open')
                <div class="p-3 border-top d-flex flex-wrap gap-2">
                    <button class="btn btn-sm btn-primary" type="button" data-bs-toggle="collapse" data-bs-target="#eventSale{{ $event->id }}" aria-expanded="false"><i class="fa-solid fa-cart-plus me-1"></i>Record Event Sale</button>
                    <a class="btn btn-sm btn-outline-primary" href="{{ route('inventory-transactions.return.create', ['hub_id' => $event->store_hub_id, 'channel' => 'event', 'event_reference' => $event->reference]) }}"><i class="fa-solid fa-rotate-left me-1"></i>Return Remaining Items</a>
                    <form method="POST" action="{{ route('event-inventory.close', $event) }}" class="ms-lg-auto" onsubmit="return confirm('Close this event? Any remaining quantity will be recorded as missing and no further sales or returns will be allowed.');">
                        @csrf
                        @method('PATCH')
                        <button class="btn btn-sm btn-outline-danger"><i class="fa-solid fa-lock me-1"></i>Reconcile & Close</button>
                    </form>
                </div>

                <div class="collapse p-3 pt-0" id="eventSale{{ $event->id }}">
                    <form method="POST" action="{{ route('event-inventory.sales.store', $event) }}" class="event-sale-panel p-3">
                        @csrf
                        <div class="d-flex justify-content-between align-items-start gap-2 mb-3">
                            <div><h5 class="mb-1">Record Event Sale</h5><div class="small text-muted">This reduces Event stock only. Head Office physical stock will not be deducted again.</div></div>
                        </div>
                        <div class="row g-3 mb-3">
                            <div class="col-md-3"><label class="form-label">Sale Date</label><input type="date" name="order_date" value="{{ now()->toDateString() }}" class="form-control" required></div>
                            <div class="col-md-3"><label class="form-label">Customer</label><input name="customer_name" class="form-control" placeholder="Optional"></div>
                            <div class="col-md-3"><label class="form-label">Payment Method</label><input name="mode_of_payment" class="form-control" placeholder="Cash, GCash, etc."></div>
                            <div class="col-md-3"><label class="form-label">Notes</label><input name="note" class="form-control" maxlength="2000"></div>
                        </div>
                        <div class="event-sale-items bg-white rounded-3 border">
                            @php
                                $saleIndex = 0;
                            @endphp
                            @foreach($event->summary_rows as $row)
                                @foreach($row['lines'] as $line)
                                    @php
                                        $conditionSold = $line->stock_condition === 'damaged' ? $row['sold_damaged'] : $row['sold_good'];
                                        $conditionReturned = $line->stock_condition === 'damaged' ? $row['returned_damaged'] : $row['returned_good'];
                                        $available = max(0, min($row['remaining'], $line->quantity - $conditionSold - $conditionReturned));
                                    @endphp
                                    @if($available > 0)
                                        <div class="event-sale-row">
                                            <div><strong>{{ $line->product?->name }}</strong><div class="small text-muted">{{ $line->product?->item_id }} &middot; {{ ucfirst($line->stock_condition) }} &middot; {{ $available }} available</div><input type="hidden" name="items[{{ $saleIndex }}][event_pullout_item_id]" value="{{ $line->id }}"></div>
                                            <div><label class="form-label">Condition</label><input class="form-control" value="{{ ucfirst($line->stock_condition) }}" readonly></div>
                                            <div><label class="form-label">Quantity</label><input type="number" name="items[{{ $saleIndex }}][quantity]" min="0" max="{{ $available }}" value="0" class="form-control"></div>
                                            <div><label class="form-label">Unit Price</label><input type="number" name="items[{{ $saleIndex }}][unit_price]" min="0" step="0.01" value="{{ number_format((float) ($line->product?->sales_price ?? 0), 2, '.', '') }}" class="form-control" required></div>
                                        </div>
                                        @php
                                            $saleIndex++;
                                        @endphp
                                    @endif
                                @endforeach
                            @endforeach
                            @if($saleIndex === 0)<div class="alert alert-info m-3 mb-0">No Event stock remains available for sale.</div>@endif
                        </div>
                        <div class="d-flex justify-content-end mt-3"><button class="btn btn-primary" @disabled($saleIndex === 0)><i class="fa-solid fa-check me-1"></i>Save Event Sale</button></div>
                    </form>
                </div>
            @endif
        </article>
    @empty
        <div class="card border-0 shadow-sm rounded-4 p-5 text-center">
            <i class="fa-solid fa-tents fs-1 text-muted mb-3"></i>
            <h4>No {{ $status === 'all' ? '' : $status }} events found</h4>
            <p class="text-muted mb-3">Create an Event Pull-Out to begin tracking off-site stock.</p>
            <div><a href="{{ route('inventory-transactions.sponsor.create', ['hub_id' => $hubId]) }}" class="btn btn-primary">Create Event Pull-Out</a></div>
        </div>
    @endforelse

    <div class="mt-3">{{ $events->links() }}</div>
</div>
@endsection
