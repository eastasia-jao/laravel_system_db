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
                            <th>Order Number</th>
                            <th>Customer Name</th>
                            <th>Order Date</th>
                            <th class="text-end">Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($orders as $order)
                            <tr>
                                <td class="fw-semibold">{{ $order->order_number ?: 'Sale #'.$order->id }}</td>
                                <td>{{ $order->customer_name ?: '—' }}</td>
                                <td>{{ $order->order_date?->format('M d, Y') ?? '—' }}</td>
                                <td class="text-end">
                                    <button type="button" class="btn btn-sm btn-outline-primary text-nowrap" data-bs-toggle="modal" data-bs-target="#marketplace-order-items-{{ $order->id }}">
                                        <i class="fa-solid fa-eye me-1"></i>View Order Items
                                    </button>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            @foreach($orders as $order)
                <div class="modal fade" id="marketplace-order-items-{{ $order->id }}" tabindex="-1" aria-labelledby="marketplace-order-items-title-{{ $order->id }}" aria-hidden="true">
                    <div class="modal-dialog modal-lg modal-dialog-scrollable">
                        <div class="modal-content">
                            <div class="modal-header">
                                <div>
                                    <h5 class="modal-title fw-bold" id="marketplace-order-items-title-{{ $order->id }}">Order Items</h5>
                                    <div class="small text-muted">{{ $order->order_number ?: 'Sale #'.$order->id }} · {{ $order->customer_name ?: '—' }}</div>
                                    <div class="small text-muted">Payment method: {{ str_replace('_', ' ', $order->mode_of_payment ?: $order->custom_mop ?: 'Not recorded') }}</div>
                                </div>
                                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                            </div>
                            <div class="modal-body">
                                <div class="table-responsive">
                                    <table class="table align-middle">
                                        <thead class="table-light">
                                            <tr>
                                                <th>Product</th>
                                                <th class="text-center">Quantity</th>
                                                <th class="text-end">Unit Price</th>
                                                <th class="text-end">Line Total</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            @forelse($order->items as $item)
                                                <tr>
                                                    <td>{{ $item->product?->name ?: 'Unavailable product' }}</td>
                                                    <td class="text-center">{{ number_format($item->quantity) }}</td>
                                                    <td class="text-end text-nowrap">₱{{ number_format((float) $item->unit_price, 2) }}</td>
                                                    <td class="text-end text-nowrap">₱{{ number_format((float) $item->line_total, 2) }}</td>
                                                </tr>
                                            @empty
                                                <tr><td colspan="4" class="text-center text-muted py-4">No items recorded for this order.</td></tr>
                                            @endforelse
                                        </tbody>
                                        <tfoot>
                                            <tr>
                                                <th colspan="3" class="text-end">Order Total</th>
                                                <th class="text-end text-nowrap">₱{{ number_format((float) ($order->grand_total ?: $order->total_amount), 2) }}</th>
                                            </tr>
                                        </tfoot>
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
            @if($orders->hasPages())
                <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mt-3">
                    {{ $orders->links() }}
                </div>
            @endif
        @endif
    </section>
</div>
@endsection
