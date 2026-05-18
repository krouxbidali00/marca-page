import { Controller } from '@hotwired/stimulus';
import { Chart, registerables } from 'chart.js';

Chart.register(...registerables);

/* Reusable Chart.js wrapper. Type and data come from data-stats-chart-*-value attributes.
   Type 'barHorizontal' is mapped to a horizontal bar chart with indexAxis: 'y'. */
export default class extends Controller {
    static targets = ['canvas'];
    static values = { type: String, data: Object };

    connect() {
        const { labels, values, colors } = this.dataValue;
        const isHorizontal = this.typeValue === 'barHorizontal';
        const chartType = isHorizontal ? 'bar' : this.typeValue;

        this.chart = new Chart(this.canvasTarget, {
            type: chartType,
            data: {
                labels,
                datasets: [{
                    data: values,
                    backgroundColor: colors.length === 1 ? colors[0] : colors,
                    borderColor: chartType === 'line' ? colors[0] : undefined,
                    borderWidth: chartType === 'line' ? 2 : 0,
                    fill: chartType === 'line',
                    tension: chartType === 'line' ? 0.3 : 0,
                }],
            },
            options: this.optionsFor(chartType, isHorizontal),
        });
    }

    disconnect() {
        this.chart?.destroy();
    }

    optionsFor(chartType, isHorizontal) {
        const common = {
            responsive: true,
            maintainAspectRatio: false,
            plugins: { legend: { display: false } },
        };
        if (chartType === 'bar' || chartType === 'line') {
            common.scales = {
                x: { grid: { display: false } },
                y: { beginAtZero: true, ticks: { precision: 0 } },
            };
            if (isHorizontal) {
                common.indexAxis = 'y';
            }
        }
        return common;
    }
}
