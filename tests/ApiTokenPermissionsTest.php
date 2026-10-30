<?php

declare(strict_types=1);

namespace Tests;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Laravel\Passport\Passport;
use Livewire\Livewire;

class ApiTokenPermissionsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->usePassportKeys();
    }

    public function test_api_tokens_can_be_deleted(): void
    {
        $this->actingAs($user = User::factory()->create());

        $token = $user->createToken('Test Token')->getToken();

        Livewire::test('api-token-manager')
            ->set(['apiTokenIdBeingDeleted' => $token->id])
            ->call('deleteApiToken');

        $this->assertTrue($token->fresh()->revoked);
        $this->assertCount(0, $user->tokens()->where('revoked', false)->get());
    }

    public function test_api_tokens_can_be_created(): void
    {
        $this->actingAs($user = User::factory()->create());

        $component = Livewire::test('api-token-manager')
            ->set(['createApiTokenForm' => [
                'name' => 'Test Token',
            ]])
            ->call('createApiToken')
            ->assertSet('displayingToken', true);

        $this->assertCount(1, $user->tokens);
        $this->assertEquals('Test Token', $user->tokens->first()->name);
        $this->assertNotEmpty($component->get('plainTextToken'));
    }

    public function test_api_token_name_is_required(): void
    {
        $this->actingAs($user = User::factory()->create());

        Livewire::test('api-token-manager')
            ->set(['createApiTokenForm' => ['name' => '']])
            ->call('createApiToken')
            ->assertHasErrors();

        $this->assertCount(0, $user->tokens);
    }

    public function test_created_token_is_listed_and_revoked_token_is_not(): void
    {
        $this->actingAs($user = User::factory()->create());

        $token = $user->createToken('Listed Token')->getToken();

        Livewire::test('api-token-manager')->assertSee('Listed Token');

        $token->revoke();

        Livewire::test('api-token-manager')->assertDontSee('Listed Token');
    }

    public function test_oauth_client_tokens_are_not_listed(): void
    {
        $this->actingAs($user = User::factory()->create());

        $user->createToken('Personal Token');

        $client = Passport::client()->factory()->create(['name' => 'Third Party App']);

        Passport::token()->forceCreate([
            'id' => Str::random(80),
            'user_id' => $user->id,
            'client_id' => $client->id,
            'name' => null,
            'scopes' => [],
            'revoked' => false,
            'expires_at' => now()->addDay(),
        ]);

        Livewire::test('api-token-manager')
            ->assertSee('Personal Token')
            ->assertDontSee('Third Party App');
    }

    public function test_cannot_delete_another_users_token(): void
    {
        $otherUser = User::factory()->create();
        $otherToken = $otherUser->createToken('Other Token')->getToken();

        $this->actingAs(User::factory()->create());

        Livewire::test('api-token-manager')
            ->set(['apiTokenIdBeingDeleted' => $otherToken->id])
            ->call('deleteApiToken')
            ->assertStatus(404);

        $this->assertFalse($otherToken->fresh()->revoked);
    }
}
