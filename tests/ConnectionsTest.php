<?php

declare(strict_types=1);

namespace Tests;

use App\Models\ConnectedAccount;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Laravel\Passport\Client;
use Laravel\Passport\Passport;
use Laravel\Passport\Token;
use Livewire\Livewire;

class ConnectionsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->usePassportKeys();

        config(['services.enabled_login_providers' => 'github,google']);
    }

    public function test_connections_page_requires_authentication(): void
    {
        $this->get(route('connections.index'))->assertRedirect(route('login'));
    }

    public function test_connections_page_renders_both_sections(): void
    {
        $this->actingAs(User::factory()->create());

        $this->get(route('connections.index'))
            ->assertOk()
            ->assertSeeLivewire('connected-accounts-manager')
            ->assertSeeLivewire('authorized-apps-manager');
    }

    public function test_enabled_providers_without_an_account_can_be_connected(): void
    {
        $this->actingAs(User::factory()->create());

        Livewire::test('connected-accounts-manager')
            ->assertSee('GitHub')
            ->assertSee('Google')
            ->assertSee(route('oauth.redirect', ['provider' => 'github']))
            ->assertDontSee('confirmDisconnect');
    }

    public function test_connected_and_pending_accounts_are_shown_with_their_state(): void
    {
        $this->actingAs($user = User::factory()->create());

        $this->createConnectedAccount($user, 'github', verified: true);
        $this->createConnectedAccount($user, 'google', verified: false);

        Livewire::test('connected-accounts-manager')
            ->assertSee('Connected')
            ->assertSee('Pending email verification')
            ->assertSee('confirmDisconnect')
            ->assertDontSee(route('oauth.redirect', ['provider' => 'github']))
            ->assertDontSee(route('oauth.redirect', ['provider' => 'google']));
    }

    public function test_no_section_is_shown_when_no_providers_are_enabled_or_connected(): void
    {
        config(['services.enabled_login_providers' => '']);

        $this->actingAs(User::factory()->create());

        Livewire::test('connected-accounts-manager')
            ->assertDontSee('Social Logins')
            ->assertDontSeeHtml('<hr');
    }

    public function test_account_of_a_provider_that_is_no_longer_enabled_is_still_listed(): void
    {
        config(['services.enabled_login_providers' => 'google']);

        $this->actingAs($user = User::factory()->create());

        $this->createConnectedAccount($user, 'github', verified: true);

        Livewire::test('connected-accounts-manager')
            ->assertSee('GitHub')
            ->assertSee('confirmDisconnect');
    }

    public function test_connected_account_can_be_disconnected(): void
    {
        $this->actingAs($user = User::factory()->create());

        $account = $this->createConnectedAccount($user, 'github', verified: true);

        Livewire::test('connected-accounts-manager')
            ->call('confirmDisconnect', $account->id)
            ->assertSet('confirmingDisconnect', true)
            ->call('disconnect')
            ->assertSet('confirmingDisconnect', false)
            ->assertSet('accountIdBeingDisconnected', null);

        $this->assertModelMissing($account);
    }

    public function test_cannot_disconnect_another_users_account(): void
    {
        $account = $this->createConnectedAccount(User::factory()->create(), 'github', verified: true);

        $this->actingAs(User::factory()->create());

        Livewire::test('connected-accounts-manager')
            ->set('accountIdBeingDisconnected', $account->id)
            ->call('disconnect')
            ->assertStatus(404);

        $this->assertModelExists($account);
    }

    public function test_oauth_client_tokens_are_grouped_by_client(): void
    {
        $this->actingAs($user = User::factory()->create());

        $client = $this->createClient('Claude Code');
        $this->createToken($user, $client);
        $this->createToken($user, $client);

        Livewire::test('authorized-apps-manager')
            ->assertSee('Claude Code')
            ->assertSee('View and manage your portfolios, transactions, and holdings')
            ->assertSee('confirmRevoke');

        $apps = Livewire::test('authorized-apps-manager')->instance()->authorizedApps();

        $this->assertCount(1, $apps);
        $this->assertSame($client->id, $apps->first()['client']->id);
    }

    public function test_personal_access_tokens_revoked_and_expired_tokens_are_not_listed(): void
    {
        $this->actingAs($user = User::factory()->create());

        $user->createToken('My Personal Token');

        $revoked = $this->createClient('Revoked App');
        $this->createToken($user, $revoked, ['revoked' => true]);

        $expired = $this->createClient('Expired App');
        $this->createToken($user, $expired, ['expires_at' => now()->subDay()], ['expires_at' => now()->subDay()]);

        Livewire::test('authorized-apps-manager')
            ->assertSee('No apps have access to your account.')
            ->assertDontSee('My Personal Token')
            ->assertDontSee('Revoked App')
            ->assertDontSee('Expired App');
    }

    public function test_app_with_expired_access_token_is_listed_while_it_can_still_refresh(): void
    {
        $this->actingAs($user = User::factory()->create());

        $this->createToken($user, $this->createClient('Refreshable App'), ['expires_at' => now()->subDay()]);

        $revokedRefresh = $this->createClient('Revoked Refresh App');
        $this->createToken($user, $revokedRefresh, ['expires_at' => now()->subDay()], ['revoked' => true]);

        Livewire::test('authorized-apps-manager')
            ->assertSee('Refreshable App')
            ->assertDontSee('Revoked Refresh App')
            ->assertDontSee('Access expires');
    }

    public function test_other_users_apps_are_not_listed(): void
    {
        $this->createToken(User::factory()->create(), $this->createClient('Someone Elses App'));

        $this->actingAs(User::factory()->create());

        Livewire::test('authorized-apps-manager')->assertDontSee('Someone Elses App');
    }

    public function test_revoking_an_app_revokes_all_its_tokens_and_refresh_tokens(): void
    {
        $this->actingAs($user = User::factory()->create());

        $client = $this->createClient('Claude Code');
        $first = $this->createToken($user, $client);
        $second = $this->createToken($user, $client);

        $otherClient = $this->createClient('Other App');
        $untouched = $this->createToken($user, $otherClient);

        Livewire::test('authorized-apps-manager')
            ->call('confirmRevoke', $client->id)
            ->assertSet('confirmingRevoke', true)
            ->call('revoke')
            ->assertSet('confirmingRevoke', false)
            ->assertSet('clientIdBeingRevoked', null)
            ->assertDontSee('Claude Code')
            ->assertSee('Other App');

        $this->assertTrue($first->fresh()->revoked);
        $this->assertTrue($second->fresh()->revoked);
        $this->assertTrue($first->refreshToken()->first()->revoked);
        $this->assertTrue($second->refreshToken()->first()->revoked);
        $this->assertFalse($untouched->fresh()->revoked);
        $this->assertFalse($untouched->refreshToken()->first()->revoked);
    }

    public function test_cannot_revoke_another_users_app(): void
    {
        $otherUser = User::factory()->create();
        $client = $this->createClient('Someone Elses App');
        $token = $this->createToken($otherUser, $client);

        $this->actingAs(User::factory()->create());

        Livewire::test('authorized-apps-manager')
            ->set('clientIdBeingRevoked', $client->id)
            ->call('revoke')
            ->assertStatus(404);

        $this->assertFalse($token->fresh()->revoked);
    }

    protected function createConnectedAccount(User $user, string $provider, bool $verified): ConnectedAccount
    {
        $account = new ConnectedAccount([
            'provider' => $provider,
            'provider_id' => Str::random(10),
            'token' => Str::random(20),
        ]);

        $account->user_id = $user->id;
        $account->verified_at = $verified ? now() : null;
        $account->save();

        return $account;
    }

    protected function createClient(string $name): Client
    {
        return Passport::client()->factory()->create(['name' => $name]);
    }

    /**
     * @param  array<string, mixed>  $attributes
     * @param  array<string, mixed>  $refreshAttributes
     */
    protected function createToken(User $user, Client $client, array $attributes = [], array $refreshAttributes = []): Token
    {
        $token = Passport::token()->forceCreate([
            'id' => Str::random(80),
            'user_id' => $user->id,
            'client_id' => $client->id,
            'name' => null,
            'scopes' => ['mcp:use'],
            'revoked' => false,
            'expires_at' => now()->addDay(),
            ...$attributes,
        ]);

        Passport::refreshToken()->forceCreate([
            'id' => Str::random(80),
            'access_token_id' => $token->id,
            'revoked' => false,
            'expires_at' => now()->addWeek(),
            ...$refreshAttributes,
        ]);

        return $token;
    }
}
