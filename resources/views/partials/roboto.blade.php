{{-- R-52: Roboto, self-hosted from this origin (dot-ui 0.6.0). No Google Fonts, no CDN. Load before the app's own CSS. --}}
<link rel="preload" href="{{ asset('dot-ui/fonts/roboto/roboto-latin-wght-normal.woff2') }}" as="font" type="font/woff2" crossorigin>
<link rel="stylesheet" href="{{ asset('dot-ui/css/dot-roboto.css') }}">
<link rel="stylesheet" href="{{ asset('dot-ui/css/dot-type.css') }}">
