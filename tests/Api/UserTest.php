<?php

declare(strict_types=1);

namespace Tests\Api;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UserTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();
    }

    public function test_can_get_authenticated_user_profile(): void
    {
        $this->actingAs($this->user)
            ->getJson(route('api.me'))
            ->assertOk()
            ->assertJsonStructure([
                'id',
                'name',
                'email',
                'profile_photo_url',
                'options' => ['display_currency', 'locale'],
                'created_at',
                'updated_at',
            ]);
    }

    public function test_profile_returns_correct_user_data(): void
    {
        $this->actingAs($this->user)
            ->getJson(route('api.me'))
            ->assertOk()
            ->assertJsonFragment([
                'id' => $this->user->id,
                'name' => $this->user->name,
                'email' => $this->user->email,
            ]);
    }

    public function test_profile_returns_correct_options(): void
    {
        $this->actingAs($this->user)
            ->getJson(route('api.me'))
            ->assertOk()
            ->assertJsonPath('options.display_currency', $this->user->getCurrency())
            ->assertJsonPath('options.locale', $this->user->getLocale());
    }

    public function test_cannot_access_profile_when_unauthenticated(): void
    {
        $this->getJson(route('api.me'))->assertUnauthorized();
    }

    public function test_profile_does_not_expose_password(): void
    {
        $response = $this->actingAs($this->user)
            ->getJson(route('api.me'))
            ->assertOk();

        $this->assertArrayNotHasKey('password', $response->json());
    }
}
