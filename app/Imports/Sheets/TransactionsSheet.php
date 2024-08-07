<?php

namespace App\Imports\Sheets;

use App\Models\Transaction;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\SkipsEmptyRows;
use Maatwebsite\Excel\Concerns\WithHeadingRow;

class TransactionsSheet implements ToCollection, WithHeadingRow, SkipsEmptyRows
{
    // use Importable;

    public function collection(Collection $transactions)
    {
        foreach ($transactions->sortBy('date') as $row) {

            Transaction::updateOrCreate([
                'id' => $row['id'],
            ],[
                'id' => $row['id'],
                'symbol' => $row['symbol'],
                'portfolio_id' => $row['portfolio'],
                'transaction_type' => $row['transaction'],
                'quantity' => $row['quantity'],
                'cost_basis' => $row['cost_basis'] ?? 0,
                'sale_price' => $row['sale_price'],
                'split' => $row['split'] ?? null,
                'date' => $row['date'],
            ]);
        }
    }
}
