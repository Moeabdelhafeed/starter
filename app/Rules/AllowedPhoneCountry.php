<?php

namespace App\Rules;

use App\Helpers\Trans;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Translation\PotentiallyTranslatedString;
use libphonenumber\NumberParseException;
use libphonenumber\PhoneNumberUtil;

class AllowedPhoneCountry implements ValidationRule
{
    protected array $allowedCountries;

    public function __construct(string $countries)
    {
        $this->allowedCountries = array_map('trim', array_map('strtoupper', explode(',', $countries)));
    }

    /**
     * Run the validation rule.
     *
     * @param  Closure(string, ?string=): PotentiallyTranslatedString  $fail
     */
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (empty($value)) {
            return;
        }

        // Check if phone contains only digits (no + or other characters)
        if (! preg_match('/^\d+$/', $value)) {
            $fail(Trans::get('api.phone_numbers_only'));

            return;
        }

        // If countries are "all", just validate it's digits (already done above)
        if (count($this->allowedCountries) === 1 && $this->allowedCountries[0] === 'ALL') {
            return;
        }

        // Try to validate phone against each allowed country
        $phoneUtil = PhoneNumberUtil::getInstance();
        $validForCountry = false;

        foreach ($this->allowedCountries as $country) {
            try {
                $phoneNumber = $phoneUtil->parse($value, $country);
                if ($phoneUtil->isValidNumberForRegion($phoneNumber, $country)) {
                    $validForCountry = true;
                    break;
                }
            } catch (NumberParseException $e) {
                // Continue to next country
            }
        }

        if (! $validForCountry) {
            $fail(Trans::get('api.allowed_phone_country', ['countries' => implode(', ', $this->allowedCountries)]));
        }
    }
}
