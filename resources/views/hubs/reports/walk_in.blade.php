<div class="card shadow-sm border-0">
    <div class="card-header bg-white">
        <span class="fw-bold">Walk-In Sales</span>
        <small class="text-muted d-block">View a customer's order to see the items purchased.</small>
    </div>
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead class="table-light"><tr><th>Date</th><th>Customer</th><th>Items</th><th>MOP</th><th>Payment Status</th><th class="text-end">Gross Amount</th><th class="text-end">Discount</th><th class="text-end">Amount After Discount</th><th>Order</th></tr></thead>
            <tbody>
                @forelse($transactions as $transaction)
                    @php
                        $gross = $transaction->items->sum(fn($item) => (float) $item->unit_price * $item->quantity);
                        $afterDiscount = (float) $transaction->items->sum('line_total');
                    @endphp
                    <tr>
                        <td>{{ optional($transaction->order_date)->format('m/d/Y') }}</td>
                        <td>{{ $transaction->customer_name ?: 'Walk-In Customer' }}</td>
                        <td>{{ $transaction->items->sum('quantity') }} units</td>
                        <td>{{ strtoupper($transaction->mode_of_payment ?: '—') }}</td>
                        <td>{{ strtoupper($transaction->payment_status ?? 'paid') }}</td>
                        <td class="text-end">₱{{ number_format($gross, 2) }}</td>
                        <td class="text-end text-danger">₱{{ number_format(max(0, $gross - $afterDiscount), 2) }}</td>
                        <td class="text-end fw-bold text-success">₱{{ number_format($afterDiscount, 2) }}</td>
                        <td><button type="button" class="btn btn-sm btn-outline-primary text-nowrap" data-bs-toggle="modal" data-bs-target="#walk-in-order-{{ $transaction->id }}">View order</button></td>
                    </tr>
                @empty
                    <tr><td colspan="9" class="text-center text-muted py-4">No walk-in sales found.</td></tr>
                @endforelse
            </tbody>
            <tfoot class="table-light fw-bold"><tr><td colspan="5">TOTAL SALES / {{ number_format($totalTransactions) }} TRANSACTIONS</td><td class="text-end">₱{{ number_format($metrics['gross_sales'], 2) }}</td><td class="text-end text-danger">₱{{ number_format($metrics['discounts'], 2) }}</td><td class="text-end text-success">₱{{ number_format($metrics['total_sales'], 2) }}</td><td></td></tr></tfoot>
        </table>
    </div>
</div>
@foreach($transactions as $transaction)
<div class="modal fade" id="walk-in-order-{{ $transaction->id }}" tabindex="-1" aria-labelledby="walk-in-title-{{ $transaction->id }}" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-scrollable modal-fullscreen-sm-down"><div class="modal-content">
        <div class="modal-header">
            <h5 class="modal-title" id="walk-in-title-{{ $transaction->id }}">Order #{{ $transaction->order_number ?: $transaction->id }}</h5>
            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close order"></button>
        </div>
        <div class="modal-body">
            <h6 class="fw-bold">{{ $transaction->customer_name ?: 'Walk-In Customer' }}</h6>
            <p class="text-muted small">{{ optional($transaction->order_date)->format('M d, Y') }} &middot; {{ strtoupper($transaction->mode_of_payment ?: '—') }} &middot; {{ strtoupper($transaction->payment_status ?? 'paid') }}</p>
            <div class="table-responsive"><table class="table align-middle">
                <thead class="table-light"><tr><th>Item</th><th class="text-center">Qty</th><th class="text-end">Unit price</th><th class="text-end">Discount</th><th class="text-end">Total</th></tr></thead>
                <tbody>
                @forelse($transaction->items as $item)
                    <tr><td>{{ $item->product?->name ?? 'Product #'.$item->product_id }}</td><td class="text-center">{{ $item->quantity }}</td><td class="text-end">₱{{ number_format($item->unit_price, 2) }}</td><td class="text-end">{{ number_format($item->discount_percentage, 2) }}%</td><td class="text-end">₱{{ number_format($item->line_total, 2) }}</td></tr>
                @empty
                    <tr><td colspan="5" class="text-muted text-center">No items recorded.</td></tr>
                @endforelse
                </tbody>
                <tfoot><tr><th colspan="4">Order total after discount</th><th class="text-end text-success">₱{{ number_format($transaction->items->sum('line_total'), 2) }}</th></tr></tfoot>
            </table></div>
            @if($transaction->note)<p class="mb-0"><strong>Remarks:</strong> {{ $transaction->note }}</p>@endif
        </div>
        <div class="modal-footer"><button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button></div>
    </div></div>
</div>
@endforeach
