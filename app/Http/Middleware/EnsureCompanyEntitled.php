<?php

namespace App\Http\Middleware;

use App\Models\Team;
use App\Models\User;
use App\Services\InfoDot\EntitlementClient;
use App\Support\EntitlementRefusal;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * The signed-in area honours the company's entitlement (R-50).
 *
 * InfoDot decides whether a company holds Dot.Forms. A person who is already signed in must stop working when that
 * ends, so each request in the signed-in area asks (through a ten-minute cache) whether the company whose book is
 * being opened still holds the platform. A clear "no" answers with the plain refusal page and HTTP 403: nothing is
 * read from or written to the book. Anything else, including every kind of failure, lets the request through
 * exactly as before.
 *
 * It sits on the signed-in route group (routes/web.php) and on Jetstream's own pages (config/jetstream.php), and is
 * a Livewire persistent middleware, so component updates from those pages are checked too. It is not on sign-in,
 * sign-out, the handoff receiver, health checks, the API, or the public form: a respondent filling in a published
 * form is never asked about the company behind it.
 */
class EnsureCompanyEntitled
{
    /** Routes inside the checked groups that must keep working for a person whose company is refused. */
    private const EXEMPT = [
        'current-team.update', // switching to another company
        'profile.show',        // the person's own account
        'terms.show', 'policy.show', 'team-invitations.accept',
    ];

    public function __construct(private readonly EntitlementClient $entitlements) {}

    public function handle(Request $request, Closure $next): Response
    {
        if (! $this->entitlements->enabled() || $request->routeIs(...self::EXEMPT)) {
            return $next($request);
        }

        $user = $request->user() ?? Auth::guard('web')->user();

        if (! $user instanceof User) {
            return $next($request);
        }

        $team = $this->company($request, $user);

        // A personal workspace, or a company InfoDot does not know: nothing to ask. Sign-in decided who came in.
        if (! $team || $team->personal_team || blank($team->infodot_team_uuid)) {
            return $next($request);
        }

        $answer = $this->entitlements->forCompany((string) $team->infodot_team_uuid);

        if ($answer === null || $answer['entitled'] === true) {
            return $next($request);
        }

        return EntitlementRefusal::response(
            $answer['status'],
            $team->name,
            $user->allTeams()->reject(fn ($other) => $other->id === $team->id),
        );
    }

    /** The company whose book this request opens: the one in the address when the person belongs to it, else their current one. */
    private function company(Request $request, User $user): ?Team
    {
        $named = $request->route('team');

        if (is_scalar($named)) {
            $named = $user->allTeams()->firstWhere('id', (int) $named);
        }

        if ($named instanceof Team && $user->belongsToTeam($named)) {
            return $named;
        }

        $current = $user->currentTeam;

        return $current instanceof Team ? $current : null;
    }
}
