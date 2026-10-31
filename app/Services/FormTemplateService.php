<?php

namespace App\Services;

use App\Models\Form;
use App\Models\FormField;
use App\Models\Team;
use App\Models\User;
use App\Support\FieldTypes;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Turns a template payload into a real draft master, and a master back into a payload
 * (R-30 Phase 3). A payload's fields carry symbolic keys; instantiation mints a fresh
 * uuid per key and remaps every visibility rule onto the new keys, so a template's
 * logic works on the very first open. Only registry types survive the trip.
 */
class FormTemplateService
{
    public function instantiate(array $payload, Team $team, User $user): Form
    {
        $title = (string) ($payload['name'] ?? 'Untitled form');

        return DB::transaction(function () use ($payload, $team, $user, $title): Form {
            $form = Form::query()->create([
                'team_id' => $team->id,
                'user_id' => $user->id,
                'title' => $title,
                'slug' => $this->uniqueSlug($title),
                'description' => (string) ($payload['description'] ?? ''),
                'settings' => is_array($payload['settings'] ?? null) ? $payload['settings'] : [],
                'is_published' => false,
            ]);

            // First pass mints the real keys; the second can then remap rule references.
            $keyMap = [];
            foreach ((array) ($payload['fields'] ?? []) as $field) {
                if (! empty($field['key'])) {
                    $keyMap[(string) $field['key']] = (string) Str::uuid();
                }
            }

            $order = 0;
            foreach ((array) ($payload['fields'] ?? []) as $field) {
                $type = (string) ($field['type'] ?? 'text');

                if (! in_array($type, FieldTypes::ALL, true)) {
                    continue; // a payload may be old or hand-edited: skip what the registry no longer knows
                }

                $rules = [];
                if (! empty($field['required']) && FieldTypes::collectsAnswer($type)) {
                    $rules[] = 'required';
                }
                $rules = array_merge($rules, FieldTypes::storedRules($type));

                $visibility = null;
                if (is_array($field['visibility'] ?? null) && ! empty($field['visibility']['rules'])) {
                    $mapped = [];
                    foreach ($field['visibility']['rules'] as $rule) {
                        $trigger = $keyMap[(string) ($rule['trigger_field_key'] ?? '')] ?? null;
                        if ($trigger !== null) {
                            $mapped[] = [
                                'trigger_field_key' => $trigger,
                                'operator' => (string) ($rule['operator'] ?? 'equals'),
                                'value' => (string) ($rule['value'] ?? ''),
                            ];
                        }
                    }
                    if ($mapped !== []) {
                        $visibility = [
                            'logic' => (($field['visibility']['logic'] ?? 'all') === 'any') ? 'any' : 'all',
                            'rules' => $mapped,
                        ];
                    }
                }

                $form->fields()->create([
                    'type' => $type,
                    'label' => (string) ($field['label'] ?? FieldTypes::label($type)),
                    'placeholder' => $field['placeholder'] ?? null,
                    'options' => [
                        'choices' => array_values(array_map('strval', (array) ($field['choices'] ?? []))),
                        'default_value' => $type === 'hidden' ? ($field['default_value'] ?? null) : null,
                        'helper_text' => $field['helper'] ?? null,
                        'conditional_logic' => null,
                        'field_key' => $keyMap[(string) ($field['key'] ?? '')] ?? (string) Str::uuid(),
                        'visibility_rule' => $visibility['rules'][0] ?? null,
                        'visibility' => $visibility,
                    ],
                    'validation_rules' => $rules,
                    'order' => ++$order,
                ]);
            }

            return $form;
        });
    }

    /** A master serialised back into a payload, for saving as a team template. */
    public function payloadFromForm(Form $form): array
    {
        return [
            'name' => $form->title,
            'description' => (string) ($form->description ?? ''),
            'settings' => collect($form->settings ?? [])
                ->except(['crm_webhook_url', 'webhook_url', 'slack_webhook_url', 'zapier_webhook_url', 'make_webhook_url', 'cover_path', 'access_password_hash'])
                ->all(),
            'fields' => $form->fields()->orderBy('order')->get()->map(function (FormField $field): array {
                $visibility = $field->options['visibility'] ?? null;

                return array_filter([
                    'key' => $field->options['field_key'] ?? (string) Str::uuid(),
                    'type' => $field->type,
                    'label' => $field->label,
                    'placeholder' => $field->placeholder,
                    'choices' => $field->options['choices'] ?? [],
                    'default_value' => $field->options['default_value'] ?? null,
                    'helper' => $field->options['helper_text'] ?? null,
                    'required' => in_array('required', $field->validation_rules ?? [], true),
                    'visibility' => is_array($visibility) && ! empty($visibility['rules']) ? $visibility : null,
                ], fn ($value) => $value !== null && $value !== '' && $value !== []);
            })->all(),
        ];
    }

    private function uniqueSlug(string $title): string
    {
        $base = Str::slug($title) ?: 'form';
        $slug = $base;
        $n = 1;

        while (Form::withoutTeamScope()->where('slug', $slug)->exists()) {
            $slug = $base.'-'.(++$n);
        }

        return $slug;
    }
}
