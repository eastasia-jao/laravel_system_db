@extends('layouts.app')

@section('content')
<style>
    .fully-booked-hero { padding:1.25rem 1.5rem; border:1px solid #dbeafe; border-radius:1rem; background:linear-gradient(110deg,#eff6ff,#fff 72%); }
    .fully-booked-preview { display:block; max-width:min(100%, 680px); max-height:560px; margin-top:.75rem; border:1px solid #e2e8f0; border-radius:.75rem; background:#f8fafc; }
    .fully-booked-pdf { width:min(100%, 760px); height:560px; margin-top:.75rem; border:1px solid #e2e8f0; border-radius:.75rem; }
    .fully-booked-order-row td { vertical-align:top; }
</style>
<div class="container-fluid p-4">
    <header class="fully-booked-hero mb-4 d-flex flex-wrap align-items-center justify-content-between gap-3">
        <div class="d-flex align-items-center gap-3">
            <span class="d-grid place-items-center rounded-3 bg-primary-subtle text-primary" style="width:3rem;height:3rem"><i class="fa-solid fa-file-circle-check fs-5"></i></span>
            <div>
                <div class="small text-uppercase fw-bold text-primary mb-1">Transaction Logs</div>
                <h3 class="fw-bold mb-1">Fully Booked Orders</h3>
                <p class="small text-muted mb-0">Review attachments submitted by designated Sales/Marketing staff. This review does not change stock.</p>
            </div>
        </div>
        @if(auth()->user()?->role === 'sales_marketing_staff' && auth()->user()->hasSalesChannel('fully_booked'))
            <a href="{{ route('inventory-transactions.sponsor.create', ['activity_type' => 'fully_booked']) }}" class="btn btn-primary"><i class="fa-solid fa-upload me-1"></i>Submit Fully Booked Order</a>
        @endif
    </header>

    <section class="card border-0 shadow-sm rounded-4 overflow-hidden">
        <div class="card-body border-bottom">
            <form class="row g-2 align-items-end">
                <div class="col-md-6">
                    <label for="fullyBookedSearch" class="form-label small fw-bold text-muted">ORDER NUMBER</label>
                    <input id="fullyBookedSearch" type="search" name="search" value="{{ request('search') }}" class="form-control" placeholder="Search Fully Booked order number">
                </div>
                <div class="col-md-3">
                    <label for="fullyBookedStatus" class="form-label small fw-bold text-muted">STATUS</label>
                    <select id="fullyBookedStatus" name="status" class="form-select">
                        <option value="">All statuses</option>
                        <option value="pending" @selected(request('status') === 'pending')>Pending review</option>
                        <option value="reviewed" @selected(request('status') === 'reviewed')>Reviewed</option>
                    </select>
                </div>
                <div class="col-md-3"><button class="btn btn-primary w-100"><i class="fa-solid fa-filter me-1"></i>Filter orders</button></div>
            </form>
        </div>
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light"><tr><th>Order Number</th><th>Sales / Marketing Staff</th><th>Submitted By</th><th>Store Hub</th><th>Order Store</th><th>Submitted</th><th>Status / Review</th></tr></thead>
                <tbody>
                    @forelse($orders as $order)
                        <tr class="fully-booked-order-row">
                            <td><strong>{{ $order->order_number }}</strong></td>
                            <td>{{ $order->salesStaff?->name ?? 'Deleted staff' }}</td>
                            <td>{{ $order->submitter?->name ?? 'Deleted user' }}</td>
                            <td>{{ $order->storeHub?->name ?? '—' }}</td>
                            <td>{{ $order->store_name ?? '—' }}</td>
                            <td>{{ $order->created_at?->format('M d, Y h:i A') }}</td>
                            <td>
                                @if($order->status === 'reviewed')
                                    <span class="badge text-bg-success">Reviewed</span>
                                    <small class="d-block text-muted mt-1">{{ $order->reviewer?->name ?? 'Inventory staff' }}{{ $order->reviewed_at ? ' · '.$order->reviewed_at->format('M d, Y h:i A') : '' }}</small>
                                @else
                                    <span class="badge text-bg-warning">Pending review</span>
                                    @if($canReview)
                                        <form method="POST" action="{{ route('inventory-transactions.fully-booked.review', $order) }}" class="mt-2">
                                            @csrf
                                            @method('PATCH')
                                            <button class="btn btn-sm btn-outline-success"><i class="fa-solid fa-check me-1"></i>Mark reviewed</button>
                                        </form>
                                    @endif
                                @endif
                            </td>
                        </tr>
                        <tr>
                            <td colspan="7" class="bg-light">
                                <details>
                                    <summary class="text-primary fw-semibold" role="button"><i class="fa-solid fa-eye me-1"></i>Preview attachment</summary>
                                    @if(str_starts_with($order->mime_type, 'image/'))
                                        <img src="{{ route('inventory-transactions.fully-booked.attachment', $order) }}" alt="Attachment for Fully Booked order {{ $order->order_number }}" class="fully-booked-preview">
                                    @else
                                        <iframe src="{{ route('inventory-transactions.fully-booked.attachment', $order) }}" title="Attachment for Fully Booked order {{ $order->order_number }}" class="fully-booked-pdf"></iframe>
                                    @endif
                                    <div class="small text-muted mt-2">{{ $order->original_filename }}</div>
                                </details>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="7" class="text-center text-muted py-5">No Fully Booked orders found.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="card-footer bg-white">{{ $orders->links() }}</div>
    </section>
</div>
@endsection
