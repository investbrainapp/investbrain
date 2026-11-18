<?php

declare(strict_types=1);

namespace Tests;

use App\Models\User;
use App\Support\VersionCheck;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;

class VersionCheckTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Http::preventStrayRequests();
    }

    public function test_admins_have_no_notifications_when_running_the_latest_release(): void
    {
        config(['investbrain.version' => 'v1.4.3']);
        $this->fakeReleases('v1.4.3', 'v1.4.2');

        $this->actingAs(User::factory()->admin()->create())
            ->get(route('dashboard'))
            ->assertSeeInOrder(['Notifications', 'No new notifications'])
            ->assertDontSee('Update available');
    }

    public function test_admins_get_an_update_notification_when_a_newer_release_is_available(): void
    {
        config(['investbrain.version' => 'v1.4.3']);
        $this->fakeReleases('v1.10.0', 'v1.5.0', 'v1.4.3', 'v1.4.2');

        $this->actingAs(User::factory()->admin()->create())
            ->get(route('dashboard'))
            ->assertSeeInOrder([VersionCheck::RELEASES_URL, 'Update available', 'Investbrain 1.10.0 is now available. You are 2 versions behind.'], false)
            ->assertDontSee('No new notifications');
    }

    public function test_bell_is_hidden_when_the_version_is_unknown(): void
    {
        config(['investbrain.version' => null]);
        Http::fake();

        $this->actingAs(User::factory()->admin()->create())
            ->get(route('dashboard'))
            ->assertOk()
            ->assertDontSee('Notifications');

        Http::assertNothingSent();
    }

    public function test_non_release_builds_are_not_checked(): void
    {
        config(['investbrain.version' => 'dev']);
        Http::fake();

        $this->actingAs(User::factory()->admin()->create())
            ->get(route('dashboard'))
            ->assertSee('No new notifications')
            ->assertDontSee('Update available');

        Http::assertNothingSent();
    }

    public function test_non_admins_do_not_see_the_bell_or_trigger_a_lookup(): void
    {
        config(['investbrain.version' => 'v1.4.3']);
        Http::fake();

        $this->actingAs(User::factory()->create())
            ->get(route('dashboard'))
            ->assertOk()
            ->assertDontSee('Notifications');

        Http::assertNothingSent();
    }

    public function test_hosted_instances_do_not_show_the_bell_even_to_admins(): void
    {
        config(['investbrain.version' => 'v1.4.3', 'investbrain.self_hosted' => false]);
        Http::fake();

        $this->actingAs(User::factory()->admin()->create())
            ->get(route('dashboard'))
            ->assertOk()
            ->assertDontSee('Notifications');

        Http::assertNothingSent();
    }

    public function test_latest_release_is_looked_up_once_and_cached(): void
    {
        config(['investbrain.version' => 'v1.4.3']);
        $this->fakeReleases('v1.4.4');

        $this->assertSame('1.4.4', app(VersionCheck::class)->latest());
        $this->assertTrue(app(VersionCheck::class)->updateAvailable());

        Http::assertSentCount(1);
    }

    public function test_failed_lookup_is_not_retried_on_every_request(): void
    {
        config(['investbrain.version' => 'v1.4.3']);
        Http::fake([VersionCheck::RELEASES_ENDPOINT.'*' => Http::sequence()
            ->pushStatus(503)
            ->push([['tag_name' => 'v1.4.4']]),
        ]);

        $this->assertNull(app(VersionCheck::class)->latest());
        $this->assertFalse(app(VersionCheck::class)->updateAvailable());

        Http::assertSentCount(1);

        $this->travel(61)->minutes();

        $this->assertSame('1.4.4', app(VersionCheck::class)->latest());
    }

    public function test_older_latest_release_does_not_count_as_an_update(): void
    {
        config(['investbrain.version' => 'v1.5.0']);
        $this->fakeReleases('v1.4.3');

        $this->assertFalse(app(VersionCheck::class)->updateAvailable());
    }

    public function test_single_newer_release_reads_as_one_version_behind(): void
    {
        config(['investbrain.version' => 'v1.4.2']);
        $this->fakeReleases('v1.4.3', 'v1.4.2', 'v1.4.1');

        $this->actingAs(User::factory()->admin()->create())
            ->get(route('dashboard'))
            ->assertSee('Investbrain 1.4.3 is now available. You are 1 version behind.');
    }

    public function test_prereleases_are_not_counted(): void
    {
        config(['investbrain.version' => 'v1.4.3']);
        Http::fake([VersionCheck::RELEASES_ENDPOINT.'*' => Http::response([
            ['tag_name' => 'v2.0.0-beta.1', 'prerelease' => true],
            ['tag_name' => 'v1.4.3', 'prerelease' => false],
        ])]);

        $this->assertSame('1.4.3', app(VersionCheck::class)->latest());
        $this->assertSame(0, app(VersionCheck::class)->versionsBehind());
        $this->assertFalse(app(VersionCheck::class)->updateAvailable());
    }

    protected function fakeReleases(string ...$tags): void
    {
        Http::fake([VersionCheck::RELEASES_ENDPOINT.'*' => Http::response(
            array_map(fn (string $tag): array => ['tag_name' => $tag, 'prerelease' => false], $tags),
        )]);
    }
}
