<?php

declare(strict_types=1);

namespace Tests;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Livewire\Livewire;

class ProfileInformationTest extends TestCase
{
    use RefreshDatabase;

    public function test_current_profile_information_is_available(): void
    {
        $this->actingAs($user = User::factory()->create());

        $component = Livewire::test('update-profile-information-form');

        $this->assertEquals($user->name, $component->state['name']);
        $this->assertEquals($user->email, $component->state['email']);
    }

    public function test_profile_information_can_be_updated(): void
    {
        $this->actingAs($user = User::factory()->create());
        session(['auth.password_confirmed_at' => time()]);

        Livewire::test('update-profile-information-form')
            ->set('state', ['name' => 'Test Name', 'email' => 'test@example.com'])
            ->call('updateProfileInformation');

        $this->assertEquals('Test Name', $user->fresh()->name);
        $this->assertEquals('test@example.com', $user->fresh()->email);
    }

    public function test_name_can_be_changed_without_confirming_the_password(): void
    {
        $this->actingAs($user = User::factory()->create());

        Livewire::test('update-profile-information-form')
            ->set('state.name', 'Test Name')
            ->call('updateProfileInformation')
            ->assertSet('confirmingPassword', false);

        $this->assertEquals('Test Name', $user->fresh()->name);
    }

    public function test_changing_the_email_asks_for_the_password_first(): void
    {
        Notification::fake();
        $this->actingAs($user = User::factory()->create());

        Livewire::test('update-profile-information-form')
            ->set('state.email', 'new@example.com')
            ->call('updateProfileInformation')
            ->assertSet('confirmingPassword', true);

        $this->assertEquals($user->email, $user->fresh()->email);
        Notification::assertNothingSent();
    }

    public function test_email_is_changed_once_the_password_is_confirmed(): void
    {
        $this->actingAs($user = User::factory()->create());

        Livewire::test('update-profile-information-form')
            ->set('state.email', 'new@example.com')
            ->call('updateProfileInformation')
            ->set('confirmablePassword', 'password')
            ->call('confirmPassword')
            ->assertDispatched('password-confirmed', id: md5('updateProfileInformation'))
            ->call('updateProfileInformation');

        $this->assertEquals('new@example.com', $user->fresh()->email);
    }

    public function test_fortify_profile_route_cannot_change_the_email_without_a_password_confirmation(): void
    {
        $this->actingAs($user = User::factory()->create());

        $this->put(route('user-profile-information.update'), ['name' => $user->name, 'email' => 'new@example.com'])
            ->assertForbidden();

        $this->assertEquals($user->email, $user->fresh()->email);
    }

    public function test_fortify_profile_route_changes_the_email_after_a_password_confirmation(): void
    {
        $this->actingAs($user = User::factory()->create());

        $this->withSession(['auth.password_confirmed_at' => time()])
            ->put(route('user-profile-information.update'), ['name' => $user->name, 'email' => 'new@example.com'])
            ->assertSessionHasNoErrors();

        $this->assertEquals('new@example.com', $user->fresh()->email);
    }

    public function test_email_is_not_changed_with_the_wrong_password(): void
    {
        $this->actingAs($user = User::factory()->create());

        Livewire::test('update-profile-information-form')
            ->set('state.email', 'new@example.com')
            ->call('updateProfileInformation')
            ->set('confirmablePassword', 'wrong-password')
            ->call('confirmPassword')
            ->assertHasErrors('confirmable_password')
            ->call('updateProfileInformation')
            ->assertSet('confirmingPassword', true);

        $this->assertEquals($user->email, $user->fresh()->email);
    }
}
