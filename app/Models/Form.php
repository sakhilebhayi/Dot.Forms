<?php

namespace App\Models;

use App\Models\Concerns\HasTeamScope;
use App\Services\PublicFormCache;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Collection;

class Form extends Model
{
    use HasFactory;
    use HasTeamScope;

    protected static function booted(): void
    {
        // The public view caches this form by slug (see PublicFormCache):
        // any save or delete, including publish/unpublish/archive/close and
        // a slug change, must drop the cached copy under both the new and
        // the previous slug.
        static::saved(fn (Form $form) => $form->forgetPublicCache());
        static::deleted(fn (Form $form) => $form->forgetPublicCache());
    }

    /**
     * Drop the cached public/preview copy of this form. Model events call
     * this already; call it directly after a bulk query that bypasses them
     * (e.g. $form->fields()->delete()).
     */
    public function forgetPublicCache(): void
    {
        PublicFormCache::forget($this->slug, $this->getOriginal('slug'));
    }

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'team_id',
        'user_id',
        'title',
        'slug',
        'description',
        'logo_path',
        'views_count',
        'current_version',
        'settings',
        'is_published',
        'published_at',
        'archived_at',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'settings' => 'array',
            'is_published' => 'boolean',
            'published_at' => 'datetime',
            'archived_at' => 'datetime',
            'views_count' => 'integer',
            'current_version' => 'integer',
        ];
    }

    /**
     * Get the team that owns the form.
     */
    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }

    /**
     * Get the user that created the form.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get all fields for the form.
     */
    public function fields(): HasMany
    {
        return $this->hasMany(FormField::class);
    }

    /**
     * Get all submissions for the form.
     */
    public function submissions(): HasMany
    {
        return $this->hasMany(FormSubmission::class);
    }

    /**
     * Get all AI suggestions linked to the form.
     */
    public function aiSuggestions(): HasMany
    {
        return $this->hasMany(AiSuggestion::class);
    }

    public function userRoles(): HasMany
    {
        return $this->hasMany(FormUserRole::class);
    }

    public function integrations(): HasMany
    {
        return $this->hasMany(FormIntegration::class);
    }

    /**
     * Narrower than editableBy() -- an editor can propose an integration
     * URL, but only an owner-equivalent user may approve it. Reuses the
     * same three-way check editableBy() uses for its owner-equivalent
     * branch (form creator, team owner) and additionally accepts an
     * explicit FormUserRole of 'owner' (not 'editor').
     */
    public function isApprover(User $user): bool
    {
        if ((int) $this->user_id === (int) $user->id || $user->ownsTeam($this->team)) {
            return true;
        }

        $role = $this->userRoles()->where('user_id', $user->id)->value('role');

        return $role === 'owner';
    }

    public function versions(): HasMany
    {
        return $this->hasMany(FormVersion::class);
    }

    /**
     * Guard against a form/team mismatch in route parameters. Every
     * Livewire component bound as Component::mount(Team $team, Form $form)
     * relies on Laravel's implicit route-model binding to resolve $form
     * independently of $team, so a URL like
     * /teams/{other-team}/forms/{form-belonging-to-a-different-team}/...
     * would otherwise resolve both models successfully and only leak data
     * if nobody checks that $form actually belongs to $team. This was
     * previously duplicated ad-hoc as
     * `abort_unless((int) $form->team_id === (int) $team->id, 404)` in
     * Builder, Submissions, AiFieldSuggestion and AiAnalytics -- centralized
     * here so future mount() methods can't forget the check.
     */
    public function assertBelongsToTeam(Team $team): void
    {
        abort_unless((int) $this->team_id === (int) $team->id, 404);
    }

    public function editableBy(User $user): bool
    {
        if ((int) $this->user_id === (int) $user->id || $user->ownsTeam($this->team)) {
            return true;
        }

        $role = $this->userRoles()->where('user_id', $user->id)->value('role');

        return in_array($role, ['owner', 'editor'], true);
    }

    public function viewableSubmissionsBy(User $user): bool
    {
        if ($this->editableBy($user)) {
            return true;
        }

        $role = $this->userRoles()->where('user_id', $user->id)->value('role');

        return in_array($role, ['viewer'], true);
    }

    public function availableCollaborators(): Collection
    {
        $members = $this->team->users()->get();

        if ($this->team->owner && ! $members->contains('id', $this->team->owner->id)) {
            $members->prepend($this->team->owner);
        }

        return $members->unique('id')->values();
    }
}
