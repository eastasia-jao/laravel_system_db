@extends('layouts.app')

@section('content')
<div class="container-fluid py-4">
    <div class="d-flex flex-wrap justify-content-between align-items-start gap-3 mb-4">
        <div>
            <a href="{{ route('hub.dashboard', $hub->id) }}" class="btn btn-sm btn-outline-secondary mb-3">
                <i class="fa-solid fa-arrow-left me-1"></i>Hub Dashboard
            </a>
            <h2 class="fw-bold mb-1"><i class="fa-solid fa-circle-xmark text-danger me-2"></i>Rejected Fully Booked Orders</h2>
            <p class="text-muted mb-0">{{ $hub->name }} · Review rejected orders and the reason provided by Inventory Staff.</p>
        </div>
    </div>

    @if($orders->isNotEmpty())
        <div class="card shadow-sm">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th class="ps-3">Order number</th>
                            <th>Order store</th>
                            <th>Submitted</th>
                            <th>Rejected</th>
                            <th>Reason for rejection</th>
                            <th class="text-end pe-3">Attachment</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($orders as $order)
                            <tr>
                                <td class="ps-3 fw-semibold">{{ $order->order_number }}</td>
                                <td>{{ $order->store_name ?: '—' }}</td>
                                <td>{{ $order->created_at?->format('M d, Y h:i A') ?: '—' }}</td>
                                <td>{{ $order->reviewed_at?->format('M d, Y h:i A') ?: '—' }}</td>
                                <td class="text-wrap" style="min-width: 260px; white-space: pre-wrap">{{ $order->rejection_reason ?: 'No reason was provided.' }}</td>
                                <td class="text-end pe-3">
                                    <a class="btn btn-sm btn-outline-primary" href="{{ route('inventory-transactions.fully-booked.attachment', $order) }}" target="_blank" rel="noopener">
                                        <i class="fa-solid fa-eye me-1"></i>View order
                                    </a>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    @else
        <div class="alert alert-info">You do not have any rejected Fully Booked orders.</div>
    @endif

    <div class="mt-4">{{ $orders->links() }}</div>
</div>
@endsection
