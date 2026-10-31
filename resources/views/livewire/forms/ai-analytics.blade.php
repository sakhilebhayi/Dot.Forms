<div style="max-width:820px;margin:0 auto;">
    <div class="cb-pagehead">
        <div>
            <h1 class="cb-h1">AI Submission Analytics</h1>
            <p class="cb-sub"><span class="cb-serial">FRM-{{ str_pad((string) $form->id, 3, '0', STR_PAD_LEFT) }}</span> · {{ $form->title }}</p>
        </div>
        <a href="{{ route('teams.forms.submissions', ['team' => $team, 'form' => $form]) }}" class="cb-btn cb-btn--quiet">Back to the copies</a>
        <button wire:click="summarize" class="cb-btn cb-btn--primary">Summarize 100 Submissions</button>
    </div>

    <div class="cb-tallies">
        <div class="cb-tally"><div class="cb-tally__lbl">Positive</div><div class="cb-tally__v" style="color:var(--cb-stamp-green)">{{ $analysis['sentiment']['positive'] ?? 0 }}</div></div>
        <div class="cb-tally"><div class="cb-tally__lbl">Neutral</div><div class="cb-tally__v">{{ $analysis['sentiment']['neutral'] ?? 0 }}</div></div>
        <div class="cb-tally"><div class="cb-tally__lbl">Negative</div><div class="cb-tally__v" style="color:var(--cb-stamp-red)">{{ $analysis['sentiment']['negative'] ?? 0 }}</div></div>
    </div>

    <section class="cb-sec">
        <div class="cb-sec__head"><h2>Summary</h2></div>
        <div class="cb-master" style="padding:18px 20px;">
            <p style="margin:0;color:var(--cb-ink);">{{ $analysis['summary'] ?: 'Click "Summarize 100 Submissions" to generate insights.' }}</p>
        </div>
    </section>

    <section class="cb-sec">
        <div class="cb-sec__head"><h2>Recommendations</h2></div>
        <div class="cb-master" style="padding:18px 20px;">
            <ul style="margin:0;padding-left:20px;display:grid;gap:6px;">
                @forelse (($analysis['recommendations'] ?? []) as $recommendation)
                    <li>{{ $recommendation }}</li>
                @empty
                    <li>No recommendations yet.</li>
                @endforelse
            </ul>
        </div>
    </section>
</div>
