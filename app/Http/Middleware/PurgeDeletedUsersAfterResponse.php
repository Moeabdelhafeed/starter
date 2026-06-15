<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

class PurgeDeletedUsersAfterResponse
{
    public function handle(Request $request, Closure $next): Response
    {
        return $next($request);
    }

    public function terminate(Request $request, Response $response): void
    {
        // add() is atomic — only one process per TTL window enters the lock,
        // so the purge runs at most once an hour across the whole app.
        if (! Cache::add('purge_deleted_users_lock', 1, now()->addHour())) {
            return;
        }

        try {
            Artisan::call('users:purge-deleted');
        } catch (Throwable $e) {
            report($e);
        }
    }
}
