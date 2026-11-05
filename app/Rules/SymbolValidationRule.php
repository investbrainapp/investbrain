<?php

declare(strict_types=1);

namespace App\Rules;

use App\Interfaces\MarketData\MarketDataInterface;
use App\Models\MarketData;
use Illuminate\Contracts\Validation\ValidationRule;

class SymbolValidationRule implements ValidationRule
{
    /**
     * The characters a symbol may contain, e.g. AAPL, BRK.B, BTC-USD, BTC/USD, ^GSPC, EURUSD=X.
     */
    public const PATTERN = '/^[A-Za-z0-9.\-^=:\/_]+$/';

    /**
     * The longest symbol the symbol columns hold.
     */
    public const MAX_LENGTH = 25;

    /**
     * The format checks on their own, for places that look symbols up some other way, like the import.
     *
     * @return list<string>
     */
    public static function formatRules(): array
    {
        return ['max:'.self::MAX_LENGTH, 'regex:'.self::PATTERN];
    }

    /**
     * The message for a symbol that fails the format, keyed for formatRules() on the given attribute.
     *
     * @return array<string, string>
     */
    public static function formatMessages(string $attribute): array
    {
        return [$attribute.'.regex' => self::formatMessage()];
    }

    private static function formatMessage(): string
    {
        return __('This field may only contain letters, numbers and . - ^ = : / _ (no spaces).');
    }

    /**
     * Validate the attribute.
     */
    public function validate(string $attribute, mixed $value, \Closure $fail): void
    {
        // check the format first, so a malformed value never reaches the market data provider
        if (! is_string($value) || mb_strlen($value) > self::MAX_LENGTH) {
            $fail(__('validation.max.string', ['max' => self::MAX_LENGTH]));

            return;
        }

        if (! preg_match(self::PATTERN, $value)) {
            $fail(self::formatMessage());

            return;
        }

        // Check if the symbol exists in the Market Data table first (avoid API call)
        if (MarketData::find($value)) {

            return;
        }

        // Then check against market data provider
        if (! app(MarketDataInterface::class)->exists($value)) {
            $fail('The symbol provided ('.$value.') is not valid');
        }
    }
}
