@extends('layouts.app')

@section('content')
<div class="container-fluid py-4">
    <x-page-header class="mb-4" eyebrow="Returns workspace" :title="$channel.' Returns'" :description="$hub->name.' · Report items customers say they are returning.'" icon="fa-rotate-left">
        <x-slot:actions><a href="{{ route('hub.dashboard', $hub->id) }}" class="btn btn-outline-secondary"><i class="fa-solid fa-arrow-left me-1"></i>Hub Dashboard</a></x-slot:actions>
    </x-page-header>

    <form method="GET" action="{{ $channelKey === 'tiktok' ? route('hub.tiktok-returns', $hub->id) : route('hub.marketplace-returns', ['hub' => $hub->id]) }}" class="card card-body mb-4">
        @if($channelKey !== 'tiktok')
            <input type="hidden" name="channel" value="{{ $channelKey }}">
        @endif
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
                            <th class="ps-3">{{ $channel }} Order #</th>
                            <th>Customer name</th>
                            <th>Order date</th>
                            <th>Items</th>
                            <th>Return summary</th>
                            <th class="text-end pe-3">Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($transactions as $transaction)
                            @php
                                $returnedItems = $transaction->items->filter(fn ($item) => $item->inventoryReturns->contains(fn ($return) => (int) $return->quantity > 0)
                                    || ($item->return_status === 'received' && (int) $item->returned_quantity > 0));
                                $returnRecords = $returnedItems->flatMap(fn ($item) => $item->inventoryReturns->where('quantity', '>', 0));
                                $goodReturned = (int) $returnRecords->where('condition', 'good')->sum('quantity');
                                $badReturned = (int) $returnRecords->where('condition', 'damaged')->sum('quantity');
                                $totalReturned = (int) $returnedItems->sum(fn ($item) => $item->returnedQuantity());
                            @endphp
                            <tr>
                                <td class="ps-3 fw-semibold">{{ $transaction->order_number }}</td>
                                <td>{{ $transaction->customer_name ?: $channel.' Customer' }}</td>
                                <td>{{ optional($transaction->order_date)->format('M d, Y') ?: '—' }}</td>
                                <td>{{ $returnedItems->count() }}</td>
                                <td>
                                    @if($totalReturned === 0)
                                        <span class="badge text-bg-secondary">No items recorded</span>
                                    @else
                                        @if($goodReturned > 0)<span class="badge text-bg-success me-1">Good: {{ $goodReturned }}</span>@endif
                                        @if($badReturned > 0)<span class="badge text-bg-danger me-1">Bad: {{ $badReturned }}</span>@endif
                                        @if($goodReturned === 0 && $badReturned === 0)<span class="badge text-bg-secondary">Returned: {{ $totalReturned }}</span>@endif
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
                                <h5 class="modal-title fw-bold" id="return-items-title-{{ $transaction->id }}">{{ $channel }} Order #{{ $transaction->order_number }}</h5>
                                <div class="small text-muted">{{ $transaction->customer_name ?: $channel.' Customer' }} · {{ optional($transaction->order_date)->format('M d, Y') }}</div>
                            </div>
                            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                        </div>
                        <div class="modal-body">
                            <p class="small text-muted">Return quantities and item conditions below are recorded by inventory staff.</p>
                            @php
                                $returnedItems = $transaction->items->filter(fn ($item) => $item->inventoryReturns->contains(fn ($return) => (int) $return->quantity > 0)
                                    || ($item->return_status === 'received' && (int) $item->returned_quantity > 0));
                            @endphp
                            @if($returnedItems->isEmpty())
                                <div class="alert alert-light border mb-0">This order has no returned items.</div>
                            @else
                                <div class="table-responsive">
                                    <table class="table table-bordered align-middle mb-0">
                                        <thead class="table-light">
                                            <tr>
                                                <th>Item</th>
                                                <th class="text-center">Sold quantity</th>
                                                <th class="text-center">Return quantity</th>
                                                <th>Item status</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            @foreach($returnedItems as $item)
                                                @php
                                                    $itemReturns = $item->inventoryReturns->where('quantity', '>', 0);
                                                    $goodQuantity = (int) $itemReturns->where('condition', 'good')->sum('quantity');
                                                    $badQuantity = (int) $itemReturns->where('condition', 'damaged')->sum('quantity');
                                                    $returnedQuantity = $item->returnedQuantity();
                                                @endphp
                                                <tr>
                                                    <td>{{ $item->product?->name ?? 'Product #'.$item->product_id }}</td>
                                                    <td class="text-center">{{ $item->quantity }}</td>
                                                    <td class="text-center">{{ $returnedQuantity }}</td>
                                                    <td>
                                                        @if($goodQuantity > 0)<span class="badge text-bg-success me-1">Good: {{ $goodQuantity }}</span>@endif
                                                        @if($badQuantity > 0)<span class="badge text-bg-danger me-1">Bad: {{ $badQuantity }}</span>@endif
                                                        @if($goodQuantity === 0 && $badQuantity === 0)<span class="badge text-bg-secondary">Returned</span>@endif
                                                    </td>
                                                </tr>
                                            @endforeach
                                        </tbody>
                                    </table>
                                </div>
                                @php
                                    $returnNotes = $returnedItems
                                        ->flatMap(fn ($item) => $item->inventoryReturns->where('quantity', '>', 0)->pluck('notes'))
                                        ->filter(fn ($note) => filled($note))
                                        ->unique()
                                        ->values();
                                @endphp
                                <div class="border rounded-3 p-3 mt-3">
                                    <div class="small fw-bold text-muted text-uppercase mb-1">Return notes</div>
                                    @forelse($returnNotes as $note)
                                        <p class="mb-1">{{ $note }}</p>
                                    @empty
                                        <span class="text-muted">No notes recorded.</span>
                                    @endforelse
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
        <div class="alert alert-info">No {{ $channel }} orders found for this Hub and date range.</div>
    @endif

    <div class="mt-4">{{ $transactions->links('vendor.pagination.bootstrap-5') }}</div>
</div>
@endsection
