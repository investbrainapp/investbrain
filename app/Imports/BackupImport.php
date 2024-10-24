<?php

namespace App\Imports;

use App\Models\User;
use App\Imports\Sheets\PortfoliosSheet;
use Illuminate\Support\Facades\Artisan;
use App\Console\Commands\SyncDailyChange;
use App\Console\Commands\SyncHoldingData;
use App\Imports\Sheets\DailyChangesSheet;
use App\Imports\Sheets\TransactionsSheet;
use Maatwebsite\Excel\Events\AfterImport;
use Maatwebsite\Excel\Concerns\Importable;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Events\BeforeImport;
use Maatwebsite\Excel\Events\ImportFailed;
use App\Console\Commands\RefreshMarketData;
use App\Console\Commands\RefreshDividendData;
use App\Models\BackupImport as BackupImportModel;
use Maatwebsite\Excel\Concerns\WithMultipleSheets;

class BackupImport implements WithMultipleSheets, WithEvents
{

    use Importable;

    public function __construct(
        public BackupImportModel $backupImportModel
    ) { }

    /**
     * @return array
     */
    public function registerEvents(): array
    {
        return [
            BeforeImport::class => fn() => $this->backupImportModel->update([
                'status' => 'in_progress',
                'message' => __('Import is in progress...'),
            ]),
            AfterImport::class => function () {

                $this->backupImportModel->update([
                    'status' => 'success',
                    'message' => 'Import completed successfully!',
                    'completed_at' => now()
                ]);
                
                Artisan::queue(RefreshMarketData::class, ['--user' => $this->backupImportModel->user_id, '--force' => true])
                    ->chain([
                        fn() => Artisan::call(RefreshDividendData::class, ['--user' => $this->backupImportModel->user_id, '--force' => true])
                    ])
                    ->chain([
                        fn() => Artisan::call(SyncHoldingData::class, ['--user' => $this->backupImportModel->user_id])
                    ])
                    ->chain([
                        function() {

                            User::find($this->backupImportModel->user_id)->portfolios->each(function($portfolio) {

                                Artisan::call(SyncDailyChange::class, ['portfolio_id' => $portfolio->id]);
                            });
                        }
                    ]);
            },
            ImportFailed::class => fn(ImportFailed $event) => $this->backupImportModel->update([
                'status' => 'failed',
                'message' => 'Error: '. substr($event->getException()->getMessage(), 0, 220),
                'has_errors' => true,
                'completed_at' => now()
            ]),
        ];
    }

    public function sheets(): array
    {
        return [
            'Portfolios' => new PortfoliosSheet($this->backupImportModel),
            'Transactions' => new TransactionsSheet($this->backupImportModel),
            'Daily Changes' => new DailyChangesSheet($this->backupImportModel),
        ];
    }
}
