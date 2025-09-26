<?php

declare(strict_types=1);

namespace App\Rules;

use App\Models\Portfolio;
use App\Models\Transaction;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Support\Carbon;

class QuantityValidationRule implements ValidationRule
{
    /**
     * Create a new rule instance.
     *
     * @return void
     */
    public function __construct(
        protected ?Portfolio $portfolio,
        protected ?string $symbol,
        protected ?string $transactionType,
        protected string|Carbon|null $date,
        protected ?Transaction $transaction
    ) {}

    /**
     * Validate the attribute.
     */
    public function validate(string $attribute, mixed $value, \Closure $fail): void
    {
        if (is_null($this->portfolio) || is_null($this->symbol) || is_null($this->transactionType) || is_null($this->date)) {
            //
            $fail(__('The quantity must not be greater than the available quantity.'));
        }

        if ($this->transactionType == 'SELL') {

            $purchase_qty = (float) $this->portfolio->transactions()
                ->symbol($this->symbol)
                ->buy()
                ->whereDate('date', '<', $this->date)
                ->sum('quantity');

            $sales_qty = (float) $this->portfolio->transactions()
                ->where('id', '!=', $this->transaction?->id)
                ->symbol($this->symbol)
                ->sell()
                ->whereDate('date', '<', $this->date)
                ->sum('quantity');

            $maxQuantity = $purchase_qty - $sales_qty;

            if (round($value, 4) > round($maxQuantity, 4)) {
                $fail(__('The quantity must not be greater than the available quantity.'));
            }
        }
    }
}
