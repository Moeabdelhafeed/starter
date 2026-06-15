<?php

namespace App\Helpers;

use App\Models\Language;
use App\Models\TranslationKey;
use Illuminate\Support\Facades\Cache;

class Trans
{
    /**
     * Get a translation from the database.
     *
     * @param  string  $key  The translation key in format 'group.key' (e.g., 'api.login_successful')
     * @param  array<string, string>  $replace  Replacements for placeholders
     * @param  string|null  $locale  The locale to use (defaults to app locale)
     */
    public static function get(string $key, array $replace = [], ?string $locale = null): string
    {
        $locale = $locale ?? app()->getLocale();
        [$group, $keyName] = self::parseKey($key);

        $cacheKey = "trans.{$group}.{$keyName}.{$locale}";

        $translation = Cache::remember($cacheKey, 600, function () use ($group, $keyName, $locale) {
            $translationKey = TranslationKey::where('key', $keyName)
                ->where('group', $group)
                ->with(['values' => fn ($q) => $q->where('locale', $locale)])
                ->first();

            if ($translationKey && $translationKey->values->first()) {
                return $translationKey->values->first()->value;
            }

            // Fallback: Try default language
            $defaultLang = Language::where('is_default', true)->first();
            if ($defaultLang && $defaultLang->code !== $locale) {
                $translationKey = TranslationKey::where('key', $keyName)
                    ->where('group', $group)
                    ->with(['values' => fn ($q) => $q->where('locale', $defaultLang->code)])
                    ->first();

                if ($translationKey && $translationKey->values->first()) {
                    return $translationKey->values->first()->value;
                }
            }

            // Final fallback: Use file-based translation
            return null;
        });

        // If no database translation found, fallback to file
        if ($translation === null) {
            $translation = __("{$group}.{$keyName}");
        }

        // Apply replacements
        if (! empty($replace)) {
            foreach ($replace as $search => $replacement) {
                $translation = str_replace(":{$search}", $replacement, $translation);
            }
        }

        return $translation;
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

    /**
     * Clear the translation cache.
     */
    public static function clearCache(?string $group = null, ?string $key = null, ?string $locale = null): void
    {
        if ($group && $key && $locale) {
            Cache::forget("trans.{$group}.{$key}.{$locale}");

            return;
        }

        // Clear all translation cache by pattern
        // Since Cache::forget doesn't support patterns, we need to clear specific keys
        // For simplicity, we'll clear all cached translations when bulk clearing
        $languages = Language::pluck('code')->toArray();
        $groups = ['api', 'admin', 'custom'];

        $keys = TranslationKey::when($group, fn ($q) => $q->where('group', $group))
            ->pluck('key')
            ->toArray();

        foreach ($keys as $keyName) {
            foreach ($languages as $lang) {
                foreach ($groups as $g) {
                    if (! $group || $g === $group) {
                        Cache::forget("trans.{$g}.{$keyName}.{$lang}");
                    }
                }
            }
        }
    }

    /**
     * Clear all translation cache for a specific locale.
     */
    public static function clearLocaleCache(string $locale): void
    {
        $groups = ['api', 'admin', 'custom'];
        $keys = TranslationKey::pluck('key')->toArray();

        foreach ($keys as $keyName) {
            foreach ($groups as $group) {
                Cache::forget("trans.{$group}.{$keyName}.{$locale}");
            }
        }
    }
}
