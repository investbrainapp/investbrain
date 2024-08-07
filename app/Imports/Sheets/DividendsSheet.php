<?php

namespace App\Imports\Sheets;

use App\Models\Dividend;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\SkipsEmptyRows;
use Maatwebsite\Excel\Concerns\WithHeadingRow;

class DividendsSheet implements ToCollection, WithHeadingRow, SkipsEmptyRows
{
    // use Importable;

    public function collection(Collection $dividend)
    {
        foreach ($dividend->sortBy('date') as $row) {

            Dividend::updateOrCreate([
                'symbol' => $row['symbol'],
                'date' => $row['date'],
            ],[
                'symbol' => $row['symbol'],
                'dividend_amount' => $row['amount'] ?? 0,
                'date' => $row['date'],
            ]);
        }
    }
}
