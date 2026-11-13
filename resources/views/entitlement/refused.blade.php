{{-- R-50: the company does not hold Dot.Forms. A sheet from The Carbon Book with the verdict stamped on it and ONE
     action (data-primary-action). Rendered by App\Support\EntitlementRefusal with HTTP 403. It shows nothing from
     the book: no forms, no copies, no counts. --}}
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <meta name="robots" content="noindex">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ $title }} · Dot.Forms</title>
    <link rel="icon" type="image/png" sizes="32x32" href="{{ asset('favicon-32x32.png') }}">
    <link rel="icon" type="image/png" sizes="16x16" href="{{ asset('favicon-16x16.png') }}">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Archivo:wght@400;500;600;700&family=IBM+Plex+Mono:wght@400;500;600&display=swap" rel="stylesheet">
    @vite(['resources/css/carbon.css'])
</head>
<body class="cb">
    <main class="cb-page cb-gate">
        <div class="cb-land__top">
            <picture>
                <source srcset="{{ asset('images/dot_forms-light.png') }}" media="(prefers-color-scheme: dark)">
                <img class="cb-land__logo" src="{{ asset('images/dot_forms.png') }}" alt="Dot.Forms" width="96" height="96">
            </picture>
            @auth
                <form method="POST" action="{{ route('logout') }}" class="cb-gate__out">
                    @csrf
                    <button type="submit">Sign out</button>
                </form>
            @endauth
        </div>

        <section class="cb-master cb-gate__sheet" aria-labelledby="gate-title">
            <h1 class="cb-gate__h1" id="gate-title">{{ $title }}</h1>
            <p class="cb-gate__body">{{ $body }}</p>
            <div class="cb-gate__foot">
                <a class="cb-btn cb-btn--primary cb-gate__go" href="{{ $platformsUrl }}" data-primary-action>Open Platforms in InfoDot</a>
                <span class="cb-stamp cb-gate__stamp">{{ $stamp }}</span>
            </div>
        </section>

        @auth
            @if ($otherCompanies->isNotEmpty())
                <section class="cb-gate__others" aria-labelledby="gate-others">
                    <h2 class="cb-gate__h2" id="gate-others">Your other {{ $otherCompanies->count() === 1 ? 'company' : 'companies' }} here</h2>
                    <ul class="cb-register cb-gate__list">
                        @foreach ($otherCompanies as $other)
                            <li>
                                <b>{{ $other->name }}</b>
                                <form method="POST" action="{{ route('current-team.update') }}">
                                    @csrf
                                    @method('PUT')
                                    <input type="hidden" name="team_id" value="{{ $other->id }}">
                                    <button type="submit" class="cb-btn cb-btn--quiet" aria-label="Switch to {{ $other->name }}">Switch</button>
                                </form>
                            </li>
                        @endforeach
                    </ul>
                </section>
            @endif
        @endauth
    </main>
</body>
</html>
