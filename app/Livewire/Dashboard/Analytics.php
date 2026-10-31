<?php

namespace App\Livewire\Dashboard;

use App\Models\Form;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('components.layouts.app')]
class Analytics extends Component
{
    public array $stats = [
        'total_submissions' => 0,
        'completion_rate' => 0,
        'avg_completion_seconds' => 0,
        'total_views' => 0,
        'total_starts' => 0,
    ];

    /** R-30 funnel: per-form views → starts → copies. Starts count from deploy day on. */
    public array $funnelRows = [];

    public array $submissionLabels = [];

    public array $submissionData = [];

    public array $formLabels = [];

    public array $formData = [];

    public function mount(): void
    {
        $team = Auth::user()?->currentTeam;

        abort_unless($team, 403);

        // team_id filtering is now handled by Form's HasTeamScope global
        // scope (scoped to Auth::user()->currentTeam, which is $team here).
        $forms = Form::query()
            ->withCount('submissions')
            ->get();

        $totalSubmissions = (int) $forms->sum('submissions_count');
        $totalViews = (int) $forms->sum('views_count');
        $completionRate = $totalViews > 0 ? round(($totalSubmissions / $totalViews) * 100, 1) : 0;

        $avgCompletionSeconds = (int) round(
            $forms
                ->flatMap(fn ($form) => $form->submissions()->pluck('completion_seconds'))
                ->filter(fn ($value) => $value !== null)
                ->avg() ?? 0
        );

        $this->stats = [
            'total_submissions' => $totalSubmissions,
            'completion_rate' => $completionRate,
            'avg_completion_seconds' => $avgCompletionSeconds,
            'total_views' => $totalViews,
            'total_starts' => (int) $forms->sum('starts_count'),
        ];

        // The funnel, per form: views → starts (first answer typed) → copies lodged.
        // Starts only exist from the day the counter shipped, so a long-lived form can
        // honestly show more copies than starts; the view marks those rows.
        $this->funnelRows = $forms
            ->sortByDesc('views_count')
            ->take(12)
            ->map(fn ($form) => [
                'title' => $form->title,
                'views' => (int) $form->views_count,
                'starts' => (int) $form->starts_count,
                'submissions' => (int) $form->submissions_count,
                'finish_rate' => $form->starts_count > 0
                    ? min(100, round($form->submissions_count / $form->starts_count * 100))
                    : null,
            ])
            ->values()
            ->all();

        $byDay = collect(range(6, 0))
            ->mapWithKeys(function ($daysAgo) use ($forms) {
                $date = now()->subDays($daysAgo)->toDateString();
                $count = 0;

                foreach ($forms as $form) {
                    $count += $form->submissions()
                        ->whereDate('submitted_at', $date)
                        ->count();
                }

                return [$date => $count];
            });

        $this->submissionLabels = $byDay->keys()->map(fn ($d) => now()->parse($d)->format('M d'))->values()->all();
        $this->submissionData = $byDay->values()->all();

        $topForms = $forms
            ->sortByDesc('submissions_count')
            ->take(6)
            ->values();

        $this->formLabels = $topForms->map(fn ($form) => $form->title)->all();
        $this->formData = $topForms->map(fn ($form) => $form->submissions_count)->all();
    }

    public function render()
    {
        return view('livewire.dashboard.analytics');
    }
}
