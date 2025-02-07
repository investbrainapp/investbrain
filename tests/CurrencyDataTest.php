<?php

declare(strict_types=1);

namespace Tests;

use App\Models\Currency;
use Database\Seeders\CurrencySeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;

class CurrencyDataTest extends TestCase
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

        Currency::create(['currency' => 'INR', 'label' => 'Indian Rupee', 'rate' => 85]);
        Currency::create(['currency' => 'USD', 'label' => 'US Dollar', 'rate' => 1]);
        $converted = Currency::convert(85, 'INR', 'USD');

        $this->assertEquals(1, $converted);
    }

    public function test_can_convert_currency_between_non_base_rate()
    {

        Currency::create(['currency' => 'INR', 'label' => 'Indian Rupee', 'rate' => 85]);
        Currency::create(['currency' => 'EUR', 'label' => 'Euro', 'rate' => .96]);
        $converted = Currency::convert(85, 'INR', 'EUR');

        $this->assertEquals(0.96, $converted);
    }

    public function test_can_convert_currency_from_base_rate()
    {

        Currency::create(['currency' => 'USD', 'label' => 'US Dollar', 'rate' => 1]);
        Currency::create(['currency' => 'EUR', 'label' => 'Euro', 'rate' => .96]);
        $converted = Currency::convert(1, 'USD', 'EUR');

        $this->assertEquals(0.96, $converted);
    }
}
