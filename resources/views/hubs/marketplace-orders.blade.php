@extends('layouts.app')

@section('content')
<div class="container-fluid p-4">
    <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-4">
        <div>
            <h2 class="fw-bold text-primary mb-1">{{ ucfirst($channel) }} Approved Orders</h2>
            <p class="text-muted mb-0">{{ $hub->name }} · Orders approved by inventory</p>
        </div>
        <a href="{{ route('hub.dashboard', $hub->id) }}" class="btn btn-outline-secondary">
            <i class="fa-solid fa-arrow-left me-1"></i> Store Hub
        </a>
    </div>

    <section class="panel p-3 p-lg-4">
        @if($orders->isEmpty())
            <div class="empty-state">No approved {{ ucfirst($channel) }} orders found.</div>
        @else
            <div class="table-responsive">
                <table class="table align-middle mb-0">
                    <thead>
                        <tr>
                            <th>Order</th>
                            <th>Customer</th>
                            <th>Order date</th>
                            <th>Items</th>
                            <th class="text-end">Order total</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($orders as $order)
                            <tr>
                                <td class="fw-semibold">{{ $order->order_number ?: 'Sale #'.$order->id }}</td>
                                <td>{{ $order->customer_name ?: '—' }}</td>
                                <td>{{ $order->order_date?->format('M d, Y') ?? '—' }}</td>
                                <td>
                                    @foreach($order->items as $item)
                                        <div>{{ $item->product?->name ?: 'Unavailable product' }} <span class="text-muted">× {{ $item->quantity }}</span></div>
                                    @endforeach
                                </td>
                                <td class="text-end text-nowrap">₱{{ number_format((float) ($order->grand_total ?: $order->total_amount), 2) }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            @if($orders->hasPages())
                <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mt-3">
                    {{ $orders->links() }}
                </div>
            @endif
        @endif
    </section>
</div>
@endsection
