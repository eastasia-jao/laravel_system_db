@php
    $popupMessages = [];
    foreach (['success' => 'success', 'error' => 'error', 'warning' => 'warning', 'notification_error' => 'warning', 'status' => 'info'] as $key => $kind) {
        if (session($key)) $popupMessages[] = ['text' => match (session($key)) {
            'password-updated' => 'Password updated successfully.',
            'profile-updated' => 'Profile updated successfully.',
            'verification-link-sent' => 'A new verification link has been sent to your email address.',
            default => session($key),
        }, 'type' => $kind, 'title' => $key === 'success'
            ? (str_starts_with((string) session($key), 'Replacement request submitted')
                ? 'Replacement request submitted'
                : (str_starts_with((string) session($key), 'Walk-In sale submitted for inventory verification')
                    ? 'Walk-In sale submitted for verification'
                    : null))
            : null];
    }
    $popupErrors = collect($errors->getBags())->flatMap(fn ($bag) => $bag->all())->unique()->values()->all();
    if ($popupErrors) $popupMessages[] = ['text' => implode("\n", $popupErrors), 'type' => 'error'];
@endphp
<style>
    .branch-transfer-success-popup {
        position: fixed;
        z-index: 2147483647 !important;
        inset: 0;
        width: 100%;
        height: 100%;
        padding: 1rem;
        border: 0;
        display: flex !important;
        align-items: center;
        justify-content: center;
        background: rgb(15 23 42 / 55%);
    }
    .branch-transfer-success-card {
        width: min(460px, 100%);
        padding: 2rem;
        border-radius: 1.25rem;
        background: #fff;
        box-shadow: 0 24px 70px rgb(15 23 42 / 30%);
        text-align: center;
    }
    .branch-transfer-success-icon {
        display: grid;
        place-items: center;
        width: 3.5rem;
        height: 3.5rem;
        margin: 0 auto 1rem;
        border-radius: 1rem;
        background: #ecfdf5;
        color: #047857;
        font-size: 1.75rem;
    }
    .branch-transfer-success-card h2 { font-size: 1.5rem; font-weight: 700; }
    .branch-transfer-success-card p { color: #475569; line-height: 1.6; }
</style>
@if(request()->boolean('branch_transfer_submitted'))
<div id="branch-transfer-success-popup" class="branch-transfer-success-popup" role="alertdialog" aria-modal="true" aria-labelledby="branch-transfer-success-title" aria-describedby="branch-transfer-success-message">
    <section class="branch-transfer-success-card">
        <span class="branch-transfer-success-icon" aria-hidden="true"><i class="fa-solid fa-circle-check"></i></span>
        <div class="text-uppercase small fw-bold text-muted mb-2">Stock Transfer</div>
        <h2 id="branch-transfer-success-title">Request submitted</h2>
        <p id="branch-transfer-success-message">Your branch-to-branch stock transfer request was submitted successfully and is awaiting inventory staff or admin approval.</p>
        <button id="branch-transfer-success-close" type="button" class="btn btn-success px-4">Done</button>
    </section>
</div>
<script>
    (() => {
        const popup = document.getElementById('branch-transfer-success-popup');
        const closeButton = document.getElementById('branch-transfer-success-close');
        if (!popup || !closeButton) return;

        const closePopup = () => {
            popup.remove();
            const url = new URL(window.location.href);
            url.searchParams.delete('branch_transfer_submitted');
            window.history.replaceState({}, '', url);
        };

        closeButton.addEventListener('click', closePopup);
        document.addEventListener('keydown', event => {
            if (event.key === 'Escape') closePopup();
        }, { once: true });
        closeButton.focus();
    })();
</script>
@endif
@if(request()->boolean('stock_transfer_saved'))
<div id="stock-transfer-success-popup" class="branch-transfer-success-popup" role="alertdialog" aria-modal="true" aria-labelledby="stock-transfer-success-title" aria-describedby="stock-transfer-success-message">
    <section class="branch-transfer-success-card">
        <span class="branch-transfer-success-icon" aria-hidden="true"><i class="fa-solid fa-circle-check"></i></span>
        <div class="text-uppercase small fw-bold text-muted mb-2">Stock Transfer</div>
        <h2 id="stock-transfer-success-title">Transfer saved</h2>
        <p id="stock-transfer-success-message">The transfer was saved successfully under document <strong>{{ request()->query('reference', '—') }}</strong>.</p>
        <button id="stock-transfer-success-close" type="button" class="btn btn-success px-4">Done</button>
    </section>
</div>
<script>
    (() => {
        const popup = document.getElementById('stock-transfer-success-popup');
        const closeButton = document.getElementById('stock-transfer-success-close');
        if (!popup || !closeButton) return;

        const closePopup = () => {
            popup.remove();
            const url = new URL(window.location.href);
            url.searchParams.delete('stock_transfer_saved');
            url.searchParams.delete('reference');
            window.history.replaceState({}, '', url);
        };

        closeButton.addEventListener('click', closePopup);
        document.addEventListener('keydown', event => {
            if (event.key === 'Escape') closePopup();
        }, { once: true });
        closeButton.focus();
    })();
</script>
@endif
@if(request()->boolean('restock_saved'))
<div id="restock-success-popup" class="branch-transfer-success-popup" role="alertdialog" aria-modal="true" aria-labelledby="restock-success-title" aria-describedby="restock-success-message">
    <section class="branch-transfer-success-card">
        <span class="branch-transfer-success-icon" aria-hidden="true"><i class="fa-solid fa-circle-check"></i></span>
        <div class="text-uppercase small fw-bold text-muted mb-2">Restock / Added from Request (Warehouse HO)</div>
        <h2 id="restock-success-title">Items added</h2>
        <p id="restock-success-message">Restock items were saved successfully under document <strong>{{ request()->query('reference', '—') }}</strong>.</p>
        <button id="restock-success-close" type="button" class="btn btn-success px-4">Done</button>
    </section>
</div>
<script>
    (() => {
        const popup = document.getElementById('restock-success-popup');
        const closeButton = document.getElementById('restock-success-close');
        if (!popup || !closeButton) return;

        const closePopup = () => {
            popup.remove();
            const url = new URL(window.location.href);
            url.searchParams.delete('restock_saved');
            url.searchParams.delete('reference');
            window.history.replaceState({}, '', url);
        };

        closeButton.addEventListener('click', closePopup);
        document.addEventListener('keydown', event => {
            if (event.key === 'Escape') closePopup();
        }, { once: true });
        closeButton.focus();
    })();
</script>
@endif
@if(request()->boolean('sponsor_workshop_saved'))
<div id="sponsor-workshop-success-popup" class="branch-transfer-success-popup" role="alertdialog" aria-modal="true" aria-labelledby="sponsor-workshop-success-title" aria-describedby="sponsor-workshop-success-message">
    <section class="branch-transfer-success-card">
        <span class="branch-transfer-success-icon" aria-hidden="true"><i class="fa-solid fa-circle-check"></i></span>
        <div class="text-uppercase small fw-bold text-muted mb-2">Event</div>
        <h2 id="sponsor-workshop-success-title">Items recorded</h2>
        <p id="sponsor-workshop-success-message">Event items were saved successfully under document <strong>{{ request()->query('reference', '—') }}</strong>.</p>
        <button id="sponsor-workshop-success-close" type="button" class="btn btn-success px-4">Done</button>
    </section>
</div>
<script>
    (() => {
        const popup = document.getElementById('sponsor-workshop-success-popup');
        const closeButton = document.getElementById('sponsor-workshop-success-close');
        if (!popup || !closeButton) return;

        const closePopup = () => {
            popup.remove();
            const url = new URL(window.location.href);
            url.searchParams.delete('sponsor_workshop_saved');
            url.searchParams.delete('reference');
            window.history.replaceState({}, '', url);
        };

        closeButton.addEventListener('click', closePopup);
        document.addEventListener('keydown', event => {
            if (event.key === 'Escape') closePopup();
        }, { once: true });
        closeButton.focus();
    })();
</script>
@endif
<script>
    (() => {
        const messages = {{ Illuminate\Support\Js::from($popupMessages) }};
        if (!messages.length) return;

        const showMessages = async () => {
            for (const message of messages) {
                await window.AppAlert.show(message.text, message.type, {
                    title: message.title || (message.type === 'success' ? 'All done'
                        : message.type === 'error' ? 'Something needs attention'
                        : message.type === 'warning' ? 'Before you continue'
                        : 'Good to know'),
                });
            }
        };

        // This partial is rendered at the end of <body>, so the DOM and AppAlert
        // script are already available. Running immediately avoids a missed or
        // delayed DOMContentLoaded callback after a redirect.
        if (window.AppAlert) {
            showMessages();
        } else {
            messages.forEach(message => window.alert(message.text));
        }
    })();
</script>
