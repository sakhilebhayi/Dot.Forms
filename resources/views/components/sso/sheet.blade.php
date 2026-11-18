{{-- R-54: the shell of the sign-in claim and error pages, in The Carbon Book: the logo, then one sheet (cb-master) with the
     double-rule head, boxed input, ink primary action. The same markup family as the refusal sheet (entitlement/refused). --}}
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="dot-roboto">
<head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <meta name="robots" content="noindex">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ $title }} · Dot.Forms</title>
    <link rel="icon" type="image/png" sizes="32x32" href="{{ asset('favicon-32x32.png') }}">
    <link rel="icon" type="image/png" sizes="16x16" href="{{ asset('favicon-16x16.png') }}">
    @include('partials.roboto')
    @vite(['resources/css/carbon.css'])
</head>
<body class="cb">
    <main class="cb-page cb-gate cb-claim">
        <div class="cb-land__top">
            <picture>
                <source srcset="{{ asset('images/dot_forms-light.png') }}" media="(prefers-color-scheme: dark)">
                <img class="cb-land__logo" src="{{ asset('images/dot_forms.png') }}" alt="Dot.Forms" width="96" height="96">
            </picture>
        </div>

        <section class="cb-master cb-gate__sheet" aria-labelledby="claim-title">
            <h1 class="cb-gate__h1" id="claim-title">{{ $title }}</h1>
            {{ $slot }}
        </section>
    </main>
</body>
</html>
