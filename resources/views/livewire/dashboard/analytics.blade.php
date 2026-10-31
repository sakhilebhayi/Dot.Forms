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

    <div class="cb-tally" style="margin:0 0 14px;">
        <div class="cb-tally__lbl" style="margin-bottom:8px;">The funnel — viewed → started → lodged</div>
        <div style="overflow-x:auto;">
            <table style="border-collapse:collapse;width:100%;font-size:13px;">
                <thead>
                    <tr style="color:var(--cb-muted);text-align:left;">
                        <th style="padding:4px 10px 6px 0;font-weight:600;">Form</th>
                        <th style="padding:4px 10px 6px 0;font-weight:600;">Viewed</th>
                        <th style="padding:4px 10px 6px 0;font-weight:600;">Started</th>
                        <th style="padding:4px 10px 6px 0;font-weight:600;">Lodged</th>
                        <th style="padding:4px 0 6px;font-weight:600;">Finish rate</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($funnelRows as $row)
                        <tr style="border-top:1px dotted var(--cb-boxline);">
                            <td style="padding:7px 10px 7px 0;overflow-wrap:break-word;max-width:260px;">{{ $row['title'] }}</td>
                            <td style="padding:7px 10px 7px 0;font-variant-numeric:tabular-nums;">{{ $row['views'] }}</td>
                            <td style="padding:7px 10px 7px 0;font-variant-numeric:tabular-nums;">{{ $row['starts'] }}</td>
                            <td style="padding:7px 10px 7px 0;font-variant-numeric:tabular-nums;">{{ $row['submissions'] }}</td>
                            <td style="padding:7px 0;font-variant-numeric:tabular-nums;">
                                @if ($row['finish_rate'] !== null){{ $row['finish_rate'] }}%@else<span style="color:var(--cb-muted);">—</span>@endif
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="5" style="padding:10px 0;color:var(--cb-muted);">No forms yet.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <p class="cb-hint" style="margin-top:8px;">"Started" counts a respondent's first answer and only exists from when this tally shipped — older forms can honestly show more lodged copies than starts.</p>
    </div>

    <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(320px,1fr));gap:14px;">
        <div class="cb-tally" style="min-height:300px;">
            <div class="cb-tally__lbl" style="margin-bottom:10px;">Submissions By Day</div>
            <div style="height:240px;"><canvas id="submissionsChart"></canvas></div>
        </div>
        <div class="cb-tally" style="min-height:300px;">
            <div class="cb-tally__lbl" style="margin-bottom:10px;">Top Forms</div>
            <div style="height:240px;"><canvas id="formsChart"></canvas></div>
        </div>
    </div>

    <script type="application/json" id="submission-chart-data">{{ json_encode(['labels' => $submissionLabels, 'data' => $submissionData]) }}</script>
    <script type="application/json" id="forms-chart-data">{{ json_encode(['labels' => $formLabels, 'data' => $formData]) }}</script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script>
        // One chart hue (carbon blue) — the system's rule: colour is data, not decoration.
        const carbon = '#2b4a9e';
        const submissionCtx = document.getElementById('submissionsChart');
        const formsCtx = document.getElementById('formsChart');
        const submissionPayload = JSON.parse(document.getElementById('submission-chart-data').textContent || '{"labels":[],"data":[]}');
        const formsPayload = JSON.parse(document.getElementById('forms-chart-data').textContent || '{"labels":[],"data":[]}');

        if (submissionCtx) {
            new Chart(submissionCtx, {
                type: 'bar',
                data: {
                    labels: submissionPayload.labels,
                    datasets: [{ label: 'Submissions', data: submissionPayload.data, backgroundColor: carbon }]
                },
                options: { responsive: true, maintainAspectRatio: false, scales: { y: { beginAtZero: true, ticks: { precision: 0 } } } }
            });
        }

        if (formsCtx) {
            new Chart(formsCtx, {
                type: 'bar',
                data: {
                    labels: formsPayload.labels,
                    datasets: [{ label: 'Submissions', data: formsPayload.data, backgroundColor: carbon }]
                },
                options: { indexAxis: 'y', responsive: true, maintainAspectRatio: false, scales: { x: { beginAtZero: true, ticks: { precision: 0 } } } }
            });
        }
    </script>
</div>
