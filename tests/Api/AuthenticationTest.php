<?php

declare(strict_types=1);

namespace Tests\Api;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

class AuthenticationTest extends ApiTestCase
{
    use RefreshDatabase;

    protected User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();
    }

    public function test_can_access_api_with_personal_access_token(): void
    {
        $token = $this->user->createToken('Test Token')->accessToken;

        $this->withToken($token)
            ->getJson(route('api.me'))
            ->assertOk()
            ->assertJsonFragment(['email' => $this->user->email]);
    }

    public function test_cannot_access_api_with_revoked_token(): void
    {
        $token = $this->user->createToken('Test Token')->accessToken;

        $this->user->tokens()->firstOrFail()->revoke();

        $this->withToken($token)
            ->getJson(route('api.me'))
            ->assertUnauthorized();
    }

    public function test_cannot_access_api_with_malformed_token(): void
    {
        $this->withToken('not-a-real-token')
            ->getJson(route('api.me'))
            ->assertUnauthorized();
    }

    public function test_cannot_access_api_without_token(): void
    {
        $this->getJson(route('api.me'))->assertUnauthorized();
    }

    public function test_cannot_access_api_with_browser_session_only(): void
    {
        $this->be($this->user, 'web');

        $this->getJson(route('api.me'))->assertUnauthorized();
    }

    public function test_token_belongs_to_the_user_who_created_it(): void
    {
        $otherUser = User::factory()->create();

        $token = $otherUser->createToken('Other Token')->accessToken;

        $this->withToken($token)
            ->getJson(route('api.me'))
            ->assertOk()
            ->assertJsonFragment(['email' => $otherUser->email])
            ->assertJsonMissing(['email' => $this->user->email]);
    }
}
