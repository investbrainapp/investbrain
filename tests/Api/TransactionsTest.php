<?php

declare(strict_types=1);

namespace Tests\Api;

use App\Models\Portfolio;
use App\Models\Transaction;
use App\Models\User;
use Database\Seeders\CurrencySeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Tests\TestCase;

class TransactionsTest extends TestCase
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
