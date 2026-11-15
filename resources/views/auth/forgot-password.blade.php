<x-guest-layout>
    <x-authentication-card>
        <x-slot name="logo">
            <x-authentication-card-logo />
        </x-slot>

        <h1 style="font-family: var(--dot-font-sans); font-size: 1.6rem; font-weight: 600; color: #221b12; margin: 0 0 6px;">Forgot your password?</h1>
        <p style="font-size: 14px; color: #5b5240; margin: 0 0 28px;">No problem. Just enter your email and we'll send you a password reset link.</p>

        <x-validation-errors class="mb-4" />

        @session('status')
            <div style="background: #DCFCE7; border: 1px solid #86EFAC; color: #15803D; border-radius: 10px; padding: 12px 16px; font-size: 13px; font-weight: 500; margin-bottom: 20px;">
                {{ $value }}
            </div>
        @endsession

        <form method="POST" action="{{ route('password.email') }}" style="display: flex; flex-direction: column; gap: 18px;">
            @csrf

            <div>
                <label for="email" style="display: block; font-size: 13px; font-weight: 600; color: #5b5240; margin-bottom: 6px;">Email address</label>
                <input id="email" type="email" name="email" value="{{ old('email') }}" required autofocus autocomplete="username"
                    style="width: 100%; height: 44px; background: white; border: 1.5px solid rgba(34, 27, 18, 0.18); border-radius: 10px; padding: 0 14px; font-size: 16px; color: #221b12; outline: none; transition: border-color .15s, box-shadow .15s;"
                    onfocus="this.style.borderColor='#f1c62e'; this.style.boxShadow='0 0 0 3px rgba(241,198,46,0.22)'" onblur="this.style.borderColor='rgba(34, 27, 18, 0.18)'; this.style.boxShadow='none'"
                    placeholder="you@example.com">
            </div>

            <button type="submit"
                style="width: 100%; height: 46px; background: #f1c62e; color: #221b12; font-weight: 700; font-size: 15px; border: none; border-radius: 10px; cursor: pointer; box-shadow: 0 4px 12px rgba(241,198,46,.35);"
                onmouseover="this.style.background='#f5d364'" onmouseout="this.style.background='#f1c62e'">
                Email Password Reset Link
            </button>
        </form>

        <p style="margin-top: 24px; text-align: center; font-size: 13px; color: #5b5240;">
            Remember your password?
            <a href="{{ route('login') }}" style="color: #d2232a; font-weight: 600; text-decoration: none;">Sign in</a>
        </p>
    </x-authentication-card>
</x-guest-layout>
