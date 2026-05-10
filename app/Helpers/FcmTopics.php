<?php

namespace App\Helpers;

/**
 * Typed access to the FCM topic registry. Topics live in the `FCM_TOPICS`
 * env var as a comma-separated list. Built-in constants let app code
 * reference common topics without typo-prone strings; custom topics added
 * via DevSettings show up via `FcmTopics::all()`.
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
     * All topics configured in `FCM_TOPICS` env. Falls back to defaults
     * when the env is empty.
     *
     * @return array<int, string>
     */
    public static function all(): array
    {
        $csv = env('FCM_TOPICS', implode(',', self::DEFAULTS));
        $list = array_values(array_filter(array_map('trim', explode(',', (string) $csv))));

        return $list ?: self::DEFAULTS;
    }

    public static function has(string $topic): bool
    {
        return in_array($topic, self::all(), true);
    }

    public static function isValidName(string $topic): bool
    {
        return preg_match(self::NAME_REGEX, $topic) === 1;
    }
}
