import {
    ArcElement,
    BarController,
    BarElement,
    CategoryScale,
    Chart,
    DoughnutController,
    Filler,
    Legend,
    LinearScale,
    LineController,
    LineElement,
    PointElement,
    Tooltip,
} from 'chart.js';

Chart.register(ArcElement, BarController, BarElement, CategoryScale, DoughnutController, Filler, Legend, LinearScale, LineController, LineElement, PointElement, Tooltip);

const charts = [];

function cssVar(name) {
    return getComputedStyle(document.documentElement).getPropertyValue(name).trim();
}

function palette() {
    return Array.from({ length: 8 }, (_, i) => cssVar(`--chart-${i + 1}`));
}

function build(canvas, spec) {
    const colours = palette();
    const isCircular = spec.type === 'doughnut';
    const datasets = spec.datasets.map((dataset, index) => {
        const colour = colours[(dataset.colour ?? index) % colours.length];
        const base = { label: dataset.label, data: dataset.data };

        if (isCircular) {
            return { ...base, backgroundColor: spec.labels.map((_, i) => colours[i % colours.length]), borderWidth: 0 };
        }
        if ((dataset.kind ?? spec.type) === 'line') {
            return {
                ...base,
                type: 'line',
                borderColor: colour,
                backgroundColor: colour,
                borderWidth: 2,
                borderDash: dataset.dashed ? [6, 4] : [],
                pointRadius: 2.5,
                tension: 0.25,
                spanGaps: false,
            };
        }
        return { ...base, backgroundColor: colour, borderRadius: 4, maxBarThickness: 42 };
    });

    const text = cssVar('--chart-text');
    const grid = cssVar('--chart-grid');
    const horizontal = spec.horizontal === true;

    // The value axis is y for vertical charts and x for horizontal ones; the other axis keeps Chart.js's default
    // labels (a tick callback set to undefined would replace them with index numbers).
    const valueTicks = { color: text, precision: 0 };
    if (spec.percent === true) {
        valueTicks.callback = (v) => `${v}%`;
    }
    const valueAxis = { stacked: spec.stacked === true, beginAtZero: true, ticks: valueTicks, grid: { color: grid } };
    if (spec.yLabel && !horizontal) {
        valueAxis.title = { display: true, text: spec.yLabel, color: text };
    }
    const categoryAxis = { stacked: spec.stacked === true, ticks: { color: text, autoSkip: !horizontal }, grid: { display: false } };
    const scales = isCircular ? {} : horizontal ? { x: valueAxis, y: categoryAxis } : { x: categoryAxis, y: valueAxis };

    return new Chart(canvas, {
        type: isCircular ? 'doughnut' : 'bar',
        data: { labels: spec.labels, datasets },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            indexAxis: spec.horizontal ? 'y' : 'x',
            animation: window.matchMedia('(prefers-reduced-motion: reduce)').matches ? false : { duration: 350 },
            plugins: {
                legend: { display: datasets.length > 1 || isCircular, position: 'bottom', labels: { color: text, boxWidth: 12 } },
                tooltip: { mode: 'index', intersect: false },
            },
            scales,
            cutout: isCircular ? '62%' : undefined,
        },
    });
}

function initViewToggles() {
    document.querySelectorAll('[data-view-toggle]').forEach((group) => {
        const target = document.getElementById(group.dataset.viewToggle);
        group.querySelectorAll('button[data-view]').forEach((button) => {
            button.addEventListener('click', () => {
                const showTable = button.dataset.view === 'table';
                target.querySelector('[data-view-chart]').classList.toggle('d-none', showTable);
                target.querySelector('[data-view-table]').classList.toggle('d-none', !showTable);
                group.querySelectorAll('button[data-view]').forEach((b) => b.setAttribute('aria-pressed', String(b === button)));
            });
        });
    });
}

export function initCharts() {
    document.querySelectorAll('canvas[data-chart]').forEach((canvas) => {
        const source = document.getElementById(canvas.dataset.chart);
        if (!source) {
            return;
        }
        const spec = JSON.parse(source.textContent);
        charts.push({ canvas, spec, chart: build(canvas, spec) });
    });

    initViewToggles();

    document.addEventListener('theme:change', () => {
        charts.forEach((entry) => {
            entry.chart.destroy();
            entry.chart = build(entry.canvas, entry.spec);
        });
    });
}
