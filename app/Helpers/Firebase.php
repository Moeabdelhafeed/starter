<?php

namespace App\Helpers;

use Illuminate\Support\Facades\Log;
use Kreait\Firebase\Contract\Auth as FirebaseAuth;
use Kreait\Firebase\Contract\Messaging;
use Throwable;

/**
 * Firebase is optional. A project may ship with no service-account JSON at all —
 * `FIREBASE_CREDENTIALS` unset, or the file simply absent on a fresh server — and the
 * rest of the app has to keep working: only social login and push notifications go
 * quiet, everything else behaves normally.
 *
 * Resolving Kreait's contracts throws when the credentials file is missing, so nothing
 * may type-hint them directly (method injection would 500 the request before the body
 * ever runs). Ask this helper instead and handle the null.
 */
class Firebase
{
    private static ?bool $available = null;

    /**
     * Whether a usable Firebase project is configured. Resolved once per process —
     * the answer cannot change mid-request, and the check touches the filesystem.
     */
    public static function available(): bool
    {
        if (self::$available !== null) {
            return self::$available;
        }

        $credentials = config('firebase.projects.'.config('firebase.default').'.credentials');

        if (blank($credentials)) {
            return self::$available = false;
        }

        // Kreait accepts a path, a JSON string, or an already-decoded array.
        if (is_string($credentials) && ! str_starts_with(trim($credentials), '{')) {
            $path = str_starts_with($credentials, '/') ? $credentials : base_path($credentials);

            if (! is_file($path)) {
                return self::$available = false;
            }
        }

        try {
            app(FirebaseAuth::class);

            return self::$available = true;
        } catch (Throwable $e) {
            Log::warning('Firebase unavailable — social auth and push are disabled.', ['error' => $e->getMessage()]);

            return self::$available = false;
        }
    }

    /** The Auth client, or null when Firebase is not configured. */
    public static function auth(): ?FirebaseAuth
    {
        return self::available() ? app(FirebaseAuth::class) : null;
    }

    /** The Messaging client, or null when Firebase is not configured. */
    public static function messaging(): ?Messaging
    {
        if (! self::available()) {
            return null;
        }

        try {
            return app(Messaging::class);
        } catch (Throwable $e) {
            Log::warning('Firebase messaging unavailable.', ['error' => $e->getMessage()]);

            return null;
        }
    }

    /** Test seam — forget the cached answer after credentials are added or removed. */
    public static function forget(): void
    {
        self::$available = null;
    }
}
