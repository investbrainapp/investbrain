<?php

declare(strict_types=1);

namespace Tests;

use App\Http\Controllers\ConnectedAccountController;
use App\Models\ConnectedAccount;
use App\Models\User;
use App\Notifications\VerifyConnectedAccountNotification;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Notification;
use Illuminate\View\ViewException;
use Laravel\Socialite\Facades\Socialite;
use PHPUnit\Framework\Attributes\TestWith;

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
        // the provider has verified the address, so the new account starts verified
        $this->assertNotNull($user->email_verified_at);

        $connectedAccount = ConnectedAccount::first();
        $this->assertNotNull($connectedAccount);
        $this->assertEquals('github', $connectedAccount->provider);
        $this->assertEquals('12345', $connectedAccount->provider_id);
        $this->assertNotNull($connectedAccount->verified_at);

        $this->assertTrue(Auth::check());
        $response->assertRedirect(route('dashboard'));
    }

    public function test_new_users_are_not_provisioned_from_a_provider_login_when_auto_provisioning_is_off(): void
    {
        config(['services.enabled_login_providers' => 'github', 'services.sso_auto_provision' => false]);
        $this->mockProviderUser('github', $this->providerUser('12345', 'john@example.com'));

        $response = $this->get(route('oauth.callback', ['provider' => 'github']));

        $response->assertRedirect(route('login'));
        $response->assertSessionHas('errors');
        $this->assertFalse(Auth::check());
        $this->assertSame(0, User::count());
        $this->assertSame(0, ConnectedAccount::count());
    }

    public function test_a_provider_login_without_an_email_does_not_create_an_account(): void
    {
        config(['services.enabled_login_providers' => 'github']);
        $this->mockProviderUser('github', (object) [...(array) $this->providerUser('12345', ''), 'email' => null]);

        $response = $this->get(route('oauth.callback', ['provider' => 'github']));

        $response->assertRedirect(route('login'));
        $response->assertSessionHas('errors');
        $this->assertFalse(Auth::check());
        $this->assertSame(0, User::count());
        $this->assertSame(0, ConnectedAccount::count());
    }

    public function test_existing_users_can_still_connect_a_provider_when_auto_provisioning_is_off(): void
    {
        config(['services.enabled_login_providers' => 'github', 'services.sso_auto_provision' => false]);
        $user = User::factory()->create(['email' => 'jane@example.com']);
        $this->mockProviderUser('github', $this->providerUser('54321', 'jane@example.com'));

        $this->get(route('oauth.callback', ['provider' => 'github']))
            ->assertRedirect(route('login'));

        $this->assertSame(1, User::count());
        $this->assertSame($user->id, ConnectedAccount::sole()->user_id);
        Notification::assertSentTo($user, VerifyConnectedAccountNotification::class);
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

    public function test_an_account_waiting_for_its_email_link_stays_unverified_even_if_verified_at_were_fillable(): void
    {
        config(['services.enabled_login_providers' => 'github']);
        User::factory()->create(['email' => 'jane@example.com']);

        Socialite::shouldReceive('driver')
            ->with('github')
            ->andReturnSelf()
            ->shouldReceive('user')
            ->andReturn((object) [
                'id' => '54321',
                'name' => 'Jane Doe',
                'email' => 'jane@example.com',
                'token' => 'token',
                'tokenSecret' => null,
                'refreshToken' => null,
                'expiresIn' => null,
            ]);

        // the defaults given to firstOrNew mustn't mark the account verified once mass assignment allows it
        Model::unguarded(function (): void {
            $this->get(route('oauth.callback', ['provider' => 'github']))->assertRedirect(route('login'));
            $this->get(route('oauth.callback', ['provider' => 'github']))->assertRedirect(route('login'));
        });

        $this->assertNull(ConnectedAccount::sole()->verified_at);
        $this->assertGuest();
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

        $verificationUrl = (new VerifyConnectedAccountNotification($connectedAccount->id))->toMail($user)->actionUrl;

        $response = $this->get($verificationUrl);

        $connectedAccount->refresh();

        $this->assertNotNull($connectedAccount->verified_at);
        $this->assertNotNull($connectedAccount->user);

        $response->assertRedirect(route('dashboard'));
        $response->assertSessionHas('toast');
    }

    public function test_verify_link_cannot_log_in_as_a_user_the_account_was_repointed_to()
    {
        config(['services.enabled_login_providers' => 'github']);
        $attacker = User::factory()->create(['email' => 'x@example.com']);
        $victim = User::factory()->create(['email' => 'v@example.com']);
        $providerUser = fn (string $email) => (object) [
            'id' => '999',
            'name' => 'Provider User',
            'email' => $email,
            'token' => 'token',
            'tokenSecret' => null,
            'refreshToken' => null,
            'expiresIn' => null,
        ];

        Socialite::shouldReceive('driver')
            ->with('github')
            ->andReturnSelf()
            ->shouldReceive('user')
            ->andReturn($providerUser('x@example.com'), $providerUser('v@example.com'));

        // the provider reports the attacker's email, so the link is mailed to the attacker
        $this->get(route('oauth.callback', ['provider' => 'github']));
        $connectedAccount = ConnectedAccount::firstOrFail();
        $attackersLink = (new VerifyConnectedAccountNotification($connectedAccount->id))->toMail($attacker)->actionUrl;

        // then it reports the victim's email, which points the same pending account at the victim
        $this->get(route('oauth.callback', ['provider' => 'github']));
        $this->assertSame($victim->id, $connectedAccount->fresh()->user_id);

        $this->get($attackersLink)->assertForbidden();

        $this->assertGuest();
        $this->assertNull($connectedAccount->fresh()->verified_at);
    }

    public function test_verify_connected_account_requires_a_signed_link()
    {
        $user = User::factory()->create();
        $connectedAccount = ConnectedAccount::forceCreate([
            'provider' => 'github',
            'provider_id' => '12345',
            'token' => '0283523',
            'user_id' => $user->id,
            'verified_at' => null,
        ]);

        $this->get(route('oauth.verify_connected_account', ['connected_account' => $connectedAccount->id]))
            ->assertForbidden();

        $this->assertNull($connectedAccount->fresh()->verified_at);
        $this->assertGuest();
    }

    public function test_verify_connected_account_link_expires_after_seven_days()
    {
        $user = User::factory()->create();
        $connectedAccount = ConnectedAccount::forceCreate([
            'provider' => 'github',
            'provider_id' => '12345',
            'token' => '0283523',
            'user_id' => $user->id,
            'verified_at' => null,
        ]);

        $verificationUrl = (new VerifyConnectedAccountNotification($connectedAccount->id))->toMail($user)->actionUrl;

        $this->travel(8)->days();

        $this->get($verificationUrl)->assertForbidden();

        $this->assertNull($connectedAccount->fresh()->verified_at);
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
        $this->mockProviderUser('github', $this->providerUser('777', $owner->email, ['token' => 'new-token']));

        $response = $this->actingAs($user)->get(route('oauth.callback', ['provider' => 'github']));

        $response->assertRedirect(route('connections.index'));
        $this->assertStringContainsString('already connected to another user', session('toast'));
        $this->assertEquals($owner->id, $account->fresh()->user_id);
        $this->assertSame('token', $account->fresh()->token);
        $this->assertEquals($user->id, Auth::id());
        $this->assertCount(0, $user->connectedAccounts);
    }

    public function test_new_account_stores_token_expiry_as_a_time_from_now(): void
    {
        config(['services.enabled_login_providers' => 'github']);
        $this->travelTo('2026-01-01 00:00:00');
        $this->mockProviderUser('github', $this->providerUser('777', 'new@example.com', ['expiresIn' => 3600]));

        $this->get(route('oauth.callback', ['provider' => 'github']))->assertRedirect(route('dashboard'));

        $this->assertSame('2026-01-01 01:00:00', ConnectedAccount::sole()->expires_at->toDateTimeString());
    }

    public function test_account_without_token_lifetime_stores_no_expiry(): void
    {
        config(['services.enabled_login_providers' => 'github']);
        $this->mockProviderUser('github', $this->providerUser('777', 'new@example.com'));

        $this->get(route('oauth.callback', ['provider' => 'github']))->assertRedirect(route('dashboard'));

        $this->assertNull(ConnectedAccount::sole()->expires_at);
    }

    public function test_signed_in_user_linking_an_account_stores_token_expiry_as_a_time_from_now(): void
    {
        config(['services.enabled_login_providers' => 'github']);
        $this->travelTo('2026-01-01 00:00:00');
        $user = User::factory()->create();
        $this->mockProviderUser('github', $this->providerUser('777', $user->email, ['expiresIn' => 7200]));

        $this->actingAs($user)->get(route('oauth.callback', ['provider' => 'github']))
            ->assertRedirect(route('connections.index'));

        $this->assertSame('2026-01-01 02:00:00', $user->connectedAccounts()->sole()->expires_at->toDateTimeString());
    }

    public function test_returning_sign_in_replaces_stored_tokens_and_keeps_refresh_token_the_provider_omits(): void
    {
        config(['services.enabled_login_providers' => 'github']);
        $this->travelTo('2026-01-01 00:00:00');
        $user = User::factory()->create();
        $account = ConnectedAccount::forceCreate([
            'provider' => 'github',
            'provider_id' => '777',
            'user_id' => $user->id,
            'token' => 'old-token',
            'refresh_token' => 'old-refresh-token',
            'expires_at' => '2025-12-01 00:00:00',
            'verified_at' => now(),
        ]);
        $this->mockProviderUser('github', $this->providerUser('777', $user->email, [
            'token' => 'new-token',
            'expiresIn' => 3600,
        ]));

        $this->get(route('oauth.callback', ['provider' => 'github']))->assertRedirect(route('dashboard'));

        $account->refresh();
        $this->assertSame('new-token', $account->token);
        $this->assertSame('old-refresh-token', $account->refresh_token);
        $this->assertSame('2026-01-01 01:00:00', $account->expires_at->toDateTimeString());
        $this->assertSame($user->id, Auth::id());
    }

    /**
     * @param  array{token?: string, tokenSecret?: ?string, refreshToken?: ?string, expiresIn?: ?int}  $tokens
     */
    protected function providerUser(string $id, string $email, array $tokens = []): object
    {
        return (object) [
            'id' => $id,
            'name' => 'Provider User',
            'email' => $email,
            'token' => 'token',
            'tokenSecret' => null,
            'refreshToken' => null,
            'expiresIn' => null,
            ...$tokens,
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

    #[TestWith(['o-check-circle', true])]
    #[TestWith(["o-check-circle' data-injected='yes", false])]
    public function test_redirect_toast_treats_the_icon_as_a_name_not_markup(string $icon, bool $rendersIcon): void
    {
        $controller = new class extends ConnectedAccountController
        {
            public function toastRedirect(string $icon): RedirectResponse
            {
                return $this->redirectWithToast('/dashboard', 'Connected', icon: $icon);
            }
        };

        try {
            $html = json_decode($controller->toastRedirect($icon)->getSession()->get('toast'), true)['toast']['icon'];
        } catch (ViewException $e) {
            // an unknown icon name is an error, not extra markup
            $this->assertStringContainsString('not found', $e->getMessage());
            $html = '';
        }

        $this->assertSame($rendersIcon, str_contains($html, '<svg'));
        $this->assertStringNotContainsString('data-injected', $html);
    }
}
