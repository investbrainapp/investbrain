<?php

declare(strict_types=1);

namespace Tests;

use App\Models\Currency;
use App\Models\CurrencyRate;
use Database\Seeders\CurrencySeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;

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

    // todo: test historic rates

    // todo: test time series rates

    // todo: handle aliases for historic

    // todo: handle aliases for time series rates

    // todo: test buy and sell in different currency than market data currency

    // todo: test portfolio with multiple currencies

    // todo: test show holdings in display currency

    // todo: test can change display currency
}
