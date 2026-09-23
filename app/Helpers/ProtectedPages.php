<?php

namespace App\Helpers;

/**
 * The page slugs this install may never lose.
 *
 * A mobile build links to Terms and Privacy by slug, so a page deleted from the CMS is a
 * dead screen in a shipped binary that no server-side fix can reach. The list lives in
 * `PROTECTED_PAGES` and is edited from DevSettings, not per row in the CMS: protection is
 * a property of what the app depends on, and an admin who could untick it could delete
 * the page a moment later.
 *
 * `PageSeeder` creates every slug listed here that is missing, so enabling one is enough
 * to make it exist.
 */
class ProtectedPages
{
    /**
     * Slug pattern — same as the CMS accepts for a page.
     */
    public const SLUG_REGEX = '/^[a-z0-9_-]+$/';

    /**
     * @return array<int, string>
     */
    public static function slugs(): array
    {
        $csv = (string) config('features.protected_pages', '');

        // An empty list means nothing is protected. Unlike FCM topics there is no
        // fallback to defaults: clearing the list in DevSettings has to actually clear it.
        return array_values(array_unique(array_filter(array_map('trim', explode(',', $csv)))));
    }

    public static function has(string $slug): bool
    {
        return in_array($slug, self::slugs(), true);
    }
}
