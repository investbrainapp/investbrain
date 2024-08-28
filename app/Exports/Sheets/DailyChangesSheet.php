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
            'Portfolio',
            'Total Market Value',
            'Total Cost Basis',
            'Total Gain Loss',
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
        return auth()->user()->daily_changes;
    }

     /**
     * @return string
     */
    public function title(): string
    {
        return 'Daily Changes';
    }
}
