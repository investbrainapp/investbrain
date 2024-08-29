<?php

namespace App\Exports\Sheets;

use App\Models\DailyChange;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithTitle;

class DailyChangesSheet implements FromCollection, WithHeadings, WithTitle
{
    public function headings(): array
    {
        return [
            'Date',
            'Portfolio ID',
            'Total Market Value',
            'Total Cost Basis',
            'Total Gain',
            'Total Dividends',
            'Realized Gains',
            'Annotation'
        ];
    }

    /**
    * @return \Illuminate\Support\Collection
    */
    public function collection()
    {
        return DailyChange::myDailyChanges()->get();
    }

     /**
     * @return string
     */
    public function title(): string
    {
        return 'Daily Changes';
    }
}
