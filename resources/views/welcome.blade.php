<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="scroll-smooth">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <title>Dot.Forms — Form builder for teams</title>
        <meta name="description" content="A team-scoped form builder: drag-style field editor, one public link per form, and a submissions table you can search, export, and route to Slack or your CRM the moment a response comes in.">

        <!-- Favicon -->
        <link rel="icon" type="image/png" sizes="32x32" href="{{ asset('favicon-32x32.png') }}">
        <link rel="icon" type="image/png" sizes="16x16" href="{{ asset('favicon-16x16.png') }}">
        <link rel="apple-touch-icon" href="{{ asset('apple-touch-icon.png') }}">

        <link rel="preconnect" href="https://fonts.googleapis.com">
        <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
        <link href="https://fonts.googleapis.com/css2?family=Fraunces:opsz,wght@9..144,500;9..144,600;9..144,700&family=IBM+Plex+Sans:wght@400;500;600;700&family=IBM+Plex+Mono:wght@400;500;600&display=swap" rel="stylesheet">

        @vite(['resources/css/app.css', 'resources/js/app.js'])

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
            body { font-family: var(--font-body); background: var(--paper); color: var(--ink); }
            .font-display { font-family: var(--font-display); font-optical-sizing: auto; }
            .font-mono { font-family: var(--font-mono); }

            .press { transition: transform 160ms var(--ease-out); }
            .press:active { transform: scale(0.97); }

            @media (prefers-reduced-motion: no-preference) {
                .reveal {
                    opacity: 0;
                    transform: translateY(14px);
                    transition: opacity 600ms var(--ease-out), transform 600ms var(--ease-out);
                }
                .reveal.is-visible { opacity: 1; transform: translateY(0); }
            }
            @media (prefers-reduced-motion: reduce) {
                .reveal { opacity: 1; transform: none; }
            }

            @media (hover: hover) and (pointer: fine) {
                .row-hover:hover { background: rgba(34, 27, 18, 0.025); }
                .link-underline { background-size: 0% 1px; }
                .link-underline:hover { background-size: 100% 1px; }
            }
            .link-underline {
                background-image: linear-gradient(currentColor, currentColor);
                background-position: 0 100%;
                background-repeat: no-repeat;
                transition: background-size 220ms var(--ease-out);
            }

            #mobile-menu[data-open="false"] { display: none; }
        </style>
    </head>
    <body class="antialiased">

        <!-- Nav -->
        <header id="site-header" class="fixed top-0 left-0 right-0 z-50 border-b border-transparent transition-colors duration-300">
            <nav class="max-w-[1400px] mx-auto px-5 sm:px-8 py-2.5 flex items-center justify-between">
                <a href="/" class="flex items-center press">
                    <img src="{{ asset('images/dot_forms.png') }}" alt="Dot.Forms" class="h-16 sm:h-20 w-auto">
                </a>

                <div class="hidden md:flex items-center gap-8 font-mono text-[13px] tracking-wide uppercase text-[var(--ink-soft)]">
                    <a href="#features" class="link-underline hover:text-[var(--ink)] pb-0.5">What it does</a>
                    <a href="#capabilities" class="link-underline hover:text-[var(--ink)] pb-0.5">For teams</a>
                </div>

                @if (Route::has('login'))
                    <div class="flex items-center gap-3">
                        @auth
                            <a href="{{ url('/dashboard') }}" class="press px-5 py-2.5 bg-[var(--ink)] hover:bg-[#332818] text-[var(--paper)] text-sm font-display font-semibold rounded-lg transition-colors">
                                Dashboard
                            </a>
                        @else
                            <a href="{{ route('login') }}" class="hidden sm:block text-sm font-medium text-[var(--ink-soft)] hover:text-[var(--ink)] transition-colors">
                                Sign in
                            </a>
                            @if (Route::has('register'))
                                <a href="{{ route('register') }}" class="press px-5 py-2.5 bg-[var(--gold)] hover:bg-[#f5d364] text-[var(--ink)] text-sm font-display font-semibold rounded-lg transition-colors">
                                    Create account
                                </a>
                            @endif
                        @endauth

                        <button id="mobile-menu-toggle" class="md:hidden press p-2 -mr-2 text-[var(--ink)]" aria-label="Toggle menu" aria-expanded="false" aria-controls="mobile-menu">
                            <svg id="icon-open" class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.75" d="M4 7h16M4 12h16M4 17h16"></path>
                            </svg>
                            <svg id="icon-close" class="w-5 h-5 hidden" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.75" d="M6 18L18 6M6 6l12 12"></path>
                            </svg>
                        </button>
                    </div>
                @endif
            </nav>

            <div id="mobile-menu" data-open="false" class="md:hidden border-t border-[var(--line)] bg-[var(--paper)]">
                <div class="flex flex-col px-5 py-4 gap-1 font-mono text-sm uppercase tracking-wide">
                    <a href="#features" class="px-3 py-2.5 text-[var(--ink-soft)] hover:text-[var(--ink)]">What it does</a>
                    <a href="#capabilities" class="px-3 py-2.5 text-[var(--ink-soft)] hover:text-[var(--ink)]">For teams</a>
                    @guest
                        <a href="{{ route('login') }}" class="px-3 py-2.5 text-[var(--ink-soft)] hover:text-[var(--ink)]">Sign in</a>
                    @endguest
                </div>
            </div>
        </header>

        <!-- Hero -->
        <section class="relative overflow-hidden pt-32 pb-20 sm:pt-40 sm:pb-24 px-5 sm:px-8">
            <!-- Photo: person writing on a white form paper, by Firmbee.com,
            unsplash.com/photos/person-writing-on-white-form-paper-9i5eqBarv-k -->
            <div class="absolute inset-0 bg-cover bg-center" style="background-image: url('https://images.unsplash.com/photo-1564846824194-346b7871b855?q=80&w=2400&auto=format&fit=crop');"></div>
            <div class="absolute inset-0" style="background: linear-gradient(100deg, var(--paper) 0%, rgba(251,246,234,0.94) 40%, rgba(251,246,234,0.75) 65%, rgba(251,246,234,0.55) 100%);"></div>
            <div class="max-w-[1400px] mx-auto relative">
                <div class="grid lg:grid-cols-[1.15fr_0.85fr] gap-14 lg:gap-16 items-center">
                    <div class="reveal" data-reveal>
                        <p class="font-mono text-xs tracking-[0.18em] uppercase text-[var(--red)] mb-6">
                            Form builder for teams
                        </p>

                        <h1 class="font-display font-semibold text-4xl sm:text-5xl lg:text-6xl leading-[1.08] sm:leading-none tracking-tight text-[var(--ink)] mb-6">
                            Build the form.<br>Skip the spreadsheet.
                        </h1>

                        <p class="text-lg text-[var(--ink-soft)] leading-relaxed max-w-xl mb-10">
                            Dot.Forms is a team-scoped form builder: a drag-style field editor, one public link per form, and a submissions table you can search, export, and route to Slack or your CRM the moment a response comes in.
                        </p>

                        @guest
                            <div class="flex flex-wrap items-center gap-4">
                                <a href="{{ route('register') }}" class="press px-7 py-3.5 bg-[var(--gold)] hover:bg-[#f5d364] text-[var(--ink)] font-display font-semibold rounded-lg transition-colors">
                                    Create account
                                </a>
                                <a href="#features" class="press flex items-center gap-2 px-7 py-3.5 text-[var(--ink)] font-medium rounded-lg border border-[var(--line)] hover:border-[var(--ink-soft)] transition-colors">
                                    See what it does
                                </a>
                            </div>
                        @endguest
                    </div>

                    <div class="hidden lg:block reveal" data-reveal>
                        <div class="relative rounded-[28px] border border-[var(--line)] bg-[var(--paper-deep)] p-10 flex items-center justify-center">
                            <!-- Signature element: line-art form/checklist, echoing the paper-and-checklist icon in the real Dot.Forms mark -->
                            <svg class="w-full max-w-[220px] h-auto" viewBox="0 0 220 280" fill="none" xmlns="http://www.w3.org/2000/svg" aria-hidden="true">
                                <rect x="46" y="34" width="120" height="172" rx="6" stroke="var(--ink)" stroke-opacity="0.14" stroke-width="3"/>
                                <path d="M24 12H130L158 40V226H24V12Z" stroke="var(--ink)" stroke-width="3"/>
                                <path d="M130 12V40H158" stroke="var(--ink)" stroke-width="3"/>
                                <line x1="44" y1="76" x2="138" y2="76" stroke="var(--ink)" stroke-width="2.5"/>
                                <line x1="44" y1="96" x2="138" y2="96" stroke="var(--ink)" stroke-width="2.5"/>
                                <line x1="44" y1="116" x2="112" y2="116" stroke="var(--ink)" stroke-width="2.5"/>
                                <rect x="44" y="148" width="16" height="16" rx="3" stroke="var(--red)" stroke-width="3"/>
                                <path d="M48 156L54 162L66 148" stroke="var(--red)" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"/>
                                <line x1="70" y1="156" x2="138" y2="156" stroke="var(--ink)" stroke-width="2.5"/>
                                <rect x="44" y="180" width="16" height="16" rx="3" stroke="var(--ink)" stroke-opacity="0.35" stroke-width="3"/>
                                <line x1="70" y1="188" x2="126" y2="188" stroke="var(--ink)" stroke-width="2.5"/>
                            </svg>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Capability strip — real functionality, not a fabricated metric -->
            <div class="mt-16 sm:mt-20 border-t border-[var(--line)] pt-6">
                <div class="max-w-[1400px] mx-auto flex flex-wrap gap-x-8 gap-y-2 font-mono text-[11px] tracking-[0.14em] uppercase text-[var(--ink-soft)]">
                    <span>Field-by-field builder</span>
                    <span class="text-[var(--red)]">·</span>
                    <span>Version history &amp; revert</span>
                    <span class="text-[var(--red)]">·</span>
                    <span>Spam &amp; rate-limit protection</span>
                    <span class="text-[var(--red)]">·</span>
                    <span>CSV / XLSX export</span>
                </div>
            </div>
        </section>

        <!-- Features -->
        <section id="features" class="py-24 sm:py-28 px-5 sm:px-8">
            <div class="max-w-[1400px] mx-auto">
                <div class="max-w-xl mb-16 reveal" data-reveal>
                    <p class="font-mono text-xs tracking-[0.18em] uppercase text-[var(--red)] mb-4">What it does</p>
                    <h2 class="font-display font-semibold text-3xl sm:text-4xl text-[var(--ink)] leading-tight sm:leading-10">
                        Everything a form needs to go from draft to data
                    </h2>
                </div>

                <div class="grid md:grid-cols-2 border-t border-[var(--line)]">
                    @php
                        $features = [
                            ['tag' => 'Builder', 'title' => 'Field-by-field form builder', 'body' => 'Draft, publish, archive, or duplicate a form; reorder fields with a drag-style editor; and revert to any earlier version with full history.'],
                            ['tag' => 'Publishing', 'title' => 'One link, honestly protected', 'body' => "Each form renders at its own slug with honeypot and timing-based spam checks, rate limiting, and an optional consent checkbox or quiz score."],
                            ['tag' => 'Submissions', 'title' => 'A submissions table you can search', 'body' => 'Search responses, export to CSV or XLSX, open any submission\'s full detail, bulk-delete, or pull a GDPR-style export for a single respondent.'],
                            ['tag' => 'Routing', 'title' => 'Webhooks that actually fire', 'body' => 'Send new submissions to Slack, Zapier, Make, or a CRM over real HTTP calls the moment someone submits — guarded against SSRF on team-supplied URLs.'],
                            ['tag' => 'AI assist', 'title' => 'A drafting assist, not a black box', 'body' => "Describe a form in plain language and get a starting structure. Calls OpenAI when a key is configured, and falls back to a real keyword-based builder — not a fake \"thinking\" animation — when it isn't."],
                            ['tag' => 'Roles', 'title' => 'Collaborators, not just teammates', 'body' => "Give a form its own viewer, editor, or owner, layered on top of whoever's on the team — access follows the form, not just the workspace."],
                        ];
                    @endphp
                    @foreach ($features as $i => $f)
                        <div class="row-hover border-b border-[var(--line)] {{ $i % 2 === 0 ? 'md:border-r' : '' }} px-1 py-8 sm:py-10 transition-colors reveal" data-reveal>
                            <p class="font-mono text-[11px] tracking-[0.14em] uppercase text-[var(--red)] mb-3">{{ $f['tag'] }}</p>
                            <h3 class="font-display font-semibold text-xl text-[var(--ink)] mb-2.5">{{ $f['title'] }}</h3>
                            <p class="text-[var(--ink-soft)] leading-relaxed max-w-md">{{ $f['body'] }}</p>
                        </div>
                    @endforeach
                </div>
            </div>
        </section>

        <!-- Capabilities -->
        <section id="capabilities" class="py-24 sm:py-28 px-5 sm:px-8 bg-[var(--paper-deep)] border-y border-[var(--line)]">
            <div class="max-w-[1400px] mx-auto">
                <div class="grid lg:grid-cols-[minmax(0,1fr)_minmax(0,1.4fr)] gap-12 lg:gap-20">
                    <div class="reveal" data-reveal>
                        <p class="font-mono text-xs tracking-[0.18em] uppercase text-[var(--red)] mb-4">Built for the team</p>
                        <h2 class="font-display font-semibold text-3xl sm:text-4xl text-[var(--ink)] leading-tight sm:leading-10 mb-5">
                            Runs one workspace or a dozen forms, from a browser
                        </h2>
                        <p class="text-[var(--ink-soft)] leading-relaxed max-w-sm">
                            No plugin to install and no separate reporting tool. Sign in, build a form, and everything else — submissions, exports, integrations — is already there.
                        </p>
                    </div>

                    <div class="grid sm:grid-cols-2 gap-x-10">
                        @php
                            $capabilities = [
                                ['title' => 'Team-scoped by default', 'body' => 'Every form belongs to a team; every builder, viewer, and export action is checked against that team before anything loads.'],
                                ['title' => 'Submission analysis, plainly stated', 'body' => "Completion-rate drop-off by field and a real keyword-based sentiment pass — not an LLM guessing, a statistical read of your own data."],
                                ['title' => 'Scheduled auto-close', 'body' => 'Forms with a configured close date stop taking new responses on schedule, no manual step required.'],
                                ['title' => 'GDPR-style export', 'body' => "Pull every submission tied to one respondent's data on request, per form."],
                                ['title' => 'Team-supplied form theming', 'body' => "Forms can carry their own custom CSS, sanitized before it renders on the public page."],
                                ['title' => 'Every AI action logged', 'body' => 'Field suggestions, label rewrites, analysis runs, and generated blueprints are all recorded against the form they touched.'],
                            ];
                        @endphp
                        @foreach ($capabilities as $c)
                            <div class="py-6 border-t border-[var(--line)] reveal" data-reveal>
                                <h3 class="font-display font-medium text-base text-[var(--ink)] mb-1.5">{{ $c['title'] }}</h3>
                                <p class="text-sm text-[var(--ink-soft)] leading-relaxed">{{ $c['body'] }}</p>
                            </div>
                        @endforeach
                    </div>
                </div>
            </div>
        </section>

        <!-- CTA -->
        <section class="relative py-28 sm:py-36 px-5 sm:px-8 overflow-hidden">
            <!-- Photo: clipboard checklist next to a cup of coffee, by Testeur de CBD (@testeurdecbd), unsplash.com/photos/UFb4LPahwHQ -->
            <div class="absolute inset-0 bg-cover bg-center" style="background-image: url('https://images.unsplash.com/photo-1642188537432-41c8a331ebdb?q=80&w=2400&auto=format&fit=crop');"></div>
            <div class="absolute inset-0" style="background: linear-gradient(180deg, var(--paper) 0%, rgba(251,246,234,0.86) 45%, var(--paper) 100%);"></div>

            <div class="relative z-10 max-w-2xl mx-auto text-center reveal" data-reveal>
                <h2 class="font-display font-semibold text-3xl sm:text-4xl text-[var(--ink)] leading-tight sm:leading-10 mb-5">
                    Ready to publish your first form?
                </h2>
                <p class="text-[var(--ink-soft)] leading-relaxed mb-10 max-w-lg mx-auto">
                    Build it, share the link, and watch responses land in a table you can search and export.
                </p>

                @guest
                    <div class="flex flex-wrap justify-center gap-4">
                        <a href="{{ route('register') }}" class="press px-8 py-3.5 bg-[var(--gold)] hover:bg-[#f5d364] text-[var(--ink)] font-display font-semibold rounded-lg transition-colors">
                            Create account
                        </a>
                        <a href="{{ route('login') }}" class="press px-8 py-3.5 text-[var(--ink)] font-medium rounded-lg border border-[var(--line)] hover:border-[var(--ink-soft)] transition-colors">
                            Sign in
                        </a>
                    </div>
                @endguest
            </div>
        </section>

        <!-- Footer -->
        <footer class="py-14 px-5 sm:px-8 border-t border-[var(--line)] bg-[var(--paper-deep)]">
            <div class="max-w-[1400px] mx-auto flex flex-col sm:flex-row items-center justify-between gap-6">
                <a href="/" class="flex items-center">
                    <img src="{{ asset('images/dot_forms.png') }}" alt="Dot.Forms" class="h-11 w-auto opacity-90">
                </a>
                <div class="flex items-center gap-6 font-mono text-xs tracking-wide uppercase text-[var(--ink-soft)]">
                    <a href="{{ route('policy.show') }}" class="hover:text-[var(--ink)] transition-colors">Privacy</a>
                    <a href="{{ route('cookies') }}" class="hover:text-[var(--ink)] transition-colors">Cookies</a>
                    <a href="{{ route('terms.show') }}" class="hover:text-[var(--ink)] transition-colors">Terms</a>
                </div>
                <p class="font-mono text-xs tracking-wide text-[var(--ink-soft)]">
                    &copy; {{ date('Y') }} Dot.Forms. Form builder for teams.
                </p>
            </div>
        </footer>

        <script>
            // Scroll-reveal, respects prefers-reduced-motion
            if (window.matchMedia('(prefers-reduced-motion: no-preference)').matches && 'IntersectionObserver' in window) {
                const io = new IntersectionObserver((entries) => {
                    entries.forEach((entry) => {
                        if (entry.isIntersecting) {
                            entry.target.classList.add('is-visible');
                            io.unobserve(entry.target);
                        }
                    });
                }, { threshold: 0.15, rootMargin: '0px 0px -40px 0px' });
                document.querySelectorAll('[data-reveal]').forEach((el) => io.observe(el));
            } else {
                document.querySelectorAll('[data-reveal]').forEach((el) => el.classList.add('is-visible'));
            }

            // Sticky header background on scroll (inline styles — avoids relying on a
            // JIT-generated alpha-channel utility class for a CSS custom property)
            const header = document.getElementById('site-header');
            const onScroll = () => {
                if (window.pageYOffset > 24) {
                    header.style.backgroundColor = 'rgba(251, 246, 234, 0.95)';
                    header.style.backdropFilter = 'blur(12px)';
                    header.style.borderBottomColor = 'var(--line)';
                } else {
                    header.style.backgroundColor = 'transparent';
                    header.style.backdropFilter = 'none';
                    header.style.borderBottomColor = 'transparent';
                }
            };
            window.addEventListener('scroll', onScroll, { passive: true });
            onScroll();

            // Mobile menu toggle (plain JS — no framework dependency on this guest page)
            const menuToggle = document.getElementById('mobile-menu-toggle');
            const menu = document.getElementById('mobile-menu');
            const iconOpen = document.getElementById('icon-open');
            const iconClose = document.getElementById('icon-close');
            if (menuToggle && menu) {
                menuToggle.addEventListener('click', () => {
                    const isOpen = menu.getAttribute('data-open') === 'true';
                    menu.setAttribute('data-open', String(!isOpen));
                    menuToggle.setAttribute('aria-expanded', String(!isOpen));
                    iconOpen.classList.toggle('hidden', !isOpen);
                    iconClose.classList.toggle('hidden', isOpen);
                });
            }
        </script>
    </body>
</html>
