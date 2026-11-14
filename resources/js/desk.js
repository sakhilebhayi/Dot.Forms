// The desk (R-51): client-side sorting of the register and the figure reveals. No requests, no library.
// Sorting reads data-* attributes the server already rendered; the reveal ends on the true value (the text in the
// page), and does nothing under prefers-reduced-motion.

function sortRegisters() {
    document.querySelectorAll('table[data-sortable]').forEach((table) => {
        const body = table.tBodies[0];
        table.querySelectorAll('th button[data-sort]').forEach((button) => {
            button.addEventListener('click', () => {
                const key = button.dataset.sort;
                const th = button.closest('th');
                const next = th.getAttribute('aria-sort') === 'ascending' ? 'descending' : 'ascending';
                table.querySelectorAll('th[aria-sort]').forEach((other) => other.setAttribute('aria-sort', 'none'));
                th.setAttribute('aria-sort', next);
                const dir = next === 'ascending' ? 1 : -1;
                [...body.rows]
                    .sort((a, b) => {
                        const x = a.dataset[key] ?? '';
                        const y = b.dataset[key] ?? '';
                        const nx = Number(x);
                        const ny = Number(y);
                        const bothNumbers = x !== '' && y !== '' && !Number.isNaN(nx) && !Number.isNaN(ny);
                        return (bothNumbers ? nx - ny : String(x).localeCompare(String(y))) * dir;
                    })
                    .forEach((row) => body.appendChild(row));
            });
        });
    });
}

function revealFigures() {
    if (window.matchMedia('(prefers-reduced-motion: reduce)').matches) {
        return;
    }
    document.querySelectorAll('[data-countup]').forEach((el) => {
        const final = el.textContent.trim();
        const target = Number(final);
        if (!Number.isInteger(target) || target < 2 || target > 100000) {
            return;
        }
        const started = performance.now() + 380;
        const duration = 520;
        el.textContent = '0';
        const tick = (now) => {
            const t = Math.min(1, Math.max(0, (now - started) / duration));
            const eased = 1 - Math.pow(1 - t, 3);
            el.textContent = String(t >= 1 ? target : Math.round(target * eased));
            if (t < 1) {
                requestAnimationFrame(tick);
            }
        };
        requestAnimationFrame(tick);
    });
}

function start() {
    sortRegisters();
    revealFigures();
}

if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', start);
} else {
    start();
}
