<div>
    <div class="cb-pagehead">
        <div>
            <h1 class="cb-h1">Copies</h1>
            <p class="cb-sub"><span class="cb-serial">FRM-{{ str_pad((string) $form->id, 3, '0', STR_PAD_LEFT) }}</span> · {{ $form->title }}</p>
        </div>
        <a href="{{ route('teams.forms.builder', ['team' => $team, 'form' => $form]) }}" class="cb-btn cb-btn--quiet">Open the master</a>
        <a href="{{ route('teams.forms.ai-analytics', ['team' => $team, 'form' => $form]) }}" class="cb-btn cb-btn--quiet">AI summary</a>
        <button wire:click="exportCsv" class="cb-btn">Export CSV</button>
        <button wire:click="exportExcel" class="cb-btn">Export Excel</button>
        <button wire:click="deleteSelected" wire:confirm="Delete selected submissions?" class="cb-btn cb-btn--quiet" style="color:var(--cb-stamp-red)">Delete Selected</button>
    </div>

    @if (session('status'))
        <p class="cb-note" role="status">{{ session('status') }}</p>
    @endif

    <div style="display:grid;grid-template-columns:minmax(0,1fr);gap:12px;margin:0 0 16px;">
        <div>
            <label class="cb-label" for="sub-search-submissions">Search submissions</label>
            <input id="sub-search-submissions" class="cb-input" type="text" wire:model.live.debounce.300ms="search" placeholder="Search inside submission data">
        </div>
        <div>
            <span class="cb-label">Visible columns</span>
            <div style="display:flex;flex-wrap:wrap;gap:8px 18px;">
                @foreach ($this->collectableFields() as $field)
                    <label class="cb-check">
                        <input type="checkbox" wire:model.live="selectedFieldIds" value="{{ $field->id }}">
                        {{ $field->label }}
                    </label>
                @endforeach
            </div>
        </div>
    </div>

    <div style="overflow-x:auto;">
        <table class="cb-table">
            <thead>
                <tr>
                    <th>
                        <input aria-label="Select all submissions" type="checkbox" x-data @change="$wire.set('selectedSubmissionIds', $event.target.checked ? @js($submissions->pluck('id')->all()) : [])">
                    </th>
                    <th>Copy</th>
                    <th>Submitted</th>
                    @foreach ($this->collectableFields()->whereIn('id', $selectedFieldIds) as $field)
                        <th>{{ $field->label }}</th>
                    @endforeach
                    <th style="text-align:right">Action</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($submissions as $submission)
                    <tr wire:key="sub-{{ $submission->id }}">
                        <td><input aria-label="Select submission #{{ $submission->id }}" type="checkbox" wire:model.live="selectedSubmissionIds" value="{{ $submission->id }}"></td>
                        <td><span class="cb-mono">FRM-{{ str_pad((string) $form->id, 3, '0', STR_PAD_LEFT) }}/{{ $submission->id }}</span></td>
                        <td class="cb-dim">{{ optional($submission->submitted_at)->diffForHumans() }}</td>
                        @foreach ($this->collectableFields()->whereIn('id', $selectedFieldIds) as $field)
                            <td style="overflow-wrap:break-word;max-width:260px;">{{ $this->fieldValue($submission, (int) $field->id) }}</td>
                        @endforeach
                        <td style="text-align:right"><button wire:click="viewSubmission({{ $submission->id }})" class="cb-btn cb-btn--quiet cb-btn--sm">Open the copy</button></td>
                    </tr>
                @empty
                    <tr><td colspan="20" style="text-align:center;color:var(--cb-muted);padding:34px 10px;">No submissions yet — the first copy lands here the moment someone submits.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div style="margin-top:16px;">{{ $submissions->links() }}</div>

    <x-dialog-modal wire:model.live="showDetailModal">
        <x-slot name="title">
            @if ($activeSubmission)
                Copy FRM-{{ str_pad((string) $form->id, 3, '0', STR_PAD_LEFT) }}/{{ $activeSubmission->id }}
            @else
                Copy
            @endif
        </x-slot>

        <x-slot name="content">
            <div style="display:grid;gap:12px;">
                @foreach ($this->activeSubmissionRows() as $label => $value)
                    <div style="border:1px solid var(--cb-boxline);border-radius:2px;padding:10px 12px;position:relative;background:var(--cb-sheet);">
                        <p style="margin:0 0 3px;font-weight:600;font-size:13px;">{{ $label }}</p>
                        <p style="margin:0;color:var(--cb-ink);overflow-wrap:break-word;border-bottom:1px dotted var(--cb-boxline);padding-bottom:3px;">{{ $value !== '' ? $value : '—' }}</p>
                    </div>
                @endforeach
            </div>
        </x-slot>

        <x-slot name="footer">
            @if ($activeSubmission && $activeSubmission->user_id)
                <button wire:click="exportUserData({{ $activeSubmission->id }})" class="cb-btn cb-btn--quiet" style="margin-right:8px;">Export User Data</button>
            @endif
            <button wire:click="closeDetailModal" class="cb-btn">Close</button>
        </x-slot>
    </x-dialog-modal>
</div>
