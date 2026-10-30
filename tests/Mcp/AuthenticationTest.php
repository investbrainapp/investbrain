<?php

declare(strict_types=1);

namespace Tests\Mcp;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class AuthenticationTest extends TestCase
{
    use RefreshDatabase;

    protected const MCP_PATH = '/mcp/investbrain';

    protected const REDIRECT_URI = 'http://localhost:3000/callback';

    protected User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->usePassportKeys();

        $this->user = User::factory()->create();
    }

    /**
     * @return array<string, mixed>
     */
    protected function initializePayload(): array
    {
        return [
            'jsonrpc' => '2.0',
            'id' => 1,
            'method' => 'initialize',
            'params' => [
                'protocolVersion' => '2025-03-26',
                'capabilities' => new \stdClass,
                'clientInfo' => ['name' => 'test-client', 'version' => '1.0.0'],
            ],
        ];
    }

    /**
     * @return array<string, string>
     */
    protected function mcpHeaders(): array
    {
        return ['Accept' => 'application/json, text/event-stream'];
    }

    public function test_cannot_reach_mcp_server_without_a_token(): void
    {
        $response = $this->postJson(self::MCP_PATH, $this->initializePayload(), $this->mcpHeaders())
            ->assertUnauthorized();

        $this->assertStringContainsString('resource_metadata=', $response->headers->get('WWW-Authenticate'));
    }

    public function test_cannot_reach_mcp_server_with_a_malformed_token(): void
    {
        $this->withToken('not-a-real-token')
            ->postJson(self::MCP_PATH, $this->initializePayload(), $this->mcpHeaders())
            ->assertUnauthorized();
    }

    public function test_cannot_reach_mcp_server_with_browser_session_only(): void
    {
        $this->actingAs($this->user)
            ->postJson(self::MCP_PATH, $this->initializePayload(), $this->mcpHeaders())
            ->assertUnauthorized();
    }

    public function test_can_reach_mcp_server_with_personal_access_token(): void
    {
        $token = $this->user->createToken('MCP Token')->accessToken;

        $this->withToken($token)
            ->postJson(self::MCP_PATH, $this->initializePayload(), $this->mcpHeaders())
            ->assertOk()
            ->assertJsonPath('result.serverInfo.name', 'Investbrain Server');
    }

    public function test_cannot_reach_mcp_server_with_revoked_token(): void
    {
        $token = $this->user->createToken('MCP Token')->accessToken;

        $this->user->tokens()->firstOrFail()->revoke();

        $this->withToken($token)
            ->postJson(self::MCP_PATH, $this->initializePayload(), $this->mcpHeaders())
            ->assertUnauthorized();
    }

    public function test_publishes_oauth_discovery_metadata(): void
    {
        $this->getJson('/.well-known/oauth-protected-resource'.self::MCP_PATH)
            ->assertOk()
            ->assertJsonPath('resource', url(self::MCP_PATH))
            ->assertJsonPath('authorization_servers.0', url('/'))
            ->assertJsonPath('scopes_supported.0', 'mcp:use');

        $this->getJson('/.well-known/oauth-authorization-server')
            ->assertOk()
            ->assertJsonPath('authorization_endpoint', route('passport.authorizations.authorize'))
            ->assertJsonPath('token_endpoint', route('passport.token'))
            ->assertJsonPath('registration_endpoint', url('/oauth/register'))
            ->assertJsonPath('code_challenge_methods_supported.0', 'S256');
    }

    public function test_clients_can_register_themselves(): void
    {
        $this->postJson('/oauth/register', [
            'client_name' => 'Test MCP Client',
            'redirect_uris' => [self::REDIRECT_URI],
        ])
            ->assertCreated()
            ->assertJsonStructure(['client_id', 'grant_types', 'redirect_uris'])
            ->assertJsonPath('token_endpoint_auth_method', 'none');

        $this->assertDatabaseHas('oauth_clients', ['name' => 'Test MCP Client']);
    }

    public function test_client_registration_requires_redirect_uris(): void
    {
        $this->postJson('/oauth/register', ['client_name' => 'No Redirects'])
            ->assertStatus(400)
            ->assertJsonPath('error', 'invalid_redirect_uri');
    }

    public function test_client_registration_rejects_invalid_redirect_uris(): void
    {
        $this->postJson('/oauth/register', [
            'client_name' => 'Bad Redirect',
            'redirect_uris' => ['not a url'],
        ])
            ->assertStatus(400)
            ->assertJsonPath('error', 'invalid_redirect_uri');

        $this->assertDatabaseMissing('oauth_clients', ['name' => 'Bad Redirect']);
    }

    public function test_consent_screen_requires_login(): void
    {
        $clientId = $this->registerClient();

        $this->get($this->authorizationUrl($clientId, $this->codeChallenge('verifier')))
            ->assertRedirect(route('login'));
    }

    public function test_user_can_complete_oauth_flow_and_use_mcp_server(): void
    {
        $clientId = $this->registerClient();
        $verifier = Str::random(64);

        $this->actingAs($this->user)
            ->get($this->authorizationUrl($clientId, $this->codeChallenge($verifier)))
            ->assertOk()
            ->assertSee('Test MCP Client')
            ->assertSee($this->user->email)
            ->assertSee('View and manage your portfolios, transactions and holdings');

        $authToken = session('authToken');
        $this->assertNotEmpty($authToken);

        $callback = $this->post(route('passport.authorizations.approve'), ['auth_token' => $authToken])
            ->assertRedirectContains(self::REDIRECT_URI)
            ->headers->get('Location');

        parse_str((string) parse_url($callback, PHP_URL_QUERY), $query);
        $this->assertSame('xyz', $query['state']);
        $this->assertNotEmpty($query['code']);

        $accessToken = $this->postJson(route('passport.token'), [
            'grant_type' => 'authorization_code',
            'client_id' => $clientId,
            'redirect_uri' => self::REDIRECT_URI,
            'code' => $query['code'],
            'code_verifier' => $verifier,
        ])
            ->assertOk()
            ->assertJsonStructure(['access_token', 'refresh_token', 'expires_in'])
            ->json('access_token');

        $this->app['auth']->forgetGuards();

        $this->withToken($accessToken)
            ->postJson(self::MCP_PATH, $this->initializePayload(), $this->mcpHeaders())
            ->assertOk()
            ->assertJsonPath('result.serverInfo.name', 'Investbrain Server');
    }

    public function test_user_can_deny_the_consent_screen(): void
    {
        $clientId = $this->registerClient();

        $this->actingAs($this->user)
            ->get($this->authorizationUrl($clientId, $this->codeChallenge('verifier')))
            ->assertOk();

        $callback = $this->delete(route('passport.authorizations.deny'), ['auth_token' => session('authToken')])
            ->assertRedirectContains(self::REDIRECT_URI)
            ->headers->get('Location');

        $this->assertStringContainsString('error=access_denied', $callback);
        $this->assertStringNotContainsString('code=', $callback);
        $this->assertDatabaseCount('oauth_access_tokens', 0);
    }

    public function test_approval_fails_with_an_invalid_auth_token(): void
    {
        $clientId = $this->registerClient();

        $this->actingAs($this->user)
            ->get($this->authorizationUrl($clientId, $this->codeChallenge('verifier')))
            ->assertOk();

        $this->post(route('passport.authorizations.approve'), ['auth_token' => 'forged'])
            ->assertForbidden();

        $this->assertDatabaseCount('oauth_auth_codes', 0);
    }

    public function test_token_exchange_fails_with_wrong_code_verifier(): void
    {
        $clientId = $this->registerClient();

        $this->actingAs($this->user)
            ->get($this->authorizationUrl($clientId, $this->codeChallenge('the-real-verifier')))
            ->assertOk();

        $callback = $this->post(route('passport.authorizations.approve'), ['auth_token' => session('authToken')])
            ->headers->get('Location');

        parse_str((string) parse_url($callback, PHP_URL_QUERY), $query);

        $this->postJson(route('passport.token'), [
            'grant_type' => 'authorization_code',
            'client_id' => $clientId,
            'redirect_uri' => self::REDIRECT_URI,
            'code' => $query['code'],
            'code_verifier' => 'a-different-verifier-that-is-long-enough-to-be-valid-0123456789',
        ])->assertStatus(400);

        $this->assertDatabaseCount('oauth_access_tokens', 0);
    }

    protected function registerClient(): string
    {
        return $this->postJson('/oauth/register', [
            'client_name' => 'Test MCP Client',
            'redirect_uris' => [self::REDIRECT_URI],
        ])->assertCreated()->json('client_id');
    }

    protected function codeChallenge(string $verifier): string
    {
        return rtrim(strtr(base64_encode(hash('sha256', $verifier, true)), '+/', '-_'), '=');
    }

    protected function authorizationUrl(string $clientId, string $challenge): string
    {
        return route('passport.authorizations.authorize').'?'.http_build_query([
            'client_id' => $clientId,
            'redirect_uri' => self::REDIRECT_URI,
            'response_type' => 'code',
            'scope' => 'mcp:use',
            'state' => 'xyz',
            'code_challenge' => $challenge,
            'code_challenge_method' => 'S256',
        ]);
    }
}
