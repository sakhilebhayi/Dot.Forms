{{-- R-54: every sign-in failure ends here, in words, with one next step and no automatic redirect. --}}
<x-sso.sheet title="{{ $headline }}">
    <p class="cb-gate__body" role="alert">{{ $body }}</p>
    <div class="cb-gate__foot">
        @if ($retry)<a class="cb-btn cb-btn--primary cb-claim__go" href="{{ $retryUrl }}">Start again</a>@endif
        <a class="cb-btn cb-claim__go" href="{{ $homeUrl }}">Back to Dot.Forms</a>
    </div>
    <p class="cb-claim__help cb-claim__ref">Reference: {{ $code }}</p>
</x-sso.sheet>
