<?php

declare(strict_types=1);

namespace Tests;

use App\Models\User;
use Illuminate\Support\Facades\Log;
use Illuminate\Foundation\Testing\RefreshDatabase;

class AuthenticationTest extends TestCase
{
    use RefreshDatabase;

    public function test_first_user_is_admin(): void
    {
        $this->post('/register', [
            'name' => 'should_be_admin',
            'email' => 'should_be_admin@example.net',
            'password' => 'password',
            'password_confirmation' => 'password',
        ]);

        $should_be_admin = User::where(['email' => 'should_be_admin@example.net'])->first();

        $this->assertTrue($should_be_admin->admin);
    }

    public function test_other_users_are_not_admin(): void
    {
        User::factory()->create();

        $this->post('/register', [
            'name' => 'not_admin',
            'email' => 'not_admin@example.net',
            'password' => 'password',
            'password_confirmation' => 'password',
        ]);

        $not_admin = User::where(['email' => 'not_admin@example.net'])->first();

        $this->assertNotTrue($not_admin->admin);
    }

    public function test_login_screen_can_be_rendered(): void
    {
        $response = $this->get('/login');

        $response->assertStatus(200);
    }

    public function test_users_can_authenticate_using_the_login_screen(): void
    {
        $user = User::factory()->create();

        $response = $this->post('/login', [
            'email' => $user->email,
            'password' => 'password',
        ]);

        $this->assertAuthenticated();
        $response->assertRedirect(route('dashboard', absolute: false));
    }

    public function test_users_can_not_authenticate_with_invalid_password(): void
    {
        $user = User::factory()->create();

        $this->post('/login', [
            'email' => $user->email,
            'password' => 'wrong-password',
        ]);

        $this->assertGuest();
    }
}
