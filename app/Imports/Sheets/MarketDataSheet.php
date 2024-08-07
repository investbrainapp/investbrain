<?php

namespace App\Imports\Sheets;

use App\Models\MarketData;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\SkipsEmptyRows;
use Maatwebsite\Excel\Concerns\WithHeadingRow;

class MarketDataSheet implements ToCollection, WithHeadingRow, SkipsEmptyRows
{
    // use Importable;

    public function collection(Collection $market_data)
    {
        foreach ($market_data->sortBy('symbol') as $row) {

            MarketData::updateOrCreate(
                [
                    'symbol' => $row['symbol']
                ],
                [
                    'symbol' => $row['symbol'],
                    'name' => $row['name'],
                    'market_value' => $row['market_value'],
                    'fifty_two_week_low' => $row['52_week_low'],
                    'fifty_two_week_high' => $row['52_week_high'],
                    'dividend_date' => $row['dividend_date'],
                    'splits_synced_to_holdings_at' => $row['splits_synced_to_holdings_at']
                ]
            );
        }
    }
}
