<?php

namespace App\Helpers;

use App\Rules\AllowedEmailDomain;
use App\Rules\AllowedPhoneCountry;

/**
 * The one place that answers "which identifiers does this project use, and what
 * does a valid one look like?".
 *
 * The same questions were being answered independently by the API controller and
 * by the User model, which is how a field could count as configured in one place
 * and not the other. Everything here reads `config('auth.*')` and holds no state.
 */
class AuthIdentity
{
    /**
     * Configured login identifiers. Limited to email/phone — username is never a
     * primary identifier — and never empty.
     *
     * @return array<int, string>
     */
    public static function identifiers(): array
    {
        $allowed = array_values(array_intersect((array) config('auth.identifiers', ['email']), ['email', 'phone']));

        return $allowed !== [] ? $allowed : ['email'];
    }

    public static function isIdentifier(string $field): bool
    {
        return in_array($field, self::identifiers(), true);
    }

    /**
     * Whether a field exists on this project at all: either it is a login
     * identifier, or it is switched on as an extra via `HAS_*_FIELD`.
     */
    public static function hasField(string $field): bool
    {
        return self::isIdentifier($field) || (bool) config('auth.fields.'.$field);
    }

    /**
     * Identifier kinds a client may declare: the configured login identifiers,
     * plus `username` when that field is enabled. Callers must say which one they
     * are sending — the API never infers it from the string's shape, because
     * inferring is how a number with no country code got silently reinterpreted
     * as a local one.
     *
     * @return array<int, string>
     */
    public static function types(): array
    {
        $types = self::identifiers();

        if (config('auth.fields.username')) {
            $types[] = 'username';
        }

        return array_values(array_unique($types));
    }

    /**
     * Storage/lookup form of a declared identifier: emails lowercased, phones in
     * E.164. Null when the value is not valid for the declared type (e.g. a phone
     * with no country code), which callers surface as a validation error.
     */
    public static function normalize(string $value, string $type): ?string
    {
        $value = trim($value);

        return match ($type) {
            'email' => $value === '' ? null : strtolower($value),
            'phone' => PhoneNumber::normalize($value),
            default => $value === '' ? null : $value,
        };
    }

    /**
     * @return array<int, mixed>
     */
    public static function emailRule(bool $required, ?int $excludeId = null): array
    {
        $rules = [
            $required ? 'required' : 'nullable',
            'string',
            'email',
            'max:255',
            $excludeId ? "unique:users,email,{$excludeId}" : 'unique:users,email',
        ];

        $domains = config('auth.allowed_email_domains');

        if ($domains !== 'all') {
            $rules[] = new AllowedEmailDomain($domains);
        }

        return $rules;
    }

    /**
     * @return array<int, mixed>
     */
    public static function phoneRule(bool $required, ?int $excludeId = null): array
    {
        return [
            $required ? 'required' : 'nullable',
            'string',
            'max:255',
            $excludeId ? "unique:users,phone,{$excludeId}" : 'unique:users,phone',
            new AllowedPhoneCountry(config('auth.allowed_phone_countries')),
        ];
    }

    /**
     * Rules for the given kind, chosen by the declared type.
     *
     * @return array<int, mixed>
     */
    public static function rule(string $kind, bool $required, ?int $excludeId = null): array
    {
        return match ($kind) {
            'email' => self::emailRule($required, $excludeId),
            default => self::phoneRule($required, $excludeId),
        };
    }

    /**
     * Rules used by OTP-mode login. No uniqueness check — the identifier may
     * belong to an existing user, which is exactly the login case. Shape, allowed
     * domain and allowed country still apply.
     *
     * @return array<int, mixed>
     */
    public static function otpLoginRule(string $kind): array
    {
        if ($kind === 'email') {
            $rules = ['required', 'string', 'email', 'max:255'];
            $domains = config('auth.allowed_email_domains');

            if ($domains !== 'all') {
                $rules[] = new AllowedEmailDomain($domains);
            }

            return $rules;
        }

        return ['required', 'string', 'max:255', new AllowedPhoneCountry(config('auth.allowed_phone_countries'))];
    }
}
