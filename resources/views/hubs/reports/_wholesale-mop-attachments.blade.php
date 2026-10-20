@php
    $recordedProofPath = $paymentRecord?->proof_of_payment ?? $paymentRecord?->payment_proof;
    $checkDate = $paymentRecord?->check_date;
    $orderDate = $paymentRecord?->order_date ?? $paymentRecord?->placed_order_date;
    $checkType = $checkDate
        ? ($orderDate && $checkDate->gt($orderDate) ? 'Post-dated check' : 'Dated check')
        : null;
    $mopAttachments = collect();
    $replacementRecords = $paymentRecord?->items
        ? $paymentRecord->items
            ->flatMap(fn ($item) => $item->replacements)
            ->unique('id')
            ->sortBy('created_at')
        : collect();

    if ($recordedProofPath) {
        $mopAttachments->push([
            'url' => \Illuminate\Support\Facades\Storage::disk('public')->url($recordedProofPath),
            'label' => 'Record Sales MOP Attachment',
            'is_image' => \Illuminate\Support\Str::endsWith(strtolower((string) $recordedProofPath), ['.jpg', '.jpeg', '.png', '.gif', '.webp']),
        ]);
    }

    foreach ($replacementRecords as $replacement) {
        foreach ($replacement->exchange_payment_proofs ?? [] as $proofIndex => $proofPath) {
            $mopAttachments->push([
                'url' => route('hub.report.replacement-attachment', [
                    'hub' => $hub->id,
                    'replacement' => $replacement->id,
                    'type' => 'payment-proof',
                    'index' => $proofIndex,
                ]),
                'label' => 'Replacement MOP Attachment',
                'is_image' => \Illuminate\Support\Str::endsWith(strtolower((string) $proofPath), ['.jpg', '.jpeg', '.png', '.gif', '.webp']),
            ]);
        }
    }
@endphp

<div class="wholesale-mop-proof-source">
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
            <div>Proof amount: <strong>&#8369;{{ number_format($paymentRecord->proof_amount, 2) }}</strong></div>
        @endif
    </div>

    <div class="wholesale-mop-attachment-data" data-wholesale-mop-attachments>
        @forelse($mopAttachments as $attachmentIndex => $attachment)
            <span
                data-wholesale-mop-attachment
                data-proof-url="{{ $attachment['url'] }}"
                data-proof-label="{{ $attachment['label'] }}"
                data-proof-kind="{{ $attachment['is_image'] ? 'image' : 'document' }}"
                data-proof-position="{{ $attachmentIndex + 1 }}"
            >{{ $attachment['label'] }}</span>
        @empty
            <span class="small text-muted payment-proof-empty">No MOP attachment recorded.</span>
        @endforelse
    </div>
</div>
