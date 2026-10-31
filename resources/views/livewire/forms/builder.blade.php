<div x-data="{
    dragging: null,
    canvasW: 'desk',
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
        <button wire:click="saveDraft" class="cb-btn">{{ $form->is_published ? 'Save' : 'Save Draft' }}</button>
        @if ($form->is_published)
            <button wire:click="unpublish" wire:confirm="Take this form out of use? The public link stops serving it until you publish again." class="cb-btn cb-btn--quiet" style="color:var(--cb-stamp-red)">Unpublish</button>
        @else
            <button wire:click="publish" class="cb-btn cb-btn--primary">Publish</button>
        @endif
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

    <div class="cb-press cb-press--canvas">
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

        {{-- the living master: the document itself is the canvas (R-31) --}}
        <div>
            <div class="cb-canvasbar">
                <span class="cb-colhead" style="margin:0;">The sheet</span>
                <div class="cb-canvasbar__widths" role="group" aria-label="Canvas width">
                    <button type="button" :class="{'cb-on': canvasW === 'desk'}" @click="canvasW = 'desk'">Desk</button>
                    <button type="button" :class="{'cb-on': canvasW === 'tablet'}" @click="canvasW = 'tablet'">Tablet</button>
                    <button type="button" :class="{'cb-on': canvasW === 'phone'}" @click="canvasW = 'phone'">Phone</button>
                </div>
            </div>
            <div class="cb-canvaswell" @keydown.escape.window="$wire.deselectField()">
                <div class="cb-doc cb-doc--{{ $settings['theme_preset'] ?? 'carbon' }} cb-doc--canvas" :style="{maxWidth: {desk: '660px', tablet: '560px', phone: '375px'}[canvasW]}">
                    @if (($settings['presentation'] ?? 'document') === 'landing')
                        <div class="cb-coverchip">
                            @if ($coverUrl)
                                <img src="{{ $coverUrl }}" alt="">
                            @endif
                            <span>COVER — respondents see a cover page first. Open Preview to see it.</span>
                        </div>
                    @endif
                    <div class="cb-doc__head">
                        <label class="cb-label" for="fb-form-title" style="position:absolute;left:-9999px;">Form Title</label>
                        <input id="fb-form-title" type="text" wire:model.live="title" placeholder="Form Title"
                            style="flex:1;min-width:160px;border:0;border-bottom:2px solid transparent;background:transparent;font:700 21px var(--cb-font);color:var(--cbd-ink);padding:0;" />
                        <span class="cb-serial">{{ $serial }}</span>
                    </div>
                    <textarea id="fb-description" wire:model.live="description" rows="2" placeholder="What this form is for, in one or two sentences." aria-label="Description"
                        style="width:100%;border:0;background:transparent;font:inherit;color:var(--cbd-muted);resize:vertical;padding:0;margin:0 0 16px;"></textarea>

                    <div x-ref="fieldList">
                        @php $qNo = 0; @endphp
                        @forelse ($fields as $index => $field)
                            <button type="button" class="cb-insert" wire:click="insertFieldAt({{ $index }})" aria-label="Insert an element here"><span>+</span></button>
                            <div
                                class="cb-el @if ($editingFieldIndex === $index) cb-el--sel @endif"
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
                                wire:click="selectField({{ $index }})"
                                role="button" tabindex="0" @keydown.enter.self="$wire.selectField({{ $index }})"
                                aria-label="Edit: {{ $field['label'] ?: \App\Support\FieldTypes::label($field['type']) }}"
                            >
                                @if ($editingFieldIndex === $index)
                                    <div class="cb-el__tools" role="toolbar" aria-label="Element actions">
                                        <button type="button" wire:click.stop="moveFieldUp({{ $index }})" aria-label="Move up" @if ($index === 0) disabled @endif>↑</button>
                                        <button type="button" wire:click.stop="moveFieldDown({{ $index }})" aria-label="Move down" @if ($index === count($fields) - 1) disabled @endif>↓</button>
                                        <button type="button" wire:click.stop="duplicateField({{ $index }})" aria-label="Duplicate">⧉</button>
                                        <button type="button" class="cb-danger" wire:click.stop="removeField({{ $index }})" aria-label="Remove">✕</button>
                                    </div>
                                @endif
                                @include('livewire.forms.partials.element-canvas', ['field' => $field, 'qNo' => \App\Support\FieldTypes::collectsAnswer($field['type']) ? ++$qNo : null])
                            </div>
                        @empty
                            <div class="cb-empty">
                                <h2>A blank master</h2>
                                <p>Add an element from the type tray, or press + below.</p>
                            </div>
                        @endforelse
                        <button type="button" class="cb-insert" wire:click="insertFieldAt({{ count($fields) }})" aria-label="Add an element at the end"><span>+</span></button>
                    </div>
                </div>
            </div>
        </div>

        <div class="cb-props @if ($editingFieldIndex !== null) cb-props--editing @endif">
            @if ($editingFieldIndex !== null && isset($fields[$editingFieldIndex]))
                @php $sel = $fields[$editingFieldIndex]; @endphp
                {{-- Keyed by the element, not the index: when the selection moves or
                     shifts, every input below re-renders with that element's values
                     instead of being morphed in place with stale ones. --}}
                <div wire:key="props-{{ $sel['key'] }}">
                <div style="display:flex;align-items:baseline;gap:8px;">
                    <h3 class="cb-colhead" style="margin:0 0 10px;">Selected element</h3>
                    <button type="button" class="cb-btn cb-btn--quiet cb-btn--sm" style="margin-left:auto;" wire:click="deselectField">Done</button>
                </div>

                <div class="cb-field">
                    <label class="cb-label" for="el-type">Type</label>
                    <select id="el-type" wire:model.live="fields.{{ $editingFieldIndex }}.type" class="cb-select">
                        @foreach ($fieldTypes as $type)
                            <option value="{{ $type }}">{{ \App\Support\FieldTypes::label($type) }}</option>
                        @endforeach
                    </select>
                </div>

                @if ($sel['type'] === \App\Support\FieldTypes::PAGE_BREAK)
                    <p class="cb-hint">The published form starts a new page here. Nothing to configure.</p>
                @elseif ($sel['type'] === 'divider')
                    <p class="cb-hint">A quiet rule between elements. Nothing to configure.</p>
                @else
                    @if ($sel['type'] === 'heading')
                        <div class="cb-field">
                            <label class="cb-label" for="el-label">Heading text</label>
                            <input id="el-label" type="text" wire:model.live="fields.{{ $editingFieldIndex }}.label" class="cb-input" />
                        </div>
                    @elseif ($sel['type'] === 'text_block')
                        <div class="cb-field">
                            <label class="cb-label" for="el-body">Text</label>
                            <textarea id="el-body" rows="5" wire:model.live="fields.{{ $editingFieldIndex }}.body" class="cb-textarea" placeholder="A paragraph between the questions. Blank lines split paragraphs."></textarea>
                            @error('fields.' . $editingFieldIndex . '.body')<p class="cb-error" role="alert">{{ $message }}</p>@enderror
                        </div>
                    @elseif ($sel['type'] === 'image')
                        <div class="cb-field">
                            <label class="cb-label" for="el-image">Image</label>
                            <input id="el-image" type="file" accept="image/*" wire:model="elementImages.{{ $editingFieldIndex }}" class="cb-input" style="padding:6px 10px;height:auto;" />
                            @error('elementImages.' . $editingFieldIndex)<p class="cb-error" role="alert">{{ $message }}</p>@enderror
                            <p class="cb-hint">Stored when you save. The label below is the image's alt text; the helper text is its caption.</p>
                        </div>
                        <div class="cb-field">
                            <label class="cb-label" for="el-label">Alt text</label>
                            <input id="el-label" type="text" wire:model.live="fields.{{ $editingFieldIndex }}.label" class="cb-input" />
                        </div>
                    @elseif ($sel['type'] === 'video')
                        <div class="cb-field">
                            <label class="cb-label" for="el-video">YouTube or Vimeo link</label>
                            <input id="el-video" type="url" wire:model.live="fields.{{ $editingFieldIndex }}.video_url" class="cb-input" placeholder="https://youtu.be/…" />
                            @error('fields.' . $editingFieldIndex . '.video_url')<p class="cb-error" role="alert">{{ $message }}</p>@enderror
                        </div>
                        <div class="cb-field">
                            <label class="cb-label" for="el-label">Title shown above the video</label>
                            <input id="el-label" type="text" wire:model.live="fields.{{ $editingFieldIndex }}.label" class="cb-input" />
                        </div>
                    @else
                        <div class="cb-field">
                            <label class="cb-label" for="el-label">Label</label>
                            <input id="el-label" type="text" wire:model.live="fields.{{ $editingFieldIndex }}.label" class="cb-input" />
                        </div>
                        @if ($sel['type'] === 'hidden')
                            <div class="cb-field">
                                <label class="cb-label" for="el-default">Value recorded with every copy</label>
                                <input id="el-default" type="text" wire:model.live="fields.{{ $editingFieldIndex }}.default_value" class="cb-input" placeholder="e.g. campaign-november" />
                            </div>
                        @else
                            <div class="cb-field">
                                <label class="cb-label" for="el-placeholder">Placeholder</label>
                                <input id="el-placeholder" type="text" wire:model.live="fields.{{ $editingFieldIndex }}.placeholder" class="cb-input" />
                            </div>
                        @endif
                        @if (\App\Support\FieldTypes::usesChoices($sel['type']))
                            <div class="cb-field">
                                <label class="cb-label" for="el-options">{{ ['matrix' => 'Columns (comma-separated)', 'ranking' => 'Items to rank (comma-separated)'][$sel['type']] ?? 'Options (comma-separated)' }}</label>
                                <input id="el-options" type="text" wire:model.live="fields.{{ $editingFieldIndex }}.options" class="cb-input" />
                            </div>
                        @endif
                        @if ($sel['type'] === 'matrix')
                            <div class="cb-field">
                                <label class="cb-label" for="el-rows">Rows (comma-separated)</label>
                                <input id="el-rows" type="text" wire:model.live="fields.{{ $editingFieldIndex }}.rows" class="cb-input" placeholder="Housekeeping, PPE, Equipment" />
                            </div>
                        @endif
                        @if ($sel['type'] === 'calculated')
                            <div class="cb-field">
                                <label class="cb-label" for="el-formula">Formula</label>
                                <textarea id="el-formula" rows="2" wire:model.live="fields.{{ $editingFieldIndex }}.formula" class="cb-textarea" style="font-family:var(--cb-mono);font-size:12px;" placeholder="{quantity} * {unit-price} * 1.15"></textarea>
                                @error('fields.' . $editingFieldIndex . '.formula')<p class="cb-error" role="alert">{{ $message }}</p>@enderror
                                <p class="cb-hint">+, −, ×, ÷ and brackets over the numeric fields below. Computed when a copy is submitted.</p>
                                @php
                                    $numericRefs = collect($fields)->filter(fn ($f, $i) => $i !== $editingFieldIndex && in_array($f['type'], ['number', 'currency', 'percentage', 'rating', 'scale'], true));
                                @endphp
                                @if ($numericRefs->isNotEmpty())
                                    <div style="display:grid;gap:3px;margin-top:6px;">
                                        @foreach ($numericRefs as $ref)
                                            <span class="cb-hint" style="margin:0;"><span class="cb-serial" style="font-size:11px;">{{ '{' . $ref['key'] . '}' }}</span> = {{ $ref['label'] ?: 'Untitled' }}</span>
                                        @endforeach
                                    </div>
                                @else
                                    <p class="cb-hint">Add a number, currency, percentage, rating or scale field to reference it here.</p>
                                @endif
                            </div>
                        @endif
                        @if ($sel['type'] !== 'hidden')
                            <label class="cb-check" style="margin:0 0 12px;">
                                <input type="checkbox" wire:model.live="fields.{{ $editingFieldIndex }}.required" />
                                <span>Mark as required</span>
                            </label>
                        @endif
                    @endif

                    @if (! in_array($sel['type'], \App\Support\FieldTypes::DISPLAY_TYPES, true) || $sel['type'] === 'image')
                        <div class="cb-field">
                            <label class="cb-label" for="el-helper">{{ $sel['type'] === 'image' ? 'Caption' : 'Helper Text' }}</label>
                            <textarea id="el-helper" rows="2" wire:model.live="fields.{{ $editingFieldIndex }}.helper_text" class="cb-textarea"></textarea>
                        </div>
                    @endif

                    @if (\App\Support\FieldTypes::collectsAnswer($sel['type']))
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
                    @endif
                @endif

                <div style="display:flex;gap:6px;border-top:1px solid var(--cb-rule);padding-top:12px;margin:4px 0 16px;">
                    <button type="button" class="cb-btn cb-btn--quiet cb-btn--sm" wire:click="duplicateField({{ $editingFieldIndex }})">Duplicate</button>
                    <button type="button" class="cb-btn cb-btn--quiet cb-btn--sm" style="margin-left:auto;color:var(--cb-stamp-red)" wire:click="removeField({{ $editingFieldIndex }})">Remove</button>
                </div>
                </div>
            @else
                <p class="cb-hint" style="margin:0 0 16px;">Select an element on the sheet to edit it — or add one from the type tray.</p>
            @endif


            <h3 class="cb-colhead">Form settings</h3>

            <details open>
                <summary>Presentation</summary>
                <div>
                    <div class="cb-field">
                        <label class="cb-label" for="fb-presentation">How the form opens</label>
                        <select id="fb-presentation" wire:model.live="settings.presentation" class="cb-select">
                            <option value="document">Document — straight to the questions</option>
                            <option value="landing">Cover — a landing page first, then the document</option>
                        </select>
                    </div>
                    @if (($settings['presentation'] ?? 'document') === 'landing')
                        <div class="cb-field">
                            <label class="cb-label" for="fb-cover">Cover image</label>
                            <input id="fb-cover" type="file" wire:model="coverUpload" accept="image/*" class="cb-input" style="padding:6px 10px;height:auto;" />
                            @error('coverUpload')<p class="cb-error" role="alert">{{ $message }}</p>@enderror
                            @if ($coverUrl)
                                <img src="{{ $coverUrl }}" alt="Cover image" style="margin-top:8px;max-height:72px;width:auto;border-radius:2px;">
                                <div><button type="button" class="cb-btn cb-btn--quiet cb-btn--sm" style="margin-top:6px;" wire:click="removeCover">Remove the cover image</button></div>
                            @endif
                            <p class="cb-hint">Optional. Shown full width at the top of the cover; the title, description and a Begin button follow it.</p>
                        </div>
                    @endif
                </div>
            </details>

            <details open>
                <summary>After submitting</summary>
                <div>
                    <div class="cb-field">
                        <label class="cb-label" for="fb-completion-title">Completion heading</label>
                        <input id="fb-completion-title" type="text" wire:model.live="settings.completion_title" class="cb-input" placeholder="Thank you — we have your details" />
                    </div>
                    <div class="cb-field">
                        <label class="cb-label" for="fb-confirmation-message">Confirmation Message</label>
                        <textarea id="fb-confirmation-message" wire:model.live="settings.confirmation_message" rows="2" class="cb-textarea"></textarea>
                    </div>
                    <label class="cb-check" style="margin:0 0 10px;">
                        <input type="checkbox" wire:model.live="settings.completion_show_another" />
                        <span>Offer "Submit another response"</span>
                    </label>
                    <div class="cb-field">
                        <label class="cb-label" for="fb-redirect-url">Onward link (optional)</label>
                        <input id="fb-redirect-url" type="url" wire:model.live="settings.completion_redirect_url" class="cb-input" placeholder="https://your-site.example/thanks" />
                        <p class="cb-hint">Shown as a button on the completion screen — respondents are never redirected without choosing to.</p>
                    </div>
                    @if (filled($settings['completion_redirect_url'] ?? null))
                        <div class="cb-field">
                            <label class="cb-label" for="fb-redirect-label">Onward link label</label>
                            <input id="fb-redirect-label" type="text" wire:model.live="settings.completion_redirect_label" class="cb-input" placeholder="Continue" />
                        </div>
                    @endif
                </div>
            </details>

            <details>
                <summary>Appearance</summary>
                <div>
                    <div class="cb-field">
                        <label class="cb-label" for="fb-theme-preset">Document theme</label>
                        <select id="fb-theme-preset" wire:model.live="settings.theme_preset" class="cb-select">
                            <option value="carbon">Carbon — the paper default</option>
                            <option value="midnight">Midnight — dark paper</option>
                            <option value="letterhead">Letterhead — your colour on the masthead</option>
                            <option value="plain">Plain — no boxes, just questions</option>
                        </select>
                    </div>
                    <div class="cb-field">
                        <label class="cb-label" for="fb-doc-width">Document width</label>
                        <select id="fb-doc-width" wire:model.live="settings.doc_width" class="cb-select">
                            <option value="narrow">Narrow</option>
                            <option value="standard">Standard</option>
                            <option value="wide">Wide</option>
                        </select>
                    </div>
                    <div class="cb-field">
                        <label class="cb-label" for="fb-brand-color">Accent colour</label>
                        <input id="fb-brand-color" type="text" wire:model.live="settings.brand_color" class="cb-input" placeholder="#2b4a9e" />
                        <p class="cb-hint">Serials, focus and the masthead on the Letterhead theme. A six-digit hex colour.</p>
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
