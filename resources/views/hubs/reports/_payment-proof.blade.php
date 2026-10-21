@php
    $paymentRecord = $paymentRecord ?? null;
    $proofPath = $paymentRecord?->proof_of_payment ?? $paymentRecord?->payment_proof;
    $checkDate = $paymentRecord?->check_date;
    $orderDate = $paymentRecord?->order_date ?? $paymentRecord?->placed_order_date;
    $checkType = $checkDate
        ? ($orderDate && $checkDate->gt($orderDate) ? 'Post-dated check' : 'Dated check')
        : null;
    $proofAttachments = collect();
    $unavailableProofs = collect();
    $replacementRecords = $paymentRecord?->items
        ? $paymentRecord->items
            ->flatMap(fn ($item) => $item->replacements)
            ->unique('id')
            ->sortBy('created_at')
        : collect();
    $attachmentDiskName = \App\Support\AttachmentStorage::diskName(\App\Support\AttachmentStorage::SALES);
    $attachmentDisk = \Illuminate\Support\Facades\Storage::disk($attachmentDiskName);
    $canCheckAttachmentAvailability = config("filesystems.disks.{$attachmentDiskName}.driver") !== 's3';
    $addProofAttachment = function (?string $path, string $url, string $label, ?float $amount = null) use ($proofAttachments, $unavailableProofs, $attachmentDisk, $canCheckAttachmentAvailability) {
        if (! is_string($path) || $path === '') {
            return;
        }

        $availability = $canCheckAttachmentAvailability
            ? \App\Support\AttachmentAvailability::check($attachmentDisk, $path)
            : true;
        if ($availability !== true) {
            $unavailableProofs->push([
                'label' => $label,
                'unverified' => $availability === null,
            ]);

            return;
        }

        $proofAttachments->push([
            'url' => $url,
            'label' => $label,
            'amount' => $amount,
            'kind' => \Illuminate\Support\Str::endsWith(strtolower($path), ['.jpg', '.jpeg', '.png', '.gif', '.webp']) ? 'image' : 'document',
        ]);
    };

    if ($proofPath) {
        $addProofAttachment(
            $proofPath,
            route('hub.report.sales-attachment', [
                'hub' => $hub->id,
                'transaction' => $paymentRecord->id,
                'type' => 'payment-proof',
                'index' => 0,
            ]),
            'Original sales payment proof',
            null
        );
    }

    foreach ($replacementRecords as $replacement) {
        foreach ($replacement->exchange_payment_proofs ?? [] as $proofIndex => $replacementProofPath) {
            $addProofAttachment(
                $replacementProofPath,
                route('hub.report.replacement-attachment', [
                    'hub' => $hub->id,
                    'replacement' => $replacement->id,
                    'type' => 'payment-proof',
                    'index' => $proofIndex,
                ]),
                'Replacement additional payment proof',
                (float) ($replacement->exchange_payment_amount ?? $replacement->additional_payment_due ?? 0)
            );
        }
    }

    foreach (($paymentRecord?->quotation_proofs ?? []) as $proofIndex => $quotationProofPath) {
        $addProofAttachment(
            $quotationProofPath,
            route('hub.report.sales-attachment', [
                'hub' => $hub->id,
                'transaction' => $paymentRecord->id,
                'type' => 'quotation-proof',
                'index' => $proofIndex,
            ]),
            'Proof of quotation'
        );
    }

    foreach (($paymentRecord?->walkin_payment_proofs ?? []) as $proofIndex => $walkInProofPath) {
        $addProofAttachment(
            $walkInProofPath,
            route('hub.report.sales-attachment', [
                'hub' => $hub->id,
                'transaction' => $paymentRecord->id,
                'type' => 'walkin-payment-proof',
                'index' => $proofIndex,
            ]),
            'Additional payment proof'
        );
    }
@endphp
<div class="border rounded-3 p-3 h-100">
    <div class="fw-semibold mb-2"><i class="fa-solid fa-money-check-dollar me-1"></i>Payment proofs</div>
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
            <div>Current proof amount: <strong>&#8369;{{ number_format($paymentRecord->proof_amount, 2) }}</strong></div>
        @endif
    </div>

    @foreach($unavailableProofs as $unavailableProof)
        <div class="small {{ $unavailableProof['unverified'] ? 'text-warning' : 'text-danger' }} mt-2" role="alert">
            {{ $unavailableProof['label'] }} {{ $unavailableProof['unverified'] ? 'could not be verified because storage is unavailable.' : 'is missing or empty. Please upload it again.' }}
        </div>
    @endforeach

    @if($proofAttachments->isNotEmpty())
        <div class="online-proof-gallery mt-3" data-online-proof-gallery>
            <div class="online-proof-stage">
                @foreach($proofAttachments as $proofIndex => $attachment)
                    <div
                        class="online-proof-slide"
                        data-online-proof-slide
                        data-proof-url="{{ $attachment['url'] }}"
                        data-proof-label="{{ $attachment['label'] }}"
                        data-proof-kind="{{ $attachment['kind'] }}"
                        data-proof-amount="{{ $attachment['amount'] === null ? '' : number_format($attachment['amount'], 2, '.', '') }}"
                        @if($proofIndex !== 0) hidden @endif
                    >
                        @if($attachment['kind'] === 'image')
                            <img src="{{ $attachment['url'] }}" alt="{{ $attachment['label'] }}" class="online-proof-image" role="button" tabindex="0" aria-label="Enlarge {{ $attachment['label'] }}">
                        @else
                            <iframe src="{{ $attachment['url'] }}" title="{{ $attachment['label'] }}" class="online-proof-document"></iframe>
                        @endif
                    </div>
                @endforeach
                <button type="button" class="online-proof-arrow is-previous" data-online-proof-previous aria-label="Previous payment proof"><i class="fa-solid fa-chevron-left"></i></button>
                <button type="button" class="online-proof-arrow is-next" data-online-proof-next aria-label="Next payment proof"><i class="fa-solid fa-chevron-right"></i></button>
            </div>
            <div class="online-proof-caption">
                <div>
                    <strong data-online-proof-label>{{ $proofAttachments->first()['label'] }}</strong>
                    <span class="d-block text-muted" data-online-proof-amount>@if(($proofAttachments->first()['amount'] ?? 0) > 0)Amount: &#8369;{{ number_format($proofAttachments->first()['amount'], 2) }}@endif</span>
                </div>
                <span class="text-nowrap" data-online-proof-count>1 of {{ $proofAttachments->count() }}</span>
            </div>
            <div class="online-proof-lightbox" data-online-proof-lightbox role="dialog" aria-modal="true" aria-label="Payment proof preview" hidden>
                <div class="online-proof-lightbox-content">
                    <button type="button" class="online-proof-lightbox-close" data-online-proof-close aria-label="Close payment proof preview"><i class="fa-solid fa-xmark"></i></button>
                    <button type="button" class="online-proof-arrow is-previous" data-online-proof-lightbox-previous aria-label="Previous payment proof"><i class="fa-solid fa-chevron-left"></i></button>
                    <div class="online-proof-lightbox-media">
                        <img class="online-proof-lightbox-image" alt="" hidden>
                        <iframe class="online-proof-lightbox-document" title="Payment proof document" hidden></iframe>
                    </div>
                    <button type="button" class="online-proof-arrow is-next" data-online-proof-lightbox-next aria-label="Next payment proof"><i class="fa-solid fa-chevron-right"></i></button>
                    <div class="online-proof-lightbox-caption" data-online-proof-lightbox-caption></div>
                </div>
            </div>
        </div>
    @else
        <div class="small text-muted mt-2 payment-proof-empty">No payment proof attached.</div>
    @endif
</div>
