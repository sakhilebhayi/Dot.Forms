{{-- Livewire allows exactly one root element per component; the layout comes
     from the component's #[Layout] attribute. --}}
<div>
    <div class="cb-pagehead">
        <div>
            <h1 class="cb-h1">Team Analytics</h1>
            <p class="cb-sub">The tallies across every form in the book.</p>
        </div>
    </div>

    <div class="cb-tallies">
        <div class="cb-tally"><div class="cb-tally__lbl">Total Submissions</div><div class="cb-tally__v">{{ $stats['total_submissions'] }}</div></div>
        <div class="cb-tally"><div class="cb-tally__lbl">Completion Rate</div><div class="cb-tally__v">{{ $stats['completion_rate'] }}<small>%</small></div></div>
        <div class="cb-tally"><div class="cb-tally__lbl">Avg Time To Complete</div><div class="cb-tally__v">{{ $stats['avg_completion_seconds'] }}<small>s</small></div></div>
    </div>

    <div class="cb-tally cb-tally--funnel">
        <div class="cb-tally__lbl">The funnel — viewed → started → lodged</div>
        <div class="cb-scroll" tabindex="0" role="region" aria-label="The funnel, one row per form">
            <table class="cb-ftable">
                <thead>
                    <tr>
                        <th scope="col">Form</th>
                        <th scope="col">Viewed</th>
                        <th scope="col">Started</th>
                        <th scope="col">Lodged</th>
                        <th scope="col">Finish rate</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($funnelRows as $row)
                        <tr>
                            <th scope="row" class="cb-ftable__form">{{ $row['title'] }}</th>
                            <td data-label="Viewed">{{ $row['views'] }}</td>
                            <td data-label="Started">{{ $row['starts'] }}</td>
                            <td data-label="Lodged">{{ $row['submissions'] }}</td>
                            <td data-label="Finish rate">
                                @if ($row['finish_rate'] !== null){{ $row['finish_rate'] }}%@else<span class="cb-dim">—</span>@endif
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="5" class="cb-dim">No forms yet.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <p class="cb-hint">"Started" counts a respondent's first answer and only exists from when this tally shipped — older forms can honestly show more lodged copies than starts.</p>
    </div>

    {{-- The charts are drawn by resources/js/tallies.js (Chart.js, bundled with the app, no CDN). The data rides in data attributes
         through @js, so nothing is HTML-escaped into a JSON string that cannot be parsed. --}}
    <div class="cb-charts">
        <div class="cb-tally cb-tally--chart">
            <div class="cb-tally__lbl">Submissions By Day</div>
            <div class="cb-chartbox" wire:ignore>
                <canvas id="submissionsChart" role="img" aria-label="Submissions by day, bar chart" data-tally-chart="bar" data-chart='@json(['labels' => $submissionLabels, 'data' => $submissionData])'></canvas>
            </div>
        </div>
        <div class="cb-tally cb-tally--chart">
            <div class="cb-tally__lbl">Top Forms</div>
            <div class="cb-chartbox" wire:ignore>
                <canvas id="formsChart" role="img" aria-label="Top forms by submissions, horizontal bar chart" data-tally-chart="barh" data-chart='@json(['labels' => $formLabels, 'data' => $formData])'></canvas>
            </div>
        </div>
    </div>
</div>
