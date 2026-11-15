<x-guest-layout>
    <x-authentication-card>
        <x-slot name="logo">
            <x-authentication-card-logo />
        </x-slot>

        <h1 style="font-family: var(--dot-font-sans); font-size: 1.6rem; font-weight: 600; color: #221b12; margin: 0 0 6px;">Reset your password</h1>
        <p style="font-size: 14px; color: #5b5240; margin: 0 0 28px;">Enter your email and choose a new password.</p>

        <x-validation-errors class="mb-4" />

        <form method="POST" action="{{ route('password.update') }}" style="display: flex; flex-direction: column; gap: 18px;">
            @csrf

            <input type="hidden" name="token" value="{{ $request->route('token') }}">

            <div>
                <label for="email" style="display: block; font-size: 13px; font-weight: 600; color: #5b5240; margin-bottom: 6px;">Email address</label>
                <input id="email" type="email" name="email" value="{{ old('email', $request->email) }}" required autofocus autocomplete="username"
                    style="width: 100%; height: 44px; background: white; border: 1.5px solid rgba(34, 27, 18, 0.18); border-radius: 10px; padding: 0 14px; font-size: 16px; color: #221b12; outline: none; transition: border-color .15s, box-shadow .15s;"
                    onfocus="this.style.borderColor='#f1c62e'; this.style.boxShadow='0 0 0 3px rgba(241,198,46,0.22)'" onblur="this.style.borderColor='rgba(34, 27, 18, 0.18)'; this.style.boxShadow='none'"
                    placeholder="you@example.com">
            </div>

            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 14px;">
                <div>
                    <label for="password" style="display: block; font-size: 13px; font-weight: 600; color: #5b5240; margin-bottom: 6px;">Password</label>
                    <input id="password" type="password" name="password" required autocomplete="new-password"
                        style="width: 100%; height: 44px; background: white; border: 1.5px solid rgba(34, 27, 18, 0.18); border-radius: 10px; padding: 0 14px; font-size: 16px; color: #221b12; outline: none; transition: border-color .15s, box-shadow .15s;"
                        onfocus="this.style.borderColor='#f1c62e'; this.style.boxShadow='0 0 0 3px rgba(241,198,46,0.22)'" onblur="this.style.borderColor='rgba(34, 27, 18, 0.18)'; this.style.boxShadow='none'"
                        placeholder="Min 8 chars">
                </div>
                <div>
                    <label for="password_confirmation" style="display: block; font-size: 13px; font-weight: 600; color: #5b5240; margin-bottom: 6px;">Confirm</label>
                    <input id="password_confirmation" type="password" name="password_confirmation" required autocomplete="new-password"
                        style="width: 100%; height: 44px; background: white; border: 1.5px solid rgba(34, 27, 18, 0.18); border-radius: 10px; padding: 0 14px; font-size: 16px; color: #221b12; outline: none; transition: border-color .15s, box-shadow .15s;"
                        onfocus="this.style.borderColor='#f1c62e'; this.style.boxShadow='0 0 0 3px rgba(241,198,46,0.22)'" onblur="this.style.borderColor='rgba(34, 27, 18, 0.18)'; this.style.boxShadow='none'"
                        placeholder="Same again">
                </div>
            </div>

            <button type="submit"
                style="width: 100%; height: 46px; background: #f1c62e; color: #221b12; font-weight: 700; font-size: 15px; border: none; border-radius: 10px; cursor: pointer; box-shadow: 0 4px 12px rgba(241,198,46,.35);"
                onmouseover="this.style.background='#f5d364'" onmouseout="this.style.background='#f1c62e'">
                Reset Password
            </button>
        </form>
    </x-authentication-card>
</x-guest-layout>
