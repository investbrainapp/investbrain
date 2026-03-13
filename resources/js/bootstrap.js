import ApexCharts from 'apexcharts'
window.ApexCharts = ApexCharts;

import axios from 'axios';
window.axios = axios;

window.axios.defaults.headers.common['X-Requested-With'] = 'XMLHttpRequest';
