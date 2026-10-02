@php
    $paymentRecord = $paymentRecord ?? null;
    $proofPath = $paymentRecord?->proof_of_payment ?? $paymentRecord?->payment_proof;
    $proofUrl = $proofPath ? \Illuminate\Support\Facades\Storage::disk('public')->url($proofPath) : null;
    $checkDate = $paymentRecord?->check_date;
    $orderDate = $paymentRecord?->order_date ?? $paymentRecord?->placed_order_date;
    $checkType = null;
    if ($checkDate) {
        $checkType = $orderDate && $checkDate->gt($orderDate) ? 'Post-dated check' : 'Dated check';
    }
@endphp
<div class="border rounded-3 p-3 h-100">
    <div class="fw-semibold mb-2"><i class="fa-solid fa-money-check-dollar me-1"></i>Payment proof</div>
    <div class="small text-muted">
        <div>Method: <strong>{{ strtoupper($paymentRecord?->mode_of_payment ?: 'Not specified') }}</strong></div>
        @if($paymentRecord?->bank_name || $paymentRecord?->custom_bank_name)
            <div>Bank: <strong>{{ $paymentRecord->bank_name ?: $paymentRecord->custom_bank_name }}</strong></div>
        @endif
        @if($paymentRecord?->check_number)
            <div>Check number: <strong>{{ $paymentRecord->check_number }}</strong></div>
        @endif
        @if($checkDate)
            <div>{{ $checkType }} date: <strong>{{ $checkDate->format('M d, Y') }}</strong></div>
        @endif
        @if($paymentRecord?->proof_amount !== null)
            <div>Proof amount: <strong>₱{{ number_format($paymentRecord->proof_amount, 2) }}</strong></div>
        @endif
    </div>
    @if($proofUrl)
        <div class="mt-3">
            @if(\Illuminate\Support\Str::endsWith(strtolower((string) $proofPath), ['.jpg', '.jpeg', '.png', '.gif', '.webp']))
                <a href="{{ $proofUrl }}" target="_blank" rel="noopener" class="d-flex align-items-center justify-content-center rounded border bg-light overflow-hidden" style="width: 100%; max-width: 520px; height: 260px;">
                    <img src="{{ $proofUrl }}" alt="Payment proof" class="img-fluid" style="max-width: 100%; max-height: 100%; object-fit: contain;">
                </a>
            @else
                <a href="{{ $proofUrl }}" target="_blank" rel="noopener" class="btn btn-sm btn-outline-primary">
                    <i class="fa-solid fa-file-arrow-up me-1"></i>Open payment proof
                </a>
            @endif
        </div>
    @elseif(!$paymentRecord?->walkin_payment_proofs)
        <div class="small text-muted mt-2 payment-proof-empty">No payment proof attached.</div>
    @endif
    @if($paymentRecord?->quotation_proofs || $paymentRecord?->walkin_payment_proofs)
        @foreach(['quotation_proofs' => 'Proof of quotation', 'walkin_payment_proofs' => 'Proof of payment'] as $proofField => $proofLabel)
            @if($paymentRecord->{$proofField})
                <div class="mt-3 fw-semibold">{{ $proofLabel }}</div>
                @foreach($paymentRecord->{$proofField} as $proofIndex => $attachment)
                    <a class="d-inline-block me-2 mt-1" href="{{ \Illuminate\Support\Facades\Storage::disk('public')->url($attachment) }}" target="_blank" rel="noopener">Open attachment {{ $proofIndex + 1 }}</a>
                @endforeach
            @endif
        @endforeach
    @endif
</div>
