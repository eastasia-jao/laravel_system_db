@php
    $paymentRecord = $paymentRecord ?? null;
    $enableProofMagnifier = $enableProofMagnifier ?? false;
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
@once
    @push('styles')
        <style>
            .online-proof-gallery { display: grid; gap: .55rem; }
            .online-proof-stage { position: relative; display: grid; height: 180px; place-items: center; overflow: hidden; padding: .5rem 3rem; border: 1px solid #dbe3ee; border-radius: .6rem; background: #eef2f7; }
            .online-proof-slide { position: relative; display: grid; width: 100%; height: 100%; min-width: 0; place-items: center; overflow: hidden; }
            .online-proof-slide[hidden] { display: none !important; }
            .online-proof-image, .online-proof-document { display: block; max-width: 100%; border: 0; background: #fff; }
            .online-proof-image { width: 100%; height: 100%; object-fit: contain; }
            .online-proof-document { width: 100%; height: 100%; min-height: 0; }
            .online-proof-arrow { position: absolute; z-index: 3; display: grid; place-items: center; padding: 0; border: 1px solid rgba(255, 255, 255, .75); border-radius: 50%; color: #fff; background: rgba(15, 23, 42, .82); box-shadow: 0 4px 14px rgba(15, 23, 42, .25); transition: background-color .15s ease, transform .15s ease; }
            .online-proof-arrow { top: 50%; width: 38px; height: 38px; transform: translateY(-50%); }
            .online-proof-arrow:hover:not(:disabled) { background: #2563eb; }
            .online-proof-arrow:hover:not(:disabled) { transform: translateY(-50%) scale(1.06); }
            .online-proof-arrow:focus-visible { outline: 3px solid rgba(37, 99, 235, .3); outline-offset: 2px; }
            .online-proof-arrow:disabled { display: none; }
            .online-proof-arrow.is-previous { left: .6rem; }
            .online-proof-arrow.is-next { right: .6rem; }
            .online-proof-magnifier { position: absolute; right: .65rem; bottom: .65rem; z-index: 4; display: grid; width: 38px; height: 38px; place-items: center; padding: 0; border: 1px solid rgba(255, 255, 255, .8); border-radius: 50%; color: #fff; background: rgba(15, 23, 42, .86); box-shadow: 0 4px 14px rgba(15, 23, 42, .3); transition: background-color .15s ease, transform .15s ease; }
            .online-proof-magnifier:hover { background: #2563eb; transform: scale(1.06); }
            .online-proof-magnifier:focus-visible { outline: 3px solid rgba(37, 99, 235, .3); outline-offset: 2px; }
            .online-proof-caption { display: flex; height: 3.25rem; min-width: 0; align-items: flex-start; justify-content: space-between; gap: .75rem; overflow: hidden; color: #475569; font-size: .78rem; }
            .online-proof-caption > :first-child { min-width: 0; overflow-wrap: anywhere; }
            .online-proof-caption strong { color: #1e3a8a; }
            .online-proof-caption [data-online-proof-amount] { min-height: 1rem; }
            .online-proof-lightbox { position: relative; width: min(1000px, calc(100vw - 2rem)); max-width: calc(100vw - 2rem); max-height: calc(100vh - 2rem); padding: 3.25rem 1rem 1rem; border: 0; border-radius: 14px; background: #0f172a; box-shadow: 0 24px 70px rgba(0, 0, 0, .45); }
            .online-proof-lightbox::backdrop { background: rgba(15, 23, 42, .86); }
            .online-proof-lightbox img, .online-proof-lightbox iframe { display: block; width: 100%; height: min(76vh, 760px); border: 0; object-fit: contain; background: #fff; }
            .online-proof-lightbox [hidden] { display: none !important; }
            .online-proof-lightbox-close { position: absolute; top: .55rem; right: .55rem; display: inline-flex; min-height: 36px; align-items: center; gap: .35rem; padding: .4rem .75rem; border: 0; border-radius: 999px; color: #fff; background: rgba(255, 255, 255, .18); font-weight: 700; }
            .online-proof-lightbox-close:hover { background: #2563eb; }
            .online-proof-lightbox-caption { margin-top: .65rem; overflow-wrap: anywhere; color: #fff; font-size: .82rem; text-align: center; }
        </style>
    @endpush

    @push('scripts')
        <script>
            (() => {
                const proofViewer = document.querySelector('[data-online-proof-lightbox]');
                const proofViewerImage = proofViewer?.querySelector('[data-online-proof-lightbox-image]');
                const proofViewerDocument = proofViewer?.querySelector('[data-online-proof-lightbox-document]');
                const proofViewerCaption = proofViewer?.querySelector('[data-online-proof-lightbox-caption]');
                const closeProofViewer = () => {
                    if (proofViewer?.open) proofViewer.close();
                };
                const openProofPreview = attachment => {
                    if (!proofViewer || !proofViewerImage || !proofViewerDocument || !proofViewerCaption) return;
                    const isImage = attachment.kind === 'image';
                    proofViewerImage.hidden = !isImage;
                    proofViewerDocument.hidden = isImage;
                    proofViewerCaption.textContent = attachment.label;
                    if (isImage) {
                        proofViewerDocument.src = 'about:blank';
                        proofViewerImage.src = attachment.url;
                        proofViewerImage.alt = attachment.label;
                    } else {
                        proofViewerImage.removeAttribute('src');
                        proofViewerImage.alt = '';
                        proofViewerDocument.src = attachment.url;
                        proofViewerDocument.title = attachment.label;
                    }
                    if (!proofViewer.open) proofViewer.showModal();
                };

                proofViewer?.querySelector('[data-online-proof-lightbox-close]')?.addEventListener('click', closeProofViewer);
                proofViewer?.addEventListener('close', () => {
                    proofViewerImage?.removeAttribute('src');
                    if (proofViewerImage) proofViewerImage.alt = '';
                    if (proofViewerDocument) proofViewerDocument.src = 'about:blank';
                    if (proofViewerCaption) proofViewerCaption.textContent = '';
                });

                document.querySelectorAll('[data-online-proof-gallery]:not([data-proof-gallery-ready])').forEach(gallery => {
                    gallery.dataset.proofGalleryReady = 'true';
                    const slides = [...gallery.querySelectorAll('[data-online-proof-slide]')];
                    const previous = gallery.querySelector('[data-online-proof-previous]');
                    const next = gallery.querySelector('[data-online-proof-next]');
                    const label = gallery.querySelector('[data-online-proof-label]');
                    const amount = gallery.querySelector('[data-online-proof-amount]');
                    const count = gallery.querySelector('[data-online-proof-count]');
                    const magnifier = gallery.querySelector('[data-online-proof-magnifier]');
                    let activeIndex = 0;

                    if (!slides.length) return;

                    const activeAttachment = () => ({
                        url: slides[activeIndex].dataset.proofUrl,
                        label: slides[activeIndex].dataset.proofLabel,
                        kind: slides[activeIndex].dataset.proofKind,
                        amount: Number(slides[activeIndex].dataset.proofAmount || 0),
                    });
                    const updateCaption = () => {
                        const attachment = activeAttachment();
                        label.textContent = attachment.label;
                        amount.textContent = attachment.amount > 0
                            ? `Amount: ₱${attachment.amount.toLocaleString('en-PH', { minimumFractionDigits: 2, maximumFractionDigits: 2 })}`
                            : '';
                        count.textContent = `${activeIndex + 1} of ${slides.length}`;
                        [previous, next].forEach(button => {
                            if (button) button.disabled = slides.length < 2;
                        });
                    };
                    const showSlide = index => {
                        activeIndex = (index + slides.length) % slides.length;
                        slides.forEach((slide, slideIndex) => { slide.hidden = slideIndex !== activeIndex; });
                        updateCaption();
                    };
                    const move = step => showSlide(activeIndex + step);

                    const bindMove = (button, step) => button?.addEventListener('click', event => {
                        event.preventDefault();
                        event.stopPropagation();
                        move(step);
                    });
                    bindMove(previous, -1);
                    bindMove(next, 1);
                    magnifier?.addEventListener('click', event => {
                        event.preventDefault();
                        event.stopPropagation();
                        openProofPreview(activeAttachment());
                    });
                    showSlide(0);
                });
            })();
        </script>
    @endpush
@endonce
<div class="border rounded-3 p-3 h-100 {{ $containerClass ?? '' }}">
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
                            <img src="{{ $attachment['url'] }}" alt="{{ $attachment['label'] }}" class="online-proof-image">
                        @else
                            <iframe src="{{ $attachment['url'] }}" title="{{ $attachment['label'] }}" class="online-proof-document"></iframe>
                        @endif
                    </div>
                @endforeach
                <button type="button" class="online-proof-arrow is-previous" data-online-proof-previous aria-label="Previous payment proof"><i class="fa-solid fa-chevron-left"></i></button>
                <button type="button" class="online-proof-arrow is-next" data-online-proof-next aria-label="Next payment proof"><i class="fa-solid fa-chevron-right"></i></button>
                @if($enableProofMagnifier)
                    <button type="button" class="online-proof-magnifier" data-online-proof-magnifier aria-label="View larger payment proof"><i class="fa-solid fa-magnifying-glass-plus" aria-hidden="true"></i></button>
                @endif
            </div>
            <div class="online-proof-caption">
                <div>
                    <strong data-online-proof-label>{{ $proofAttachments->first()['label'] }}</strong>
                    <span class="d-block text-muted" data-online-proof-amount>@if(($proofAttachments->first()['amount'] ?? 0) > 0)Amount: &#8369;{{ number_format($proofAttachments->first()['amount'], 2) }}@endif</span>
                </div>
                <span class="text-nowrap" data-online-proof-count>1 of {{ $proofAttachments->count() }}</span>
            </div>
        </div>
    @else
        <div class="small text-muted mt-2 payment-proof-empty">No payment proof attached.</div>
    @endif
</div>
