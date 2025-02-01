<?php

declare(strict_types=1);

namespace Tests;

use App\Models\MarketData;
use Database\Seeders\MarketDataSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;

class MarketDataTest extends TestCase
{
    use RefreshDatabase;

    public function test_can_seed_market_data()
    {
        Artisan::call('db:seed', [
            '--class' => MarketDataSeeder::class,
            '--force' => true,
        ]);

        $this->assertEquals(14464, MarketData::count('symbol'));
    }

    public function test_can_get_quote_from_provider()
    {

        $market_data = MarketData::getMarketData('ACME');

        $this->assertEquals(class_basename($market_data), 'MarketData');
        $this->assertEquals($market_data->symbol, 'ACME');
    }

    public function test_quote_always_has_default_meta_data()
    {

        $market_data = MarketData::getMarketData('ACME');

        $this->assertIsArray($market_data->meta_data);
        $this->assertArrayHasKey('country', $market_data->meta_data);
        $this->assertArrayHasKey('industry', $market_data->meta_data);
    }
}
