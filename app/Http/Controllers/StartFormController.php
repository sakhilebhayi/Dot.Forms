<?php

namespace App\Http\Controllers;

use App\Actions\Forms\CreateBlankForm;
use App\Models\Team;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

/**
 * The desk's primary action: set a blank form and open it on the press (the builder).
 *
 * A POST, because it creates a record; a link that created one would fire on every prefetch.
 * Same rule and same record as the register's "Set a new form" button.
 */
class StartFormController extends Controller
{
    public function __invoke(Request $request, Team $team, CreateBlankForm $createBlankForm): RedirectResponse
    {
        Gate::authorize('canCreateForm', $team);

        $form = $createBlankForm($team, $request->user());

        return redirect()->route('teams.forms.builder', ['team' => $team, 'form' => $form]);
    }
}
