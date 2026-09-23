<?php

namespace App\Helpers;

use App\Models\Language;

/**
 * Typed access to the FCM topic registry. The base topics live in the `FCM_TOPICS` env var
 * as a comma-separated list, and each one gets a per-language variant `{base}_{lang_code}`
 * derived from active rows in the `languages` table. Devices subscribe to variants only.
 *
 * A template targets a variant (that language alone), a base (that audience, every
 * language), or the `all` option (every audience, every language) — which is not a topic.
 */
class FcmTopics
{
    /**
     * Not a topic — an option in the notification-template picker meaning "every base, in
     * every language". Nothing subscribes to it and nothing is ever sent to it: choosing it
     * fans out to `users_en`, `users_ar`, `guests_en`, `guests_ar` and so on
     * (`sendTargets()`). It is listed in `structured()` so the CMS can offer it, and
     * deliberately absent from `published()`, `bases()` and `all()`.
     */
    public const ALL = 'all';

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
        $csv = config('features.fcm_topics', implode(',', self::DEFAULTS));
        $list = array_values(array_filter(array_map('trim', explode(',', (string) $csv))));
        // `all` is an option, not a base: an admin who typed it into FCM_TOPICS would
        // otherwise get `all_en`/`all_ar` topics nobody subscribes to.
        $list = array_values(array_filter($list, fn (string $name) => $name !== self::ALL));

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

        // The picker's first choice: everyone, in every language. `lang => null` keeps it
        // out of `published()`, which is what devices subscribe from.
        $out = [['name' => self::ALL, 'base' => self::ALL, 'lang' => null]];

        foreach ($bases as $base) {
            $out[] = ['name' => $base, 'base' => $base, 'lang' => null];
            foreach ($codes as $code) {
                $out[] = ['name' => $base.'_'.$code, 'base' => $base, 'lang' => $code];
            }
        }

        return $out;
    }

    /**
     * Split a topic into its base and language: `users_ar` → `['users', 'ar']`, `users` →
     * `['users', null]`. Matched against the configured bases rather than by splitting on
     * the last underscore, because a base may legitimately contain one (`new_arrivals`).
     */
    public static function parse(string $topic): array
    {
        foreach (self::bases() as $base) {
            if ($topic === $base) {
                return [$base, null];
            }

            foreach (self::activeLanguageCodes() as $code) {
                if ($topic === $base.'_'.$code) {
                    return [$base, $code];
                }
            }
        }

        return [$topic, null];
    }

    /**
     * Where a send to `$topic` actually goes, as `[topic => language code]`.
     *
     * A base topic is a shorthand for "everybody, in their own language", so it fans out to
     * every language variant — firing `all` sends `all_en` AND `all_ar`, each with that
     * language's copy. Devices subscribe to variants only (see `published()`), so sending to
     * the bare base would reach nobody at all.
     *
     * A topic that already names a language sends once, to itself. With no active languages
     * configured there are no variants, so the base is the only thing left to send to.
     *
     * @return array<string, string|null>
     */
    public static function sendTargets(string $topic): array
    {
        $codes = self::activeLanguageCodes();

        // The picker's "all": every base, in every language. It is the only value here that
        // is not a topic itself, which is why it cannot simply fan out to its own variants.
        if ($topic === self::ALL) {
            $targets = [];

            foreach (self::bases() as $base) {
                foreach ($codes as $code) {
                    $targets[$base.'_'.$code] = $code;
                }

                if ($codes === []) {
                    $targets[$base] = null;
                }
            }

            return $targets;
        }

        [$base, $lang] = self::parse($topic);

        if ($lang !== null) {
            return [$topic => $lang];
        }

        $targets = [];

        foreach ($codes as $code) {
            $targets[$base.'_'.$code] = $code;
        }

        return $targets ?: [$base => null];
    }

    /**
     * What a client is told to subscribe to: the language variants only.
     *
     * The bare base is deliberately withheld, and so is the `all` option. A device has one
     * language at a time, so subscribing to both `users` and `users_ar` would deliver every
     * broadcast twice, and `all` is not a topic at all. Bases stay what an admin picks in
     * the CMS, meaning "this audience, in their own language"; `all` means every audience.
     *
     * @return array<int, array{name: string, base: string, lang: ?string}>
     */
    public static function published(): array
    {
        $variants = array_values(array_filter(self::structured(), fn (array $row) => $row['lang'] !== null));

        // An install with no active languages has no variants to offer; the bases are then
        // the only topics that exist, and sendTargets() delivers to them directly. The `all`
        // option is still not one of them — nothing can subscribe to it.
        return $variants ?: array_values(array_filter(self::structured(), fn (array $row) => $row['base'] !== self::ALL));
    }

    /**
     * Everything a notification template may be aimed at: the `all` option, every base, and
     * every language variant. Wider than `all()`, which lists only topics that really exist.
     *
     * @return array<int, string>
     */
    public static function selectable(): array
    {
        return array_values(array_unique([self::ALL, ...self::all()]));
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
            return Language::activeCodes();
        } catch (\Throwable $e) {
            return [];
        }
    }
}
