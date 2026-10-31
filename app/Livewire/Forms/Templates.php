<?php

namespace App\Livewire\Forms;

use App\Models\FormTemplate;
use App\Models\Team;
use App\Services\FormTemplateService;
use App\Support\FormTemplates;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Livewire\Component;

/**
 * Master templates (R-30 Phase 3): the built-in library grouped by business
 * function, and the organisation's own saved masters. Using one creates a
 * draft and opens it in the press — nothing publishes by itself.
 */
class Templates extends Component
{
    public Team $team;

    public function mount(Team $team): void
    {
        Gate::authorize('view', $team);

        $this->team = $team;
    }

    public function useTemplate(string $slug): void
    {
        Gate::authorize('canCreateForm', $this->team);

        $payload = FormTemplates::all()[$slug] ?? null;

        abort_if($payload === null, 404);

        $form = app(FormTemplateService::class)->instantiate($payload, $this->team, Auth::user());

        session()->flash('status', 'Drafted from the "'.$payload['name'].'" template — set it, then publish.');

        $this->redirectRoute('teams.forms.builder', ['team' => $this->team, 'form' => $form]);
    }

    public function useTeamTemplate(int $templateId): void
    {
        Gate::authorize('canCreateForm', $this->team);

        $template = FormTemplate::query()
            ->where('team_id', $this->team->id)
            ->findOrFail($templateId);

        $form = app(FormTemplateService::class)->instantiate($template->payload, $this->team, Auth::user());

        session()->flash('status', 'Drafted from your "'.$template->name.'" template.');

        $this->redirectRoute('teams.forms.builder', ['team' => $this->team, 'form' => $form]);
    }

    public function deleteTeamTemplate(int $templateId): void
    {
        Gate::authorize('canEditForm', $this->team);

        FormTemplate::query()
            ->where('team_id', $this->team->id)
            ->whereKey($templateId)
            ->delete();

        session()->flash('status', 'Template removed. Forms already made from it are untouched.');
    }

    public function render()
    {
        return view('livewire.forms.templates', [
            'builtIns' => collect(FormTemplates::all())->groupBy('group', preserveKeys: true),
            'teamTemplates' => FormTemplate::query()
                ->where('team_id', $this->team->id)
                ->orderBy('name')
                ->get(),
        ]);
    }
}
