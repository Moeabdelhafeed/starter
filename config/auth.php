<?php

use App\Models\User;

return [

    /*
    |--------------------------------------------------------------------------
    | Authentication Defaults
    |--------------------------------------------------------------------------
    |
    | This option defines the default authentication "guard" and password
    | reset "broker" for your application. You may change these values
    | as required, but they're a perfect start for most applications.
    |
    */

    'defaults' => [
        'guard' => env('AUTH_GUARD', 'web'),
        'passwords' => env('AUTH_PASSWORD_BROKER', 'users'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Authentication Guards
    |--------------------------------------------------------------------------
    |
    | Next, you may define every authentication guard for your application.
    | Of course, a great default configuration has been defined for you
    | which utilizes session storage plus the Eloquent user provider.
    |
    | All authentication guards have a user provider, which defines how the
    | users are actually retrieved out of your database or other storage
    | system used by the application. Typically, Eloquent is utilized.
    |
    | Supported: "session"
    |
    */

    'guards' => [
        'web' => [
            'driver' => 'session',
            'provider' => 'users',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | User Providers
    |--------------------------------------------------------------------------
    |
    | All authentication guards have a user provider, which defines how the
    | users are actually retrieved out of your database or other storage
    | system used by the application. Typically, Eloquent is utilized.
    |
    | If you have multiple user tables or models you may configure multiple
    | providers to represent the model / table. These providers may then
    | be assigned to any extra authentication guards you have defined.
    |
    | Supported: "database", "eloquent"
    |
    */

    'providers' => [
        'users' => [
            'driver' => 'eloquent',
            'model' => env('AUTH_MODEL', User::class),
        ],

        // 'users' => [
        //     'driver' => 'database',
        //     'table' => 'users',
        // ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Resetting Passwords
    |--------------------------------------------------------------------------
    |
    | These configuration options specify the behavior of Laravel's password
    | reset functionality, including the table utilized for token storage
    | and the user provider that is invoked to actually retrieve users.
    |
    | The expiry time is the number of minutes that each reset token will be
    | considered valid. This security feature keeps tokens short-lived so
    | they have less time to be guessed. You may change this as needed.
    |
    | The throttle setting is the number of seconds a user must wait before
    | generating more password reset tokens. This prevents the user from
    | quickly generating a very large amount of password reset tokens.
    |
    */

    'passwords' => [
        'users' => [
            'provider' => 'users',
            'table' => env('AUTH_PASSWORD_RESET_TOKEN_TABLE', 'password_reset_tokens'),
            'expire' => 60,
            'throttle' => 60,
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Password Confirmation Timeout
    |--------------------------------------------------------------------------
    |
    | Here you may define the number of seconds before a password confirmation
    | window expires and users are asked to re-enter their password via the
    | confirmation screen. By default, the timeout lasts for three hours.
    |
    */

    'password_timeout' => env('AUTH_PASSWORD_TIMEOUT', 10800),

    /*
    |--------------------------------------------------------------------------
    | Account Deletion Retention
    |--------------------------------------------------------------------------
    |
    | Days a user-initiated soft deletion (account_deleted_at) is retained
    | before the PurgeDeletedUsersAfterResponse middleware permanently
    | force-deletes the row.
    |
    */

    'account_deletion_retention_days' => (int) env('ACCOUNT_DELETION_RETENTION_DAYS', 30),

    /*
    |--------------------------------------------------------------------------
    | Multi-Session Mode
    |--------------------------------------------------------------------------
    |
    | true  → users may keep multiple Sanctum tokens (one per device). Each
    |          login adds a new `user_devices` row. Devices API + UI exposed.
    | false → every login revokes the user's prior tokens and broadcasts a
    |          `device.revoked` Pusher event so kicked clients clear their
    |          local token. Single active session per user.
    |
    */

    'multi_session_enabled' => filter_var(env('MULTI_SESSION_ENABLED', true), FILTER_VALIDATE_BOOLEAN),

    /*
    |--------------------------------------------------------------------------
    | Mobile API auth configuration
    |--------------------------------------------------------------------------
    |
    | Read via config('auth.*') — never env() — so values survive config:cache.
    | See the mobile-auth-identity skill for what each flag does.
    |
    */

    // 'password' (register/login/forgot-password) or 'otp' (identifier-only login + verify-login).
    'mode' => strtolower((string) env('AUTH_MODE', 'password')) === 'otp' ? 'otp' : 'password',

    // Which identifiers a user may log in with: subset of ['email', 'phone'].
    'identifiers' => array_values(array_filter(array_map('trim', explode(',', (string) env('AUTH_IDENTIFIERS', 'email'))))),

    // Extra profile fields enabled even when they are not identifiers.
    'fields' => [
        'email' => filter_var(env('HAS_EMAIL_FIELD', true), FILTER_VALIDATE_BOOLEAN),
        'phone' => filter_var(env('HAS_PHONE_FIELD', false), FILTER_VALIDATE_BOOLEAN),
        'username' => filter_var(env('HAS_USERNAME_FIELD', false), FILTER_VALIDATE_BOOLEAN),
    ],

    // Deliver OTPs over WhatsApp instead of SMS (phone identifier only).
    'otp_whatsapp' => filter_var(env('IS_OTP_WHATSAPP', false), FILTER_VALIDATE_BOOLEAN),

    // Comma-separated Firebase provider ids (google.com, apple.com, ...). Empty = all allowed.
    'social_providers' => env('SOCIAL_AUTH_PROVIDERS', ''),

    // Max linked social accounts per user. 0 = unlimited.
    'social_max_accounts' => (int) env('SOCIAL_AUTH_MAX_ACCOUNTS', 0),

    // 'all' or a comma-separated allow-list.
    'allowed_email_domains' => env('ALLOWED_EMAIL_DOMAINS', 'all'),
    'allowed_phone_countries' => env('ALLOWED_PHONE_COUNTRIES', 'all'),

    // App-store reviewer accounts that bypass the auth/otp rate limiters.
    'reviewer_emails' => array_values(array_filter([
        strtolower(trim((string) env('APPLE_REVIEWER_EMAIL', ''))),
        strtolower(trim((string) env('GOOGLE_REVIEWER_EMAIL', ''))),
    ])),

    // Rate limits: [max attempts, decay minutes].
    'rate_limits' => [
        'api' => [(int) env('RATE_LIMIT_API', 60), (int) env('RATE_LIMIT_API_DECAY', 1)],
        'auth' => [(int) env('RATE_LIMIT_AUTH', 5), (int) env('RATE_LIMIT_AUTH_DECAY', 1)],
        'otp' => [(int) env('RATE_LIMIT_OTP', 3), (int) env('RATE_LIMIT_OTP_DECAY', 5)],
    ],

];
