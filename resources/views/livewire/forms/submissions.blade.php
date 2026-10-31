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

    <div style="display:grid;grid-template-columns:minmax(0,2fr) minmax(0,1fr);gap:12px;margin:0 0 16px;">
        <div>
            <label class="cb-label" for="sub-search-submissions">Search submissions</label>
            <input id="sub-search-submissions" class="cb-input" type="text" wire:model.live.debounce.300ms="search" placeholder="Search inside submission data">
        </div>
        <div>
            <label class="cb-label" for="sub-status-filter">Show</label>
            <select id="sub-status-filter" class="cb-select" wire:model.live="statusFilter">
                <option value="all">Every copy</option>
                @foreach (\App\Models\FormSubmission::STATUS_LABELS as $value => $label)
                    <option value="{{ $value }}">{{ $label }}</option>
                @endforeach
            </select>
        </div>
        <div style="grid-column:1 / -1;">
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
                    <th>Status</th>
                    <th>Held by</th>
                    <th>Verdict</th>
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
                        <td>
                            <select class="cb-select" style="height:30px;font-size:12px;min-width:110px;" aria-label="Status of copy {{ $submission->id }}" wire:change="setStatus({{ $submission->id }}, $event.target.value)">
                                @foreach (\App\Models\FormSubmission::STATUS_LABELS as $value => $label)
                                    <option value="{{ $value }}" @selected($submission->status === $value)>{{ $label }}</option>
                                @endforeach
                            </select>
                        </td>
                        <td>
                            <select class="cb-select" style="height:30px;font-size:12px;min-width:120px;" aria-label="Assignee of copy {{ $submission->id }}" wire:change="assign({{ $submission->id }}, $event.target.value || null)">
                                <option value="">Nobody</option>
                                @foreach ($this->assignees() as $userId => $userName)
                                    <option value="{{ $userId }}" @selected($submission->assigned_to === $userId)>{{ $userName }}</option>
                                @endforeach
                            </select>
                        </td>
                        <td style="white-space:nowrap;">
                            @if ($submission->decision)
                                <span class="cb-stamp @if ($submission->decision === 'approved') cb-stamp--ok @endif cb-stamp--landed" title="{{ $submission->decider?->name }} · {{ optional($submission->decided_at)?->format('j M Y, H:i') }}">{{ strtoupper($submission->decision) }}</span>
                                <button type="button" class="cb-btn cb-btn--quiet cb-btn--sm" wire:click="decide({{ $submission->id }}, null)" aria-label="Lift the stamp on copy {{ $submission->id }}" title="Lift the stamp">↺</button>
                            @else
                                <button type="button" class="cb-btn cb-btn--quiet cb-btn--sm" style="color:var(--cb-stamp-green);" wire:click="decide({{ $submission->id }}, 'approved')">Approve</button>
                                <button type="button" class="cb-btn cb-btn--quiet cb-btn--sm" style="color:var(--cb-stamp-red);" wire:click="decide({{ $submission->id }}, 'rejected')">Reject</button>
                            @endif
                        </td>
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
            @if ($activeSubmission)
                <div style="display:flex;align-items:center;gap:10px;margin:0 0 14px;flex-wrap:wrap;">
                    @if ($activeSubmission->decision)
                        <span class="cb-stamp @if ($activeSubmission->decision === 'approved') cb-stamp--ok @endif cb-stamp--landed">{{ strtoupper($activeSubmission->decision) }}</span>
                        <span class="cb-dim" style="font-size:12.5px;color:var(--cb-muted);">by {{ $activeSubmission->decider?->name ?? 'someone no longer on the team' }} · {{ optional($activeSubmission->decided_at)?->format('j M Y, H:i') }}</span>
                        <button type="button" class="cb-btn cb-btn--quiet cb-btn--sm" wire:click="decide({{ $activeSubmission->id }}, null)">Lift the stamp</button>
                    @else
                        <button type="button" class="cb-btn cb-btn--sm" style="color:var(--cb-stamp-green);" wire:click="decide({{ $activeSubmission->id }}, 'approved')">Approve this copy</button>
                        <button type="button" class="cb-btn cb-btn--sm" style="color:var(--cb-stamp-red);" wire:click="decide({{ $activeSubmission->id }}, 'rejected')">Reject this copy</button>
                    @endif
                </div>
            @endif
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
            @if ($activeSubmission)
                <div style="flex:1;margin-right:12px;text-align:left;">
                    <label class="cb-label" for="desk-note">Internal note (never shown to the respondent)</label>
                    <textarea id="desk-note" class="cb-textarea" rows="2" wire:model="noteDraft"></textarea>
                    @error('noteDraft')<p class="cb-error" role="alert">{{ $message }}</p>@enderror
                    <button wire:click="saveNote" class="cb-btn cb-btn--sm" style="margin-top:6px;">Save note</button>
                </div>
            @endif
            @if ($activeSubmission && $activeSubmission->user_id)
                <button wire:click="exportUserData({{ $activeSubmission->id }})" class="cb-btn cb-btn--quiet" style="margin-right:8px;">Export User Data</button>
            @endif
            <button wire:click="closeDetailModal" class="cb-btn">Close</button>
        </x-slot>
    </x-dialog-modal>
</div>
