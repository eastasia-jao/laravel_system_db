<div class="sidebar d-flex flex-column flex-shrink-0 p-4 bg-white border-end" 
     style="width: 280px; height: 100vh; position: fixed; top: 0; left: 0; z-index: 1000;">
    @php
       $notifications = auth()->user()->notifications()->latest()->limit(10)->get();
       $unreadNotificationCount = auth()->user()->unreadNotifications()->count();
    @endphp
    
    <div class="d-flex align-items-center justify-content-between gap-2 mb-4 pb-3 border-bottom text-primary fw-bold fs-5">
       <svg width="26" height="26" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
           <path d="M20 7H4C2.89543 7 2 7.89543 2 9V19C2 20.1046 2.89543 21 4 21H20C21.1046 21 22 20.1046 22 19V9C22 7.89543 21.1046 7 20 7Z" stroke="#3b82f6" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
           <path d="M16 21V5C16 4.46957 15.7893 3.96086 15.4142 3.58579C15.0391 3.21071 14.5304 3 14 3H10C9.46957 3 8.96086 3.21071 8.58579 3.58579C8.21071 3.96086 8 4.46957 8 5V21" stroke="#3b82f6" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
       </svg>
       <span class="text-truncate">Art Caravan PH</span>

       <div class="dropdown">
           <button type="button" class="btn btn-link p-0 border-0 text-primary position-relative" data-bs-toggle="dropdown" aria-expanded="false" title="Notifications">
               <i class="fa-solid fa-bell"></i>
               @if($unreadNotificationCount)
                   <span class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-danger">
                       {{ $unreadNotificationCount }}
                   </span>
               @endif
           </button>

           <div class="dropdown-menu dropdown-menu-end shadow-sm border-0 p-0" style="width: min(340px, calc(100vw - 1rem)); max-height: 420px; overflow-y: auto;">
               <div class="d-flex justify-content-between align-items-center gap-2 px-3 py-2 border-bottom">
                   <span class="fw-semibold text-dark">Notifications</span>
                   <a href="{{ route('notifications.read-all') }}" class="small text-primary text-decoration-none text-nowrap">Mark all read</a>
               </div>

               @forelse($notifications as $notification)
                   @php
                       $notificationData = $notification->data ?? [];
                       $notificationUrl = route('notifications.read', $notification->id);
                   @endphp
                   <a href="{{ $notificationUrl }}" class="dropdown-item px-3 py-3 border-bottom {{ $notification->read_at ? 'text-muted' : 'text-dark' }}" style="white-space: normal; overflow-wrap: anywhere;">
                       <div class="d-flex align-items-start gap-2" style="min-width: 0;">
                           <div class="rounded-circle bg-primary bg-opacity-10 text-primary d-flex align-items-center justify-content-center" style="width: 36px; height: 36px; flex-shrink: 0;">
                               <i class="fa-solid fa-bell small"></i>
                           </div>
                           <div class="flex-grow-1" style="min-width: 0; overflow-wrap: anywhere; word-break: break-word;">
                               <div class="fw-semibold small" style="overflow-wrap: anywhere; word-break: break-word;">{{ $notificationData['title'] ?? 'Notification' }}</div>
                               <div class="small mt-1" style="overflow-wrap: anywhere; word-break: break-word;">{{ $notificationData['message'] ?? 'You have a new update.' }}</div>
                               <div class="small text-muted mt-1">
                                   {{ $notification->created_at?->diffForHumans() }}
                                   <span class="d-block">{{ $notification->created_at?->format('M d, Y h:i A') }}</span>
                               </div>
                           </div>
                           @if(is_null($notification->read_at))
                               <span class="rounded-circle bg-primary" style="width: 8px; height: 8px; display: inline-block; flex: 0 0 8px; margin-top: 6px;"></span>
                           @endif
                       </div>
                   </a>
               @empty
                   <div class="p-3 text-center text-muted small">No notifications yet.</div>
               @endforelse
           </div>
       </div>
    </div>

    <ul class="nav nav-pills flex-column mb-auto gap-1">
        @can('access-sales')
        <li class="nav-item">
            <a href="{{ route('dashboard') }}" class="nav-link {{ request()->routeIs('dashboard') ? 'active-link text-primary fw-semibold' : 'text-secondary' }} d-flex align-items-center gap-3 py-2 px-3 rounded-3">
                <i class="fa-solid fa-chart-pie"></i> Dashboard
            </a>
        </li>
        @endcan

        @can('view-inventory')
        <li>
            <a href="{{ route('products.index') }}" class="nav-link {{ request()->routeIs('products.index') ? 'active-link text-primary fw-semibold' : 'text-secondary' }} d-flex align-items-center gap-3 py-2 px-3 rounded-3">
                <i class="fa-solid fa-boxes-stacked"></i> List of Products
            </a>
        </li>
        @can('manage-inventory')
        <li>
            <a href="{{ route('stock-allocation.index') }}" class="nav-link {{ request()->routeIs('stock-allocation.*') ? 'active-link text-primary fw-semibold' : 'text-secondary' }} d-flex align-items-center gap-3 py-2 px-3 rounded-3">
                <i class="fa-solid fa-layer-group"></i> Stock Allocation
            </a>
        </li>
        @endcan
        @can('view-transaction-logs')
        <li>
            <a href="{{ route('inventory-transactions.index') }}" class="nav-link {{ request()->routeIs('inventory-transactions.*') ? 'active-link text-primary fw-semibold' : 'text-secondary' }} d-flex align-items-center gap-3 py-2 px-3 rounded-3">
                <i class="fa-solid fa-clipboard-list"></i> Transaction Logs
            </a>
        </li>
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
                            : $sidebarHubs->where('id', Auth::user()->store_hub_id);
                    @endphp
                    @foreach($visibleSidebarHubs as $hub)
                    @php
                        // Check if the current page matches this hub's route or ID
                        $isActive = request()->route('id') == $hub->id || request()->is('store-hub/' . $hub->id . '*');
                    @endphp
                    <li class="px-2">
                        <div class="d-flex align-items-center justify-content-between mt-2 mb-1 px-2 rounded-2 {{ $isActive ? 'bg-light border-start border-primary border-3 ps-2' : '' }}">
                            <a href="{{ route('hub.dashboard', $hub->id) }}" class="text-decoration-none {{ $isActive ? 'text-primary fw-bold' : 'text-muted' }} text-truncate" style="font-size: 11px; letter-spacing: 0.5px;" title="{{ $hub->name }}">
                                <i class="fa-solid fa-location-dot me-2 {{ $isActive ? 'text-primary' : '' }}"></i> {{ strtoupper($hub->name) }}
                            </a>
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
            <a href="#" class="nav-link text-secondary d-flex align-items-center gap-3 py-2 px-3 rounded-3">
                <i class="fa-solid fa-tags"></i> Discount
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

    <div class="pt-3 border-top d-flex align-items-center justify-content-between">
        <div class="d-flex flex-column text-truncate" style="max-width: 150px;">
            <span class="fw-semibold text-dark small text-truncate">{{ Auth::user()->name }}</span>
            <span class="text-muted text-truncate" style="font-size: 11px;">@<span>{{ Auth::user()->username }}</span></span>
        </div>
        <form method="POST" action="{{ route('logout') }}" class="m-0">
            @csrf
            <button type="submit" class="btn btn-sm btn-light text-danger rounded-circle p-2 shadow-2" title="Log Out">
                <i class="fa-solid fa-right-from-bracket"></i>
            </button>
        </form>
    </div>
</div>
