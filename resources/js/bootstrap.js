let apexChartsPromise = null;

/**
 * Lazily load ApexCharts so it is split into its own chunk and only
 * downloaded on pages that actually render a chart.
 */
window.loadApexCharts = () => {
    apexChartsPromise ??= import('apexcharts').then((module) => module.default);

    return apexChartsPromise;
};

import axios from 'axios';
window.axios = axios;

window.axios.defaults.headers.common['X-Requested-With'] = 'XMLHttpRequest';
