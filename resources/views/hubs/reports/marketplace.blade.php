<div class="card shadow-sm border-0">
    <div class="card-header bg-white">
        <span class="fw-bold">{{ $activeLabel }} Order Sales</span>
        <small class="text-muted d-block">Internal verified orders. Platform statement fees remain in the {{ $activeLabel }} app.</small>
    </div>
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead class="table-light">
                <tr><th>Date</th><th>Order #</th><th>Customer</th><th>Items</th><th class="text-end">Gross</th><th class="text-end">Discount</th><th class="text-end">Order Total</th></tr>
            </thead>
            <tbody>
                @forelse($transactions as $transaction)
                    @php
                        $gross = $transaction->items->sum(fn($item) => (float) $item->unit_price * $item->quantity);
                        $itemNet = (float) $transaction->items->sum('line_total');
                        $total = max((float) $transaction->grand_total, (float) $transaction->total_amount, $itemNet);
                    @endphp
                    <tr>
                        <td>{{ optional($transaction->order_date)->format('M d, Y') }}</td>
                        <td class="fw-semibold">{{ $transaction->order_number }}</td>
                        <td>{{ $transaction->customer_name }}</td>
                        <td>
                            @foreach($transaction->items as $item)
                                <div class="small">{{ $item->product?->name ?? 'Product #'.$item->product_id }} × {{ $item->quantity }}</div>
                            @endforeach
                        </td>
                        <td class="text-end">₱{{ number_format($gross, 2) }}</td>
                        <td class="text-end text-danger">₱{{ number_format(max(0, $gross - $itemNet), 2) }}</td>
                        <td class="text-end fw-bold text-success">₱{{ number_format($total, 2) }}</td>
                    </tr>
                @empty
                    <tr><td colspan="7" class="text-center text-muted py-4">No {{ $activeLabel }} sales found.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
