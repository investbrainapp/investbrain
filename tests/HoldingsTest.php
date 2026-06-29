<?php

declare(strict_types=1);

namespace Tests;

use App\Models\Holding;
use App\Models\Portfolio;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;

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

    public function test_holding_page_can_render_optional_market_sentiment_card(): void
    {
        config()->set('app.key', 'base64:'.base64_encode(random_bytes(32)));
        config()->set('services.adanos.key', 'test-key');

        Http::fake([
            'https://api.adanos.org/reddit/stocks/v1/compare*' => Http::response([
                'stocks' => [[
                    'ticker' => 'AAPL',
                    'buzz_score' => 55.4,
                    'bullish_pct' => 62,
                    'mentions' => 120,
                ]],
            ]),
            'https://api.adanos.org/x/stocks/v1/compare*' => Http::response([
                'stocks' => [[
                    'ticker' => 'AAPL',
                    'buzz_score' => 60.0,
                    'bullish_pct' => 58,
                    'mentions' => 94,
                ]],
            ]),
            'https://api.adanos.org/news/stocks/v1/compare*' => Http::response([
                'stocks' => [[
                    'ticker' => 'AAPL',
                    'buzz_score' => 48.0,
                    'bullish_pct' => 61,
                    'mentions' => 37,
                ]],
            ]),
            'https://api.adanos.org/polymarket/stocks/v1/compare*' => Http::response([
                'stocks' => [[
                    'ticker' => 'AAPL',
                    'buzz_score' => 50.0,
                    'bullish_pct' => 64,
                    'trade_count' => 512,
                ]],
            ]),
        ]);

        $this->actingAs($user = User::factory()->create());

        $portfolio = Portfolio::factory()->create();

        Transaction::factory()
            ->buy()
            ->lastYear()
            ->portfolio($portfolio->id)
            ->symbol('AAPL')
            ->create();

        $this->get(route('holding.show', ['portfolio' => $portfolio->id, 'symbol' => 'AAPL']))
            ->assertOk()
            ->assertSee('Market sentiment')
            ->assertSee('Reddit:')
            ->assertSee('Finance News')
            ->assertSee('Polymarket:');
    }

    public function test_holding_page_hides_market_sentiment_when_adanos_is_not_configured(): void
    {
        config()->set('app.key', 'base64:'.base64_encode(random_bytes(32)));

        Http::fake();

        $this->actingAs($user = User::factory()->create());

        $portfolio = Portfolio::factory()->create();

        Transaction::factory()
            ->buy()
            ->lastYear()
            ->portfolio($portfolio->id)
            ->symbol('AAPL')
            ->create();

        $this->get(route('holding.show', ['portfolio' => $portfolio->id, 'symbol' => 'AAPL']))
            ->assertOk()
            ->assertDontSee('Market sentiment');

        Http::assertNothingSent();
    }
}
