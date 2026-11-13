<?php

declare(strict_types=1);

namespace Tests;

use App\Interfaces\MarketData\FakeMarketData;
use App\Interfaces\MarketData\Types\Quote;
use App\Models\BackupImport;
use App\Models\Currency;
use App\Models\CurrencyRate;
use App\Models\DailyChange;
use App\Models\Dividend;
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
use Investbrain\Frankfurter\FrankfurterClient;
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
        $mockClient = Mockery::mock(FrankfurterClient::class);

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

    public function test_rate_requests_are_limited_to_the_dates_frankfurter_has(): void
    {
        Frankfurter::expects('setSymbols')->andReturnSelf();
        Frankfurter::expects('timeSeries')
            ->withArgs(fn ($start, $end) => $start->toDateString() === '1999-01-04' && $end->toDateString() === now()->toDateString())
            ->andReturn(['rates' => []]);

        CurrencyRate::timeSeriesRates('ZZZ', '1000-01-01', now()->addYears(5));
    }

    public function test_base_currency_rates_are_limited_to_the_dates_frankfurter_has(): void
    {
        $rates = CurrencyRate::timeSeriesRates(config('investbrain.base_currency'), '1000-01-01', now()->addYears(5));

        $this->assertSame('1999-01-04', array_key_first($rates));
        $this->assertSame(now()->toDateString(), array_key_last($rates));
    }

    public function test_no_rates_are_requested_for_a_range_entirely_in_the_future(): void
    {
        Frankfurter::shouldReceive('timeSeries')->never();

        $this->assertSame([], CurrencyRate::timeSeriesRates('ZZZ', now()->addDays(2), now()->addDays(5)));
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

        $mockClient = Mockery::mock(FrankfurterClient::class);

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

        // every day from the start date is returned, including the first
        $result = CurrencyRate::timeSeriesRates('ZZZ', $start, $end);
        $this->assertEquals(count($period), count($result));

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

    public function test_can_get_time_series_rates_with_currencies()
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
                'BAR' => random_int(10, 150) / 1000,
            ];
        });

        Frankfurter::expects('setSymbols')
            ->andReturnSelf();
        Frankfurter::expects('timeSeries')
            ->andReturn(['rates' => $results]);

        $result = CurrencyRate::timeSeriesRates(null, $start, $end);
        $this->assertEquals(0, count($result));

        $result = CurrencyRate::all();
        $this->assertEquals(count($period) * 2, count($result));
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
        $mockClient = Mockery::mock(FrankfurterClient::class);

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
        // the shares sold cost what they did in USD on the day they were bought, not the day they were sold
        $this->assertEqualsWithDelta(363.41, $metrics->get('realized_gain_dollars'), 0.01);
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
        $this->assertEqualsWithDelta(322.24, $metrics->get('realized_gain_dollars'), 0.01);
        $this->assertEqualsWithDelta(1332.59, $metrics->get('total_market_value'), 0.01);
    }

    public function test_portfolio_daily_change_from_multiple_currencies()
    {

        $this->actingAs($user = User::factory()->create());

        $monthAgo = now()->subMonth()->toDateString();
        $fiveWeeksAgo = now()->subWeeks(5)->toDateString();
        $fiveDaysAgo = now()->subDays(5)->toDateString();

        $portfolio = Portfolio::factory()->create();
        Transaction::factory(5)->buy()->costBasis(100)->date($monthAgo)->portfolio($portfolio->id)->symbol('AAPL')->create();
        Transaction::factory(5)->buy()->costBasis(190)->date($fiveWeeksAgo)->portfolio($portfolio->id)->symbol('ACME')->create();
        Transaction::factory()->sell()->date($fiveDaysAgo)->portfolio($portfolio->id)->symbol('ACME')->create();

        $portfolio->syncDailyChanges();

        $dailyChange = DailyChange::withDailyPerformance()
            ->portfolio($portfolio->id)
            ->get();

        $metrics = Holding::query()
            ->portfolio($portfolio->id)
            ->getPortfolioMetrics();

        $this->assertEqualsWithDelta($metrics->get('total_market_value'), $dailyChange->last()->total_market_value, 0.01);
        $this->assertEqualsWithDelta($metrics->get('total_cost_basis'), $dailyChange->last()->total_cost_basis, 0.01);
        $this->assertEqualsWithDelta(Holding::get()->sum('total_cost_basis'), $dailyChange->last()->total_cost_basis, 0.01);
        $this->assertEqualsWithDelta($metrics->get('realized_gain_dollars'), $dailyChange->last()->realized_gain_dollars, 0.01);
        $this->assertEqualsWithDelta($metrics->get('total_market_value') - $metrics->get('total_cost_basis'), $dailyChange->last()->total_market_gain, 0.01);

        // add a rate for every calendar day, as the daily currency refresh does in production
        foreach (CarbonPeriod::create($fiveWeeksAgo, now()->addDay()) as $date) {
            CurrencyRate::create([
                'currency' => 'GBP',
                'rate' => .88,
                'date' => $date->toDateString(),
            ]);
        }

        // switch user display currency
        $user->options = array_merge($user->options ?? [], [
            'display_currency' => 'GBP',
        ]);
        $user->save();

        $dailyChange = DailyChange::withDailyPerformance()
            ->portfolio($portfolio->id)
            ->get();

        $metrics = Holding::query()
            ->portfolio($portfolio->id)
            ->getPortfolioMetrics();

        $this->assertEqualsWithDelta($metrics->get('total_market_value'), $dailyChange->last()->total_market_value, 0.01);
        $this->assertEqualsWithDelta($metrics->get('total_cost_basis'), $dailyChange->last()->total_cost_basis, 0.01);
        $this->assertEqualsWithDelta($metrics->get('realized_gain_dollars'), $dailyChange->last()->realized_gain_dollars, 0.01);
        $this->assertEqualsWithDelta($metrics->get('total_market_value') - $metrics->get('total_cost_basis'), $dailyChange->last()->total_market_gain, 0.01);
    }

    public function test_multi_currency_import_calculates_correct_holding_data(): void
    {
        $this->actingAs($user = User::factory()->create());

        Artisan::call('db:seed', [
            '--class' => CurrencySeeder::class,
            '--force' => true,
        ]);

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

    public function test_portfolio_metrics_count_same_day_transactions_once(): void
    {
        $this->actingAs(User::factory()->create(['options' => ['display_currency' => 'EUR']]));

        $portfolio = Portfolio::factory()->create();

        $firstPurchase = now()->subMonths(2)->toDateString();
        $secondPurchase = now()->subMonth()->toDateString();

        CurrencyRate::create(['currency' => 'EUR', 'rate' => 0.9, 'date' => $firstPurchase]);
        CurrencyRate::create(['currency' => 'EUR', 'rate' => 0.8, 'date' => $secondPurchase]);

        Transaction::factory()->buy(10)->costBasis(100)->date($firstPurchase)->portfolio($portfolio->id)->symbol('ACME')->create();
        Transaction::factory()->buy(10)->costBasis(120)->date($secondPurchase)->portfolio($portfolio->id)->symbol('ACME')->create();

        // sold the same day as the second purchase, at the average cost basis of 110
        Transaction::factory()->sell(5)->salePrice(150)->date($secondPurchase)->portfolio($portfolio->id)->symbol('ACME')->create();

        $metrics = Holding::query()->portfolio($portfolio->id)->getPortfolioMetrics();

        // (150 - 110) * 5 at the 0.8 rate on the day of the sale
        $this->assertEqualsWithDelta(160, $metrics->get('realized_gain_dollars'), 0.01);

        // purchases cost 10 * 100 * 0.9 + 10 * 120 * 0.8 = 1860 for 20 shares, of which 15 are still owned
        $this->assertEqualsWithDelta(1395, $metrics->get('total_cost_basis'), 0.01);
    }

    public function test_portfolio_metrics_convert_cost_basis_at_the_rates_shares_still_owned_were_bought_at(): void
    {
        $this->actingAs(User::factory()->create(['options' => ['display_currency' => 'EUR']]));

        $portfolio = Portfolio::factory()->create();

        $firstPurchase = now()->subMonths(3)->toDateString();
        $soldOut = now()->subMonths(2)->toDateString();
        $secondPurchase = now()->subMonth()->toDateString();

        CurrencyRate::create(['currency' => 'EUR', 'rate' => 0.9, 'date' => $firstPurchase]);
        CurrencyRate::create(['currency' => 'EUR', 'rate' => 0.85, 'date' => $soldOut]);
        CurrencyRate::create(['currency' => 'EUR', 'rate' => 0.8, 'date' => $secondPurchase]);

        Transaction::factory()->buy(10)->costBasis(100)->date($firstPurchase)->portfolio($portfolio->id)->symbol('ACME')->create();
        Transaction::factory()->sell(10)->salePrice(150)->date($soldOut)->portfolio($portfolio->id)->symbol('ACME')->create();
        Transaction::factory()->buy(10)->costBasis(120)->date($secondPurchase)->portfolio($portfolio->id)->symbol('ACME')->create();

        $metrics = Holding::query()->portfolio($portfolio->id)->getPortfolioMetrics();

        // only the shares bought after selling out count, at the 0.8 rate they were bought at
        $this->assertEqualsWithDelta(960, $metrics->get('total_cost_basis'), 0.01);
    }

    public function test_portfolio_metrics_convert_dividends_at_the_rate_on_the_dividend_date(): void
    {
        $this->actingAs(User::factory()->create(['options' => ['display_currency' => 'EUR']]));

        $portfolio = Portfolio::factory()->create();

        Transaction::factory()->buy(10)->yearsAgo()->portfolio($portfolio->id)->symbol('ACME')->create();

        $dividend = Dividend::create([
            'symbol' => 'ACME',
            'date' => now()->subMonth()->toDateString(),
            'dividend_amount' => 2,
        ]);

        // stored the way rates from the currency provider are, as plain dates
        CurrencyRate::insert([
            'currency' => 'EUR',
            'rate' => 0.5,
            'date' => $dividend->date->toDateString(),
        ]);

        $metrics = Holding::query()->portfolio($portfolio->id)->getPortfolioMetrics();

        $this->assertEqualsWithDelta(10 * 2 * 0.5, $metrics->get('total_dividends_earned'), 0.01);
    }
}
