<?php

namespace App\Livewire\Forms;

use App\Events\FormBuilderUpdated;
use App\Models\Form;
use App\Models\FormIntegration;
use App\Models\FormUserRole;
use App\Models\FormVersion;
use App\Models\Team;
use App\Rules\SafeWebhookUrl;
use App\Services\Ai\AiFieldSuggestionEngine;
use App\Services\PublicFormCache;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\WithFileUploads;

#[Layout('components.layouts.app')]
class Builder extends Component
{
    use WithFileUploads;

    public Team $team;

    public Form $form;

    public string $title = '';

    public ?string $description = null;

    public array $settings = [];

    public array $fields = [];

    public $logoUpload;

    public array $formRoles = [];

    public ?int $selectedMemberId = null;

    public string $selectedMemberRole = 'viewer';

    public array $versions = [];

    public bool $showFieldSettingsModal = false;

    public ?int $editingFieldIndex = null;

    public ?int $selectedVersionId = null;

    public ?string $rejectingIntegrationType = null;

    public string $integrationRejectReason = '';

    /**
     * @var array<int, string>
     */
    public array $fieldTypes = [
        'text',
        'email',
        'number',
        'textarea',
        'select',
        'radio',
        'checkbox',
        'date',
        'file',
    ];

    public function mount(Team $team, Form $form): void
    {
        Gate::authorize('view', $team);

        $form->assertBelongsToTeam($team);

        $user = Auth::user();

        if (! $user || (! Gate::forUser($user)->allows('canEditForm', $team) && ! $form->editableBy($user))) {
            abort(403);
        }

        $this->team = $team;
        $this->form = $form;
        $this->hydrateState();
    }

    public function addField(string $type): void
    {
        Gate::authorize('canEditForm', $this->team);

        if (! in_array($type, $this->fieldTypes, true)) {
            return;
        }

        $this->fields[] = [
            'id' => null,
            'key' => (string) Str::uuid(),
            'type' => $type,
            'label' => Str::headline($type).' Field',
            'placeholder' => null,
            'options' => '',
            'required' => false,
            'helper_text' => null,
            'conditional_logic' => null,
            'visibility_rule' => ['trigger_field_key' => null, 'operator' => 'equals', 'value' => ''],
        ];
    }

    public function removeField(int $index): void
    {
        Gate::authorize('canEditForm', $this->team);

        unset($this->fields[$index]);
        $this->fields = array_values($this->fields);
    }

    public function moveFieldUp(int $index): void
    {
        if ($index < 1) {
            return;
        }

        [$this->fields[$index - 1], $this->fields[$index]] = [$this->fields[$index], $this->fields[$index - 1]];
        $this->fields = array_values($this->fields);
    }

    public function moveFieldDown(int $index): void
    {
        if ($index >= count($this->fields) - 1) {
            return;
        }

        [$this->fields[$index + 1], $this->fields[$index]] = [$this->fields[$index], $this->fields[$index + 1]];
        $this->fields = array_values($this->fields);
    }

    /**
     * @param  array<int, string>  $orderedKeys
     */
    public function reorderFields(array $orderedKeys): void
    {
        $lookup = [];

        foreach ($this->fields as $field) {
            $lookup[$field['key']] = $field;
        }

        $reordered = [];

        foreach ($orderedKeys as $key) {
            if (isset($lookup[$key])) {
                $reordered[] = $lookup[$key];
                unset($lookup[$key]);
            }
        }

        foreach ($lookup as $remaining) {
            $reordered[] = $remaining;
        }

        $this->fields = $reordered;
    }

    public function openFieldSettings(int $index): void
    {
        $this->editingFieldIndex = $index;
        $this->showFieldSettingsModal = true;
    }

    public function closeFieldSettings(): void
    {
        $this->showFieldSettingsModal = false;
        $this->editingFieldIndex = null;
    }

    public function saveDraft(): void
    {
        $this->persist();

        $this->form->update([
            'is_published' => false,
            'published_at' => null,
            'archived_at' => null,
        ]);

        session()->flash('status', 'Draft saved.');
    }

    public function publish(): void
    {
        $this->persist();

        $this->form->update([
            'is_published' => true,
            'published_at' => now(),
            'archived_at' => null,
        ]);

        session()->flash('status', 'Form published.');
    }

    public function autoSave(): void
    {
        $this->persist(false);
    }

    public function assignMemberRole(): void
    {
        $this->validate([
            'selectedMemberId' => ['required', 'integer'],
            'selectedMemberRole' => ['required', 'in:viewer,editor,owner'],
        ]);

        FormUserRole::query()->updateOrCreate(
            [
                'form_id' => $this->form->id,
                'user_id' => $this->selectedMemberId,
            ],
            [
                'role' => $this->selectedMemberRole,
            ]
        );

        $this->form->refresh();
        $this->hydrateState();
        session()->flash('status', 'Form role updated.');
    }

    public function removeMemberRole(int $userId): void
    {
        $this->form->userRoles()->where('user_id', $userId)->delete();
        $this->form->refresh();
        $this->hydrateState();
        session()->flash('status', 'Form role removed.');
    }

    public function revertToVersion(int $versionId): void
    {
        $version = $this->form->versions()->findOrFail($versionId);

        PublicFormCache::deferring(fn () => DB::transaction(function () use ($version): void {
            $this->form->update([
                'title' => $version->title,
                'description' => $version->description,
                'settings' => $version->settings,
                'current_version' => $version->version_number,
            ]);

            $this->form->fields()->delete();

            foreach (($version->fields_snapshot ?? []) as $index => $field) {
                $this->form->fields()->create([
                    'type' => (string) ($field['type'] ?? 'text'),
                    'label' => (string) ($field['label'] ?? 'Untitled Field'),
                    'placeholder' => $field['placeholder'] ?? null,
                    'options' => $field['options'] ?? [],
                    'validation_rules' => $field['validation_rules'] ?? [],
                    'order' => $index + 1,
                ]);
            }

            $this->form->refresh();
        }));

        $this->hydrateState();
        session()->flash('status', 'Form reverted to version #'.$version->version_number.'.');
    }

    public function suggestValidationRules(): void
    {
        foreach ($this->fields as $index => $field) {
            $label = Str::lower((string) ($field['label'] ?? ''));
            $type = (string) ($field['type'] ?? 'text');

            if (str_contains($label, 'email') && $type !== 'email') {
                $this->fields[$index]['type'] = 'email';
            }

            if (str_contains($label, 'phone') && $type === 'number') {
                $this->fields[$index]['type'] = 'text';
            }

            if (str_contains($label, 'name') || str_contains($label, 'email')) {
                $this->fields[$index]['required'] = true;
            }
        }

        session()->flash('status', 'AI suggested validation updates applied.');
    }

    public function suggestConditionalLogicForEditingField(): void
    {
        if ($this->editingFieldIndex === null) {
            return;
        }

        $index = $this->editingFieldIndex;
        $current = $this->fields[$index] ?? null;
        $previous = $index > 0 ? ($this->fields[$index - 1] ?? null) : null;

        if (! is_array($current)) {
            return;
        }

        $this->fields[$index]['conditional_logic'] = app(AiFieldSuggestionEngine::class)
            ->suggestConditionalLogic($current, $previous);
    }

    protected function persist(bool $flashOnError = true): void
    {
        $user = Auth::user();

        if (! $user || (! Gate::forUser($user)->allows('canEditForm', $this->team) && ! $this->form->editableBy($user))) {
            abort(403);
        }

        $validated = $this->validate([
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'settings.confirmation_message' => ['nullable', 'string', 'max:1000'],
            'settings.limit_responses' => ['nullable', 'integer', 'min:1'],
            'settings.open_at' => ['nullable', 'date'],
            'settings.close_at' => ['nullable', 'date', 'after_or_equal:settings.open_at'],
            'settings.webhook_url' => ['nullable', 'url', 'max:2048', new SafeWebhookUrl],
            'settings.slack_webhook_url' => ['nullable', 'url', 'max:2048', new SafeWebhookUrl],
            'settings.zapier_webhook_url' => ['nullable', 'url', 'max:2048', new SafeWebhookUrl],
            'settings.make_webhook_url' => ['nullable', 'url', 'max:2048', new SafeWebhookUrl],
            'settings.theme' => ['nullable', 'in:light,dark,brand'],
            'settings.brand_color' => ['nullable', 'regex:/^#?[0-9A-Fa-f]{6}$/'],
            'settings.custom_css' => ['nullable', 'string', 'max:8000'],
            'settings.retention_days' => ['nullable', 'integer', 'min:1', 'max:3650'],
            'settings.consent_required' => ['boolean'],
            'settings.consent_label' => ['nullable', 'string', 'max:255'],
            'settings.quiz_enabled' => ['boolean'],
            'settings.quiz_answer_key_json' => ['nullable', 'string', 'max:10000'],
            'settings.conversational_mode' => ['boolean'],
            'settings.crm_provider' => ['nullable', 'in:none,hubspot,pipedrive,generic'],
            'settings.crm_webhook_url' => ['nullable', 'url', 'max:2048', new SafeWebhookUrl],
            'fields' => ['array'],
            'fields.*.type' => ['required', 'string'],
            'fields.*.label' => ['required', 'string', 'max:255'],
            'fields.*.placeholder' => ['nullable', 'string', 'max:255'],
            'fields.*.options' => ['nullable', 'string'],
            'fields.*.required' => ['boolean'],
            'fields.*.helper_text' => ['nullable', 'string', 'max:500'],
            'fields.*.conditional_logic' => ['nullable', 'string', 'max:1000'],
            'fields.*.visibility_rule.trigger_field_key' => ['nullable', 'string'],
            'fields.*.visibility_rule.operator' => ['nullable', 'in:equals,not_equals,contains'],
            'fields.*.visibility_rule.value' => ['nullable', 'string', 'max:255'],
        ]);

        $formData = [
            'title' => $validated['title'],
            'description' => $validated['description'] ?? null,
            'logo_path' => $this->form->logo_path,
            'settings' => [
                'confirmation_message' => $validated['settings']['confirmation_message'] ?? 'Thanks for your submission.',
                'limit_responses' => $validated['settings']['limit_responses'] ?? null,
                'open_at' => $validated['settings']['open_at'] ?? null,
                'close_at' => $validated['settings']['close_at'] ?? null,
                'theme' => $validated['settings']['theme'] ?? 'light',
                'brand_color' => $validated['settings']['brand_color'] ?? '#4f46e5',
                'custom_css' => $this->sanitizeCustomCss((string) ($validated['settings']['custom_css'] ?? '')),
                'retention_days' => $validated['settings']['retention_days'] ?? null,
                'consent_required' => (bool) ($validated['settings']['consent_required'] ?? false),
                'consent_label' => $validated['settings']['consent_label'] ?? 'I consent to processing my submitted data.',
                'quiz_enabled' => (bool) ($validated['settings']['quiz_enabled'] ?? false),
                'quiz_answer_key' => $this->parseAnswerKey((string) ($validated['settings']['quiz_answer_key_json'] ?? '')),
                'conversational_mode' => (bool) ($validated['settings']['conversational_mode'] ?? false),
                'crm_provider' => $validated['settings']['crm_provider'] ?? 'none',
            ],
        ];

        if ($this->logoUpload) {
            $formData['logo_path'] = $this->logoUpload->store('form-logos', config('dotforms.forms.upload_disk', 'public'));
        }

        // One atomic write: a visitor (or a failure) must never see or leave
        // a half-saved form. Cache invalidation is held back and applied once
        // after the commit instead of once per saved field.
        PublicFormCache::deferring(fn () => DB::transaction(function () use ($formData, $validated): void {
            $this->form->fill($formData);
            $this->form->save();

            $this->syncIntegrations($validated['settings']);

            $this->form->fields()->delete();

            foreach ($validated['fields'] as $index => $field) {
                $options = [];

                if (in_array($field['type'], ['select', 'radio', 'checkbox'], true)) {
                    $options = collect(explode(',', (string) ($field['options'] ?? '')))
                        ->map(fn (string $option) => trim($option))
                        ->filter()
                        ->values()
                        ->all();
                }

                $rules = [];
                if (! empty($field['required'])) {
                    $rules[] = 'required';
                }
                if ($field['type'] === 'email') {
                    $rules[] = 'email';
                }
                if ($field['type'] === 'number') {
                    $rules[] = 'numeric';
                }
                if ($field['type'] === 'date') {
                    $rules[] = 'date';
                }

                // Every field is deleted and recreated on each save, so a DB id
                // is never stable enough for a visibility rule to reference --
                // it's keyed by this stable builder-assigned UUID instead
                // (also what makes a rule survive a version revert for free:
                // the snapshot carries the same key-based options blob).
                $key = (string) ($this->fields[$index]['key'] ?? Str::uuid());
                $rule = $field['visibility_rule'] ?? null;
                $triggerKey = is_array($rule) ? ($rule['trigger_field_key'] ?? null) : null;

                $this->form->fields()->create([
                    'type' => $field['type'],
                    'label' => $field['label'],
                    'placeholder' => $field['placeholder'] ?: null,
                    'options' => [
                        'choices' => $options,
                        'helper_text' => $field['helper_text'] ?? null,
                        'conditional_logic' => $field['conditional_logic'] ?? null,
                        'field_key' => $key,
                        'visibility_rule' => $triggerKey ? [
                            'trigger_field_key' => $triggerKey,
                            'operator' => $rule['operator'] ?? 'equals',
                            'value' => $rule['value'] ?? '',
                        ] : null,
                    ],
                    'validation_rules' => $rules,
                    'order' => $index + 1,
                ]);
            }

            $this->form->refresh();
            $this->createVersionSnapshot();
        }));
        $this->hydrateState();
        $this->logoUpload = null;

        // ->toOthers(): the saving client already has what it just wrote;
        // this only needs to reach other tabs with this form's builder
        // open, so they know to reload rather than have their own next
        // autosave silently clobber it.
        broadcast(new FormBuilderUpdated($this->form->id, (int) $user->id, (string) $user->name))->toOthers();

        if ($flashOnError) {
            session()->flash('status', 'Changes saved.');
        }
    }

    /**
     * @param  array<string, mixed>  $settings
     */
    protected function syncIntegrations(array $settings): void
    {
        $slots = [
            'webhook_url' => 'webhook',
            'slack_webhook_url' => 'slack',
            'zapier_webhook_url' => 'zapier',
            'make_webhook_url' => 'make',
            'crm_webhook_url' => 'crm',
        ];

        foreach ($slots as $settingsKey => $type) {
            $url = trim((string) ($settings[$settingsKey] ?? ''));
            $existing = $this->form->integrations()->where('type', $type)->first();

            if ($url === '') {
                $existing?->delete();

                continue;
            }

            if ($existing && $existing->url === $url && $existing->status === 'active') {
                // Unchanged active URL -- no-op, does not re-trigger review.
                continue;
            }

            if ($existing) {
                $existing->update([
                    'url' => $url,
                    'status' => 'pending_approval',
                    'rejected_reason' => null,
                    'reviewed_by' => null,
                    'reviewed_at' => null,
                ]);
            } else {
                FormIntegration::create([
                    'form_id' => $this->form->id,
                    'type' => $type,
                    'url' => $url,
                    'status' => 'pending_approval',
                    'proposed_by' => auth()->id(),
                ]);
            }
        }
    }

    protected function hydrateState(): void
    {
        $this->title = $this->form->title;
        $this->description = $this->form->description;
        $this->settings = array_merge([
            'confirmation_message' => 'Thanks for your submission.',
            'limit_responses' => null,
            'open_at' => null,
            'close_at' => null,
            'webhook_url' => null,
            'slack_webhook_url' => null,
            'zapier_webhook_url' => null,
            'make_webhook_url' => null,
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
        ], $this->form->settings ?? []);

        foreach ($this->form->integrations as $integration) {
            $key = match ($integration->type) {
                'webhook' => 'webhook_url',
                'slack' => 'slack_webhook_url',
                'zapier' => 'zapier_webhook_url',
                'make' => 'make_webhook_url',
                'crm' => 'crm_webhook_url',
                default => null,
            };

            if ($key !== null) {
                $this->settings[$key] = $integration->url;
            }
        }

        $this->settings['quiz_answer_key_json'] = json_encode($this->settings['quiz_answer_key'] ?? [], JSON_PRETTY_PRINT);

        $this->formRoles = $this->form
            ->userRoles()
            ->with('user')
            ->get()
            ->map(fn (FormUserRole $role) => [
                'user_id' => $role->user_id,
                'user_name' => $role->user?->name,
                'role' => $role->role,
            ])
            ->values()
            ->all();

        $this->versions = $this->form
            ->versions()
            ->latest('version_number')
            ->take(10)
            ->get(['id', 'version_number', 'created_at'])
            ->map(fn (FormVersion $version) => [
                'id' => $version->id,
                'version_number' => $version->version_number,
                'created_at' => optional($version->created_at)?->toDateTimeString(),
            ])
            ->all();

        $this->fields = $this->form->fields()
            ->orderBy('order')
            ->get()
            ->map(function ($field): array {
                $choices = $field->options['choices'] ?? [];
                $rule = $field->options['visibility_rule'] ?? null;

                return [
                    'id' => $field->id,
                    // Falls back to a fresh key only for fields saved
                    // before this feature existed -- every field saved
                    // from here on always has one (see persist()).
                    'key' => (string) ($field->options['field_key'] ?? Str::uuid()),
                    'type' => $field->type,
                    'label' => $field->label,
                    'placeholder' => $field->placeholder,
                    'options' => is_array($choices) ? implode(', ', $choices) : '',
                    'required' => in_array('required', $field->validation_rules ?? [], true),
                    'helper_text' => $field->options['helper_text'] ?? null,
                    'conditional_logic' => $field->options['conditional_logic'] ?? null,
                    'visibility_rule' => [
                        'trigger_field_key' => is_array($rule) ? ($rule['trigger_field_key'] ?? null) : null,
                        'operator' => is_array($rule) ? ($rule['operator'] ?? 'equals') : 'equals',
                        'value' => is_array($rule) ? ($rule['value'] ?? '') : '',
                    ],
                ];
            })
            ->toArray();
    }

    public function getIntegrationsProperty(): Collection
    {
        return $this->form->integrations()->get()->keyBy('type');
    }

    public function approveIntegration(string $type): void
    {
        abort_unless($this->form->isApprover(Auth::user()), 403);

        $integration = $this->form->integrations()->where('type', $type)->first();

        if (! $integration || $integration->status !== 'pending_approval') {
            return;
        }

        $integration->update([
            'status' => 'active',
            'reviewed_by' => auth()->id(),
            'reviewed_at' => now(),
        ]);
    }

    public function rejectIntegration(string $type, string $reason): void
    {
        abort_unless($this->form->isApprover(Auth::user()), 403);

        if (trim($reason) === '') {
            throw ValidationException::withMessages([
                'integrationRejectReason' => 'A rejection reason is required.',
            ]);
        }

        $integration = $this->form->integrations()->where('type', $type)->first();

        if (! $integration || $integration->status !== 'pending_approval') {
            return;
        }

        $integration->update([
            'status' => 'rejected',
            'rejected_reason' => $reason,
            'reviewed_by' => auth()->id(),
            'reviewed_at' => now(),
        ]);
    }

    public function promptRejectIntegration(string $type): void
    {
        $this->rejectingIntegrationType = $type;
        $this->integrationRejectReason = '';
    }

    public function cancelRejectIntegration(): void
    {
        $this->rejectingIntegrationType = null;
        $this->integrationRejectReason = '';
    }

    public function confirmRejectIntegration(): void
    {
        if (! $this->rejectingIntegrationType) {
            return;
        }

        $this->rejectIntegration($this->rejectingIntegrationType, $this->integrationRejectReason);
        $this->rejectingIntegrationType = null;
        $this->integrationRejectReason = '';
    }

    public function render()
    {
        return view('livewire.forms.builder', [
            'previewUrl' => route('forms.public', ['slug' => $this->form->slug]).'?preview=1',
            'submissionsUrl' => route('teams.forms.submissions', ['team' => $this->team, 'form' => $this->form]),
            'aiSuggestionsUrl' => route('teams.forms.ai-suggestions', ['team' => $this->team, 'form' => $this->form]),
            'aiAnalyticsUrl' => route('teams.forms.ai-analytics', ['team' => $this->team, 'form' => $this->form]),
            'logoUrl' => $this->form->logo_path ? asset('storage/'.$this->form->logo_path) : null,
            'collaborators' => $this->form->availableCollaborators(),
        ]);
    }

    protected function sanitizeCustomCss(string $css): string
    {
        $sanitized = preg_replace('/<[^>]*>/', '', $css) ?? '';
        $sanitized = preg_replace('/@import\s+[^;]+;?/i', '', $sanitized) ?? '';
        $sanitized = preg_replace('/expression\s*\([^)]*\)/i', '', $sanitized) ?? '';
        $sanitized = preg_replace('/javascript\s*:/i', '', $sanitized) ?? '';

        return trim($sanitized);
    }

    protected function parseAnswerKey(string $json): array
    {
        $trimmed = trim($json);

        if ($trimmed === '') {
            return [];
        }

        $decoded = json_decode($trimmed, true);

        return is_array($decoded) ? $decoded : [];
    }

    protected function createVersionSnapshot(): void
    {
        $nextVersion = (int) $this->form->versions()->max('version_number') + 1;

        $fields = $this->form->fields()
            ->orderBy('order')
            ->get(['type', 'label', 'placeholder', 'options', 'validation_rules', 'order'])
            ->map(fn ($field) => [
                'type' => $field->type,
                'label' => $field->label,
                'placeholder' => $field->placeholder,
                'options' => $field->options,
                'validation_rules' => $field->validation_rules,
                'order' => $field->order,
            ])
            ->values()
            ->all();

        $this->form->versions()->create([
            'user_id' => Auth::id(),
            'version_number' => $nextVersion,
            'title' => $this->form->title,
            'description' => $this->form->description,
            'settings' => $this->form->settings,
            'fields_snapshot' => $fields,
        ]);

        $this->form->update([
            'current_version' => $nextVersion,
        ]);
    }
}
