<?php

declare(strict_types=1);

namespace Tests\Mcp;

use App\Mcp\Servers\InvestbrainServer;
use App\Mcp\Tools\CreatePortfolioTool;
use App\Mcp\Tools\CreateTransactionTool;
use App\Mcp\Tools\DeletePortfolioTool;
use App\Mcp\Tools\DeleteTransactionTool;
use App\Mcp\Tools\GetAuthenticatedUserTool;
use App\Mcp\Tools\ListHoldingsTool;
use App\Mcp\Tools\ListPortfoliosTool;
use App\Mcp\Tools\ListTransactionsTool;
use App\Mcp\Tools\ShowHoldingTool;
use App\Mcp\Tools\ShowMarketDataTool;
use App\Mcp\Tools\ShowPortfolioTool;
use App\Mcp\Tools\ShowTransactionTool;
use App\Mcp\Tools\UpdateHoldingTool;
use App\Mcp\Tools\UpdatePortfolioTool;
use App\Mcp\Tools\UpdateTransactionTool;
use App\Models\Holding;
use App\Models\Portfolio;
use App\Models\Transaction;
use App\Models\User;
use Database\Seeders\CurrencySeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Tests\TestCase;

class InvestbrainServerTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;

    protected Portfolio $portfolio;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();

        $this->portfolio = Portfolio::factory()->makeOne();
        $this->portfolio->setOwnerIdAttribute($this->user->id);
        $this->portfolio->save();
    }

    public function test_can_get_authenticated_user(): void
    {
        InvestbrainServer::actingAs($this->user)
            ->tool(GetAuthenticatedUserTool::class, [])
            ->assertOk()
            ->assertSee('"email":"'.$this->user->email.'"');
    }

    public function test_can_list_own_portfolios(): void
    {
        $this->actingAs($this->user);
        Portfolio::factory(3)->create();

        $this->actingAs($otherUser = User::factory()->create());
        Portfolio::factory(2)->create();

        InvestbrainServer::actingAs($this->user)
            ->tool(ListPortfoliosTool::class, [])
            ->assertOk()
            ->assertSee('"total":4');
    }

    public function test_can_create_portfolio(): void
    {
        InvestbrainServer::actingAs($this->user)
            ->tool(CreatePortfolioTool::class, ['title' => 'My New Portfolio'])
            ->assertOk()
            ->assertSee('"title":"My New Portfolio"');

        $this->assertDatabaseHas('portfolios', ['title' => 'My New Portfolio']);
    }

    public function test_create_portfolio_requires_title(): void
    {
        InvestbrainServer::actingAs($this->user)
            ->tool(CreatePortfolioTool::class, [])
            ->assertHasErrors();
    }

    public function test_can_show_portfolio(): void
    {
        InvestbrainServer::actingAs($this->user)
            ->tool(ShowPortfolioTool::class, ['portfolio_id' => $this->portfolio->id])
            ->assertOk()
            ->assertSee('"id":"'.$this->portfolio->id.'"');
    }

    public function test_show_portfolio_returns_error_when_not_found(): void
    {
        InvestbrainServer::actingAs($this->user)
            ->tool(ShowPortfolioTool::class, ['portfolio_id' => 'does-not-exist'])
            ->assertHasErrors(['not found']);
    }

    public function test_cannot_show_portfolio_without_access(): void
    {
        $otherUser = User::factory()->create();

        InvestbrainServer::actingAs($otherUser)
            ->tool(ShowPortfolioTool::class, ['portfolio_id' => $this->portfolio->id])
            ->assertHasErrors();
    }

    public function test_can_update_portfolio(): void
    {
        InvestbrainServer::actingAs($this->user)
            ->tool(UpdatePortfolioTool::class, [
                'portfolio_id' => $this->portfolio->id,
                'title' => 'Updated Title',
            ])
            ->assertOk()
            ->assertSee('"title":"Updated Title"');

        $this->assertDatabaseHas('portfolios', ['id' => $this->portfolio->id, 'title' => 'Updated Title']);
    }

    public function test_cannot_update_portfolio_without_access(): void
    {
        $otherUser = User::factory()->create();

        InvestbrainServer::actingAs($otherUser)
            ->tool(UpdatePortfolioTool::class, [
                'portfolio_id' => $this->portfolio->id,
                'title' => 'Updated Title',
            ])
            ->assertHasErrors();
    }

    public function test_can_delete_portfolio(): void
    {
        InvestbrainServer::actingAs($this->user)
            ->tool(DeletePortfolioTool::class, ['portfolio_id' => $this->portfolio->id])
            ->assertOk();

        $this->assertDatabaseMissing('portfolios', ['id' => $this->portfolio->id]);
    }

    public function test_cannot_delete_portfolio_without_access(): void
    {
        $otherUser = User::factory()->create();

        InvestbrainServer::actingAs($otherUser)
            ->tool(DeletePortfolioTool::class, ['portfolio_id' => $this->portfolio->id])
            ->assertHasErrors();

        $this->assertDatabaseHas('portfolios', ['id' => $this->portfolio->id]);
    }

    public function test_can_list_own_transactions(): void
    {
        $this->actingAs($this->user);
        Transaction::factory(3)->create();

        $this->actingAs($otherUser = User::factory()->create());
        Transaction::factory(2)->create();

        InvestbrainServer::actingAs($this->user)
            ->tool(ListTransactionsTool::class, [])
            ->assertOk()
            ->assertSee('"total":3');
    }

    public function test_can_create_transaction(): void
    {
        Artisan::call('db:seed', ['--class' => CurrencySeeder::class, '--force' => true]);

        InvestbrainServer::actingAs($this->user)
            ->tool(CreateTransactionTool::class, [
                'symbol' => 'AAPL',
                'portfolio_id' => $this->portfolio->id,
                'transaction_type' => 'BUY',
                'quantity' => 10,
                'currency' => 'USD',
                'date' => now()->toDateString(),
                'cost_basis' => 150,
            ])
            ->assertOk()
            ->assertSee('"symbol":"AAPL"');

        $this->assertDatabaseHas('transactions', ['symbol' => 'AAPL', 'portfolio_id' => $this->portfolio->id]);
    }

    public function test_create_transaction_requires_symbol(): void
    {
        InvestbrainServer::actingAs($this->user)
            ->tool(CreateTransactionTool::class, [
                'portfolio_id' => $this->portfolio->id,
            ])
            ->assertHasErrors();
    }

    public function test_cannot_sell_more_transaction_quantity_than_owned(): void
    {
        Artisan::call('db:seed', ['--class' => CurrencySeeder::class, '--force' => true]);

        $this->actingAs($this->user);
        Transaction::factory(5)->buy()->lastYear()->portfolio($this->portfolio->id)->symbol('AAPL')->create();

        InvestbrainServer::actingAs($this->user)
            ->tool(CreateTransactionTool::class, [
                'symbol' => 'AAPL',
                'portfolio_id' => $this->portfolio->id,
                'transaction_type' => 'SELL',
                'quantity' => 999,
                'currency' => 'USD',
                'date' => now()->toDateString(),
                'sale_price' => 150,
            ])
            ->assertHasErrors();
    }

    public function test_can_show_transaction(): void
    {
        $this->actingAs($this->user);
        $transaction = Transaction::factory()->create();

        InvestbrainServer::actingAs($this->user)
            ->tool(ShowTransactionTool::class, ['transaction_id' => $transaction->id])
            ->assertOk()
            ->assertSee('"id":"'.$transaction->id.'"');
    }

    public function test_can_update_transaction(): void
    {
        $this->actingAs($this->user);
        $transaction = Transaction::factory()->create();

        InvestbrainServer::actingAs($this->user)
            ->tool(UpdateTransactionTool::class, [
                'transaction_id' => $transaction->id,
                'symbol' => 'ZZZ',
            ])
            ->assertOk()
            ->assertSee('"symbol":"ZZZ"');
    }

    public function test_cannot_update_transaction_without_access(): void
    {
        $this->actingAs($this->user);
        $transaction = Transaction::factory()->create();

        $otherUser = User::factory()->create();

        InvestbrainServer::actingAs($otherUser)
            ->tool(UpdateTransactionTool::class, [
                'transaction_id' => $transaction->id,
                'symbol' => 'ZZZ',
            ])
            ->assertHasErrors();
    }

    public function test_can_delete_transaction(): void
    {
        $this->actingAs($this->user);
        $transaction = Transaction::factory()->create();

        InvestbrainServer::actingAs($this->user)
            ->tool(DeleteTransactionTool::class, ['transaction_id' => $transaction->id])
            ->assertOk();

        $this->assertDatabaseMissing('transactions', ['id' => $transaction->id]);
    }

    public function test_can_list_own_holdings(): void
    {
        $this->actingAs($this->user);
        Transaction::factory(3)->create();

        InvestbrainServer::actingAs($this->user)
            ->tool(ListHoldingsTool::class, [])
            ->assertOk();
    }

    public function test_can_show_holding(): void
    {
        $this->actingAs($this->user);
        $transaction = Transaction::factory()->create();

        $holding = Holding::where([
            'portfolio_id' => $transaction->portfolio_id,
            'symbol' => $transaction->symbol,
        ])->firstOrFail();

        InvestbrainServer::actingAs($this->user)
            ->tool(ShowHoldingTool::class, [
                'portfolio_id' => $transaction->portfolio_id,
                'symbol' => $transaction->symbol,
            ])
            ->assertOk()
            ->assertSee('"id":"'.$holding->id.'"');
    }

    public function test_show_holding_returns_error_when_not_found(): void
    {
        InvestbrainServer::actingAs($this->user)
            ->tool(ShowHoldingTool::class, [
                'portfolio_id' => $this->portfolio->id,
                'symbol' => 'ZZZZ',
            ])
            ->assertHasErrors(['not found']);
    }

    public function test_can_update_holding(): void
    {
        $this->actingAs($this->user);
        $transaction = Transaction::factory()->create();

        InvestbrainServer::actingAs($this->user)
            ->tool(UpdateHoldingTool::class, [
                'portfolio_id' => $transaction->portfolio_id,
                'symbol' => $transaction->symbol,
                'reinvest_dividends' => true,
            ])
            ->assertOk()
            ->assertSee('"reinvest_dividends":true');
    }

    public function test_cannot_update_holding_without_access(): void
    {
        $this->actingAs($this->user);
        $transaction = Transaction::factory()->create();

        $otherUser = User::factory()->create();

        InvestbrainServer::actingAs($otherUser)
            ->tool(UpdateHoldingTool::class, [
                'portfolio_id' => $transaction->portfolio_id,
                'symbol' => $transaction->symbol,
                'reinvest_dividends' => true,
            ])
            ->assertHasErrors();
    }

    public function test_can_get_market_data(): void
    {
        InvestbrainServer::actingAs($this->user)
            ->tool(ShowMarketDataTool::class, ['symbol' => 'AAPL'])
            ->assertOk()
            ->assertSee('"symbol":"AAPL"');
    }
}
