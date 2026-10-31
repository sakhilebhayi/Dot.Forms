<div>
    <div class="cb-pagehead">
        <div>
            <h1 class="cb-h1">The book</h1>
            <p class="cb-sub">Every form {{ $team->name }} prints, numbered and bound.</p>
        </div>
        <a href="{{ route('teams.forms.templates', $team) }}" class="cb-btn cb-btn--quiet">Master templates</a>
        <a href="{{ route('teams.forms.ai-builder', $team) }}" class="cb-btn">Set with AI</a>
        <button wire:click="createForm" class="cb-btn cb-btn--primary">Set a new form</button>
    </div>

    @if (session('status'))
        <p class="cb-note" role="status">{{ session('status') }}</p>
    @endif

    <div style="display:grid;grid-template-columns:minmax(0,2fr) minmax(0,1fr);gap:12px;margin:0 0 16px;">
        <div>
            <label class="cb-label" for="form-search">Find a form</label>
            <input id="form-search" class="cb-input" type="text" wire:model.live.debounce.300ms="search" placeholder="Search by title…">
        </div>
        <div>
            <label class="cb-label" for="form-status">Show</label>
            <select id="form-status" class="cb-select" wire:model.live="status">
                <option value="all">All forms</option>
                <option value="published">In use</option>
                <option value="draft">Unset drafts</option>
                <option value="archived">Closed</option>
            </select>
        </div>
    </div>

    <div class="cb-register">
        @forelse ($forms as $form)
            <div class="cb-bookrow">
                <span class="cb-serial">FRM-{{ str_pad((string) $form->id, 3, '0', STR_PAD_LEFT) }}</span>
                <span>
                    <b>{{ $form->title }}</b><br>
                    <span class="cb-dim">/{{ $form->slug }}</span>
                </span>
                <span class="cb-n">{{ $form->submissions_count ?? $form->submissions()->count() }} {{ Str::plural('copy', $form->submissions_count ?? $form->submissions()->count()) }}</span>
                <span class="cb-dim cb-when">{{ $form->updated_at->diffForHumans(short: true) }}</span>
                @if ($form->archived_at)
                    <span class="cb-state cb-state--closed">CLOSED</span>
                @elseif ($form->is_published)
                    <span class="cb-state cb-state--live">IN USE</span>
                @else
                    <span class="cb-state cb-state--draft">UNSET</span>
                @endif
            </div>
            <div style="display:flex;gap:6px;flex-wrap:wrap;padding:0 2px 12px;border-bottom:1px solid var(--cb-rule);margin-bottom:1px;">
                <a class="cb-btn cb-btn--quiet cb-btn--sm" href="{{ route('teams.forms.builder', ['team' => $team, 'form' => $form]) }}">Open the master</a>
                <a class="cb-btn cb-btn--quiet cb-btn--sm" href="{{ route('teams.forms.submissions', ['team' => $team, 'form' => $form]) }}">Copies</a>
                <button class="cb-btn cb-btn--quiet cb-btn--sm" wire:click="duplicateForm({{ $form->id }})">Duplicate</button>
                <button class="cb-btn cb-btn--quiet cb-btn--sm" wire:click="archiveForm({{ $form->id }})">Close</button>
                <button class="cb-btn cb-btn--quiet cb-btn--sm" style="color:var(--cb-stamp-red)" wire:click="deleteForm({{ $form->id }})" wire:confirm="Delete this form and every copy it collected? This cannot be undone.">Delete</button>
            </div>
        @empty
            <div class="cb-empty" style="margin-top:14px;">
                <h2>Nothing in the book yet</h2>
                <p>No forms found. Create one to get started.</p>
                <button wire:click="createForm" class="cb-btn cb-btn--primary">Set a new form</button>
            </div>
        @endforelse
    </div>

    <div style="margin-top:16px;">{{ $forms->links() }}</div>
</div>
