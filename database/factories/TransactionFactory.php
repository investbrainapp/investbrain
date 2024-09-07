<?php

namespace Database\Factories;

use App\Models\Portfolio;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Transaction>
 */
class TransactionFactory extends Factory
{
     protected static ?string $transaction_type;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'symbol' => $this->faker->randomElement(['AAPL', 'GOOGL', 'AMZN']),
            'transaction_type' => static::$transaction_type = $this->faker->randomElement(['BUY', 'SELL']),
            'portfolio_id' => Portfolio::factory()->create(), 
            'date' => $this->faker->date('Y-m-d'),
            'quantity' => $this->faker->randomFloat(2, 1, 100),
            'cost_basis' => $this->faker->randomFloat(2, 10, 500),
            'sale_price' => static::$transaction_type == 'SELL' 
                ? $this->faker->randomFloat(2, 10, 500)
                : null, 
        ];
    }

    public function symbol($symbol): static
    {
        return $this->state(fn (array $attributes) => [
            'symbol' => $symbol,
        ]);
    }

    public function portfolios($portfolio_id): static
    {
        return $this->state(fn (array $attributes) => [
            'portfolio_id' => $portfolio_id,
        ]);
    }

    public function buy(): static
    {
        return $this->state(fn (array $attributes) => [
            'transaction_type' => 'BUY',
        ]);
    }

    public function sell(): static
    {
        return $this->state(fn (array $attributes) => [
            'transaction_type' => 'SELL',
        ]);
    }
}
