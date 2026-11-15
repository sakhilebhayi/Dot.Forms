<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="dot-roboto">
<head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ trim($__env->yieldContent('title')) !== '' ? trim($__env->yieldContent('title')).' · ' : '' }}Dot.Forms</title>
    <link rel="icon" type="image/png" sizes="32x32" href="{{ asset('favicon-32x32.png') }}">
    <link rel="icon" type="image/png" sizes="16x16" href="{{ asset('favicon-16x16.png') }}">
    <link rel="apple-touch-icon" href="{{ asset('apple-touch-icon.png') }}">
    @include('partials.roboto')
    {{-- R-52: the shared Dot UI header system (dot-ui 0.6.0), self-hosted; the host mapping is in carbon.css --}}
    <link rel="stylesheet" href="{{ asset('dot-ui/css/dot-tokens.css') }}">
    <link rel="stylesheet" href="{{ asset('dot-ui/css/dot-search.css') }}">
    <link rel="stylesheet" href="{{ asset('dot-ui/css/dot-switcher.css') }}">
    <link rel="stylesheet" href="{{ asset('dot-ui/css/dot-header.css') }}">
    <script src="{{ asset('dot-ui/js/dot-search.js') }}" defer></script>
    <script src="{{ asset('dot-ui/js/dot-search-results.js') }}" defer></script>
    <script src="{{ asset('dot-ui/js/dot-switcher.js') }}" defer></script>
    <script src="{{ asset('dot-ui/js/dot-nav.js') }}" defer></script>
    @vite(['resources/css/signed-in.css', 'resources/css/carbon.css', 'resources/js/app.js'])
    @livewireStyles
</head>
{{-- The Carbon Book (R-30, DESIGN.md): the header on top, the page below. --}}
<body class="cb">
    @php
        $user = Auth::user();
        $team = $user?->currentTeam;
        $nav = [
            ['label' => 'The desk', 'href' => route('dashboard'), 'current' => request()->routeIs('dashboard')],
        ];
        if ($team) {
            $nav[] = ['label' => 'The book', 'href' => route('teams.forms', $team), 'current' => request()->routeIs('teams.forms') || request()->is('teams/*/forms/*')];
            $nav[] = ['label' => 'Tallies', 'href' => route('dashboard.analytics'), 'current' => request()->routeIs('dashboard.analytics')];
            if ($user->can('canCreateForm', $team)) {
                $nav[] = ['label' => 'Set a form', 'items' => [
                    ['label' => 'Master templates', 'href' => route('teams.forms.templates', $team)],
                    ['label' => 'Set with AI', 'href' => route('teams.forms.ai-builder', $team)],
                ]];
            }
        }
        $account = $user ? [
            'name' => $user->name,
            'email' => $user->email,
            'team' => $team?->name,
            'links' => array_values(array_filter([
                ['label' => 'Account', 'href' => route('profile.show'), 'current' => request()->routeIs('profile.show')],
                $team ? ['label' => 'Company settings', 'href' => route('teams.show', $team)] : null,
            ])),
            'signOut' => ['action' => route('logout')],
        ] : null;
    @endphp

    <x-dot.header brand-href="{{ route('dashboard') }}" brand-label="Dot.Forms desk" :nav="$nav" :account="$account"
        :guest="$user ? null : ['login' => ['label' => 'Log in', 'href' => route('login')]]"
        :context="$team?->name" skip="#main">
        <x-slot:brand><span class="dh-brand__name">Dot<span>.</span>Forms</span></x-slot:brand>
        <x-slot:search>
            @livewire('forms.header-search')
        </x-slot:search>
        <x-slot:tools>
            @auth
                <x-dot.switcher platform="forms" :company="$team && ! $team->personal_team ? $team->infodot_team_uuid : null" :company-name="$team?->name" logos="/dot-ui/img/platforms" />
            @endauth
        </x-slot:tools>
    </x-dot.header>

    <main id="main" class="cb-page">{{ $slot }}</main>

    @stack('modals')
    @livewireScripts
</body>
</html>
