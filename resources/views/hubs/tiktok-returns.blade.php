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
                            <th>Return summary</th>
                            <th>Notes</th>
                            <th class="text-end pe-3">Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($transactions as $transaction)
                            @php
                                $returnRecords = $transaction->items->flatMap->inventoryReturns;
                                $goodReturned = (int) $returnRecords->where('condition', 'good')->sum('quantity');
                                $badReturned = (int) $returnRecords->where('condition', 'damaged')->sum('quantity');
                                $totalReturned = (int) $returnRecords->sum('quantity');
                                $returnNotes = $returnRecords->pluck('notes')->filter()->unique()->values();
                            @endphp
                            <tr>
                                <td class="ps-3 fw-semibold">{{ $transaction->order_number }}</td>
                                <td>{{ $transaction->customer_name ?: 'TikTok Customer' }}</td>
                                <td>{{ optional($transaction->order_date)->format('M d, Y') ?: '—' }}</td>
                                <td>{{ $transaction->items->count() }}</td>
                                <td>
                                    @if($totalReturned === 0)
                                        <span class="badge text-bg-secondary">No items recorded</span>
                                    @else
                                        @if($goodReturned > 0)<span class="badge text-bg-success me-1">Good: {{ $goodReturned }}</span>@endif
                                        @if($badReturned > 0)<span class="badge text-bg-danger me-1">Bad: {{ $badReturned }}</span>@endif
                                    @endif
                                </td>
                                <td>{{ $returnNotes->isNotEmpty() ? $returnNotes->implode('; ') : '—' }}</td>
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
                            <p class="small text-muted">Return quantities and item conditions below are recorded by inventory staff.</p>
                            @if($transaction->items->isEmpty())
                                <div class="alert alert-light border mb-0">This order has no items.</div>
                            @else
                                <div class="table-responsive">
                                    <table class="table table-bordered align-middle mb-0">
                                        <thead class="table-light">
                                            <tr>
                                                <th>Item</th>
                                                <th class="text-center">Sold quantity</th>
                                                <th class="text-center">Return quantity</th>
                                                <th>Item status</th>
                                                <th>Notes</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            @foreach($transaction->items as $item)
                                                @php
                                                    $itemReturns = $item->inventoryReturns;
                                                    $goodQuantity = (int) $itemReturns->where('condition', 'good')->sum('quantity');
                                                    $badQuantity = (int) $itemReturns->where('condition', 'damaged')->sum('quantity');
                                                    $returnedQuantity = (int) $itemReturns->sum('quantity');
                                                    $itemNotes = $itemReturns->pluck('notes')->filter()->unique()->values();
                                                @endphp
                                                <tr>
                                                    <td>{{ $item->product?->name ?? 'Product #'.$item->product_id }}</td>
                                                    <td class="text-center">{{ $item->quantity }}</td>
                                                    <td class="text-center">{{ $returnedQuantity }}</td>
                                                    <td>
                                                        @if($returnedQuantity === 0)
                                                            <span class="text-muted">Not recorded by inventory staff</span>
                                                        @else
                                                            @if($goodQuantity > 0)<span class="badge text-bg-success me-1">Good: {{ $goodQuantity }}</span>@endif
                                                            @if($badQuantity > 0)<span class="badge text-bg-danger me-1">Bad: {{ $badQuantity }}</span>@endif
                                                        @endif
                                                    </td>
                                                    <td>{{ $itemNotes->isNotEmpty() ? $itemNotes->implode('; ') : '—' }}</td>
                                                </tr>
                                            @endforeach
                                        </tbody>
                                    </table>
                                </div>
                            @endif
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
