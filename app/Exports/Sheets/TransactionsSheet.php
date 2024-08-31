<?php

namespace App\Exports\Sheets;

use App\Models\Transaction;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\FromCollection;

class TransactionsSheet implements FromCollection, WithHeadings, WithTitle
{
    public function __construct(
        public bool $empty = false
    ) { }

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
            'Updated'
        ];
    }

    /**
    * @return \Illuminate\Support\Collection
    */
    public function collection()
    {
        return $this->empty ? collect() : Transaction::myTransactions()->get();
    }

     /**
     * @return string
     */
    public function title(): string
    {
        return 'Transactions';
    }
}
