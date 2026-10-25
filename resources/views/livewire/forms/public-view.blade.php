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
@endphp

@if (! empty($customCss))
    <style id="form-custom-css"></style>
    <script type="application/json" id="form-custom-css-data">{{ json_encode(['css' => $customCss]) }}</script>
    <script>
        const customCssData = JSON.parse(document.getElementById('form-custom-css-data').textContent || '{"css":""}');
        document.getElementById('form-custom-css').textContent = customCssData.css || '';
    </script>
@endif

<div style="min-height: 100vh; background: linear-gradient(135deg, #FAFAFA 0%, #F5F5F5 100%); padding: 40px 24px; display: flex; align-items: center; justify-content: center;">
    {{-- Known limitation: x-data's `total` is only evaluated once on init,
         so if conversational_mode and conditional logic are both used
         together, a mid-fill visibility change won't retarget Alpine's
         step bounds until the page reloads. Conditional logic itself
         (which fields render, validate, and get recorded) is unaffected
         either way -- this only touches the conversational step counter. --}}
    <div style="width: 100%; max-width: 600px; background: white; border-radius: 16px; border: 1px solid #E5E5E5; padding: 40px; box-shadow: 0 4px 20px rgba(0,0,0,.06);" x-data="{ submitted: @entangle('submitted'), step: 0, conversational: {{ $isConversational ? 'true' : 'false' }}, total: {{ $orderedFields->count() }} }">
        @if ($logoUrl)
            <img src="{{ $logoUrl }}" alt="Form logo" style="height: 40px; width: auto; margin-bottom: 20px;">
        @endif

        <h1 style="font-family: 'Sora', sans-serif; font-size: 28px; font-weight: 800; color: var(--ink); margin: 0 0 8px 0;">
            {{ $form->title }}
        </h1>

        @if ($form->description)
            <p style="font-size: 14px; color: var(--muted); margin: 12px 0 0 0;">
                {{ $form->description }}
            </p>
        @endif

        @if ($submitted)
            <div style="margin-top: 28px; padding: 16px; background: linear-gradient(135deg, #ECFDF5 0%, #F0FDF4 100%); border: 1px solid #DCFCE7; border-radius: 10px; font-size: 14px; color: #166534; display: flex; gap: 12px; align-items: flex-start;">
                <span style="font-size: 18px;">✅</span>
                <span>{{ $form->settings['confirmation_message'] ?? 'Thanks for your submission.' }}</span>
            </div>

            @if ($submittedQuizScore !== null && $submittedQuizMax !== null)
                <div style="margin-top: 14px; padding: 16px; background: linear-gradient(135deg, #F0F9FF 0%, #F0F4FF 100%); border: 1px solid #BAE6FD; border-radius: 10px; font-size: 14px; color: #0C4A6E; display: flex; gap: 12px;">
                    <span style="font-size: 18px;">🎯</span>
                    <span>Your Score: <strong>{{ $submittedQuizScore }} / {{ $submittedQuizMax }}</strong></span>
                </div>
            @endif
        @else
            <form wire:submit="submit" style="margin-top: 28px; display: flex; flex-direction: column; gap: 18px;">
                <input type="text" wire:model="website" style="display: none;" tabindex="-1" autocomplete="off" aria-hidden="true">

                @foreach ($orderedFields as $field)
                    <div style="display: flex; flex-direction: column; gap: 8px;" x-show="!conversational || step === {{ $loop->index }}" x-cloak>
                        <label style="font-size: 14px; font-weight: 600; color: var(--ink);">
                            {{ $field->label }}
                            @if (in_array('required', $field->validation_rules ?? [], true))
                                <span style="color: var(--red);">*</span>
                            @endif
                        </label>

                        @if ($field->type === 'textarea')
                            <textarea wire:model="answers.{{ $field->id }}" rows="4" placeholder="{{ $field->placeholder }}" style="width: 100%; padding: 11px 14px; border: 1px solid #E5E5E5; border-radius: 8px; font-size: 14px; font-family: 'Inter', sans-serif; resize: vertical;"></textarea>
                        @elseif ($field->type === 'select')
                            {{-- .live: other fields' visibility can depend on this
                                 answer, so a change here has to round-trip and
                                 re-evaluate which fields show. --}}
                            <select wire:model.live="answers.{{ $field->id }}" style="width: 100%; padding: 11px 14px; border: 1px solid #E5E5E5; border-radius: 8px; font-size: 14px; font-family: 'Inter', sans-serif;">
                                <option value="">Select an option</option>
                                @foreach (($field->options['choices'] ?? []) as $option)
                                    <option value="{{ $option }}">{{ $option }}</option>
                                @endforeach
                            </select>
                        @elseif ($field->type === 'radio')
                            <div style="display: flex; flex-direction: column; gap: 10px;">
                                @foreach (($field->options['choices'] ?? []) as $option)
                                    <label style="display: flex; align-items: center; gap: 10px; font-size: 14px; color: var(--ink); cursor: pointer;">
                                        <input type="radio" wire:model.live="answers.{{ $field->id }}" value="{{ $option }}" style="width: 18px; height: 18px; cursor: pointer; accent-color: var(--yellow);">
                                        <span>{{ $option }}</span>
                                    </label>
                                @endforeach
                            </div>
                        @elseif ($field->type === 'checkbox')
                            <label style="display: flex; align-items: center; gap: 10px; font-size: 14px; color: var(--ink); cursor: pointer;">
                                <input type="checkbox" wire:model.live="answers.{{ $field->id }}" style="width: 18px; height: 18px; cursor: pointer; accent-color: var(--yellow);">
                                <span>I confirm</span>
                            </label>
                        @elseif ($field->type === 'file')
                            <input type="file" wire:model="uploads.{{ $field->id }}" style="width: 100%; padding: 11px 14px; border: 1px solid #E5E5E5; border-radius: 8px; font-size: 13px;" />
                        @else
                            {{-- .blur rather than .live: a text-type trigger still
                                 re-evaluates visibility, but without a round-trip
                                 on every keystroke. --}}
                            <input type="{{ in_array($field->type, ['text', 'email', 'number', 'date'], true) ? $field->type : 'text' }}" wire:model.blur="answers.{{ $field->id }}" placeholder="{{ $field->placeholder }}" style="width: 100%; padding: 11px 14px; border: 1px solid #E5E5E5; border-radius: 8px; font-size: 14px; font-family: 'Inter', sans-serif;" />
                        @endif

                        @if (! empty($field->options['helper_text']))
                            <p style="font-size: 12px; color: var(--muted); margin: 0;">{{ $field->options['helper_text'] }}</p>
                        @endif

                        @if ($isConversational)
                            <div style="display: flex; gap: 10px; margin-top: 8px;">
                                @if (! $loop->first)
                                    <button type="button" @click="step = Math.max(step - 1, 0)" style="padding: 9px 14px; background: white; border: 1px solid #E5E5E5; border-radius: 6px; font-size: 12px; font-weight: 600; color: var(--ink); cursor: pointer; transition: all 0.2s;">
                                        ← Back
                                    </button>
                                @endif

                                @if (! $loop->last)
                                    <button type="button" @click="step = Math.min(step + 1, total - 1)" style="padding: 9px 14px; background: var(--yellow); border: none; border-radius: 6px; font-size: 12px; font-weight: 600; color: var(--ink); cursor: pointer; transition: all 0.2s;">
                                        Next →
                                    </button>
                                @endif
                            </div>
                        @endif
                    </div>
                @endforeach

                @if ($form->settings['consent_required'] ?? false)
                    <label style="display: flex; align-items: flex-start; gap: 10px; font-size: 13px; color: var(--ink); cursor: pointer; margin-top: 8px;">
                        <input type="checkbox" wire:model="consentAccepted" style="width: 18px; height: 18px; margin-top: 2px; cursor: pointer; accent-color: var(--yellow);">
                        <span>{{ $consentLabel }}</span>
                    </label>
                @endif

                <button type="submit" style="padding: 12px 18px; background: var(--yellow); color: var(--ink); border: none; border-radius: 8px; font-size: 14px; font-weight: 700; cursor: pointer; transition: all 0.2s; align-self: flex-start; margin-top: 8px;" :class="{ 'hidden': conversational && step < total - 1 }">
                    Submit
                </button>
            </form>
        @endif
    </div>
</div>
