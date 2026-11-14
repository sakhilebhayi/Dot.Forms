<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">

        <title>{{ config('app.name', 'Dot.Forms') }}</title>

        <!-- Favicon -->
        <link rel="icon" type="image/png" sizes="32x32" href="{{ asset('favicon-32x32.png') }}">
        <link rel="icon" type="image/png" sizes="16x16" href="{{ asset('favicon-16x16.png') }}">
        <link rel="apple-touch-icon" href="{{ asset('apple-touch-icon.png') }}">

        <link rel="preconnect" href="https://fonts.googleapis.com">
        <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
        <link href="https://fonts.googleapis.com/css2?family=Fraunces:opsz,wght@9..144,500;9..144,600;9..144,700&family=IBM+Plex+Sans:wght@400;500;600;700&family=IBM+Plex+Mono:wght@400;500;600&display=swap" rel="stylesheet">

        @vite(['resources/css/app.css', 'resources/js/app.js'])
        @livewireStyles

        <style>
            :root {
                --paper: #fbf6ea;
                --paper-deep: #f1e7cd;
                --ink: #221b12;
                --ink-soft: #5b5240;
                --gold: #f1c62e;
                --gold-deep: #a97b0f;
                --red: #d2232a;
                --red-deep: #a81b21;
                --line: rgba(34, 27, 18, 0.12);
                --font-display: 'Fraunces', Georgia, serif;
                --font-body: 'IBM Plex Sans', system-ui, sans-serif;
                --font-mono: 'IBM Plex Mono', ui-monospace, monospace;
                --ease-out: cubic-bezier(0.23, 1, 0.32, 1);
            }
            html { background: var(--paper); }
            body {
                font-family: var(--font-body);
                min-height: 100vh;
                margin: 0;
                background: var(--paper);
                color: var(--ink);
            }
            .font-display { font-family: var(--font-display); font-optical-sizing: auto; }
            .font-mono { font-family: var(--font-mono); }

            .press { transition: transform 160ms var(--ease-out); }
            .press:active { transform: scale(0.97); }
        </style>
    </head>
    <body class="antialiased">
        <div class="relative min-h-screen flex flex-col items-center justify-center px-5 py-12 sm:px-8 overflow-hidden">
            {{-- Same hero photo as welcome.blade.php's CTA section (clipboard checklist next to a
            cup of coffee, by Testeur de CBD, unsplash.com/photos/UFb4LPahwHQ), with the same
            paper-toned linear-gradient scrim the CTA section itself uses. --}}
            <div class="absolute inset-0 bg-cover bg-center" style="background-image: url('https://images.unsplash.com/photo-1642188537432-41c8a331ebdb?q=80&w=2400&auto=format&fit=crop');"></div>
            <div class="absolute inset-0" style="background: linear-gradient(180deg, var(--paper) 0%, rgba(251,246,234,0.86) 45%, var(--paper) 100%);"></div>

            <div class="relative z-10 w-full flex flex-col items-center">
                {{ $slot }}
            </div>
        </div>
        @livewireScripts
    </body>
</html>
