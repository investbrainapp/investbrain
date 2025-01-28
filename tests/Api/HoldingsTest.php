<?php

namespace Tests\Api;

use Tests\TestCase;
use App\Models\User;
use App\Models\Holding;
use App\Models\Portfolio;
use App\Models\Transaction;
use Illuminate\Foundation\Testing\RefreshDatabase;

class HoldingsTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;
    protected Portfolio $portfolio;

    protected function setUp(): void
    {
        parent::setUp();

        // make user
        $this->user = User::factory()->create();
    }

    public function test_can_list_holdings()
    {
        $this->actingAs($this->user);

        Transaction::factory(10)->create();
        
        $this->actingAs($this->user)
            ->getJson(route('api.holding.index', ['page' => 1, 'itemsPerPage' => 5]))
            ->assertOk()
            ->assertJsonStructure([
                'data' => [['id', 'symbol', 'portfolio_id', 'total_market_value', 'dividends_earned']],
                'meta' => ['current_page', 'last_page', 'total'],
                'links' => ['first', 'last', 'prev', 'next']
            ]);
    }

    public function test_cannot_list_others_holdings()
    {
        // create transactions with existing user
        $this->actingAs($this->user);
        Transaction::factory(10)->create();
        
        // Create a new user
        $this->actingAs($user = User::factory()->create());
        Transaction::factory(1)->create();
        $this->actingAs($user)
            ->getJson(route('api.holding.index', ['page' => 1, 'itemsPerPage' => 5]))
            ->assertOk()
            ->assertJsonCount(1, 'data');
    }

    public function test_cannot_access_holdings_when_unauthenticated()
    {
        $this->getJson(route('api.holding.index'))->assertUnauthorized();
    }

    public function test_can_show_a_holding()
    {
        $this->actingAs($this->user);

        $transaction = Transaction::factory()->create();

        $holding = Holding::where(['portfolio_id' => $transaction->portfolio->id, 'symbol' => $transaction->symbol])->firstOrFail();

        $this->getJson(route('api.holding.show', ['portfolio' => $transaction->portfolio_id, 'symbol' => $transaction->symbol]))
            ->assertOk()
            ->assertJsonFragment([
                'id' => $holding->id,
            ]);
    }

    public function test_cannot_show_nonexistent_holdings()
    {
        $this->actingAs($this->user)
            ->getJson(route('api.holding.show', ['portfolio' => 'abc-123-foo-BAR', 'symbol' => 'AAPL']))
            ->assertNotFound();
    }

    public function test_can_update_holding_options()
    {
        $this->actingAs($this->user);
        $transaction = Transaction::factory()->create();

        $data = [
            'reinvest_dividends' => true
        ];

        $this->actingAs($this->user)
            ->putJson(route('api.holding.update', ['portfolio' => $transaction->portfolio_id, 'symbol' => $transaction->symbol]), $data)
            ->assertOk()
            ->assertJsonFragment([
                'reinvest_dividends' => true
            ]);
    }

    public function test_cannot_update_holding_without_permission()
    {
        $this->actingAs($this->user);
        $transaction = Transaction::factory()->create();

        $data = [
            'reinvest_dividends' => true
        ];

        $otherUser = User::factory()->create();
        $this->actingAs($otherUser)
            ->putJson(route('api.holding.update', ['portfolio' => $transaction->portfolio_id, 'symbol' => $transaction->symbol]), $data)
            ->assertForbidden();
    }

}