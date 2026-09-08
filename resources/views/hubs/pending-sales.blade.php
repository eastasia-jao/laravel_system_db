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
</style>

<div class="container p-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h2 class="fw-bold text-primary">
            <i class="fa-solid fa-clipboard-check me-2"></i> Inventory Verification Queue
        </h2>
        <a href="{{ isset($hub) ? route('hub.dashboard', $hub->id) : route('hub.dashboard', auth()->user()->store_hub_id ?? 1) }}" class="btn btn-outline-secondary">
            <i class="fa-solid fa-arrow-left me-1"></i> Back
        </a>
    </div>

    @if(session('success'))
        <div class="alert alert-success alert-dismissible fade show shadow-sm mb-4" role="alert">
            <i class="fa-solid fa-circle-check me-2"></i> {{ session('success') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    @endif

    @if(session('error'))
        <div class="alert alert-danger alert-dismissible fade show shadow-sm mb-4" role="alert">
            <i class="fa-solid fa-triangle-exclamation me-2"></i> {{ session('error') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
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
                        $rowGrandTotal = $sale->grand_total > 0 ? $sale->grand_total : 0;
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
                                <i class="fa-solid fa-xmark me-1"></i> Reject
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
        <div class="modal fade" id="itemsModal{{ $sale->id }}" tabindex="-1" aria-hidden="true">
            <div class="modal-dialog modal-lg">
                <div class="modal-content">
                    <div class="modal-header bg-primary text-white">
                        <h5 class="modal-title">Order Items & Summary - {{ $sale->invoice_number ?: 'Order #' . $sale->id }}</h5>
                        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        @if($sale->order_slip)
                            <div class="border rounded p-3 mb-3">
                                <h6 class="fw-bold">Order Slip</h6>
                                <a href="{{ route('sales.order-slip', $sale->id) }}" target="_blank" rel="noopener">
                                    <img src="{{ route('sales.order-slip', $sale->id) }}" class="img-fluid rounded" style="max-height: 300px;" alt="Order slip for {{ $sale->invoice_number ?: 'Order #'.$sale->id }}" loading="lazy">
                                    <span class="d-block mt-2">View full-size order slip</span>
                                </a>
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

                        <div class="table-responsive mb-3">
                            <table class="table table-bordered align-middle">
                                <thead class="table-light">
                                    <tr>
                                        <th>Product Name</th>
                                        <th class="text-center" style="width: 90px;">Requested</th>
                                        <th class="text-center" style="width: 90px;">Available</th>
                                        <th class="text-center" style="width: 120px;">Stock Status</th>
                                        <th class="text-end" style="width: 120px;">Unit Price</th>
                                        <th class="text-center" style="width: 100px;">Discount</th>
                                        <th class="text-end" style="width: 130px;">Total</th>
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

                                            if (empty($productName)) {
                                                if ($pId) {
                                                    $prod = \App\Models\Product::where('id', $pId)->orWhere('item_id', $pId)->first();
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
                                                
                                            $storedPrice = is_object($item) 
                                                ? ($item->unit_price ?? $item->price ?? 0) 
                                                : ($item['unit_price'] ?? $item['price'] ?? 0);

                                            if ($storedPrice <= 0) {
                                                if ($pId) {
                                                    $prod = $prod ?? \App\Models\Product::where('id', $pId)->orWhere('item_id', $pId)->first();
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
                                            <td>{{ $productName }}</td>
                                            <td class="text-center">{{ $quantity }}</td>
                                            <td class="text-center">{{ $stockDetails['available'] }}</td>
                                            <td class="text-center">
                                                @if($stockDetails['shortage'] > 0)
                                                    <span class="badge bg-danger">SHORT {{ $stockDetails['shortage'] }}</span>
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

                        {{-- Order Financial Summary Box --}}
                        @php
                            $orderDiscount = $sale->discount ?? $sale->total_discount ?? 0;
                            $deliveryFee = $sale->delivery_fee ?? 0;
                            $finalGrandTotal = ($sale->grand_total > 0) ? $sale->grand_total : (($subtotal - $orderDiscount) + $deliveryFee);
                            
                            $channel = strtolower($sale->sales_channel ?? '');
                            $isTiktok = ($channel === 'tiktok');
                            $isMarketplace = in_array($channel, ['shopee', 'lazada', 'tiktok']);
                        @endphp
                        <div class="row justify-content-end">
                            <div class="col-md-6">
                                <ul class="list-group list-group-flush shadow-sm border rounded p-2 bg-light">
                                    @if(!$isMarketplace)
                                        <li class="list-group-item d-flex justify-content-between bg-transparent py-1">
                                            <span class="text-muted">Subtotal:</span>
                                            <span class="fw-semibold">₱{{ number_format($subtotal, 2) }}</span>
                                        </li>
                                    @endif

                                    @if($orderDiscount > 0)
                                        <li class="list-group-item d-flex justify-content-between bg-transparent py-1 text-danger">
                                            <span>Discount:</span>
                                            <span class="fw-semibold">-₱{{ number_format($orderDiscount, 2) }}</span>
                                        </li>
                                    @endif

                                    @if($deliveryFee > 0)
                                        <li class="list-group-item d-flex justify-content-between bg-transparent py-1">
                                            <span class="text-muted">Delivery Fee:</span>
                                            <span class="fw-semibold">₱{{ number_format($deliveryFee, 2) }}</span>
                                        </li>
                                    @endif

                                    <li class="list-group-item d-flex justify-content-between bg-transparent pt-2 border-top">
                                        <span class="fw-bold text-dark">{{ $isTiktok ? 'Sales After Transaction:' : 'Total Amount:' }}</span>
                                        <span class="fw-bold text-success fs-5">₱{{ number_format($finalGrandTotal, 2) }}</span>
                                    </li>
                                </ul>
                            </div>
                        </div>

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
