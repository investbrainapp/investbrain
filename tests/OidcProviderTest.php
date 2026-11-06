<?php

declare(strict_types=1);

namespace Tests;

use App\Models\ConnectedAccount;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Exceptions;
use Illuminate\Support\Facades\Http;
use Illuminate\Testing\TestResponse;
use RuntimeException;

class OidcProviderTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'services.enabled_login_providers' => 'oidc',
            'services.oidc.issuer' => 'https://idp.example.com/realms/home/',
            'services.oidc.client_id' => 'investbrain',
            'services.oidc.client_secret' => 'se:cret',
        ]);
    }

    public function test_the_login_page_shows_a_button_named_after_the_identity_provider(): void
    {
        config(['services.oidc.name' => 'Authelia']);

        $response = $this->withoutVite()->get(route('login'));

        $response->assertOk();
        $response->assertSee('Login with Authelia');
        $response->assertSee(route('oauth.redirect', ['provider' => 'oidc']));
    }

    public function test_login_redirects_to_the_issuers_authorization_endpoint_with_pkce(): void
    {
        $this->fakeIssuer();

        $response = $this->get(route('oauth.redirect', ['provider' => 'oidc']));

        $location = (string) $response->headers->get('Location');
        parse_str((string) parse_url($location, PHP_URL_QUERY), $query);
        $this->assertStringStartsWith('https://idp.example.com/realms/home/protocol/openid-connect/auth?', $location);
        $this->assertSame('investbrain', $query['client_id']);
        $this->assertSame(route('oauth.callback', ['provider' => 'oidc']), $query['redirect_uri']);
        $this->assertSame('code', $query['response_type']);
        $this->assertSame('openid profile email', $query['scope']);
        $this->assertSame('S256', $query['code_challenge_method']);
        $this->assertNotEmpty($query['code_challenge']);
        $response->assertSessionHas('state', $query['state']);
    }

    public function test_signing_in_creates_an_account_from_the_userinfo_claims(): void
    {
        $this->fakeIssuer();

        $response = $this->returnFromIssuer();

        $response->assertRedirect(route('dashboard'));
        $user = User::sole();
        $this->assertSame('ada@example.com', $user->email);
        $this->assertSame('Ada Lovelace', $user->name);
        $this->assertSame($user->id, Auth::id());
        $account = ConnectedAccount::sole();
        $this->assertSame('oidc', $account->provider);
        $this->assertSame('idp-user-1', $account->provider_id);
        $this->assertSame($user->id, $account->user_id);
        $this->assertNotNull($account->verified_at);
        Http::assertSent(fn (Request $request) => $request->url() === 'https://idp.example.com/realms/home/protocol/openid-connect/token'
            && $request->hasHeader('Authorization', 'Basic '.base64_encode('investbrain:se%3Acret'))
            && $request['code'] === 'code-123'
            && $request['code_verifier'] === 'verifier-123'
            && ! isset($request['client_secret']));
    }

    public function test_the_client_secret_is_sent_in_the_body_when_the_issuer_does_not_accept_basic_auth(): void
    {
        $this->fakeIssuer(discovery: ['token_endpoint_auth_methods_supported' => ['client_secret_post']]);

        $this->returnFromIssuer()->assertRedirect(route('dashboard'));

        Http::assertSent(fn (Request $request) => $request->url() === 'https://idp.example.com/realms/home/protocol/openid-connect/token'
            && ! $request->hasHeader('Authorization')
            && $request['client_id'] === 'investbrain'
            && $request['client_secret'] === 'se:cret');
    }

    public function test_an_issuer_that_does_not_match_its_discovery_document_sends_the_user_back_to_login(): void
    {
        Exceptions::fake();
        $this->fakeIssuer(discovery: ['issuer' => 'https://evil.example.com']);

        $response = $this->get(route('oauth.redirect', ['provider' => 'oidc']));

        $response->assertRedirect(route('login'));
        $response->assertSessionHas('errors');
        Exceptions::assertReported(RuntimeException::class);
    }

    /**
     * Return from the issuer with an authorization code, as the browser would after signing in.
     */
    protected function returnFromIssuer(): TestResponse
    {
        return $this->withSession(['state' => 'state-123', 'code_verifier' => 'verifier-123'])
            ->get(route('oauth.callback', ['provider' => 'oidc', 'code' => 'code-123', 'state' => 'state-123']));
    }

    /**
     * Fake the issuer's discovery, token, and userinfo endpoints.
     *
     * @param  array<string, mixed>  $discovery
     */
    protected function fakeIssuer(array $discovery = []): void
    {
        $base = 'https://idp.example.com/realms/home';

        Http::preventStrayRequests();
        Http::fake([
            "$base/.well-known/openid-configuration" => Http::response([
                'issuer' => $base,
                'authorization_endpoint' => "$base/protocol/openid-connect/auth",
                'token_endpoint' => "$base/protocol/openid-connect/token",
                'userinfo_endpoint' => "$base/protocol/openid-connect/userinfo",
                'token_endpoint_auth_methods_supported' => ['client_secret_basic', 'client_secret_post'],
                ...$discovery,
            ]),
            "$base/protocol/openid-connect/token" => Http::response([
                'access_token' => 'access-123',
                'token_type' => 'Bearer',
                'refresh_token' => 'refresh-123',
            ]),
            "$base/protocol/openid-connect/userinfo" => Http::response([
                'sub' => 'idp-user-1',
                'name' => 'Ada Lovelace',
                'email' => 'ada@example.com',
            ]),
        ]);
    }
}
