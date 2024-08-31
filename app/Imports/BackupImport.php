<?php

namespace App\Imports;

use Illuminate\Support\Facades\DB;
use App\Imports\Sheets\SplitsSheet;
use App\Imports\Sheets\DividendsSheet;
use App\Imports\Sheets\MarketDataSheet;
use App\Imports\Sheets\PortfoliosSheet;
use Illuminate\Support\Facades\Artisan;
use Maatwebsite\Excel\Events\AfterSheet;
use App\Imports\Sheets\DailyChangesSheet;
use App\Imports\Sheets\TransactionsSheet;
use Maatwebsite\Excel\Events\BeforeSheet;
use Maatwebsite\Excel\Concerns\Importable;
use Maatwebsite\Excel\Concerns\WithEvents;
use App\Console\Commands\RefreshHoldingData;
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
            // BeforeSheet::class => DB::commit(),
            // AfterSheet::class => Artisan::queue(RefreshHoldingData::class),
            // AfterSheet::class => Artisan::call(RefreshHoldingData::class)
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
