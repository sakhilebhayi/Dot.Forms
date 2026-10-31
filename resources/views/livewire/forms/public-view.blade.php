@php
    $consentLabel = $form->settings['consent_label'] ?? 'I consent to processing my submitted data.';
    $customCss = $form->settings['custom_css'] ?? '';
    $isConversational = (bool) ($form->settings['conversational_mode'] ?? false);
    // Filtered once here (rather than skipped mid-loop) so conversational
    // mode's step index/total stay a contiguous 0..N-1 over what the
    // respondent can actually see -- a field hidden by conditional logic
    // doesn't leave a gap or count toward the total.
    $orderedFields = $form->fields->sortBy('order')
        ->filter(fn ($field) => $this->isFieldVisible($field))
        ->values();
    $logoUrl = $form->logo_path ? asset('storage/' . $form->logo_path) : null;
    $serial = 'FRM-' . str_pad((string) $form->id, 3, '0', STR_PAD_LEFT);
@endphp

@if (! empty($customCss))
    <style id="form-custom-css"></style>
    <script type="application/json" id="form-custom-css-data">{{ json_encode(['css' => $customCss]) }}</script>
    <script>
        const customCssData = JSON.parse(document.getElementById('form-custom-css-data').textContent || '{"css":""}');
        document.getElementById('form-custom-css').textContent = customCssData.css || '';
    </script>
@endif

{{-- Known limitation: x-data's `total` is only evaluated once on init, so if
     conversational_mode and conditional logic are both used together, a mid-fill
     visibility change won't retarget Alpine's step bounds until the page reloads. --}}
<div class="cb-doc" x-data="{ submitted: @entangle('submitted'), step: 0, conversational: {{ $isConversational ? 'true' : 'false' }}, total: {{ $orderedFields->count() }} }">
    @if ($logoUrl)
        <img src="{{ $logoUrl }}" alt="{{ $form->title }} logo" style="height:40px;width:auto;margin-bottom:18px;">
    @endif

    <div class="cb-doc__head">
        <h1>{{ $form->title }}</h1>
        <span class="cb-serial">{{ $serial }}</span>
    </div>
    <p class="cb-doc__meta">
        {{ $form->team?->name ?? config('app.name') }}
        @if ($isConversational && $orderedFields->count() > 0)
            · <span x-text="'question ' + (step + 1) + ' of ' + total"></span>
        @endif
    </p>

    @if ($form->description)
        <p class="cb-doc__desc">{{ $form->description }}</p>
    @endif

    @if ($submitted)
        <div class="cb-doc__done">
            <span class="cb-stamp cb-stamp--ok cb-stamp--landed">RECEIVED</span>
            <p>{{ $form->settings['confirmation_message'] ?? 'Thanks for your submission.' }}</p>

            @if ($submittedQuizScore !== null && $submittedQuizMax !== null)
                <p style="margin-top:10px;">Your Score: <strong>{{ $submittedQuizScore }} / {{ $submittedQuizMax }}</strong></p>
            @endif
        </div>
    @else
        <form wire:submit="submit">
            <input type="text" wire:model="website" style="display:none;" tabindex="-1" autocomplete="off" aria-hidden="true">

            @foreach ($orderedFields as $field)
                @php $required = in_array('required', $field->validation_rules ?? [], true); @endphp
                <div class="cb-q" x-show="!conversational || step === {{ $loop->index }}" x-cloak wire:key="q-{{ $field->id }}">
                    <span class="cb-q__no">{{ str_pad((string) ($loop->index + 1), 2, '0', STR_PAD_LEFT) }}</span>
                    <label @if (! in_array($field->type, ['radio', 'checkbox'], true)) for="field-{{ $field->id }}" @endif>
                        {{ $field->label }}@if ($required) <span aria-hidden="true" style="color:#b3362a;">*</span>@endif
                    </label>

                    @if (! empty($field->options['helper_text']))
                        <p class="cb-help">{{ $field->options['helper_text'] }}</p>
                    @endif

                    @if ($field->type === 'textarea')
                        <textarea id="field-{{ $field->id }}" wire:model="answers.{{ $field->id }}" rows="4" placeholder="{{ $field->placeholder }}"></textarea>
                    @elseif ($field->type === 'select')
                        {{-- .live: other fields' visibility can depend on this answer. --}}
                        <select id="field-{{ $field->id }}" wire:model.live="answers.{{ $field->id }}">
                            <option value="">Select an option</option>
                            @foreach (($field->options['choices'] ?? []) as $option)
                                <option value="{{ $option }}">{{ $option }}</option>
                            @endforeach
                        </select>
                    @elseif ($field->type === 'radio')
                        <div>
                            @foreach (($field->options['choices'] ?? []) as $option)
                                <label class="cb-choice">
                                    <input type="radio" wire:model.live="answers.{{ $field->id }}" value="{{ $option }}" name="field-{{ $field->id }}">
                                    <span>{{ $option }}</span>
                                </label>
                            @endforeach
                        </div>
                    @elseif ($field->type === 'checkbox')
                        <label class="cb-choice">
                            <input type="checkbox" wire:model.live="answers.{{ $field->id }}">
                            <span>I confirm</span>
                        </label>
                    @elseif ($field->type === 'file')
                        <input id="field-{{ $field->id }}" type="file" wire:model="uploads.{{ $field->id }}" />
                    @else
                        {{-- .blur: visibility re-evaluates without a round-trip per keystroke. --}}
                        <input id="field-{{ $field->id }}" type="{{ in_array($field->type, ['text', 'email', 'number', 'date'], true) ? $field->type : 'text' }}" wire:model.blur="answers.{{ $field->id }}" placeholder="{{ $field->placeholder }}" />
                    @endif

                    @error('answers.' . $field->id)
                        <p class="cb-error" role="alert">{{ $message }}</p>
                    @enderror
                    @error('uploads.' . $field->id)
                        <p class="cb-error" role="alert">{{ $message }}</p>
                    @enderror

                    @if ($isConversational)
                        <div style="display:flex;gap:8px;margin-top:12px;">
                            @if (! $loop->first)
                                <button type="button" class="cb-btn cb-btn--sm" @click="step = Math.max(step - 1, 0)">← Back</button>
                            @endif
                            @if (! $loop->last)
                                <button type="button" class="cb-btn cb-btn--primary cb-btn--sm" @click="step = Math.min(step + 1, total - 1)">Next →</button>
                            @endif
                        </div>
                    @endif
                </div>
            @endforeach

            @if ($form->settings['consent_required'] ?? false)
                <label class="cb-choice" style="margin:4px 0 0;">
                    <input type="checkbox" wire:model="consentAccepted">
                    <span>{{ $consentLabel }}</span>
                </label>
                @error('consentAccepted')<p class="cb-error" role="alert">{{ $message }}</p>@enderror
            @endif

            @error('website')<p class="cb-error" role="alert">{{ $message }}</p>@enderror

            <div class="cb-doc__foot" :class="{ 'hidden': conversational && step < total - 1 }" x-bind:style="conversational && step < total - 1 ? 'display:none' : ''">
                <button type="submit" class="cb-btn cb-btn--primary">Submit</button>
                <small>Your answers go to {{ $form->team?->name ?? 'the form owner' }} only.</small>
            </div>
        </form>
    @endif
</div>
