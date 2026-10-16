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
    .event-log-table td { padding: .75rem .65rem; }
    .event-log-view { border-radius: 999px; white-space: nowrap; }
    .event-log-modal .modal-header { background: linear-gradient(135deg, #eff6ff, #fff); border-bottom-color: #dbeafe; }
    .event-log-modal .modal-body { background: #f8fafc; }
    .event-log-hero { border: 1px solid #bfdbfe; border-radius: .9rem; background: linear-gradient(135deg, #eff6ff, #fff); }
    .event-log-sale-hero { display: flex; align-items: center; justify-content: space-between; gap: 1rem; }
    .event-log-sale-customer-label { color: #64748b; font-size: .68rem; font-weight: 800; letter-spacing: .05em; text-transform: uppercase; }
    .event-log-sale-customer { margin: .15rem 0 .25rem; color: #0f172a; font-size: 1.25rem; font-weight: 800; overflow-wrap: anywhere; }
    .event-log-sale-total { min-width: 130px; padding-left: 1rem; border-left: 1px solid #bfdbfe; text-align: right; }
    .event-log-meta { height: 100%; border: 1px solid #e2e8f0; border-radius: .75rem; background: #fff; padding: .8rem; }
    .event-log-meta .label { color: #64748b; font-size: .68rem; font-weight: 800; letter-spacing: .05em; text-transform: uppercase; }
    .event-log-attachments { padding: 0; overflow: hidden; }
    .event-log-attachments-title { padding: .8rem 1rem; border-bottom: 1px solid #e2e8f0; background: #fff; }
    .event-log-attachment-groups { display: grid; grid-template-columns: repeat(auto-fit, minmax(min(100%, 190px), 1fr)); gap: .75rem; padding: .85rem; }
    .event-log-attachment-group { min-width: 0; padding: .7rem; border: 1px solid #e2e8f0; border-radius: .65rem; background: #f8fafc; }
    .event-log-attachment-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(86px, 1fr)); gap: .5rem; }
    .event-log-attachment-card { display: block; overflow: hidden; border: 1px solid #dbe3ee; border-radius: .55rem; background: #fff; }
    .event-log-attachment { display: block; width: 100%; aspect-ratio: 4 / 3; object-fit: cover; background: #f1f5f9; }
    .event-log-attachment[hidden] { display: none; }
    .event-log-attachment-fallback:not([hidden]) { display: grid; min-height: 76px; place-items: center; padding: .5rem; color: #64748b; font-size: .75rem; text-align: center; }
    .event-log-products { border: 1px solid #e2e8f0; border-radius: .85rem; background: #fff; overflow: hidden; }
    .event-log-products-header { padding: .75rem 1rem; border-bottom: 1px solid #e2e8f0; background: #f8fafc; color: #475569; font-size: .72rem; font-weight: 800; letter-spacing: .05em; text-transform: uppercase; }
    .event-log-products-list { max-height: min(46vh, 420px); overflow-y: auto; overscroll-behavior-y: contain; scrollbar-gutter: stable; scrollbar-width: thin; scrollbar-color: #94a3b8 #f1f5f9; }
    .event-log-products-list::-webkit-scrollbar { width: 8px; }
    .event-log-products-list::-webkit-scrollbar-track { background: #f1f5f9; }
    .event-log-products-list::-webkit-scrollbar-thumb { border-radius: 999px; background: #94a3b8; }
    .event-log-product { display: flex; align-items: center; justify-content: space-between; gap: 1rem; padding: .85rem 1rem; }
    .event-log-product + .event-log-product { border-top: 1px solid #eef2f7; }
    .event-log-condition { font-size: .7rem; }
    @media (max-width: 575.98px) {
        .event-log-sale-hero { align-items: flex-start; }
        .event-log-sale-total { min-width: auto; padding-left: .75rem; }
    }
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
            <thead class="table-light"><tr><th>Date / Time</th><th>Activity</th><th>Event</th><th>Reference / Customer</th><th>Products</th><th>Total Qty</th><th>Recorded By</th><th>Actions</th></tr></thead>
            <tbody>
            @forelse($eventTransactionGroups as $group)
                @php
                    [$label, $badge, $effect] = $eventActivityLabels[$group['type']] ?? [ucwords(str_replace('_', ' ', $group['type'])), 'text-bg-secondary', 'Event inventory activity'];
                    $modalId = 'eventLogDetails'.(($eventTransactionGroups->currentPage() - 1) * $eventTransactionGroups->perPage() + $loop->iteration);
                @endphp
                <tr>
                    <td class="text-nowrap"><strong>{{ $group['occurred_on']?->format('M d, Y') }}</strong><div class="small text-muted">{{ $group['created_at']?->format('h:i A') }}</div></td>
                    <td><span class="badge {{ $badge }}">{{ $label }}</span><div class="small text-muted mt-1">{{ $effect }}</div></td>
                    <td><strong>{{ $group['event']?->source ?? 'Event' }}</strong><div class="small text-muted">{{ $group['event']?->reference ?? $group['reference'] }}</div></td>
                    <td>
                        @if($group['sale'])
                            <strong>{{ $group['sale']->customer_name }}</strong>
                            <div class="small text-muted">{{ $group['reference'] }} &middot; &#8369;{{ number_format((float) $group['sale']->grand_total, 2) }}</div>
                        @else
                            <strong>{{ $group['reference'] }}</strong>
                        @endif
                    </td>
                    <td><strong>{{ $group['products']->count() }}</strong><div class="small text-muted">unique {{ $group['products']->count() === 1 ? 'product' : 'products' }}</div></td>
                    <td class="fw-bold">{{ $group['quantity'] }}</td>
                    <td>{{ $group['creator']?->name ?? $group['creator']?->username ?? 'System' }}</td>
                    <td><button type="button" class="btn btn-sm btn-outline-primary event-log-view" data-bs-toggle="modal" data-bs-target="#{{ $modalId }}"><i class="fa-solid fa-eye me-1"></i>View details</button></td>
                </tr>
            @empty
                <tr><td colspan="8" class="text-center py-5"><i class="fa-solid fa-clock-rotate-left fs-2 text-muted mb-2"></i><div class="fw-semibold">No Event transactions found</div><div class="small text-muted">Adjust the filters or create an Event Pull-Out.</div></td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
    @if($eventTransactionGroups->hasPages())<div class="p-3 border-top">{{ $eventTransactionGroups->links() }}</div>@endif
</div>

@foreach($eventTransactionGroups as $group)
    @php
        [$label, $badge, $effect] = $eventActivityLabels[$group['type']] ?? [ucwords(str_replace('_', ' ', $group['type'])), 'text-bg-secondary', 'Event inventory activity'];
        $eventEffect = $group['type'] === 'sponsor_workshop' ? '+' : '-';
        $headOfficeEffect = match($group['type']) {
            'sponsor_workshop' => 'Physical stock deducted when items were pulled out.',
            'return' => 'Physical stock restored according to the returned condition.',
            default => 'No second Head Office stock deduction was made.',
        };
        $modalId = 'eventLogDetails'.(($eventTransactionGroups->currentPage() - 1) * $eventTransactionGroups->perPage() + $loop->iteration);
    @endphp
    <div class="modal fade" id="{{ $modalId }}" tabindex="-1" aria-labelledby="{{ $modalId }}Label" aria-hidden="true">
        <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable">
            <div class="modal-content event-log-modal">
                <div class="modal-header"><div><div class="small text-primary fw-bold text-uppercase">Event transaction</div><h5 class="modal-title" id="{{ $modalId }}Label">{{ $label }} Details</h5></div><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button></div>
                <div class="modal-body p-3 p-lg-4">
                    @if($group['sale'])
                        <div class="event-log-hero event-log-sale-hero p-3 mb-3">
                            <div class="min-w-0">
                                <div class="event-log-sale-customer-label">Customer</div>
                                <h3 class="event-log-sale-customer">{{ $group['sale']->customer_name ?: 'Customer not specified' }}</h3>
                                <div class="small text-muted text-break">{{ $group['reference'] }} <span aria-hidden="true">&middot;</span> {{ $group['event']?->source ?? 'Event' }}</div>
                            </div>
                            <div class="event-log-sale-total">
                                <div class="small text-muted text-uppercase fw-bold">Sale Total</div>
                                <div class="fs-4 fw-bold text-success">&#8369;{{ number_format((float) $group['sale']->grand_total, 2) }}</div>
                            </div>
                        </div>
                    @else
                        <div class="event-log-hero p-3 mb-3">
                            <div class="d-flex flex-wrap justify-content-between gap-3">
                                <div><span class="badge {{ $badge }} mb-2">{{ $label }}</span><h4 class="mb-1">{{ $group['event']?->source ?? 'Event' }}</h4><div class="text-muted">{{ $group['reference'] }}</div></div>
                                <div class="text-lg-end"><div class="small text-muted text-uppercase fw-bold">Total quantity</div><div class="fs-2 fw-bold {{ $eventEffect === '+' ? 'text-primary' : 'text-danger' }}">{{ $eventEffect }}{{ $group['quantity'] }}</div></div>
                            </div>
                        </div>
                    @endif

                    <div class="row g-3 mb-3">
                        <div class="col-md-4"><div class="event-log-meta"><div class="label">Date / Time</div><div class="fw-semibold mt-1">{{ $group['occurred_on']?->format('M d, Y') }}<div class="small text-muted">{{ $group['created_at']?->format('h:i A') }}</div></div></div></div>
                        <div class="col-md-4"><div class="event-log-meta"><div class="label">Event Reference</div><div class="fw-semibold mt-1 text-break">{{ $group['event']?->reference ?? $group['reference'] }}</div></div></div>
                        <div class="col-md-4"><div class="event-log-meta"><div class="label">Recorded By</div><div class="fw-semibold mt-1">{{ $group['creator']?->name ?? $group['creator']?->username ?? 'System' }}</div></div></div>
                        @if($group['sale'])
                            @php
                                $paymentLabels = ['CASH' => 'Cash', 'GCASH' => 'GCash', 'BDO' => 'BDO', 'BPI' => 'BPI', 'PAYMAYA' => 'PayMaya', 'OTHERS' => 'Others'];
                                $paymentLabel = $group['sale']->mode_of_payment === 'OTHERS'
                                    ? ($group['sale']->custom_mop ?: 'Others')
                                    : ($paymentLabels[$group['sale']->mode_of_payment] ?? ($group['sale']->mode_of_payment ?: 'Not specified'));
                            @endphp
                            <div class="col-md-6"><div class="event-log-meta"><div class="label">Activity</div><div class="fw-semibold mt-1">{{ $effect }}</div>@if($group['notes'])<div class="small text-muted mt-1">{{ $group['notes'] }}</div>@endif</div></div>
                            <div class="col-md-6"><div class="event-log-meta"><div class="label">Payment</div><div class="fw-semibold mt-1">{{ $paymentLabel }}</div><div class="small text-muted">{{ ucfirst($group['sale']->payment_status ?: 'paid') }}</div></div></div>
                        @else
                            @if($group['type'] !== 'sold')
                                <div class="col-md-6"><div class="event-log-meta"><div class="label">Inventory Effect</div><div class="fw-semibold mt-1">{{ $headOfficeEffect }}</div><div class="small text-muted">Event balance {{ $eventEffect }}{{ $group['quantity'] }}</div></div></div>
                            @endif
                            <div class="col-md-6"><div class="event-log-meta"><div class="label">Activity</div><div class="fw-semibold mt-1">{{ $effect }}</div>@if($group['notes'])<div class="small text-muted mt-1">{{ $group['notes'] }}</div>@endif</div></div>
                        @endif
                    </div>

                    @if($group['sale'])
                        @php
                            $storedAttachments = $group['sale']->event_attachments ?? [];
                            $categorizedAttachments = is_array($storedAttachments)
                                && (array_key_exists('order_slip', $storedAttachments) || array_key_exists('item_images', $storedAttachments) || array_key_exists('proof_of_payment', $storedAttachments));
                            $attachmentGroups = $categorizedAttachments ? [
                                'Order Slip Image' => array_filter([$storedAttachments['order_slip'] ?? null]),
                                'Item Images' => array_filter($storedAttachments['item_images'] ?? []),
                                'Proof of Payment' => array_filter([$storedAttachments['proof_of_payment'] ?? null]),
                            ] : ['Event Images' => array_filter(is_array($storedAttachments) ? $storedAttachments : [])];
                        @endphp
                    @endif

                    <div class="event-log-products mb-3">
                        <div class="event-log-products-header">Products &middot; Same products are consolidated</div>
                        <div class="event-log-products-list">
                        @foreach($group['products'] as $item)
                            <div class="event-log-product" data-event-log-product="{{ $item['product']?->id }}">
                                <div><strong>{{ $item['product']?->name ?? 'Unknown product' }}</strong><div class="small text-muted">{{ $item['product']?->item_id ?? 'N/A' }}</div><div class="d-flex flex-wrap gap-1 mt-2">@foreach($item['conditions'] as $condition => $quantity)<span class="badge text-bg-light border event-log-condition">{{ ucfirst($condition) }}: {{ $quantity }}</span>@endforeach</div></div>
                                <div class="text-end"><div class="small text-muted">Total Qty</div><div class="fs-5 fw-bold">{{ $item['quantity'] }}</div></div>
                            </div>
                        @endforeach
                        </div>
                    </div>

                    @if($group['sale'] && collect($attachmentGroups)->flatten()->isNotEmpty())
                        <div class="event-log-meta event-log-attachments mb-3">
                            <div class="event-log-attachments-title"><div class="label">Sale Image Attachments</div></div>
                            <div class="event-log-attachment-groups">
                                @foreach($attachmentGroups as $attachmentLabel => $attachments)
                                    @if(!empty($attachments))
                                        <div class="event-log-attachment-group">
                                            <div class="small fw-semibold text-muted mb-2">{{ $attachmentLabel }}</div>
                                            <div class="event-log-attachment-grid">
                                                @foreach($attachments as $attachment)
                                                    <a class="event-log-attachment-card" href="{{ asset('storage/'.$attachment) }}" target="_blank" rel="noopener" aria-label="Open {{ $attachmentLabel }} {{ $loop->iteration }}">
                                                        <img src="{{ asset('storage/'.$attachment) }}" class="event-log-attachment" alt="{{ $attachmentLabel }} {{ $loop->iteration }}" onerror="this.hidden=true; this.nextElementSibling.hidden=false">
                                                        <span class="event-log-attachment-fallback" hidden>Image unavailable</span>
                                                    </a>
                                                @endforeach
                                            </div>
                                        </div>
                                    @endif
                                @endforeach
                            </div>
                        </div>
                    @endif
                </div>
                <div class="modal-footer"><button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button></div>
            </div>
        </div>
    </div>
@endforeach
