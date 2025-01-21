<?php

namespace aliirfaan\CitronelCommerce\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

class CurrencyCode implements ValidationRule
{
    /**
     * Run the validation rule.
     *
     * @param  \Closure(string): \Illuminate\Translation\PotentiallyTranslatedString  $fail
     */
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        $supportedCurrencyCodes = array_keys(config('citronel.currency.supported'));
        if (!in_array($value, $supportedCurrencyCodes)) {
            $fail('The :attribute is not valid.');
        }
    }
}
