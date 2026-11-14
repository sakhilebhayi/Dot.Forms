<?php

namespace App\Http\Controllers;

use App\Models\Form;
use App\Models\FormSubmission;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Illuminate\View\View;

/**
 * The desk (R-50, R-51): where a person arrives after signing in through InfoDot.
 *
 * Everything shown comes from the current company's own records. Nothing is estimated:
 * with no forms there are no figures at all, only the blank sheet and the next step.
 *
 * Every copy count is derived through the company's own forms (`form_id` in the team's form ids) and is
 * computed only for a person who may read copies (`canViewSubmissions`); anyone else gets null, never zero.
 */
class DashboardController extends Controller
{
    /** How many fields of the selected master the desk shows in miniature. */
    public const SHEET_FIELDS = 4;

    /** How many forms the register lists. */
    public const REGISTER_ROWS = 12;

    /** How many of a person's own waiting copies the desk lists. */
    public const ASSIGNED_ROWS = 5;

    /** Days in the copies-per-day chart, today included. */
    public const CHART_DAYS = 14;

    public function __invoke(Request $request): View
    {
        $user = $request->user();
        $team = $user->currentTeam;

        $canCreate = $team !== null && Gate::allows('canCreateForm', $team);
        $canEdit = $team !== null && Gate::allows('canEditForm', $team);
        $canViewCopies = $team !== null && Gate::allows('canViewSubmissions', $team);

        $forms = collect();
        if ($team) {
            $query = $team->forms()->withCount(['fields', 'submissions']);
            if ($canViewCopies) {
                $query->withCount(['submissions as new_submissions_count' => fn ($q) => $q->where('status', 'new')])
                    ->withMax('submissions', 'submitted_at');
            }
            $forms = $query->orderBy('updated_at', 'desc')->orderBy('id', 'desc')->limit(self::REGISTER_ROWS)->get();
        }

        // Option A: ?form=ID picks the stage master. It must be one of the rows loaded from THIS company's forms;
        // anything else (another company's id, junk, a form past the register) falls back to the latest.
        $selected = $forms->first();
        $asked = $request->query('form');
        if (is_scalar($asked) && ctype_digit((string) $asked)) {
            $selected = $forms->firstWhere('id', (int) $asked) ?? $selected;
        }

        $showStage = $selected !== null && ($canEdit || $canViewCopies);
        $copies = $canViewCopies && $team ? $this->copyFigures($team->forms()->select('id'), $user->id, $showStage ? $selected : null) : null;

        return view('dashboard', [
            'team' => $team,
            'firstName' => Str::of($user->name)->trim()->before(' ')->toString(),
            'canCreate' => $canCreate,
            'canEdit' => $canEdit,
            'canViewCopies' => $canViewCopies,
            'forms' => $forms,
            'selected' => $selected,
            'showStage' => $showStage,
            'selectedFields' => $showStage ? $selected->fields()->orderBy('order')->limit(self::SHEET_FIELDS)->get() : collect(),
            'copies' => $copies,
            'totalForms' => $team ? $team->forms()->count() : 0,
            'published' => $team ? $team->forms()->where('is_published', true)->whereNull('archived_at')->count() : 0,
            'unset' => $team ? $team->forms()->where('is_published', false)->whereNull('archived_at')->count() : 0,
            'closed' => $team ? $team->forms()->whereNotNull('archived_at')->count() : 0,
            'copiesThisWeek' => $team
                ? FormSubmission::whereIn('form_id', $team->forms()->select('id'))
                    ->whereDate('created_at', '>=', now()->subDays(7))->count()
                : 0,
            'infodotUrl' => rtrim((string) config('ecosystem.platforms.infodot.url', 'https://infodot.co.za'), '/'),
        ]);
    }

    /**
     * The copy-derived figures. $formIds is always the company's own forms, so no row of another company is reachable.
     *
     * @return array{waiting: int, byStatus: array<string, int>, decided: array<string, int>, assignedCount: int, assigned: Collection, perDay: array<int, array{date: Carbon, count: int}>|null, perDayTotal: int}
     */
    private function copyFigures($formIds, int $userId, ?Form $selected): array
    {
        $byStatus = FormSubmission::whereIn('form_id', $formIds)
            ->select('status', DB::raw('count(*) as n'))->groupBy('status')->pluck('n', 'status');
        $decided = FormSubmission::whereIn('form_id', $formIds)
            ->whereIn('decision', FormSubmission::DECISIONS)
            ->select('decision', DB::raw('count(*) as n'))->groupBy('decision')->pluck('n', 'decision');

        $mine = FormSubmission::whereIn('form_id', $formIds)
            ->where('assigned_to', $userId)->whereIn('status', ['new', 'in_review']);

        $status = [];
        foreach (FormSubmission::STATUSES as $key) {
            $status[$key] = (int) ($byStatus[$key] ?? 0);
        }

        $perDay = null;
        $perDayTotal = 0;
        if ($selected) {
            $start = now()->startOfDay()->subDays(self::CHART_DAYS - 1);
            $counts = FormSubmission::where('form_id', $selected->id)
                ->where('submitted_at', '>=', $start)
                ->select(DB::raw('date(submitted_at) as day'), DB::raw('count(*) as n'))
                ->groupBy('day')->pluck('n', 'day');
            $perDay = [];
            for ($i = 0; $i < self::CHART_DAYS; $i++) {
                $day = $start->copy()->addDays($i);
                $n = (int) ($counts[$day->toDateString()] ?? 0);
                $perDayTotal += $n;
                $perDay[] = ['date' => $day, 'count' => $n];
            }
        }

        return [
            'waiting' => $status['new'],
            'byStatus' => $status,
            'decided' => [
                'approved' => (int) ($decided['approved'] ?? 0),
                'rejected' => (int) ($decided['rejected'] ?? 0),
            ],
            'assignedCount' => (clone $mine)->count(),
            'assigned' => $mine->orderBy('submitted_at')->orderBy('id')->with('form:id,title')->limit(self::ASSIGNED_ROWS)->get(),
            'perDay' => $perDay,
            'perDayTotal' => $perDayTotal,
        ];
    }
}
