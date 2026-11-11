<?php

declare(strict_types=1);

namespace RoundlyConsulting\Addresses\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Translation\PotentiallyTranslatedString;
use RoundlyConsulting\Addresses\Enums\AddressType;
use RoundlyConsulting\Addresses\Support\AddressTypes;

/**
 * Passes a type configured in `addresses.types` (trimmed, case-insensitive), so a form
 * request refuses exactly what the package would:
 *
 *     'type' => ['required', new ValidAddressType],
 */
final class ValidAddressType implements ValidationRule
{
    /**
     * @param  Closure(string, ?string=): PotentiallyTranslatedString  $fail
     */
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (($value instanceof AddressType || is_string($value)) && AddressTypes::allows($value)) {
            return;
        }

        $fail('addresses::validation.type')->translate();
    }
}
