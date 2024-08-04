@props(['data' => [], 'name' => 'apex-chart' ])

@php
    $data = array_merge([
      'chart' => [
        'type' => "area",
        'stacked' => true,
        'height' => 300,
        'foreColor' => "#999",
        'dropShadow' => [
           'enabled' => false,
        ],
        'toolbar' => [
            'show' => false,
        ],
      ],
      
      'colors' => ['#00E396', '#0090FF'],
      'stroke' => [
        'curve' => "smooth",
        'width' => 3
      ],
      'dataLabels' => [
        'enabled' => false
      ],
      'markers' => [
        'size' => 0,
        'strokeColor' => "#fff",
        'strokeWidth' => 3,
        'strokeOpacity' => 1,
        'fillOpacity' => 1,
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
        'tooltip' => [
          'enabled' => false
        ]
      ],
      'yaxis' => [
        'labels' => [
          'offsetX' => 0,
          'offsetY' => -10
        ],
        'tooltip' => [
          'enabled' => false
        ]
      ],
      'grid' => [
        'padding' => [
            'top' => 0,
            'left' => -30,
            'right' => 0,
            'bottom' => 15
        ]
      ],
      'legend' => [
        'show' => false,
        // 'position' => 'top',
        // 'horizontalAlign' => 'left'
      ],
    ], $data);
    $data = json_encode($data)
@endphp

    <div 
        id="chart"
        wire:key="{{ rand() }}"
        x-data="{
            data: {{ $data }},
            init(){
                
                this.data.series = [{
                    'name': 'Total Views',
                    'data': generateDayWiseTimeSeries(0, 18)
                },{
                    'name': 'Unique Views',
                    'data': generateDayWiseTimeSeries(1, 18)
                }]

                this.data.chart.events = {
                    mounted: function (chartContext, config) {
                        renderCustomLegend(chartContext);
                    },
                    updated: function (chartContext, config) {
                        renderCustomLegend(chartContext);
                    }
                }
                
                var chart = new ApexCharts(document.querySelector('#chart-{{ $name }}'), this.data);

                chart.render();

                async function renderCustomLegend(chartContext) {
                    var legendContainer = document.querySelector('#chart-legend');
                    legendContainer.innerHTML = ''; // Clear any existing legend items
                    
                    chartContext.w.globals.seriesNames.forEach(function (seriesName, i) {
                    
                        var seriesColor = chartContext.w.config.colors[i];
                        var legendItem = document.createElement('div');
                        legendItem.classList.add('flex', 'items-center', 'm-2', 'cursor-pointer');
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
                        console.log(chartContext.w.globals.collapsedSeriesIndices.includes(i))
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

                function generateDayWiseTimeSeries(s, count) {
                    var values = [[
                        4,3,10,9,29,19,25,9,12,7,19,5,13,9,17,2,7,5
                    ], [
                        2,3,8,7,22,16,23,7,11,5,12,5,10,4,15,2,6,2
                    ]];
                    var i = 0;
                    var series = [];
                    var x = new Date('11 Nov 2012').getTime();
                    while (i < count) {
                        series.push([x, values[s][i]]);
                        x += 86400000;
                        i++;
                    }
                    return series;
                }





            }
        }"
    >
        <div id="chart-{{ $name }}"></div>
    </div>
    
