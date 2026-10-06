@extends('layouts.app')

@section('content')
<div class="container-fluid py-4">
    <div class="d-flex flex-wrap justify-content-between align-items-start gap-3 mb-4">
        <div>
            <a href="{{ route('hub.dashboard', $hub->id) }}" class="btn btn-sm btn-outline-secondary mb-3">
                <i class="fa-solid fa-arrow-left me-1"></i>Hub Dashboard
            </a>
            <h2 class="fw-bold mb-1"><i class="fa-solid fa-rotate-left text-warning me-2"></i>Fully Booked Returns</h2>
            <p class="text-muted mb-0">{{ $hub->name }} · Review items returned from Fully Booked orders.</p>
        </div>
    </div>

    <form method="GET" action="{{ route('hub.fully-booked-returns', $hub->id) }}" class="card card-body mb-4">
        <div class="row g-3 align-items-end">
            <div class="col-md-4">
                <label class="form-label" for="date_from">Return date from</label>
                <input class="form-control" type="date" name="date_from" id="date_from" value="{{ $dateFrom }}">
            </div>
            <div class="col-md-4">
                <label class="form-label" for="date_to">Return date to</label>
                <input class="form-control" type="date" name="date_to" id="date_to" value="{{ $dateTo }}">
            </div>
            <div class="col-md-4 d-flex gap-2">
                <button class="btn btn-primary" type="submit"><i class="fa-solid fa-filter me-1"></i>Filter returns</button>
                <a class="btn btn-outline-secondary" href="{{ route('hub.fully-booked-returns', $hub->id) }}">Clear</a>
            </div>
        </div>
    </form>

    @if($transactions->isNotEmpty())
        <div class="card shadow-sm">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th class="ps-3">Fully Booked Order #</th>
                            <th>Customer</th>
                            <th>Order date</th>
                            <th>Returned lines</th>
                            <th>Returned quantity</th>
                            <th>Condition summary</th>
                            <th class="text-end pe-3">Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($transactions as $transaction)
                            @php
                                $returnRecords = $transaction->inventoryReturns;
                                $goodReturned = (int) $returnRecords->where('condition', 'good')->sum('quantity');
                                $badReturned = (int) $returnRecords->where('condition', 'damaged')->sum('quantity');
                            @endphp
                            <tr>
                                <td class="ps-3 fw-semibold">{{ $transaction->order_number ?: '—' }}</td>
                                <td>{{ $transaction->customer_name ?: 'Fully Booked Customer' }}</td>
                                <td>{{ optional($transaction->order_date)->format('M d, Y') ?: '—' }}</td>
                                <td>{{ $returnRecords->count() }}</td>
                                <td>{{ $returnRecords->sum('quantity') }}</td>
                                <td>
                                    @if($goodReturned > 0)<span class="badge text-bg-success me-1">Good: {{ $goodReturned }}</span>@endif
                                    @if($badReturned > 0)<span class="badge text-bg-danger me-1">Damaged: {{ $badReturned }}</span>@endif
                                </td>
                                <td class="text-end pe-3">
                                    <button class="btn btn-sm btn-outline-primary text-nowrap" type="button" data-bs-toggle="modal" data-bs-target="#fully-booked-return-{{ $transaction->id }}">
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
            <div class="modal fade" id="fully-booked-return-{{ $transaction->id }}" tabindex="-1" aria-labelledby="fully-booked-return-title-{{ $transaction->id }}" aria-hidden="true">
                <div class="modal-dialog modal-xl modal-dialog-scrollable">
                    <div class="modal-content">
                        <div class="modal-header">
                            <div>
                                <h5 class="modal-title fw-bold" id="fully-booked-return-title-{{ $transaction->id }}">Fully Booked Order #{{ $transaction->order_number ?: $transaction->id }}</h5>
                                <div class="small text-muted">{{ $transaction->customer_name ?: 'Fully Booked Customer' }} · {{ optional($transaction->order_date)->format('M d, Y') }}</div>
                            </div>
                            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                        </div>
                        <div class="modal-body">
                            <p class="small text-muted">These return quantities and conditions were recorded by inventory staff.</p>
                            <div class="table-responsive">
                                <table class="table table-bordered align-middle mb-0">
                                    <thead class="table-light">
                                        <tr>
                                            <th>Returned item</th>
                                            <th>Original order item</th>
                                            <th class="text-center">Quantity</th>
                                            <th>Condition</th>
                                            <th>Return date</th>
                                            <th>Notes</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @foreach($transaction->inventoryReturns as $return)
                                            <tr>
                                                <td>{{ $return->product?->name ?? 'Product #'.$return->product_id }}</td>
                                                <td>
                                                    @if($return->productReplacement)
                                                        {{ $return->productReplacement->replacementProduct?->name ?? 'Replacement item' }}
                                                    @else
                                                        {{ $return->transactionItem?->product?->name ?? '—' }}
                                                    @endif
                                                </td>
                                                <td class="text-center">{{ $return->quantity }}</td>
                                                <td>
                                                    @if($return->condition === 'good')
                                                        <span class="badge text-bg-success">Good</span>
                                                    @elseif($return->condition === 'damaged')
                                                        <span class="badge text-bg-danger">Damaged</span>
                                                    @else
                                                        <span class="badge text-bg-secondary">Refund only</span>
                                                    @endif
                                                </td>
                                                <td>{{ optional($return->occurred_on)->format('M d, Y') ?: '—' }}</td>
                                                <td>{{ $return->notes ?: '—' }}</td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Close</button>
                        </div>
                    </div>
                </div>
            </div>
        @endforeach
    @else
        <div class="alert alert-info">No Fully Booked return items were recorded for this hub and date range.</div>
    @endif

    <div class="mt-4">{{ $transactions->links() }}</div>
</div>
@endsection
