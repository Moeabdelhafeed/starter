<?php

namespace App\Helpers;

use libphonenumber\NumberParseException;
use libphonenumber\PhoneNumberFormat;
use libphonenumber\PhoneNumberUtil;

/**
 * One canonical phone format across the API: E.164 (`+966501234567`).
 *
 * The country code is always the caller's responsibility — a number is parsed as an
 * international number and never against a default region, so `0501234567` and
 * `501234567` are rejected rather than silently resolved into some country. That is
 * what keeps one human from ending up as three rows: every write and every lookup
 * runs through normalize() first.
 */
class PhoneNumber
{
    /**
     * E.164 form of an international number, or null when the input is not one
     * (missing country code, wrong length, not a real number).
     */
    public static function normalize(?string $value): ?string
    {
        $value = preg_replace('/[\s\-().]/', '', trim((string) $value));

        if (! preg_match('/^\+?[0-9]{8,15}$/', $value)) {
            return null;
        }

        try {
            // Region null: the number MUST carry its own country code to parse.
            $number = PhoneNumberUtil::getInstance()->parse(str_starts_with($value, '+') ? $value : '+'.$value, null);
        } catch (NumberParseException) {
            return null;
        }

        $util = PhoneNumberUtil::getInstance();

        return $util->isValidNumber($number) ? $util->format($number, PhoneNumberFormat::E164) : null;
    }

    /**
     * ISO country code (e.g. `SA`) a normalized number belongs to, or null.
     */
    public static function region(string $e164): ?string
    {
        try {
            return PhoneNumberUtil::getInstance()->getRegionCodeForNumber(
                PhoneNumberUtil::getInstance()->parse($e164, null)
            );
        } catch (NumberParseException) {
            return null;
        }
    }

    /**
     * The configured allow-list, uppercased. `['ALL']` means unrestricted.
     *
     * @return array<int, string>
     */
    public static function allowedCountries(): array
    {
        return array_map('trim', array_map('strtoupper', explode(',', (string) config('auth.allowed_phone_countries'))));
    }
}
