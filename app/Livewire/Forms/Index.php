<?php

namespace App\Livewire\Forms;

use App\Actions\Forms\CreateBlankForm;
use App\Models\Team;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

class Index extends Component
{
    use WithPagination;

    public Team $team;

    #[Url(as: 'q')]
    public string $search = '';

    #[Url]
    public string $status = 'all';

    public function mount(Team $team): void
    {
        Gate::authorize('view', $team);

        $this->team = $team;
    }

    public function updatingSearch(): void
    {
        $this->resetPage();
    }

    public function updatedStatus(): void
    {
        $this->resetPage();
    }

    public function createForm(): void
    {
        Gate::authorize('canCreateForm', $this->team);

        $form = app(CreateBlankForm::class)($this->team, Auth::user());

        $this->redirectRoute('teams.forms.builder', ['team' => $this->team, 'form' => $form]);
    }

    public function duplicateForm(int $formId): void
    {
        $form = $this->team->forms()->with('fields')->findOrFail($formId);

        Gate::authorize('canEditForm', $this->team);

        $copy = $form->replicate(['slug', 'is_published', 'published_at', 'archived_at']);
        $copy->title = $form->title.' Copy';
        $copy->slug = $this->uniqueSlug($copy->title);
        $copy->is_published = false;
        $copy->published_at = null;
        $copy->archived_at = null;
        $copy->save();

        foreach ($form->fields as $field) {
            $copy->fields()->create($field->only([
                'type',
                'label',
                'placeholder',
                'options',
                'validation_rules',
                'order',
            ]));
        }

        session()->flash('status', 'Form duplicated successfully.');
    }

    public function archiveForm(int $formId): void
    {
        $form = $this->team->forms()->findOrFail($formId);

        Gate::authorize('canEditForm', $this->team);

        $form->update([
            'is_published' => false,
            'archived_at' => now(),
        ]);

        session()->flash('status', 'Form archived.');
    }

    public function deleteForm(int $formId): void
    {
        $form = $this->team->forms()->findOrFail($formId);

        Gate::authorize('canEditForm', $this->team);

        $form->delete();

        session()->flash('status', 'Form deleted.');
    }

    protected function uniqueSlug(string $title): string
    {
        return app(CreateBlankForm::class)->uniqueSlug($title);
    }

    public function render()
    {
        $forms = $this->team
            ->forms()
            ->when($this->search !== '', function ($query): void {
                $query->where(function ($inner): void {
                    $inner
                        ->where('title', 'like', '%'.$this->search.'%')
                        ->orWhere('description', 'like', '%'.$this->search.'%');
                });
            })
            ->when($this->status === 'published', fn ($query) => $query->where('is_published', true)->whereNull('archived_at'))
            ->when($this->status === 'draft', fn ($query) => $query->where('is_published', false)->whereNull('archived_at'))
            ->when($this->status === 'archived', fn ($query) => $query->whereNotNull('archived_at'))
            ->latest()
            ->paginate(10);

        return view('livewire.forms.index', [
            'forms' => $forms,
        ]);
    }
}
