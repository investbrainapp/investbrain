<?php

declare(strict_types=1);

namespace Tests;

use App\Models\Holding;
use App\Models\Portfolio;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

class HoldingsTest extends TestCase
{
    use RefreshDatabase;

    public function test_calculates_cost_basis(): void
    {
        $this->actingAs($user = User::factory()->create());

        $portfolio = Portfolio::factory()->create();

        Transaction::factory()->buy()->lastYear()->costBasis(200)->portfolio($portfolio->id)->symbol('AAPL')->create();
        Transaction::factory()->buy()->lastMonth()->costBasis(300)->portfolio($portfolio->id)->symbol('AAPL')->create();
        $holding = Holding::query()->getPortfolioMetrics();
        $this->assertEquals(500, $holding->get('total_cost_basis'));
    }

    public function test_calculates_cost_basis_after_multiple_sales(): void
    {
        $this->actingAs($user = User::factory()->create());

        $portfolio = Portfolio::factory()->create();

        Transaction::factory()->buy()->lastYear()->costBasis(200)->portfolio($portfolio->id)->symbol('AAPL')->create();
        Transaction::factory()->buy()->lastMonth()->costBasis(300)->portfolio($portfolio->id)->symbol('AAPL')->create();

        Transaction::factory()->sell()->recent()->costBasis(250)->portfolio($portfolio->id)->symbol('AAPL')->create();
        $holding = Holding::query()->getPortfolioMetrics();
        $this->assertEquals(250, $holding->get('total_cost_basis'));

        Transaction::factory()->sell()->recent()->costBasis(250)->portfolio($portfolio->id)->symbol('AAPL')->create();
        $holding = Holding::query()->getPortfolioMetrics();
        $this->assertEquals(0, $holding->get('total_cost_basis'));
    }

    public function test_calculates_cost_bases_on_same_day_buy_sell_transaction(): void
    {
        $this->actingAs($user = User::factory()->create());

        $portfolio = Portfolio::factory()->create();

        Transaction::factory(2)->buy()->lastYear()->costBasis(100)->portfolio($portfolio->id)->symbol('AAPL')->create();
        Transaction::factory(2)->buy()->lastYear()->costBasis(300)->portfolio($portfolio->id)->symbol('AAPL')->create();

        Transaction::factory()->sell()->lastYear()->portfolio($portfolio->id)->symbol('AAPL')->create();
        Transaction::factory()->sell()->recent()->portfolio($portfolio->id)->symbol('AAPL')->create();

        $holding = Holding::query()->getPortfolioMetrics();
        $this->assertEquals(400, $holding->get('total_cost_basis'));
    }

    public function test_delete_holding_on_sync_if_no_transactions(): void
    {
        $this->actingAs($user = User::factory()->create());

        $portfolio = Portfolio::factory()->create();

        $transaction = Transaction::factory()->buy()->lastYear()->costBasis(100)->portfolio($portfolio->id)->symbol('AAPL')->create();

        $this->assertDatabaseCount('holdings', 1);

        $transaction->delete();

        $this->assertDatabaseEmpty('holdings');
    }
}
