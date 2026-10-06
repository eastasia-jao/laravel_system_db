@forelse($notifications as $notification)
    @php
        $notificationData = $notification->data ?? [];
        $notificationUrl = route('notifications.read', $notification->id);
        $notificationHub = $notificationHubs->get($notificationData['hub_id'] ?? null);
        $event = $notificationData['event'] ?? null;
        $isReplacement = in_array($event, ['replacement_request', 'replacement_approved', 'replacement_rejected'], true);
        $isRejected = in_array($event, ['rejected', 'replacement_rejected', 'branch_transfer_rejected'], true);
        $isSuccessful = in_array($event, ['confirmed', 'inventory_verification', 'replacement_approved', 'branch_transfer_approved', 'fully_booked_completed'], true);
        $isWalkIn = ($notificationData['channel'] ?? null) === 'walk_in'
            || str_starts_with($notificationData['title'] ?? '', 'Walk-In ')
            || ($isReplacement && $notificationHub && ! $notificationHub->is_head_office);
        $title = $isWalkIn && $isReplacement
            ? str_replace('Wholesale', 'Walk-In', $notificationData['title'] ?? 'Replacement notification')
            : ($notificationData['title'] ?? 'Notification');
        $message = $notificationData['message'] ?? 'You have a new update.';
        if ($isWalkIn) {
            $message = preg_replace_callback('/\bPENDING-(\d+)\b/', fn ($matches) => sprintf('WALK-IN%03d', (int) $matches[1]), $message);
        }
        $visual = $isRejected
            ? ['icon' => 'fa-circle-xmark', 'color' => 'text-danger', 'background' => 'bg-danger']
            : ($isSuccessful
                ? ['icon' => 'fa-clipboard-check', 'color' => 'text-success', 'background' => 'bg-success']
                : ($isWalkIn
                    ? ['icon' => 'fa-cash-register', 'color' => 'text-warning', 'background' => 'bg-warning']
                    : match ($event) {
                        'submitted' => ['icon' => 'fa-file-circle-plus', 'color' => 'text-info', 'background' => 'bg-info'],
                        'import' => ['icon' => 'fa-file-import', 'color' => 'text-success', 'background' => 'bg-success'],
                        'export' => ['icon' => 'fa-file-export', 'color' => 'text-primary', 'background' => 'bg-primary'],
                        'stock_allocation' => ['icon' => 'fa-layer-group', 'color' => 'text-primary', 'background' => 'bg-primary'],
                        'transaction_log' => ['icon' => 'fa-clipboard-list', 'color' => 'text-warning', 'background' => 'bg-warning'],
                        'fully_booked_order' => ['icon' => 'fa-book-open', 'color' => 'text-primary', 'background' => 'bg-primary'],
                        'replacement_request' => ['icon' => 'fa-box-open', 'color' => 'text-warning', 'background' => 'bg-warning'],
                        'branch_transfer_request' => ['icon' => 'fa-right-left', 'color' => 'text-warning', 'background' => 'bg-warning'],
                        'return_recorded' => ['icon' => 'fa-rotate-left', 'color' => 'text-danger', 'background' => 'bg-danger'],
                        default => ['icon' => 'fa-bell', 'color' => 'text-primary', 'background' => 'bg-primary'],
                    }));
    @endphp
    <a href="{{ $notificationUrl }}" class="d-block px-3 py-3 border-bottom {{ $notification->read_at ? 'text-muted' : 'text-dark' }} text-decoration-none" style="white-space: normal; overflow-wrap: anywhere;">
        <div class="d-flex align-items-start gap-2" style="min-width: 0;">
            <div class="rounded-circle {{ $visual['background'] }} bg-opacity-10 {{ $visual['color'] }} d-flex align-items-center justify-content-center" style="width: 36px; height: 36px; flex-shrink: 0;">
                <i class="fa-solid {{ $visual['icon'] }} small"></i>
            </div>
            <div class="flex-grow-1" style="min-width: 0; overflow-wrap: anywhere; word-break: break-word;">
                <div class="fw-semibold small" style="overflow-wrap: anywhere; word-break: break-word;">{{ $title }}</div>
                <div class="small mt-1" style="overflow-wrap: anywhere; word-break: break-word;">{{ $message }}</div>
                <div class="small text-muted mt-1">{{ $notification->created_at?->diffForHumans() }}</div>
            </div>
            @if(is_null($notification->read_at))
                <span class="rounded-circle bg-primary" style="width: 8px; height: 8px; display: inline-block; flex: 0 0 8px; margin-top: 6px;"></span>
            @endif
        </div>
    </a>
@empty
    <div class="p-4 text-center text-muted small">No notifications yet.</div>
@endforelse
@if($notifications->count() >= 10)
    <div class="p-3 border-top text-center bg-white">
        <a href="{{ route('notifications.index') }}" class="btn btn-link p-0 small fw-semibold text-primary text-decoration-none">See more notifications <i class="fa-solid fa-arrow-right ms-1"></i></a>
    </div>
@endif
