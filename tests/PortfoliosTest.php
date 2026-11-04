<?php

declare(strict_types=1);

namespace Tests;

use App\Models\Portfolio;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Features\SupportLockedProperties\CannotUpdateLockedPropertyException;
use Livewire\Livewire;

class PortfoliosTest extends TestCase
{
    use RefreshDatabase;

    public function test_owner_is_assigned_to_portfolio_on_create(): void
    {
        $this->actingAs($user = User::factory()->create());

        $portfolio = Portfolio::factory()->create();

        $this->assertEquals($user->id, $portfolio->owner_id);
    }

    public function test_owner_reflects_ownership_set_after_the_users_were_loaded(): void
    {
        $this->actingAs($owner = User::factory()->create());
        $portfolio = Portfolio::factory()->create();
        $portfolio->load('users');

        $newOwner = User::factory()->create();
        $portfolio->users()->sync([$newOwner->id => ['owner' => true]]);

        $this->assertSame($newOwner->id, $portfolio->owner->id);
    }

    public function test_my_portfolios_keeps_earlier_constraints(): void
    {
        $this->actingAs(User::factory()->create());
        Portfolio::factory()->create(['title' => 'Kept']);
        Portfolio::factory()->create(['title' => 'Filtered out']);

        $this->assertSame(['Kept'], Portfolio::where('title', 'Kept')->myPortfolios()->pluck('title')->all());
    }

    public function test_without_wishlists_keeps_earlier_constraints(): void
    {
        $this->actingAs(User::factory()->create());
        Portfolio::factory()->create(['title' => 'Kept', 'wishlist' => false]);
        Portfolio::factory()->create(['title' => 'Filtered out', 'wishlist' => false]);

        $this->assertSame(['Kept'], Portfolio::where('title', 'Kept')->withoutWishlists()->pluck('title')->all());
    }

    public function test_owner_can_be_forced_on_create(): void
    {
        $this->actingAs($user = User::factory()->create());

        $portfolio = Portfolio::factory()->make();
        $portfolio->owner_id = $user->id;
        $portfolio->save();

        $this->assertEquals($user->id, $portfolio->owner_id);
    }

    public function test_owner_cannot_be_changed_on_update(): void
    {
        $this->actingAs($owner = User::factory()->create());

        $interloper = User::factory()->create();

        $portfolio = Portfolio::factory()->create();
        $portfolio->owner_id = $interloper->id;
        $portfolio->save();

        $this->assertEquals($owner->id, $portfolio->owner_id);
    }

    public function test_performance_chart_time_periods_cannot_be_changed_from_the_browser(): void
    {
        $this->actingAs(User::factory()->create());
        $portfolio = Portfolio::factory()->create();

        $this->expectException(CannotUpdateLockedPropertyException::class);

        Livewire::test('portfolio-performance-chart', ['portfolio' => $portfolio])
            ->set('scopeOptions', [['id' => 'YTD', 'name' => 'Year to date', 'method' => 'subDays', 'args' => [1]]]);
    }
}
