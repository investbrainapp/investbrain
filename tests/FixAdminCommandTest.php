<?php

declare(strict_types=1);

namespace Tests;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

class FixAdminCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_oldest_user_becomes_admin_when_no_admin_exists(): void
    {
        $newerUser = User::factory()->create(['created_at' => now()->subDay()]);
        $oldestUser = User::factory()->create(['created_at' => now()->subYear()]);

        $this->artisan('fix:admin')
            ->expectsOutputToContain($oldestUser->email)
            ->assertSuccessful();

        $this->assertTrue($oldestUser->refresh()->admin);
        $this->assertNotTrue($newerUser->refresh()->admin);
    }

    public function test_nothing_changes_when_an_admin_already_exists(): void
    {
        $oldestUser = User::factory()->create(['created_at' => now()->subYear()]);
        $admin = User::factory()->admin()->create(['created_at' => now()->subDay()]);

        $this->artisan('fix:admin')
            ->expectsOutputToContain($admin->email)
            ->assertSuccessful();

        $this->assertNotTrue($oldestUser->refresh()->admin);
        $this->assertSame(1, User::where('admin', true)->count());
    }

    public function test_command_refuses_to_run_when_not_self_hosted(): void
    {
        config(['investbrain.self_hosted' => false]);

        $user = User::factory()->create();

        $this->artisan('fix:admin')->assertFailed();

        $this->assertNotTrue($user->refresh()->admin);
    }

    public function test_command_fails_when_there_are_no_users(): void
    {
        $this->artisan('fix:admin')->assertFailed();
    }
}
