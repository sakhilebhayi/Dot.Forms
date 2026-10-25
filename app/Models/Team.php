<?php

namespace App\Models;

use Database\Factories\TeamFactory;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Laravel\Jetstream\Events\TeamCreated;
use Laravel\Jetstream\Events\TeamDeleted;
use Laravel\Jetstream\Events\TeamUpdated;
use Laravel\Jetstream\Team as JetstreamTeam;

class Team extends JetstreamTeam
{
    protected static function booted(): void
    {
        // The forms table cascades on delete, which fires no Form events, so
        // drop their cached public copies while the rows can still be found.
        static::deleting(fn (Team $model) => Form::forgetPublicCacheFor('team_id', $model->getKey()));
    }

    /** @use HasFactory<TeamFactory> */
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'name',
        'personal_team',
    ];

    /**
     * The event map for the model.
     *
     * @var array<string, class-string>
     */
    protected $dispatchesEvents = [
        'created' => TeamCreated::class,
        'updated' => TeamUpdated::class,
        'deleted' => TeamDeleted::class,
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'personal_team' => 'boolean',
        ];
    }

    /**
     * Get forms owned by this team.
     */
    public function forms(): HasMany
    {
        return $this->hasMany(Form::class);
    }

    /**
     * Create a new Eloquent Collection instance.
     *
     * @param  array<int, static>  $models
     */
    public function newCollection(array $models = []): Collection
    {
        return new Collection($models);
    }

    /**
     * Get the configured maximum team size.
     */
    public function maxMembers(): int
    {
        return (int) config('dotforms.teams.max_members', 10);
    }

    /**
     * Get the configured owner role label.
     */
    public function ownerRoleName(): string
    {
        return (string) config('dotforms.teams.owner_role', 'owner');
    }
}
