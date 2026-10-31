<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ trim($__env->yieldContent('title')) !== '' ? trim($__env->yieldContent('title')).' · ' : '' }}Dot.Forms</title>
    <link rel="icon" type="image/png" sizes="32x32" href="{{ asset('favicon-32x32.png') }}">
    <link rel="icon" type="image/png" sizes="16x16" href="{{ asset('favicon-16x16.png') }}">
    <link rel="apple-touch-icon" href="{{ asset('apple-touch-icon.png') }}">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Archivo:wght@400;500;600;700&family=IBM+Plex+Mono:wght@400;500;600&display=swap" rel="stylesheet">
    @vite(['resources/css/signed-in.css', 'resources/css/carbon.css', 'resources/js/app.js'])
    @livewireStyles
    <style>[x-cloak]{display:none!important}</style>
</head>
{{-- The Carbon Book (R-30, DESIGN.md): the spine on top, the page below. --}}
<body class="cb">
    @php $team = Auth::user()?->currentTeam; @endphp

    <header class="cb-spine">
        <div class="cb-spine__inner">
            <a class="cb-brand" href="{{ route('dashboard') }}">Dot<span>.</span>Forms</a>
            <nav aria-label="Sections">
                <a href="{{ route('dashboard') }}" @class(['cb-on' => request()->routeIs('dashboard')])>The desk</a>
                @if ($team)
                    <a href="{{ route('teams.forms', $team) }}" @class(['cb-on' => request()->routeIs('teams.forms') || request()->is('teams/*/forms/*')])>The book</a>
                    <a href="{{ route('dashboard.analytics') }}" @class(['cb-on' => request()->routeIs('dashboard.analytics')])>Tallies</a>
                @endif
                <a href="{{ route('profile.show') }}" @class(['cb-on' => request()->routeIs('profile.show')])>Account</a>
            </nav>
            @auth
                <div class="cb-spine__co">
                    <span>{{ mb_strtoupper($team->name ?? Auth::user()->name) }}</span>
                    <form method="POST" action="{{ route('logout') }}">
                        @csrf
                        <button type="submit">Sign out</button>
                    </form>
                </div>
            @endauth
        </div>
    </header>

    <main class="cb-page">{{ $slot }}</main>

    @stack('modals')
    @livewireScripts
</body>
</html>
