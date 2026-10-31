<x-app-layout>
    @php
        $team = auth()->user()->currentTeam;
        $forms = $team ? $team->forms()->orderBy('updated_at', 'desc')->limit(6)->get() : collect();
        $totalForms = $team ? $team->forms()->count() : 0;
        $published = $team ? $team->forms()->where('is_published', true)->count() : 0;
        $awaiting = $team
            ? \App\Models\FormSubmission::whereIn('form_id', $team->forms()->pluck('id'))
                ->whereDate('created_at', '>=', now()->subDays(7))->count()
            : 0;
    @endphp

    <div class="cb-pagehead">
        <div>
            <h1 class="cb-h1">The desk</h1>
            <p class="cb-sub">What {{ $team->name ?? 'your company' }} is collecting, and what came in.</p>
        </div>
        @if ($team)
            <a href="{{ route('teams.forms.templates', $team) }}" class="cb-btn cb-btn--quiet">Templates</a>
            <a href="{{ route('teams.forms.ai-builder', $team) }}" class="cb-btn">Set with AI</a>
            <a href="{{ route('teams.forms', $team) }}" class="cb-btn cb-btn--primary">Open the book</a>
        @endif
    </div>

    <div class="cb-tallies">
        <div class="cb-tally"><div class="cb-tally__lbl">Forms in the book</div><div class="cb-tally__v">{{ $totalForms }}</div></div>
        <div class="cb-tally"><div class="cb-tally__lbl">In use</div><div class="cb-tally__v">{{ $published }}</div></div>
        <div class="cb-tally"><div class="cb-tally__lbl">Copies this week</div><div class="cb-tally__v">{{ $awaiting }}</div></div>
    </div>

    <section class="cb-sec">
        <div class="cb-sec__head">
            <h2>Recent masters</h2>
            @if ($team)<a href="{{ route('teams.forms', $team) }}">The whole book</a>@endif
        </div>

        @if ($forms->count() > 0)
            <div class="cb-register">
                @foreach ($forms as $form)
                    <div class="cb-bookrow">
                        <span class="cb-serial">FRM-{{ str_pad((string) $form->id, 3, '0', STR_PAD_LEFT) }}</span>
                        <span>
                            <b>{{ $form->title }}</b><br>
                            <span class="cb-dim">{{ $form->fields()->count() }} {{ Str::plural('field', $form->fields()->count()) }}</span>
                        </span>
                        <span class="cb-n">{{ $form->submissions()->count() }} {{ Str::plural('copy', $form->submissions()->count()) }}</span>
                        <span class="cb-dim cb-when">{{ $form->updated_at->diffForHumans(short: true) }}</span>
                        @if ($form->archived_at)
                            <span class="cb-state cb-state--closed">CLOSED</span>
                        @elseif ($form->is_published)
                            <span class="cb-state cb-state--live">IN USE</span>
                        @else
                            <span class="cb-state cb-state--draft">UNSET</span>
                        @endif
                    </div>
                    <div style="display:flex;gap:6px;padding:0 2px 12px;border-bottom:1px solid var(--cb-rule);margin-bottom:1px;">
                        <a class="cb-btn cb-btn--quiet cb-btn--sm" href="{{ route('teams.forms.builder', ['team' => $team, 'form' => $form]) }}">Open the master</a>
                        <a class="cb-btn cb-btn--quiet cb-btn--sm" href="{{ route('teams.forms.submissions', ['team' => $team, 'form' => $form]) }}">Copies</a>
                    </div>
                @endforeach
            </div>
        @else
            <div class="cb-empty">
                <h2>The book is empty</h2>
                <p>Set your first form — by hand, or described in a sentence and set with AI.</p>
                @if ($team)
                    <a href="{{ route('teams.forms', $team) }}" class="cb-btn cb-btn--primary">Set a new form</a>
                @endif
            </div>
        @endif
    </section>
</x-app-layout>
