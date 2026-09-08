<div class="card shadow-sm border-0">
    <div class="card-header bg-white">
        <span class="fw-bold">Online Payment Reconciliation</span>
        <small class="text-muted d-block">Difference = proof amount - sale amount - shipping fee.</small>
    </div>
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead class="table-info">
                <tr><th>Client/Company</th><th>Date</th><th>Order #</th><th>Payment Method</th><th class="text-end">Sale Amount</th><th class="text-end">Shipping Fee</th><th class="text-end">Proof Amount</th><th class="text-end">Difference</th><th>Remarks</th></tr>
            </thead>
            <tbody>
                @forelse($transactions as $transaction)
                    @php
                        $saleAmount = (float) ($transaction->sub_total ?: $transaction->items->sum('line_total'));
                        $difference = $transaction->proof_amount === null ? null : (float) $transaction->proof_amount - $saleAmount - (float) $transaction->shipping_fee_amount;
                    @endphp
                    <tr>
                        <td class="fw-semibold">{{ $transaction->customer_name }}</td>
                        <td>{{ optional($transaction->order_date)->format('m/d/Y') }}</td>
                        <td>{{ $transaction->order_number }}</td>
                        <td>{{ strtoupper($transaction->mode_of_payment ?: '—') }}</td>
                        <td class="text-end">₱{{ number_format($saleAmount, 2) }}</td>
                        <td class="text-end">₱{{ number_format($transaction->shipping_fee_amount, 2) }}</td>
                        <td class="text-end">{{ $transaction->proof_amount !== null ? '₱'.number_format($transaction->proof_amount, 2) : 'Not entered' }}</td>
                        <td class="text-end fw-bold {{ $difference !== null && abs($difference) >= 0.01 ? 'text-danger' : 'text-success' }}">
                            {{ $difference !== null ? '₱'.number_format($difference, 2) : '—' }}
                        </td>
                        <td>{{ $transaction->note ?: '—' }}</td>
                    </tr>
                @empty
                    <tr><td colspan="9" class="text-center text-muted py-4">No online sales found.</td></tr>
                @endforelse
            </tbody>
            <tfoot class="table-light fw-bold">
                <tr><td colspan="4">TOTAL SALES / {{ number_format($totalTransactions) }} TRANSACTIONS</td><td class="text-end">₱{{ number_format($metrics['total_sales'], 2) }}</td><td class="text-end">₱{{ number_format($metrics['shipping_fees'], 2) }}</td><td class="text-end">₱{{ number_format($metrics['proof_amount'], 2) }}</td><td class="text-end">₱{{ number_format($metrics['difference'], 2) }}</td><td></td></tr>
            </tfoot>
        </table>
    </div>
</div>
