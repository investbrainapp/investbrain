<?php

namespace App\Imports\Sheets;

use App\Models\Holding;
use App\Models\Transaction;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\SkipsEmptyRows;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use Maatwebsite\Excel\Concerns\WithValidation;
use Maatwebsite\Excel\Concerns\WithChunkReading;

class TransactionsSheet implements ToCollection, WithHeadingRow, WithValidation, SkipsEmptyRows, WithChunkReading
{
    public function collection(Collection $transactions)
    {
        Transaction::withoutEvents(function () use ($transactions) {

            foreach ($transactions->sortBy('date') as $transaction) {

                $transaction = Transaction::where('id', $transaction['transaction_id'])
                        ->firstOr(function () use ($transaction) {

                            return Transaction::make()->forceFill([
                                'id' => $transaction['transaction_id'],
                                'symbol' => $transaction['symbol'],
                                'portfolio_id' => $transaction['portfolio_id'],
                                'transaction_type' => $transaction['transaction_type'],
                                'quantity' => $transaction['quantity'],
                                'cost_basis' => $transaction['cost_basis'] ?? 0,
                                'sale_price' => $transaction['sale_price'],
                                'split' => $transaction['split'] ?? null,
                                'date' => $transaction['date'],
                            ])->save();
                        });

                Holding::firstOrCreate([
                    'symbol' => $transaction['symbol'],
                    'portfolio_id' => $transaction['portfolio_id'],
                ], [
                    'quantity' => 0,
                    'average_cost_basis' => 0,
                    'total_cost_basis' => 0,
                    'realized_gain_dollars' => 0,
                    'dividends_earned' => 0
                ]);
            }
        });
    }

    public function rules(): array
    {
        return [
            'transaction_id' => ['sometimes', 'nullable'],
            'symbol' => ['required', 'string'],
            'portfolio_id' => ['required', 'exists:portfolios,id'],
            'quantity' => ['required', 'min:0', 'numeric'],
            'transaction_type' => ['required', 'in:BUY,SELL'],
            'date' => ['required', 'date'],
            'quantity' => ['required', 'min:0', 'numeric'],
            'cost_basis' => ['sometimes', 'nullable', 'min:0', 'numeric'],
            'sale_price' => ['sometimes', 'nullable', 'min:0', 'numeric'],
        ];
    }

    public function chunkSize(): int
    {
        return 500;
    }
}
