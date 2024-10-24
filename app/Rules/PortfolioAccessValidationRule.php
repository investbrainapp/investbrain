<?php

namespace App\Rules;

use App\Models\Portfolio;
use Illuminate\Contracts\Validation\ValidationRule;

class PortfolioAccessValidationRule implements ValidationRule
{
    public function __construct(
        public ?string $user_id
    ) { }
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
        if (!Portfolio::fullAccess($this->user_id)->where('id', $value)->count()) {
            $fail(__('You do not have access to that portfolio.'));
        }
    }
}
