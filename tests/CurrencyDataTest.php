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
}
