<?php

namespace App\Exports\Sheets;

use App\Models\MarketData;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithTitle;

class MarketDataSheet implements FromCollection, WithHeadings, WithTitle
{
    public function headings(): array
    {
        return [
            'Symbol',
            'Name',
            'Market Value',
            '52 Week Low',
            '52 Week High',
            'Dividend Date',
            'Splits Synced To Holdings At',
            'Created',
            'Updated',
        ];
    }

    /**
    * @return \Illuminate\Support\Collection
    */
    public function collection()
    {
        return MarketData::get();
    }

     /**
     * @return string
     */
    public function title(): string
    {
        return 'Market Data';
    }
}
