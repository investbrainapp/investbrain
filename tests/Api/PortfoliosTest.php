<?php

namespace Tests\Api;

use App\Models\Portfolio;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PortfoliosTest extends TestCase
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
