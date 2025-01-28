<?php

namespace Tests;

use App\Models\Portfolio;
use App\Models\User;
use App\Policies\PortfolioPolicy;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;

class PortfolioPolicyTest extends TestCase
{
    use RefreshDatabase;

    protected $policy;

    protected $owner;

    protected $user;

    protected $portfolio;

    protected function setUp(): void
    {
        parent::setUp();

        $this->policy = new PortfolioPolicy;

        $this->owner = User::factory()->create();
        Auth::login($this->owner);
        $this->portfolio = Portfolio::factory()->create();

        // Attach the users to the portfolio
        $this->user = User::factory()->create();
        $this->portfolio->users()->syncWithoutDetaching([
            $this->user->id => [
                'full_access' => false,
                'owner' => false,
            ],
        ]);
    }

    public function test_stranger_access_via_web()
    {
        $user = User::factory()->create();

        $result = $this->actingAs($user)->get(route('portfolio.show', ['portfolio' => $this->portfolio]));

        $result->assertStatus(403);
    }

    public function test_stranger_access_via_policy()
    {
        $user = User::factory()->create();

        $result = $this->policy->readOnly($user, $this->portfolio);
        $this->assertFalse($result, 'User should not have readonly access');

        $result = $this->policy->fullAccess($user, $this->portfolio);
        $this->assertFalse($result, 'User should not have full access');

        $result = $this->policy->owner($user, $this->portfolio);
        $this->assertFalse($result, 'User should not have owner access');
    }

    public function test_read_only_policy()
    {
        $result = $this->policy->readOnly($this->user, $this->portfolio);
        $this->assertTrue($result, 'User should have read-only access');
    }

    public function test_read_only_via_web()
    {
        $result = $this->actingAs($this->user)->get(route('portfolio.show', ['portfolio' => $this->portfolio]));

        $result->assertStatus(200);
    }

    public function test_full_access_policy_with_full_access()
    {
        // Update pivot table to give full access
        $this->portfolio->users()->updateExistingPivot($this->user->id, [
            'full_access' => true,
        ]);

        $result = $this->policy->fullAccess($this->user, $this->portfolio);
        $this->assertTrue($result, 'User should have full access');
    }

    public function test_full_access_policy_without_full_access()
    {
        // Check that the user doesn't have full access
        $result = $this->policy->fullAccess($this->user, $this->portfolio);
        $this->assertFalse($result, 'User should not have full access');
    }

    public function test_owner_policy_when_user_is_owner()
    {
        // Update pivot table to make the user the owner
        $this->portfolio->users()->updateExistingPivot($this->user->id, [
            'owner' => true,
        ]);

        $result = $this->policy->owner($this->user, $this->portfolio);
        $this->assertTrue($result, 'User should be the owner');
    }

    public function test_owner_policy_when_user_is_not_owner()
    {
        // Check that the user is not the owner
        $result = $this->policy->owner($this->user, $this->portfolio);
        $this->assertFalse($result, 'User should not be the owner');
    }
}
