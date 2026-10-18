<?php

namespace App\Models;

use App\Services\PublicFormCache;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class FormField extends Model
{
    use HasFactory;

    protected static function booted(): void
    {
        // A field is part of what the cached public form shows and validates
        // against, so changing one invalidates its form's cached copy.
        static::saved(fn (FormField $field) => $field->forgetFormPublicCache());
        static::deleted(fn (FormField $field) => $field->forgetFormPublicCache());
    }

    public function forgetFormPublicCache(): void
    {
        $slug = Form::withoutTeamScope()->whereKey($this->form_id)->value('slug');

        PublicFormCache::forget($slug);
    }

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'form_id',
        'type',
        'label',
        'placeholder',
        'options',
        'validation_rules',
        'order',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'options' => 'array',
            'validation_rules' => 'array',
            'order' => 'integer',
        ];
    }

    /**
     * Get the form that owns the field.
     */
    public function form(): BelongsTo
    {
        return $this->belongsTo(Form::class);
    }

    /**
     * Get AI suggestions for the field.
     */
    public function aiSuggestions(): HasMany
    {
        return $this->hasMany(AiSuggestion::class, 'field_id');
    }
}
