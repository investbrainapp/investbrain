<?php

declare(strict_types=1);

namespace Tests;

use App\Interfaces\MarketData\FakeMarketData;
use App\Interfaces\MarketData\Types\Quote;
use App\Jobs\SyncCurrencyRatesJob;
use App\Models\Currency;
use App\Models\CurrencyRate;
use App\Models\MarketData;
use App\Models\Portfolio;
use App\Models\Transaction;
use App\Models\User;
use Carbon\CarbonPeriod;
use Database\Seeders\CurrencySeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
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

        $chunk_size = (new SyncCurrencyRatesJob)->chunk_size;
        $expected_num_calls = count(collect(CarbonPeriod::create($transaction->date, now()))->chunk($chunk_size));

        Frankfurter::shouldReceive('setSymbols')
            ->andReturn(new \Investbrain\Frankfurter\FrankfurterClient)
            ->times($expected_num_calls);

        dispatch(new SyncCurrencyRatesJob);
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

        $mockClient = Mockery::mock(\Investbrain\Frankfurter\FrankfurterClient::class);

        Frankfurter::shouldReceive('setSymbols')
            ->andReturn($mockClient);

        $start = now()->subWeeks(2);
        $end = now();

        $results = [];

        $period = CarbonPeriod::create($start, $end);

        collect($period->copy()->filter('isWeekday'))->each(function ($date) use (&$results) {
            $date = $date->toDateString();

            $results[$date] = [
                'ZZZ' => random_int(10, 150) / 1000,
            ];
        });

        $mockClient->shouldReceive('timeSeries')
            ->andReturn(['rates' => $results]);

        $result = CurrencyRate::timeSeriesRates('ZZZ', $start, $end);

        $this->assertEquals(count($period) - 1, count($result));
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

        $mockClient = Mockery::mock(\Investbrain\Frankfurter\FrankfurterClient::class);

        Frankfurter::shouldReceive('setSymbols')
            ->andReturn($mockClient);

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

        $mockClient->shouldReceive('timeSeries')
            ->andReturn(['rates' => $results]);

        $result = CurrencyRate::timeSeriesRates('ZZZ', $start, $end);

        $this->assertEquals(
            $results[$end->toDateString()]['YYY'] * $adjustment,
            $result[$end->toDateString()]
        );
    }

    // todo:
    public function test_can_buy_in_different_currency()
    {

        $this->actingAs($user = User::factory()->create());

        $portfolio = Portfolio::factory()->create();
        $date = now()->subYear();
        $cost_basis = 100; // in ZZZ currency
        $rate = .78; // ZZZ to USD (base and currency ACME is traded in)

        // $market_data = [
        //     'name' => 'ACME Company Ltd',
        //     'symbol' => 'ACME',
        //     'currency' => 'ZZZ',
        //     'market_value' => 230.19,
        // ];
        // $quoteMock = Mockery::mock(FakeMarketData::class);
        // $quoteMock->shouldReceive('quote')
        //     ->andReturn(new Quote($market_data));

        // MarketData::create([$market_data]);

        $exchangeMock = Mockery::mock(\Investbrain\Frankfurter\FrankfurterClient::class);
        $exchangeMock->shouldReceive('historical')
            ->andReturn([
                'date' => $date->toDateString(),
                'rates' => [
                    'ZZZ' => $rate,
                ],
            ]);

        $transaction = Transaction::factory()
            ->buy()
            ->date($date)
            ->costBasis($cost_basis)
            ->currency('ZZZ')
            ->portfolio($portfolio->id)
            ->symbol('ACME')
            ->create();

        $this->assertEquals($cost_basis * $rate, $transaction->cost_basis);

    }

    // public function test_can_sell_in_different_currency()
    // {

    //     $this->actingAs($user = User::factory()->create());

    //     $portfolio = Portfolio::factory()->create();
    //     $transaction = Transaction::factory()->buy()->lastYear()->portfolio($portfolio->id)->symbol('ACME')->create();

    //     //
    // }

    // // todo:
    // public function test_holdings_calculations_from_multiple_currencies()
    // {

    //     $this->actingAs($user = User::factory()->create());

    //     $portfolio = Portfolio::factory()->create();
    //     $transaction = Transaction::factory()->buy()->lastYear()->portfolio($portfolio->id)->symbol('ACME')->create();

    //     //
    // }

    // // todo:
    // public function test_portfolio_daily_change_from_multiple_currencies()
    // {

    //     $this->actingAs($user = User::factory()->create());

    //     $portfolio = Portfolio::factory()->create();
    //     $transaction = Transaction::factory()->buy()->lastYear()->portfolio($portfolio->id)->symbol('ACME')->create();

    //     //
    // }

    // // todo:
    // public function test_can_change_display_currency()
    // {

    //     $this->actingAs($user = User::factory()->create());

    //     $portfolio = Portfolio::factory()->create();
    //     $transaction = Transaction::factory()->buy()->lastYear()->portfolio($portfolio->id)->symbol('ACME')->create();

    //     //
    // }
}
