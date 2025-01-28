<?php

namespace Tests;

use App\Models\Portfolio;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

class PortfoliosTest extends TestCase
{
    use RefreshDatabase;

    public function test_owner_is_assigned_to_portfolio_on_create(): void
    {
        $this->actingAs($user = User::factory()->create());

        $portfolio = Portfolio::factory()->create();

        $this->assertEquals($user->id, $portfolio->owner_id);
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
}
