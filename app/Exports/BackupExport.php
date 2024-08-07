<?php

namespace App\Exports;

use App\Exports\Sheets\DailyChangesSheet;
use App\Exports\Sheets\SplitsSheet;
use App\Exports\Sheets\DividendsSheet;
use App\Exports\Sheets\MarketDataSheet;
use App\Exports\Sheets\PortfoliosSheet;
use App\Exports\Sheets\TransactionsSheet;
use Maatwebsite\Excel\Concerns\Exportable;
use Maatwebsite\Excel\Concerns\WithMultipleSheets;

class BackupExport implements WithMultipleSheets
{
    use Exportable;

    /**
     * @return array
     */
    public function sheets(): array
    {
            return [
                new PortfoliosSheet,
                new TransactionsSheet,
                new MarketDataSheet,
                new DividendsSheet,
                new SplitsSheet,
                new DailyChangesSheet
            ];
    }
}
