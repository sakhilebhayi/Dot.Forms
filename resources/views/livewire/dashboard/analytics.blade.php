{{-- Livewire allows exactly one root element per component. The page layout
     comes from the component's #[Layout] attribute, so this view must not
     wrap itself in <x-app-layout>; the heading lives inside the root. --}}
<div>
    <h2 class="font-semibold text-xl text-gray-800 leading-tight">Team Analytics</h2>

    <div class="py-8">
        <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8 space-y-6">
            <div class="grid gap-4 md:grid-cols-3">
                <div class="rounded-lg border border-gray-200 bg-white p-4 shadow-sm">
                    <p class="text-xs font-semibold uppercase tracking-wide text-gray-500">Total Submissions</p>
                    <p class="mt-2 text-3xl font-bold text-gray-900">{{ $stats['total_submissions'] }}</p>
                </div>
                <div class="rounded-lg border border-gray-200 bg-white p-4 shadow-sm">
                    <p class="text-xs font-semibold uppercase tracking-wide text-gray-500">Completion Rate</p>
                    <p class="mt-2 text-3xl font-bold text-gray-900">{{ $stats['completion_rate'] }}%</p>
                </div>
                <div class="rounded-lg border border-gray-200 bg-white p-4 shadow-sm">
                    <p class="text-xs font-semibold uppercase tracking-wide text-gray-500">Avg Time To Complete</p>
                    <p class="mt-2 text-3xl font-bold text-gray-900">{{ $stats['avg_completion_seconds'] }}s</p>
                </div>
            </div>

            <div class="grid gap-6 lg:grid-cols-2">
                <div class="rounded-lg border border-gray-200 bg-white p-4 shadow-sm">
                    <h3 class="text-sm font-semibold uppercase tracking-wide text-gray-500">Submissions By Day</h3>
                    <canvas id="submissionsChart" class="mt-4"></canvas>
                </div>
                <div class="rounded-lg border border-gray-200 bg-white p-4 shadow-sm">
                    <h3 class="text-sm font-semibold uppercase tracking-wide text-gray-500">Top Forms</h3>
                    <canvas id="formsChart" class="mt-4"></canvas>
                </div>
            </div>
        </div>
    </div>

    <script type="application/json" id="submission-chart-data">{{ json_encode(['labels' => $submissionLabels, 'data' => $submissionData]) }}</script>
    <script type="application/json" id="forms-chart-data">{{ json_encode(['labels' => $formLabels, 'data' => $formData]) }}</script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script>
        const submissionCtx = document.getElementById('submissionsChart');
        const formsCtx = document.getElementById('formsChart');
        const submissionPayload = JSON.parse(document.getElementById('submission-chart-data').textContent || '{"labels":[],"data":[]}');
        const formsPayload = JSON.parse(document.getElementById('forms-chart-data').textContent || '{"labels":[],"data":[]}');

        if (submissionCtx) {
            new Chart(submissionCtx, {
                type: 'bar',
                data: {
                    labels: submissionPayload.labels,
                    datasets: [{
                        label: 'Submissions',
                        data: submissionPayload.data,
                        backgroundColor: '#4f46e5'
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    scales: {
                        y: {
                            beginAtZero: true
                        }
                    }
                }
            });
        }

        if (formsCtx) {
            new Chart(formsCtx, {
                type: 'pie',
                data: {
                    labels: formsPayload.labels,
                    datasets: [{
                        data: formsPayload.data,
                        backgroundColor: ['#4f46e5', '#0ea5e9', '#16a34a', '#f59e0b', '#ef4444', '#8b5cf6']
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false
                }
            });
        }
    </script>
</div>
