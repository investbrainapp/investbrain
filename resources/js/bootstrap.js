import ApexCharts from 'apexcharts'
window.ApexCharts = ApexCharts;

import '../../vendor/rappasoft/laravel-livewire-tables/resources/imports/laravel-livewire-tables.js';

import axios from 'axios';
window.axios = axios;

window.axios.defaults.headers.common['X-Requested-With'] = 'XMLHttpRequest';
