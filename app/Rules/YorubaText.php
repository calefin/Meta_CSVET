<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * Allows letters/marks from any script (including Yoruba diacritics: ẹ ọ ṣ n̄ etc.)
 * plus spaces, hyphens, apostrophes and periods. Used instead of Laravel's `alpha`
 * rule, which rejects accented/diacritic characters (§3.3 of the spec).
 */
class YorubaText implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_string($value) || ! preg_match('/^[\pL\pM\s\-\'\.]+$/u', $value)) {
            $fail('O campo :attribute contem caracteres invalidos.');
        }
    }
}
