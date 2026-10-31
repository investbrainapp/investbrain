<?php

declare(strict_types=1);

namespace Tests;

use App\Models\Portfolio;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Livewire\Livewire;

class SharePortfolioFormTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Notification::fake();
    }

    public function test_sharing_with_full_access_updates_component_permissions(): void
    {
        $this->actingAs(User::factory()->create());

        $portfolio = Portfolio::factory()->create();

        $component = Livewire::test('share-portfolio-form', ['portfolio' => $portfolio])
            ->set('emailAddress', 'friend@example.net')
            ->set('fullAccess', true)
            ->call('addUser')
            ->assertHasNoErrors()
            ->assertDispatched('toggle-add-user-modal')
            ->assertSet('emailAddress', '')
            ->assertSet('fullAccess', false);

        $friend = User::where('email', 'friend@example.net')->firstOrFail();

        $this->assertTrue((bool) $component->get("permissions.{$friend->id}.full_access"));
        $this->assertTrue((bool) $portfolio->users()->find($friend->id)->pivot->full_access);
    }

    public function test_sharing_without_full_access_grants_read_only(): void
    {
        $this->actingAs(User::factory()->create());

        $portfolio = Portfolio::factory()->create();

        $component = Livewire::test('share-portfolio-form', ['portfolio' => $portfolio])
            ->set('emailAddress', 'friend@example.net')
            ->call('addUser')
            ->assertHasNoErrors();

        $friend = User::where('email', 'friend@example.net')->firstOrFail();

        $this->assertFalse((bool) $component->get("permissions.{$friend->id}.full_access"));
        $this->assertFalse((bool) $portfolio->users()->find($friend->id)->pivot->full_access);
    }

    public function test_changing_another_users_permission_keeps_newly_shared_user(): void
    {
        $this->actingAs(User::factory()->create());

        $portfolio = Portfolio::factory()->create();
        $existing = User::factory()->create();
        $portfolio->share($existing->email);

        Livewire::test('share-portfolio-form', ['portfolio' => $portfolio])
            ->set('emailAddress', 'friend@example.net')
            ->set('fullAccess', true)
            ->call('addUser')
            ->set("permissions.{$existing->id}.full_access", 1);

        $friend = User::where('email', 'friend@example.net')->firstOrFail();

        $this->assertTrue((bool) $portfolio->users()->find($friend->id)?->pivot->full_access);
        $this->assertTrue((bool) $portfolio->users()->find($existing->id)->pivot->full_access);
    }

    public function test_re_adding_a_removed_user_restores_their_permissions(): void
    {
        $this->actingAs(User::factory()->create());

        $portfolio = Portfolio::factory()->create();
        $friend = User::factory()->create();
        $portfolio->share($friend->email, true);

        $component = Livewire::test('share-portfolio-form', ['portfolio' => $portfolio])
            ->call('deleteUser', $friend->id, true)
            ->set('emailAddress', $friend->email)
            ->set('fullAccess', true)
            ->call('addUser');

        $this->assertTrue((bool) $component->get("permissions.{$friend->id}.full_access"));
    }

    public function test_read_only_user_cannot_share_portfolio(): void
    {
        $this->actingAs($owner = User::factory()->create());

        $portfolio = Portfolio::factory()->create();
        $readOnly = User::factory()->create();
        $portfolio->share($readOnly->email);

        $this->actingAs($readOnly);

        Livewire::test('share-portfolio-form', ['portfolio' => $portfolio])
            ->set('emailAddress', 'friend@example.net')
            ->call('addUser')
            ->assertForbidden();

        $this->assertNull(User::where('email', 'friend@example.net')->first());
    }

    public function test_sharing_requires_a_valid_email(): void
    {
        $this->actingAs(User::factory()->create());

        $portfolio = Portfolio::factory()->create();

        Livewire::test('share-portfolio-form', ['portfolio' => $portfolio])
            ->set('emailAddress', 'not-an-email')
            ->call('addUser')
            ->assertHasErrors(['emailAddress'])
            ->assertNotDispatched('toggle-add-user-modal');
    }
}
