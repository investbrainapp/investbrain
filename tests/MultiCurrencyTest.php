<?php

declare(strict_types=1);

namespace Tests;

use App\Jobs\SyncCurrencyRatesJob;
use App\Models\Currency;
use App\Models\CurrencyRate;
use App\Models\Portfolio;
use App\Models\Transaction;
use App\Models\User;
use Carbon\CarbonPeriod;
use Database\Seeders\CurrencySeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Investbrain\Frankfurter\Frankfurter;

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

    // todo:
    public function test_can_get_historic_exchange_rates()
    {

        $this->actingAs($user = User::factory()->create());

        $portfolio = Portfolio::factory()->create();
        $transaction = Transaction::factory()->buy()->lastYear()->portfolio($portfolio->id)->symbol('ACME')->create();

        //
    }

    // todo:
    public function test_can_get_time_series_rates()
    {

        $this->actingAs($user = User::factory()->create());

        $portfolio = Portfolio::factory()->create();
        $transaction = Transaction::factory()->buy()->lastYear()->portfolio($portfolio->id)->symbol('ACME')->create();

        //
    }

    // todo:
    public function test_can_handle_aliases_for_historic_rates()
    {

        $this->actingAs($user = User::factory()->create());

        $portfolio = Portfolio::factory()->create();
        $transaction = Transaction::factory()->buy()->lastYear()->portfolio($portfolio->id)->symbol('ACME')->create();

        //
    }

    // todo:
    public function test_can_handle_aliases_for_time_series_rates()
    {

        $this->actingAs($user = User::factory()->create());

        $portfolio = Portfolio::factory()->create();
        $transaction = Transaction::factory()->buy()->lastYear()->portfolio($portfolio->id)->symbol('ACME')->create();

        //
    }

    // todo:
    public function test_can_buy_and_sell_in_different_currencies()
    {

        $this->actingAs($user = User::factory()->create());

        $portfolio = Portfolio::factory()->create();
        $transaction = Transaction::factory()->buy()->lastYear()->portfolio($portfolio->id)->symbol('ACME')->create();

        //
    }

    // todo:
    public function test_holdings_calculations_from_multiple_currencies()
    {

        $this->actingAs($user = User::factory()->create());

        $portfolio = Portfolio::factory()->create();
        $transaction = Transaction::factory()->buy()->lastYear()->portfolio($portfolio->id)->symbol('ACME')->create();

        //
    }

    // todo:
    public function test_portfolio_daily_change_from_multiple_currencies()
    {

        $this->actingAs($user = User::factory()->create());

        $portfolio = Portfolio::factory()->create();
        $transaction = Transaction::factory()->buy()->lastYear()->portfolio($portfolio->id)->symbol('ACME')->create();

        //
    }

    // todo:
    public function test_can_change_display_currency()
    {

        $this->actingAs($user = User::factory()->create());

        $portfolio = Portfolio::factory()->create();
        $transaction = Transaction::factory()->buy()->lastYear()->portfolio($portfolio->id)->symbol('ACME')->create();

        //
    }
}
