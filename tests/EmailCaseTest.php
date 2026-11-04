<?php

declare(strict_types=1);

namespace Tests;

use App\Models\ConnectedAccount;
use App\Models\Portfolio;
use App\Models\User;
use App\Notifications\VerifyConnectedAccountNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Laravel\Socialite\Facades\Socialite;
use Livewire\Livewire;

class EmailCaseTest extends TestCase
{
    use RefreshDatabase;

    public function test_emails_are_stored_in_lower_case(): void
    {
        $user = User::factory()->create(['email' => ' Mixed.Case@Example.COM ']);

        $this->assertSame('mixed.case@example.com', $user->fresh()->email);
    }

    public function test_sharing_with_a_differently_cased_email_reuses_the_existing_account(): void
    {
        Notification::fake();
        $friend = User::factory()->create(['email' => 'friend@example.net']);
        $this->actingAs(User::factory()->create());
        $portfolio = Portfolio::factory()->create();

        $portfolio->share('Friend@Example.NET');

        $this->assertSame(2, User::count());
        $this->assertTrue($portfolio->users()->whereKey($friend->id)->exists());
    }

    public function test_social_login_matches_an_existing_account_whatever_the_case(): void
    {
        Notification::fake();
        config(['services.enabled_login_providers' => 'github']);
        $user = User::factory()->create(['email' => 'v@example.com']);

        Socialite::shouldReceive('driver')
            ->with('github')
            ->andReturnSelf()
            ->shouldReceive('user')
            ->andReturn((object) [
                'id' => '999',
                'name' => 'Provider User',
                'email' => 'V@Example.com',
                'token' => 'token',
                'tokenSecret' => null,
                'refreshToken' => null,
                'expiresIn' => null,
            ]);

        $this->get(route('oauth.callback', ['provider' => 'github']));

        $this->assertSame(1, User::count());
        $this->assertSame($user->id, ConnectedAccount::sole()->user_id);
        Notification::assertSentTo($user, VerifyConnectedAccountNotification::class);
    }

    public function test_profile_email_is_saved_in_lower_case(): void
    {
        $this->actingAs($user = User::factory()->create());
        session(['auth.password_confirmed_at' => time()]);

        Livewire::test('update-profile-information-form')
            ->set('state.email', 'New.Address@Example.com')
            ->call('updateProfileInformation');

        $this->assertSame('new.address@example.com', $user->fresh()->email);
    }

    public function test_changing_only_the_case_of_the_email_does_not_ask_for_the_password(): void
    {
        $this->actingAs($user = User::factory()->create(['email' => 'same@example.com']));

        Livewire::test('update-profile-information-form')
            ->set('state.email', 'Same@Example.com')
            ->call('updateProfileInformation')
            ->assertSet('confirmingPassword', false);

        $this->assertSame('same@example.com', $user->fresh()->email);
    }
}
