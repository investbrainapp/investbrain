<?php

namespace App\Imports;

use App\Imports\Sheets\PortfoliosSheet;
use Illuminate\Support\Facades\Artisan;
use App\Imports\Sheets\DailyChangesSheet;
use App\Imports\Sheets\TransactionsSheet;
use Maatwebsite\Excel\Events\AfterImport;
use Maatwebsite\Excel\Concerns\Importable;
use Maatwebsite\Excel\Concerns\WithEvents;
use App\Console\Commands\RefreshMarketData;
use App\Console\Commands\RefreshDividendData;
use Maatwebsite\Excel\Concerns\WithMultipleSheets;

class BackupImport implements WithMultipleSheets, WithEvents
{

    use Importable;

    /**
     * @return array
     */
    public function registerEvents(): array
    {
        return [
            AfterImport::class => 
                fn() => Artisan::queue(RefreshMarketData::class, ['--force' => true])->chain([
                    fn() => Artisan::call(RefreshDividendData::class)
                ])
        ];
    }

    public function sheets(): array
    {
        return [
            'Portfolios' => new PortfoliosSheet,
            'Transactions' => new TransactionsSheet,
            'Daily Changes' => new DailyChangesSheet,
        ];
    }
}
