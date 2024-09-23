<?php

namespace Tests;

use Tests\TestCase;
use App\Models\User;
use App\Models\Holding;
use App\Models\Portfolio;
use App\Models\Transaction;
use Illuminate\Foundation\Testing\RefreshDatabase;

class TransactionsTest extends TestCase
{
    use RefreshDatabase;

    /**
     */
    public function test_can_create_a_transaction(): void
    {
        $this->actingAs($user = User::factory()->create());

        $transaction = Transaction::factory()->create();

        $this->assertNotNull($transaction);
    }

    /**
     */
    public function test_sales_calculate_cost_basis(): void
    {
        $this->actingAs($user = User::factory()->create());

        Transaction::factory(5)->buy()->lastYear()->symbol('AAPL')->create();

        $transaction = Transaction::factory()->sell()->lastMonth()->symbol('AAPL')->create();

        $this->assertNotNull($transaction->cost_basis);
    }

    /**
     */
    public function test_purchases_dont_have_sale_price(): void
    {
        $this->actingAs($user = User::factory()->create());

        $transaction = Transaction::factory()->buy()->create();

        $this->assertNull($transaction->sale_price);
    }

    /**
     */
    public function test_transaction_synced_to_holding(): void
    {
        $this->actingAs($user = User::factory()->create());

        $portfolio = Portfolio::factory()->create();

        Transaction::factory(5)->buy()->lastYear()->portfolio($portfolio->id)->symbol('AAPL')->create();
        $transaction = Transaction::factory()->sell()->lastMonth()->portfolio($portfolio->id)->symbol('AAPL')->create();

        $this->assertDatabaseHas('holdings', [
            'portfolio_id' => $portfolio->id,
            'symbol' => 'AAPL',
            'quantity' => 4
        ]);

        $holding = Holding::where([
            'portfolio_id' => $portfolio->id,
            'symbol' => 'AAPL'
        ])->first();

        $this->assertEqualsWithDelta(
            $holding->realized_gain_dollars, 
            $transaction->sale_price - $transaction->cost_basis, 
            0.01
        );
    }
}
