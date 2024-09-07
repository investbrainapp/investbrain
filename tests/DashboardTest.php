<?php

namespace Tests;

use Tests\TestCase;
use App\Models\User;
use App\Models\Holding;
use App\Models\Portfolio;
use App\Models\Transaction;
use Illuminate\Foundation\Testing\RefreshDatabase;

class DashboardTest extends TestCase
{
    use RefreshDatabase;

    /**
     */
    public function test_user_has_portfolios(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        Portfolio::factory(5)->create();

        $this->assertCount(5, $user->portfolios);
    }

    /**
     */
    public function test_user_has_transactions(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        Transaction::factory(10)->create();

        $this->assertCount(10, $user->transactions);
    }

    /**
     */
    public function test_user_has_holdings(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $portfolio = Portfolio::factory()->create();

        Transaction::factory(5)->symbol('AAPL')->portfolio($portfolio->id)->create();

        $this->assertCount(1, $user->holdings);
    }

    /**
     */
    public function test_user_has_dashboard_metrics(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $portfolio = Portfolio::factory()->create();

        Transaction::factory(5)->buy()->portfolio($portfolio->id)->symbol('AAPL')->create();
        $transaction = Transaction::factory()->sell()->portfolio($portfolio->id)->symbol('AAPL')->create();

        $metrics = Holding::query()
                    ->myHoldings()
                    ->withPortfolioMetrics()
                    ->first();
    
        $this->assertEqualsWithDelta(
            $transaction->sale_price - $transaction->cost_basis, 
            $metrics->realized_gain_dollars,
            0.01
        );
    }
}
