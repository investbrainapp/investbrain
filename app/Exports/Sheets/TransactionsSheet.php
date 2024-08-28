<?php

namespace App\Exports\Sheets;

use App\Models\Transaction;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithTitle;

class TransactionsSheet implements FromCollection, WithHeadings, WithTitle
{
    public function headings(): array
    {
        return [
            'Transaction ID',
            'Symbol',
            'Portfolio ID',
            'Transaction Type',
            'Quantity',
            'Cost Basis',
            'Sale Price',
            'Split',
            'Date',
            'Created',
            'Updated',
            'Company Name',
            'Portfolio Title',
            'Market Value',
            '52 Week Low',
            '52 Week High',
            'Market Data Refresh Date',
            'Gain/Loss Dollars'
        ];
    }

    /**
    * @return \Illuminate\Support\Collection
    */
    public function collection()
    {
        return auth()->user()->transactions;
    }

     /**
     * @return string
     */
    public function title(): string
    {
        return 'Transactions';
    }
}
