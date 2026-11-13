<?php

namespace App\Actions\Forms;

use App\Models\Form;
use App\Models\Team;
use App\Models\User;
use Illuminate\Support\Str;

/**
 * Sets a blank, unpublished form for a team: the one way a new master enters the book.
 *
 * Used by the register's "Set a new form" button and by the desk's primary action, so both
 * create exactly the same record. The caller authorises (canCreateForm) before calling.
 */
class CreateBlankForm
{
    public function __invoke(Team $team, User $user): Form
    {
        $title = 'Untitled Form';

        return Form::query()->create([
            'team_id' => $team->id,
            'user_id' => $user->id,
            'title' => $title,
            'slug' => $this->uniqueSlug($title),
            'settings' => [
                'confirmation_message' => 'Thanks for your submission.',
                'limit_responses' => null,
                'open_at' => null,
                'close_at' => null,
                'theme' => 'light',
                'brand_color' => '#4f46e5',
                'custom_css' => null,
                'retention_days' => null,
                'consent_required' => false,
                'consent_label' => 'I consent to processing my submitted data.',
                'quiz_enabled' => false,
                'quiz_answer_key' => [],
                'conversational_mode' => false,
                'crm_provider' => 'none',
                'crm_webhook_url' => null,
            ],
            'is_published' => false,
        ]);
    }

    public function uniqueSlug(string $title): string
    {
        $base = Str::slug($title) ?: 'form';
        $slug = $base;
        $counter = 1;

        // slugs are unique across all teams (see forms table migration), so
        // this check must bypass Form's per-team scope.
        while (Form::withoutTeamScope()->where('slug', $slug)->exists()) {
            $slug = $base.'-'.$counter;
            $counter++;
        }

        return $slug;
    }
}
