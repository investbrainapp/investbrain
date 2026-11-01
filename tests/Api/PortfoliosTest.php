<?php

declare(strict_types=1);

namespace Tests\Api;

use App\Models\Portfolio;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

class PortfoliosTest extends ApiTestCase
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

    public function test_can_list_own_portfolios_with_pagination()
    {
        $this->actingAs($this->user);

        Portfolio::factory(10)->create();

        $this->actingAs($this->user)
            ->getJson(route('api.portfolio.index', ['page' => 1, 'itemsPerPage' => 5]))
            ->assertOk()
            ->assertJsonStructure([
                'data' => [['id', 'title', 'owner', 'holdings', 'transactions']],
                'meta' => ['current_page', 'last_page', 'total'],
                'links' => ['first', 'last', 'prev', 'next'],
            ]);
    }

    public function test_cannot_list_others_portfolios()
    {
        // create portfolios with existing user
        $this->actingAs($this->user);
        Portfolio::factory(10)->create();

        // Create a new user
        $this->actingAs($user = User::factory()->create());
        Portfolio::factory(1)->create();
        $this->actingAs($user)
            ->getJson(route('api.portfolio.index', ['page' => 1, 'itemsPerPage' => 5]))
            ->assertOk()
            ->assertJsonCount(1, 'data');
    }

    public function test_cannot_access_portfolios_when_unauthenticated()
    {
        $this->getJson(route('api.portfolio.index'))->assertUnauthorized();
    }

    public function test_can_filter_portfolios_by_holding_symbol()
    {
        $this->actingAs($this->user);
        $applePortfolio = Portfolio::factory()->create();
        $googlePortfolio = Portfolio::factory()->create();
        Transaction::factory()->buy()->symbol('AAPL')->portfolio($applePortfolio->id)->create();
        Transaction::factory()->buy()->symbol('GOOG')->portfolio($googlePortfolio->id)->create();

        $response = $this->getJson(route('api.portfolio.index', ['filter' => ['holdings.symbol' => 'AAPL']]));

        $response->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $applePortfolio->id);
    }

    public function test_can_filter_portfolios_by_boolean_column()
    {
        $this->actingAs($this->user);
        Portfolio::factory()->create(['wishlist' => false]);
        $wishlist = Portfolio::factory()->create(['wishlist' => true]);

        $response = $this->getJson(route('api.portfolio.index', ['filter' => ['wishlist' => 'true']]));

        $response->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $wishlist->id);
    }

    public function test_can_search_portfolios_across_title_and_notes()
    {
        $this->actingAs($this->user);
        $byTitle = Portfolio::factory()->create(['title' => 'Retirement', 'notes' => null]);
        $byNotes = Portfolio::factory()->create(['title' => 'Brokerage', 'notes' => 'saving for retirement']);
        Portfolio::factory()->create(['title' => 'Crypto', 'notes' => 'speculative']);

        $response = $this->getJson(route('api.portfolio.index', ['search' => 'RETIRE', 'sort' => 'title']));

        $response->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('data.0.id', $byNotes->id)
            ->assertJsonPath('data.1.id', $byTitle->id);
    }

    public function test_can_sort_portfolios_by_title()
    {
        $this->actingAs($this->user);
        Portfolio::factory()->create(['title' => 'Bravo']);
        Portfolio::factory()->create(['title' => 'Charlie']);
        Portfolio::factory()->create(['title' => 'Alpha']);

        $response = $this->getJson(route('api.portfolio.index', ['sort' => 'title']));

        $response->assertOk();
        $this->assertSame(['Alpha', 'Bravo', 'Charlie'], array_column($response->json('data'), 'title'));
    }

    public function test_can_create_a_portfolio()
    {
        $data = Portfolio::factory()->make()->toArray();

        $this->actingAs($this->user)
            ->postJson(route('api.portfolio.store'), $data)
            ->assertCreated()
            ->assertJsonStructure(['id', 'title', 'owner']);

        $this->assertDatabaseHas('portfolios', ['title' => $data['title']]);
    }

    public function test_cannot_create_portfolio_without_required_fields()
    {
        $this->actingAs($this->user)
            ->postJson(route('api.portfolio.store'), [])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['title']);
    }

    public function test_can_show_a_portfolio()
    {
        $this->actingAs($this->user);
        $portfolio = Portfolio::factory()->create();

        $this->actingAs($this->user)
            ->getJson(route('api.portfolio.show', $portfolio))
            ->assertOk()
            ->assertJsonStructure(['id', 'title', 'owner']);
    }

    public function test_cannot_show_nonexistent_portfolio()
    {
        $this->actingAs($this->user)
            ->getJson(route('api.portfolio.show', ['portfolio' => 999]))
            ->assertNotFound();
    }

    public function test_can_update_a_portfolio()
    {
        $updatedData = ['title' => 'Updated Portfolio Title'];

        $this->actingAs($this->user);
        $portfolio = Portfolio::factory()->create();

        $this->actingAs($this->user)
            ->putJson(route('api.portfolio.update', $portfolio), $updatedData)
            ->assertOk()
            ->assertJson($updatedData);

        $this->assertDatabaseHas('portfolios', $updatedData);
    }

    public function test_shared_user_can_update_portfolio()
    {
        // create portfolio
        $this->actingAs($this->user);
        $portfolio = Portfolio::factory()->create();

        // share it
        $otherUser = User::factory()->create();
        $portfolio->share($otherUser->email, true);

        // shared user tries to update it
        $this->actingAs($otherUser)
            ->putJson(route('api.portfolio.update', $portfolio), ['title' => 'A brand new updated title'])
            ->assertOk()
            ->assertJsonFragment([
                'title' => 'A brand new updated title',
            ]);
    }

    public function test_removed_user_cannot_update_portfolio()
    {
        // create portfolio
        $this->actingAs($this->user);
        $portfolio = Portfolio::factory()->create();

        // share it
        $otherUser = User::factory()->create();
        $portfolio->share($otherUser->email, true);

        // unshare it
        $otherUser = User::factory()->create();
        $portfolio->unShare($otherUser->id);

        // shared user tries to update it
        $this->actingAs($otherUser)
            ->putJson(route('api.portfolio.update', $portfolio), ['Title' => 'A brand new updated title'])
            ->assertForbidden();
    }

    public function test_read_only_user_cannot_update_portfolio()
    {
        // create portfolio
        $this->actingAs($this->user);
        $portfolio = Portfolio::factory()->create();

        // share it
        $otherUser = User::factory()->create();
        $portfolio->share($otherUser->email, false);

        // shared user tries to update it
        $this->actingAs($otherUser)
            ->putJson(route('api.portfolio.update', $portfolio), ['Title' => 'A brand new updated title'])
            ->assertForbidden();
    }

    public function test_cannot_update_portfolio_without_permission()
    {
        $this->actingAs($this->user);
        $portfolio = Portfolio::factory()->create();

        $otherUser = User::factory()->create();
        $this->actingAs($otherUser)
            ->putJson(route('api.portfolio.update', $portfolio), ['title' => 'New Title'])
            ->assertForbidden();
    }

    public function test_can_delete_a_portfolio()
    {
        $this->actingAs($this->user);
        $portfolio = Portfolio::factory()->create();

        $this->actingAs($this->user)
            ->deleteJson(route('api.portfolio.destroy', $portfolio))
            ->assertNoContent();

        $this->assertDatabaseMissing('portfolios', ['id' => $portfolio->id]);
    }

    public function test_cannot_delete_portfolio_without_permission()
    {
        $this->actingAs($this->user);
        $portfolio = Portfolio::factory()->create();

        $otherUser = User::factory()->create();
        $this->actingAs($otherUser)
            ->deleteJson(route('api.portfolio.destroy', $portfolio))
            ->assertForbidden();
    }
}
