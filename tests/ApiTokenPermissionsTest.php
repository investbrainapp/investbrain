<?php

declare(strict_types=1);

namespace Tests;

use App\Models\User;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Testing\RefreshDatabase;
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

    public function test_cannot_delete_another_users_token(): void
    {
        $otherUser = User::factory()->create();
        $otherToken = $otherUser->createToken('Other Token')->getToken();

        $this->actingAs(User::factory()->create());

        $this->expectException(ModelNotFoundException::class);

        Livewire::test('api-token-manager')
            ->set(['apiTokenIdBeingDeleted' => $otherToken->id])
            ->call('deleteApiToken');
    }
}
