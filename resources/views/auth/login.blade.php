<x-guest-layout>
    <div class="login-card">
        
        <!-- Minimalist Brand Header -->
        <div class="brand-logo">
            <svg width="28" height="28" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                <path d="M20 7H4C2.89543 7 2 7.89543 2 9V19C2 20.1046 2.89543 21 4 21H20C21.1046 21 22 20.1046 22 19V9C22 7.89543 21.1046 7 20 7Z" stroke="#3b82f6" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                <path d="M16 21V5C16 4.46957 15.7893 3.96086 15.4142 3.58579C15.0391 3.21071 14.5304 3 14 3H10C9.46957 3 8.96086 3.21071 8.58579 3.58579C8.21071 3.96086 8 4.46957 8 5V21" stroke="#3b82f6" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
            </svg>
            <span>Art Caravan PH Inventory</span>
        </div>

        <h5 class="text-center mb-1 fw-bold" style="color: #1e293b;">Welcome Back</h5>
        <p class="text-center text-muted small mb-4">Please sign-in to access the dashboard</p>

        <!-- Session Status -->
        <x-auth-session-status class="alert alert-info mb-3 py-2 small" :status="session('status')" />

        <!-- Validation Errors -->
        @if ($errors->any())
            <div class="alert alert-danger py-2 small mb-3">
                <ul class="mb-0 ps-3">
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <form method="POST" action="{{ route('login') }}">
            @csrf

            <!-- Email Address Field -->
            <!-- Username Field -->
            <div class="mb-3">
                <label for="username" class="form-label small fw-semibold text-secondary">Username</label>
                <input type="text" id="username" name="username" class="form-control" placeholder="Enter your username" value="{{ old('username') }}" required autofocus>
            </div>

            <!-- Password Field -->
            <div class="mb-3">
                <label for="password" class="form-label small fw-semibold text-secondary">Password</label>
                <div class="password-wrapper">
                    <input type="password" id="password" name="password" class="form-control" placeholder="Enter your password" required autocomplete="current-password">
                    <span class="password-toggle" onclick="togglePassword()">
                    </span>
                </div>
            </div>

            <!-- Remember Me Field Layout -->
            <div class="mb-4 form-check d-flex align-items-center">
                <input type="checkbox" id="remember_me" name="remember" class="form-check-input mt-0 me-2">
                <label class="form-check-label small text-muted" for="remember_me">Keep me logged in</label>
            </div>

            <!-- Submit Login Action -->
            <button type="submit" class="btn btn-blue w-100 text-uppercase tracking-wide small">
                Sign In
            </button>
        </form>
    </div>
</x-guest-layout>