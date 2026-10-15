@php
    $eventActivityLabels = [
        'sponsor_workshop' => ['Pull-Out', 'text-bg-primary', 'Stock entered Event inventory'],
        'sold' => ['Event Sale', 'text-bg-success', 'Stock sold from Event inventory'],
        'return' => ['Return', 'text-bg-info', 'Stock returned to Head Office'],
        'event_variance' => ['Missing', 'text-bg-danger', 'Shortage recorded at closeout'],
    ];
@endphp
<style>
    .event-log-table th { color: #64748b; font-size: .72rem; letter-spacing: .04em; text-transform: uppercase; white-space: nowrap; }
    .event-log-table td { vertical-align: middle; }
    .event-log-product + .event-log-product { border-top: 1px dashed #cbd5e1; margin-top: .55rem; padding-top: .55rem; }
    .event-log-condition { font-size: .7rem; }
</style>

<div class="card border-0 shadow-sm rounded-4 p-3 mb-3">
    <form class="row g-2 align-items-end" action="{{ route('event-inventory.index') }}">
        <input type="hidden" name="section" value="transactions">
        <div class="col-lg-2 col-md-4"><label class="form-label small fw-bold text-muted">STORE HUB</label><select name="hub_id" class="form-select"><option value="">All active hubs</option>@foreach($hubs as $hub)<option value="{{ $hub->id }}" @selected((int) $hubId === (int) $hub->id)>{{ $hub->name }}</option>@endforeach</select></div>
        <div class="col-lg-3 col-md-4"><label class="form-label small fw-bold text-muted">EVENT</label><select name="event_id" class="form-select"><option value="">All events</option>@foreach($eventOptions as $event)<option value="{{ $event->id }}" @selected((int) request('event_id') === (int) $event->id)>{{ $event->source }} — {{ $event->reference }}</option>@endforeach</select></div>
        <div class="col-lg-2 col-md-4"><label class="form-label small fw-bold text-muted">ACTIVITY</label><select name="activity" class="form-select"><option value="all" @selected($activity === 'all')>All activities</option><option value="pullout" @selected($activity === 'pullout')>Pull-Out</option><option value="sale" @selected($activity === 'sale')>Event Sale</option><option value="return" @selected($activity === 'return')>Return</option><option value="missing" @selected($activity === 'missing')>Missing</option></select></div>
        <div class="col-lg-2 col-md-3"><label class="form-label small fw-bold text-muted">FROM</label><input type="date" name="from" value="{{ request('from') }}" class="form-control"></div>
        <div class="col-lg-2 col-md-3"><label class="form-label small fw-bold text-muted">TO</label><input type="date" name="to" value="{{ request('to') }}" class="form-control"></div>
        <div class="col-lg-1 col-md-2"><button class="btn btn-primary w-100" title="Apply filters"><i class="fa-solid fa-filter"></i></button></div>
        <div class="col-lg-11 col-md-10"><label class="form-label small fw-bold text-muted">SEARCH</label><input name="search" value="{{ request('search') }}" class="form-control" placeholder="Reference, event, product name, or item code"></div>
        <div class="col-lg-1 col-md-2"><a href="{{ route('event-inventory.index', ['hub_id' => $hubId, 'section' => 'transactions']) }}" class="btn btn-outline-secondary w-100" title="Clear filters"><i class="fa-solid fa-rotate-left"></i></a></div>
    </form>
</div>

<div class="card border-0 shadow-sm rounded-4 overflow-hidden">
    <div class="table-responsive">
        <table class="table table-hover mb-0 event-log-table">
            <thead class="table-light"><tr><th>Date / Time</th><th>Event</th><th>Activity</th><th>Products</th><th>Total Qty</th><th>Reference / Customer</th><th>Inventory Effect</th><th>Recorded By</th></tr></thead>
            <tbody>
            @forelse($eventTransactionGroups as $group)
                @php
                    [$label, $badge, $effect] = $eventActivityLabels[$group['type']] ?? [ucwords(str_replace('_', ' ', $group['type'])), 'text-bg-secondary', 'Event inventory activity'];
                    $eventEffect = $group['type'] === 'sponsor_workshop' ? '+' : '-';
                    $headOfficeEffect = match($group['type']) {
                        'sponsor_workshop' => 'Physical stock deducted',
                        'return' => 'Physical stock restored',
                        default => 'No second stock deduction',
                    };
                @endphp
                <tr>
                    <td class="text-nowrap"><strong>{{ $group['occurred_on']?->format('M d, Y') }}</strong><div class="small text-muted">{{ $group['created_at']?->format('h:i A') }}</div></td>
                    <td><strong>{{ $group['event']?->source ?? 'Event' }}</strong><div class="small text-muted">{{ $group['event']?->reference ?? $group['reference'] }}</div></td>
                    <td><span class="badge {{ $badge }}">{{ $label }}</span><div class="small text-muted mt-1">{{ $effect }}</div></td>
                    <td>
                        @foreach($group['products'] as $item)
                            <div class="event-log-product" data-event-log-product="{{ $item['product']?->id }}">
                                <strong>{{ $item['product']?->name ?? 'Unknown product' }}</strong>
                                <div class="small text-muted mb-1">{{ $item['product']?->item_id ?? 'N/A' }}</div>
                                <div class="d-flex flex-wrap gap-1">
                                    @foreach($item['conditions'] as $condition => $quantity)
                                        <span class="badge text-bg-light border event-log-condition">{{ ucfirst($condition) }}: {{ $quantity }}</span>
                                    @endforeach
                                </div>
                            </div>
                        @endforeach
                    </td>
                    <td><strong class="{{ $eventEffect === '+' ? 'text-primary' : 'text-danger' }}">{{ $eventEffect }}{{ $group['quantity'] }}</strong></td>
                    <td><strong>{{ $group['reference'] }}</strong>@if($group['sale'])<div class="small text-muted">{{ $group['sale']->customer_name }} &middot; &#8369;{{ number_format((float) $group['sale']->grand_total, 2) }}</div>@endif</td>
                    <td><div>{{ $headOfficeEffect }}</div><div class="small text-muted">Event balance {{ $eventEffect }}{{ $group['quantity'] }}</div></td>
                    <td>{{ $group['creator']?->name ?? $group['creator']?->username ?? 'System' }}</td>
                </tr>
            @empty
                <tr><td colspan="8" class="text-center py-5"><i class="fa-solid fa-clock-rotate-left fs-2 text-muted mb-2"></i><div class="fw-semibold">No Event transactions found</div><div class="small text-muted">Adjust the filters or create an Event Pull-Out.</div></td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
    @if($eventTransactionGroups->hasPages())<div class="p-3 border-top">{{ $eventTransactionGroups->links() }}</div>@endif
</div>
