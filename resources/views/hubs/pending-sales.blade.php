@extends('layouts.app')

@section('content')

<style>
    /* Custom clean hover effect for table rows */
    .custom-table-row {
        transition: background-color 0.15s ease-in-out;
    }
    .custom-table-row:hover {
        background-color: rgba(0, 0, 0, 0.075);
    }
    .verification-card {
        border: 1px solid #e5eaf1;
        border-radius: 14px;
        background: #fff;
        padding: 1rem;
    }
    .verification-card + .verification-card {
        margin-top: .85rem;
    }
    .verification-card .meta-label {
        color: #64748b;
        font-size: .7rem;
        text-transform: uppercase;
        font-weight: 700;
    }
    .verification-card .meta-value {
        font-size: .88rem;
        font-weight: 600;
    }
    .review-items-modal .modal-dialog { max-width: min(1180px, calc(100vw - 2rem)); }
    .review-items-modal .modal-header { padding: .9rem 1.25rem; }
    .review-items-modal .modal-title { font-size: 1rem; font-weight: 700; }
    .review-items-modal .modal-body { padding: 1.25rem; background: #f8fafc; }
    .review-items-modal .order-slip-panel,
    .review-items-modal .order-summary-panel { height: 100%; border: 1px solid #e2e8f0; border-radius: 12px; background: #fff; padding: 1rem; }
    .review-items-modal .order-slip-preview { display: flex; width: 100%; min-height: 300px; height: clamp(300px, 55vh, 600px); align-items: center; justify-content: center; overflow: hidden; border-radius: 8px; background: #f1f5f9; }
    .review-items-modal .order-slip-image { display: block; width: 100%; height: 100%; object-fit: contain; object-position: center; }
    .review-items-modal .order-slip-pdf { display: block; width: 100%; height: 100%; border: 0; background: #fff; }
    .review-items-modal .order-summary-panel { background: linear-gradient(145deg, #fff, #f8fbff); }
    .review-items-modal .order-summary-title { color: #64748b; font-size: .72rem; font-weight: 700; letter-spacing: .06em; text-transform: uppercase; }
    .review-items-modal .order-summary-line { display: flex; justify-content: space-between; gap: 1rem; padding: .45rem 0; border-bottom: 1px solid #edf2f7; font-size: .84rem; }
    .review-items-modal .order-summary-total { display: flex; justify-content: space-between; align-items: baseline; gap: 1rem; margin-top: .6rem; padding-top: .75rem; border-top: 2px solid #e2e8f0; }
    .review-items-modal .order-summary-total strong { color: #15803d; font-size: 1.25rem; }
    .review-items-modal .order-items-heading { display: flex; flex-wrap: wrap; align-items: center; justify-content: space-between; gap: .5rem; margin: 1.25rem 0 .65rem; }
    .review-items-modal .order-items-heading h6 { margin: 0; font-weight: 700; }
    .review-items-modal .order-items-table { min-width: 920px; margin-bottom: 0; }
    .review-items-modal .order-items-table th { padding: .7rem .75rem; color: #475569; font-size: .72rem; text-transform: uppercase; letter-spacing: .035em; white-space: nowrap; }
    .review-items-modal .order-items-table td { padding: .75rem; font-size: .84rem; vertical-align: middle; }
    .review-items-modal .order-product-name { min-width: 220px; font-weight: 600; line-height: 1.4; overflow-wrap: anywhere; }
    .review-items-modal .order-product-code { display: block; margin-top: .2rem; color: #64748b; font-size: .72rem; font-weight: 500; }
    .review-items-modal .order-items-table .stock-status-cell { min-width: 145px; }
    .review-items-modal .order-items-table .stock-status-cell .btn { margin-top: .4rem; }
    @media (max-width: 575.98px) {
        .review-items-modal .modal-dialog { max-width: calc(100vw - 1rem); margin: .5rem auto; }
        .review-items-modal .modal-body { padding: .75rem; }
        .review-items-modal .order-slip-panel, .review-items-modal .order-summary-panel { padding: .8rem; }
    }
</style>

<div class="container p-4">
    <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-4">
        <h2 class="fw-bold text-primary">
            <i class="fa-solid fa-clipboard-check me-2"></i> Inventory Verification Queue
        </h2>
        <a href="{{ isset($hub) ? route('hub.dashboard', $hub->id) : route('hub.dashboard', auth()->user()->store_hub_id ?? 1) }}" class="btn btn-outline-secondary">
            <i class="fa-solid fa-arrow-left me-1"></i> Back
        </a>
    </div>

    

    

    @if($replacementRequests->isNotEmpty())
        <div class="card shadow-sm border-0 mb-4">
            <div class="card-header bg-white py-3"><h5 class="mb-0 text-success"><i class="fa-solid fa-arrow-right-arrow-left me-2"></i>Replacement / Exchange Requests</h5></div>
            <div class="card-body">
                @foreach($replacementRequests as $replacement)
                    @php
                        $exchangeLines = $replacement->exchange_lines ?? collect([$replacement]);
                        $returnedQty = (int) $exchangeLines->sum('quantity');
                        $credit = (float) ($replacement->exchange_credit ?: ((float) $replacement->original_unit_price * $returnedQty));
                        $charge = (float) ($replacement->exchange_total ?: $exchangeLines->sum(fn ($line) => (float) $line->replacement_unit_price * (1 - ((float) ($line->replacement_discount_percentage ?? 0) / 100)) * (int) ($line->replacement_quantity ?: $line->quantity)));
                        $difference = $charge - $credit;
                        $replacementChannel = $replacement->replacement_channel ?: 'wholesale';
                        $exchangeStockReady = (bool) ($replacement->exchange_stock_ready ?? false);
                        $exchangeReturnReady = (bool) ($replacement->exchange_return_ready ?? false);
                    @endphp
                    <div class="verification-card">
                        <div class="d-flex flex-wrap justify-content-between gap-3">
                            <div>
                                <span class="badge bg-warning text-dark mb-2">EXCHANGE AWAITING VERIFICATION</span>
                                <h5 class="mb-1">{{ $replacement->transaction?->order_number }} · {{ $replacement->transaction?->customer_name }}</h5>
                                <div class="small text-muted">{{ $replacement->transaction?->storeHub?->name }} · {{ ucfirst(str_replace('_', ' ', $replacementChannel)) }} · Requested by {{ $replacement->creator?->name ?? 'N/A' }}</div>
                            </div>
                            <div class="text-end"><div class="small text-muted">Estimated total adjustment</div><div class="fw-bold {{ $difference > 0 ? 'text-danger' : 'text-success' }}">{{ $difference >= 0 ? '+' : '−' }}₱{{ number_format(abs($difference), 2) }}</div></div>
                        </div>
                        <div class="row g-3 mt-2">
                            <div class="col-md-5"><div class="meta-label">Return to inventory</div><div class="meta-value">{{ $returnedQty }} × {{ $replacement->originalProduct?->name }}</div><small class="text-muted">Exchange credit ₱{{ number_format($credit, 2) }}</small></div>
                            <div class="col-md-5">
                                <div class="meta-label">Release to customer</div>
                                @if($replacementChannel === 'online')
                                    <button type="button" class="btn btn-sm {{ $exchangeStockReady ? 'btn-outline-success' : 'btn-outline-warning' }} fw-bold mt-2"
                                            data-bs-toggle="modal" data-bs-target="#onlineReplacementInventory{{ $replacement->id }}">
                                        <i class="fa-solid fa-list-check me-1"></i> Review Inventory ({{ $exchangeLines->count() }})
                                    </button>
                                    <div class="small text-muted mt-1">Review replacement items, stock, and totals.</div>
                                @else
                                    @foreach($exchangeLines as $line)
                                        <div class="border rounded-3 p-2 mt-2">
                                            <div class="meta-value">{{ $line->replacement_quantity ?: $line->quantity }} × {{ $line->replacementProduct?->name }}</div>
                                            <small class="text-muted d-block">{{ $line->replacement_available_stock }} available · ₱{{ number_format((float) $line->replacement_unit_price * (1 - ((float) ($line->replacement_discount_percentage ?? 0) / 100)) * (int) ($line->replacement_quantity ?: $line->quantity), 2) }}</small>
                                            @if((int) $line->replacement_available_stock < (int) ($line->replacement_quantity ?: $line->quantity))
                                                <div class="alert alert-warning small py-2 px-2 mt-2 mb-0">
                                                    Not enough {{ $replacementChannel === 'walk_in' ? 'unallocated physical' : 'allocated '.ucfirst(str_replace('_', ' ', $replacementChannel)) }} stock for this item.
                                                    @if($replacementChannel !== 'walk_in')
                                                        <a href="{{ route('stock-allocation.index', ['hub_id' => $replacement->transaction?->store_hub_id, 'search' => $line->replacementProduct?->item_id, 'product_id' => $line->replacement_product_id, 'return_to' => 'verification-queue', 'return_hub_id' => $replacement->transaction?->store_hub_id]) }}"
                                                           class="btn btn-sm btn-outline-primary text-nowrap d-block mt-2"
                                                           title="Open this product in Stock Allocation">
                                                            <i class="fa-solid fa-layer-group me-1"></i> Open Stock Allocation
                                                        </a>
                                                    @endif
                                                </div>
                                            @endif
                                        </div>
                                    @endforeach
                                    <div class="small fw-semibold mt-2">Replacement total (including shipping) ₱{{ number_format($charge, 2) }} · Additional payment ₱{{ number_format(max(0, $difference), 2) }}</div>
                                    @if((float) ($replacement->replacement_shipping_fee_amount ?? 0) > 0)
                                        <div class="small text-muted">Replacement shipping ({{ $replacement->replacement_shipping_fee_type ?: 'Custom Amount' }}): ₱{{ number_format($replacement->replacement_shipping_fee_amount, 2) }}</div>
                                    @else
                                        <div class="small text-muted">Replacement shipping: Free delivery</div>
                                    @endif
                                @endif
                            </div>
                            <div class="col-md-2"><div class="meta-label">Reason</div><div class="meta-value">{{ $replacement->reason ?: '—' }}</div></div>
                        </div>
                        @if((float) ($replacement->additional_payment_due ?? 0) > 0)
                            <div class="alert alert-info small mt-3 mb-0">
                                <strong>Additional payment:</strong> ₱{{ number_format($replacement->exchange_payment_amount, 2) }} via {{ str_replace('_', ' ', $replacement->exchange_payment_method ?: 'Unspecified') }}
                                @if($replacement->exchange_custom_mop)<span class="d-block">Specified MOP: {{ $replacement->exchange_custom_mop }}</span>@endif
                                @if($replacement->exchange_bank_name)<span class="d-block">Bank: {{ $replacement->exchange_bank_name === 'OTHERS' ? $replacement->exchange_custom_bank_name : str_replace('_', ' ', $replacement->exchange_bank_name) }}</span>@endif
                                @if($replacement->exchange_check_number)<span class="d-block">Check #: {{ $replacement->exchange_check_number }} · Date: {{ optional($replacement->exchange_check_date)->format('M d, Y') }}</span>@endif
                                @if($replacement->exchange_payment_reference)<span class="d-block">Reference: {{ $replacement->exchange_payment_reference }}</span>@endif
                                @if($replacement->exchange_payment_proofs)<span class="d-block">{{ count($replacement->exchange_payment_proofs) }} proof file(s) attached</span>@endif
                            </div>
                        @endif
                        @if($replacement->replacement_order_slip)
                            <div class="mt-3">
                                <a href="{{ \Illuminate\Support\Facades\Storage::disk('public')->url($replacement->replacement_order_slip) }}" target="_blank" rel="noopener" class="btn btn-sm btn-outline-primary">
                                    <i class="fa-solid fa-file-arrow-up me-1"></i> Open replacement order slip
                                </a>
                            </div>
                        @endif
                        <div class="d-flex justify-content-end gap-2 mt-3">
                            <form method="POST" action="{{ route('wholesale-replacements.approve', $replacement->id) }}">@csrf
                                <button class="btn btn-sm btn-success" @disabled(!$exchangeStockReady || !$exchangeReturnReady)><i class="fa-solid fa-check me-1"></i>Verify & Apply</button>
                            </form>
                            <button type="button" class="btn btn-sm btn-outline-danger" data-bs-toggle="modal" data-bs-target="#rejectReplacement{{ $replacement->id }}">Reject</button>
                        </div>
                        @if(!$exchangeReturnReady)
                            <div class="alert alert-warning small mt-3 mb-0">
                                Inventory must record the original item as received before this replacement can be approved or released.
                            </div>
                        @endif
                    </div>
                    @if($replacementChannel === 'online')
                        <div class="modal fade review-items-modal" id="onlineReplacementInventory{{ $replacement->id }}" tabindex="-1" aria-hidden="true">
                            <div class="modal-dialog modal-xl modal-dialog-centered modal-dialog-scrollable">
                                <div class="modal-content">
                                    <div class="modal-header bg-primary text-white">
                                        <h5 class="modal-title">Replacement Inventory Review - {{ $replacement->transaction?->order_number }}</h5>
                                        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                                    </div>
                                    <div class="modal-body bg-light">
                                        @if(!$exchangeStockReady)
                                            <div class="alert alert-warning">
                                                <div class="fw-bold mb-1"><i class="fa-solid fa-triangle-exclamation me-1"></i> This replacement is awaiting stock</div>
                                                <div>Verification is disabled until every replacement item has enough allocated Online stock. No stock will be deducted.</div>
                                            </div>
                                        @endif
                                        <div class="order-items-heading">
                                            <h6>Replacement Items</h6>
                                            <span class="badge text-bg-light">{{ $exchangeLines->count() }} item line(s)</span>
                                        </div>
                                        <div class="table-responsive border rounded-3 bg-white mb-3">
                                            <table class="table table-hover align-middle order-items-table">
                                                <thead class="table-light">
                                                    <tr>
                                                        <th>Product</th>
                                                        <th class="text-center">Requested</th>
                                                        <th class="text-center">Available<br>(Online)</th>
                                                        <th class="text-center">Stock Status</th>
                                                        <th class="text-end">Unit Price</th>
                                                        <th class="text-center">Discount</th>
                                                        <th class="text-end">Line Total</th>
                                                    </tr>
                                                </thead>
                                                <tbody>
                                                    @foreach($exchangeLines as $line)
                                                        @php
                                                            $requestedQuantity = (int) ($line->replacement_quantity ?: $line->quantity);
                                                            $discountPercentage = (float) ($line->replacement_discount_percentage ?? 0);
                                                            $unitPrice = (float) $line->replacement_unit_price;
                                                            $lineTotal = round($unitPrice * (1 - ($discountPercentage / 100)) * $requestedQuantity, 2);
                                                            $stockShortage = max(0, $requestedQuantity - (int) $line->replacement_available_stock);
                                                        @endphp
                                                        <tr>
                                                            <td class="order-product-name">
                                                                {{ $line->replacementProduct?->name ?? 'Product #'.$line->replacement_product_id }}
                                                                @if($line->replacementProduct?->item_id)<span class="order-product-code">{{ $line->replacementProduct->item_id }}</span>@endif
                                                            </td>
                                                            <td class="text-center">{{ $requestedQuantity }}</td>
                                                            <td class="text-center">{{ $line->replacement_available_stock }}</td>
                                                            <td class="text-center stock-status-cell">
                                                                @if($stockShortage > 0)
                                                                    <span class="badge bg-danger d-inline-block mb-2">SHORT {{ $stockShortage }}</span>
                                                                    @can('manage-inventory')
                                                                        <a href="{{ route('stock-allocation.index', ['hub_id' => $replacement->transaction?->store_hub_id, 'search' => $line->replacementProduct?->item_id, 'product_id' => $line->replacement_product_id, 'return_to' => 'verification-queue', 'return_hub_id' => $replacement->transaction?->store_hub_id]) }}"
                                                                           class="btn btn-sm btn-outline-primary text-nowrap d-block"
                                                                           title="Open this product in Stock Allocation">
                                                                            <i class="fa-solid fa-layer-group me-1"></i> Stock Allocation
                                                                        </a>
                                                                    @endcan
                                                                @else
                                                                    <span class="badge bg-success">READY</span>
                                                                @endif
                                                            </td>
                                                            <td class="text-end">₱{{ number_format($unitPrice, 2) }}</td>
                                                            <td class="text-center text-danger fw-semibold">{{ $discountPercentage > 0 ? number_format($discountPercentage, 2).'%' : '—' }}</td>
                                                            <td class="text-end fw-bold">₱{{ number_format($lineTotal, 2) }}</td>
                                                        </tr>
                                                    @endforeach
                                                </tbody>
                                            </table>
                                        </div>
                                        <div class="order-summary-panel">
                                            <div class="order-summary-title">Replacement Summary</div>
                                            <div class="order-summary-line"><span class="text-muted">Replacement total, including shipping</span><strong>₱{{ number_format($charge, 2) }}</strong></div>
                                            <div class="order-summary-line"><span class="text-muted">Replacement shipping</span><strong>{{ (float) ($replacement->replacement_shipping_fee_amount ?? 0) > 0 ? '₱'.number_format($replacement->replacement_shipping_fee_amount, 2) : 'Free delivery' }}</strong></div>
                                            <div class="order-summary-line"><span class="text-muted">Additional payment</span><strong>₱{{ number_format(max(0, $difference), 2) }}</strong></div>
                                        </div>
                                    </div>
                                    <div class="modal-footer bg-white">
                                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                                        <form method="POST" action="{{ route('wholesale-replacements.approve', $replacement->id) }}">@csrf
                                            <button class="btn btn-success" @disabled(!$exchangeStockReady || !$exchangeReturnReady) title="{{ $exchangeStockReady && $exchangeReturnReady ? 'Verify and apply this replacement' : 'Restock the insufficient items before verification' }}">
                                                <i class="fa-solid {{ $exchangeStockReady && $exchangeReturnReady ? 'fa-check' : 'fa-lock' }} me-1"></i>{{ $exchangeStockReady && $exchangeReturnReady ? 'Verify & Apply' : 'Awaiting Stock' }}
                                            </button>
                                        </form>
                                    </div>
                                </div>
                            </div>
                        </div>
                    @endif
                    <div class="modal fade" id="rejectReplacement{{ $replacement->id }}" tabindex="-1" aria-hidden="true">
                        <div class="modal-dialog modal-dialog-centered"><form method="POST" action="{{ route('wholesale-replacements.reject', $replacement->id) }}" class="modal-content">@csrf
                            <div class="modal-header"><h5 class="modal-title">Reject Replacement Request</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
                            <div class="modal-body"><label class="form-label">Reason</label><textarea name="rejection_reason" class="form-control" required maxlength="2000"></textarea></div>
                            <div class="modal-footer"><button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button><button class="btn btn-danger">Reject Request</button></div>
                        </form></div>
                    </div>
                @endforeach
            </div>
        </div>
    @endif

    <div class="card shadow-sm border-0">
        <div class="card-body">
            @if($pendingSales->isEmpty())
                <div class="text-center py-5 text-muted">
                    <i class="fa-solid fa-box-open fa-3x mb-3"></i>
                    <p class="fs-5">No channel orders are waiting for inventory verification.</p>
                </div>
            @else
                @foreach($pendingSales as $sale)
                    @php
                        $inventory = $sale->inventory_availability;
                        $isWholesale = str_replace(['-', ' '], '_', strtolower((string) $sale->sales_channel)) === 'wholesale';
                        $isTiktok = strtolower((string) $sale->sales_channel) === 'tiktok';
                        $rowGrandTotal = $isTiktok && $sale->sales_after_transaction_fee !== null
                            ? (float) $sale->sales_after_transaction_fee
                            : (float) ($sale->grand_total ?? 0);
                    @endphp
                    <div class="verification-card">
                        <div class="d-flex flex-wrap justify-content-between align-items-start gap-3">
                            <div>
                                <div class="d-flex flex-wrap align-items-center gap-2 mb-2">
                                    <span class="badge bg-info text-dark text-uppercase">{{ $sale->sales_channel }}</span>
                                    @if($inventory['ready'])
                                        <span class="badge bg-success">READY</span>
                                    @else
                                        <span class="badge bg-warning text-dark">AWAITING STOCK</span>
                                    @endif
                                    @if(in_array(str_replace(['-', ' '], '_', strtolower((string) $sale->sales_channel)), ['walk_in', 'walkin'], true))
                                        <span class="badge bg-info text-dark">Awaiting Inventory Verification</span>
                                    @endif
                                </div>
                                <h5 class="mb-1">{{ $sale->customer_name ?: 'N/A' }}</h5>
                                <div class="small text-muted">{{ $sale->invoice_number ?: 'Order #'.$sale->id }}</div>
                            </div>
                            <div class="text-md-end">
                                <div class="small text-muted">Total Amount</div>
                                <div class="fs-5 fw-bold text-success">₱{{ number_format($rowGrandTotal, 2) }}</div>
                            </div>
                        </div>
                        <div class="row g-3 mt-2">
                            <div class="col-6 col-md-3"><div class="meta-label">Store / Branch</div><div class="meta-value">{{ $sale->storeHub?->name ?? 'N/A' }}</div></div>
                            <div class="col-6 col-md-3"><div class="meta-label">Submitted By</div><div class="meta-value">{{ $sale->submittedBy?->name ?? 'N/A' }}</div></div>
                            <div class="col-6 col-md-3"><div class="meta-label">Order Date</div><div class="meta-value">{{ \Carbon\Carbon::parse($sale->placed_order_date)->format('M d, Y') }}</div></div>
                            <div class="col-6 col-md-3"><div class="meta-label">Submitted At</div><div class="meta-value">{{ optional($sale->created_at)->format('M d, Y h:i A') }}</div></div>
                        </div>
                        @if($isWholesale)
                            <div class="d-flex flex-wrap gap-2 mt-3">
                                <span class="small text-muted">Payment:</span><span class="badge bg-secondary">{{ strtoupper($sale->payment_status ?? 'unpaid') }}</span>
                                <span class="small text-muted ms-2">Delivery:</span><span class="badge bg-secondary">{{ strtoupper($sale->delivery_status ?? 'pending') }}</span>
                            </div>
                        @endif
                        <div class="d-flex flex-wrap justify-content-end gap-2 mt-3">
                            <button type="button" class="btn btn-sm {{ $inventory['ready'] ? 'btn-success' : 'btn-warning' }} fw-bold" data-bs-toggle="modal" data-bs-target="#itemsModal{{ $sale->id }}">
                                <i class="fa-solid fa-list-check me-1"></i> Review Inventory ({{ count($sale->items) }})
                            </button>
                            <button type="button" class="btn btn-sm btn-outline-danger" data-bs-toggle="modal" data-bs-target="#rejectModal{{ $sale->id }}">
                                <i class="fa-solid fa-circle-xmark me-1" aria-hidden="true"></i> Reject
                            </button>
                        </div>
                    </div>
                @endforeach

                <div class="mt-3">
                    {{ $pendingSales->links() }}
                </div>
            @endif
        </div>
    </div>
</div>

{{-- Render Modals OUTSIDE the table container --}}
@if(!$pendingSales->isEmpty())
    @foreach($pendingSales as $sale)
        @php $inventory = $sale->inventory_availability; @endphp
        <div class="modal fade review-items-modal" id="itemsModal{{ $sale->id }}" tabindex="-1" aria-hidden="true">
            <div class="modal-dialog modal-xl modal-dialog-centered modal-dialog-scrollable">
                <div class="modal-content">
                    <div class="modal-header bg-primary text-white">
                        <h5 class="modal-title">Order Items & Summary - {{ $sale->invoice_number ?: 'Order #' . $sale->id }}</h5>
                        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        @if($sale->quotation_proofs || $sale->walkin_payment_proofs)
                            <div class="border rounded p-3 mb-3">
                                @foreach(['quotation_proofs' => 'Proof of quotation', 'walkin_payment_proofs' => 'Proof of payment'] as $proofField => $proofLabel)
                                    @if($sale->{$proofField})
                                        <div class="fw-semibold">{{ $proofLabel }}</div>
                                        @foreach($sale->{$proofField} as $proofIndex => $attachment)
                                            <a class="d-inline-block me-2 mb-2" href="{{ \Illuminate\Support\Facades\Storage::disk('public')->url($attachment) }}" target="_blank" rel="noopener">Open attachment {{ $proofIndex + 1 }}</a>
                                        @endforeach
                                    @endif
                                @endforeach
                            </div>
                        @endif
                        @php
                            $orderDiscount = $sale->discount ?? $sale->total_discount ?? 0;
                            $deliveryFee = $sale->delivery_fee ?? 0;
                            $channel = strtolower($sale->sales_channel ?? '');
                            $isTiktok = ($channel === 'tiktok');
                            $isMarketplace = in_array($channel, ['shopee', 'lazada', 'tiktok']);
                        @endphp
                        @if($sale->order_slip)
                            @php $orderSlipIsPdf = strtolower(pathinfo($sale->order_slip, PATHINFO_EXTENSION)) === 'pdf'; @endphp
                            <div class="order-slip-panel mb-3">
                                <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-2">
                                    <h6 class="fw-bold mb-0">Order Attachment</h6>
                                    <a href="{{ route('sales.order-slip', $sale->id) }}" target="_blank" rel="noopener" class="btn btn-sm btn-outline-primary">Open full size</a>
                                </div>
                                <div class="order-slip-preview">
                                    @if($orderSlipIsPdf)
                                        <iframe src="{{ route('sales.order-slip', $sale->id) }}" class="order-slip-pdf" title="Order attachment for {{ $sale->invoice_number ?: 'Order #'.$sale->id }}"></iframe>
                                    @else
                                        <img src="{{ route('sales.order-slip', $sale->id) }}" class="order-slip-image" alt="Order attachment for {{ $sale->invoice_number ?: 'Order #'.$sale->id }}" loading="lazy">
                                    @endif
                                </div>
                            </div>
                        @endif
                        @if(!$inventory['ready'])
                            <div class="alert alert-warning">
                                <div class="fw-bold mb-1">
                                    <i class="fa-solid fa-triangle-exclamation me-1"></i> This order is awaiting stock
                                </div>
                                <div>Verification is disabled until every item has enough available stock. No stock will be deducted.</div>
                            </div>
                        @endif

                        <div class="order-items-heading">
                            <h6>Order Items</h6>
                            <span class="badge text-bg-light">{{ count($sale->items) }} item line(s)</span>
                        </div>
                        <div class="table-responsive border rounded-3 bg-white mb-3">
                            <table class="table table-hover align-middle order-items-table">
                                <thead class="table-light">
                                    <tr>
                                        <th>Product</th>
                                        <th class="text-center">Requested</th>
                                        <th class="text-center">Available<br>({{ ucfirst($sale->sales_channel) }})</th>
                                        <th class="text-center">Stock Status</th>
                                        <th class="text-end">Unit Price</th>
                                        <th class="text-center">Discount</th>
                                        <th class="text-end">Line Total</th>
                                    </tr>
                                </thead>
                                @php $subtotal = 0; @endphp
                                <tbody>
                                    @foreach($sale->items as $item)
                                        @php
                                            $prod = null;

                                            $pluck = function ($keys) use ($item) {
                                                foreach ($keys as $key) {
                                                    $value = is_object($item) ? ($item->{$key} ?? null) : ($item[$key] ?? null);
                                                    if (is_string($value) && trim($value) !== '') {
                                                        return trim($value);
                                                    }
                                                }
                                                return null;
                                            };

                                            $productName = $pluck(['product_name', 'name', 'title', 'item_name']);

                                            $pId = is_object($item)
                                                ? ($item->product_id ?? $item->item_id ?? $item->id ?? null)
                                                : ($item['product_id'] ?? $item['item_id'] ?? $item['id'] ?? null);

                                            if ($pId) {
                                                $prod = \App\Models\Product::with('catalogProduct')->whereKey($pId)->first();
                                                if (! $prod) {
                                                    $prod = \App\Models\Product::with('catalogProduct')
                                                        ->whereHas('catalogProduct', fn ($query) => $query->where('item_id', $pId))
                                                        ->first();
                                                }
                                            }

                                            if (empty($productName)) {
                                                if ($pId) {
                                                    $productName = collect([
                                                        $prod->name ?? null,
                                                        $prod->product_name ?? null,
                                                        $prod->title ?? null,
                                                        $prod->item_name ?? null,
                                                        $prod->description ?? null,
                                                    ])->first(fn ($v) => is_string($v) && trim($v) !== '') ?? ('Product #' . $pId);
                                                } else {
                                                    $productName = 'Unknown Product';
                                                }
                                            }

                                            $quantity = is_object($item) 
                                                ? ($item->quantity ?? 0) 
                                                : ($item['quantity'] ?? 0);

                                            $stockDetails = $inventory['products'][(int) $pId] ?? [
                                                'available' => 0,
                                                'shortage' => $quantity,
                                            ];

                                            if ($stockDetails['shortage'] > 0 && $pId && !$prod) {
                                                $prod = \App\Models\Product::with('catalogProduct')->whereKey($pId)->first();
                                            }
                                                
                                            $storedPrice = is_object($item) 
                                                ? ($item->unit_price ?? $item->price ?? 0) 
                                                : ($item['unit_price'] ?? $item['price'] ?? 0);

                                            if ($storedPrice <= 0) {
                                                if ($pId) {
                                                    $storedPrice = $prod->sales_price ?? 0;
                                                }
                                            }
                                                
                                            $lineGross = $storedPrice * $quantity;
                                            $subtotal += $lineGross;

                                            $itemDiscountPercent = is_object($item) 
                                                ? ($item->discount_percentage ?? $item->discount_percent ?? 0) 
                                                : ($item['discount_percentage'] ?? $item['discount_percent'] ?? 0);

                                            if ($itemDiscountPercent > 0) {
                                                $calculatedDiscount = $lineGross * ($itemDiscountPercent / 100);
                                                $discountDisplay = $itemDiscountPercent . '%';
                                            } else {
                                                $calculatedDiscount = 0;
                                                $discountDisplay = '—';
                                            }

                                            $lineTotal = $lineGross - $calculatedDiscount;
                                        @endphp
                                        <tr>
                                            <td class="order-product-name">
                                                {{ $productName }}
                                                @if($prod?->item_id)<span class="order-product-code">{{ $prod->item_id }}</span>@endif
                                            </td>
                                            <td class="text-center">{{ $quantity }}</td>
                                            <td class="text-center">{{ $stockDetails['available'] }}</td>
                                            <td class="text-center stock-status-cell">
                                                @if($stockDetails['shortage'] > 0)
                                                    <span class="badge bg-danger d-inline-block mb-2">SHORT {{ $stockDetails['shortage'] }}</span>
                                                    @can('manage-inventory')
                                                        <a href="{{ route('stock-allocation.index', ['hub_id' => $sale->store_hub_id, 'search' => $prod?->item_id ?: $productName, 'product_id' => $prod?->id, 'return_to' => 'verification-queue', 'return_hub_id' => $sale->store_hub_id]) }}"
                                                           class="btn btn-sm btn-outline-primary text-nowrap d-block"
                                                           title="Open this product in Stock Allocation">
                                                            <i class="fa-solid fa-layer-group me-1"></i> Stock Allocation
                                                        </a>
                                                    @endcan
                                                @else
                                                    <span class="badge bg-success">READY</span>
                                                @endif
                                            </td>
                                            <td class="text-end">₱{{ number_format($storedPrice, 2) }}</td>
                                            <td class="text-center text-danger fw-semibold">
                                                {{ $discountDisplay }}
                                            </td>
                                            <td class="text-end fw-bold">₱{{ number_format($lineTotal, 2) }}</td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>

                        @if(!$isTiktok)
                            @php
                                $finalGrandTotal = ($sale->grand_total > 0)
                                    ? $sale->grand_total
                                    : (($subtotal - $orderDiscount) + $deliveryFee);
                            @endphp
                            <div class="row justify-content-end">
                                <div class="col-md-7 col-lg-6">
                                    <div class="order-summary-panel">
                                        <div class="order-summary-title">Order Summary</div>
                                        @if(!$isMarketplace)
                                            <div class="order-summary-line"><span class="text-muted">Subtotal</span><strong>₱{{ number_format($subtotal, 2) }}</strong></div>
                                        @endif
                                        @if($orderDiscount > 0)
                                            <div class="order-summary-line text-danger"><span>Discount</span><strong>-₱{{ number_format($orderDiscount, 2) }}</strong></div>
                                        @endif
                                        @if($deliveryFee > 0)
                                            <div class="order-summary-line"><span class="text-muted">Delivery Fee</span><strong>₱{{ number_format($deliveryFee, 2) }}</strong></div>
                                        @endif
                                        <div class="order-summary-total">
                                            <span class="fw-bold">Total Amount</span>
                                            <strong>₱{{ number_format($finalGrandTotal, 2) }}</strong>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        @endif

                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Close</button>

                        <form action="{{ route('sales.confirmPending', $sale->id) }}" method="POST" class="d-inline confirm-form">
                            @csrf
                            <button type="submit" class="btn btn-success fw-bold" @disabled(!$inventory['ready']) title="{{ $inventory['ready'] ? 'Verify this order and deduct stock' : 'Restock the insufficient items before verification' }}">
                                <i class="fa-solid {{ $inventory['ready'] ? 'fa-check' : 'fa-lock' }} me-1"></i>
                                {{ $inventory['ready'] ? 'Verify & Deduct Stock' : 'Awaiting Stock' }}
                            </button>
                        </form>
                    </div>
                </div>
            </div>
        </div>
        <div class="modal fade" id="rejectModal{{ $sale->id }}" tabindex="-1" aria-hidden="true">
            <div class="modal-dialog">
                <form method="POST" action="{{ route('sales.rejectPending', $sale->id) }}" class="modal-content">
                    @csrf
                    <div class="modal-header bg-danger text-white">
                        <h5 class="modal-title"><i class="fa-solid fa-circle-xmark me-2"></i>Reject Sale</h5>
                        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        <p class="small text-muted">Explain what needs to be corrected. The submitting Sales Associate will receive this reason.</p>
                        <label for="rejectionReason{{ $sale->id }}" class="form-label fw-semibold">Rejection reason</label>
                        <textarea id="rejectionReason{{ $sale->id }}" name="rejection_reason" class="form-control" rows="4" required maxlength="2000" placeholder="Example: The order slip does not match the selected products or quantities."></textarea>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-danger">Reject and Notify</button>
                    </div>
                </form>
            </div>
        </div>
    @endforeach
@endif

<!-- Custom Confirmation Modal matching JS reference IDs -->
<div class="modal fade" id="customConfirmModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Verify Inventory</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <p id="customConfirmMessage">Are you sure the products and quantities are correct? This will verify the order and deduct inventory stock.</p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" id="customConfirmYesBtn" class="btn btn-success">Verify and Deduct Stock</button>
            </div>
        </div>
    </div>
</div>

<script>
    document.addEventListener('DOMContentLoaded', function () {
        // Apply table row hover class safely
        document.querySelectorAll('table tbody tr').forEach(row => {
            if (!row.classList.contains('custom-table-row')) {
                row.classList.add('custom-table-row');
            }
        });

        // Intercept confirm forms to use the custom confirmation modal
        document.querySelectorAll('.confirm-form').forEach(form => {
            form.addEventListener('submit', async function (e) {
                e.preventDefault();

                // Prevent multiple submissions if already processing
                if (form.getAttribute('data-submitting') === 'true') return;

                const confirmed = await window.showConfirmationModal(
                    'Are you sure the products and quantities are correct? This will verify the order and deduct inventory stock.'
                );

                if (confirmed) {
                    form.setAttribute('data-submitting', 'true');
                    
                    // Disable submit buttons inside the form to give visual feedback
                    const submitBtn = form.querySelector('button[type="submit"]');
                    if (submitBtn) submitBtn.disabled = true;

                    this.submit();
                }
            });
        });

        // Reusable Custom Confirmation Modal Function
        window.showConfirmationModal = function(message) {
            return new Promise((resolve) => {
                const modalElement = document.getElementById('customConfirmModal');
                const messageEl = document.getElementById('customConfirmMessage');
                const yesBtn = document.getElementById('customConfirmYesBtn');
                
                if (!modalElement || !yesBtn) {
                    resolve(confirm(message)); // Fallback if modal elements don't exist
                    return;
                }

                if (message && messageEl) {
                    messageEl.textContent = message;
                }

                // Get or initialize the Bootstrap modal instance safely
                let bsModal = bootstrap.Modal.getInstance(modalElement);
                if (!bsModal) {
                    bsModal = new bootstrap.Modal(modalElement, { backdrop: 'static', keyboard: false });
                }

                let isResolved = false;

                const resolveOnce = (value) => {
                    if (isResolved) return;
                    isResolved = true;
                    cleanup();
                    resolve(value);
                };

                const handleYes = () => {
                    resolveOnce(true);
                    bsModal.hide(); // Hide the modal after resolving true
                };

                const handleHidden = () => {
                    resolveOnce(false); // Fires if closed via 'X', backdrop click, or Escape key
                };

                const cleanup = () => {
                    yesBtn.removeEventListener('click', handleYes);
                    modalElement.removeEventListener('hidden.bs.modal', handleHidden);
                };

                // Attach fresh listeners
                yesBtn.addEventListener('click', handleYes, { once: true });
                modalElement.addEventListener('hidden.bs.modal', handleHidden, { once: true });

                bsModal.show();
            });
        };
    });
</script>
@endsection
