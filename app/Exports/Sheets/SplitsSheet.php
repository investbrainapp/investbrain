<?php

namespace App\Exports\Sheets;

use App\Models\Split;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithTitle;

class SplitsSheet implements FromCollection, WithHeadings, WithTitle
{
    public function headings(): array
    {
        return [
            'ID',
            'Date',
            'Symbol',
            'Split',
            'Created',
            'Updated',
        ];
    }

    /**
    * @return \Illuminate\Support\Collection
    */
    public function collection()
    {
        return Split::get();
    }

     /**
     * @return string
     */
    public function title(): string
    {
        return 'Splits';
    }
}
