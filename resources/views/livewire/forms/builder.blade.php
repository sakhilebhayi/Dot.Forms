<div x-data="{
    dragging: null,
    collectKeys() {
        return Array.from(this.$refs.fieldList.querySelectorAll('[data-key]')).map((el) => el.dataset.key);
    }
}">
    @php $serial = 'FRM-' . str_pad((string) $form->id, 3, '0', STR_PAD_LEFT); @endphp

    <div class="cb-pagehead">
        <div>
            <h1 class="cb-h1">{{ $form->title ?: 'Untitled form' }}</h1>
            <p class="cb-sub">
                <span class="cb-serial">MASTER · {{ $serial }}@if ($form->current_version) · v{{ $form->current_version }}@endif</span>
                @if ($form->is_published) · in use @else · not yet in use @endif
            </p>
        </div>
        <a href="{{ $submissionsUrl }}" class="cb-btn cb-btn--quiet">Copies</a>
        <a href="{{ $previewUrl }}" target="_blank" class="cb-btn cb-btn--quiet">Preview</a>
        <a href="{{ $aiSuggestionsUrl }}" class="cb-btn cb-btn--quiet">AI fields</a>
        <a href="{{ $aiAnalyticsUrl }}" class="cb-btn cb-btn--quiet">AI summary</a>
        <span class="cb-serial" aria-live="polite" style="align-self:center;">
            <span wire:loading wire:target="saveDraft, publish, autoSave">SAVING…</span>
            @if ($lastSavedAt)<span wire:loading.remove wire:target="saveDraft, publish, autoSave">SAVED {{ $lastSavedAt }}</span>@endif
        </span>
        <button wire:click="saveDraft" class="cb-btn">Save Draft</button>
        <button wire:click="publish" class="cb-btn cb-btn--primary">Publish</button>
    </div>

    @if (session('status'))
        <p class="cb-note" role="status">{{ session('status') }}</p>
    @endif

    {{-- Populated live by Echo presence (script at the bottom); hidden without an Echo client. --}}
    <div id="activeEditorsBanner" class="hidden cb-note">
        Also at this master right now: <span id="activeEditorsList"></span>
    </div>
    <div id="builderUpdatedBanner" class="hidden cb-note">
        <span id="builderUpdatedMessage"></span>
        <button type="button" onclick="window.location.reload()" class="cb-btn cb-btn--sm" style="margin-left:10px;">Reload</button>
    </div>

    <div class="cb-press">
        {{-- the type tray --}}
        <div>
            @foreach (\App\Support\FieldTypes::groups() as $groupLabel => $groupTypes)
                <h3 class="cb-colhead" @if (! $loop->first) style="margin-top:16px;" @endif>{{ $groupLabel }}</h3>
                <div class="cb-tray">
                    @foreach ($groupTypes as $type)
                        <button wire:click="addField('{{ $type }}')" type="button">
                            <span class="cb-mark">{{ \App\Support\FieldTypes::mark($type) }}</span>
                            {{ \App\Support\FieldTypes::label($type) }}
                        </button>
                    @endforeach
                </div>
            @endforeach

            @if (! empty($versions))
                <h3 class="cb-colhead" style="margin-top:22px;">Earlier settings of this master</h3>
                <div style="display:grid;gap:6px;">
                    @foreach ($versions as $version)
                        <div style="display:flex;align-items:center;gap:8px;border:1px solid var(--cb-rule);border-radius:3px;padding:7px 10px;background:var(--cb-sheet);">
                            <span class="cb-serial" style="font-size:12px;">v{{ $version['version_number'] }}</span>
                            <span class="cb-dim" style="font-size:12px;color:var(--cb-muted);">{{ $version['created_at'] }}</span>
                            <button wire:click="revertToVersion({{ $version['id'] }})" wire:confirm="Set the master back to v{{ $version['version_number'] }}? Collected copies are untouched." class="cb-btn cb-btn--quiet cb-btn--sm" style="margin-left:auto;">Restore</button>
                        </div>
                    @endforeach
                </div>
            @endif
        </div>

        {{-- the master sheet --}}
        <div class="cb-master">
            <div class="cb-master__head">
                <b style="flex:1;min-width:200px;">
                    <label class="cb-label" for="fb-form-title" style="color:var(--cb-muted);">Form Title</label>
                    <input id="fb-form-title" type="text" wire:model.live="title" class="cb-input" style="border:0;border-bottom:1.5px solid var(--cb-ink);border-radius:0;font-weight:700;font-size:17px;padding:0 2px;height:34px;background:transparent;" />
                </b>
                <span class="cb-serial">{{ $serial }}</span>
            </div>

            <div class="cb-field">
                <label class="cb-label" for="fb-description">Description</label>
                <textarea id="fb-description" wire:model.live="description" rows="2" class="cb-textarea" placeholder="What this form is for, in one or two sentences."></textarea>
            </div>

            <h3 class="cb-colhead" style="margin-top:18px;">Form Fields</h3>
            <div x-ref="fieldList" style="display:grid;gap:16px;">
                @forelse ($fields as $index => $field)
                    <article
                        class="cb-fieldbox"
                        wire:key="builder-field-{{ $field['key'] }}"
                        data-key="{{ $field['key'] }}"
                        draggable="true"
                        @dragstart="dragging = $el.dataset.key"
                        @dragover.prevent
                        @drop.prevent="
                            if (dragging && dragging !== $el.dataset.key) {
                                const items = Array.from($refs.fieldList.querySelectorAll('[data-key]'));
                                const from = items.find((x) => x.dataset.key === dragging);
                                const to = $el;
                                if (from && to) {
                                    const fromIndex = items.indexOf(from);
                                    const toIndex = items.indexOf(to);
                                    if (fromIndex < toIndex) { to.after(from); } else { to.before(from); }
                                    $wire.reorderFields(collectKeys());
                                }
                            }
                            dragging = null;
                        "
                        style="cursor:grab;"
                    >
                        <span class="cb-fieldbox__no">{{ str_pad((string) ($index + 1), 2, '0', STR_PAD_LEFT) }}</span>
                        <div class="cb-fieldbox__tools">
                            <button type="button" wire:click="moveFieldUp({{ $index }})" aria-label="Move field up">↑</button>
                            <button type="button" wire:click="moveFieldDown({{ $index }})" aria-label="Move field down">↓</button>
                            <button type="button" wire:click="duplicateField({{ $index }})" aria-label="Duplicate field">⧉</button>
                            @if ($field['type'] !== \App\Support\FieldTypes::PAGE_BREAK)
                                <button type="button" wire:click="openFieldSettings({{ $index }})" aria-label="Field settings">⋯</button>
                            @endif
                            <button type="button" class="cb-danger" wire:click="removeField({{ $index }})" aria-label="Remove field">✕</button>
                        </div>

                        @if ($field['type'] === \App\Support\FieldTypes::PAGE_BREAK)
                            <p style="margin:6px 90px 2px 0;font:600 11px var(--cb-mono);letter-spacing:.08em;color:var(--cb-muted);">⤵ PAGE BREAK — the published form starts a new page here</p>
                        @else
                        <div style="display:grid;grid-template-columns:minmax(0,1fr) 190px;gap:12px;margin-top:4px;">
                            <div>
                                <label class="cb-label" for="fb-label-{{ $index }}">Label</label>
                                <input id="fb-label-{{ $index }}" type="text" wire:model.live="fields.{{ $index }}.label" class="cb-input" />
                            </div>
                            <div>
                                <label class="cb-label" for="fb-type-{{ $index }}">Type</label>
                                <select id="fb-type-{{ $index }}" wire:model.live="fields.{{ $index }}.type" class="cb-select">
                                    @foreach ($fieldTypes as $type)
                                        <option value="{{ $type }}">{{ \App\Support\FieldTypes::label($type) }}</option>
                                    @endforeach
                                </select>
                            </div>
                        </div>

                        @if ($field['type'] === 'hidden')
                            <div class="cb-field" style="margin-top:12px;">
                                <label class="cb-label" for="fb-default-value-{{ $index }}">Value recorded with every copy</label>
                                <input id="fb-default-value-{{ $index }}" type="text" wire:model.live="fields.{{ $index }}.default_value" class="cb-input" placeholder="e.g. campaign-november" />
                            </div>
                        @else
                            <div class="cb-field" style="margin-top:12px;">
                                <label class="cb-label" for="fb-placeholder-{{ $index }}">Placeholder</label>
                                <input id="fb-placeholder-{{ $index }}" type="text" wire:model.live="fields.{{ $index }}.placeholder" class="cb-input" />
                            </div>
                        @endif

                        @if (\App\Support\FieldTypes::usesChoices($field['type']))
                            <div class="cb-field">
                                <label class="cb-label" for="fb-options-comma-separated-{{ $index }}">Options (comma-separated)</label>
                                <input id="fb-options-comma-separated-{{ $index }}" type="text" wire:model.live="fields.{{ $index }}.options" class="cb-input" />
                            </div>
                        @endif

                        @if ($field['type'] !== 'hidden')
                            <label class="cb-check">
                                <input type="checkbox" wire:model.live="fields.{{ $index }}.required" />
                                <span>Mark as required</span>
                            </label>
                        @endif

                        @if (! empty($field['visibility_rule']['trigger_field_key']))
                            <p class="cb-hint">Shown only when another answer matches — set under ⋯.</p>
                        @endif
                        @endif
                    </article>
                @empty
                    <div class="cb-empty">
                        <h2>A blank master</h2>
                        <p>Add a field from the type tray to start setting this form.</p>
                    </div>
                @endforelse
            </div>
        </div>

        {{-- settings --}}
        <div class="cb-props">
            <h3 class="cb-colhead">Form settings</h3>

            <details open>
                <summary>After submitting</summary>
                <div>
                    <div class="cb-field">
                        <label class="cb-label" for="fb-confirmation-message">Confirmation Message</label>
                        <textarea id="fb-confirmation-message" wire:model.live="settings.confirmation_message" rows="2" class="cb-textarea"></textarea>
                    </div>
                </div>
            </details>

            <details>
                <summary>Appearance</summary>
                <div>
                    <div class="cb-field">
                        <label class="cb-label" for="fb-theme">Theme</label>
                        <select id="fb-theme" wire:model.live="settings.theme" class="cb-select">
                            <option value="light">Light</option>
                            <option value="dark">Dark</option>
                            <option value="brand">Brand</option>
                        </select>
                    </div>
                    <div class="cb-field">
                        <label class="cb-label" for="fb-brand-color">Brand Color</label>
                        <input id="fb-brand-color" type="text" wire:model.live="settings.brand_color" class="cb-input" placeholder="#4f46e5" />
                    </div>
                    <div class="cb-field">
                        <label class="cb-label" for="fb-logo">Logo</label>
                        <input id="fb-logo" type="file" wire:model="logoUpload" accept="image/*" class="cb-input" style="padding:6px 10px;height:auto;" />
                        @if ($logoUrl)
                            <img src="{{ $logoUrl }}" alt="Form logo" style="margin-top:8px;height:40px;width:auto;">
                        @endif
                    </div>
                    <div class="cb-field">
                        <label class="cb-label" for="fb-custom-css">Custom CSS</label>
                        <textarea id="fb-custom-css" wire:model.live="settings.custom_css" rows="3" class="cb-textarea" style="font-family:var(--cb-mono);font-size:12px;" placeholder=".form-card { border-radius: 12px; }"></textarea>
                    </div>
                </div>
            </details>

            <details>
                <summary>Who may submit, and when</summary>
                <div>
                    <div class="cb-field">
                        <label class="cb-label" for="fb-response-limit">Response Limit</label>
                        <input id="fb-response-limit" type="number" min="1" wire:model.live="settings.limit_responses" class="cb-input" />
                    </div>
                    <div class="cb-field">
                        <label class="cb-label" for="fb-opens-at">Opens At</label>
                        <input id="fb-opens-at" type="datetime-local" wire:model.live="settings.open_at" class="cb-input" />
                    </div>
                    <div class="cb-field">
                        <label class="cb-label" for="fb-closes-at">Closes At</label>
                        <input id="fb-closes-at" type="datetime-local" wire:model.live="settings.close_at" class="cb-input" />
                    </div>
                    <label class="cb-check" style="margin:0 0 10px;">
                        <input type="checkbox" wire:model.live="settings.conversational_mode" />
                        <span>One question at a time</span>
                    </label>
                </div>
            </details>

            <details>
                <summary>Consent and retention</summary>
                <div>
                    <label class="cb-check" style="margin:0 0 10px;">
                        <input type="checkbox" wire:model.live="settings.consent_required" />
                        <span>Require consent</span>
                    </label>
                    @if ($settings['consent_required'] ?? false)
                        <div class="cb-field">
                            <label class="cb-label" for="fb-consent-label">Consent Label</label>
                            <input id="fb-consent-label" type="text" wire:model.live="settings.consent_label" class="cb-input" />
                        </div>
                    @endif
                    <div class="cb-field">
                        <label class="cb-label" for="fb-data-retention-days">Data Retention (days)</label>
                        <input id="fb-data-retention-days" type="number" min="1" wire:model.live="settings.retention_days" class="cb-input" />
                    </div>
                </div>
            </details>

            <details>
                <summary>Quiz</summary>
                <div>
                    <label class="cb-check" style="margin:0 0 10px;">
                        <input type="checkbox" wire:model.live="settings.quiz_enabled" />
                        <span>Enable Quiz Mode</span>
                    </label>
                    @if ($settings['quiz_enabled'] ?? false)
                        <div class="cb-field">
                            <label class="cb-label" for="fb-answer-key-json">Answer Key (JSON)</label>
                            <textarea id="fb-answer-key-json" wire:model.live="settings.quiz_answer_key_json" rows="3" class="cb-textarea" style="font-family:var(--cb-mono);font-size:12px;" placeholder='{"1":"Option A","2":"42"}'></textarea>
                        </div>
                    @endif
                </div>
            </details>

            <details>
                <summary>Team template</summary>
                <div>
                    <div class="cb-field">
                        <label class="cb-label" for="fb-template-name">Save this master as a reusable template</label>
                        <input id="fb-template-name" type="text" wire:model="templateName" class="cb-input" placeholder="e.g. Standard incident report" />
                        @error('templateName')<p class="cb-error" role="alert">{{ $message }}</p>@enderror
                    </div>
                    <button type="button" class="cb-btn cb-btn--sm" wire:click="saveAsTemplate">Save as a team template</button>
                </div>
            </details>

            @php $isApprover = $this->form->isApprover(auth()->user()); @endphp

            <details>
                <summary>Integrations</summary>
                <div>
                    <div class="cb-field">
                        <label class="cb-label" for="fb-crm-provider">CRM Provider</label>
                        <select id="fb-crm-provider" wire:model.live="settings.crm_provider" class="cb-select">
                            <option value="none">None</option>
                            <option value="hubspot">HubSpot</option>
                            <option value="pipedrive">Pipedrive</option>
                            <option value="generic">Generic</option>
                        </select>
                    </div>

                    @if (($settings['crm_provider'] ?? 'none') !== 'none')
                        <div class="cb-field">
                            <label class="cb-label" for="fb-crm-webhook-url">CRM Webhook URL</label>
                            <input id="fb-crm-webhook-url" type="url" wire:model.live="settings.crm_webhook_url" class="cb-input" />
                            @php $integration = $this->integrations->get('crm'); @endphp
                            @if ($integration)
                                <div style="margin-top:6px;font-size:12px;">
                                    @if ($integration->status === 'active')
                                        <span class="cb-stamp cb-stamp--ok" style="font-size:10px;">ACTIVE</span>
                                    @elseif ($integration->status === 'rejected')
                                        <span class="cb-stamp" style="font-size:10px;">REJECTED</span> <span class="cb-hint">{{ $integration->rejected_reason }}</span>
                                    @elseif ($isApprover)
                                        <span class="cb-stamp cb-stamp--muted" style="font-size:10px;">AWAITING</span>
                                        @if ($rejectingIntegrationType === 'crm')
                                            <div style="display:flex;gap:6px;margin-top:6px;">
                                                <input aria-label="Reason for rejecting" type="text" wire:model="integrationRejectReason" placeholder="Reason for rejecting" class="cb-input" style="height:30px;font-size:12px;" />
                                                <button type="button" wire:click="confirmRejectIntegration" class="cb-btn cb-btn--sm" style="color:var(--cb-stamp-red)">Confirm Reject</button>
                                                <button type="button" wire:click="cancelRejectIntegration" class="cb-btn cb-btn--quiet cb-btn--sm">Cancel</button>
                                            </div>
                                        @else
                                            <div style="display:flex;gap:6px;margin-top:6px;">
                                                <button type="button" wire:click="approveIntegration('crm')" class="cb-btn cb-btn--sm">Approve</button>
                                                <button type="button" wire:click="promptRejectIntegration('crm')" class="cb-btn cb-btn--quiet cb-btn--sm" style="color:var(--cb-stamp-red)">Reject</button>
                                            </div>
                                        @endif
                                    @else
                                        <span class="cb-stamp cb-stamp--muted" style="font-size:10px;">AWAITING</span> <span class="cb-hint">awaiting the form owner</span>
                                    @endif
                                </div>
                            @endif
                        </div>
                    @endif

                    @php
                        $integrationSlots = [
                            'webhook' => ['label' => 'Webhook', 'field' => 'webhook_url', 'placeholder' => 'https://example.com/webhook'],
                            'slack' => ['label' => 'Slack Webhook', 'field' => 'slack_webhook_url', 'placeholder' => 'https://hooks.slack.com/...'],
                            'zapier' => ['label' => 'Zapier Webhook', 'field' => 'zapier_webhook_url', 'placeholder' => 'https://hooks.zapier.com/...'],
                            'make' => ['label' => 'Make Webhook', 'field' => 'make_webhook_url', 'placeholder' => 'https://hook.make.com/...'],
                        ];
                    @endphp

                    @foreach ($integrationSlots as $type => $slot)
                        <div class="cb-field">
                            <label class="cb-label" for="fb-{{ $slot['field'] }}">{{ $slot['label'] }}</label>
                            <input id="fb-{{ $slot['field'] }}" type="url" wire:model.live="settings.{{ $slot['field'] }}" class="cb-input" placeholder="{{ $slot['placeholder'] }}" />
                            @php $integration = $this->integrations->get($type); @endphp
                            @if ($integration)
                                <div style="margin-top:6px;font-size:12px;">
                                    @if ($integration->status === 'active')
                                        <span class="cb-stamp cb-stamp--ok" style="font-size:10px;">ACTIVE</span>
                                    @elseif ($integration->status === 'rejected')
                                        <span class="cb-stamp" style="font-size:10px;">REJECTED</span> <span class="cb-hint">{{ $integration->rejected_reason }}</span>
                                    @elseif ($isApprover)
                                        <span class="cb-stamp cb-stamp--muted" style="font-size:10px;">AWAITING</span>
                                        @if ($rejectingIntegrationType === $type)
                                            <div style="display:flex;gap:6px;margin-top:6px;">
                                                <input aria-label="Reason for rejecting" type="text" wire:model="integrationRejectReason" placeholder="Reason for rejecting" class="cb-input" style="height:30px;font-size:12px;" />
                                                <button type="button" wire:click="confirmRejectIntegration" class="cb-btn cb-btn--sm" style="color:var(--cb-stamp-red)">Confirm Reject</button>
                                                <button type="button" wire:click="cancelRejectIntegration" class="cb-btn cb-btn--quiet cb-btn--sm">Cancel</button>
                                            </div>
                                        @else
                                            <div style="display:flex;gap:6px;margin-top:6px;">
                                                <button type="button" wire:click="approveIntegration('{{ $type }}')" class="cb-btn cb-btn--sm">Approve</button>
                                                <button type="button" wire:click="promptRejectIntegration('{{ $type }}')" class="cb-btn cb-btn--quiet cb-btn--sm" style="color:var(--cb-stamp-red)">Reject</button>
                                            </div>
                                        @endif
                                    @else
                                        <span class="cb-stamp cb-stamp--muted" style="font-size:10px;">AWAITING</span> <span class="cb-hint">awaiting the form owner</span>
                                    @endif
                                </div>
                            @endif
                        </div>
                    @endforeach
                </div>
            </details>
        </div>
    </div>

    <div wire:poll.30s="autoSave" class="hidden" aria-hidden="true"></div>

    {{-- Field settings modal --}}
    @if ($showFieldSettingsModal && $editingFieldIndex !== null)
        <div style="position:fixed;inset:0;background:rgba(25,26,28,.45);display:flex;align-items:center;justify-content:center;z-index:50;padding:16px;">
            <div style="background:var(--cb-sheet);border:1px solid var(--cb-boxline);box-shadow:4px 4px 0 var(--cb-shadow);border-radius:3px;padding:22px;max-width:520px;width:100%;">
                <h3 style="font-size:16px;font-weight:700;margin:0 0 16px;">Field settings</h3>

                <div class="cb-field">
                    <label class="cb-label" for="fb-helper-text-{{ $editingFieldIndex }}">Helper Text</label>
                    <textarea id="fb-helper-text-{{ $editingFieldIndex }}" wire:model.live="fields.{{ $editingFieldIndex }}.helper_text" rows="2" class="cb-textarea"></textarea>
                </div>

                <div class="cb-field">
                    <span class="cb-label">Show this field only when</span>
                    @php
                        $otherFields = collect($fields)->filter(
                            fn ($f, $i) => $i !== $editingFieldIndex
                                && ! empty($f['key'])
                                && \App\Support\FieldTypes::collectsAnswer($f['type'] ?? 'text')
                                && ($f['type'] ?? '') !== 'hidden'
                        )->values();
                        $editRules = $fields[$editingFieldIndex]['visibility']['rules'] ?? [];
                        $operatorWords = [
                            'equals' => 'equals', 'not_equals' => 'does not equal', 'contains' => 'contains',
                            'empty' => 'is empty', 'not_empty' => 'is answered',
                            'greater_than' => 'is more than', 'less_than' => 'is less than',
                        ];
                    @endphp
                    <div style="display:grid;gap:8px;">
                        @if (count($editRules) > 1)
                            <select aria-label="How the rules combine" wire:model.live="fields.{{ $editingFieldIndex }}.visibility.logic" class="cb-select">
                                <option value="all">every rule below matches</option>
                                <option value="any">any rule below matches</option>
                            </select>
                        @endif

                        @foreach ($editRules as $ruleIndex => $editRule)
                            <div style="display:grid;grid-template-columns:minmax(0,1.2fr) minmax(0,1fr) minmax(0,1fr) 30px;gap:6px;align-items:center;" wire:key="vr-{{ $editingFieldIndex }}-{{ $ruleIndex }}">
                                <select aria-label="The field the rule watches" wire:model.live="fields.{{ $editingFieldIndex }}.visibility.rules.{{ $ruleIndex }}.trigger_field_key" class="cb-select">
                                    <option value="">Choose a field…</option>
                                    @foreach ($otherFields as $other)
                                        <option value="{{ $other['key'] }}">{{ $other['label'] ?: 'Untitled field' }}</option>
                                    @endforeach
                                </select>
                                <select aria-label="How the answer is compared" wire:model.live="fields.{{ $editingFieldIndex }}.visibility.rules.{{ $ruleIndex }}.operator" class="cb-select">
                                    @foreach ($operatorWords as $op => $word)
                                        <option value="{{ $op }}">{{ $word }}</option>
                                    @endforeach
                                </select>
                                @if (! in_array($editRule['operator'] ?? 'equals', \App\Services\FormFieldVisibilityEvaluator::VALUELESS, true))
                                    <input aria-label="Value the answer is compared with" type="text" wire:model.live="fields.{{ $editingFieldIndex }}.visibility.rules.{{ $ruleIndex }}.value" placeholder="value" class="cb-input">
                                @else
                                    <span class="cb-hint" style="margin:0;">—</span>
                                @endif
                                <button type="button" class="cb-btn cb-btn--quiet cb-btn--sm" style="min-width:30px;padding:0;color:var(--cb-stamp-red)" wire:click="removeVisibilityRule({{ $editingFieldIndex }}, {{ $ruleIndex }})" aria-label="Remove this rule">✕</button>
                            </div>
                        @endforeach

                        <div>
                            <button type="button" class="cb-btn cb-btn--quiet cb-btn--sm" wire:click="addVisibilityRule({{ $editingFieldIndex }})">+ Add a rule</button>
                        </div>

                        @if ($editRules !== [])
                            <p class="cb-hint">While the rules do not match, this field is hidden from respondents, is never required, and its answer is never recorded.</p>
                        @else
                            <p class="cb-hint">No rules: the field always shows.</p>
                        @endif
                    </div>
                </div>

                <div style="display:flex;justify-content:flex-end;border-top:1px solid var(--cb-rule);padding-top:14px;">
                    <button wire:click="closeFieldSettings" class="cb-btn">Close</button>
                </div>
            </div>
        </div>
    @endif
</div>

<script>
    document.addEventListener('DOMContentLoaded', () => {
        if (!window.Echo) {
            // No Echo client (the frontend was built without VITE_REVERB_APP_KEY,
            // as in production): the builder still works, presence/live-update
            // banners just don't appear. Nothing here opens a connection or retries.
            return;
        }

        const formId = {{ $form->id }};
        const currentUserId = {{ auth()->id() ?? 'null' }};
        const banner = document.getElementById('activeEditorsBanner');
        const list = document.getElementById('activeEditorsList');
        const updatedBanner = document.getElementById('builderUpdatedBanner');
        const updatedMessage = document.getElementById('builderUpdatedMessage');

        let editors = {};

        const renderEditors = () => {
            const others = Object.values(editors).filter((e) => e.id !== currentUserId);

            if (others.length === 0) {
                banner.classList.add('hidden');

                return;
            }

            list.textContent = others.map((e) => e.name).join(', ');
            banner.classList.remove('hidden');
        };

        window.Echo.join(`form-builder.${formId}`)
            .here((members) => {
                editors = {};
                members.forEach((m) => { editors[m.id] = m; });
                renderEditors();
            })
            .joining((member) => {
                editors[member.id] = member;
                renderEditors();
            })
            .leaving((member) => {
                delete editors[member.id];
                renderEditors();
            })
            .listen('.builder.updated', (payload) => {
                if (payload.user_id === currentUserId) {
                    return;
                }

                updatedMessage.textContent = `${payload.user_name} just saved changes to this form.`;
                updatedBanner.classList.remove('hidden');
            });
    });
</script>
