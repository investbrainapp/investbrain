<?php

use App\Models\DailyChange;
use App\Models\Portfolio;
use Livewire\Volt\Component;

new class extends Component
{
    // props
    public ?Portfolio $portfolio = null;

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
            10,
            function () use ($dailyChangeQuery) {
                return $dailyChangeQuery->withMultipleDailyPerformance()->get();
            }
        );

        $marketValueData = [];
        $costBasisData = [];
        $marketGainData = [];

        foreach ($dailyChange as $data) {
            $date = $data->date;
            $marketValueData[] = [$date, round($data->total_market_value, 2)];
            $costBasisData[] = [$date, round($data->total_cost_basis, 2)];
            $marketGainData[] = [$date, round($data->total_market_gain, 2)];
            // $dividendSeries[] = [$date, round($data->total_dividends_earned, 2)];
            // $realizedGainSeries[] = [$date, round($data->realized_gains, 2)];
        }

        return [
            'series' => [
                [
                    'name' => __('Market Value'),
                    'data' => $marketValueData,
                ],
                [
                    'name' => __('Cost Basis'),
                    'data' => $costBasisData,
                ],
                [
                    'name' => __('Market Gain'),
                    'data' => $marketGainData,
                ],

                // [
                //     'name' => __('Dividends Earned'),
                //     'data' => $dividendSeries
                // ],
                // [
                //     'name' => __('Realized Gains'),
                //     'data' => $realizedGainSeries
                // ],
            ],
        ];
    }

    public function changeScope($scope)
    {
        $this->scope = $scope;

        cache()->forget('graph-'.$this->scope.'-'.(isset($this->portfolio) ? $this->portfolio->id : request()->user()->id));

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