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
            ['currency' => 'AUD', 'label' => 'Australian Dollar', 'created_at' => now()],
            ['currency' => 'BRL', 'label' => 'Brazilian Real', 'created_at' => now()],
            ['currency' => 'GBP', 'label' => 'British Pound', 'created_at' => now()],
            ['currency' => 'CAD', 'label' => 'Canadian Dollar', 'created_at' => now()],
            ['currency' => 'CNY', 'label' => 'Chinese Yuan', 'created_at' => now()],
            ['currency' => 'CZK', 'label' => 'Czech Koruna', 'created_at' => now()],
            ['currency' => 'DKK', 'label' => 'Danish Krone', 'created_at' => now()],
            ['currency' => 'EUR', 'label' => 'Euro', 'created_at' => now()],
            ['currency' => 'HKD', 'label' => 'Hong Kong Dollar', 'created_at' => now()],
            ['currency' => 'INR', 'label' => 'Indian Rupee', 'created_at' => now()],
            ['currency' => 'JPY', 'label' => 'Japanese Yen', 'created_at' => now()],
            ['currency' => 'NZD', 'label' => 'New Zealand Dollar', 'created_at' => now()],
            ['currency' => 'NOK', 'label' => 'Norwegian Krone', 'created_at' => now()],
            ['currency' => 'SGD', 'label' => 'Singapore Dollar', 'created_at' => now()],
            ['currency' => 'KRW', 'label' => 'South Korean Won', 'created_at' => now()],
            ['currency' => 'ZAR', 'label' => 'South African Rand', 'created_at' => now()],
            ['currency' => 'SEK', 'label' => 'Swedish Krona', 'created_at' => now()],
            ['currency' => 'CHF', 'label' => 'Swiss Franc', 'created_at' => now()],
            ['currency' => 'USD', 'label' => 'United States Dollar', 'created_at' => now()],
        ]);
    }
}
