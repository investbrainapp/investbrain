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
        $transaction_type = $this->faker->randomElement(['BUY', 'SELL']);

        return [
            'symbol' => $this->faker->randomElement(['AAPL', 'GOOG', 'AMZN']),
            'transaction_type' => $transaction_type,
            'portfolio_id' => Portfolio::factory()->create()->id, 
            'date' => $this->faker->date('Y-m-d'),
            'quantity' => 1,
            'cost_basis' => $transaction_type == 'BUY' 
                ? $this->faker->randomFloat(2, 10, 500)
                : null, 
            'sale_price' => $transaction_type == 'SELL' 
                ? $this->faker->randomFloat(2, 10, 500)
                : null, 
        ];
    }

    public function yearsAgo(): static
    {
        return $this->state(fn (array $attributes) => [
            'date' => $this->faker->dateTimeBetween('-5 years', '-3 years')->format('Y-m-d'),
        ]);
    }

    public function lastYear(): static
    {
        return $this->state(fn (array $attributes) => [
            'date' => now()->subYear()->format('Y-m-d'),
        ]);
    }

    public function lastMonth(): static
    {
        return $this->state(fn (array $attributes) => [
            'date' => now()->subMonth()->format('Y-m-d'),
        ]);
    }

    public function recent(): static
    {
        return $this->state(fn (array $attributes) => [
            'date' => $this->faker->dateTimeBetween('-2 weeks', 'now')->format('Y-m-d'),
        ]);
    }

    public function symbol($symbol): static
    {
        return $this->state(fn (array $attributes) => [
            'symbol' => $symbol,
        ]);
    }

    public function portfolio($portfolio_id): static
    {
        return $this->state(fn (array $attributes) => [
            'portfolio_id' => $portfolio_id,
        ]);
    }

    public function buy(): static
    {
        return $this->state(fn (array $attributes) => [
            'transaction_type' => 'BUY',
            'cost_basis' => $this->faker->randomFloat(2, 10, 500),
            'sale_price' => null
        ]);
    }

    public function sell(): static
    {
        return $this->state(fn (array $attributes) => [
            'transaction_type' => 'SELL',
            'sale_price' => $this->faker->randomFloat(2, 10, 500),
            'cost_basis' => null,
        ]);
    }
}
