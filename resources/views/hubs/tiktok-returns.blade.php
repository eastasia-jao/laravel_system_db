@extends('layouts.app')

@section('content')
<div class="container-fluid py-4">
    <div class="d-flex flex-wrap justify-content-between align-items-start gap-3 mb-4">
        <div>
            <a href="{{ route('hub.dashboard', $hub->id) }}" class="btn btn-sm btn-outline-secondary mb-3">
                <i class="fa-solid fa-arrow-left me-1"></i> Hub Dashboard
            </a>
            <h2 class="fw-bold mb-1"><i class="fa-solid fa-rotate-left text-warning me-2"></i>TikTok Returns</h2>
            <p class="text-muted mb-0">{{ $hub->name }} · Report items customers say they are returning.</p>
        </div>
    </div>

    <form method="GET" action="{{ route('hub.tiktok-returns', $hub->id) }}" class="card card-body mb-4">
        <div class="row g-3 align-items-end">
            <div class="col-md-4">
                <label class="form-label" for="date_from">Order date from</label>
                <input class="form-control" type="date" name="date_from" id="date_from" value="{{ $dateFrom }}">
            </div>
            <div class="col-md-4">
                <label class="form-label" for="date_to">Order date to</label>
                <input class="form-control" type="date" name="date_to" id="date_to" value="{{ $dateTo }}">
            </div>
            <div class="col-md-4 d-flex gap-2">
                <button class="btn btn-primary" type="submit"><i class="fa-solid fa-filter me-1"></i>Filter orders</button>
                <a class="btn btn-outline-secondary" href="{{ route('hub.tiktok-returns', $hub->id) }}">Clear</a>
            </div>
        </div>
    </form>

    @if($transactions->isNotEmpty())
        <div class="card shadow-sm">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th class="ps-3">TikTok Order #</th>
                            <th>Customer name</th>
                            <th>Order date</th>
                            <th>Items</th>
                            <th>Notes</th>
                            <th class="text-end pe-3">Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($transactions as $transaction)
                            @php
                                $returnNotes = $transaction->items
                                    ->pluck('return_reason')
                                    ->filter()
                                    ->unique()
                                    ->values();
                            @endphp
                            <tr>
                                <td class="ps-3 fw-semibold">{{ $transaction->order_number }}</td>
                                <td>{{ $transaction->customer_name ?: 'TikTok Customer' }}</td>
                                <td>{{ optional($transaction->order_date)->format('M d, Y') ?: '—' }}</td>
                                <td>{{ $transaction->items->count() }}</td>
                                <td>
                                    @if($returnNotes->isEmpty())
                                        <span class="text-muted">—</span>
                                    @else
                                        {{ $returnNotes->implode('; ') }}
                                    @endif
                                </td>
                                <td class="text-end pe-3">
                                    <button class="btn btn-sm btn-outline-primary text-nowrap"
                                        type="button"
                                        data-bs-toggle="modal"
                                        data-bs-target="#return-items-modal-{{ $transaction->id }}">
                                        <i class="fa-solid fa-eye me-1"></i>View return items
                                    </button>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
        @foreach($transactions as $transaction)
            <div class="modal fade" id="return-items-modal-{{ $transaction->id }}" tabindex="-1" aria-labelledby="return-items-title-{{ $transaction->id }}" aria-hidden="true">
                <div class="modal-dialog modal-xl modal-dialog-scrollable">
                    <div class="modal-content">
                        <div class="modal-header">
                            <div>
                                <h5 class="modal-title fw-bold" id="return-items-title-{{ $transaction->id }}">TikTok Order #{{ $transaction->order_number }}</h5>
                                <div class="small text-muted">{{ $transaction->customer_name ?: 'TikTok Customer' }} · {{ optional($transaction->order_date)->format('M d, Y') }}</div>
                            </div>
                            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                        </div>
                        <div class="modal-body">
                            <p class="small text-muted">Select the returned quantity, mark each item Good or Bad, and add notes if needed. Saving marks the item received and updates stock.</p>
                            @forelse($transaction->items as $item)
                                @include('hubs.reports.tiktok-return-entry', ['hub' => $hub, 'transaction' => $transaction, 'item' => $item])
                            @empty
                                <div class="alert alert-light border mb-0">This order has no items.</div>
                            @endforelse
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Close</button>
                        </div>
                    </div>
                </div>
            </div>
        @endforeach
    @else
        <div class="alert alert-info">No TikTok orders found for this Hub and date range.</div>
    @endif

    <div class="mt-4">{{ $transactions->links() }}</div>
</div>
@endsection
