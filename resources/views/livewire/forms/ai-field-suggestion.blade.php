<div style="max-width:820px;margin:0 auto;">
    <div class="cb-pagehead">
        <div>
            <h1 class="cb-h1">AI Field Suggestions</h1>
            <p class="cb-sub"><span class="cb-serial">FRM-{{ str_pad((string) $form->id, 3, '0', STR_PAD_LEFT) }}</span> · {{ $form->title }}</p>
        </div>
        <a href="{{ route('teams.forms.builder', ['team' => $team, 'form' => $form]) }}" class="cb-btn cb-btn--quiet">Open the master</a>
        <button wire:click="enhanceLabels" class="cb-btn">Enhance Field Labels</button>
    </div>

    @if (session('status'))
        <p class="cb-note" role="status">{{ session('status') }}</p>
    @endif

    <div style="display:grid;gap:14px;">
        @forelse ($suggestions as $index => $suggestion)
            <article class="cb-fieldbox">
                <span class="cb-fieldbox__no">{{ str_pad((string) ($index + 1), 2, '0', STR_PAD_LEFT) }}</span>
                <div style="display:flex;justify-content:space-between;gap:12px;align-items:flex-start;margin-top:2px;">
                    <div style="min-width:0;">
                        <b>{{ $suggestion['label'] }}</b>
                        <p class="cb-hint" style="margin-top:2px;">{{ \Illuminate\Support\Str::headline($suggestion['type']) }}</p>
                        @if (! empty($suggestion['placeholder']))
                            <p class="cb-hint">Placeholder: {{ $suggestion['placeholder'] }}</p>
                        @endif
                        @if (! empty($suggestion['options']))
                            <p class="cb-hint">Options: {{ implode(', ', $suggestion['options']) }}</p>
                        @endif
                    </div>
                    <label class="cb-check" style="flex-shrink:0;">
                        <input type="checkbox" wire:model="selectedSuggestionIds" value="{{ $index }}">
                        Select
                    </label>
                </div>
            </article>
        @empty
            <div class="cb-empty"><p style="margin:0;">No suggestions available.</p></div>
        @endforelse
    </div>

    <div style="margin-top:18px;">
        <button wire:click="applySuggestions" class="cb-btn cb-btn--primary">Apply Selected Suggestions</button>
    </div>
</div>
