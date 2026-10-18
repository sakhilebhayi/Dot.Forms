<div align="center">

<img src="public/images/dot_forms.png" alt="Dot.Forms" width="220" />

<br /><br />

**Build, publish, and analyse forms without writing code.**

<br />

![Laravel](https://img.shields.io/badge/Laravel-13-FF2D20?style=flat-square&logo=laravel&logoColor=white) ![PHP](https://img.shields.io/badge/PHP-8.4-777BB4?style=flat-square&logo=php&logoColor=white) ![Livewire](https://img.shields.io/badge/Livewire-3-FB70A9?style=flat-square) ![SQLite](https://img.shields.io/badge/SQLite-WAL-003B57?style=flat-square&logo=sqlite&logoColor=white)

<br /><br />

**Part of the [InfoDot Ecosystem](https://github.com/sakhileb/InfoDot)** &nbsp;·&nbsp; `forms.infodot.app`

</div>

---

## What is Dot.Forms?

Dot.Forms is the team-scoped form-building and submission-management platform in the InfoDot ecosystem. Teams build forms with a field builder, publish them at a public slug, collect responses, and review or export submissions. An OpenAI-backed builder can generate a starting form from a plain-language prompt (falling back to genuine keyword-based heuristics if no API key is configured — never a fake result), and a rule-based analytics pass summarizes submissions without requiring an LLM at all.

See [`wiki.md`](wiki.md) for the full, code-verified breakdown of what's built versus aspirational.

## Core Features

- Field builder — text, email, number, textarea, select, radio, checkbox, date, and file fields, with per-field settings and drag-style reordering
- AI-powered form generation from a plain-language prompt (OpenAI, with an honest non-AI fallback)
- Conditional logic notes and AI-suggested validation rules
- Form versioning with one-click revert to a previous snapshot
- Submission dashboard with search, CSV/Excel export, and a GDPR-style per-user data export
- Webhook/Slack/Zapier/Make/CRM delivery of submissions to external systems
- Per-form collaborator roles (viewer/editor/owner) layered on top of team membership
- Ecosystem SSO from the InfoDot hub (`EcosystemAuthController`, verified against the ecosystem-wide Sanctum contract)

## Domain Models

- **Form** — a team-owned form with JSON `settings` (confirmation message, schedule, webhooks, theme, consent, quiz config)
- **FormField** — individual field definition
- **FormSubmission** — captured response, keyed by field ID
- **FormUserRole** — per-form collaborator role
- **FormVersion** — a snapshot of a form for revert
- **AiSuggestion** — log of AI actions applied to a form

## Tech Stack

| Layer | Technology |
|---|---|
| Framework | Laravel 13 |
| Language | PHP 8.4 |
| Frontend | Livewire 3 · Tailwind CSS 4 via Vite (Alpine.js is the copy Livewire ships; no runtime CDN for either) |
| Database | SQLite (one file, `database/database.sqlite`, WAL mode; PostgreSQL is not used in production) |
| Auth | Laravel Sanctum 4 (InfoDot SSO) + Jetstream 5 Teams |
| AI | OpenAI Chat Completions (`OPENAI_API_KEY`), with a rule-based fallback when no key is set |
| Storage | Local/S3 via Flysystem (`config('filesystems')`) |
| Exports | Laravel Excel (`maatwebsite/excel`) for CSV/XLSX |
| Queue | Database queue driver, drained by cron (`queue:work --stop-when-empty`); the AI jobs are dispatched synchronously from Livewire today |

**Not used in production:** Laravel Reverb / any websocket server (the host has none: `BROADCAST_CONNECTION=null`, so the builder's "who else is editing" and "just saved" banners never appear; the frontend only opens a websocket if it is built with `VITE_REVERB_APP_KEY`), Laravel Scout/Meilisearch (search is plain SQL), Redis/Laravel Horizon. See `wiki.md` §4 for details.

## Tailwind CSS 4

Tailwind is v4, built by `@tailwindcss/vite` (`vite.config.js`). There is no `tailwind.config.js`, `postcss.config.js` or autoprefixer: everything that used to be config lives in `resources/css/app.css`.

- **Which pages use it.** `resources/css/app.css` is loaded by the Livewire layout (`components/layouts/app.blade.php`: builder, submissions, AI pages, public form pages), the guest layouts (login, register, legal pages), the welcome page and the error pages. The legacy `layouts/app.blade.php` (dashboard, profile, teams, forms list) loads its own entry, `resources/css/signed-in.css`: the same theme, utilities and v3 compatibility rules, but **no preflight and no plugins**, because that layout used to run the v3 Play CDN with `preflight: false` and neither the forms nor the typography plugin, and its inline styles rely on the browser defaults. Do not import `tailwindcss` or add a plugin there. Alpine comes from Livewire's bundle only; do not add a CDN copy (a second Alpine makes every `x-data` on the page fail).
- `@import 'tailwindcss' source(none)` plus four `@source` lines (the old `content` globs, nothing wider: automatic detection would scan `app/`, `docs/` and `tests/`), `@plugin` for forms and typography.
- **Every v4 default that moved a pixel is put back**, each with a comment in `app.css`: border colour (gray-200, not `currentColor`), pointer cursor on buttons, placeholder colour, `hover:` on touch screens, `ring` (3px blue-500/50), the `shadow`/`rounded`/`blur` scale renames, the transparent `outline-none`, the v3 `transition` property lists, the browser's own padding on `th`/`td`/`option`/the file button, `ltr:`/`rtl:` needing an explicit `dir`, px breakpoints, the v3 `font-sans`/`font-mono` stacks (declared `@theme inline`, because the layouts define their own `--font-mono`), the v3 palette hex values (`tailwind-v3-palette.css`; v4 moved to oklch) and the v3 colours of `prose`. The migration's rule is NO visual change: fix a difference in `app.css`, never by redesigning.
- **`@tailwindcss/forms` and the cascade.** v4 puts the plugin in `@layer base`, below every utility, so a plain `border-gray-300` now beats the plugin's `:checked` rule (checked checkboxes got a gray outline). The plugin's `:focus`, `:checked` and `:indeterminate` rules are therefore re-emitted in `@layer utilities` in `app.css`; keep them in step with the plugin.
- **`space-y-*` and `divide-y*`** put the margin or border on every child but the last (v3: every child but the first that is not `[hidden]`). The v3 rules are restored for the sizes the views use (`space-y-1/2/3/4/6`, `divide-y`, `divide-gray-100/200`); add a size to that block before using another one.
- Things a view can still trip over, not compensated: `bg-opacity-*`, `ring-opacity-*` and friends no longer exist (use `bg-black/5`); `flex-shrink-*`/`flex-grow-*` are `shrink-*`/`grow-*`; the important marker goes last (`grid!`); `scale-*`, `translate-*` and `rotate-*` set their own properties, not `transform`; a `leading-*` class now beats the line-height of a `text-*` class at any breakpoint (v3: whichever came later in the stylesheet), which is why the welcome headings say `sm:leading-none` / `sm:leading-10` explicitly.
- `@tailwindcss/typography` still pins a flagged `postcss-selector-parser`; `package.json` `overrides` pins it to ^7.1.6 so `npm audit --audit-level=moderate` is clean. Leave it until the plugin itself moves.
- Browser floor: Chrome 111, Safari 16.4, Firefox 128 (cascade layers, `@property`, `color-mix`).

## Quick Start

```bash
git clone https://github.com/sakhileb/Dot.Forms.git
cd Dot.Forms
cp .env.example .env
composer install
npm install && npm run build
php artisan key:generate
php artisan migrate
php artisan serve
```

> **Database:** the default `.env.example` uses SQLite; `php artisan migrate` creates `database/database.sqlite` if you `touch` it first. **Ecosystem SSO:** set `APP_URL` to the app's address; users authenticated through InfoDot gain access automatically via Sanctum handoff tokens.

## Ecosystem

**Dot.Forms** is one of **21 platforms** in the InfoDot ecosystem, connected by Sanctum SSO (each platform has its own database). Visit [InfoDot](https://github.com/sakhileb/InfoDot) to explore the full platform map.

## License

MIT © [SK Digital / BluPin Incorporated](https://github.com/sakhileb)
