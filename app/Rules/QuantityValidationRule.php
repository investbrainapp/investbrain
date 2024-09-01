<?php

namespace App\Rules;

use App\Models\Portfolio;
use Illuminate\Contracts\Validation\ValidationRule;

class QuantityValidationRule implements ValidationRule
{
    /**
     * Create a new rule instance.
     *
     * @return void
     */
    public function __construct(
        protected Portfolio $portfolio, 
        protected string $symbol, 
        protected string $transactionType
    ) {
        $this->portfolio = $portfolio;
        $this->symbol = $symbol;
        $this->transactionType = $transactionType;
    }

    /**
     * Validate the attribute.
     *
     * @param  string  $attribute
     * @param  mixed  $value
     * @param  \Closure  $fail
     * @return void
     */
    public function validate(string $attribute, mixed $value, \Closure $fail): void
    {
        if ($this->transactionType == 'SELL') {
            $holding = $this->portfolio->holdings()->symbol($this->symbol)->first();
            $maxQuantity = $holding ? $holding->quantity : 0;
            
            if ($value > $maxQuantity) {
                $fail(__('The quantity must not be greater than the available quantity.'));
            }
        }
    }
}
