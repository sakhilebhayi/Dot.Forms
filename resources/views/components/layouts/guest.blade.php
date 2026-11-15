<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="dot-roboto">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">

        <title>{{ config('app.name', 'Dot.Forms') }}</title>

        <!-- Favicon -->
        <link rel="icon" type="image/png" sizes="32x32" href="{{ asset('favicon-32x32.png') }}">
        <link rel="icon" type="image/png" sizes="16x16" href="{{ asset('favicon-16x16.png') }}">
        <link rel="apple-touch-icon" href="{{ asset('apple-touch-icon.png') }}">

        @include('partials.roboto')

        @vite(['resources/css/app.css', 'resources/js/app.js'])
        @livewireStyles

        <style>
            *, *::before, *::after { box-sizing: border-box; }
            :root {
                --yellow: #F5B800;
                --yellow-dark: #C9950A;
                --yellow-light: #FFF4C2;
                --red: #D32F2F;
                --red-light: #FFEBEB;
                --ink: #1A1A1A;
                --muted: #6B7280;
                --surface: #FFFFFF;
                --bg: #FAFAFA;
            }
            body {
                font-family: var(--dot-font-sans);
                min-height: 100vh;
                margin: 0;
                background: var(--bg);
                color: var(--ink);
            }
            .display { font-family: var(--dot-font-sans); }
        </style>
    </head>
    <body class="antialiased">
        {{ $slot }}
        @livewireScripts
    </body>
</html>
