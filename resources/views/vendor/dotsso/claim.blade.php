{{-- R-54: the claim page, in The Carbon Book. Shown when an account here already uses the email address of the InfoDot
     sign-in. Nothing on the account is replaced; the code proves the address. Variables come from the shared package. --}}
<x-sso.sheet title="Confirm it is you">
    <p class="cb-gate__body">An account here already uses <strong class="cb-claim__email">{{ $maskedEmail }}</strong>. We sent a {{ $codeLength }}-digit code to that address. Enter it to connect the two. Your forms, copies and companies stay as they are.</p>

    @if (session('dotsso_status'))
        <p class="cb-note" role="status">{{ session('dotsso_status') }}</p>
    @endif

    <form method="POST" action="{{ $verifyUrl }}" novalidate>
        @csrf
        <div class="cb-field">
            <label class="cb-label" for="code">Code from the email</label>
            <input id="code" name="code" type="text" inputmode="numeric" pattern="[0-9]*" autocomplete="one-time-code"
                   maxlength="{{ $codeLength }}" required autofocus class="cb-input cb-claim__code"
                   aria-describedby="code-help code-error" aria-invalid="{{ $errors->has('code') ? 'true' : 'false' }}">
            <p id="code-help" class="cb-claim__help">It works for {{ $minutes }} minutes. You have {{ $maxAttempts }} tries.</p>
            <p id="code-error" class="cb-error" role="alert">{{ $errors->first('code') }}</p>
        </div>
        <button type="submit" class="cb-btn cb-btn--primary cb-claim__go">Confirm and continue</button>
    </form>

    <div class="cb-claim__aside">
        <form method="POST" action="{{ $resendUrl }}">@csrf<button type="submit" class="cb-btn cb-btn--quiet">Send a new code</button></form>
        <form method="POST" action="{{ $cancelUrl }}">@csrf<button type="submit" class="cb-btn cb-btn--quiet">Cancel</button></form>
    </div>
</x-sso.sheet>
