<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

/*
|--------------------------------------------------------------------------
| Scheduled Tasks
|--------------------------------------------------------------------------
|
| Requires a cron entry on the server:
|
|     * * * * * cd /path-to-project && php artisan schedule:run >> /dev/null 2>&1
|
| Shared hosts that offer no cron still get the account purge: the
| App\Http\Middleware\PurgeDeletedUsersAfterResponse middleware runs the same
| command after a response, at most once an hour behind a cache lock. It is a
| fallback, not a replacement — leave it registered even with cron in place;
| withoutOverlapping() below keeps the two from colliding.
|
*/

Schedule::command('users:purge-deleted')
    ->hourly()
    ->withoutOverlapping();

// Sanctum tokens now carry an expiry (config/sanctum.php `expiration`); expired
// rows are dead weight in personal_access_tokens until they're pruned.
Schedule::command('sanctum:prune-expired --hours=24')
    ->daily();
