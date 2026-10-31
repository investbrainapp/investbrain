<?php

use App\Models\MarketDataProviderFailure;
use App\Models\MarketDataProviderMetric;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Number;
use Livewire\Attributes\Computed;
use Livewire\Volt\Component;

new class extends Component
{
    /**
     * Selectable time periods and how many hours each one covers.
     */
    public const RANGES = [
        '24h' => ['name' => 'Last 24 hours', 'hours' => 24],
        '7d' => ['name' => 'Last 7 days', 'hours' => 24 * 7],
        '30d' => ['name' => 'Last 30 days', 'hours' => 24 * 30],
    ];

    // props
    public string $range = '24h';

    // methods
    public function mount(): void
    {
        Gate::authorize('admin');
    }

    public function since(): CarbonInterface
    {
        return now()->subHours((self::RANGES[$this->range] ?? self::RANGES['24h'])['hours']);
    }

    #[Computed]
    public function providers(): Collection
    {
        return MarketDataProviderMetric::since($this->since())
            ->withTotals()
            ->orderBy('provider')
            ->orderBy('method')
            ->get();
    }

    #[Computed]
    public function totals(): MarketDataProviderMetric
    {
        return new MarketDataProviderMetric([
            'success_count' => $this->providers->sum('success_count'),
            'failure_count' => $this->providers->sum('failure_count'),
            'fallback_count' => $this->providers->sum('fallback_count'),
            'total_duration_ms' => $this->providers->sum('total_duration_ms'),
        ]);
    }

    #[Computed]
    public function latestFailures(): Collection
    {
        return MarketDataProviderFailure::since($this->since())
            ->latestForEachProvider()
            ->get()
            ->keyBy(fn (MarketDataProviderFailure $failure) => $failure->provider.'.'.$failure->method);
    }

    #[Computed]
    public function recentFailures(): Collection
    {
        return MarketDataProviderFailure::since($this->since())
            ->latest()
            ->latest('id')
            ->limit(25)
            ->get();
    }

    #[Computed]
    public function chartSeries(): array
    {
        $calls = [];
        $failures = [];

        foreach (MarketDataProviderMetric::timeline($this->since()) as $bucket => $counts) {
            $timestamp = CarbonImmutable::parse($bucket)->getTimestampMs();

            $calls[] = [$timestamp, $counts['calls']];
            $failures[] = [$timestamp, $counts['failures']];
        }

        return [
            'series' => [
                ['name' => __('Calls'), 'data' => $calls],
                ['name' => __('Failures'), 'data' => $failures],
            ],
            'colors' => ['#3185FC', '#B74F6F'],
        ];
    }

    public function formatRate(?float $rate): string
    {
        return $rate === null ? '—' : Number::percentage($rate, 1);
    }

    public function formatDuration(?float $milliseconds): string
    {
        return $milliseconds === null ? '—' : Number::format($milliseconds, 0).' ms';
    }
}; ?>

<div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
    <x-ui.toolbar title="{{ __('Provider Health') }}">

        <x-ui.dropdown title="{{ __('Choose time period') }}" label="{{ __($this::RANGES[$range]['name'] ?? $this::RANGES['24h']['name']) }}" class="btn-sm btn-outline">
            @foreach ($this::RANGES as $id => $option)
                <x-ui.menu-item title="{{ __($option['name']) }}" wire:click="$set('range', '{{ $id }}')" />
            @endforeach
        </x-ui.dropdown>

        <x-ui.loading wire:loading class="text-gray-400 ml-2" />

    </x-ui.toolbar>

    <div class="grid grid-cols-2 sm:grid-cols-4 gap-5">
        <x-ui.card dense="true" sub-title="{{ __('Calls') }}">
            <div class="font-black text-xl"> {{ Number::format($this->totals->calls) }} </div>
        </x-ui.card>

        <x-ui.card dense="true" sub-title="{{ __('Success rate') }}">
            <div class="font-black text-xl"> {{ $this->formatRate($this->totals->success_rate) }} </div>
        </x-ui.card>

        <x-ui.card dense="true" sub-title="{{ __('Fallbacks') }}">
            <div class="font-black text-xl"> {{ Number::format($this->totals->fallback_count) }} </div>
        </x-ui.card>

        <x-ui.card dense="true" sub-title="{{ __('Average latency') }}">
            <div class="font-black text-xl"> {{ $this->formatDuration($this->totals->average_duration_ms) }} </div>
        </x-ui.card>
    </div>

    <x-ui.card class="mt-6">
        <div class="flex flex-col md:flex-row items-start md:items-center mb-2">
            <h2 class="text-xl mb-2 md:mb-0 md:mr-4">{{ __('Calls over time') }}</h2>

            <div id="chart-legend-provider-health" class="flex space-between whitespace-nowrap"></div>
        </div>

        <div class="h-[280px] mb-5">
            <x-ui.apex-chart :series-data="$this->chartSeries" name="provider-health" :currency="false" />
        </div>
    </x-ui.card>

    <x-ui.card title="{{ __('Providers') }}" class="mt-6">
        @if ($this->providers->isEmpty())
            <p class="text-gray-400 py-4">{{ __('Nothing to show here yet') }}</p>
        @else
            <div class="overflow-x-auto">
                <table class="table">
                    <thead>
                        <tr>
                            <th>{{ __('Provider') }}</th>
                            <th>{{ __('Method') }}</th>
                            <th class="text-right">{{ __('Calls') }}</th>
                            <th class="text-right">{{ __('Success rate') }}</th>
                            <th class="text-right">{{ __('Fallbacks') }}</th>
                            <th class="text-right">{{ __('Average latency') }}</th>
                            <th>{{ __('Last failure') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($this->providers as $metric)
                            @php
                                $latestFailure = $this->latestFailures->get($metric->provider.'.'.$metric->method);
                            @endphp
                            <tr wire:key="provider-{{ $metric->provider }}-{{ $metric->method }}">
                                <td class="font-medium">{{ $metric->provider }}</td>
                                <td>{{ $metric->method }}</td>
                                <td class="text-right">{{ Number::format($metric->calls) }}</td>
                                <td @class(['text-right', 'text-error' => $metric->failure_count > 0])>{{ $this->formatRate($metric->success_rate) }}</td>
                                <td class="text-right">{{ Number::format($metric->fallback_count) }}</td>
                                <td class="text-right">{{ $this->formatDuration($metric->average_duration_ms) }}</td>
                                <td class="text-gray-400" title="{{ $latestFailure?->error }}">{{ $latestFailure?->created_at->diffForHumans() ?? '—' }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </x-ui.card>

    <x-ui.card title="{{ __('Recent failures') }}" class="mt-6">
        @if ($this->recentFailures->isEmpty())
            <p class="text-gray-400 py-4">{{ __('No failures in this period') }}</p>
        @else
            <div class="overflow-x-auto">
                <table class="table">
                    <thead>
                        <tr>
                            <th>{{ __('Time') }}</th>
                            <th>{{ __('Provider') }}</th>
                            <th>{{ __('Method') }}</th>
                            <th>{{ __('Symbol') }}</th>
                            <th>{{ __('Error') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($this->recentFailures as $failure)
                            <tr wire:key="failure-{{ $failure->id }}">
                                <td class="whitespace-nowrap text-gray-400" title="{{ $failure->created_at->toDateTimeString() }}">{{ $failure->created_at->diffForHumans() }}</td>
                                <td class="font-medium">{{ $failure->provider }}</td>
                                <td>{{ $failure->method }}</td>
                                <td>{{ $failure->symbol ?? '—' }}</td>
                                <td class="max-w-md truncate" title="{{ $failure->error }}">{{ $failure->error }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </x-ui.card>
</div>
