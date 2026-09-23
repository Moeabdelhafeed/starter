<?php

namespace App\Helpers;

use App\Models\Language;
use App\Models\TranslationKey;
use Illuminate\Support\Facades\Cache;

/**
 * DB-backed translations (`Trans::get('api.key')`), cached per key+locale.
 *
 * Every cached entry embeds a version number. Invalidation bumps that single
 * version key instead of forgetting thousands of individual entries, so a
 * translation write costs one cache operation regardless of catalogue size.
 * Misses are cached too (as an empty string) so unknown keys don't hit the DB
 * on every call.
 */
class Trans
{
    private const VERSION_KEY = 'trans.version';

    private const TTL = 3600;

    /**
     * Get a translation from the database, falling back to the default
     * language and finally to the file-based `lang/*` catalogue.
     *
     * @param  string  $key  The translation key in format 'group.key' (e.g., 'api.login_successful')
     * @param  array<string, string>  $replace  Replacements for `:placeholder` tokens
     * @param  string|null  $locale  The locale to use (defaults to app locale)
     */
    public static function get(string $key, array $replace = [], ?string $locale = null): string
    {
        $locale = $locale ?? app()->getLocale();
        [$group, $keyName] = self::parseKey($key);

        $cacheKey = 'trans.'.self::version().".{$group}.{$keyName}.{$locale}";

        $translation = Cache::remember($cacheKey, self::TTL, function () use ($group, $keyName, $locale): string {
            $value = self::lookup($group, $keyName, $locale);

            if ($value === null) {
                $defaultLocale = Language::defaultCode();
                if ($defaultLocale !== $locale) {
                    $value = self::lookup($group, $keyName, $defaultLocale);
                }
            }

            return $value ?? '';
        });

        if ($translation === '') {
            $translation = __("{$group}.{$keyName}");
        }

        foreach ($replace as $search => $replacement) {
            $translation = str_replace(":{$search}", (string) $replacement, $translation);
        }

        return $translation;
    }

    private static function lookup(string $group, string $keyName, string $locale): ?string
    {
        $translationKey = TranslationKey::query()
            ->where('key', $keyName)
            ->where('group', $group)
            ->with(['values' => fn ($q) => $q->where('locale', $locale)])
            ->first();

        return $translationKey?->values->first()?->value;
    }

    /**
     * Parse a key into group and key name.
     *
     * @return array{0: string, 1: string}
     */
    protected static function parseKey(string $key): array
    {
        if (str_contains($key, '.')) {
            $parts = explode('.', $key, 2);

            return [$parts[0], $parts[1]];
        }

        // If no group specified, assume 'custom'
        return ['custom', $key];
    }

    private static function version(): int
    {
        return (int) Cache::rememberForever(self::VERSION_KEY, fn () => 1);
    }

    /**
     * Invalidate every cached translation. Called automatically from
     * TranslationKey / TranslationValue model events; arguments are accepted
     * for backwards compatibility but the whole catalogue is always bumped.
     */
    public static function clearCache(?string $group = null, ?string $key = null, ?string $locale = null): void
    {
        Cache::forever(self::VERSION_KEY, self::version() + 1);
    }

    /**
     * A translation, or `$fallback` when the catalogue has no entry for the key.
     * `get()` echoes the key back when nothing matches, which is fine for a
     * missing admin string but reads as a bug in an API response.
     *
     * @param  array<string, string>  $replace
     */
    public static function getOr(string $key, string $fallback, array $replace = [], ?string $locale = null): string
    {
        $value = self::get($key, $replace, $locale);

        return ($value === '' || $value === $key) ? $fallback : $value;
    }

    /**
     * Resolve a locale straight from an `Accept-Language` header, falling back to
     * the default language. Needed wherever a message is built outside the request
     * pipeline: an unknown or method-mismatched API route never reaches
     * SetLocaleMiddleware, so `app()->getLocale()` is still the boot default there.
     */
    public static function localeFromHeader(?string $header): string
    {
        $header = trim((string) $header);

        return in_array($header, Language::activeCodes(), true) ? $header : Language::defaultCode();
    }
}
