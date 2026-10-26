<div class="space-y-6">
    <div class="flex flex-col gap-3 md:flex-row md:items-center md:justify-between">
        <div>
            <h2 class="text-2xl font-bold text-gray-900">Submissions</h2>
            <p class="text-sm text-gray-600">{{ $form->title }}</p>
        </div>

        <div class="flex flex-wrap items-center gap-2">
            <a href="{{ route('teams.forms.builder', ['team' => $team, 'form' => $form]) }}" class="rounded-md border border-gray-300 px-3 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50">Back to Builder</a>
            <a href="{{ route('teams.forms.ai-analytics', ['team' => $team, 'form' => $form]) }}" class="rounded-md border border-gray-300 px-3 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50">AI Analytics</a>
            <button wire:click="exportCsv" class="rounded-md border border-gray-300 px-3 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50">Export CSV</button>
            <button wire:click="exportExcel" class="rounded-md border border-gray-300 px-3 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50">Export Excel</button>
            <button wire:click="deleteSelected" wire:confirm="Delete selected submissions?" class="rounded-md border border-red-300 px-3 py-2 text-sm font-medium text-red-700 hover:bg-red-50">Delete Selected</button>
        </div>
    </div>

    @if (session('status'))
        <div class="rounded-md border border-emerald-200 bg-emerald-50 px-3 py-2 text-sm text-emerald-700">
            {{ session('status') }}
        </div>
    @endif

    <div class="rounded-lg border border-gray-200 bg-white p-4 space-y-4">
        <div>
            <label for="sub-search-submissions" class="text-sm font-medium text-gray-700">Search Submissions</label>
            <input id="sub-search-submissions" type="text" wire:model.live.debounce.300ms="search" class="mt-1 w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500" placeholder="Search inside submission data">
        </div>

        <div>
            <p class="mb-2 text-sm font-medium text-gray-700">Visible Columns</p>
            <div class="flex flex-wrap gap-3">
                @foreach ($form->fields as $field)
                    <label class="inline-flex items-center gap-2 text-sm text-gray-700">
                        <input type="checkbox" wire:model.live="selectedFieldIds" value="{{ $field->id }}" class="rounded border-gray-300 text-indigo-600 shadow-sm focus:ring-indigo-500">
                        {{ $field->label }}
                    </label>
                @endforeach
            </div>
        </div>
    </div>

    <div class="overflow-x-auto rounded-lg border border-gray-200 bg-white">
        <table class="min-w-full divide-y divide-gray-200">
            <thead class="bg-gray-50">
                <tr>
                    <th class="px-4 py-3 text-left text-xs font-semibold uppercase text-gray-500">
                        <input aria-label="Select all submissions" type="checkbox" x-data @change="$wire.set('selectedSubmissionIds', $event.target.checked ? @js($submissions->pluck('id')->all()) : [])" class="rounded border-gray-300 text-indigo-600 shadow-sm focus:ring-indigo-500">
                    </th>
                    <th class="px-4 py-3 text-left text-xs font-semibold uppercase text-gray-500">Submitted</th>
                    @foreach ($form->fields->whereIn('id', $selectedFieldIds) as $field)
                        <th class="px-4 py-3 text-left text-xs font-semibold uppercase text-gray-500">{{ $field->label }}</th>
                    @endforeach
                    <th class="px-4 py-3 text-right text-xs font-semibold uppercase text-gray-500">Action</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100 bg-white">
                @forelse ($submissions as $submission)
                    <tr>
                        <td class="px-4 py-3 text-sm">
                            <input aria-label="Select submission #{{ $submission->id }}" type="checkbox" wire:model.live="selectedSubmissionIds" value="{{ $submission->id }}" class="rounded border-gray-300 text-indigo-600 shadow-sm focus:ring-indigo-500">
                        </td>
                        <td class="px-4 py-3 text-sm text-gray-600">{{ optional($submission->submitted_at)->diffForHumans() }}</td>
                        @foreach ($form->fields->whereIn('id', $selectedFieldIds) as $field)
                            <td class="px-4 py-3 text-sm text-gray-700">{{ $this->fieldValue($submission, (int) $field->id) }}</td>
                        @endforeach
                        <td class="px-4 py-3 text-right">
                            <button wire:click="viewSubmission({{ $submission->id }})" class="rounded-md border border-gray-300 px-2 py-1 text-xs font-medium text-gray-700 hover:bg-gray-50">View</button>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="20" class="px-4 py-8 text-center text-sm text-gray-500">No submissions yet.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div>
        {{ $submissions->links() }}
    </div>

    <x-dialog-modal wire:model.live="showDetailModal">
        <x-slot name="title">Submission Details</x-slot>

        <x-slot name="content">
            <div class="space-y-2">
                @foreach ($this->activeSubmissionRows() as $label => $value)
                    <div class="rounded-md border border-gray-100 bg-gray-50 px-3 py-2">
                        <p class="text-xs font-semibold uppercase tracking-wide text-gray-500">{{ $label }}</p>
                        <p class="mt-1 text-sm text-gray-800 break-words">{{ $value !== '' ? $value : '-' }}</p>
                    </div>
                @endforeach
            </div>
        </x-slot>

        <x-slot name="footer">
            @if ($activeSubmission && $activeSubmission->user_id)
                <button wire:click="exportUserData({{ $activeSubmission->id }})" class="me-2 rounded-md border border-gray-300 px-3 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50">Export User Data</button>
            @endif
            <x-secondary-button wire:click="closeDetailModal">Close</x-secondary-button>
        </x-slot>
    </x-dialog-modal>
</div>
