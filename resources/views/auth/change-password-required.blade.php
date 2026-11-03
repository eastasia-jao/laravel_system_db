<x-guest-layout>
    <div class="login-card">
        <div class="brand-logo">
            <i class="fa-solid fa-shield-halved" aria-hidden="true"></i>
            <span>Secure Your Account</span>
        </div>

        <div class="alert alert-primary border-0 mb-4" role="status">
            <div class="fw-semibold mb-1">Create your private password</div>
            <div class="small">The administrator issued a temporary password. Replace it before continuing so only you know your account password.</div>
        </div>

        @if($errors->updatePassword->any())
            <div class="alert alert-danger py-2" role="alert">
                <ul class="small mb-0 ps-3">
                    @foreach($errors->updatePassword->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <form method="POST" action="{{ route('password.update') }}">
            @csrf
            @method('PUT')

            <div class="mb-3">
                <label for="current_password" class="form-label small fw-semibold text-secondary">Temporary Password</label>
                <input id="current_password" name="current_password" type="password" class="form-control" required autofocus autocomplete="current-password">
            </div>

            <div class="mb-3">
                <label for="password" class="form-label small fw-semibold text-secondary">New Password</label>
                <input id="password" name="password" type="password" class="form-control" required autocomplete="new-password">
            </div>

            <div class="mb-4">
                <label for="password_confirmation" class="form-label small fw-semibold text-secondary">Confirm New Password</label>
                <input id="password_confirmation" name="password_confirmation" type="password" class="form-control" required autocomplete="new-password">
                <div class="form-text">Use at least 8 characters. Do not reuse the temporary password.</div>
            </div>

            <button type="submit" class="btn btn-blue w-100">
                <i class="fa-solid fa-key me-1" aria-hidden="true"></i> Save Private Password
            </button>
        </form>

        <form method="POST" action="{{ route('logout') }}" class="mt-3 text-center">
            @csrf
            <button type="submit" class="btn btn-link btn-sm text-secondary text-decoration-none">Sign out instead</button>
        </form>
    </div>
</x-guest-layout>
