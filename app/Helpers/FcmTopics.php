<?php

namespace App\Helpers;

use App\Models\Language;

/**
 * Typed access to the FCM topic registry. Base topics live in the
 * `FCM_TOPICS` env var as a comma-separated list. For each base topic the
 * helper also exposes per-language variants `{base}_{lang_code}` derived
 * from active rows in the `languages` table. Templates can target either a
 * base topic (lang-agnostic broadcast) or a langed variant (single-lang).
 */
class FcmTopics
{
    public const GUESTS = 'guests';

    public const USERS = 'users';

    public const DEFAULTS = [self::GUESTS, self::USERS];

    /**
     * Topic name regex: lowercase alphanumeric, dash, underscore. Matches
     * Firebase's allowed pattern (a subset of `[a-zA-Z0-9-_.~%]`).
     */
    public const NAME_REGEX = '/^[a-z0-9_-]+$/';

    /**
     * Base topics configured in `FCM_TOPICS` env. Falls back to defaults
     * when the env is empty.
     *
     * @return array<int, string>
     */
    public static function bases(): array
    {
        $csv = env('FCM_TOPICS', implode(',', self::DEFAULTS));
        $list = array_values(array_filter(array_map('trim', explode(',', (string) $csv))));

        return $list ?: self::DEFAULTS;
    }

    /**
     * Bases + every `{base}_{lang_code}` variant for active languages.
     * Used by admin UI dropdowns and validation.
     *
     * @return array<int, string>
     */
    public static function all(): array
    {
        $bases = self::bases();
        $codes = self::activeLanguageCodes();
        $expanded = [];
        foreach ($bases as $base) {
            $expanded[] = $base;
            foreach ($codes as $code) {
                $expanded[] = $base.'_'.$code;
            }
        }

        return array_values(array_unique($expanded));
    }

    /**
     * Structured form for admin UI: each entry knows its base + lang. UI uses
     * `lang` to restrict TranslatableInput to that locale (null = all locales).
     *
     * @return array<int, array{name: string, base: string, lang: ?string}>
     */
    public static function structured(): array
    {
        $bases = self::bases();
        $codes = self::activeLanguageCodes();
        $out = [];
        foreach ($bases as $base) {
            $out[] = ['name' => $base, 'base' => $base, 'lang' => null];
            foreach ($codes as $code) {
                $out[] = ['name' => $base.'_'.$code, 'base' => $base, 'lang' => $code];
            }
        }

        return $out;
    }

    public static function has(string $topic): bool
    {
        return in_array($topic, self::all(), true);
    }

    public static function isValidName(string $topic): bool
    {
        return preg_match(self::NAME_REGEX, $topic) === 1;
    }

    /**
     * @return array<int, string>
     */
    private static function activeLanguageCodes(): array
    {
        try {
            return Language::active()->pluck('code')->all();
        } catch (\Throwable $e) {
            return [];
        }
    }
}
