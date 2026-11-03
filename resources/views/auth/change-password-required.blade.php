<x-guest-layout>
    <style>
        .login-card { max-height: calc(100vh - 2rem); overflow-y: auto; }
        .password-strength-compact { padding: .15rem .1rem 0; }
        .password-strength-track { height: .3rem; overflow: hidden; border-radius: 999px; background: #e2e8f0; }
        .password-strength-bar { width: 0; height: 100%; border-radius: inherit; background: #94a3b8; transition: width .2s ease, background-color .2s ease; }
        .password-strength-label { color: #64748b; font-size: .78rem; font-weight: 700; }
        .password-field-wrap { position: relative; }
        .password-guidance-popover { position: absolute; z-index: 20; top: calc(100% + .55rem); right: .15rem; left: .15rem; padding: .85rem; border: 1px solid #dbe3ee; border-radius: .85rem; background: #fff; box-shadow: 0 16px 34px rgb(15 23 42 / 20%); opacity: 0; visibility: hidden; transform: translateY(-.35rem); transition: opacity .15s ease, transform .15s ease, visibility .15s ease; pointer-events: none; }
        .password-guidance-popover::before { position: absolute; top: -.45rem; left: 50%; width: .85rem; height: .85rem; border-top: 1px solid #dbe3ee; border-left: 1px solid #dbe3ee; background: #fff; content: ''; transform: translateX(-50%) rotate(45deg); }
        .password-guidance-popover.is-visible { opacity: 1; visibility: visible; transform: translateY(0); }
        .password-guidance-heading { position: relative; display: flex; align-items: center; gap: .45rem; color: #334155; font-size: .8rem; font-weight: 700; }
        .password-requirements-count { margin-left: auto; color: #64748b; font-size: .7rem; font-weight: 700; }
        .password-requirements { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: .45rem .75rem; margin: .65rem 0 0; padding: 0; list-style: none; }
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

            <div class="mb-3 password-field-wrap">
                <label for="password" class="form-label small fw-semibold text-secondary">New Password</label>
                <input id="password" name="password" type="password" class="form-control" required autocomplete="new-password" aria-describedby="passwordStrengthLabel passwordRequirementsPopover">
                <div class="password-strength-compact mt-2" aria-live="polite">
                    <div class="d-flex justify-content-between align-items-center gap-2 mb-1">
                        <span class="small fw-semibold text-secondary">Password strength</span>
                        <span id="passwordStrengthLabel" class="password-strength-label">Not rated</span>
                    </div>
                    <div id="passwordStrengthMeter" class="password-strength-track" role="progressbar" aria-label="Password strength" aria-valuemin="0" aria-valuemax="5" aria-valuenow="0">
                        <div id="passwordStrengthBar" class="password-strength-bar"></div>
                    </div>
                </div>
                <div id="passwordRequirementsPopover" class="password-guidance-popover" role="status" aria-live="polite" aria-hidden="true">
                    <div class="password-guidance-heading">
                        <i class="fa-solid fa-shield-halved text-primary" aria-hidden="true"></i>
                        <span>Password must include:</span>
                        <span id="passwordRequirementsCount" class="password-requirements-count">0/6 met</span>
                    </div>
                    <ul class="password-requirements" aria-label="Password requirements">
                        <li class="password-requirement" data-password-requirement="length"><i class="fa-solid fa-circle" aria-hidden="true"></i><span>At least 12 characters</span></li>
                        <li class="password-requirement" data-password-requirement="uppercase"><i class="fa-solid fa-circle" aria-hidden="true"></i><span>One uppercase letter</span></li>
                        <li class="password-requirement" data-password-requirement="lowercase"><i class="fa-solid fa-circle" aria-hidden="true"></i><span>One lowercase letter</span></li>
                        <li class="password-requirement" data-password-requirement="number"><i class="fa-solid fa-circle" aria-hidden="true"></i><span>One number</span></li>
                        <li class="password-requirement" data-password-requirement="symbol"><i class="fa-solid fa-circle" aria-hidden="true"></i><span>One symbol</span></li>
                        <li class="password-requirement" data-password-requirement="different"><i class="fa-solid fa-circle" aria-hidden="true"></i><span>Different from temporary password</span></li>
                    </ul>
                </div>
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
            const requirementsPopover = document.getElementById('passwordRequirementsPopover');
            const requirementsCount = document.getElementById('passwordRequirementsCount');
            const matchMessage = document.getElementById('passwordMatchMessage');
            if (!form || !temporaryPassword || !newPassword || !confirmation || !strengthLabel || !strengthMeter || !strengthBar || !requirementsPopover || !requirementsCount || !matchMessage) return;

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

            const showGuidance = () => {
                requirementsPopover.classList.add('is-visible');
                requirementsPopover.setAttribute('aria-hidden', 'false');
            };
            const hideGuidance = () => {
                requirementsPopover.classList.remove('is-visible');
                requirementsPopover.setAttribute('aria-hidden', 'true');
            };

            temporaryPassword.addEventListener('input', evaluate);
            newPassword.addEventListener('focus', showGuidance);
            newPassword.addEventListener('input', () => {
                showGuidance();
                evaluate();
            });
            newPassword.addEventListener('blur', hideGuidance);
            confirmation.addEventListener('input', evaluate);
            document.addEventListener('keydown', event => {
                if (event.key === 'Escape') hideGuidance();
            });

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
                    newPassword.focus();
                    showGuidance();
                    showPasswordPopup(issues.join('\n'));
                }
            });

            evaluate();
        })();
    </script>
</x-guest-layout>
