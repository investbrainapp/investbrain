<?php

declare(strict_types=1);

namespace App\Exports\Sheets;

use App\Models\Transaction;
use Illuminate\Database\Eloquent\Builder;
use Maatwebsite\Excel\Concerns\FromQuery;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithTitle;

class TransactionsSheet implements FromQuery, WithHeadings, WithMapping, WithTitle
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
     * Uses a sub-select for the currency (rather than joining market data) so transactions
     * without market data are still included in the backup.
     */
    public function query(): Builder
    {
        return Transaction::myTransactions()
            ->when($this->empty, fn ($query) => $query->whereRaw('1 = 0'))
            ->withAggregate('market_data', 'currency')
            ->orderBy('date')
            ->orderBy('id');
    }

    /**
     * @param  Transaction  $transaction
     */
    public function map($transaction): array
    {
        return [
            $transaction->id,
            $transaction->symbol,
            $transaction->portfolio_id,
            $transaction->transaction_type,
            $transaction->quantity,
            $transaction->cost_basis,
            $transaction->sale_price,
            $transaction->market_data_currency,
            $transaction->split,
            $transaction->reinvested_dividend,
            date_format($transaction->date, 'Y-m-d'),
            $transaction->created_at,
            $transaction->updated_at,
        ];
    }

    public function title(): string
    {
        return 'Transactions';
    }
}
