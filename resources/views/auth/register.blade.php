<x-guest-layout>
    <x-authentication-card>
        <x-slot name="logo">
            <x-authentication-card-logo />
        </x-slot>

        <h1 style="font-family: var(--dot-font-sans); font-size: 1.6rem; font-weight: 600; color: #221b12; margin: 0 0 6px;">Create your account</h1>
        <p style="font-size: 14px; color: #5b5240; margin: 0 0 28px;">Free forever on the starter plan. No card needed.</p>

        <x-validation-errors class="mb-4" />

        <form method="POST" action="{{ route('register') }}" style="display: flex; flex-direction: column; gap: 18px;">
            @csrf

            <div>
                <label for="name" style="display: block; font-size: 13px; font-weight: 600; color: #5b5240; margin-bottom: 6px;">Full name</label>
                <input id="name" type="text" name="name" value="{{ old('name') }}" required autofocus autocomplete="name"
                    style="width: 100%; height: 44px; background: white; border: 1.5px solid rgba(34, 27, 18, 0.18); border-radius: 10px; padding: 0 14px; font-size: 16px; color: #221b12; outline: none; transition: border-color .15s, box-shadow .15s;"
                    onfocus="this.style.borderColor='#f1c62e'; this.style.boxShadow='0 0 0 3px rgba(241,198,46,0.22)'" onblur="this.style.borderColor='rgba(34, 27, 18, 0.18)'; this.style.boxShadow='none'"
                    placeholder="Jane Smith">
            </div>

            <div>
                <label for="email" style="display: block; font-size: 13px; font-weight: 600; color: #5b5240; margin-bottom: 6px;">Email address</label>
                <input id="email" type="email" name="email" value="{{ old('email') }}" required autocomplete="username"
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

            @if (Laravel\Jetstream\Jetstream::hasTermsAndPrivacyPolicyFeature())
                <label style="display: flex; align-items: flex-start; gap: 10px; cursor: pointer;">
                    <input type="checkbox" name="terms" id="terms" required
                        style="width: 16px; height: 16px; margin-top: 2px; accent-color: #f1c62e; cursor: pointer; flex-shrink: 0;">
                    <span style="font-size: 13px; color: #5b5240; line-height: 1.5;">
                        {!! __('I agree to the :terms_of_service and :privacy_policy', [
                            'terms_of_service' => '<a target="_blank" href="'.route('terms.show').'" style="color: #a97b0f; font-weight: 500; text-decoration: none;">'.__('Terms of Service').'</a>',
                            'privacy_policy' => '<a target="_blank" href="'.route('policy.show').'" style="color: #a97b0f; font-weight: 500; text-decoration: none;">'.__('Privacy Policy').'</a>',
                        ]) !!}
                    </span>
                </label>
            @endif

            <button type="submit"
                style="width: 100%; height: 46px; background: #f1c62e; color: #221b12; font-weight: 700; font-size: 15px; border: none; border-radius: 10px; cursor: pointer; box-shadow: 0 4px 12px rgba(241,198,46,.35);"
                onmouseover="this.style.background='#f5d364'" onmouseout="this.style.background='#f1c62e'">
                Create account
            </button>
        </form>

        <p style="margin-top: 24px; text-align: center; font-size: 13px; color: #5b5240;">
            Already have an account?
            <a href="{{ route('login') }}" style="color: #d2232a; font-weight: 600; text-decoration: none;">Sign in</a>
        </p>
    </x-authentication-card>
</x-guest-layout>
