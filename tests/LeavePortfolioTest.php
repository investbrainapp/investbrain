<?php

declare(strict_types=1);

namespace Tests;

use App\Models\Portfolio;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Livewire\Livewire;

class LeavePortfolioTest extends TestCase
{
    use RefreshDatabase;

    protected User $owner;

    protected User $user;

    protected Portfolio $portfolio;

    protected function setUp(): void
    {
        parent::setUp();

        $this->owner = User::factory()->create();
        Auth::login($this->owner);
        $this->portfolio = Portfolio::factory()->create();

        $this->user = User::factory()->create();
        $this->portfolio->users()->syncWithoutDetaching([
            $this->user->id => ['full_access' => false],
        ]);
    }

    public function test_shared_user_can_leave_portfolio(): void
    {
        $this->actingAs($this->user);

        Livewire::test('leave-portfolio', ['portfolio' => $this->portfolio])
            ->call('leave')
            ->assertRedirect(route('dashboard'));

        $this->assertNull($this->portfolio->users()->find($this->user->id));
        $this->assertNotNull($this->portfolio->users()->find($this->owner->id));
    }

    public function test_owner_cannot_leave_portfolio(): void
    {
        $this->actingAs($this->owner);

        Livewire::test('leave-portfolio', ['portfolio' => $this->portfolio])
            ->call('leave')
            ->assertForbidden();

        $this->assertNotNull($this->portfolio->users()->find($this->owner->id));
    }

    public function test_user_without_access_cannot_leave_portfolio(): void
    {
        $this->actingAs(User::factory()->create());

        Livewire::test('leave-portfolio', ['portfolio' => $this->portfolio])
            ->call('leave')
            ->assertForbidden();
    }

    public function test_shared_user_sees_leave_option_on_portfolio_page(): void
    {
        $this->actingAs($this->user);

        $this->get(route('portfolio.show', $this->portfolio))
            ->assertOk()
            ->assertSee(__('Portfolio shared with you'))
            ->assertSee(__('Shared by :name', ['name' => $this->owner->name]))
            ->assertSee(__('Access').': '.__('Read only'))
            ->assertSee(__('Leave Portfolio'));
    }

    public function test_full_access_user_sees_full_access_in_dialog(): void
    {
        $this->portfolio->users()->updateExistingPivot($this->user->id, ['full_access' => true]);

        $this->actingAs($this->user);

        Livewire::test('leave-portfolio', ['portfolio' => $this->portfolio])
            ->assertSee(__('Access').': '.__('Full access'));
    }

    public function test_owner_does_not_see_leave_option_on_portfolio_page(): void
    {
        $this->actingAs($this->owner);

        $this->get(route('portfolio.show', $this->portfolio))
            ->assertOk()
            ->assertDontSee(__('Leave Portfolio'));
    }
}
