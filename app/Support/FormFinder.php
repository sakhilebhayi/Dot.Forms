<?php

namespace App\Support;

use App\Models\Form;
use App\Models\Team;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;

/**
 * The header search's one question: which forms of THIS company match the words (R-52).
 *
 * Scope: the viewer's own current company, and only when they belong to it (TeamPolicy::view). Never another company's forms.
 * The same match the book's own filter uses (title or description contains the words), with the LIKE wildcards escaped,
 * bound parameters, fewer than two characters finds nothing, and a hard limit. A viewer who may neither read the copies nor
 * edit forms still finds the form (the book lists it for every member) but lands on the book, not on its copies.
 */
class FormFinder
{
    public const MIN = 2;

    public function ready(string $query): bool
    {
        return mb_strlen(trim($query)) >= self::MIN;
    }

    public function allowed(?User $user, ?Team $team): bool
    {
        return $user !== null && $team !== null && $user->can('view', $team);
    }

    /** @return Collection<int, Form> */
    public function find(string $query, ?User $user, ?Team $team, int $limit = 7): Collection
    {
        $needle = trim($query);

        if (! $this->allowed($user, $team) || ! $this->ready($needle)) {
            return new Collection;
        }

        $like = '%'.str_replace(['\\', '%', '_'], ['\\\\', '\%', '\_'], mb_strtolower($needle)).'%';

        return $team->forms()
            ->where(function ($q) use ($like): void {
                $q->whereRaw("LOWER(title) LIKE ? ESCAPE '\\'", [$like])
                    ->orWhereRaw("LOWER(COALESCE(description, '')) LIKE ? ESCAPE '\\'", [$like]);
            })
            ->orderByDesc('updated_at')
            ->limit($limit)
            ->get(['id', 'team_id', 'title', 'slug', 'is_published', 'archived_at', 'updated_at']);
    }

    /**
     * The empty box offers this company's most recently touched forms, nothing invented.
     *
     * @return Collection<int, Form>
     */
    public function recent(?User $user, ?Team $team, int $limit = 5): Collection
    {
        if (! $this->allowed($user, $team)) {
            return new Collection;
        }

        return $team->forms()->orderByDesc('updated_at')->limit($limit)
            ->get(['id', 'team_id', 'title', 'slug', 'is_published', 'archived_at', 'updated_at']);
    }

    /** Where a form row leads for this viewer: its copies, else its master, else the book. */
    public function url(Form $form, User $user, Team $team): string
    {
        if ($user->can('canViewSubmissions', $team)) {
            return route('teams.forms.submissions', ['team' => $team, 'form' => $form]);
        }

        if ($user->can('canEditForm', $team)) {
            return route('teams.forms.builder', ['team' => $team, 'form' => $form]);
        }

        return route('teams.forms', ['team' => $team, 'q' => $form->title]);
    }
}
