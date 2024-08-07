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
            'ID',
            'Symbol',
            'Portfolio',
            'Transaction',
            'Quantity',
            'Cost Basis',
            'Sale Price',
            'Split',
            'Date',
            'Created',
            'Updated',
        ];
    }

    /**
    * @return \Illuminate\Support\Collection
    */
    public function collection()
    {
        return Transaction::myTransactions()->get();
    }

     /**
     * @return string
     */
    public function title(): string
    {
        return 'Transactions';
    }
}
