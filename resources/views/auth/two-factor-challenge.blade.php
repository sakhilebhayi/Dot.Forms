<x-guest-layout>
    <x-authentication-card>
        <x-slot name="logo">
            <x-authentication-card-logo />
        </x-slot>

        <div x-data="{ recovery: false }">
            <h1 style="font-family: var(--dot-font-sans); font-size: 1.6rem; font-weight: 600; color: #221b12; margin: 0 0 6px;" x-show="! recovery">
                Two-factor authentication
            </h1>
            <h1 style="font-family: var(--dot-font-sans); font-size: 1.6rem; font-weight: 600; color: #221b12; margin: 0 0 6px;" x-cloak x-show="recovery">
                Enter recovery code
            </h1>

            <p style="font-size: 14px; color: #5b5240; margin: 0 0 28px;" x-show="! recovery">
                {{ __('Enter the code from your authenticator app to complete the login.') }}
            </p>
            <p style="font-size: 14px; color: #5b5240; margin: 0 0 28px;" x-cloak x-show="recovery">
                {{ __('Enter one of your emergency recovery codes to continue.') }}
            </p>

            <x-validation-errors class="mb-4" />

            <form method="POST" action="{{ route('two-factor.login') }}" style="display: flex; flex-direction: column; gap: 18px;">
                @csrf

                <div x-show="! recovery">
                    <label for="code" style="display: block; font-size: 13px; font-weight: 600; color: #5b5240; margin-bottom: 6px;">Authentication code</label>
                    <input id="code" type="text" inputmode="numeric" name="code" autofocus x-ref="code" autocomplete="one-time-code"
                        style="width: 100%; height: 44px; background: white; border: 1.5px solid rgba(34, 27, 18, 0.18); border-radius: 10px; padding: 0 14px; font-size: 16px; color: #221b12; outline: none; transition: border-color .15s, box-shadow .15s; text-align: center; letter-spacing: .1em;"
                        onfocus="this.style.borderColor='#f1c62e'; this.style.boxShadow='0 0 0 3px rgba(241,198,46,0.22)'" onblur="this.style.borderColor='rgba(34, 27, 18, 0.18)'; this.style.boxShadow='none'"
                        placeholder="000000">
                </div>

                <div x-cloak x-show="recovery">
                    <label for="recovery_code" style="display: block; font-size: 13px; font-weight: 600; color: #5b5240; margin-bottom: 6px;">Recovery code</label>
                    <input id="recovery_code" type="text" name="recovery_code" x-ref="recovery_code" autocomplete="one-time-code"
                        style="width: 100%; height: 44px; background: white; border: 1.5px solid rgba(34, 27, 18, 0.18); border-radius: 10px; padding: 0 14px; font-size: 16px; color: #221b12; outline: none; transition: border-color .15s, box-shadow .15s;"
                        onfocus="this.style.borderColor='#f1c62e'; this.style.boxShadow='0 0 0 3px rgba(241,198,46,0.22)'" onblur="this.style.borderColor='rgba(34, 27, 18, 0.18)'; this.style.boxShadow='none'"
                        placeholder="xxxx-xxxx-xxxx-xxxx">
                </div>

                <button type="submit"
                    style="width: 100%; height: 46px; background: #f1c62e; color: #221b12; font-weight: 700; font-size: 15px; border: none; border-radius: 10px; cursor: pointer; box-shadow: 0 4px 12px rgba(241,198,46,.35);"
                    onmouseover="this.style.background='#f5d364'" onmouseout="this.style.background='#f1c62e'">
                    {{ __('Log in') }}
                </button>
            </form>

            <div style="margin-top: 20px; text-align: center;">
                <button type="button" style="font-size: 13px; color: #d2232a; font-weight: 600; text-decoration: none; background: none; border: none; cursor: pointer; display: block; width: 100%;" x-show="! recovery" x-on:click="recovery = true; $nextTick(() => { $refs.recovery_code.focus() })">
                    {{ __('Use a recovery code instead') }}
                </button>

                <button type="button" style="font-size: 13px; color: #d2232a; font-weight: 600; text-decoration: none; background: none; border: none; cursor: pointer; display: block; width: 100%;" x-cloak x-show="recovery" x-on:click="recovery = false; $nextTick(() => { $refs.code.focus() })">
                    {{ __('Use an authentication code') }}
                </button>
            </div>
        </div>
    </x-authentication-card>
</x-guest-layout>
