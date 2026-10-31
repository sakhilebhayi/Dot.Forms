<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FormSubmission extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    /** The desk's working states (R-30 Phase 4). */
    public const STATUSES = ['new', 'in_review', 'actioned', 'closed'];

    public const STATUS_LABELS = [
        'new' => 'New',
        'in_review' => 'In review',
        'actioned' => 'Actioned',
        'closed' => 'Closed',
    ];

    protected $fillable = [
        'form_id',
        'user_id',
        'data',
        'submitted_at',
        'completion_seconds',
        'quiz_score',
        'quiz_max_score',
        'ip_address',
        'user_agent',
        'status',
        'assigned_to',
        'internal_note',
    ];

    public function assignee()
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'data' => 'array',
            'submitted_at' => 'datetime',
            'completion_seconds' => 'integer',
            'quiz_score' => 'integer',
            'quiz_max_score' => 'integer',
        ];
    }

    /**
     * Get the form this submission belongs to.
     */
    public function form(): BelongsTo
    {
        return $this->belongsTo(Form::class);
    }

    /**
     * Get the user who submitted this response.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
