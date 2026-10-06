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

    @forelse($transactions as $transaction)
        <section class="card mb-4 shadow-sm">
            <div class="card-header bg-white d-flex flex-wrap justify-content-between align-items-center gap-2">
                <div>
                    <h5 class="fw-bold mb-1">{{ $transaction->customer_name ?: 'TikTok Customer' }}</h5>
                    <div class="small text-muted">Order #{{ $transaction->order_number }} · {{ optional($transaction->order_date)->format('M d, Y') }}</div>
                </div>
                <span class="badge text-bg-dark">TikTok · {{ $transaction->items->count() }} item(s)</span>
            </div>
            <div class="card-body">
                <p class="small text-muted">Use <strong>Return requested</strong> to notify the team about items the customer says they are returning. Mark items as received only after they physically arrive.</p>
                @forelse($transaction->items as $item)
                    @include('hubs.reports.tiktok-return-form', ['hub' => $hub, 'transaction' => $transaction, 'item' => $item])
                @empty
                    <div class="alert alert-light border mb-0">This order has no items.</div>
                @endforelse
            </div>
        </section>
    @empty
        <div class="alert alert-info">No TikTok orders found for this Hub and date range.</div>
    @endforelse

    <div class="mt-4">{{ $transactions->links() }}</div>
</div>
@endsection
