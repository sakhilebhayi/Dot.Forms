<?php

namespace App\Livewire\Forms;

use App\Models\Form;
use App\Support\FormFinder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\View\View;
use Livewire\Attributes\Computed;
use Livewire\Component;

/**
 * The search in the top bar (the shared dot-ui pill, typed into, with its results under it), on every signed-in page.
 *
 * It keeps the book's own search: Enter with nothing highlighted goes to the book filtered by the words (GET ?q=, the
 * book's #[Url(as: 'q')]). Typing also lists the forms of the viewer's own company that match, through App\Support\FormFinder.
 * A person with no company gets the same pill and a plain line saying what to do, not a missing control.
 */
class HeaderSearch extends Component
{
    private const LIMIT = 7;

    public string $query = '';

    #[Computed]
    public function team()
    {
        return auth()->user()?->currentTeam;
    }

    public function ready(): bool
    {
        return app(FormFinder::class)->ready($this->query);
    }

    /** @return Collection<int, Form> */
    #[Computed]
    public function forms(): Collection
    {
        return app(FormFinder::class)->find($this->query, auth()->user(), $this->team, self::LIMIT);
    }

    /** @return Collection<int, Form> */
    #[Computed]
    public function recent(): Collection
    {
        return app(FormFinder::class)->recent(auth()->user(), $this->team);
    }

    public function urlFor(Form $form): string
    {
        return app(FormFinder::class)->url($form, auth()->user(), $this->team);
    }

    /** Enter in the box: the first match, else the book filtered by the words. Nothing without a company. */
    public function go()
    {
        $team = $this->team;
        $user = auth()->user();

        if (! app(FormFinder::class)->allowed($user, $team)) {
            return null;
        }

        $first = $this->forms->first();

        if ($first) {
            return $this->redirect($this->urlFor($first), navigate: false);
        }

        $words = trim($this->query);

        return $this->redirect(route('teams.forms', $words === '' ? ['team' => $team] : ['team' => $team, 'q' => $words]), navigate: false);
    }

    public function render(): View
    {
        return view('livewire.forms.header-search');
    }
}
