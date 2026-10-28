@extends('layouts.app')

@section('content')
<style>
    .notifications-page { max-width: 980px; }
    .notification-card { border: 0; border-left: 4px solid transparent; border-radius: 12px; box-shadow: 0 5px 16px rgba(15,23,42,.06); }
    .notification-card.unread { border-left-color: #2563eb; background: #eff6ff; }
    .notification-icon { width: 40px; height: 40px; flex: 0 0 40px; }
    .notifications-list { display: grid; gap: .5rem; max-height: min(65vh, 680px); padding: .15rem .4rem .15rem 0; overflow-x: hidden; overflow-y: auto; overscroll-behavior-y: contain; scrollbar-gutter: stable; scrollbar-width: thin; scrollbar-color: #94a3b8 #f1f5f9; }
    .notifications-list::-webkit-scrollbar { width: 8px; }
    .notifications-list::-webkit-scrollbar-track { background: #f1f5f9; }
    .notifications-list::-webkit-scrollbar-thumb { border-radius: 999px; background: #94a3b8; }
    .notifications-list::-webkit-scrollbar-thumb:hover { background: #64748b; }
    .notifications-load-more { display: flex; flex-wrap: wrap; align-items: center; justify-content: space-between; gap: .75rem; margin-top: 1rem; padding-top: 1rem; border-top: 1px solid #e2e8f0; }
</style>

<div class="container-fluid p-4 notifications-page">
    <x-page-header class="mb-4" eyebrow="Activity center" title="Notifications" description="Review updates, workflow alerts, and inventory activity." icon="fa-bell">
        <x-slot:actions>
            <form action="{{ route('notifications.read-all') }}" method="POST" class="m-0">@csrf<button type="submit" class="btn btn-primary btn-sm">Mark all as read</button></form>
        </x-slot:actions>
    </x-page-header>

    <div class="d-flex justify-content-between align-items-center mb-3">
        <h5 class="fw-bold mb-0">All notifications</h5>
        <span class="small text-muted">{{ $notifications->total() }} total</span>
    </div>

    <div id="notificationList" class="notifications-list" aria-label="Notification history">
        @forelse($notifications as $notification)
            @php
                $notificationData = $notification->data ?? [];
                $notificationUrl = route('notifications.read', $notification->id);
                $notificationHub = $notificationHubs->get($notificationData['hub_id'] ?? null);
                $isReplacementNotification = in_array($notificationData['event'] ?? null, ['replacement_request', 'replacement_approved', 'replacement_rejected'], true);
                $isWalkInNotification = ($notificationData['channel'] ?? null) === 'walk_in'
                    || str_starts_with($notificationData['title'] ?? '', 'Walk-In ')
                    || ($isReplacementNotification && $notificationHub && ! $notificationHub->is_head_office);
                $notificationTitle = $isWalkInNotification && $isReplacementNotification
                    ? str_replace('Wholesale', 'Walk-In', $notificationData['title'] ?? 'Replacement notification')
                    : ($notificationData['title'] ?? 'Notification');
                $notificationMessage = $notificationData['message'] ?? 'You have a new update.';
                if ($isWalkInNotification) {
                    $notificationMessage = preg_replace_callback(
                        '/\bPENDING-(\d+)\b/',
                        fn ($matches) => sprintf('WALK-IN%03d', (int) $matches[1]),
                        $notificationMessage
                    );
                }
                $isRejectedNotification = in_array($notificationData['event'] ?? null, ['rejected', 'replacement_rejected', 'branch_transfer_rejected', 'fully_booked_rejected'], true);
                $isSuccessfulVerification = in_array($notificationData['event'] ?? null, ['confirmed', 'inventory_verification', 'replacement_approved', 'branch_transfer_approved', 'fully_booked_completed'], true);
                $notificationVisuals = $isRejectedNotification
                    ? ['icon' => 'fa-circle-xmark', 'color' => 'text-danger', 'background' => 'bg-danger']
                    : ($isSuccessfulVerification
                    ? ['icon' => 'fa-clipboard-check', 'color' => 'text-success', 'background' => 'bg-success']
                    : ($isWalkInNotification
                    ? ['icon' => 'fa-cash-register', 'color' => 'text-warning', 'background' => 'bg-warning']
                    : match ($notificationData['event'] ?? null) {
                    'submitted' => ['icon' => 'fa-file-circle-plus', 'color' => 'text-info', 'background' => 'bg-info'],
                    'confirmed', 'inventory_verification' => ['icon' => 'fa-clipboard-check', 'color' => 'text-success', 'background' => 'bg-success'],
                    'rejected', 'replacement_rejected' => ['icon' => 'fa-circle-xmark', 'color' => 'text-danger', 'background' => 'bg-danger'],
                    'import', 'product_file_request' => ['icon' => 'fa-file-import', 'color' => 'text-success', 'background' => 'bg-success'],
                    'product_file_reviewed' => ($notificationData['file_type'] ?? 'import') === 'export'
                        ? ['icon' => 'fa-file-export', 'color' => 'text-primary', 'background' => 'bg-primary']
                        : ['icon' => 'fa-file-import', 'color' => 'text-success', 'background' => 'bg-success'],
                    'export' => ['icon' => 'fa-file-export', 'color' => 'text-primary', 'background' => 'bg-primary'],
                    'stock_allocation' => ['icon' => 'fa-layer-group', 'color' => 'text-primary', 'background' => 'bg-primary'],
                    'transaction_log' => ['icon' => 'fa-clipboard-list', 'color' => 'text-warning', 'background' => 'bg-warning'],
                    'fully_booked_order' => ['icon' => 'fa-book-open', 'color' => 'text-primary', 'background' => 'bg-primary'],
                    'replacement_request' => ['icon' => 'fa-box-open', 'color' => 'text-warning', 'background' => 'bg-warning'],
                    'branch_transfer_request' => ['icon' => 'fa-right-left', 'color' => 'text-warning', 'background' => 'bg-warning'],
                    'replacement_approved' => ['icon' => 'fa-boxes-stacked', 'color' => 'text-success', 'background' => 'bg-success'],
                    'return_recorded' => ['icon' => 'fa-rotate-left', 'color' => 'text-danger', 'background' => 'bg-danger'],
                    default => ['icon' => 'fa-bell', 'color' => 'text-primary', 'background' => 'bg-primary'],
                }));
            @endphp
            <a href="{{ $notificationUrl }}" class="notification-card card p-3 text-decoration-none text-dark {{ is_null($notification->read_at) ? 'unread' : '' }}">
                <div class="d-flex align-items-start gap-3">
                    <div class="notification-icon rounded-circle {{ $notificationVisuals['background'] }} bg-opacity-10 {{ $notificationVisuals['color'] }} d-flex align-items-center justify-content-center">
                        <i class="fa-solid {{ $notificationVisuals['icon'] }}"></i>
                    </div>
                    <div class="flex-grow-1 min-width-0">
                        <div class="d-flex justify-content-between gap-2">
                            <strong>{{ $notificationTitle }}</strong>
                            @if(is_null($notification->read_at))<span class="badge text-bg-primary">New</span>@endif
                        </div>
                        <div class="small mt-1">{{ $notificationMessage }}</div>
                        <div class="small text-muted mt-2">{{ $notification->created_at?->diffForHumans() }}</div>
                    </div>
                </div>
            </a>
        @empty
            <div class="card border-0 shadow-sm p-5 text-center text-muted">No notifications yet.</div>
        @endforelse
    </div>

    @if($notifications->total() > 0)
        <div class="notifications-load-more">
            <span id="notificationLoadedCount" class="small text-muted" data-total="{{ $notifications->total() }}" aria-live="polite">
                Showing {{ $notifications->count() }} of {{ $notifications->total() }} notifications
            </span>
            <button type="button" id="loadMoreNotifications" class="btn btn-outline-primary btn-sm" data-next-url="{{ $notifications->nextPageUrl() }}" @if(!$notifications->hasMorePages()) hidden @endif>
                <i class="fa-solid fa-angles-down me-1" aria-hidden="true"></i>Load more notifications
            </button>
        </div>
    @endif
</div>
@endsection

@push('scripts')
<script>
    (() => {
        const list = document.getElementById('notificationList');
        const button = document.getElementById('loadMoreNotifications');
        const count = document.getElementById('notificationLoadedCount');
        if (!list || !button || !count) return;

        let loading = false;
        const defaultButtonHtml = button.innerHTML;

        button.addEventListener('click', async () => {
            const nextUrl = button.dataset.nextUrl;
            if (loading || !nextUrl) return;

            loading = true;
            button.disabled = true;
            button.innerHTML = '<i class="fa-solid fa-spinner fa-spin me-1" aria-hidden="true"></i>Loading notifications';

            try {
                const response = await fetch(nextUrl, {
                    credentials: 'same-origin',
                    headers: { Accept: 'text/html', 'X-Requested-With': 'XMLHttpRequest' },
                });
                if (!response.ok) throw new Error(`Notification request failed with ${response.status}`);

                const documentPage = new DOMParser().parseFromString(await response.text(), 'text/html');
                const nextList = documentPage.getElementById('notificationList');
                const nextButton = documentPage.getElementById('loadMoreNotifications');
                nextList?.querySelectorAll('.notification-card').forEach(card => list.append(card));

                button.dataset.nextUrl = nextButton?.dataset.nextUrl || '';
                button.hidden = !button.dataset.nextUrl;
                const loaded = list.querySelectorAll('.notification-card').length;
                count.textContent = `Showing ${loaded} of ${count.dataset.total} notifications`;
                button.innerHTML = defaultButtonHtml;
            } catch (error) {
                button.innerHTML = '<i class="fa-solid fa-rotate-right me-1" aria-hidden="true"></i>Retry load more';
            } finally {
                loading = false;
                button.disabled = false;
            }
        });
    })();
</script>
@endpush
