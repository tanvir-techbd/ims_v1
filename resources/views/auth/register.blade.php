<x-guest-layout>
    <div class="auth-shell">
        <div class="auth-card">
            <div class="auth-brand">
                <div class="logo-mark">IM</div>
                <div class="name">Inventory MS</div>
            </div>
            <h1>Create your account</h1>
            <div class="auth-sub">An administrator must assign you a role before you can sign in to the panel.</div>

            @if ($errors->any())
                <div class="auth-status" style="background: var(--color-rejected-bg); color: var(--color-rejected);">
                    {{ $errors->first() }}
                </div>
            @endif

            <form method="POST" action="{{ route('register') }}">
                @csrf

                <div class="field">
                    <label for="name">Name</label>
                    <input id="name" type="text" name="name" placeholder="Your full name" value="{{ old('name') }}" required autofocus autocomplete="name" />
                </div>

                <div class="field">
                    <label for="email">Email</label>
                    <input id="email" type="email" name="email" placeholder="you@company.com" value="{{ old('email') }}" required autocomplete="username" />
                </div>

                <div class="field">
                    <label for="password">Password</label>
                    <input id="password" type="password" name="password" placeholder="••••••••" required autocomplete="new-password" />
                </div>

                <div class="field">
                    <label for="password_confirmation">Confirm Password</label>
                    <input id="password_confirmation" type="password" name="password_confirmation" placeholder="••••••••" required autocomplete="new-password" />
                </div>

                @if (Laravel\Jetstream\Jetstream::hasTermsAndPrivacyPolicyFeature())
                    <div class="auth-row">
                        <label style="display:flex; align-items:center; gap:6px;">
                            <input type="checkbox" name="terms" required style="width:auto;" />
                            <span>
                                I agree to the <a target="_blank" href="{{ route('terms.show') }}">Terms of Service</a>
                                and <a target="_blank" href="{{ route('policy.show') }}">Privacy Policy</a>
                            </span>
                        </label>
                    </div>
                @endif

                <button type="submit" class="btn-primary">Register</button>
            </form>

            <div class="auth-footer">
                Already registered? <a href="{{ route('login') }}">Sign in</a>
            </div>
        </div>
    </div>
</x-guest-layout>
