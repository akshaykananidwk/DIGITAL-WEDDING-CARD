/**
 * Chart bootstrapping.
 *
 * Every chart on the site is declared in markup:
 *
 *     <canvas data-sk-chart='{"type":"line","labels":[…],"datasets":[…]}'></canvas>
 *
 * so no page needs its own inline script (which the CSP would block anyway).
 */
(function () {
    'use strict';

    const PALETTE = ['#C8102E', '#F0A02A', '#2E7D62', '#3C6EAF', '#8E4FA8', '#B06A2C', '#4A5568'];

    function hexToRgba(hex, alpha) {
        const value = hex.replace('#', '');
        const int = parseInt(value.length === 3 ? value.replace(/(.)/g, '$1$1') : value, 16);
        return 'rgba(' + ((int >> 16) & 255) + ',' + ((int >> 8) & 255) + ',' + (int & 255) + ',' + alpha + ')';
    }

    function shortDate(label) {
        if (!/^\d{4}-\d{2}-\d{2}$/.test(label)) { return label; }
        const parts = label.split('-');
        return parts[2] + '/' + parts[1];
    }

    function decorate(dataset, index, type) {
        const colour = dataset.color || PALETTE[index % PALETTE.length];
        const base = {
            borderColor: colour,
            borderWidth: type === 'line' ? 2 : 0,
            tension: 0.32,
            pointRadius: 0,
            pointHoverRadius: 4,
            fill: type === 'line',
            backgroundColor: type === 'line' ? hexToRgba(colour, 0.12) : colour,
            borderRadius: type === 'bar' ? 6 : 0,
            maxBarThickness: 42
        };
        if (type === 'doughnut' || type === 'pie') {
            base.backgroundColor = (dataset.data || []).map(function (_, i) {
                return PALETTE[i % PALETTE.length];
            });
            base.borderColor = '#fff';
            base.borderWidth = 2;
        }
        return Object.assign(base, dataset);
    }

    function build(canvas) {
        let spec;
        try {
            spec = JSON.parse(canvas.getAttribute('data-sk-chart') || '{}');
        } catch (error) {
            return;
        }
        if (!spec.datasets || !spec.datasets.length) { return; }

        const type = spec.type || 'line';
        const isCircular = type === 'doughnut' || type === 'pie';

        // eslint-disable-next-line no-new
        new Chart(canvas, {
            type: type,
            data: {
                labels: (spec.labels || []).map(shortDate),
                datasets: spec.datasets.map(function (dataset, index) {
                    return decorate(dataset, index, type);
                })
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                interaction: { mode: 'index', intersect: false },
                plugins: {
                    legend: {
                        display: spec.legend !== false,
                        position: isCircular ? 'right' : 'top',
                        labels: { boxWidth: 12, usePointStyle: true, font: { size: 11 } }
                    },
                    tooltip: {
                        backgroundColor: 'rgba(24,18,16,.92)',
                        padding: 10,
                        displayColors: !isCircular
                    }
                },
                scales: isCircular ? {} : {
                    x: { grid: { display: false }, ticks: { maxRotation: 0, autoSkipPadding: 16, font: { size: 10 } } },
                    y: {
                        beginAtZero: true,
                        border: { display: false },
                        grid: { color: 'rgba(0,0,0,.06)' },
                        ticks: { precision: 0, font: { size: 10 } },
                        stacked: spec.stacked === true
                    }
                }
            }
        });
    }

    document.addEventListener('DOMContentLoaded', function () {
        if (typeof Chart === 'undefined') { return; }
        Chart.defaults.font.family = "'Noto Sans', system-ui, sans-serif";
        Chart.defaults.color = '#5B5148';
        document.querySelectorAll('[data-sk-chart]').forEach(build);
    });
})();
