<x-guest-layout>
    <style>
        .login-card { max-height: calc(100vh - 2rem); overflow-y: auto; }
        .password-strength-compact { padding: .15rem .1rem 0; }
        .password-strength-track { height: .3rem; overflow: hidden; border-radius: 999px; background: #e2e8f0; }
        .password-strength-bar { width: 0; height: 100%; border-radius: inherit; background: #94a3b8; transition: width .2s ease, background-color .2s ease; }
        .password-strength-label { color: #64748b; font-size: .78rem; font-weight: 700; }
        .password-requirements-disclosure { overflow: hidden; border: 1px solid #e2e8f0; border-radius: .65rem; background: #fff; }
        .password-requirements-disclosure summary { display: flex; align-items: center; justify-content: space-between; gap: .75rem; min-height: 2.35rem; padding: .45rem .65rem; color: #475569; font-size: .75rem; font-weight: 600; cursor: pointer; list-style: none; }
        .password-requirements-disclosure summary::-webkit-details-marker { display: none; }
        .password-requirements-disclosure summary:hover { background: #f8fafc; }
        .password-requirements-disclosure[open] summary { border-bottom: 1px solid #e2e8f0; background: #f8fafc; }
        .password-requirements-chevron { color: #94a3b8; font-size: .68rem; transition: transform .15s ease; }
        .password-requirements-disclosure[open] .password-requirements-chevron { transform: rotate(180deg); }
        .password-requirements-count { margin-left: auto; color: #64748b; font-size: .7rem; font-weight: 700; }
        .password-requirements { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: .4rem .75rem; margin: 0; padding: .7rem .75rem; list-style: none; }
        .password-requirement { display: flex; align-items: center; gap: .4rem; color: #64748b; font-size: .72rem; }
        .password-requirement i { width: .85rem; color: #94a3b8; text-align: center; }
        .password-requirement.is-met { color: #047857; }
        .password-requirement.is-met i { color: #10b981; }
        @media (max-width: 420px) { .password-requirements { grid-template-columns: 1fr; } }
    </style>

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

        <form id="requiredPasswordForm" method="POST" action="{{ route('password.update') }}" novalidate>
            @csrf
            @method('PUT')

            <div class="mb-3">
                <label for="current_password" class="form-label small fw-semibold text-secondary">Temporary Password</label>
                <input id="current_password" name="current_password" type="password" class="form-control" required autofocus autocomplete="current-password">
            </div>

            <div class="mb-3">
                <label for="password" class="form-label small fw-semibold text-secondary">New Password</label>
                <input id="password" name="password" type="password" class="form-control" required autocomplete="new-password">
                <div class="password-strength-compact mt-2" aria-live="polite">
                    <div class="d-flex justify-content-between align-items-center gap-2 mb-1">
                        <span class="small fw-semibold text-secondary">Password strength</span>
                        <span id="passwordStrengthLabel" class="password-strength-label">Not rated</span>
                    </div>
                    <div id="passwordStrengthMeter" class="password-strength-track" role="progressbar" aria-label="Password strength" aria-valuemin="0" aria-valuemax="5" aria-valuenow="0">
                        <div id="passwordStrengthBar" class="password-strength-bar"></div>
                    </div>
                </div>
                <details id="passwordRequirementsDisclosure" class="password-requirements-disclosure mt-2">
                    <summary>
                        <span><i class="fa-solid fa-shield-halved me-1" aria-hidden="true"></i>Password requirements</span>
                        <span id="passwordRequirementsCount" class="password-requirements-count">0/6 met</span>
                        <i class="fa-solid fa-chevron-down password-requirements-chevron" aria-hidden="true"></i>
                    </summary>
                    <ul class="password-requirements" aria-label="Password requirements">
                        <li class="password-requirement" data-password-requirement="length"><i class="fa-solid fa-circle" aria-hidden="true"></i><span>At least 12 characters</span></li>
                        <li class="password-requirement" data-password-requirement="uppercase"><i class="fa-solid fa-circle" aria-hidden="true"></i><span>One uppercase letter</span></li>
                        <li class="password-requirement" data-password-requirement="lowercase"><i class="fa-solid fa-circle" aria-hidden="true"></i><span>One lowercase letter</span></li>
                        <li class="password-requirement" data-password-requirement="number"><i class="fa-solid fa-circle" aria-hidden="true"></i><span>One number</span></li>
                        <li class="password-requirement" data-password-requirement="symbol"><i class="fa-solid fa-circle" aria-hidden="true"></i><span>One symbol</span></li>
                        <li class="password-requirement" data-password-requirement="different"><i class="fa-solid fa-circle" aria-hidden="true"></i><span>Different from temporary password</span></li>
                    </ul>
                </details>
            </div>

            <div class="mb-4">
                <label for="password_confirmation" class="form-label small fw-semibold text-secondary">Confirm New Password</label>
                <input id="password_confirmation" name="password_confirmation" type="password" class="form-control" required autocomplete="new-password">
                <div id="passwordMatchMessage" class="form-text" aria-live="polite">Re-enter the new password exactly.</div>
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

    <script>
        (() => {
            const form = document.getElementById('requiredPasswordForm');
            const temporaryPassword = document.getElementById('current_password');
            const newPassword = document.getElementById('password');
            const confirmation = document.getElementById('password_confirmation');
            const strengthLabel = document.getElementById('passwordStrengthLabel');
            const strengthMeter = document.getElementById('passwordStrengthMeter');
            const strengthBar = document.getElementById('passwordStrengthBar');
            const requirementsDisclosure = document.getElementById('passwordRequirementsDisclosure');
            const requirementsCount = document.getElementById('passwordRequirementsCount');
            const matchMessage = document.getElementById('passwordMatchMessage');
            if (!form || !temporaryPassword || !newPassword || !confirmation || !strengthLabel || !strengthMeter || !strengthBar || !requirementsDisclosure || !requirementsCount || !matchMessage) return;

            const requirementElements = Object.fromEntries(
                [...document.querySelectorAll('[data-password-requirement]')]
                    .map(element => [element.dataset.passwordRequirement, element])
            );
            const compositionLabels = {
                length: 'Use at least 12 characters.',
                uppercase: 'Add at least one uppercase letter.',
                lowercase: 'Add at least one lowercase letter.',
                number: 'Add at least one number.',
                symbol: 'Add at least one symbol.',
            };

            const evaluate = () => {
                const value = newPassword.value;
                const checks = {
                    length: value.length >= 12,
                    uppercase: /[A-Z]/.test(value),
                    lowercase: /[a-z]/.test(value),
                    number: /[0-9]/.test(value),
                    symbol: /[^A-Za-z0-9]/.test(value),
                    different: temporaryPassword.value.length > 0 && value.length > 0 && value !== temporaryPassword.value,
                };
                const score = ['length', 'uppercase', 'lowercase', 'number', 'symbol']
                    .filter(key => checks[key]).length;

                Object.entries(checks).forEach(([key, met]) => {
                    const element = requirementElements[key];
                    if (!element) return;
                    element.classList.toggle('is-met', met);
                    const icon = element.querySelector('i');
                    icon?.classList.toggle('fa-circle', !met);
                    icon?.classList.toggle('fa-circle-check', met);
                });
                const requirementsMet = Object.values(checks).filter(Boolean).length;
                requirementsCount.textContent = requirementsMet === 6 ? 'All met' : `${requirementsMet}/6 met`;

                let label = 'Not rated';
                let color = '#94a3b8';
                if (value.length > 0 && score <= 2) {
                    label = 'Weak';
                    color = '#dc2626';
                } else if (score <= 4 && value.length > 0) {
                    label = 'Medium';
                    color = '#d97706';
                } else if (score === 5) {
                    label = 'Strong';
                    color = '#059669';
                }

                strengthLabel.textContent = label;
                strengthLabel.style.color = color;
                strengthBar.style.width = `${score * 20}%`;
                strengthBar.style.backgroundColor = color;
                strengthMeter.setAttribute('aria-valuenow', String(score));
                strengthMeter.setAttribute('aria-valuetext', label);

                const matches = confirmation.value.length > 0 && confirmation.value === value;
                matchMessage.textContent = confirmation.value.length === 0
                    ? 'Re-enter the new password exactly.'
                    : (matches ? 'Passwords match.' : 'Passwords do not match.');
                matchMessage.classList.toggle('text-success', matches);
                matchMessage.classList.toggle('text-danger', confirmation.value.length > 0 && !matches);

                return { checks, matches };
            };

            const showPasswordPopup = message => {
                if (window.AppAlert?.show) {
                    return window.AppAlert.show(message, 'error', {
                        title: 'Password needs attention',
                        buttonLabel: 'Review password',
                    });
                }

                window.alert(message);
            };

            [temporaryPassword, newPassword, confirmation].forEach(input => input.addEventListener('input', evaluate));

            form.addEventListener('submit', event => {
                const { checks, matches } = evaluate();
                const issues = [];
                if (!temporaryPassword.value) issues.push('Enter the temporary password.');
                Object.entries(compositionLabels).forEach(([key, message]) => {
                    if (!checks[key]) issues.push(message);
                });
                if (!checks.different) issues.push('The new password cannot reuse the temporary password.');
                if (!confirmation.value) {
                    issues.push('Confirm the new password.');
                } else if (!matches) {
                    issues.push('The new password and confirmation do not match.');
                }

                if (issues.length > 0) {
                    event.preventDefault();
                    requirementsDisclosure.open = true;
                    showPasswordPopup(issues.join('\n'));
                }
            });

            evaluate();
        })();
    </script>
</x-guest-layout>
