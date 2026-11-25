<?php

declare(strict_types=1);

namespace Tests\Api;

use App\Actions\Export\StartBackupExport;
use App\Jobs\AssembleBackupExportJob;
use App\Models\BackupExport;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;

class ExportTest extends ApiTestCase
{
    use RefreshDatabase;

    protected User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();
    }

    public function test_cannot_start_an_export_when_unauthenticated(): void
    {
        $this->postJson(route('api.export.store'))->assertUnauthorized();

        $this->assertDatabaseCount('backup_export_jobs', 0);
    }

    public function test_starting_an_export_builds_the_backup_in_the_background(): void
    {
        Bus::fake();

        $response = $this->actingAs($this->user)
            ->postJson(route('api.export.store'))
            ->assertAccepted()
            ->assertJsonPath('status', 'pending')
            ->assertJsonPath('download_url', null)
            ->assertJsonPath('completed_at', null);

        $backupExport = BackupExport::sole();
        $this->assertSame($backupExport->id, $response->json('id'));
        $this->assertSame($this->user->id, $backupExport->user_id);
        Bus::assertChained([AssembleBackupExportJob::class]);
    }

    public function test_starting_an_export_while_one_is_being_prepared_returns_that_export(): void
    {
        Queue::fake();
        $running = BackupExport::create(['user_id' => $this->user->id]);

        $this->actingAs($this->user)
            ->postJson(route('api.export.store'))
            ->assertAccepted()
            ->assertJsonPath('id', $running->id);

        $this->assertSame($running->id, BackupExport::sole()->id);
        Queue::assertNothingPushed();
    }

    public function test_starting_too_many_exports_returns_429(): void
    {
        Queue::fake();
        $this->actingAs($this->user);

        for ($i = 0; $i < StartBackupExport::EXPORTS_PER_MINUTE; $i++) {
            $this->postJson(route('api.export.store'))->assertAccepted();

            // finish it so the next request starts a new export rather than returning this one
            BackupExport::sole()->markAsFailed();
        }

        $this->postJson(route('api.export.store'))
            ->assertTooManyRequests()
            ->assertHeader('Retry-After')
            ->assertJsonPath('message', 'Hang on! You\'re doing that too much.');
    }

    public function test_finished_export_shows_where_to_download_it(): void
    {
        $backupExport = $this->finishedExportFor($this->user);

        $this->actingAs($this->user)
            ->getJson(route('api.export.show', $backupExport))
            ->assertOk()
            ->assertJsonPath('id', $backupExport->id)
            ->assertJsonPath('status', 'success')
            ->assertJsonPath('download_url', route('api.export.download', $backupExport))
            ->assertJsonPath('expires_at', $backupExport->created_at->addHours(24)->toJSON());
    }

    public function test_cannot_see_another_users_export_returns_404(): void
    {
        $backupExport = $this->finishedExportFor(User::factory()->create());

        $this->actingAs($this->user)
            ->getJson(route('api.export.show', $backupExport))
            ->assertNotFound();
    }

    public function test_can_download_a_finished_export(): void
    {
        $backupExport = $this->finishedExportFor($this->user);

        $this->actingAs($this->user)
            ->get(route('api.export.download', $backupExport))
            ->assertOk()
            ->assertDownload($backupExport->filename());
    }

    public function test_downloading_an_export_that_is_not_finished_returns_404(): void
    {
        $backupExport = BackupExport::create(['user_id' => $this->user->id]);

        $this->actingAs($this->user)
            ->getJson(route('api.export.download', $backupExport))
            ->assertNotFound();
    }

    public function test_cannot_download_another_users_export_returns_404(): void
    {
        $backupExport = $this->finishedExportFor(User::factory()->create());

        $this->actingAs($this->user)
            ->getJson(route('api.export.download', $backupExport))
            ->assertNotFound();
    }

    private function finishedExportFor(User $user): BackupExport
    {
        Storage::fake();
        $backupExport = BackupExport::create(['user_id' => $user->id]);
        Storage::disk()->put($backupExport->workbookPath(), 'contents');
        $backupExport->update(['status' => 'success', 'path' => $backupExport->workbookPath(), 'completed_at' => now()]);

        return $backupExport;
    }
}
