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
        protected string $transactionType,
        protected string $date
    ) {
        $this->portfolio = $portfolio;
        $this->symbol = $symbol; 
        $this->transactionType = $transactionType;
        $this->date = $date;
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

            $purchase_qty = $this->portfolio->transactions()
                                        ->symbol($this->symbol)
                                        ->buy()
                                        ->beforeDate($this->date)
                                        ->sum('quantity');

            $sales_qty = $this->portfolio->transactions()
                                        ->symbol($this->symbol)
                                        ->sell()
                                        ->beforeDate($this->date)
                                        ->sum('quantity');
   
            $maxQuantity = $purchase_qty - $sales_qty;

            if ($value > $maxQuantity) {
                $fail(__('The quantity must not be greater than the available quantity.'));
            }
        }
    }
}
