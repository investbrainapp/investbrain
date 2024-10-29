<?php

namespace Tests;

use Tests\TestCase;
use App\Models\User;
use App\Models\Transaction;
use Maatwebsite\Excel\Facades\Excel;
use App\Exports\BackupExport;
use App\Models\BackupImport as BackupImportModel;
use Illuminate\Foundation\Testing\RefreshDatabase;

class ImportExportTest extends TestCase
{
    use RefreshDatabase;

    /**
     */
    public function test_can_create_exports(): void
    {
        Excel::fake();

        $this->actingAs($user = User::factory()->create());

        Transaction::factory(5)->buy()->lastYear()->symbol('AAPL')->create();

        Excel::download(new BackupExport, now()->format('Y_m_d') . '_investbrain_backup.xlsx');

        Excel::assertDownloaded(now()->format('Y_m_d') . '_investbrain_backup.xlsx', function(BackupExport $export) {
            return true;
        });
    }

    /**
     */
    public function test_backup_job_completes(): void
    {
        $this->actingAs($user = User::factory()->create());

        $backup_job = BackupImportModel::create([
            'user_id' => auth()->user()->id,
            'path' => __DIR__.'/0000_00_00_import_test.xlsx'
        ]);

        $backup_job->refresh();

        $this->assertEquals('success', $backup_job->status);
    }

    /**
     */
    public function test_backup_job_inserts_rows(): void
    {
        $this->actingAs($user = User::factory()->create());

        BackupImportModel::create([
            'user_id' => auth()->user()->id,
            'path' => __DIR__.'/0000_00_00_import_test.xlsx'
        ]);

        $this->assertEquals(3, $user->transactions->count());
    }

    /**
     */
    public function test_backup_job_calculates_correct_holding_data(): void
    {
        $this->actingAs($user = User::factory()->create());

        BackupImportModel::create([
            'user_id' => auth()->user()->id,
            'path' => __DIR__.'/0000_00_00_import_test.xlsx'
        ]);

        $holding = $user->holdings->first();

        $this->assertEquals('AAPL', $holding->symbol);
        $this->assertEquals(6, $holding->quantity);
        $this->assertEqualsWithDelta(233.33, $holding->average_cost_basis, 0.01);
    }
}
