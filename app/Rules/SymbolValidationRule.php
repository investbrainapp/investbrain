<?php

declare(strict_types=1);

namespace App\Rules;

use App\Interfaces\MarketData\MarketDataInterface;
use App\Models\MarketData;
use Illuminate\Contracts\Validation\ValidationRule;

class SymbolValidationRule implements ValidationRule
{
    public $symbol;

    /**
     * Create a new rule instance.
     *
     * @return void
     */
    public function __construct()
    {
        //
    }

    /**
     * Validate the attribute.
     */
    public function validate(string $attribute, mixed $value, \Closure $fail): void
    {
        $this->symbol = $value;

        // Check if the symbol exists in the Market Data table first (avoid API call)
        if (MarketData::find($this->symbol)) {

            return;
        }

        // Then check against market data provider
        if (! app(MarketDataInterface::class)->exists($value)) {
            $fail('The symbol provided ('.$this->symbol.') is not valid');
        }
    }
}
