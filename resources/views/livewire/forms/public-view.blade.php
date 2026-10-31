@php
    use App\Support\FieldTypes;

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

    // What actually renders as questions: structural breaks and hidden values never do.
    $renderFields = $orderedFields
        ->reject(fn ($field) => $field->type === FieldTypes::PAGE_BREAK || $field->type === 'hidden')
        ->values();

    // The standard document splits into pages at each break (R-30 Phase 2).
    $pages = [[]];
    foreach ($orderedFields as $field) {
        if ($field->type === FieldTypes::PAGE_BREAK) {
            if ($pages[count($pages) - 1] !== []) {
                $pages[] = [];
            }

            continue;
        }
        if ($field->type === 'hidden') {
            continue;
        }
        $pages[count($pages) - 1][] = $field;
    }
    $pages = array_values(array_filter($pages, fn ($page) => $page !== []));
    $pages = $pages === [] ? [[]] : $pages;
    $pageCount = count($pages);

    $logoUrl = $form->logo_path ? asset('storage/' . $form->logo_path) : null;
    $serial = 'FRM-' . str_pad((string) $form->id, 3, '0', STR_PAD_LEFT);
    $questionNo = 0;

    // Theme studio (R-30 Phase 4): preset class + the master's accent and width as
    // inline custom properties. Legacy theme values map onto the presets.
    $preset = $form->settings['theme_preset']
        ?? ['light' => 'carbon', 'dark' => 'midnight', 'brand' => 'letterhead'][$form->settings['theme'] ?? 'light']
        ?? 'carbon';
    $preset = in_array($preset, ['carbon', 'midnight', 'letterhead', 'plain'], true) ? $preset : 'carbon';
    $accent = (string) ($form->settings['brand_color'] ?? '');
    $accent = preg_match('/^#[0-9a-fA-F]{6}$/', $accent) ? $accent : null;
    $docWidth = ['narrow' => '560px', 'standard' => '660px', 'wide' => '780px'][$form->settings['doc_width'] ?? 'standard'] ?? '660px';
    $docStyle = '--cbd-width: '.$docWidth.';'.($accent ? ' --cbd-accent: '.$accent.';' : '');
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
     visibility change won't retarget Alpine's step bounds until the page reloads.
     `showAll` opens every page after a failed submit so no error can hide. --}}
<div class="cb-doc cb-doc--{{ $preset }}" style="{{ $docStyle }}" x-data="{ submitted: @entangle('submitted'), step: 0, conversational: {{ $isConversational ? 'true' : 'false' }}, total: {{ $renderFields->count() }}, page: 0, pages: {{ $pageCount }}, showAll: {{ $errors->any() ? 'true' : 'false' }} }">
    @if ($logoUrl)
        <img src="{{ $logoUrl }}" alt="{{ $form->title }} logo" style="height:40px;width:auto;margin-bottom:18px;">
    @endif

    <div class="cb-doc__head">
        <h1>{{ $form->title }}</h1>
        <span class="cb-serial">{{ $serial }}</span>
    </div>
    <p class="cb-doc__meta">
        {{ $form->team?->name ?? config('app.name') }}
        @if ($isConversational && $renderFields->count() > 0)
            · <span x-text="'question ' + (step + 1) + ' of ' + total"></span>
        @elseif ($pageCount > 1)
            · <span x-show="!showAll" x-text="'page ' + (page + 1) + ' of ' + pages"></span>
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

            @if ($isConversational)
                @foreach ($renderFields as $field)
                    <div class="cb-q" x-show="step === {{ $loop->index }}" x-cloak wire:key="q-{{ $field->id }}">
                        <span class="cb-q__no">{{ str_pad((string) ($loop->index + 1), 2, '0', STR_PAD_LEFT) }}</span>
                        @include('livewire.forms.partials.field-input', ['field' => $field])

                        <div style="display:flex;gap:8px;margin-top:12px;">
                            @if (! $loop->first)
                                <button type="button" class="cb-btn cb-btn--sm" @click="step = Math.max(step - 1, 0)">← Back</button>
                            @endif
                            @if (! $loop->last)
                                <button type="button" class="cb-btn cb-btn--primary cb-btn--sm" @click="step = Math.min(step + 1, total - 1)">Next →</button>
                            @endif
                        </div>
                    </div>
                @endforeach
            @else
                @foreach ($pages as $pageIndex => $pageFields)
                    <div @if ($pageCount > 1) x-show="showAll || page === {{ $pageIndex }}" x-cloak @endif wire:key="page-{{ $pageIndex }}">
                        @foreach ($pageFields as $field)
                            @php $questionNo++; @endphp
                            <div class="cb-q" wire:key="q-{{ $field->id }}">
                                <span class="cb-q__no">{{ str_pad((string) $questionNo, 2, '0', STR_PAD_LEFT) }}</span>
                                @include('livewire.forms.partials.field-input', ['field' => $field])
                            </div>
                        @endforeach
                    </div>
                @endforeach

                @if ($pageCount > 1)
                    <div style="display:flex;gap:8px;margin:4px 0 16px;" x-show="!showAll" x-cloak>
                        <button type="button" class="cb-btn cb-btn--sm" x-show="page > 0" @click="page = Math.max(page - 1, 0)">← Back</button>
                        <button type="button" class="cb-btn cb-btn--primary cb-btn--sm" x-show="page < pages - 1" @click="page = Math.min(page + 1, pages - 1)">Next page →</button>
                    </div>
                @endif
            @endif

            @if ($form->settings['consent_required'] ?? false)
                <label class="cb-choice" style="margin:4px 0 0;">
                    <input type="checkbox" wire:model="consentAccepted">
                    <span>{{ $consentLabel }}</span>
                </label>
                @error('consentAccepted')<p class="cb-error" role="alert">{{ $message }}</p>@enderror
            @endif

            @error('website')<p class="cb-error" role="alert">{{ $message }}</p>@enderror

            <div class="cb-doc__foot"
                x-bind:style="(conversational && step < total - 1) || (!conversational && !showAll && pages > 1 && page < pages - 1) ? 'display:none' : ''">
                <button type="submit" class="cb-btn cb-btn--primary">Submit</button>
                <small>Your answers go to {{ $form->team?->name ?? 'the form owner' }} only.</small>
            </div>
        </form>
    @endif
</div>
