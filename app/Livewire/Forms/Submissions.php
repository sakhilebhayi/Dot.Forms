<?php

namespace App\Livewire\Forms;

use App\Exports\FormSubmissionsExport;
use App\Models\Form;
use App\Models\FormSubmission;
use App\Models\Team;
use App\Support\FieldTypes;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;
use Maatwebsite\Excel\Facades\Excel;

#[Layout('components.layouts.app')]
class Submissions extends Component
{
    use WithPagination;

    public Team $team;

    public Form $form;

    /**
     * @var array<int, int>
     */
    public array $selectedFieldIds = [];

    /**
     * @var array<int, int>
     */
    public array $selectedSubmissionIds = [];

    #[Url(as: 'q')]
    public string $search = '';

    public bool $showDetailModal = false;

    public ?FormSubmission $activeSubmission = null;

    public function mount(Team $team, Form $form): void
    {
        Gate::authorize('view', $team);

        $form->assertBelongsToTeam($team);

        $user = Auth::user();

        if (! $user || (! Gate::forUser($user)->allows('canViewSubmissions', $team) && ! $form->viewableSubmissionsBy($user))) {
            abort(403);
        }

        $this->team = $team;
        $this->form = $form->load('fields');
        $this->selectedFieldIds = $this->collectableFields()->pluck('id')->all();
    }

    public function updatingSearch(): void
    {
        $this->resetPage();
    }

    public function updatedSelectedFieldIds(): void
    {
        $this->selectedFieldIds = array_values(array_map('intval', $this->selectedFieldIds));
    }

    public function viewSubmission(int $submissionId): void
    {
        $this->activeSubmission = $this->form->submissions()->findOrFail($submissionId);
        $this->showDetailModal = true;
    }

    public function closeDetailModal(): void
    {
        $this->showDetailModal = false;
        $this->activeSubmission = null;
    }

    public function deleteSelected(): void
    {
        $ids = array_values(array_unique(array_map('intval', $this->selectedSubmissionIds)));

        if ($ids === []) {
            return;
        }

        $this->form->submissions()->whereIn('id', $ids)->delete();
        $this->selectedSubmissionIds = [];

        session()->flash('status', 'Selected submissions deleted.');
    }

    public function exportCsv()
    {
        return Excel::download(
            new FormSubmissionsExport($this->form->fresh('fields'), $this->effectiveFieldIds()),
            'form-'.$this->form->id.'-submissions.csv',
            \Maatwebsite\Excel\Excel::CSV
        );
    }

    public function exportExcel()
    {
        if (! extension_loaded('zip')) {
            session()->flash('status', 'XLSX export requires the PHP zip extension. Exporting CSV instead.');

            return $this->exportCsv();
        }

        return Excel::download(
            new FormSubmissionsExport($this->form->fresh('fields'), $this->effectiveFieldIds()),
            'form-'.$this->form->id.'-submissions.xlsx',
            \Maatwebsite\Excel\Excel::XLSX
        );
    }

    public function exportUserData(int $submissionId)
    {
        $submission = $this->form->submissions()->findOrFail($submissionId);

        if (! $submission->user_id) {
            session()->flash('status', 'This submission is anonymous, so there is no user profile data to export.');

            return null;
        }

        $userSubmissions = $this->form
            ->submissions()
            ->where('user_id', $submission->user_id)
            ->get(['id', 'submitted_at', 'data', 'ip_address', 'user_agent', 'completion_seconds'])
            ->map(fn ($item) => [
                'id' => $item->id,
                'submitted_at' => optional($item->submitted_at)?->toIso8601String(),
                'completion_seconds' => $item->completion_seconds,
                'data' => $item->data,
            ])
            ->all();

        $payload = [
            'form' => [
                'id' => $this->form->id,
                'title' => $this->form->title,
            ],
            'user_id' => $submission->user_id,
            'submissions' => $userSubmissions,
        ];

        return response()->streamDownload(function () use ($payload): void {
            echo json_encode($payload, JSON_PRETTY_PRINT);
        }, 'form-'.$this->form->id.'-user-'.$submission->user_id.'-data.json', [
            'Content-Type' => 'application/json',
        ]);
    }

    /**
     * @return array<int, int>
     */
    protected function effectiveFieldIds(): array
    {
        if ($this->selectedFieldIds === []) {
            return $this->collectableFields()->pluck('id')->all();
        }

        return $this->selectedFieldIds;
    }

    /** The fields that can hold an answer: structural page breaks never show as columns. */
    public function collectableFields()
    {
        return $this->form->fields->filter(fn ($field) => FieldTypes::collectsAnswer($field->type))->values();
    }

    public function fieldValue(FormSubmission $submission, int $fieldId): string
    {
        $value = $submission->data[$fieldId] ?? null;

        if (is_bool($value)) {
            return $value ? 'Yes' : 'No';
        }

        if (is_array($value)) {
            return implode(', ', array_map('strval', $value));
        }

        return (string) ($value ?? '');
    }

    /**
     * @return array<int, string>
     */
    public function activeSubmissionRows(): array
    {
        if (! $this->activeSubmission) {
            return [];
        }

        $rows = [];

        foreach ($this->collectableFields() as $field) {
            $rows[$field->label] = $this->fieldValue($this->activeSubmission, (int) $field->id);
        }

        $rows['Submitted At'] = optional($this->activeSubmission->submitted_at)?->toDateTimeString() ?? '';
        $rows['IP Address'] = (string) ($this->activeSubmission->ip_address ?? '');
        $rows['User Agent'] = (string) ($this->activeSubmission->user_agent ?? '');

        return $rows;
    }

    protected function submissions(): LengthAwarePaginator
    {
        return $this->form
            ->submissions()
            ->when($this->search !== '', function ($query): void {
                $query->whereRaw('LOWER(data) like ?', ['%'.strtolower($this->search).'%']);
            })
            ->latest('submitted_at')
            ->paginate(15);
    }

    public function render()
    {
        return view('livewire.forms.submissions', [
            'submissions' => $this->submissions(),
        ]);
    }
}
