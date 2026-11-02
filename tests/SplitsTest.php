<?php

declare(strict_types=1);

namespace Tests;

use App\Interfaces\MarketData\AlpacaMarketData;
use App\Interfaces\MarketData\MarketDataInterface;
use App\Interfaces\MarketData\Types\Split as SplitData;
use App\Models\Dividend;
use App\Models\Holding;
use App\Models\Portfolio;
use App\Models\Split;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

class SplitsTest extends TestCase
{
    use RefreshDatabase;

    public function test_splits_adjust_existing_transactions_instead_of_creating_new_ones(): void
    {
        $this->actingAs(User::factory()->create());

        $portfolio = Portfolio::factory()->create();
        Transaction::factory()->buy(1)->costBasis(100)->yearsAgo()->portfolio($portfolio->id)->symbol('ACME')->create();

        $this->resetSplitsSyncedAt($portfolio);

        // fake market data provider returns a 10:1 split dated a year ago
        Split::refreshSplitData('ACME');

        $transactions = Transaction::query()->symbol('ACME')->portfolio($portfolio->id)->get();
        $holding = Holding::query()->portfolio($portfolio->id)->symbol('ACME')->first();

        $this->assertCount(1, $transactions);
        $this->assertEqualsWithDelta(10, $transactions->first()->quantity, 0.0001);
        $this->assertEqualsWithDelta(10, $transactions->first()->cost_basis, 0.0001);
        $this->assertEqualsWithDelta(10, $holding->quantity, 0.0001);
        $this->assertEqualsWithDelta(100, $holding->total_cost_basis, 0.0001);
    }

    public function test_splits_do_not_adjust_transactions_after_split_date(): void
    {
        $this->actingAs(User::factory()->create());

        $portfolio = Portfolio::factory()->create();
        Transaction::factory()->buy(1)->costBasis(100)->yearsAgo()->portfolio($portfolio->id)->symbol('ACME')->create();
        $recent = Transaction::factory()->buy(1)->costBasis(12)->lastMonth()->portfolio($portfolio->id)->symbol('ACME')->create();

        $this->resetSplitsSyncedAt($portfolio);

        Split::refreshSplitData('ACME');

        $recent->refresh();
        $holding = Holding::query()->portfolio($portfolio->id)->symbol('ACME')->first();

        $this->assertEqualsWithDelta(1, $recent->quantity, 0.0001);
        $this->assertEqualsWithDelta(12, $recent->cost_basis, 0.0001);
        $this->assertEqualsWithDelta(11, $holding->quantity, 0.0001);
    }

    public function test_splits_do_not_adjust_transactions_if_already_synced(): void
    {
        $this->actingAs(User::factory()->create());

        $portfolio = Portfolio::factory()->create();
        Transaction::factory()->buy(1)->yearsAgo()->portfolio($portfolio->id)->symbol('ACME')->create();

        Split::refreshSplitData('ACME');

        $transactions = Transaction::query()->symbol('ACME')->portfolio($portfolio->id)->get();

        $this->assertCount(1, $transactions);
        $this->assertEqualsWithDelta(1, $transactions->first()->quantity, 0.0001);
    }

    public function test_splits_are_only_applied_once(): void
    {
        $this->actingAs(User::factory()->create());

        $portfolio = Portfolio::factory()->create();
        Transaction::factory()->buy(1)->yearsAgo()->portfolio($portfolio->id)->symbol('ACME')->create();

        $this->resetSplitsSyncedAt($portfolio);

        Split::refreshSplitData('ACME');
        Split::syncToTransactions('ACME');

        $holding = Holding::query()->portfolio($portfolio->id)->symbol('ACME')->first();

        $this->assertEqualsWithDelta(10, $holding->quantity, 0.0001);
    }

    public function test_reverse_splits_adjust_existing_transactions(): void
    {
        $this->actingAs(User::factory()->create());

        $portfolio = Portfolio::factory()->create();
        $buy = Transaction::factory()->buy(300)->costBasis(10)->yearsAgo()->portfolio($portfolio->id)->symbol('ACME')->create();
        $sell = Transaction::factory()->sell(30)->salePrice(12)->date(now()->subMonths(8)->toDateString())->portfolio($portfolio->id)->symbol('ACME')->create();

        $this->resetSplitsSyncedAt($portfolio);

        // 1:3 reverse split, stored with limited precision
        Split::create([
            'symbol' => 'ACME',
            'date' => now()->subMonths(6)->toDateString(),
            'split_amount' => 0.3333,
            'dividends_adjusted_at' => now(),
        ]);

        Split::syncToTransactions('ACME');

        $buy->refresh();
        $sell->refresh();
        $holding = Holding::query()->portfolio($portfolio->id)->symbol('ACME')->first();

        $this->assertEqualsWithDelta(100, $buy->quantity, 0.0001);
        $this->assertEqualsWithDelta(30, $buy->cost_basis, 0.0001);
        $this->assertEqualsWithDelta(10, $sell->quantity, 0.0001);
        $this->assertEqualsWithDelta(36, $sell->sale_price, 0.0001);
        $this->assertEqualsWithDelta(30, $sell->cost_basis, 0.0001);
        $this->assertEqualsWithDelta(90, $holding->quantity, 0.0001);
        $this->assertEqualsWithDelta(60, $holding->realized_gain_dollars, 0.0001);
    }

    public function test_new_splits_adjust_dividends_recorded_before_the_split(): void
    {
        $this->actingAs(User::factory()->create());

        $portfolio = Portfolio::factory()->create();
        Transaction::factory()->buy(1)->yearsAgo()->portfolio($portfolio->id)->symbol('ACME')->create();

        $this->resetSplitsSyncedAt($portfolio);

        $preSplitDividendId = $this->insertDividend(date: now()->subMonths(18), amount: 1, createdAt: now()->subMonths(17));
        $postSplitDividendId = $this->insertDividend(date: now()->subMonths(3), amount: 0.5, createdAt: now()->subMonths(3));

        Split::refreshSplitData('ACME');

        $holding = Holding::query()->portfolio($portfolio->id)->symbol('ACME')->first();

        $this->assertEqualsWithDelta(0.1, Dividend::find($preSplitDividendId)->dividend_amount, 0.0001);
        $this->assertEqualsWithDelta(0.5, Dividend::find($postSplitDividendId)->dividend_amount, 0.0001);
        $this->assertNotNull(Split::query()->where('symbol', 'ACME')->first()->dividends_adjusted_at);

        // 1 pre-split share x $1 + 10 post-split shares x $0.50
        $this->assertEqualsWithDelta(6, $holding->dividends_earned, 0.0001);
    }

    public function test_upcoming_splits_are_not_applied_until_they_take_effect(): void
    {
        $this->actingAs(User::factory()->create());

        $portfolio = Portfolio::factory()->create();
        Transaction::factory()->buy(1)->yearsAgo()->portfolio($portfolio->id)->symbol('ACME')->create();

        $this->resetSplitsSyncedAt($portfolio);

        $dividendId = $this->insertDividend(date: now()->subMonths(3), amount: 1, createdAt: now()->subMonths(3));

        $this->mock(MarketDataInterface::class, function ($mock) {
            $mock->shouldReceive('splits')->andReturn(collect([
                new SplitData(['symbol' => 'ACME', 'date' => now()->addMonth(), 'split_amount' => 10]),
            ]));
        });

        Split::refreshSplitData('ACME');

        $holding = Holding::query()->portfolio($portfolio->id)->symbol('ACME')->first();

        $this->assertEquals(0, Split::count());
        $this->assertEqualsWithDelta(1, $holding->quantity, 0.0001);
        $this->assertEqualsWithDelta(1, Dividend::find($dividendId)->dividend_amount, 0.0001);
    }

    public function test_alpaca_returns_forward_and_reverse_splits(): void
    {
        Http::fake([
            '*corporate-actions*' => Http::response([
                'corporate_actions' => [
                    'forward_splits' => [
                        ['symbol' => 'ACME', 'ex_date' => '2024-06-10', 'new_rate' => 4, 'old_rate' => 1],
                    ],
                    'reverse_splits' => [
                        ['symbol' => 'ACME', 'ex_date' => '2025-06-10', 'new_rate' => 1, 'old_rate' => 10],
                    ],
                ],
            ]),
        ]);

        $splits = (new AlpacaMarketData)->splits('ACME', now()->subYears(5), now());

        $this->assertEquals([4.0, 0.1], $splits->map(fn ($split) => $split->getSplitAmount())->values()->all());
    }

    public function test_repair_converts_legacy_split_transactions(): void
    {
        $this->actingAs(User::factory()->create());

        $portfolio = Portfolio::factory()->create();
        $splitDate = now()->subYear()->toDateString();

        $buy = Transaction::factory()->buy(1)->costBasis(100)->yearsAgo()->portfolio($portfolio->id)->symbol('ACME')->create();
        $this->createSplit($splitDate, 10);
        $this->createLegacySplitTransaction($portfolio, $splitDate, 9);

        // recorded after the legacy split transaction, so already in post-split terms
        $this->travel(5)->minutes();
        $laterBuy = Transaction::factory()->buy(5)->costBasis(10)->yearsAgo()->portfolio($portfolio->id)->symbol('ACME')->create();

        $this->artisan('fix:split-transactions')->assertSuccessful();
        $this->artisan('fix:split-transactions')->assertSuccessful();

        $buy->refresh();
        $laterBuy->refresh();
        $holding = Holding::query()->portfolio($portfolio->id)->symbol('ACME')->first();

        $this->assertEquals(0, Transaction::where('split', true)->count());
        $this->assertEqualsWithDelta(10, $buy->quantity, 0.0001);
        $this->assertEqualsWithDelta(10, $buy->cost_basis, 0.0001);
        $this->assertEqualsWithDelta(5, $laterBuy->quantity, 0.0001);
        $this->assertEqualsWithDelta(10, $laterBuy->cost_basis, 0.0001);
        $this->assertEqualsWithDelta(15, $holding->quantity, 0.0001);
        $this->assertEqualsWithDelta(150, $holding->total_cost_basis, 0.0001);
    }

    public function test_repair_infers_ratio_when_split_data_is_missing(): void
    {
        $this->actingAs(User::factory()->create());

        $portfolio = Portfolio::factory()->create();
        $splitDate = now()->subYear()->toDateString();

        $buy = Transaction::factory()->buy(2)->costBasis(100)->yearsAgo()->portfolio($portfolio->id)->symbol('ACME')->create();
        $this->createLegacySplitTransaction($portfolio, $splitDate, 2);

        $this->artisan('fix:split-transactions')->assertSuccessful();

        $buy->refresh();

        $this->assertEquals(0, Transaction::where('split', true)->count());
        $this->assertEqualsWithDelta(4, $buy->quantity, 0.0001);
        $this->assertEqualsWithDelta(50, $buy->cost_basis, 0.0001);
    }

    public function test_repair_adjusts_dividends_for_existing_splits_once(): void
    {
        $splitDate = now()->subYear()->toDateString();

        $this->createSplit($splitDate, 10);

        $dividendId = $this->insertDividend(date: now()->subMonths(18), amount: 1, createdAt: now()->subMonths(17));

        $this->artisan('fix:split-transactions')->assertSuccessful();
        $this->artisan('fix:split-transactions')->assertSuccessful();

        $this->assertEqualsWithDelta(0.1, Dividend::find($dividendId)->dividend_amount, 0.0001);
    }

    protected function resetSplitsSyncedAt(Portfolio $portfolio): void
    {
        // the split sync date is set when the holding is created
        Holding::query()->portfolio($portfolio->id)->symbol('ACME')->update([
            'splits_synced_at' => null,
        ]);
    }

    protected function createSplit(string $date, float $splitAmount): Split
    {
        // mimics a split synced before splits were applied to dividends
        return Split::create([
            'symbol' => 'ACME',
            'date' => $date,
            'split_amount' => $splitAmount,
        ]);
    }

    protected function createLegacySplitTransaction(Portfolio $portfolio, string $date, float $quantity): Transaction
    {
        return Transaction::create([
            'symbol' => 'ACME',
            'portfolio_id' => $portfolio->id,
            'transaction_type' => 'BUY',
            'date' => $date,
            'quantity' => $quantity,
            'cost_basis' => 0,
            'split' => true,
        ]);
    }

    protected function insertDividend($date, float $amount, $createdAt): string
    {
        $id = Str::uuid()->toString();

        DB::table('dividends')->insert([
            'id' => $id,
            'symbol' => 'ACME',
            'date' => $date->toDateString(),
            'dividend_amount' => $amount,
            'dividend_amount_base' => $amount,
            'created_at' => $createdAt,
            'updated_at' => $createdAt,
        ]);

        return $id;
    }
}
