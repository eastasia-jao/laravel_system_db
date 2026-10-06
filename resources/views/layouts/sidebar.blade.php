<div id="appSidebar" class="sidebar d-flex flex-column flex-shrink-0 p-4 bg-white border-end"
     style="width: 280px; height: 100vh; position: fixed; top: 0; left: 0; z-index: 1000;">
    @php
       $notifications = auth()->user()->notifications()->latest()->limit(10)->get();
       $unreadNotificationCount = auth()->user()->unreadNotifications()->count();
       $sidebarContextHubId = request()->integer('hub_id')
           ?: request()->route('id')
           ?: request()->route('hub')
           ?: auth()->user()->store_hub_id;
       $sidebarContextHub = $sidebarHubs->firstWhere('id', (int) $sidebarContextHubId);
       $sidebarPendingVerificationCount = (int) $sidebarPendingVerificationCountsByHub->get((int) $sidebarContextHubId, 0);
       if (! $sidebarContextHubId && in_array(auth()->user()?->role, ['admin', 'inventory_staff'], true)) {
           $sidebarPendingVerificationCount = (int) $sidebarPendingVerificationCountsByHub->sum();
       }
    @endphp
    
    <div class="d-flex align-items-center gap-2 mb-4 pb-3 border-bottom text-primary fw-bold">
       <button id="sidebarToggle" type="button" class="btn btn-sm btn-light text-primary border-0 flex-shrink-0" title="Hide sidebar" aria-label="Hide sidebar">
           <i class="fa-solid fa-bars"></i>
       </button>
       <div class="d-flex align-items-center gap-2 flex-shrink-0">
           <svg width="25" height="25" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg" aria-hidden="true">
               <path d="M20 7H4C2.89543 7 2 7.89543 2 9V19C2 20.1046 2.89543 21 4 21H20C21.1046 21 22 20.1046 22 19V9C22 7.89543 21.1046 7 20 7Z" stroke="#3b82f6" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
               <path d="M16 21V5C16 4.46957 15.7893 3.96086 15.4142 3.58579C15.0391 3.21071 14.5304 3 14 3H10C9.46957 3 8.96086 3.21071 8.58579 3.58579C8.21071 3.96086 8 4.46957 8 5V21" stroke="#3b82f6" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
           </svg>
           <span style="font-size: 1rem; white-space: nowrap;">Art Caravan PH</span>
       </div>

       <div class="dropdown ms-auto flex-shrink-0">
           <button id="notificationToggle" type="button" class="btn btn-link p-0 border-0 text-primary position-relative" data-bs-toggle="dropdown" aria-expanded="false" title="Notifications">
               <i class="fa-solid fa-bell"></i>
               <span id="notificationBadge" class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-danger {{ $unreadNotificationCount ? '' : 'd-none' }}">
                   {{ $unreadNotificationCount }}
               </span>
           </button>

           <div class="dropdown-menu dropdown-menu-end shadow-sm border-0 p-0" style="width: min(340px, calc(100vw - 1rem)); max-height: 420px; overflow-y: auto;">
               <div class="d-flex justify-content-between align-items-center gap-2 px-3 py-3 border-bottom">
                   <div><div class="text-uppercase small fw-bold text-primary">Recent activity</div><span class="fw-semibold text-dark">Notifications</span></div>
                   <form action="{{ route('notifications.read-all') }}" method="POST" class="m-0">
                       @csrf
                       <button type="submit" class="btn btn-link p-0 small text-primary text-decoration-none text-nowrap">Mark all read</button>
                   </form>
               </div>
               <div id="sidebarNotificationItems"><div>

                   @forelse($notifications as $notification)
                   @php
                       $notificationData = $notification->data ?? [];
                       $notificationUrl = route('notifications.read', $notification->id);
                       $notificationHub = $sidebarHubs->firstWhere('id', $notificationData['hub_id'] ?? null);
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
                   <a href="{{ $notificationUrl }}" class="d-block px-3 py-3 border-bottom {{ $notification->read_at ? 'text-muted' : 'text-dark' }} text-decoration-none" style="white-space: normal; overflow-wrap: anywhere;">
                       <div class="d-flex align-items-start gap-2" style="min-width: 0;">
                           <div class="rounded-circle {{ $notificationVisuals['background'] }} bg-opacity-10 {{ $notificationVisuals['color'] }} d-flex align-items-center justify-content-center" style="width: 36px; height: 36px; flex-shrink: 0;">
                               <i class="fa-solid {{ $notificationVisuals['icon'] }} small"></i>
                           </div>
                           <div class="flex-grow-1" style="min-width: 0; overflow-wrap: anywhere; word-break: break-word;">
                               <div class="fw-semibold small" style="overflow-wrap: anywhere; word-break: break-word;">{{ $notificationTitle }}</div>
                               <div class="small mt-1" style="overflow-wrap: anywhere; word-break: break-word;">{{ $notificationMessage }}</div>
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
                   </div>
                   @if($notifications->count() >= 10)
                       <div class="p-3 border-top text-center bg-white">
                           <a href="{{ route('notifications.index') }}" class="btn btn-link p-0 small fw-semibold text-primary text-decoration-none">See more notifications <i class="fa-solid fa-arrow-right ms-1"></i></a>
                       </div>
                   @endif
               </div>
               </div>
           </div>
       </div>

    <ul class="nav nav-pills flex-column mb-auto gap-1">
        @can('access-dashboard')
        <li class="nav-item">
            <a href="{{ route('dashboard') }}" class="nav-link {{ request()->routeIs('dashboard') ? 'active-link text-primary fw-semibold' : 'text-secondary' }} d-flex align-items-center gap-3 py-2 px-3 rounded-3">
                <i class="fa-solid fa-chart-pie"></i> Dashboard
            </a>
        </li>
        @endcan

        @can('view-inventory')
        @can('view-products')
        <li>
            <a href="{{ route('products.index') }}" class="nav-link {{ request()->routeIs('products.index') ? 'active-link text-primary fw-semibold' : 'text-secondary' }} d-flex align-items-center gap-3 py-2 px-3 rounded-3">
                <i class="fa-solid fa-boxes-stacked"></i> List of Products
            </a>
        </li>
        @endcan
        @can('manage-stock-allocation')
        @if(!auth()->user()->storeHub || auth()->user()->storeHub->is_head_office)
        <li>
            <a href="{{ route('stock-allocation.index') }}" class="nav-link {{ request()->routeIs('stock-allocation.*') ? 'active-link text-primary fw-semibold' : 'text-secondary' }} d-flex align-items-center gap-3 py-2 px-3 rounded-3">
                <i class="fa-solid fa-layer-group"></i> Stock Allocation
            </a>
        </li>
        @endif
        @endcan
        @can('view-transaction-logs')
        <li>
            <a href="{{ route('inventory-transactions.index', $sidebarContextHubId ? ['hub_id' => $sidebarContextHubId] : []) }}" class="nav-link {{ request()->routeIs('inventory-transactions.index') ? 'active-link text-primary fw-semibold' : 'text-secondary' }} d-flex align-items-center gap-3 py-2 px-3 rounded-3">
                <i class="fa-solid fa-clipboard-list"></i> Transaction Logs
            </a>
        </li>
        @endcan
        @can('manage-branch-returns')
        @if(auth()->user()?->role !== 'inventory_staff' && $sidebarContextHub && ! $sidebarContextHub->is_head_office)
        <li>
            <a href="{{ route('inventory-transactions.return.create', ['hub_id' => $sidebarContextHub->id]) }}" class="nav-link {{ request()->routeIs('inventory-transactions.return.*') ? 'active-link text-primary fw-semibold' : 'text-secondary' }} d-flex align-items-center gap-3 py-2 px-3 rounded-3">
                <i class="fa-solid fa-rotate-left"></i> Return Items
            </a>
        </li>
        @endif
        @endcan
        @can('submit-branch-transfers')
        @if(auth()->user()?->role === 'sales_associate')
        <li>
            <a href="{{ route('inventory-transactions.branch-transfer.create') }}" class="nav-link {{ request()->routeIs('inventory-transactions.branch-transfer.*') ? 'active-link text-primary fw-semibold' : 'text-secondary' }} d-flex align-items-center gap-3 py-2 px-3 rounded-3">
                <i class="fa-solid fa-right-left"></i> Stock Transfer (Branch to Branch)
            </a>
        </li>
        @endif
        @endcan
        @endcan

        @can('access-sales')
        <li>
            <a class="nav-link text-secondary d-flex align-items-center gap-3 py-2 px-3 rounded-3" data-bs-toggle="collapse" href="#storeHubDropdown" role="button">
                <i class="fa-solid fa-shop"></i> Store Hub <i class="fa-solid fa-chevron-down ms-auto small"></i>
            </a>
            <div class="collapse ps-2" id="storeHubDropdown">
                <ul class="list-unstyled fw-normal pb-1 small d-flex flex-column gap-1 pt-1" style="max-height: 250px; overflow-y: auto; overflow-x: hidden;">
                    @php
                        $visibleSidebarHubs = in_array(Auth::user()->role, ['admin', 'inventory_staff'], true)
                            ? $sidebarHubs
                            : $sidebarHubs->whereIn('id', Auth::user()->accessibleStoreHubIds());
                        $visibleSidebarHubs = $visibleSidebarHubs->where('status', 'active');
                    @endphp
                    @foreach($visibleSidebarHubs as $hub)
                    @php
                        // Check if the current page matches this hub's route or ID
                        $isActive = request()->route('id') == $hub->id || request()->is('store-hub/' . $hub->id . '*');
                    @endphp
                    <li class="px-2">
                        @php($pendingHubCount = (int) $sidebarPendingVerificationCountsByHub->get($hub->id, 0))
                        <div class="d-flex align-items-center justify-content-between mt-2 mb-1 px-2 rounded-2 {{ $isActive ? 'bg-light border-start border-primary border-3 ps-2' : '' }}">
                            <a href="{{ route('hub.dashboard', $hub->id) }}" class="text-decoration-none {{ $isActive ? 'text-primary fw-bold' : 'text-muted' }} text-truncate" style="font-size: 11px; letter-spacing: 0.5px;" title="{{ $hub->name }}">
                                <i class="fa-solid fa-location-dot me-2 {{ $isActive ? 'text-primary' : '' }}"></i> {{ strtoupper($hub->name) }}
                            </a>
                            @if($pendingHubCount > 0)
                                <span class="badge rounded-pill bg-danger ms-2 flex-shrink-0" aria-label="{{ $pendingHubCount }} inventory item(s) awaiting verification">{{ $pendingHubCount }}</span>
                            @endif
                        </div>
                    </li>
                    @endforeach
                </ul>
            </div>
        </li>
        @endcan

        @can('view-staff-logs')
        <li>
            <a href="{{ route('staff-logs.index') }}" class="nav-link {{ request()->routeIs('staff-logs.*') ? 'active-link text-primary fw-semibold' : 'text-secondary' }} d-flex align-items-center gap-3 py-2 px-3 rounded-3">
                <i class="fa-solid fa-clock-rotate-left"></i> Staff Logs
            </a>
        </li>
        @endcan

        @can('full-access')
        <li>
            <a href="/users" class="nav-link {{ request()->is('users') ? 'active-link text-primary fw-semibold' : 'text-secondary' }} d-flex align-items-center gap-3 py-2 px-3 rounded-3">
                <i class="fa-solid fa-users-gear"></i> Users
            </a>
        </li>
        @endcan

        @can('manage-master-data')
        <li>
            <a href="/configuration" class="nav-link {{ request()->is('configuration') ? 'active-link text-primary fw-semibold' : 'text-secondary' }} d-flex align-items-center gap-3 py-2 px-3 rounded-3">
                <i class="fa-solid fa-sliders"></i> Configuration
            </a>
        </li>
        @endcan
    </ul>

    <div class="pt-3 border-top d-flex align-items-center gap-2">
        <div class="d-flex flex-column" style="min-width: 0; flex: 1 1 auto;">
            <span class="fw-semibold text-dark" style="font-size: .78rem; line-height: 1.2; white-space: nowrap;">{{ Auth::user()->name }}</span>
            <div class="d-flex align-items-center justify-content-between gap-2 mt-1">
                <span class="text-muted text-truncate" style="font-size: 11px; min-width: 0;">@<span>{{ Auth::user()->username }}</span></span>
                <span id="sidebarClock" class="text-muted text-end" style="flex: 0 0 78px; font-size: 11px; white-space: nowrap;" aria-label="Current time"></span>
            </div>
        </div>
        <form method="POST" action="{{ route('logout') }}" class="m-0 flex-shrink-0">
            @csrf
            <button type="submit" class="btn btn-sm btn-light text-danger rounded-circle p-2 shadow-2" title="Log Out">
                <i class="fa-solid fa-right-from-bracket"></i>
            </button>
        </form>
    </div>
</div>
<script>
    (() => {
        const clock = document.getElementById('sidebarClock');
        const updateClock = () => {
            clock.textContent = new Intl.DateTimeFormat('en-PH', {
                timeZone: 'Asia/Manila',
                hour: 'numeric',
                minute: '2-digit',
                second: '2-digit',
                hour12: true,
            }).format(new Date());
        };
        updateClock();
        setInterval(updateClock, 1000);
    })();
    (() => {
        const items = document.getElementById('sidebarNotificationItems');
        const badge = document.getElementById('notificationBadge');
        const feedUrl = @json(route('notifications.feed'));
        if (!items || !badge) return;

        let refreshing = false;
        const refreshNotifications = async () => {
            if (refreshing || document.hidden) return;
            refreshing = true;
            try {
                const response = await fetch(feedUrl, {
                    credentials: 'same-origin',
                    cache: 'no-store',
                    headers: { Accept: 'application/json' },
                });
                if (!response.ok) return;
                const payload = await response.json();
                items.innerHTML = payload.html || '';
                const unread = Number(payload.unread_count || 0);
                badge.textContent = unread;
                badge.classList.toggle('d-none', unread === 0);
            } catch (error) {
                console.warn('Notification refresh failed.', error);
            } finally {
                refreshing = false;
            }
        };

        setInterval(refreshNotifications, 20000);
        document.addEventListener('visibilitychange', () => {
            if (!document.hidden) refreshNotifications();
        });
    })();
</script>
