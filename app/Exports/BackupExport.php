<?php

declare(strict_types=1);

namespace App\Exports;

use App\Exports\Sheets\ConfigSheet;
use App\Exports\Sheets\DailyChangesSheet;
use App\Exports\Sheets\PortfoliosSheet;
use App\Exports\Sheets\TransactionsSheet;
use Maatwebsite\Excel\Concerns\Exportable;
use Maatwebsite\Excel\Concerns\WithMultipleSheets;

class BackupExport implements WithMultipleSheets
{
    use Exportable;

    public function __construct(
        public bool $empty = false
    ) {}

    public function sheets(): array
    {
        return [
            new PortfoliosSheet($this->empty),
            new TransactionsSheet($this->empty),
            new DailyChangesSheet($this->empty),
            new ConfigSheet($this->empty),
        ];
    }
}
