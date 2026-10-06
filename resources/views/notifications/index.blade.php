@extends('layouts.app')

@section('content')
<style>
    .notifications-page { max-width: 980px; }
    .notifications-hero { border-radius: 22px; padding: 1.75rem 2rem; color: #fff; background: linear-gradient(135deg, #1e3a8a, #2563eb 65%, #38bdf8); box-shadow: 0 14px 32px rgba(37,99,235,.16); }
    .notification-card { border: 0; border-left: 4px solid transparent; border-radius: 12px; box-shadow: 0 5px 16px rgba(15,23,42,.06); }
    .notification-card.unread { border-left-color: #2563eb; background: #eff6ff; }
    .notification-icon { width: 40px; height: 40px; flex: 0 0 40px; }
</style>

<div class="container-fluid p-4 notifications-page">
    <div class="notifications-hero d-flex flex-wrap justify-content-between align-items-center gap-3 mb-4">
        <div>
            <div class="text-uppercase small fw-bold opacity-75 mb-2">Activity center</div>
            <h2 class="fw-bold mb-1">Notifications</h2>
            <p class="mb-0 opacity-75">Review updates, workflow alerts, and inventory activity.</p>
        </div>
        <form action="{{ route('notifications.read-all') }}" method="POST" class="m-0">
            @csrf
            <button type="submit" class="btn btn-light btn-sm">Mark all as read</button>
        </form>
    </div>

    <div class="d-flex justify-content-between align-items-center mb-3">
        <h5 class="fw-bold mb-0">All notifications</h5>
        <span class="small text-muted">{{ $notifications->total() }} total</span>
    </div>

    <div class="d-grid gap-2">
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
                $isRejectedNotification = in_array($notificationData['event'] ?? null, ['rejected', 'replacement_rejected', 'branch_transfer_rejected'], true);
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

    <div class="mt-4">{{ $notifications->links() }}</div>
</div>
@endsection
