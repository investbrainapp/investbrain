<?php

declare(strict_types=1);

namespace App\Exports\Sheets;

use App\Models\Transaction;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithTitle;

class TransactionsSheet implements FromCollection, WithHeadings, WithTitle
{
    public function __construct(
        public bool $empty = false
    ) {}

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
            'Currency',
            'Split',
            'Reinvested Dividend',
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
        if ($this->empty) {
            return collect();
        }

        return Transaction::myTransactions()
            ->withMarketData()
            ->get()
            ->map(function ($transaction) {
                return [
                    'id' => $transaction->id,
                    'symbol' => $transaction->symbol,
                    'portfolio_id' => $transaction->portfolio_id,
                    'transaction_type' => $transaction->transaction_type,
                    'quantity' => $transaction->quantity,
                    'cost_basis' => $transaction->cost_basis,
                    'sale_price' => $transaction->sale_price,
                    'currency' => $transaction->market_data_currency,
                    'split' => $transaction->split,
                    'reinvested_dividend' => $transaction->reinvested_dividend,
                    'date' => $transaction->date,
                    'created_at' => $transaction->created_at,
                    'updated_at' => $transaction->updated_at,
                ];
            });
    }

    public function title(): string
    {
        return 'Transactions';
    }
}
