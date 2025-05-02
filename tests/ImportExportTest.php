<?php

declare(strict_types=1);

namespace Tests;

use App\Exports\BackupExport;
use App\Models\BackupImport as BackupImportModel;
use App\Models\Holding;
use App\Models\Portfolio;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Maatwebsite\Excel\Facades\Excel;

class ImportExportTest extends TestCase
{
    use RefreshDatabase;

    public function test_can_create_exports(): void
    {
        Excel::fake();

        $this->actingAs($user = User::factory()->create());

        Transaction::factory(5)->buy()->lastYear()->symbol('AAPL')->create();

        Excel::download(new BackupExport, now()->format('Y_m_d').'_investbrain_backup.xlsx');

        Excel::assertDownloaded(now()->format('Y_m_d').'_investbrain_backup.xlsx', function (BackupExport $export) {
            return true;
        });
    }

    public function test_import_job_completes(): void
    {
        $this->actingAs($user = User::factory()->create());

        $import_job = BackupImportModel::create([
            'user_id' => auth()->user()->id,
            'path' => __DIR__.'/0000_00_00_import_test.xlsx',
        ]);

        $import_job->refresh();

        $this->assertEquals('success', $import_job->status);
    }

    public function test_import_job_inserts_rows(): void
    {
        $this->actingAs($user = User::factory()->create());

        BackupImportModel::create([
            'user_id' => auth()->user()->id,
            'path' => __DIR__.'/0000_00_00_import_test.xlsx',
        ]);

        $this->assertEquals(3, $user->transactions->count());
    }

    public function test_import_job_calculates_correct_holding_data(): void
    {
        $this->actingAs($user = User::factory()->create());

        BackupImportModel::create([
            'user_id' => auth()->user()->id,
            'path' => __DIR__.'/0000_00_00_import_test.xlsx',
        ]);

        $holding = $user->holdings->first();

        $this->assertEquals('AAPL', $holding->symbol);
        $this->assertEquals(6, $holding->quantity);
        $this->assertEqualsWithDelta(233.33, $holding->average_cost_basis, 0.01);
    }

    public function test_configurations_are_set_on_import(): void
    {
        $this->actingAs($user = User::factory()->create());

        Portfolio::create([
            'id' => '9e792bb8-94e7-4ed3-b8cc-43b50d34c337',
            'title' => 'Test Portfolio',
        ]);

        $holding = Holding::create([
            'portfolio_id' => '9e792bb8-94e7-4ed3-b8cc-43b50d34c337',
            'symbol' => 'ACME',
            'quantity' => 0,
            'reinvest_dividends' => false,
        ]);

        $this->assertEquals(false, $holding->reinvest_dividends);

        BackupImportModel::create([
            'user_id' => auth()->user()->id,
            'path' => __DIR__.'/0000_00_00_import_configs_test.xlsx',
        ]);

        $holding->refresh();

        $this->assertEquals(true, $holding->reinvest_dividends);
    }
}
