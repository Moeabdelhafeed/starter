<?php

namespace App\Rules;

use App\Helpers\PhoneNumber;
use App\Helpers\Trans;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Translation\PotentiallyTranslatedString;

/**
 * A phone number must arrive with its country code and be a real number in one of the
 * allowed countries. Callers store PhoneNumber::normalize()'s output, never the raw input.
 */
class AllowedPhoneCountry implements ValidationRule
{
    /** @var array<int, string> */
    protected array $allowedCountries;

    public function __construct(?string $countries = null)
    {
        $this->allowedCountries = $countries === null
            ? PhoneNumber::allowedCountries()
            : array_map('trim', array_map('strtoupper', explode(',', $countries)));
    }

    /**
     * @param  Closure(string, ?string=): PotentiallyTranslatedString  $fail
     */
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (empty($value)) {
            return;
        }

        $e164 = PhoneNumber::normalize((string) $value);

        if ($e164 === null) {
            $fail(Trans::get('api.phone_country_code_required'));

            return;
        }

        if (count($this->allowedCountries) === 1 && $this->allowedCountries[0] === 'ALL') {
            return;
        }

        if (! in_array((string) PhoneNumber::region($e164), $this->allowedCountries, true)) {
            $fail(Trans::get('api.allowed_phone_country', ['countries' => implode(', ', $this->allowedCountries)]));
        }
    }
}
