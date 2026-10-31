<div style="max-width:820px;margin:0 auto;">
    <div class="cb-pagehead">
        <div>
            <h1 class="cb-h1">Set with AI</h1>
            <p class="cb-sub">Describe the form; a complete draft master is set for you to edit.</p>
        </div>
        <a href="{{ route('teams.forms', $team) }}" class="cb-btn cb-btn--quiet">← Back to the book</a>
    </div>

    @if (session('status'))
        <p class="cb-note" role="status">{{ session('status') }}</p>
    @endif

    <div class="cb-master" style="margin:0 0 20px;">
        <div class="cb-field">
            <label class="cb-label" for="ab-describe-the-form-you-want">Describe the form you want</label>
            <textarea id="ab-describe-the-form-you-want" wire:model="prompt" rows="5" class="cb-textarea" placeholder="A leave application with employee details, leave type, dates, reason and a supporting document…"></textarea>
        </div>
        <button wire:click="generate" class="cb-btn cb-btn--primary">
            <span wire:loading.remove wire:target="generate">Generate Form Blueprint</span>
            <span wire:loading wire:target="generate">Setting the draft…</span>
        </button>
    </div>

    @if ($generated)
        <div class="cb-master">
            <div class="cb-master__head"><b>The generated master</b><span class="cb-serial">DRAFT</span></div>

            <div class="cb-field">
                <label class="cb-label" for="ab-form-title">Form Title</label>
                <input id="ab-form-title" type="text" wire:model="title" class="cb-input" />
            </div>
            <div class="cb-field">
                <label class="cb-label" for="ab-description">Description</label>
                <textarea id="ab-description" wire:model="description" rows="3" class="cb-textarea"></textarea>
            </div>

            <div style="display:flex;align-items:center;gap:10px;margin:18px 0 12px;">
                <h3 class="cb-colhead" style="margin:0;">Generated fields</h3>
                <button wire:click="addField" class="cb-btn cb-btn--sm" style="margin-left:auto;">+ Add Field</button>
            </div>

            <div style="display:grid;gap:16px;">
                @foreach ($fields as $index => $field)
                    <article class="cb-fieldbox">
                        <span class="cb-fieldbox__no">{{ str_pad((string) ($index + 1), 2, '0', STR_PAD_LEFT) }}</span>
                        <div style="display:grid;grid-template-columns:minmax(0,1fr) 170px;gap:12px;margin-top:4px;">
                            <div>
                                <label class="cb-label" for="ab-label-{{ $index }}">Label</label>
                                <input id="ab-label-{{ $index }}" type="text" wire:model="fields.{{ $index }}.label" class="cb-input" />
                            </div>
                            <div>
                                <label class="cb-label" for="ab-type-{{ $index }}">Type</label>
                                <select id="ab-type-{{ $index }}" wire:model="fields.{{ $index }}.type" class="cb-select">
                                    @foreach (['text', 'email', 'number', 'textarea', 'select', 'radio', 'checkbox', 'date', 'file'] as $type)
                                        <option value="{{ $type }}">{{ \Illuminate\Support\Str::headline($type) }}</option>
                                    @endforeach
                                </select>
                            </div>
                        </div>
                        <div class="cb-field" style="margin-top:12px;">
                            <label class="cb-label" for="ab-placeholder-{{ $index }}">Placeholder</label>
                            <input id="ab-placeholder-{{ $index }}" type="text" wire:model="fields.{{ $index }}.placeholder" class="cb-input" />
                        </div>
                        <div style="display:flex;align-items:center;justify-content:space-between;gap:10px;">
                            <label class="cb-check">
                                <input type="checkbox" wire:model="fields.{{ $index }}.required" />
                                <span>Mark as required</span>
                            </label>
                            <button wire:click="removeField({{ $index }})" class="cb-btn cb-btn--quiet cb-btn--sm" style="color:var(--cb-stamp-red)">Remove</button>
                        </div>
                    </article>
                @endforeach
            </div>

            <div style="margin-top:20px;">
                <button wire:click="saveForm" class="cb-btn cb-btn--primary">Save Generated Form</button>
            </div>
        </div>
    @endif
</div>
