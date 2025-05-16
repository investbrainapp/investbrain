<?php

declare(strict_types=1);

namespace Tests;

use App\Interfaces\MarketData\FakeMarketData;
use App\Interfaces\MarketData\Types\Quote;
use App\Models\BackupImport;
use App\Models\Currency;
use App\Models\CurrencyRate;
use App\Models\DailyChange;
use App\Models\Holding;
use App\Models\Portfolio;
use App\Models\Transaction;
use App\Models\User;
use Carbon\CarbonPeriod;
use Database\Seeders\CurrencySeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Artisan;
use Investbrain\Frankfurter\Frankfurter;
use Mockery;

class MultiCurrencyTest extends TestCase
{
    use RefreshDatabase;

    public function test_can_seed_currencies()
    {
        Artisan::call('db:seed', [
            '--class' => CurrencySeeder::class,
            '--force' => true,
        ]);

        $this->assertEquals(19, Currency::count('currency'));
    }

    public function test_perists_rates_that_after_historic_lookup()
    {
        $mockClient = Mockery::mock(\Investbrain\Frankfurter\FrankfurterClient::class);

        Frankfurter::shouldReceive('setSymbols')
            ->andReturn($mockClient);

        $response = [
            'AAA' => rand(10, 150) / 1000,
            'BBB' => rand(10, 150) / 1000,
            'ZZZ' => rand(10, 150) / 1000,
        ];

        $mockClient->shouldReceive('historical')
            ->andReturn([
                'date' => now()->toDateString(),
                'rates' => $response,
            ]);

        CurrencyRate::historic('ZZZ', now()->toDateString());

        $count = CurrencyRate::count('date');

        $this->assertEquals(3, $count);
    }

    public function test_perists_rates_that_after_time_series_lookup()
    {

        $startDate = now()->subYear();
        $response = [];
        $period = CarbonPeriod::create($startDate, now());

        foreach ($period->copy() as $date) {
            $response[$date->toDateString()] = [
                'AAA' => rand(10, 150) / 1000,
                'BBB' => rand(10, 150) / 1000,
                'ZZZ' => rand(10, 150) / 1000,
            ];
        }

        Frankfurter::expects('setSymbols')
            ->andReturnSelf();
        Frankfurter::expects('timeSeries')
            ->andReturn([
                'start_date' => $startDate->toDateString(),
                'end_date' => now()->toDateString(),
                'rates' => $response,
            ]);

        CurrencyRate::timeSeriesRates('ZZZ', $startDate);

        $count = CurrencyRate::count('date');

        $this->assertEquals(1098, $count);
    }

    public function test_can_convert_currency_to_base()
    {
        CurrencyRate::create(['currency' => 'INR', 'date' => now(), 'rate' => 85]);
        CurrencyRate::create(['currency' => 'USD', 'date' => now(), 'rate' => 1]);

        $converted = Currency::convert(85, 'INR', 'USD');

        $this->assertEquals(1, $converted);
    }

    public function test_can_convert_currency_between_non_base_rate()
    {

        CurrencyRate::create(['currency' => 'INR', 'date' => now(), 'rate' => 85]);
        CurrencyRate::create(['currency' => 'EUR', 'date' => now(), 'rate' => .96]);

        $converted = Currency::convert(85, 'INR', 'EUR');

        $this->assertEquals(0.96, $converted);
    }

    public function test_can_convert_currency_from_base_rate()
    {

        CurrencyRate::create(['currency' => 'USD', 'date' => now(), 'rate' => 1]);
        CurrencyRate::create(['currency' => 'EUR', 'date' => now(), 'rate' => .96]);

        $converted = Currency::convert(1, 'USD', 'EUR');

        $this->assertEquals(0.96, $converted);
    }

    public function test_can_sync_currency_rates_during_migration()
    {

        $this->actingAs($user = User::factory()->create());

        $portfolio = Portfolio::factory()->create();
        $transaction = Transaction::factory()->buy()->lastYear()->portfolio($portfolio->id)->symbol('ACME')->create();

        Frankfurter::expects('setSymbols')
            ->andReturnSelf();
        Frankfurter::expects('timeSeries')
            ->andReturn(['rates' => [
                now()->subDays(3)->toDateString() => [
                    'ZZZ' => .01,
                ],
                now()->subDays(2)->toDateString() => [
                    'ZZZ' => .01,
                ],
                now()->subDays(1)->toDateString() => [
                    'ZZZ' => .01,
                ],
                now()->toDateString() => [
                    'ZZZ' => .01,
                ],
            ]]);

        CurrencyRate::timeSeriesRates(
            '', // use fake currency to force
            Transaction::min('date')
        );
    }

    public function test_nothing_to_sync_during_migration_on_new_install()
    {

        Frankfurter::expects('setSymbols')
            ->times(0);
        Frankfurter::expects('timeSeries')
            ->times(0);

        CurrencyRate::timeSeriesRates(
            '', // use fake currency to force
            Transaction::min('date')
        );
    }

    public function test_can_get_historic_exchange_rates()
    {

        $mockClient = Mockery::mock(\Investbrain\Frankfurter\FrankfurterClient::class);

        Frankfurter::shouldReceive('setSymbols')
            ->andReturn($mockClient);

        $date = now()->subDays(2);

        $response = [
            'AAA' => rand(10, 150) / 1000,
            'BBB' => rand(10, 150) / 1000,
            'ZZZ' => rand(10, 150) / 1000,
        ];

        $mockClient->shouldReceive('historical')
            ->andReturn([
                'date' => $date->toDateString(),
                'rates' => $response,
            ]);

        $rate = CurrencyRate::historic('ZZZ', $date);

        $this->assertEquals(
            $response['ZZZ'],
            $rate
        );
    }

    public function test_can_get_time_series_rates()
    {

        $start = now()->subWeeks(2);
        $end = now();

        $period = CarbonPeriod::create($start, $end);

        // mock response from Frankfurter
        $results = [];
        collect($period->copy()->filter('isWeekday'))->each(function ($date) use (&$results) {
            $date = $date->toDateString();

            $results[$date] = [
                'ZZZ' => random_int(10, 150) / 1000,
            ];
        });

        Frankfurter::expects('setSymbols')
            ->andReturnSelf();
        Frankfurter::expects('timeSeries')
            ->andReturn(['rates' => $results]);

        $result = CurrencyRate::timeSeriesRates('ZZZ', $start, $end);
        $this->assertEquals(count($period) - 1, count($result));

        $result = CurrencyRate::all();
        $this->assertEquals(count($period), count($result));
    }

    public function test_can_get_time_series_rates_with_null_currency()
    {

        $start = now()->subWeeks(2);
        $end = now();

        $period = CarbonPeriod::create($start, $end);

        // mock response from Frankfurter
        $results = [];
        collect($period->copy()->filter('isWeekday'))->each(function ($date) use (&$results) {
            $date = $date->toDateString();

            $results[$date] = [
                'FOO' => random_int(10, 150) / 1000,
            ];
        });

        Frankfurter::expects('setSymbols')
            ->andReturnSelf();
        Frankfurter::expects('timeSeries')
            ->andReturn(['rates' => $results]);

        $result = CurrencyRate::timeSeriesRates(null, $start, $end);
        $this->assertEquals(0, count($result));

        $result = CurrencyRate::all();
        $this->assertEquals(count($period), count($result));
    }

    public function test_time_series_rate_calls_are_chunked()
    {

        $start = now()->subYears(5);
        $end = now();

        $results = [];

        $period = CarbonPeriod::create($start, $end);

        collect($period->copy()->filter('isWeekday'))->each(function ($date) use (&$results) {
            $date = $date->toDateString();

            $results[$date] = [
                'ZZZ' => random_int(10, 150) / 1000,
            ];
        });

        Frankfurter::expects('setSymbols')
            ->andReturnSelf();
        Frankfurter::expects('timeSeries')
            ->andReturn(['rates' => $results]);

        CurrencyRate::timeSeriesRates('ZZZ', $start, $end);
    }

    public function test_can_handle_aliases_for_historic_rates()
    {
        $mockClient = Mockery::mock(\Investbrain\Frankfurter\FrankfurterClient::class);

        Frankfurter::shouldReceive('setSymbols')
            ->andReturn($mockClient);

        $adjustment = 100;
        $date = now()->subDays(5);

        config()->set(
            'investbrain.currency_aliases',
            ['ZZZ' => ['alias_of' => 'YYY', 'label' => 'Test Alias', 'adjustment' => $adjustment]]
        );

        $response = [
            'AAA' => rand(10, 150) / 1000,
            'BBB' => rand(10, 150) / 1000,

            // ZZZ should be created as an alias of YYY
            'YYY' => rand(10, 150) / 1000,
        ];

        $mockClient->shouldReceive('historical')
            ->andReturn([
                'date' => $date->toDateString(),
                'rates' => $response,
            ]);

        $rate = CurrencyRate::historic('ZZZ', $date);

        $this->assertEquals(
            $response['YYY'] * $adjustment,
            $rate
        );
    }

    public function test_can_handle_aliases_for_time_series_rates()
    {
        $start = now()->subWeeks(2);
        $end = now();
        $adjustment = 100;

        config()->set(
            'investbrain.currency_aliases',
            ['ZZZ' => ['alias_of' => 'YYY', 'label' => 'Test Alias', 'adjustment' => $adjustment]]
        );

        $results = [];

        $period = CarbonPeriod::create($start, $end);

        collect($period->copy()->filter('isWeekday'))->each(function ($date) use (&$results) {
            $date = $date->toDateString();

            $results[$date] = [
                'AAA' => rand(10, 150) / 1000,
                'BBB' => rand(10, 150) / 1000,

                // ZZZ should be created as an alias of YYY
                'YYY' => rand(10, 150) / 1000,
            ];
        });

        Frankfurter::expects('setSymbols')
            ->andReturnSelf();
        Frankfurter::expects('timeSeries')
            ->andReturn(['rates' => $results]);

        $result = CurrencyRate::timeSeriesRates('ZZZ', $start, $end);

        $this->assertEquals(
            Arr::last($results)['YYY'] * $adjustment,
            Arr::last($result)
        );
    }

    public function test_can_buy_in_different_currency()
    {

        $this->actingAs($user = User::factory()->create());

        $date = now()->subYear();
        $cost_basis = 100; // in ZZZ currency
        $rate = .78; // ZZZ to USD (base and currency ACME is traded in)

        CurrencyRate::create([
            'currency' => 'ZZZ',
            'date' => $date,
            'rate' => $rate,
        ]);

        $portfolio = Portfolio::factory()->create();
        $transaction = Transaction::factory()
            ->buy()
            ->date($date)
            ->costBasis($cost_basis)
            ->currency('ZZZ')
            ->portfolio($portfolio->id)
            ->symbol('ACME')
            ->create();

        $this->assertEquals($cost_basis * (1 / $rate), $transaction->cost_basis);
    }

    public function test_can_sell_in_different_currency()
    {

        $this->actingAs($user = User::factory()->create());

        $date = now()->subMonth();
        $sale_price = 100; // in ZZZ currency
        $rate = .78; // ZZZ to USD (base and currency ACME is traded in)

        CurrencyRate::create([
            'currency' => 'ZZZ',
            'date' => $date,
            'rate' => $rate,
        ]);

        $portfolio = Portfolio::factory()->create();
        Transaction::factory()->buy()->yearsAgo()->portfolio($portfolio->id)->symbol('ACME')->create();
        $sell_transaction = Transaction::factory()
            ->sell()
            ->date($date)
            ->salePrice($sale_price)
            ->currency('ZZZ')
            ->portfolio($portfolio->id)
            ->symbol('ACME')
            ->create();

        $this->assertEquals($sale_price * (1 / $rate), $sell_transaction->sale_price);
    }

    public function test_holdings_calculations_for_multiple_currencies()
    {

        $fiveWeeksAgo = now()->subWeeks(5)->toDateString();
        $fiveDaysAgo = now()->subDays(5)->toDateString();
        $yearAgo = now()->subYear()->toDateString();
        $monthAgo = now()->subMonth()->toDateString();
        $today = now()->toDateString();

        $this->actingAs($user = User::factory()->create());

        $portfolio = Portfolio::factory()->create();

        // create some local currency transaction history
        Transaction::factory(5)->buy()->costBasis(110)->date($fiveWeeksAgo)->portfolio($portfolio->id)->symbol('ACME')->create();
        Transaction::factory()->sell()->salePrice(219.99)->date($fiveDaysAgo)->portfolio($portfolio->id)->symbol('ACME')->create();

        // mock foreign quotes
        $fakeMock = Mockery::mock(FakeMarketData::class);
        $fakeMock->shouldReceive('quote')
            ->andReturn(new Quote([
                'name' => 'British Company Ltd',
                'symbol' => 'BAR',
                'currency' => 'GBP',
                'market_value' => 109.99,
            ]));
        $this->app->instance(FakeMarketData::class, $fakeMock);

        // add currency rates
        $rates = collect([[
            'currency' => 'GBP',
            'rate' => .79,
            'date' => $fiveWeeksAgo,
        ], [
            'currency' => 'GBP',
            'rate' => .81,
            'date' => $fiveDaysAgo,
        ], [
            'currency' => 'GBP',
            'rate' => .89,
            'date' => $yearAgo,
        ], [
            'currency' => 'GBP',
            'rate' => .92,
            'date' => $monthAgo,
        ], [
            'currency' => 'GBP',
            'rate' => .85,
            'date' => now()->subDay()->toDateString(),
        ], [
            'currency' => 'GBP',
            'rate' => .85,
            'date' => $today,
        ], [
            'currency' => 'GBP',
            'rate' => .85,
            'date' => now()->addDay()->toDateString(),
        ]]);
        $rates->each(fn ($rate) => CurrencyRate::create($rate));

        // create some foreign currency transaction history
        Transaction::factory(10)->buy()->costBasis(100)->currency('GBP')->date($yearAgo)->portfolio($portfolio->id)->symbol('BAR')->create();
        Transaction::factory(5)->sell()->salePrice(150)->currency('GBP')->date($monthAgo)->portfolio($portfolio->id)->symbol('BAR')->create();

        $metrics = Holding::query()
            ->portfolio($portfolio->id)
            ->getPortfolioMetrics();

        $this->assertEqualsWithDelta(1001.79, $metrics->get('total_cost_basis'), 0.01);
        $this->assertEqualsWithDelta(381.73, $metrics->get('realized_gain_dollars'), 0.01);
        $this->assertEqualsWithDelta(1567.76, $metrics->get('total_market_value'), 0.01);

        // switch user display currency
        $user->options = array_merge($user->options ?? [], [
            'display_currency' => 'GBP',
        ]);
        $user->save();

        $metrics = Holding::query()
            ->portfolio($portfolio->id)
            ->getPortfolioMetrics();

        $this->assertEqualsWithDelta(847.6, $metrics->get('total_cost_basis'), 0.01);
        $this->assertEqualsWithDelta(339.1, $metrics->get('realized_gain_dollars'), 0.01);
        $this->assertEqualsWithDelta(1332.59, $metrics->get('total_market_value'), 0.01);
    }

    public function test_portfolio_daily_change_from_multiple_currencies()
    {

        $this->actingAs($user = User::factory()->create());

        $portfolio = Portfolio::factory()->create();
        Transaction::factory(5)->buy()->lastMonth()->portfolio($portfolio->id)->symbol('AAPL')->create();
        Transaction::factory(5)->buy()->lastMonth()->portfolio($portfolio->id)->symbol('ACME')->create();
        Transaction::factory()->sell()->recent()->portfolio($portfolio->id)->symbol('ACME')->create();

        $portfolio->syncDailyChanges();

        $dailyChange = DailyChange::withDailyPerformance()
            ->portfolio($portfolio->id)
            ->get()
            ->sortBy('date')
            ->groupBy('date')
            ->map(function ($group) {
                return (object) [
                    'date' => $group->first()->date->toDateString(),
                    'total_market_value' => $group->sum('total_market_value'),
                    'total_cost_basis' => $group->sum('total_cost_basis'),
                    'total_gain' => $group->sum('total_gain'),
                    'realized_gain_dollars' => $group->sum('realized_gain_dollars'),
                    'total_dividends_earned' => $group->sum('total_dividends_earned'),
                ];
            });

        $metrics = Holding::query()
            ->portfolio($portfolio->id)
            ->getPortfolioMetrics();

        $this->assertEqualsWithDelta($metrics->get('total_market_value'), $dailyChange->last()->total_market_value, 0.01);
        $this->assertEqualsWithDelta($metrics->get('total_cost_basis'), $dailyChange->last()->total_cost_basis, 0.01);
        $this->assertEqualsWithDelta($metrics->get('realized_gain_dollars'), $dailyChange->last()->realized_gain_dollars, 0.01);
        $this->assertEqualsWithDelta($metrics->get('total_market_value') - $metrics->get('total_cost_basis'), $dailyChange->last()->total_gain, 0.01);

        // switch user display currency
        $user->options = array_merge($user->options ?? [], [
            'display_currency' => 'GBP',
        ]);
        $user->save();

        $dailyChange = DailyChange::withDailyPerformance()
            ->portfolio($portfolio->id)
            ->get()
            ->sortBy('date')
            ->groupBy('date')
            ->map(function ($group) {
                return (object) [
                    'date' => $group->first()->date->toDateString(),
                    'total_market_value' => $group->sum('total_market_value'),
                    'total_cost_basis' => $group->sum('total_cost_basis'),
                    'total_gain' => $group->sum('total_gain'),
                    'realized_gain_dollars' => $group->sum('realized_gain_dollars'),
                    'total_dividends_earned' => $group->sum('total_dividends_earned'),
                ];
            });

        $metrics = Holding::query()
            ->portfolio($portfolio->id)
            ->getPortfolioMetrics();

        $this->assertEqualsWithDelta($metrics->get('total_market_value'), $dailyChange->last()->total_market_value, 0.01);
        $this->assertEqualsWithDelta($metrics->get('total_cost_basis'), $dailyChange->last()->total_cost_basis, 0.01);
        $this->assertEqualsWithDelta($metrics->get('realized_gain_dollars'), $dailyChange->last()->realized_gain_dollars, 0.01);
        $this->assertEqualsWithDelta($metrics->get('total_market_value') - $metrics->get('total_cost_basis'), $dailyChange->last()->total_gain, 0.01);
    }

    public function test_multi_currency_import_calculates_correct_holding_data(): void
    {
        $this->actingAs($user = User::factory()->create());

        Frankfurter::expects('setSymbols')
            ->zeroOrMoreTimes()
            ->andReturnSelf();
        Frankfurter::expects('timeSeries')
            ->zeroOrMoreTimes()
            ->andReturn(['rates' => [
                now()->subDays(3)->toDateString() => [
                    'ZZZ' => .01,
                ],
                now()->subDays(2)->toDateString() => [
                    'ZZZ' => .01,
                ],
                now()->subDays(1)->toDateString() => [
                    'ZZZ' => .01,
                ],
                now()->toDateString() => [
                    'ZZZ' => .01,
                ],
            ]]);
        Frankfurter::expects('historical')
            ->zeroOrMoreTimes()
            ->andReturn([
                'rates' => [
                    'GBP' => .89,
                ],
            ]);

        BackupImport::create([
            'user_id' => auth()->user()->id,
            'path' => __DIR__.'/0000_00_00_import_multi_curr_test.xlsx',
        ]);

        $this->assertContains('AAPL', $user->holdings->pluck('symbol'));
        $this->assertContains('BP.L', $user->holdings->pluck('symbol'));
        $this->assertEquals(17, $user->holdings->sum('quantity'));
        $this->assertEqualsWithDelta(371.42, $user->holdings->sum('average_cost_basis'), 0.01);
    }
}
