<?php

declare(strict_types=1);

namespace Tests;

use App\Http\Controllers\ConnectedAccountController;
use App\Models\ConnectedAccount;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Notification;
use Laravel\Socialite\Facades\Socialite;

class ConnectedAccountTest extends TestCase
{
    use RefreshDatabase;

    protected $controller;

    protected function setUp(): void
    {
        parent::setUp();
        $this->controller = new ConnectedAccountController;
        Notification::fake();
    }

    public function test_handle_provider_callback_with_already_connected_account()
    {
        $provider = 'github';
        config(['services.enabled_login_providers' => 'github,google']);

        // Create a user and a connected account for the provider
        $user = User::create([
            'name' => 'Alice Smith',
            'email' => 'alice@example.com',
            'email_verified_at' => now(),
        ]);
        $providerUser = (object) [
            'id' => '67890',
            'name' => 'Alice Smith',
            'email' => 'alice@example.com',
            'token' => '15932t8',
            'tokenSecret' => null,
            'refreshToken' => null,
            'expiresIn' => null,
        ];
        ConnectedAccount::forceCreate([
            'provider' => $provider,
            'provider_id' => $providerUser->id,
            'user_id' => $user->id,
            'token' => $providerUser->token,
            'verified_at' => now(),
        ]);

        Socialite::shouldReceive('driver')
            ->with($provider)
            ->andReturnSelf()
            ->shouldReceive('user')
            ->andReturn($providerUser);

        $response = $this->get(route('oauth.callback', ['provider' => $provider]));

        $this->assertTrue(Auth::check());
        $this->assertEquals($user->id, Auth::id());

        $response->assertRedirect(route('dashboard'));
    }

    public function test_handle_provider_callback_with_new_user()
    {
        $provider = 'github';
        config(['services.enabled_login_providers' => 'github,google']);
        $providerUser = (object) [
            'id' => '12345',
            'name' => 'John Doe',
            'email' => 'john@example.com',
            'token' => 'token',
            'tokenSecret' => null,
            'refreshToken' => null,
            'expiresIn' => null,
        ];

        Socialite::shouldReceive('driver')
            ->with($provider)
            ->andReturnSelf()
            ->shouldReceive('user')
            ->andReturn($providerUser);

        $response = $this->get(route('oauth.callback', ['provider' => $provider]));

        $user = User::where('email', 'john@example.com')->first();
        $this->assertNotNull($user);
        $this->assertEquals('John Doe', $user->name);

        $connectedAccount = ConnectedAccount::first();
        $this->assertNotNull($connectedAccount);
        $this->assertEquals('github', $connectedAccount->provider);
        $this->assertEquals('12345', $connectedAccount->provider_id);
        $this->assertNotNull($connectedAccount->verified_at);

        $this->assertTrue(Auth::check());
        $response->assertRedirect(route('dashboard'));
    }

    public function test_handle_provider_callback_with_existing_account()
    {
        $provider = 'github';
        config(['services.enabled_login_providers' => 'github,google']);
        User::create([
            'name' => 'Jane Doe',
            'email' => 'jane@example.com',
            'email_verified_at' => now(),
        ]);
        $providerUser = (object) [
            'id' => '54321',
            'name' => 'Jane Doe',
            'email' => 'jane@example.com',
            'token' => 'token',
            'tokenSecret' => null,
            'refreshToken' => null,
            'expiresIn' => null,
        ];

        Socialite::shouldReceive('driver')
            ->with($provider)
            ->andReturnSelf()
            ->shouldReceive('user')
            ->andReturn($providerUser);

        $response = $this->get(route('oauth.callback', ['provider' => $provider]));

        $connectedAccount = ConnectedAccount::first();
        $this->assertNotNull($connectedAccount);
        $this->assertEquals('github', $connectedAccount->provider);
        $this->assertEquals('54321', $connectedAccount->provider_id);
        $this->assertNull($connectedAccount->verified_at);

        $response->assertRedirect(route('login'));
        $response->assertSessionHas('status', 'Account already exists. Check your email to connect your GitHub account.');
    }

    public function test_verify_connected_account()
    {
        $user = User::create([
            'name' => 'Alice Smith',
            'email' => 'alice@example.com',
            'email_verified_at' => null,
        ]);
        $connectedAccount = ConnectedAccount::forceCreate([
            'provider' => 'github',
            'provider_id' => '12345',
            'token' => '0283523',
            'user_id' => $user->id,
            'verified_at' => null,
        ]);

        $this->assertNull($connectedAccount->verified_at);

        $response = $this->get(route('oauth.verify_connected_account', ['connected_account' => $connectedAccount->id]));

        $connectedAccount->refresh();

        $this->assertNotNull($connectedAccount->verified_at);
        $this->assertNotNull($connectedAccount->user);

        $response->assertRedirect(route('dashboard'));
        $response->assertSessionHas('toast');
    }

    public function test_signed_in_user_can_link_a_provider_account(): void
    {
        config(['services.enabled_login_providers' => 'github,google']);
        $user = User::factory()->create(['email' => 'me@example.com']);
        $this->mockProviderUser('github', $this->providerUser('777', 'someone-else@example.com'));

        $response = $this->actingAs($user)->get(route('oauth.callback', ['provider' => 'github']));

        $response->assertRedirect(route('connections.index'));
        $response->assertSessionHas('toast');

        $connectedAccount = $user->connectedAccounts()->first();
        $this->assertNotNull($connectedAccount);
        $this->assertEquals('777', $connectedAccount->provider_id);
        $this->assertNotNull($connectedAccount->verified_at);

        $this->assertEquals($user->id, Auth::id());
        $this->assertEquals(1, User::count());
        Notification::assertNothingSent();
    }

    public function test_signed_in_user_can_verify_their_own_pending_account(): void
    {
        config(['services.enabled_login_providers' => 'github']);
        $user = User::factory()->create();
        $pending = ConnectedAccount::forceCreate([
            'provider' => 'github',
            'provider_id' => '777',
            'user_id' => $user->id,
            'token' => 'old-token',
            'verified_at' => null,
        ]);
        $this->mockProviderUser('github', $this->providerUser('777', $user->email));

        $this->actingAs($user)->get(route('oauth.callback', ['provider' => 'github']))
            ->assertRedirect(route('connections.index'));

        $this->assertEquals(1, ConnectedAccount::count());
        $this->assertNotNull($pending->fresh()->verified_at);
    }

    public function test_signed_in_user_cannot_link_an_account_owned_by_another_user(): void
    {
        config(['services.enabled_login_providers' => 'github']);
        $owner = User::factory()->create();
        $account = ConnectedAccount::forceCreate([
            'provider' => 'github',
            'provider_id' => '777',
            'user_id' => $owner->id,
            'token' => 'token',
            'verified_at' => now(),
        ]);
        $user = User::factory()->create();
        $this->mockProviderUser('github', $this->providerUser('777', $owner->email));

        $response = $this->actingAs($user)->get(route('oauth.callback', ['provider' => 'github']));

        $response->assertRedirect(route('connections.index'));
        $this->assertStringContainsString('already connected to another user', session('toast'));
        $this->assertEquals($owner->id, $account->fresh()->user_id);
        $this->assertEquals($user->id, Auth::id());
        $this->assertCount(0, $user->connectedAccounts);
    }

    protected function providerUser(string $id, string $email): object
    {
        return (object) [
            'id' => $id,
            'name' => 'Provider User',
            'email' => $email,
            'token' => 'token',
            'tokenSecret' => null,
            'refreshToken' => null,
            'expiresIn' => null,
        ];
    }

    protected function mockProviderUser(string $provider, object $providerUser): void
    {
        Socialite::shouldReceive('driver')
            ->with($provider)
            ->andReturnSelf()
            ->shouldReceive('user')
            ->andReturn($providerUser);
    }
}
