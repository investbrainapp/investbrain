<?php

use App\Models\DailyChange;
use App\Models\Portfolio;
use Livewire\Attributes\{Title, Rule};
use Livewire\Volt\Component;

new class extends Component {

    // props
    public ?Portfolio $portfolio;
    public String $name = 'portfolio';
    public String $scope = 'YTD';
    public Array $scopeOptions = [
        ['id' => '1M', 'name' => '1 month', 'method' => 'subMonths', 'args' => [1]],
        ['id' => '3M', 'name' => '3 months', 'method' => 'subMonths', 'args' => [3]],
        ['id' => 'YTD', 'name' => 'Year to date', 'method' => 'startOfYear', 'args' => []],
        ['id' => '1Y', 'name' => '1 year', 'method' => 'subYears', 'args' => [1]],
        ['id' => '3Y', 'name' => '3 years', 'method' => 'subYears', 'args' => [3]],
        ['id' => 'ALL', 'name' => 'All time', 'method' => null]
    ];

    // data
    public Array $chartSeries;

    // methods
    public function mount() 
    {
        $this->chartSeries = $this->generatePerformanceData();
    }

    public function generatePerformanceData()
    {
        $filterMethod = collect($this->scopeOptions)->where('id', $this->scope)->first();

        $dailyChangeQuery = DailyChange::query();

        if (isset($this->portfolio)) {
            
            $dailyChangeQuery->portfolio($this->portfolio->id);

        } else {
            
            $dailyChangeQuery->selectRaw('
                date, 
                SUM(total_market_value) as total_market_value, 
                SUM(total_cost_basis) as total_cost_basis, 
                SUM(total_gain) as total_gain,
                --SUM(realized_gains) as realized_gains,
                --SUM(total_dividends_earned) as total_dividends_earned
            ')->groupBy('date');

        }

        if ($filterMethod['method']) {
            
            $dailyChangeQuery->whereDate('date', '>=', now()->{$filterMethod['method']}(...$filterMethod['args']));
        }
        
        $dailyChange = $dailyChangeQuery->get();

        return [
            'series' => [
                [
                    'name' => __('Market Value'),
                    'data' => $dailyChange->map(fn($data) => [$data->date, $data->total_market_value])->toArray(),
                ],
                [
                    'name' => __('Cost Basis'),
                    'data' => $dailyChange->map(fn($data) => [$data->date, $data->total_cost_basis])->toArray(),
                ],
                [
                    'name' => __('Market Gain'),
                    'data' => $dailyChange->map(fn($data) => [$data->date, $data->total_gain])->toArray()
                ],
                
                // [
                //     'name' => __('Dividends Earned'),
                //     'data' => $dailyChange->map(fn($data) => [$data->date, $data->total_dividends_earned])->toArray()
                // ],
                // [
                //     'name' => __('Realized Gains'),
                //     'data' => $dailyChange->map(fn($data) => [$data->date, $data->realized_gains])->toArray()
                // ],
            ]
        ];
    }

    public function changeScope($scope)
    {
        $this->scope = $scope;

        $this->chartSeries = $this->generatePerformanceData();
    }

    public function getScopeName($scope)
    {
        return collect($this->scopeOptions)->where('id', $scope)->first()['name'];
    }

}; ?>

<x-card class="bg-slate-100 dark:bg-base-200 rounded-lg mb-6">
    <div class="flex justify-between items-center mb-2">
                    
        <div class="flex items-center">
            
            <h2 class="text-xl mr-4">{{ __('Performance') }}</h2>
            <div id="chart-legend-{{ $name }}" class="flex space-between"></div>
            
        </div>
        
        <div class="flex items-center" x-data="{ loading: false }">
            {{-- <x-button title="{{ __('Reset chart') }}" icon="o-arrow-path" class="btn-ghost btn-sm btn-circle mr-2" id="chart-reset-zoom-{{ $name }}" /> --}}

            <x-loading x-show="loading" x-cloak class="text-gray-400 ml-2" />

            <x-dropdown title="{{ __('Choose time period') }}" label="{{ $scope }}" class="btn-ghost btn-sm" x-bind:disabled="loading">
                    
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