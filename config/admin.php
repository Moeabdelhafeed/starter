<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Initial Super Admin Credentials
    |--------------------------------------------------------------------------
    |
    | Used by RoleSeeder to create/update the super admin account. Read via
    | config() (not env()) so the values survive `php artisan config:cache`
    | during deploys — env() returns null once config is cached.
    |
    */

    'email' => env('ADMIN_EMAIL'),
    'password' => env('ADMIN_PASSWORD'),

];
