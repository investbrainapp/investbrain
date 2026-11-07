@props(['seriesData' => [], 'name' => 'apex-chart', 'currency' => true ])

@php
    $seriesData = array_merge([
      'chart' => [
        'type' => "area",
        'height' => 300,
        'foreColor' => "#999",
        'toolbar' => [
            'show' => false,
        ],
        'zoom' => [
          'enabled' => false
        ],
        // the chart is rebuilt on every time period change, so keep its entrance short
        'animations' => [
          'speed' => 300,
          'animateGradually' => [
            'enabled' => false
          ]
        ]
      ],
      'colors' => ['#3185FC', '#48435C', '#9792E3', '#00E396', '#B74F6F'],
      'stroke' => [
        'width' => 3
      ],
      'dataLabels' => [
        'enabled' => false
      ],
      'markers' => [
        'strokeWidth' => 3,
        'strokeOpacity' => 1,
        'hover' => [
          'size' => 6
        ]
      ],
      'xaxis' => [
        'type' => "datetime",
        'axisBorder' => [
          'show' => false
        ],
        'axisTicks' => [
          'show' => false
        ],
        'labels' => [
          'offsetX' => 15,
        ],
        'tooltip' => [
          'enabled' => false
        ]
      ],
      'yaxis' => [
        'labels' => [
          'offsetX' => -10,
          'offsetY' => -10
        ],
        'tooltip' => [
          'enabled' => false
        ]
      ],
      'grid' => [
        'padding' => [
            'left' => -30,
            'right' => 0,
            'bottom' => -5
        ]
      ],
      'legend' => [
        'show' => false,
      ],
    ], $seriesData);
    // percentages for the tooltip are not ApexCharts options, so keep them out of its config
    $percentages = json_encode(Arr::pull($seriesData, 'percentages', []));
    $seriesData = json_encode($seriesData)
@endphp

<div
    wire:key="{{ rand() }}"
    x-data="{
        data: {{ $seriesData }},
        percentages: {{ $percentages }},
        removed: false,
        async init(){

            const ApexCharts = await window.loadApexCharts();

            // Livewire may have replaced this element while ApexCharts was loading
            if (this.removed) return;

            this.data.chart.events = {
                mounted: function (chartContext, config) {
                    renderLegend(chartContext);
                },
            }

            @if ($currency)
            const locale = {{ Js::from(str_replace('_', '-', auth()->user()->getLocale())) }};
            const currency = { style: 'currency', currency: {{ Js::from(auth()->user()->getCurrency()) }} };

            // short axis labels such as -$5K, full amounts such as -$22,686.48 in the tooltip
            const axisAmount = new Intl.NumberFormat(locale, { ...currency, notation: 'compact', maximumFractionDigits: 1 });
            const tooltipAmount = new Intl.NumberFormat(locale, currency);
            const tooltipPercentage = new Intl.NumberFormat(locale, { style: 'percent', minimumFractionDigits: 2, maximumFractionDigits: 2 });

            this.data.yaxis.labels.formatter = (value) => axisAmount.format(value);

            this.data.tooltip = {
                shared: false,
                y: {
                    formatter: (value, { seriesIndex, dataPointIndex }) => {
                        const percentage = this.percentages[seriesIndex]?.[dataPointIndex];
                        const amount = tooltipAmount.format(value);

                        return percentage == null ? amount : `${amount} (${tooltipPercentage.format(percentage / 100)})`;
                    }
                },
            }
            @endif

            var chart = new ApexCharts(this.$refs.chart, this.data);

            // kept off Alpine's reactive data, so the chart instance is not wrapped in proxies
            this.$el.apexChart = chart;

            chart.render();

            // generate custom legend view
            function renderLegend(chartContext) {
            
                var legendContainer = document.querySelector('#chart-legend-{{ $name }}');

                if (!legendContainer) return;

                legendContainer.innerHTML = ''; // Clear any existing legend items
                
                chartContext.w.globals.seriesNames.forEach(function (seriesName, i) {

                    var seriesColor = chartContext.w.config.colors[i];
                    var legendItem = document.createElement('div');
                    legendItem.classList.add('flex', 'items-center', 'my-2', 'mr-4', 'text-xs', 'md:text-sm', 'cursor-pointer');
                    legendItem.setAttribute('data-series-index', i);

                    var colorBox = document.createElement('span');
                    colorBox.id = seriesName
                    colorBox.classList.add('w-4', 'h-4', 'inline-block', 'mr-2');
                    colorBox.style.backgroundColor = seriesColor;

                    var labelText = document.createElement('span');
                    labelText.textContent = seriesName;

                    legendItem.appendChild(colorBox);
                    legendItem.appendChild(labelText);
                    legendContainer.appendChild(legendItem);

                    // Initial visibility state
                    var isCollapsed = chartContext.w.globals.collapsedSeriesIndices.includes(i);
                    if (isCollapsed) {
                        legendItem.classList.add('opacity-50');
                    }

                    legendItem.addEventListener('click', function () {
                      
                        var seriesIndex = parseInt(this.getAttribute('data-series-index'), 10);
                        var isCurrentlyCollapsed = chartContext.w.globals.collapsedSeriesIndices.includes(seriesIndex);

                        chart.toggleSeries(chartContext.w.globals.seriesNames[seriesIndex]);

                        if (isCurrentlyCollapsed) {
                            this.classList.remove('opacity-50');
                        } else {
                            this.classList.add('opacity-50');
                        }
                    });
                });
            }

        },

        // wire:key changes on every render, so Livewire swaps in a new element each time;
        // without this the old chart and its window resize listener are never released
        destroy() {
            this.removed = true;
            this.$el.apexChart?.destroy();
            delete this.$el.apexChart;
        }
    }"
>
  <div x-ref="chart" class="apex-chart"></div>
</div>

