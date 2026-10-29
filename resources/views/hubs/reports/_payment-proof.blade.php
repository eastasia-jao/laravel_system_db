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
@once
    @push('styles')
        <style>
            .online-proof-gallery { display: grid; gap: .55rem; }
            .online-proof-stage { position: relative; display: grid; height: 180px; place-items: center; overflow: hidden; padding: .5rem 3rem; border: 1px solid #dbe3ee; border-radius: .6rem; background: #eef2f7; }
            .online-proof-slide { position: relative; display: grid; width: 100%; height: 100%; min-width: 0; place-items: center; overflow: hidden; }
            .online-proof-slide[hidden], .online-proof-lightbox[hidden] { display: none !important; }
            .online-proof-image, .online-proof-document { display: block; max-width: 100%; border: 0; background: #fff; }
            .online-proof-image { width: 100%; height: 100%; object-fit: contain; cursor: zoom-in; }
            .online-proof-document { width: 100%; height: 100%; min-height: 0; }
            .online-proof-arrow { position: absolute; z-index: 3; display: grid; place-items: center; padding: 0; border: 1px solid rgba(255, 255, 255, .75); border-radius: 50%; color: #fff; background: rgba(15, 23, 42, .82); box-shadow: 0 4px 14px rgba(15, 23, 42, .25); transition: background-color .15s ease, transform .15s ease; }
            .online-proof-arrow { top: 50%; width: 38px; height: 38px; transform: translateY(-50%); }
            .online-proof-arrow:hover:not(:disabled) { background: #2563eb; }
            .online-proof-arrow:hover:not(:disabled) { transform: translateY(-50%) scale(1.06); }
            .online-proof-arrow:focus-visible,
            .online-proof-lightbox-close:focus-visible { outline: 3px solid rgba(37, 99, 235, .3); outline-offset: 2px; }
            .online-proof-arrow:disabled { display: none; }
            .online-proof-arrow.is-previous { left: .6rem; }
            .online-proof-arrow.is-next { right: .6rem; }
            .online-proof-caption { display: flex; height: 3.25rem; min-width: 0; align-items: flex-start; justify-content: space-between; gap: .75rem; overflow: hidden; color: #475569; font-size: .78rem; }
            .online-proof-caption > :first-child { min-width: 0; overflow-wrap: anywhere; }
            .online-proof-caption strong { color: #1e3a8a; }
            .online-proof-caption [data-online-proof-amount] { min-height: 1rem; }
            .online-proof-lightbox { position: fixed; inset: 0; z-index: 2100; display: grid; place-items: center; padding: 1rem; background: rgba(15, 23, 42, .9); }
            .online-proof-lightbox-content { position: relative; display: grid; width: fit-content; max-width: calc(100vw - 2rem); max-height: calc(100dvh - 2rem); place-items: center; gap: .65rem; padding: 3.25rem 3.25rem 1rem; border-radius: .8rem; background: #fff; box-shadow: 0 24px 70px rgba(0, 0, 0, .4); }
            .online-proof-lightbox-media { display: grid; width: fit-content; max-width: calc(100vw - 8.5rem); max-height: calc(100dvh - 6.5rem); place-items: center; overflow: hidden; }
            .online-proof-lightbox-image, .online-proof-lightbox-document { display: block; max-width: 100%; border: 0; }
            .online-proof-lightbox-image { width: auto; height: auto; max-height: calc(100dvh - 7.5rem); object-fit: contain; }
            .online-proof-lightbox-document { width: min(900px, calc(100vw - 8.5rem)); height: min(75dvh, 720px); }
            .online-proof-lightbox-close { position: absolute; top: .55rem; right: .55rem; z-index: 4; display: grid; width: 38px; height: 38px; place-items: center; border: 0; border-radius: 50%; color: #fff; background: rgba(15, 23, 42, .82); }
            .online-proof-lightbox-close:hover { background: #2563eb; }
            .online-proof-lightbox-caption { color: #1e3a8a; font-size: .85rem; font-weight: 700; text-align: center; }
        </style>
    @endpush

    @push('scripts')
        <script>
            (() => {
                document.querySelectorAll('[data-online-proof-gallery]:not([data-proof-gallery-ready])').forEach(gallery => {
                    gallery.dataset.proofGalleryReady = 'true';
                    const slides = [...gallery.querySelectorAll('[data-online-proof-slide]')];
                    const previous = gallery.querySelector('[data-online-proof-previous]');
                    const next = gallery.querySelector('[data-online-proof-next]');
                    const label = gallery.querySelector('[data-online-proof-label]');
                    const amount = gallery.querySelector('[data-online-proof-amount]');
                    const count = gallery.querySelector('[data-online-proof-count]');
                    const lightbox = gallery.querySelector('[data-online-proof-lightbox]');
                    const lightboxImage = gallery.querySelector('.online-proof-lightbox-image');
                    const lightboxDocument = gallery.querySelector('.online-proof-lightbox-document');
                    const lightboxCaption = gallery.querySelector('[data-online-proof-lightbox-caption]');
                    const lightboxPrevious = gallery.querySelector('[data-online-proof-lightbox-previous]');
                    const lightboxNext = gallery.querySelector('[data-online-proof-lightbox-next]');
                    const lightboxClose = gallery.querySelector('[data-online-proof-close]');
                    let activeIndex = 0;
                    let lightboxTrigger = null;

                    if (!slides.length || !lightbox) return;
                    document.body.appendChild(lightbox);

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
                        [previous, next, lightboxPrevious, lightboxNext].forEach(button => {
                            if (button) button.disabled = slides.length < 2;
                        });
                    };
                    const showSlide = index => {
                        activeIndex = (index + slides.length) % slides.length;
                        slides.forEach((slide, slideIndex) => { slide.hidden = slideIndex !== activeIndex; });
                        updateCaption();
                    };
                    const renderLightbox = () => {
                        const attachment = activeAttachment();
                        const amountText = attachment.amount > 0
                            ? ` · ₱${attachment.amount.toLocaleString('en-PH', { minimumFractionDigits: 2, maximumFractionDigits: 2 })}`
                            : '';
                        lightboxCaption.textContent = `${attachment.label}${amountText} · ${activeIndex + 1} of ${slides.length}`;
                        lightboxImage.hidden = attachment.kind !== 'image';
                        lightboxDocument.hidden = attachment.kind === 'image';
                        if (attachment.kind === 'image') {
                            lightboxDocument.removeAttribute('src');
                            lightboxImage.src = attachment.url;
                            lightboxImage.alt = attachment.label;
                        } else {
                            lightboxImage.removeAttribute('src');
                            lightboxDocument.src = attachment.url;
                            lightboxDocument.title = attachment.label;
                        }
                    };
                    const openLightbox = trigger => {
                        lightboxTrigger = trigger;
                        renderLightbox();
                        lightbox.hidden = false;
                        lightboxClose.focus();
                    };
                    const closeLightbox = () => {
                        lightbox.hidden = true;
                        lightboxImage.removeAttribute('src');
                        lightboxDocument.removeAttribute('src');
                        lightboxTrigger?.focus();
                        lightboxTrigger = null;
                    };
                    const move = step => {
                        showSlide(activeIndex + step);
                        if (!lightbox.hidden) renderLightbox();
                    };

                    const bindMove = (button, step) => button?.addEventListener('click', event => {
                        event.preventDefault();
                        event.stopPropagation();
                        move(step);
                    });
                    bindMove(previous, -1);
                    bindMove(next, 1);
                    bindMove(lightboxPrevious, -1);
                    bindMove(lightboxNext, 1);
                    lightboxClose.addEventListener('click', closeLightbox);
                    slides.forEach(slide => {
                        const image = slide.querySelector('.online-proof-image');
                        image?.addEventListener('click', () => openLightbox(image));
                        image?.addEventListener('keydown', event => {
                            if (event.key === 'Enter' || event.key === ' ') {
                                event.preventDefault();
                                openLightbox(image);
                            }
                        });
                    });
                    document.addEventListener('keydown', event => {
                        if (lightbox.hidden) return;
                        if (event.key === 'Escape') closeLightbox();
                        if (event.key === 'ArrowLeft') move(-1);
                        if (event.key === 'ArrowRight') move(1);
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
