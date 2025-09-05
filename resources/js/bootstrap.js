import ApexCharts from 'apexcharts'
window.ApexCharts = ApexCharts;

import { themeChange } from 'theme-change'
themeChange()

import axios from 'axios';
window.axios = axios;

window.axios.defaults.headers.common['X-Requested-With'] = 'XMLHttpRequest';
