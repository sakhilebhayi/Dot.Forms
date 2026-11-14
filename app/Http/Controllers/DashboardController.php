<?php

namespace App\Http\Controllers;

use App\Models\FormSubmission;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Illuminate\View\View;

/**
 * The desk (R-50): where a person arrives after signing in through InfoDot.
 *
 * Everything shown comes from the current company's own records. Nothing is estimated:
 * with no forms there are no figures at all, only the blank sheet and the next step.
 */
class DashboardController extends Controller
{
    /** How many fields of the latest master the desk shows in miniature. */
    public const SHEET_FIELDS = 4;

    public function __invoke(Request $request): View
    {
        $user = $request->user();
        $team = $user->currentTeam;

        $forms = $team
            ? $team->forms()->withCount(['fields', 'submissions'])->orderBy('updated_at', 'desc')->limit(6)->get()
            : collect();

        $latest = $forms->first();

        return view('dashboard', [
            'team' => $team,
            'firstName' => Str::of($user->name)->trim()->before(' ')->toString(),
            'canCreate' => $team !== null && Gate::allows('canCreateForm', $team),
            'canEdit' => $team !== null && Gate::allows('canEditForm', $team),
            'canViewCopies' => $team !== null && Gate::allows('canViewSubmissions', $team),
            'forms' => $forms,
            'latest' => $latest,
            'latestFields' => $latest ? $latest->fields()->orderBy('order')->limit(self::SHEET_FIELDS)->get() : collect(),
            'totalForms' => $team ? $team->forms()->count() : 0,
            'published' => $team ? $team->forms()->where('is_published', true)->count() : 0,
            'copiesThisWeek' => $team
                ? FormSubmission::whereIn('form_id', $team->forms()->select('id'))
                    ->whereDate('created_at', '>=', now()->subDays(7))->count()
                : 0,
            'infodotUrl' => rtrim((string) config('ecosystem.platforms.infodot.url', 'https://infodot.co.za'), '/'),
        ]);
    }
}
