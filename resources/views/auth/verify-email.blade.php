<x-guest-layout>
    <x-authentication-card>
        <x-slot name="logo">
            <x-authentication-card-logo />
        </x-slot>

        <h1 style="font-family: var(--dot-font-sans); font-size: 1.6rem; font-weight: 600; color: #221b12; margin: 0 0 6px;">Verify your email</h1>
        <p style="font-size: 14px; color: #5b5240; margin: 0 0 28px;">We've sent a verification link to your email address. Click the link to verify your account.</p>

        @if (session('status') == 'verification-link-sent')
            <div style="background: #DCFCE7; border: 1px solid #86EFAC; color: #15803D; border-radius: 10px; padding: 12px 16px; font-size: 13px; font-weight: 500; margin-bottom: 20px;">
                {{ __('A new verification link has been sent to the email address you provided during registration.') }}
            </div>
        @endif

        <div style="display: flex; flex-direction: column; gap: 12px;">
            <form method="POST" action="{{ route('verification.send') }}">
                @csrf
                <button type="submit"
                    style="width: 100%; height: 46px; background: #f1c62e; color: #221b12; font-weight: 700; font-size: 15px; border: none; border-radius: 10px; cursor: pointer; box-shadow: 0 4px 12px rgba(241,198,46,.35);"
                    onmouseover="this.style.background='#f5d364'" onmouseout="this.style.background='#f1c62e'">
                    {{ __('Resend Verification Email') }}
                </button>
            </form>

            <div style="display: flex; gap: 12px; align-items: center; justify-content: center;">
                <a href="{{ route('profile.show') }}" style="font-size: 13px; color: #d2232a; font-weight: 600; text-decoration: none;">
                    {{ __('Edit Profile') }}
                </a>
                <span style="color: rgba(34, 27, 18, 0.18);">·</span>
                <form method="POST" action="{{ route('logout') }}" style="display: inline;">
                    @csrf
                    <button type="submit" style="font-size: 13px; color: #d2232a; font-weight: 600; text-decoration: none; background: none; border: none; cursor: pointer; padding: 0;">
                        {{ __('Log Out') }}
                    </button>
                </form>
            </div>
        </div>
    </x-authentication-card>
</x-guest-layout>
