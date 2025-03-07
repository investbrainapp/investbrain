<?php

declare(strict_types=1);

namespace Tests;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Laravel\Jetstream\Features;
use Laravel\Jetstream\Http\Livewire\ApiTokenManager;
use Livewire\Livewire;

class ApiTokenPermissionsTest extends TestCase
{
    use RefreshDatabase;

    public function test_api_tokens_can_be_deleted(): void
    {

        if (! Features::hasApiFeatures()) {
            $this->markTestSkipped('API support is not enabled.');
        }

        $this->actingAs($user = User::factory()->create());

        $token = $user->tokens()->create([
            'name' => 'Test Token',
            'token' => Str::random(40),
            'abilities' => [],
        ]);

        Livewire::test(ApiTokenManager::class)
            ->set(['apiTokenIdBeingDeleted' => $token->id])
            ->call('deleteApiToken');

        $this->assertCount(0, $user->fresh()->tokens);
    }

    public function test_api_tokens_can_be_created(): void
    {

        if (! Features::hasApiFeatures()) {
            $this->markTestSkipped('API support is not enabled.');
        }

        $this->actingAs($user = User::factory()->create());

        Livewire::test(ApiTokenManager::class)
            ->set(['createApiTokenForm' => [
                'name' => 'Test Token',
                'permissions' => [],
            ]])
            ->call('createApiToken');

        $this->assertCount(1, $user->fresh()->tokens);
        $this->assertEquals('Test Token', $user->fresh()->tokens->first()->name);
    }

    // public function test_api_token_permissions_can_be_updated(): void
    // {
    //     if (! Features::hasApiFeatures()) {
    //         $this->markTestSkipped('API support is not enabled.');
    //     }

    //     $this->actingAs($user = User::factory()->create());

    //     $token = $user->tokens()->create([
    //         'name' => 'Test Token',
    //         'token' => Str::random(40),
    //         'abilities' => ['create', 'read'],
    //     ]);

    //     Livewire::test(ApiTokenManager::class)
    //         ->set(['managingPermissionsFor' => $token])
    //         ->set(['updateApiTokenForm' => [
    //             'permissions' => [],
    //         ]])
    //         ->call('updateApiToken');

    //     $this->assertTrue($user->fresh()->tokens->first()->can('delete'));
    //     $this->assertFalse($user->fresh()->tokens->first()->can('read'));
    //     $this->assertFalse($user->fresh()->tokens->first()->can('missing-permission'));
    // }
}
