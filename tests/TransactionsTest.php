<?php

declare(strict_types=1);

namespace Tests;

use App\Models\Holding;
use App\Models\Portfolio;
use App\Models\Transaction;
use App\Models\User;
use App\Rules\QuantityValidationRule;
use Database\Seeders\CurrencySeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Notification;
use Livewire\Livewire;

class TransactionsTest extends TestCase
{
    use RefreshDatabase;

    public function test_can_create_a_transaction(): void
    {
        $this->actingAs($user = User::factory()->create());

        $transaction = Transaction::factory()->create();

        $this->assertNotNull($transaction);
    }

    public function test_sales_calculate_cost_basis(): void
    {
        $this->actingAs($user = User::factory()->create());

        Transaction::factory(5)->buy()->lastYear()->symbol('AAPL')->create();

        $transaction = Transaction::factory()->sell()->lastMonth()->symbol('AAPL')->create();

        $this->assertNotNull($transaction->cost_basis);
    }

    public function test_purchases_dont_have_sale_price(): void
    {
        $this->actingAs($user = User::factory()->create());

        $transaction = Transaction::factory()->buy()->create();

        $this->assertNull($transaction->sale_price);
    }

    public function test_transaction_synced_to_holding(): void
    {
        $this->actingAs($user = User::factory()->create());

        $portfolio = Portfolio::factory()->create();

        Transaction::factory(5)->buy()->lastYear()->portfolio($portfolio->id)->symbol('AAPL')->create();
        $transaction = Transaction::factory()->sell()->lastMonth()->portfolio($portfolio->id)->symbol('AAPL')->create();

        $this->assertDatabaseHas('holdings', [
            'portfolio_id' => $portfolio->id,
            'symbol' => 'AAPL',
            'quantity' => 4,
        ]);

        $holding = Holding::where([
            'portfolio_id' => $portfolio->id,
            'symbol' => 'AAPL',
        ])->first();

        $this->assertEqualsWithDelta(
            $holding->realized_gain_dollars,
            $transaction->sale_price - $transaction->cost_basis,
            0.01
        );
    }

    public function test_cannot_sell_more_than_owned(): void
    {
        $this->actingAs($user = User::factory()->create());

        $portfolio = Portfolio::factory()->create();

        Transaction::factory(5)->buy()->lastYear()->portfolio($portfolio->id)->symbol('AAPL')->create();
        $sale_transaction = Transaction::factory()->sell(6)->lastMonth()->portfolio($portfolio->id)->symbol('AAPL')->make();

        $rule = new QuantityValidationRule($portfolio, $sale_transaction->symbol, 'SELL', $sale_transaction->date, $sale_transaction);

        $failed = false;
        $rule->validate('quantity', $sale_transaction->quantity, function () use (&$failed) {
            $failed = true;
        });

        $this->assertTrue($failed, 'Not permitted to sell more than owned.');
    }

    public function test_can_sell_shares_bought_on_the_same_day(): void
    {
        $this->actingAs($user = User::factory()->create());

        $portfolio = Portfolio::factory()->create();

        Transaction::factory()->buy(5)->date('2026-04-10')->portfolio($portfolio->id)->symbol('AAPL')->create();

        $rule = new QuantityValidationRule($portfolio, 'AAPL', 'SELL', '2026-04-10', null);

        $failed = false;
        $rule->validate('quantity', 5, function () use (&$failed) {
            $failed = true;
        });

        $this->assertFalse($failed, 'Same-day purchases should count toward the available quantity.');
    }

    public function test_cannot_sell_more_than_bought_on_the_same_day(): void
    {
        $this->actingAs($user = User::factory()->create());

        $portfolio = Portfolio::factory()->create();

        Transaction::factory()->buy(5)->date('2026-04-10')->portfolio($portfolio->id)->symbol('AAPL')->create();

        $rule = new QuantityValidationRule($portfolio, 'AAPL', 'SELL', '2026-04-10', null);

        $failed = false;
        $rule->validate('quantity', 6, function () use (&$failed) {
            $failed = true;
        });

        $this->assertTrue($failed, 'Not permitted to sell more than owned.');
    }

    public function test_transactions_list_cannot_open_a_transaction_from_another_portfolio(): void
    {
        [$member, $ownPortfolio, $readOnlyTransaction] = $this->createReadOnlyMemberWithOwnPortfolio();

        Livewire::actingAs($member)
            ->test('transactions-list', ['portfolio' => $ownPortfolio, 'transactions' => $ownPortfolio->transactions])
            ->call('showTransactionDialog', $readOnlyTransaction->id)
            ->assertNotFound();
    }

    public function test_cannot_delete_a_read_only_transaction_through_own_portfolio_form(): void
    {
        [$member, $ownPortfolio, $readOnlyTransaction] = $this->createReadOnlyMemberWithOwnPortfolio();

        Livewire::actingAs($member)
            ->test('manage-transaction-form', ['portfolio' => $ownPortfolio, 'transaction' => $readOnlyTransaction])
            ->call('delete')
            ->assertForbidden();

        $this->assertModelExists($readOnlyTransaction);
    }

    public function test_cannot_move_a_transaction_into_a_read_only_portfolio_through_the_form(): void
    {
        Artisan::call('db:seed', ['--class' => CurrencySeeder::class, '--force' => true]);

        [$member, $ownPortfolio, $readOnlyTransaction] = $this->createReadOnlyMemberWithOwnPortfolio();

        $ownTransaction = Transaction::factory()->buy()->lastYear()->portfolio($ownPortfolio->id)->symbol('AAPL')->create();

        Livewire::actingAs($member)
            ->test('manage-transaction-form', ['portfolio' => $ownPortfolio, 'transaction' => $ownTransaction])
            ->set('portfolio_id', $readOnlyTransaction->portfolio_id)
            ->call('update')
            ->assertForbidden();

        $this->assertEquals($ownPortfolio->id, $ownTransaction->fresh()->portfolio_id);
    }

    public function test_transaction_can_store_optional_notes(): void
    {
        $this->actingAs(User::factory()->create());

        $transaction = Transaction::factory()->buy()->create([
            'notes' => 'Bought after earnings dip',
        ]);

        $this->assertDatabaseHas('transactions', [
            'id' => $transaction->id,
            'notes' => 'Bought after earnings dip',
        ]);
    }

    /**
     * Create a user who has read-only access to someone else's portfolio and owns a portfolio of their own.
     *
     * @return array{0: User, 1: Portfolio, 2: Transaction}
     */
    private function createReadOnlyMemberWithOwnPortfolio(): array
    {
        Notification::fake();

        $this->actingAs(User::factory()->create());
        $readOnlyPortfolio = Portfolio::factory()->create();
        $readOnlyTransaction = Transaction::factory()->buy()->lastYear()->portfolio($readOnlyPortfolio->id)->symbol('AAPL')->create();

        $this->actingAs($member = User::factory()->create());
        $readOnlyPortfolio->share($member->email);
        $ownPortfolio = Portfolio::factory()->create();

        return [$member, $ownPortfolio, $readOnlyTransaction];
    }
}
