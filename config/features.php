<?php

/*
|--------------------------------------------------------------------------
| Feature flags
|--------------------------------------------------------------------------
|
| Every optional module of the starter is toggled here, read from .env once.
| Application code must read these via config('features.*') — never env() —
| so the flags survive `php artisan config:cache` on a deployed server
| (env() returns null once the config is cached).
|
*/

return [

    // Mobile-app user module: API auth routes + the admin "App Users" page.
    'app_users' => filter_var(env('APP_USERS', true), FILTER_VALIDATE_BOOLEAN),

    // Anonymous device-bound guest users (POST /api/guest).
    'app_guests' => filter_var(env('APP_GUESTS', true), FILTER_VALIDATE_BOOLEAN),

    'translations' => filter_var(env('HAS_TRANSLATIONS', true), FILTER_VALIDATE_BOOLEAN),

    'notification_templates' => filter_var(env('HAS_NOTIFICATION_TEMPLATES', true), FILTER_VALIDATE_BOOLEAN),

    'pages' => filter_var(env('HAS_PAGES', true), FILTER_VALIDATE_BOOLEAN),

    'app_settings' => filter_var(env('HAS_APP_SETTINGS', true), FILTER_VALIDATE_BOOLEAN),

    'dynamic_storage' => filter_var(env('HAS_DYNAMIC_STORAGE', true), FILTER_VALIDATE_BOOLEAN),

    'activity_logs' => filter_var(env('HAS_ACTIVITY_LOGS', true), FILTER_VALIDATE_BOOLEAN),

    /*
     * Opens the content-provisioning writes: POST/DELETE on /api/translations and
     * /api/media. Deliberately separate from IS_TESTING, and deliberately allowed on a
     * production flavor: a live install is seeded once (the client app pushes its
     * translations and media into MySQL) and the flag is then turned back off.
     *
     * Defaults to TRUE so every local install and every non-production flavor can seed
     * without being configured first. Production is the one that gets turned off, from the
     * flavor's own override in the Deploy panel, once its content is in.
     *
     * Those endpoints carry no authentication beyond the shared X-API-TOKEN, which ships
     * inside every mobile binary — leaving this on is leaving the content tables writable
     * by anyone who decompiled the app.
     */
    'content_seeding' => filter_var(env('ALLOW_CONTENT_SEEDING', true), FILTER_VALIDATE_BOOLEAN),

    /*
     * The admin assistant (see config/ai.php for the model backend).
     *
     * Off by default: it needs a model backend configured before it can answer
     * anything, and an install that never sets one should not grow a chat panel
     * that only ever errors.
     */

    // Comma-separated page slugs the CMS may never delete (see App\Helpers\ProtectedPages).
    // Seeded by PageSeeder, editable in DevSettings.
    'protected_pages' => env('PROTECTED_PAGES', 'terms,privacy'),

    // Comma-separated FCM topic bases (see App\Helpers\FcmTopics).
    'fcm_topics' => env('FCM_TOPICS', 'guests,users'),

    // Lets `migrate:fresh` / `db:wipe` run in production. The deploy panel sets
    // this inline for the destructive deploy option only.
    'allow_destructive_migrations' => filter_var(env('ALLOW_DESTRUCTIVE_MIGRATIONS', false), FILTER_VALIDATE_BOOLEAN),

];
