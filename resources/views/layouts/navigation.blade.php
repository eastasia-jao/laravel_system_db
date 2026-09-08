<nav class="navbar navbar-expand-lg navbar-light bg-white border-bottom py-3 shadow-sm">
    <div class="container">
        <a class="navbar-brand fw-bold text-primary d-flex align-items-center gap-2" href="{{ route('dashboard') }}">
            <svg width="24" height="24" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                <path d="M20 7H4C2.89543 7 2 7.89543 2 9V19C2 20.1046 2.89543 21 4 21H20C21.1046 21 22 20.1046 22 19V9C22 7.89543 21.1046 7 20 7Z" stroke="#3b82f6" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                <path d="M16 21V5C16 4.46957 15.7893 3.96086 15.4142 3.58579C15.0391 3.21071 14.5304 3 14 3H10C9.46957 3 8.96086 3.21071 8.58579 3.58579C8.21071 3.96086 8 4.46957 8 5V21" stroke="#3b82f6" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
            </svg>
            <span>Art Caravan PH</span>
        </a>

        <div class="ms-auto d-flex align-items-center gap-3">
            <span class="text-muted small d-none d-sm-inline">Welcome, <strong>{{ Auth::user()->name }}</strong></span>
            
            <form method="POST" action="{{ route('logout') }}" class="m-0">
                @csrf
                <button type="submit" class="btn btn-sm btn-outline-danger px-3 rounded-pill fw-medium">
                    <i class="fa-solid fa-right-from-bracket me-1"></i> Log Out
                </button>
            </form>
        </div>
    </div>
</nav>