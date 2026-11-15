/*
 * The two Tallies charts. Chart.js is bundled with the app (no CDN) and loaded only on a page that has a canvas.
 * The data comes from the canvas's data-chart attribute (JSON written with @json, so the quotes survive).
 * One chart hue, the carbon blue (--cb-chart): colour is data, not decoration.
 */
const readToken = (name, fallback) =>
    getComputedStyle(document.documentElement).getPropertyValue(name).trim() || fallback;

async function drawTallies() {
    const canvases = document.querySelectorAll('canvas[data-tally-chart]');
    if (canvases.length === 0) return;

    const { Chart, BarController, BarElement, CategoryScale, LinearScale, Tooltip } = await import('chart.js');
    Chart.register(BarController, BarElement, CategoryScale, LinearScale, Tooltip);

    const ink = readToken('--cb-muted', '#6e6a60');
    const rule = readToken('--cb-rule', '#d9d4c6');
    const hue = readToken('--cb-chart', '#2b4a9e');
    Chart.defaults.font.family = readToken('--dot-font-sans', 'Roboto, system-ui, sans-serif');
    Chart.defaults.color = ink;

    canvases.forEach((canvas) => {
        if (canvas.dataset.drawn === '1') return;

        let payload = { labels: [], data: [] };
        try {
            payload = JSON.parse(canvas.dataset.chart || '') || payload;
        } catch (e) {
            return;
        }

        const horizontal = canvas.dataset.tallyChart === 'barh';
        const count = { beginAtZero: true, ticks: { precision: 0 }, grid: { color: rule } };
        const names = { grid: { display: false }, ticks: { autoSkip: true, maxRotation: 0 } };

        canvas.dataset.drawn = '1';
        new Chart(canvas, {
            type: 'bar',
            data: { labels: payload.labels, datasets: [{ label: 'Submissions', data: payload.data, backgroundColor: hue }] },
            options: {
                indexAxis: horizontal ? 'y' : 'x',
                responsive: true,
                maintainAspectRatio: false,
                plugins: { legend: { display: false } },
                scales: horizontal ? { x: count, y: names } : { y: count, x: names },
            },
        });
    });
}

document.addEventListener('DOMContentLoaded', drawTallies);
document.addEventListener('livewire:navigated', drawTallies);
