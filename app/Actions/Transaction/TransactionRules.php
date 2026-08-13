<?php

declare(strict_types=1);

namespace App\Actions\Transaction;

use App\Models\Portfolio;
use App\Models\Transaction;
use App\Rules\QuantityValidationRule;
use App\Rules\SymbolValidationRule;

class TransactionRules
{
    /**
     * Build the validation rules for creating or updating a transaction. Shared by
     * CreateTransaction and UpdateTransaction so both enforce identical rules.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, array<int, mixed>>
     */
    public function build(array $data, ?Portfolio $portfolio, ?Transaction $transaction = null): array
    {
        $rules = [
            'portfolio_id' => ['required', 'exists:portfolios,id'],
            'symbol' => ['required', 'string', new SymbolValidationRule],
            'transaction_type' => ['required', 'string', 'in:BUY,SELL'],
            'date' => ['required', 'date_format:Y-m-d', 'before_or_equal:'.now()->toDateString()],
            'quantity' => [
                'required',
                'numeric',
                'gt:0',
                new QuantityValidationRule(
                    $portfolio,
                    $data['symbol'] ?? $transaction?->symbol,
                    $data['transaction_type'] ?? $transaction?->transaction_type,
                    $data['date'] ?? $transaction?->date,
                    $transaction,
                ),
            ],
            'currency' => ['required', 'exists:currencies,currency'],
            'cost_basis' => ['exclude_if:transaction_type,SELL', 'min:0', 'numeric'],
            'sale_price' => ['exclude_if:transaction_type,BUY', 'min:0', 'numeric'],
        ];

        if ($transaction !== null) {
            $rules['portfolio_id'][0] = 'sometimes';
            $rules['symbol'][0] = 'sometimes';
            $rules['transaction_type'][0] = 'sometimes';
            $rules['currency'][0] = 'sometimes';
            $rules['date'][0] = 'sometimes';
            $rules['quantity'][0] = 'sometimes';

            $transactionType = $data['transaction_type'] ?? $transaction->transaction_type;

            if ($transactionType === 'SELL' && ($data['sale_price'] ?? $transaction->sale_price) === null) {
                $rules['sale_price'][0] = 'required';
            } elseif ($transactionType === 'BUY' && ($data['cost_basis'] ?? $transaction->cost_basis) === null) {
                $rules['cost_basis'][0] = 'required';
            }
        }

        return $rules;
    }
}
