<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A team's own saved master template (R-30 Phase 3). Always queried through the
 * owning team — there is no global listing by design.
 *
 * @property int $id
 * @property int $team_id
 * @property string $name
 * @property string|null $description
 * @property array $payload
 * @property int $created_by
 */
class FormTemplate extends Model
{
    protected $fillable = ['team_id', 'name', 'description', 'payload', 'created_by'];

    protected function casts(): array
    {
        return ['payload' => 'array'];
    }

    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
