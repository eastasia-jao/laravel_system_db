@extends('layouts.app')

@section('content')
<style>
    .rejected-sales-page {
        max-width: 1100px;
    }
    .rejected-sale-card {
        border: 1px solid #e5eaf1 !important;
    }
    .rejected-sale-card .card-body {
        padding: 1rem 1.15rem;
    }
    .rejected-sale-card h5 {
        font-size: 1rem;
    }
    .rejected-sale-card .alert {
        padding: .65rem .8rem;
        font-size: .875rem;
    }
    .order-slip-frame {
        width: 100%;
        height: 70vh;
        border: 0;
        background: #f8fafc;
    }
</style>
<div class="container rejected-sales-page p-4">
    <div class="d-flex flex-wrap justify-content-between align-items-center gap-3 mb-3">
        <div>
            <h2 class="fw-bold text-danger mb-1"><i class="fa-solid fa-circle-xmark me-2"></i>Rejected Sales</h2>
            <p class="text-muted small mb-0">Review corrections requested by Inventory Staff.</p>
        </div>
        <a href="{{ $returnHubId ? route('hub.dashboard', $returnHubId) : route('dashboard') }}" class="btn btn-outline-secondary"><i class="fa-solid fa-arrow-left me-1"></i>{{ $returnHubId ? 'Back to Store Hub' : 'Back' }}</a>
    </div>

    @forelse($rejectedSales as $sale)
        <article class="card rejected-sale-card shadow-sm rounded-3 mb-2">
            <div class="card-body">
                <div class="d-flex flex-wrap justify-content-between gap-3">
                    <div>
                        <i class="fa-solid fa-circle-xmark text-danger me-1" aria-hidden="true"></i>
                        <span class="badge bg-danger text-uppercase">{{ $sale->sales_channel }}</span>
                        <h5 class="mt-2 mb-1">{{ $sale->customer_name ?: 'N/A' }}</h5>
                        <div class="small text-muted">
                            {{ $sale->invoice_number ?: 'Order #'.$sale->id }} · {{ $sale->storeHub?->name ?? 'N/A' }}
                            @if(in_array(auth()->user()?->role, ['admin', 'inventory_staff'], true))
                                · Submitted by {{ $sale->submittedBy?->name ?? 'N/A' }}
                            @endif
                        </div>
                    </div>
                    <div class="text-md-end small text-muted">
                        Rejected {{ optional($sale->rejected_at)->format('M d, Y h:i A') }}<br>
                        By {{ $sale->rejectedBy?->name ?? 'Inventory Staff' }}
                    </div>
                </div>
                <div class="alert alert-danger mt-3 mb-0">
                    <strong>Correction required:</strong> {{ $sale->rejection_reason ?: 'Please review the order details and submit it again.' }}
                </div>
                <div class="d-flex justify-content-end mt-3">
                    <button type="button" class="btn btn-sm btn-outline-primary" data-bs-toggle="modal" data-bs-target="#rejectedSaleModal{{ $sale->id }}">
                        <i class="fa-solid fa-eye me-1"></i> View
                    </button>
                </div>
            </div>
        </article>
    @empty
        <div class="card border-0 shadow-sm rounded-4">
            <div class="card-body text-center text-muted py-5">No rejected sales found.</div>
        </div>
    @endforelse

    @if($rejectedSales->hasPages())
        <div class="d-flex justify-content-center mt-4">
            {{ $rejectedSales->onEachSide(1)->links() }}
        </div>
    @endif
</div>

@foreach($rejectedSales as $sale)
    <div class="modal fade" id="rejectedSaleModal{{ $sale->id }}" data-bs-backdrop="static" data-bs-keyboard="false" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-lg modal-dialog-scrollable">
            <div class="modal-content">
                <div class="modal-header">
                    <div>
                        <h5 class="modal-title mb-1">Rejected Sale Details</h5>
                        <div class="small text-muted">{{ $sale->invoice_number ?: 'Order #'.$sale->id }}</div>
                    </div>
                </div>
                <div class="modal-body">
                    <div class="row g-3">
                        <div class="col-12">
                    <div class="row g-3 mb-4">
                        <div class="col-6 col-md-3">
                            <div class="small text-muted">Channel</div>
                            <div class="fw-semibold text-uppercase">{{ $sale->sales_channel }}</div>
                        </div>
                        <div class="col-6 col-md-3">
                            <div class="small text-muted">Customer</div>
                            <div class="fw-semibold">{{ $sale->customer_name ?: 'N/A' }}</div>
                        </div>
                        <div class="col-6 col-md-3">
                            <div class="small text-muted">Branch</div>
                            <div class="fw-semibold">{{ $sale->storeHub?->name ?? 'N/A' }}</div>
                        </div>
                        <div class="col-6 col-md-3">
                            <div class="small text-muted">Rejected</div>
                            <div class="fw-semibold">{{ optional($sale->rejected_at)->format('M d, Y h:i A') }}</div>
                        </div>
                    </div>

                    <h6 class="fw-bold">Order Items</h6>
                    <div class="table-responsive">
                        <table class="table table-sm table-bordered align-middle">
                            <thead class="table-light">
                                <tr>
                                    <th>Product</th>
                                    <th class="text-center">Qty</th>
                                    <th class="text-end">Unit Price</th>
                                    <th class="text-end">Total</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($sale->items ?? [] as $item)
                                    @php
                                        $productName = data_get($item, 'product_name')
                                            ?: data_get($item, 'name')
                                            ?: data_get($item, 'title')
                                            ?: data_get($item, 'item_name')
                                            ?: 'Product #'.(data_get($item, 'product_id') ?: data_get($item, 'item_id') ?: 'N/A');
                                        $quantity = (float) (data_get($item, 'quantity') ?: 0);
                                        $unitPrice = (float) (data_get($item, 'unit_price') ?: data_get($item, 'price') ?: 0);
                                    @endphp
                                    <tr>
                                        <td>{{ $productName }}</td>
                                        <td class="text-center">{{ rtrim(rtrim(number_format($quantity, 2), '0'), '.') }}</td>
                                        <td class="text-end">₱{{ number_format($unitPrice, 2) }}</td>
                                        <td class="text-end">₱{{ number_format($quantity * $unitPrice, 2) }}</td>
                                    </tr>
                                @empty
                                    <tr><td colspan="4" class="text-center text-muted">No item details available.</td></tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>

                    <div class="d-flex justify-content-end mb-3">
                        <span class="fw-bold">Grand Total: ₱{{ number_format((float) ($sale->grand_total ?: $sale->total ?: 0), 2) }}</span>
                    </div>

                    <div class="alert alert-danger mb-0">
                        <strong>Correction required:</strong>
                        {{ $sale->rejection_reason ?: 'Please review the order details and submit it again.' }}
                    </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    @if($sale->order_slip)
                        <button type="button" class="btn btn-outline-secondary" data-bs-toggle="modal" data-bs-target="#rejectedOrderSlipModal{{ $sale->id }}">
                            <i class="fa-solid fa-file-image me-1"></i> View Order Slip
                        </button>
                    @endif
                    <button type="button" class="btn btn-primary" data-bs-dismiss="modal">Close</button>
                </div>
            </div>
        </div>
    </div>

    @if($sale->order_slip)
        <div class="modal fade" id="rejectedOrderSlipModal{{ $sale->id }}" data-reopen-modal="#rejectedSaleModal{{ $sale->id }}" tabindex="-1" aria-hidden="true">
            <div class="modal-dialog modal-xl modal-dialog-centered">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title">Order Slip - {{ $sale->invoice_number ?: 'Order #'.$sale->id }}</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body p-0">
                        <iframe class="order-slip-frame" src="{{ route('sales.rejected-order-slip', $sale->id) }}" title="Order slip"></iframe>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-primary" data-bs-dismiss="modal">Close Order Slip</button>
                    </div>
                </div>
            </div>
        </div>
    @endif
@endforeach

@push('scripts')
<script>
    document.querySelectorAll('[data-reopen-modal]').forEach(function (orderSlipModal) {
        orderSlipModal.addEventListener('hidden.bs.modal', function () {
            var parentModal = document.querySelector(orderSlipModal.dataset.reopenModal);
            if (parentModal) {
                bootstrap.Modal.getOrCreateInstance(parentModal).show();
            }
        });
    });
</script>
@endpush
@endsection
