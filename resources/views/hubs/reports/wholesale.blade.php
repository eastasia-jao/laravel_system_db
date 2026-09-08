<div class="card shadow-sm border-0">
    <div class="card-header bg-white fw-bold">Wholesale Billing and Delivery</div>
    <div class="table-responsive">
        <table class="table table-bordered table-sm align-middle mb-0">
            <thead class="table-success">
                <tr><th>Company/Customer</th><th>Purchase Date</th><th>MOP</th><th class="text-end">Order Value</th><th class="text-end">Collected</th><th>Withholding Tax</th><th>Invoice #</th><th>Payment</th><th>Order</th><th>Delivery</th><th>Delivery/Pickup Date</th><th>Type</th><th class="text-end">Shipping Amount</th><th>Courier</th><th>Check Date</th><th>Update Status</th></tr>
            </thead>
            <tbody>
                @forelse($transactions as $transaction)
                    @php
                        $total = max((float) $transaction->grand_total, (float) $transaction->total_amount, (float) $transaction->items->sum('line_total'));
                        $paymentStatus = $transaction->payment_status ?? 'unpaid';
                        $deliveryStatus = $transaction->delivery_status ?? 'pending';
                        $collected = min($total, max(0, (float) ($transaction->amount_paid ?? 0)));
                        $paymentLocked = $paymentStatus === 'paid';
                    @endphp
                    <tr>
                        <td>{{ $transaction->customer_name }}</td>
                        <td>{{ optional($transaction->order_date)->format('F d, Y') }}</td>
                        <td>{{ $transaction->mode_of_payment ?: '—' }}</td>
                        <td class="text-end fw-bold">₱{{ number_format($total, 2) }}</td>
                        <td class="text-end fw-bold text-success">₱{{ number_format($collected, 2) }}</td>
                        <td>{{ $transaction->withholding_tax ? number_format($transaction->withholding_tax, 2).'%' : '—' }} {{ $transaction->withholding_tax_amount ? '(₱'.number_format($transaction->withholding_tax_amount, 2).')' : '' }}</td>
                        <td>{{ $transaction->order_number }}</td>
                        <td>{{ strtoupper($transaction->payment_status ?? 'unpaid') }}</td>
                        <td>{{ strtoupper($transaction->status ?? 'confirmed') }}</td>
                        <td>{{ strtoupper($transaction->delivery_status ?? 'pending') }}</td>
                        <td>{{ optional($transaction->delivery_date ?? $transaction->date_of_arrangement)->format('F d, Y') ?? '—' }}</td>
                        <td>{{ strtoupper($transaction->shipping_fee_type ?: '—') }}</td>
                        <td class="text-end">₱{{ number_format($transaction->shipping_fee_amount, 2) }}</td>
                        <td>{{ $transaction->courier ?: '—' }}</td>
                        <td>{{ optional($transaction->check_date)->format('F d, Y') ?? '—' }}</td>
                        <td style="min-width: 260px;">
                            <form action="{{ route('sales.status.update', $transaction->id) }}" method="POST" class="status-update-form">
                                @csrf
                                @method('PATCH')
                                <div class="input-group input-group-sm mb-1">
                                    <select name="payment_status" class="form-select" aria-label="Payment status" @disabled($paymentLocked)>
                                        <option value="unpaid" @selected($paymentStatus === 'unpaid')>Unpaid</option>
                                        <option value="partial" @selected($paymentStatus === 'partial')>Partial</option>
                                        <option value="paid" @selected($paymentStatus === 'paid')>Paid</option>
                                    </select>
                                    @if($paymentLocked)
                                        <input type="hidden" name="payment_status" value="paid">
                                    @endif
                                    <select name="delivery_status" class="form-select" aria-label="Delivery status">
                                        @foreach(['pending', 'preparing', 'shipped', 'delivered', 'cancelled'] as $status)
                                            <option value="{{ $status }}" @selected($deliveryStatus === $status)>{{ ucfirst($status) }}</option>
                                        @endforeach
                                    </select>
                                </div>
                                <div class="input-group input-group-sm">
                                    <span class="input-group-text">₱</span>
                                    <input type="number" name="amount_paid" class="form-control amount-paid-input" min="0" max="{{ $total }}" step="0.01" value="{{ $transaction->amount_paid ?? 0 }}" placeholder="Amount paid" @disabled($paymentLocked)>
                                    @if($paymentLocked)
                                        <input type="hidden" name="amount_paid" value="{{ $transaction->amount_paid ?? $total }}">
                                    @endif
                                    <button type="submit" class="btn btn-primary">Save</button>
                                </div>
                                <small class="text-muted">{{ $paymentLocked ? 'Payment is fully paid and locked. Delivery status remains editable.' : 'For Partial, enter an amount below ₱'.number_format($total, 2).'.' }}</small>
                            </form>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="16" class="text-center text-muted py-4">No wholesale sales found.</td></tr>
                @endforelse
            </tbody>
            <tfoot class="table-light fw-bold"><tr><td colspan="4">TOTAL ORDER VALUE / {{ number_format($totalTransactions) }} TRANSACTIONS</td><td class="text-end text-success">₱{{ number_format($allTransactions->sum(fn ($transaction) => max((float) $transaction->grand_total, (float) $transaction->total_amount, (float) $transaction->items->sum('line_total'))), 2) }}</td><td colspan="11"></td></tr></tfoot>
        </table>
    </div>
</div>
