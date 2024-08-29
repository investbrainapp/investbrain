<?php

namespace App\Imports\Sheets;

use App\Models\DailyChange;
use Exception;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\SkipsEmptyRows;
use Maatwebsite\Excel\Concerns\WithHeadingRow;

class DailyChangesSheet implements ToCollection, WithHeadingRow, SkipsEmptyRows
{
    // use Importable;

    public function collection(Collection $dailyChanges)
    {
        foreach ($dailyChanges as $dailyChange) {

            if ($dailyChange['user'] != auth()->user()->id) {

                throw new Exception('Can\'t do that.');
            }

            DailyChange::updateOrCreate([
                'date' => $dailyChange['date'],
                'portfolio_id' => $dailyChange['portfolio_id'],
            ],[
                'portfolio_id' => $dailyChange['portfolio_id'],
                'date' => $dailyChange['date'],
                'total_market_value' => $dailyChange['total_market_value'],
                'total_cost_basis' => $dailyChange['total_cost_basis'],
                'total_gain' => $dailyChange['total_gain'],
                'total_dividends' => $dailyChange['total_dividends'],
                'realized_gains' => $dailyChange['realized_gains'],
                'annotation' => $dailyChange['annotation'],
            ]);
        }
    }
}
