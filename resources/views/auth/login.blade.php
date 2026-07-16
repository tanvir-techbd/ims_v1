<x-guest-layout>
    <div class="auth-shell">
        <div class="auth-card">
            <div class="auth-brand">
                <div class="logo-mark">IM</div>
                <div class="name">Inventory MS</div>
            </div>
            <h1>Sign in to your account</h1>
            <div class="auth-sub">Track requests, approvals and stock in one place.</div>

            @if (session('status'))
                <div class="auth-status">{{ session('status') }}</div>
            @endif

            @if ($errors->any())
                <div class="auth-status" style="background: var(--color-rejected-bg); color: var(--color-rejected);">
                    {{ $errors->first() }}
                </div>
            @endif

            <form method="POST" action="{{ route('login') }}">
                @csrf

                <div class="field">
                    <label for="email">Email</label>
                    <input id="email" type="email" name="email" placeholder="you@company.com" value="{{ old('email') }}" required autofocus autocomplete="username" />
                </div>

                <div class="field">
                    <label for="password">Password</label>
                    <input id="password" type="password" name="password" placeholder="••••••••" required autocomplete="current-password" />
                </div>

                <div class="auth-row">
                    <label style="display:flex; align-items:center; gap:6px;">
                        <input type="checkbox" name="remember" style="width:auto;" /> Remember me
                    </label>
                    @if (Route::has('password.request'))
                        <a href="{{ route('password.request') }}">Forgot password?</a>
                    @endif
                </div>

                <button type="submit" class="btn-primary">Sign in</button>
            </form>

            @if (Route::has('register') && \Laravel\Fortify\Features::enabled(\Laravel\Fortify\Features::registration()))
                <div class="auth-footer">
                    Don't have an account? <a href="{{ route('register') }}">Register</a>
                </div>
            @endif
        </div>
    </div>
</x-guest-layout>
