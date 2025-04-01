<?php

declare(strict_types=1);

namespace Tests;

use App\Models\Holding;
use App\Models\Portfolio;
use App\Models\Split;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

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

        $this->assertCount(2, $transactions); // todo: intermittently failing
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
}
