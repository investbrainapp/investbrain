<?php

declare(strict_types=1);

namespace Tests;

use App\Interfaces\MarketData\AlpacaMarketData;
use App\Models\Holding;
use App\Models\Portfolio;
use App\Models\Split;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;

class SplitsTest extends TestCase
{
    use RefreshDatabase;

    public function test_splits_create_new_transaction(): void
    {
        $this->actingAs($user = User::factory()->create());

        $portfolio = Portfolio::factory()->create();
        Transaction::factory()->buy()->yearsAgo()->portfolio($portfolio->id)->symbol('ACME')->create();

        // manually reset the split last sync date (which is set when the holding is created)
        Holding::query()->portfolio($portfolio->id)->symbol('ACME')->update([
            'splits_synced_at' => null,
        ]);

        Split::refreshSplitData('ACME');

        $transactions = Transaction::query()->symbol('ACME')->portfolio($portfolio->id)->get();

        $this->assertCount(2, $transactions);
    }

    public function test_splits_do_not_create_new_transaction_if_already_synced(): void
    {
        $this->actingAs($user = User::factory()->create());

        $portfolio = Portfolio::factory()->create();
        Transaction::factory()->buy()->yearsAgo()->portfolio($portfolio->id)->symbol('ACME')->create();

        Split::refreshSplitData('ACME');

        $transactions = Transaction::query()->symbol('ACME')->portfolio($portfolio->id)->get();

        $this->assertCount(1, $transactions);
    }

    public function test_reverse_splits_reduce_quantity_without_changing_cost_basis(): void
    {
        $this->actingAs(User::factory()->create());

        $portfolio = Portfolio::factory()->create();
        Transaction::factory()->buy(300)->costBasis(10)->yearsAgo()->portfolio($portfolio->id)->symbol('ACME')->create();

        $this->resetSplitsSyncedAt($portfolio);

        // 1:3 reverse split, stored with limited precision
        Split::create([
            'symbol' => 'ACME',
            'date' => now()->subMonths(6)->toDateString(),
            'split_amount' => 0.3333,
        ]);

        Split::syncToTransactions('ACME');

        $splitTransaction = Transaction::query()->symbol('ACME')->portfolio($portfolio->id)->where('split', true)->first();
        $holding = Holding::query()->portfolio($portfolio->id)->symbol('ACME')->first();

        $this->assertEqualsWithDelta(-200, $splitTransaction->quantity, 0.0001);
        $this->assertEqualsWithDelta(100, $holding->quantity, 0.0001);
        $this->assertEqualsWithDelta(30, $holding->average_cost_basis, 0.0001);
        $this->assertEqualsWithDelta(3000, $holding->total_cost_basis, 0.0001);
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

    protected function resetSplitsSyncedAt(Portfolio $portfolio): void
    {
        // the split sync date is set when the holding is created
        Holding::query()->portfolio($portfolio->id)->symbol('ACME')->update([
            'splits_synced_at' => null,
        ]);
    }
}
