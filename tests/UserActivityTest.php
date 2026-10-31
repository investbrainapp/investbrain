<?php

declare(strict_types=1);

namespace Tests;

use App\Listeners\RecordUserActivity;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;

class UserActivityTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        RecordUserActivity::ignoreUsing(null);

        parent::tearDown();
    }

    public function test_each_login_is_recorded_without_touching_updated_at(): void
    {
        $user = User::factory()->create();
        $updatedAt = $user->updated_at;

        $this->post('/login', ['email' => $user->email, 'password' => 'password']);
        Auth::logout();

        $this->travel(1)->day();
        $this->post('/login', ['email' => $user->email, 'password' => 'password'], ['REMOTE_ADDR' => '203.0.113.9']);

        $user->refresh();

        $this->assertTrue($user->last_login_at->isSameMinute(now()));
        $this->assertTrue($user->last_seen_at->isSameMinute(now()));
        $this->assertSame('203.0.113.9', $user->last_login_ip);
        $this->assertTrue($user->updated_at->equalTo($updatedAt));
    }

    public function test_authenticated_requests_update_last_seen_at_most_hourly(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->get('/dashboard');
        $firstSeen = $user->fresh()->last_seen_at;

        $this->travel(30)->minutes();
        $this->actingAs($user)->get('/dashboard');
        $this->assertTrue($user->fresh()->last_seen_at->equalTo($firstSeen));

        $this->travel(31)->minutes();
        $this->actingAs($user)->get('/dashboard');
        $this->assertTrue($user->fresh()->last_seen_at->isSameMinute(now()));
        $this->assertNull($user->fresh()->last_login_at);
    }

    public function test_activity_can_be_ignored(): void
    {
        RecordUserActivity::ignoreUsing(fn (): bool => true);

        $user = User::factory()->create();

        $this->post('/login', ['email' => $user->email, 'password' => 'password']);

        $this->assertNull($user->fresh()->last_login_at);
        $this->assertNull($user->fresh()->last_seen_at);
    }

    public function test_last_login_ip_is_not_serialized(): void
    {
        $user = User::factory()->create();
        $user->forceFill(['last_login_ip' => '203.0.113.9'])->save();

        $this->assertArrayNotHasKey('last_login_ip', $user->toArray());
    }
}
