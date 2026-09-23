<?php

namespace App\Http\Middleware;

use App\Helpers\ApiResponse;
use App\Helpers\Trans;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Restricts the content-provisioning writes (POST/DELETE on /api/translations and
 * /api/media) to installs with ALLOW_CONTENT_SEEDING=true.
 *
 * Separate from IS_TESTING because the two answer different questions. Testing mode is
 * "this install is not real" — it echoes OTP codes and drops rate limits, and is forced
 * off in production. Seeding is "fill the content tables now", which a *production*
 * install legitimately needs exactly once, when the client app pushes its translations
 * and media in. It is then turned back off.
 *
 * Read per request via config so the flag survives `config:cache` and can be flipped by
 * editing the env file without touching code.
 */
class EnsureContentSeeding
{
    /**
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        if (! config('features.content_seeding')) {
            return ApiResponse::error(Trans::getOr('api.content_seeding_disabled', 'Content seeding is disabled. Set ALLOW_CONTENT_SEEDING=true to provision content.'), null, 403);
        }

        return $next($request);
    }
}
