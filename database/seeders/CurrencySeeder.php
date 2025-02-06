<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Currency;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class CurrencySeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Run the database seeds.
     */
    public function run(): void
    {

        Currency::insert([
            ['currency' => 'AUD', 'label' => 'Australian Dollar', 'rate_to_usd' => 0, 'created_at' => now()],
            ['currency' => 'BRL', 'label' => 'Brazilian Real', 'rate_to_usd' => 0, 'created_at' => now()],
            ['currency' => 'GBP', 'label' => 'British Pound', 'rate_to_usd' => 0, 'created_at' => now()],
            ['currency' => 'CAD', 'label' => 'Canadian Dollar', 'rate_to_usd' => 0, 'created_at' => now()],
            ['currency' => 'CNY', 'label' => 'Chinese Yuan', 'rate_to_usd' => 0, 'created_at' => now()],
            ['currency' => 'CZK', 'label' => 'Czech Koruna', 'rate_to_usd' => 0, 'created_at' => now()],
            ['currency' => 'DKK', 'label' => 'Danish Krone', 'rate_to_usd' => 0, 'created_at' => now()],
            ['currency' => 'EUR', 'label' => 'Euro', 'rate_to_usd' => 0, 'created_at' => now()],
            ['currency' => 'HKD', 'label' => 'Hong Kong Dollar', 'rate_to_usd' => 0, 'created_at' => now()],
            ['currency' => 'INR', 'label' => 'Indian Rupee', 'rate_to_usd' => 0, 'created_at' => now()],
            ['currency' => 'JPY', 'label' => 'Japanese Yen', 'rate_to_usd' => 0, 'created_at' => now()],
            ['currency' => 'NZD', 'label' => 'New Zealand Dollar', 'rate_to_usd' => 0, 'created_at' => now()],
            ['currency' => 'NOK', 'label' => 'Norwegian Krone', 'rate_to_usd' => 0, 'created_at' => now()],
            ['currency' => 'SGD', 'label' => 'Singapore Dollar', 'rate_to_usd' => 0, 'created_at' => now()],
            ['currency' => 'KRW', 'label' => 'South Korean Won', 'rate_to_usd' => 0, 'created_at' => now()],
            ['currency' => 'ZAR', 'label' => 'South African Rand', 'rate_to_usd' => 0, 'created_at' => now()],
            ['currency' => 'SEK', 'label' => 'Swedish Krona', 'rate_to_usd' => 0, 'created_at' => now()],
            ['currency' => 'CHF', 'label' => 'Swiss Franc', 'rate_to_usd' => 0, 'created_at' => now()],
            ['currency' => 'USD', 'label' => 'United States Dollar', 'rate_to_usd' => 1, 'created_at' => now()],
        ]);
    }
}
