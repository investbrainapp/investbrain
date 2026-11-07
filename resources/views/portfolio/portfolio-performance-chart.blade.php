<?php

use App\Models\DailyChange;
use App\Models\Portfolio;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Livewire\Attributes\Lazy;
use Livewire\Attributes\Locked;
use Livewire\Volt\Component;

new #[Lazy] class extends Component
{
    // props
    public ?Portfolio $portfolio = null;

    #[Locked]
    public string $name = 'portfolio';

    #[Locked]
    public string $scope = 'YTD';

    #[Locked]
    public array $scopeOptions = [
        ['id' => '1M', 'name' => '1 month', 'method' => 'subMonths', 'args' => [1]],
        ['id' => '3M', 'name' => '3 months', 'method' => 'subMonths', 'args' => [3]],
        ['id' => 'YTD', 'name' => 'Year to date', 'method' => 'startOfYear', 'args' => []],
        ['id' => '1Y', 'name' => '1 year', 'method' => 'subYears', 'args' => [1]],
        ['id' => '3Y', 'name' => '3 years', 'method' => 'subYears', 'args' => [3]],
        ['id' => 'ALL', 'name' => 'All time', 'method' => null],
    ];

    // data
    #[Locked]
    public array $chartSeries;

    // methods
    public function mount()
    {
        $this->chartSeries = $this->generatePerformanceData();
    }

    public function placeholder()
    {
        return <<<'HTML'
        <div class="skeleton h-[395px] mb-5"></div>
        HTML;
    }

    public function generatePerformanceData()
    {
        $filterMethod = collect($this->scopeOptions)->where('id', $this->scope)->first();

        $dailyChangeQuery = DailyChange::query();

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

        // after the filters, so performance is only calculated for the portfolios shown
        $dailyChangeQuery->withDailyPerformance();

        $dailyChange = cache()->remember(
            'graph-'.$this->scope.'-'.(isset($this->portfolio) ? $this->portfolio->id : request()->user()->id),
            10,
            function () use ($dailyChangeQuery) {
                return $this->groupIntoPeriods($dailyChangeQuery->withMultipleDailyPerformance()->get());
            }
        );

        $marketValueData = [];
        $costBasisData = [];
        $marketGainData = [];
        $marketGainPercentages = [];

        foreach ($dailyChange as $data) {
            $date = $data->date;
            $marketGainData[] = [$date, round($data->total_market_gain, 2)];
            $marketGainPercentages[] = $data->total_cost_basis == 0
                ? null
                : round($data->total_market_gain / $data->total_cost_basis * 100, 2);
            $marketValueData[] = [$date, round($data->total_market_value, 2)];
            $costBasisData[] = [$date, round($data->total_cost_basis, 2)];

            // $dividendSeries[] = [$date, round($data->total_dividends_earned, 2)];
            // $realizedGainSeries[] = [$date, round($data->realized_gains, 2)];
        }

        return [
            'series' => [
                [
                    'name' => __('Market Gain'),
                    'data' => $marketGainData,
                ],
                [
                    'name' => __('Market Value'),
                    'data' => $marketValueData,
                    'hidden' => true,
                ],
                [
                    'name' => __('Cost Basis'),
                    'data' => $costBasisData,
                    'hidden' => true,
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

            // shown next to each value in the tooltip, in the same order as the series
            'percentages' => [
                $marketGainPercentages,
                $this->changeSinceStart($marketValueData),
                $this->changeSinceStart($costBasisData),
            ],
        ];
    }

    /**
     * Percentage change of each point from the first point in the range, or null when the range starts at zero
     *
     * @param  array<int, array{0: mixed, 1: float}>  $points
     * @return array<int, float|null>
     */
    protected function changeSinceStart(array $points): array
    {
        $first = $points[0][1] ?? 0;

        return array_map(
            fn (array $point) => $first == 0 ? null : round(($point[1] - $first) / abs($first) * 100, 2),
            $points
        );
    }

    /**
     * Keeps one point per week when the data covers more than a year, and one per month past three years.
     * The values are balances, so each period shows its last day rather than a sum.
     */
    protected function groupIntoPeriods(Collection $dailyChange): Collection
    {
        if ($dailyChange->isEmpty()) {
            return $dailyChange;
        }

        $first = Carbon::parse($dailyChange->first()->date);
        $last = Carbon::parse($dailyChange->last()->date);

        $period = match (true) {
            $first->lt($last->copy()->subYears(3)) => 'Y-m', // group by month
            $first->lt($last->copy()->subYear()) => 'o-W', // group by week
            default => null,
        };

        if ($period === null) {
            return $dailyChange;
        }

        return $dailyChange
            ->groupBy(fn ($day) => Carbon::parse($day->date)->format($period))
            ->map->last()
            ->values();
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

<x-ui.card class="mb-6">
    <div class="flex flex-col md:flex-row md:justify-between mb-2">
                    
        <div class="flex flex-col md:flex-row items-start md:items-center">
            
            <h2 class="text-xl mb-2 md:mb-0 md:mr-4">{{ __('Performance') }}</h2>

            <div id="chart-legend-{{ $name }}" class="flex space-between whitespace-nowrap mb-2 md:mb-0"></div>
            
        </div>
        
        <div class="flex items-center" x-data="{ loading: false }">
            {{-- <x-ui.button title="{{ __('Reset chart') }}" icon="o-arrow-path" class="btn-ghost btn-sm btn-circle mr-2" id="chart-reset-zoom-{{ $name }}" /> --}}

            <x-ui.loading x-show="loading" x-cloak class="text-gray-400 ml-2" />

            <x-ui.dropdown title="{{ __('Choose time period') }}" label="{{ $scope }}" class="btn-xs md:btn-sm btn-outline" x-bind:disabled="loading">
                    
                @foreach($scopeOptions as $option)

                    <x-ui.menu-item 
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
        <x-ui.apex-chart :series-data="$chartSeries" :name="$name" />
    </div>

</x-ui.card>