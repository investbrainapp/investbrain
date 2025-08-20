<?php

use App\Models\DailyChange;
use App\Models\Portfolio;
use Livewire\Volt\Component;

new class extends Component
{
    // props
    public ?Portfolio $portfolio;

    public string $name = 'portfolio';

    public string $scope = 'YTD';

    public array $scopeOptions = [
        ['id' => '1M', 'name' => '1 month', 'method' => 'subMonths', 'args' => [1]],
        ['id' => '3M', 'name' => '3 months', 'method' => 'subMonths', 'args' => [3]],
        ['id' => 'YTD', 'name' => 'Year to date', 'method' => 'startOfYear', 'args' => []],
        ['id' => '1Y', 'name' => '1 year', 'method' => 'subYears', 'args' => [1]],
        ['id' => '3Y', 'name' => '3 years', 'method' => 'subYears', 'args' => [3]],
        ['id' => 'ALL', 'name' => 'All time', 'method' => null],
    ];

    // data
    public array $chartSeries;

    // methods
    public function mount()
    {
        $this->chartSeries = $this->generatePerformanceData();
    }

    public function generatePerformanceData()
    {
        $filterMethod = collect($this->scopeOptions)->where('id', $this->scope)->first();

        $dailyChangeQuery = DailyChange::withDailyPerformance();

        if (isset($this->portfolio)) {

            // portfolio
            $dailyChangeQuery->portfolio($this->portfolio->id);

        } else {

            // dashboard
            $dailyChangeQuery->myDailyChanges()->withoutWishlists();
        }

        if ($filterMethod['method']) {

            $dailyChangeQuery->whereDate('daily_change.date', '>=', now()->{$filterMethod['method']}(...$filterMethod['args']));
        }

        $dailyChange = cache()->remember(
            'graph-'.$this->scope.'-'.(isset($this->portfolio) ? $this->portfolio->id : request()->user()->id),
            30,
            function () use ($dailyChangeQuery) {
                return $dailyChangeQuery->getDailyPerformance();
            }
        );

        return [
            'series' => [
                [
                    'name' => __('Market Value'),
                    'data' => $dailyChange->map(fn ($data) => [$data->date, $data->total_market_value])->toArray(),
                ],
                [
                    'name' => __('Cost Basis'),
                    'data' => $dailyChange->map(fn ($data) => [$data->date, $data->total_cost_basis])->toArray(),
                ],
                [
                    'name' => __('Market Gain'),
                    'data' => $dailyChange->map(fn ($data) => [$data->date, $data->total_gain])->toArray(),
                ],

                // [
                //     'name' => __('Dividends Earned'),
                //     'data' => $dailyChange->map(fn($data) => [$data->date, $data->total_dividends_earned])->toArray()
                // ],
                // [
                //     'name' => __('Realized Gains'),
                //     'data' => $dailyChange->map(fn($data) => [$data->date, $data->realized_gains])->toArray()
                // ],
            ],
        ];
    }

    public function changeScope($scope)
    {
        $this->scope = $scope;

        cache()->forget('graph-'.isset($this->portfolio) ? $this->portfolio->id : request()->user()->id);

        $this->chartSeries = $this->generatePerformanceData();
    }

    public function getScopeName($scope)
    {
        return collect($this->scopeOptions)->where('id', $scope)->first()['name'];
    }
}; ?>

<x-card class="bg-slate-100 dark:bg-base-200 rounded-lg mb-6">
    <div class="flex flex-col md:flex-row md:justify-between mb-2">
                    
        <div class="flex flex-col md:flex-row items-start md:items-center">
            
            <h2 class="text-xl mb-2 md:mb-0 md:mr-4">{{ __('Performance') }}</h2>

            <div id="chart-legend-{{ $name }}" class="flex space-between whitespace-nowrap mb-2 md:mb-0"></div>
            
        </div>
        
        <div class="flex items-center" x-data="{ loading: false }">
            {{-- <x-button title="{{ __('Reset chart') }}" icon="o-arrow-path" class="btn-ghost btn-sm btn-circle mr-2" id="chart-reset-zoom-{{ $name }}" /> --}}

            <x-loading x-show="loading" x-cloak class="text-gray-400 ml-2" />

            <x-dropdown title="{{ __('Choose time period') }}" label="{{ $scope }}" class="btn-xs md:btn-sm btn-outline" x-bind:disabled="loading">
                    
                @foreach($scopeOptions as $option)

                    <x-menu-item 
                        title="{{ $option['name'] }}" 
                        @click="
                            timeout = setTimeout(() => { loading = true }, 200);
                            $wire.changeScope('{{ $option['id'] }}').then(() => {
                                clearTimeout(timeout);
                                loading = false;
                            })
                        "
                    />
            
                @endforeach

            </x-dropdown>
        </div>
    </div>

    <div
        class="h-[280px] mb-5"
    >
        <x-ib-apex-chart :series-data="$chartSeries" :name="$name" />
    </div>

</x-card>