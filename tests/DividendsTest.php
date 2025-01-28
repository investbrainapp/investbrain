<?php

declare(strict_types=1);

namespace Tests;

use App\Models\Dividend;
use App\Models\Holding;
use App\Models\MarketData;
use App\Models\Portfolio;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

class DividendsTest extends TestCase
{
    use RefreshDatabase;

    public function test_new_dividends_update_holding(): void
    {
        $this->actingAs($user = User::factory()->create());

        $portfolio = Portfolio::factory()->create();
        Transaction::factory()->buy()->yearsAgo()->portfolio($portfolio->id)->symbol('ACME')->create();

        $holding = Holding::query()->portfolio($portfolio->id)->symbol('ACME')->first();

        $this->assertEquals(0, $holding->dividends_earned);

        Dividend::refreshDividendData('ACME');

        $holding->refresh();

        $this->assertEquals(4.95, $holding->dividends_earned);
    }

    public function test_new_dividends_are_reinvested(): void
    {
        $this->actingAs($user = User::factory()->create());

        $portfolio = Portfolio::factory()->create();
        Transaction::factory()->buy()->yearsAgo()->portfolio($portfolio->id)->symbol('ACME')->create();

        $holding = Holding::query()->portfolio($portfolio->id)->symbol('ACME')->first();
        $holding->reinvest_dividends = true;
        $holding->save();

        $this->assertEquals(0, $holding->dividends_earned);

        Dividend::refreshDividendData('ACME');

        $transactions = Transaction::where(['reinvested_dividend' => true])->symbol('ACME')->portfolio($portfolio->id)->get();

        $market_data = MarketData::symbol('ACME')->first();
        $dividendsReinvested = $transactions->sum('quantity');

        $this->assertCount(3, $transactions);
        $this->assertEqualsWithDelta(4.95, $dividendsReinvested * $market_data->market_value, 0.01);
    }

    public function test_do_not_duplicate_recent_dividends(): void
    {
        $this->actingAs($user = User::factory()->create());

        $portfolio = Portfolio::factory()->create();
        Transaction::factory()->buy()->yearsAgo()->portfolio($portfolio->id)->symbol('ACME')->create();

        $holding = Holding::query()->portfolio($portfolio->id)->symbol('ACME')->first();

        Dividend::create([
            'symbol' => 'ACME',
            'date' => now()->subDay(2),
            'dividend_amount' => .01,
        ]);

        Dividend::refreshDividendData('ACME');

        $this->assertCount(1, $holding->dividends);
    }
}
