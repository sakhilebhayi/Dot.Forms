<?php

use App\Http\Controllers\Auth\EcosystemAuthController;
use App\Http\Controllers\CspReportController;
use App\Http\Controllers\GuardianHealthController;
use App\Http\Controllers\SchedulerProbeController;
use App\Http\Middleware\AuthenticateGuardian;
use App\Livewire\Dashboard\Analytics as DashboardAnalytics;
use App\Livewire\Forms\AiAnalytics;
use App\Livewire\Forms\AiBuilder;
use App\Livewire\Forms\AiFieldSuggestion;
use App\Livewire\Forms\Builder;
use App\Livewire\Forms\PublicView;
use App\Livewire\Forms\Submissions;
use App\Livewire\Forms\Templates;
use App\Models\Team;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;
use Illuminate\View\Middleware\ShareErrorsFromSession;
use Laravel\Fortify\Features;
use Laravel\Jetstream\Jetstream;

Route::post('/auth/ecosystem', [EcosystemAuthController::class, 'handle'])
    ->withoutMiddleware(['Illuminate\Foundation\Http\Middleware\PreventRequestForgery', 'Illuminate\Foundation\Http\Middleware\VerifyCsrfToken', 'Illuminate\Foundation\Http\Middleware\ValidateCsrfToken'])->name('auth.ecosystem');

// Browsers POST Content-Security-Policy violation reports here (the policy is report-only, see SecurityHeaders). The browser sends
// no cookie, CSRF token or login, so the route drops the web group's session and CSRF middleware: a report neither starts a
// session nor needs one. Throttled per IP; the controller keeps only the violated directive and the blocked host.
Route::post('/csp-report', CspReportController::class)
    ->withoutMiddleware([StartSession::class, ShareErrorsFromSession::class, PreventRequestForgery::class])
    ->middleware('throttle:60,1,csp-report')
    ->name('csp.report');

// Links minted before the token moved into a POST body carried it in the URL. They are no longer honoured:
// the visitor is sent to the normal sign-in page and the token in the URL is never read.
Route::get('/auth/ecosystem', [EcosystemAuthController::class, 'legacy']);

Route::get('/', function () {
    return view('welcome');
});

// Cookie Policy — Jetstream's termsAndPrivacyPolicy feature covers terms.show/policy.show
// natively (registered at /terms-of-service and /privacy-policy, reading resources/markdown/
// terms.md and policy.md). There's no Jetstream equivalent for a Cookie Policy, so this one is
// wired by hand, following the exact same Markdown-source convention.
Route::get('/cookies', function () {
    return view('cookies', [
        'cookies' => Str::markdown(file_get_contents(Jetstream::localizedMarkdownPath('cookies.md'))),
    ]);
})->name('cookies');

Route::middleware([
    'auth:sanctum',
    config('jetstream.auth_session'),
    'verified',
])->group(function () {
    Route::get('/dashboard', function () {
        return view('dashboard');
    })->name('dashboard');

    Route::get('/dashboard/analytics', DashboardAnalytics::class)
        ->name('dashboard.analytics');

    Route::get('/teams/{team}/forms', function (Team $team) {
        Gate::authorize('view', $team);

        return view('teams.forms', [
            'team' => $team,
        ]);
    })->name('teams.forms');

    Route::get('/teams/{team}/forms/{form}/builder', Builder::class)
        ->name('teams.forms.builder');

    Route::get('/teams/{team}/forms/{form}/submissions', Submissions::class)
        ->name('teams.forms.submissions');

    Route::get('/teams/{team}/forms/templates', Templates::class)
        ->name('teams.forms.templates');

    Route::get('/teams/{team}/forms/ai-builder', AiBuilder::class)
        ->name('teams.forms.ai-builder');

    Route::get('/teams/{team}/forms/{form}/ai-suggestions', AiFieldSuggestion::class)
        ->name('teams.forms.ai-suggestions');

    Route::get('/teams/{team}/forms/{form}/ai-analytics', AiAnalytics::class)
        ->name('teams.forms.ai-analytics');
});

Route::get('/forms/{slug}', PublicView::class)->name('forms.public');

// Public liveness probe for the cron scheduler and queue worker, watched from GitHub every
// 15 minutes by .github/workflows/scheduler-watchdog.yml.
Route::get('/up/scheduler', SchedulerProbeController::class)->name('up.scheduler');

// Guardian observability report for Dot.Brain (dot-guardian/v1): operational
// detail, so behind a bearer token (fails closed with 503 when none is
// configured) rather than public like /up and /up/scheduler.
Route::get('/guardian/health', GuardianHealthController::class)
    ->middleware(AuthenticateGuardian::class)
    ->name('guardian.health');

// Public sign-up is closed (accounts are created by InfoDot at first sign-in). Keep the named route so any link or
// invitation email that points at it lands on the login page instead of failing.
if (! Features::enabled(Features::registration())) {
    Route::redirect('/register', '/login')->name('register');
}
