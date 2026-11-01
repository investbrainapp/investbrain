<?php

declare(strict_types=1);

namespace Tests\Api;

use App\Models\Portfolio;
use App\Models\Transaction;
use App\Models\User;
use Database\Seeders\CurrencySeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use PHPUnit\Framework\Attributes\TestWith;

class TransactionsTest extends ApiTestCase
{
    use RefreshDatabase;

    protected User $user;

    protected Portfolio $portfolio;

    protected function setUp(): void
    {
        parent::setUp();

        // make user
        $this->user = User::factory()->create();

        // make portfolio
        $this->portfolio = Portfolio::factory()->makeOne();
        $this->portfolio->setOwnerIdAttribute($this->user->id);
        $this->portfolio->save();
    }

    public function test_can_list_transactions()
    {
        $this->actingAs($this->user);

        Transaction::factory(10)->create();

        $this->actingAs($this->user)
            ->getJson(route('api.transaction.index', ['page' => 1, 'itemsPerPage' => 5]))
            ->assertOk()
            ->assertJsonStructure([
                'data' => [['id', 'symbol', 'transaction_type', 'portfolio_id', 'date']],
                'meta' => ['current_page', 'last_page', 'total'],
                'links' => ['first', 'last', 'prev', 'next'],
            ]);
    }

    public function test_cannot_list_others_transactions()
    {
        // create transactions with existing user
        $this->actingAs($this->user);
        Transaction::factory(10)->create();

        // Create a new user
        $this->actingAs($user = User::factory()->create());
        Transaction::factory(1)->create();
        $this->actingAs($user)
            ->getJson(route('api.transaction.index', ['page' => 1, 'itemsPerPage' => 5]))
            ->assertOk()
            ->assertJsonCount(1, 'data');
    }

    public function test_cannot_access_transactions_when_unauthenticated()
    {
        $this->getJson(route('api.transaction.index'))->assertUnauthorized();
    }

    public function test_can_filter_transactions_by_column()
    {
        $this->actingAs($this->user);
        Transaction::factory(2)->buy()->symbol('AAPL')->portfolio($this->portfolio->id)->create();
        Transaction::factory()->buy()->symbol('GOOG')->portfolio($this->portfolio->id)->create();

        $response = $this->getJson(route('api.transaction.index', ['filter' => ['symbol' => 'AAPL']]));

        $response->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('data.0.symbol', 'AAPL')
            ->assertJsonPath('data.1.symbol', 'AAPL');
    }

    public function test_filter_with_comma_separated_values_matches_any_value()
    {
        $this->actingAs($this->user);
        Transaction::factory()->buy()->symbol('AAPL')->portfolio($this->portfolio->id)->create();
        Transaction::factory()->buy()->symbol('GOOG')->portfolio($this->portfolio->id)->create();
        Transaction::factory()->buy()->symbol('AMZN')->portfolio($this->portfolio->id)->create();

        $response = $this->getJson(route('api.transaction.index', ['filter' => ['symbol' => 'AAPL,GOOG'], 'sort' => 'symbol']));

        $response->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('data.0.symbol', 'AAPL')
            ->assertJsonPath('data.1.symbol', 'GOOG');
    }

    public function test_filtering_by_another_users_portfolio_returns_no_transactions()
    {
        $otherUser = User::factory()->create();
        $this->actingAs($otherUser);
        $otherTransaction = Transaction::factory()->buy()->create();

        $response = $this->actingAs($this->user)
            ->getJson(route('api.transaction.index', ['filter' => ['portfolio_id' => $otherTransaction->portfolio_id]]));

        $response->assertOk()->assertJsonCount(0, 'data');
    }

    public function test_can_search_transactions()
    {
        $this->actingAs($this->user);
        Transaction::factory()->buy()->symbol('AAPL')->portfolio($this->portfolio->id)->create();
        Transaction::factory()->buy()->symbol('GOOG')->portfolio($this->portfolio->id)->create();

        $response = $this->getJson(route('api.transaction.index', ['search' => 'AP']));

        $response->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.symbol', 'AAPL');
    }

    public function test_can_sort_transactions_descending()
    {
        $this->actingAs($this->user);
        Transaction::factory()->buy(3)->symbol('AAPL')->portfolio($this->portfolio->id)->create();
        Transaction::factory()->buy(1)->symbol('AAPL')->portfolio($this->portfolio->id)->create();
        Transaction::factory()->buy(2)->symbol('AAPL')->portfolio($this->portfolio->id)->create();

        $response = $this->getJson(route('api.transaction.index', ['sort' => '-quantity']));

        $response->assertOk();
        $this->assertSame([3, 2, 1], array_map('intval', array_column($response->json('data'), 'quantity')));
    }

    #[TestWith(['portfolio_id'])]
    #[TestWith(['symbol; DROP TABLE transactions'])]
    #[TestWith(['market_data.name'])]
    public function test_rejects_sorting_by_a_column_that_is_not_sortable(string $sort)
    {
        $response = $this->actingAs($this->user)
            ->getJson(route('api.transaction.index', ['sort' => $sort]));

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['sort' => 'The sort field may only contain: symbol, transaction_type, quantity, cost_basis, sale_price, date, created_at, updated_at.']);
    }

    public function test_rejects_filtering_by_a_column_that_is_not_filterable()
    {
        $response = $this->actingAs($this->user)
            ->getJson(route('api.transaction.index', ['filter' => ['cost_basis' => '100']]));

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['filter' => 'The filter field may only contain: symbol, portfolio_id, transaction_type, currency, split, reinvested_dividend.']);
    }

    public function test_rejects_search_that_is_not_a_string()
    {
        $response = $this->actingAs($this->user)
            ->getJson(route('api.transaction.index', ['search' => ['AAPL']]));

        $response->assertUnprocessable()->assertJsonValidationErrors(['search']);
    }

    #[TestWith(['101'])]
    #[TestWith(['-1'])]
    public function test_rejects_items_per_page_outside_allowed_range(string $itemsPerPage)
    {
        $response = $this->actingAs($this->user)
            ->getJson(route('api.transaction.index', ['itemsPerPage' => $itemsPerPage]));

        $response->assertUnprocessable()->assertJsonValidationErrors(['itemsPerPage']);
    }

    public function test_can_create_transaction()
    {
        Artisan::call('db:seed', [
            '--class' => CurrencySeeder::class,
            '--force' => true,
        ]);

        $this->actingAs($this->user);

        $data = [
            'symbol' => 'AAPL',
            'portfolio_id' => $this->portfolio->id,
            'transaction_type' => 'BUY',
            'quantity' => 10,
            'currency' => 'USD',
            'date' => now()->toDateString(),
            'cost_basis' => 150,
        ];

        $this->actingAs($this->user)
            ->postJson(route('api.transaction.store'), $data)
            ->assertCreated()
            ->assertJsonStructure([
                'id',
                'symbol',
                'portfolio_id',
                'transaction_type',
                'quantity',
                'date',
                'cost_basis',
                'sale_price',
            ]);
    }

    public function test_cannot_create_transaction_without_required_fields()
    {
        $this->actingAs($this->user)
            ->postJson(route('api.transaction.store'), [
                'portfolio_id' => $this->portfolio->id,
                'symbol' => null,
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['symbol']);
    }

    public function test_cannot_sell_more_than_owned()
    {
        Artisan::call('db:seed', [
            '--class' => CurrencySeeder::class,
            '--force' => true,
        ]);

        $this->actingAs($this->user);

        Transaction::factory(5)->buy()->lastYear()->portfolio($this->portfolio->id)->symbol('AAPL')->create();

        $data = [
            'symbol' => 'AAPL',
            'portfolio_id' => $this->portfolio->id,
            'transaction_type' => 'SELL',
            'quantity' => 6,
            'currency' => 'USD',
            'date' => now()->toDateString(),
            'sale_price' => 150,
        ];

        $this->actingAs($this->user)
            ->postJson(route('api.transaction.store'), $data)
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['quantity']);
    }

    public function test_can_sell_shares_bought_on_the_same_day()
    {
        Artisan::call('db:seed', [
            '--class' => CurrencySeeder::class,
            '--force' => true,
        ]);

        $this->actingAs($this->user)
            ->postJson(route('api.transaction.store'), [
                'symbol' => 'AAPL',
                'portfolio_id' => $this->portfolio->id,
                'transaction_type' => 'BUY',
                'quantity' => 1,
                'currency' => 'USD',
                'date' => '2026-04-10',
                'cost_basis' => 100,
            ])
            ->assertCreated();

        $this->actingAs($this->user)
            ->postJson(route('api.transaction.store'), [
                'symbol' => 'AAPL',
                'portfolio_id' => $this->portfolio->id,
                'transaction_type' => 'SELL',
                'quantity' => 1,
                'currency' => 'USD',
                'date' => '2026-04-10',
                'sale_price' => 105,
            ])
            ->assertCreated()
            ->assertJsonPath('transaction_type', 'SELL');

        $this->assertDatabaseHas('transactions', [
            'portfolio_id' => $this->portfolio->id,
            'symbol' => 'AAPL',
            'transaction_type' => 'SELL',
            'quantity' => 1,
        ]);
    }

    public function test_can_show_a_transaction()
    {
        $this->actingAs($this->user);

        $transaction = Transaction::factory()->create();

        $this->getJson(route('api.transaction.show', $transaction))
            ->assertOk()
            ->assertJsonFragment([
                'id' => $transaction->id,
            ]);
    }

    public function test_cannot_show_nonexistent_transactions()
    {
        $this->actingAs($this->user)
            ->getJson(route('api.transaction.show', ['transaction' => 999]))
            ->assertNotFound();
    }

    public function test_can_update_a_transaction()
    {
        $this->actingAs($this->user);

        $transaction = Transaction::factory()->create();

        $data = [
            'symbol' => 'ZZZ',
            'transaction_type' => 'BUY',
            'cost_basis' => 200.19,
            'quantity' => 5,
        ];

        $this->actingAs($this->user)
            ->putJson(route('api.transaction.update', $transaction), $data)
            ->assertOk()
            ->assertJsonFragment([
                'symbol' => 'ZZZ',
                'transaction_type' => 'BUY',
                'cost_basis' => 200.19,
                'quantity' => 5,
            ]);
    }

    public function test_shared_user_can_update_transaction()
    {
        // create transaction (and portfolio)
        $this->actingAs($this->user);
        $transaction = Transaction::factory()->create();

        // share it
        $otherUser = User::factory()->create();
        $transaction->portfolio->share($otherUser->email, true);

        // shared user tries to update it
        $this->actingAs($otherUser)
            ->putJson(route('api.transaction.update', $transaction), ['symbol' => 'ZZZ'])
            ->assertOk()
            ->assertJsonFragment([
                'symbol' => 'ZZZ',
            ]);
    }

    public function test_cannot_update_transaction_without_permission()
    {
        $this->actingAs($this->user);
        $transaction = Transaction::factory()->create();

        $otherUser = User::factory()->create();
        $this->actingAs($otherUser)
            ->putJson(route('api.transaction.update', $transaction), ['symbol' => 'AAPL'])
            ->assertForbidden();
    }

    public function test_cannot_move_transaction_into_portfolio_without_full_access()
    {
        $this->actingAs($this->user);
        $transaction = Transaction::factory()->create();

        $readOnlyPortfolio = Portfolio::factory()->makeOne();
        $readOnlyPortfolio->setOwnerIdAttribute(User::factory()->create()->id);
        $readOnlyPortfolio->save();
        $readOnlyPortfolio->users()->syncWithoutDetaching([
            $this->user->id => ['full_access' => false, 'owner' => false],
        ]);

        $this->actingAs($this->user)
            ->putJson(route('api.transaction.update', $transaction), ['portfolio_id' => $readOnlyPortfolio->id])
            ->assertForbidden();

        $this->assertEquals($transaction->portfolio_id, $transaction->fresh()->portfolio_id);
    }

    public function test_can_delete_a_transaction()
    {
        $this->actingAs($this->user);
        $transaction = Transaction::factory()->create();

        $this->deleteJson(route('api.transaction.destroy', $transaction))
            ->assertNoContent();

        $this->assertDatabaseMissing('transactions', ['id' => $transaction->id]);
    }

    public function test_cannot_delete_transaction_without_permission()
    {
        $this->actingAs($this->user);
        $transaction = Transaction::factory()->create();

        $otherUser = User::factory()->create();
        $this->actingAs($otherUser)
            ->deleteJson(route('api.transaction.destroy', $transaction))
            ->assertForbidden();
    }
}
