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
                            <th>Return status</th>
                            <th class="text-end pe-3">Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($transactions as $transaction)
                            @php
                                $returnStatuses = $transaction->items
                                    ->map(fn ($item) => $item->return_status ?? 'none')
                                    ->filter(fn ($status) => $status !== 'none')
                                    ->unique();
                            @endphp
                            <tr>
                                <td class="ps-3 fw-semibold">{{ $transaction->order_number }}</td>
                                <td>{{ $transaction->customer_name ?: 'TikTok Customer' }}</td>
                                <td>{{ optional($transaction->order_date)->format('M d, Y') ?: '—' }}</td>
                                <td>{{ $transaction->items->count() }}</td>
                                <td>
                                    @if($returnStatuses->isEmpty())
                                        <span class="badge text-bg-secondary">No return reported</span>
                                    @else
                                        @foreach($returnStatuses as $status)
                                            <span class="badge {{ $status === 'received' ? 'text-bg-success' : ($status === 'rejected' ? 'text-bg-danger' : 'text-bg-warning') }} me-1">
                                                {{ ucfirst(str_replace('_', ' ', $status)) }}
                                            </span>
                                        @endforeach
                                    @endif
                                </td>
                                <td class="text-end pe-3">
                                    <button class="btn btn-sm btn-outline-primary text-nowrap"
                                        type="button"
                                        data-bs-toggle="collapse"
                                        data-bs-target="#return-items-{{ $transaction->id }}"
                                        aria-expanded="false"
                                        aria-controls="return-items-{{ $transaction->id }}">
                                        <i class="fa-solid fa-eye me-1"></i>View return items
                                    </button>
                                </td>
                            </tr>
                            <tr>
                                <td colspan="6" class="p-0 border-0">
                                    <div class="collapse" id="return-items-{{ $transaction->id }}">
                                        <div class="p-3 bg-light border-top">
                                            <div class="d-flex flex-wrap justify-content-between align-items-start gap-2 mb-3">
                                                <div>
                                                    <h6 class="fw-bold mb-1">TikTok Order #{{ $transaction->order_number }}</h6>
                                                    <div class="small text-muted">{{ $transaction->customer_name ?: 'TikTok Customer' }} · Report items customers say they are returning.</div>
                                                </div>
                                                <span class="small text-muted">Mark items received only after they physically arrive.</span>
                                            </div>
                                            @forelse($transaction->items as $item)
                                                @include('hubs.reports.tiktok-return-form', ['hub' => $hub, 'transaction' => $transaction, 'item' => $item])
                                            @empty
                                                <div class="alert alert-light border mb-0">This order has no items.</div>
                                            @endforelse
                                        </div>
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    @else
        <div class="alert alert-info">No TikTok orders found for this Hub and date range.</div>
    @endif

    <div class="mt-4">{{ $transactions->links() }}</div>
</div>
@endsection
