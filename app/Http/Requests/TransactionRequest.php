<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Models\Portfolio;
use App\Rules\QuantityValidationRule;
use App\Rules\SymbolValidationRule;

class TransactionRequest extends FormRequest
{
    protected function prepareForValidation(): void
    {

        $this->merge([
            'portfolio' => Portfolio::find($this->requestOrModelValue('portfolio_id', 'transaction')),
        ]);
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
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
                    $this->input('portfolio'),
                    $this->requestOrModelValue('symbol', 'transaction'),
                    $this->requestOrModelValue('transaction_type', 'transaction'),
                    $this->requestOrModelValue('date', 'transaction')
                ),
            ],
            'cost_basis' => ['exclude_if:transaction_type,SELL', 'min:0', 'numeric'],
            'sale_price' => ['exclude_if:transaction_type,BUY', 'min:0', 'numeric'],
        ];

        if (! is_null($this->transaction)) {
            $rules['portfolio_id'][0] = 'sometimes';
            $rules['symbol'][0] = 'sometimes';
            $rules['transaction_type'][0] = 'sometimes';
            $rules['date'][0] = 'sometimes';
            $rules['quantity'][0] = 'sometimes';

            if (
                $this->requestOrModelValue('transaction_type', 'transaction') == 'SELL'
                && $this->requestOrModelValue('sale_price', 'transaction') == null
            ) {
                $rules['sale_price'][0] = 'required';
            } elseif (
                $this->requestOrModelValue('transaction_type', 'transaction') == 'BUY'
                && $this->requestOrModelValue('cost_basis', 'transaction') == null
            ) {
                $rules['cost_basis'][0] = 'required';
            }
        }

        return $rules;
    }
}
