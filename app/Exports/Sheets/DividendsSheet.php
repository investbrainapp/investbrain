<?php

namespace App\Exports\Sheets;

use App\Models\Dividend;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithTitle;

class DividendsSheet implements FromCollection, WithHeadings, WithTitle
{
    public function headings(): array
    {
        return [
            'ID',
            'Date',
            'Symbol',
            'Amount',
            'Created',
            'Updated',
        ];
    }

    /**
    * @return \Illuminate\Support\Collection
    */
    public function collection()
    {
        return Dividend::get();
    }

     /**
     * @return string
     */
    public function title(): string
    {
        return 'Dividends';
    }
}
