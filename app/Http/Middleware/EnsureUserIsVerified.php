<?php

namespace App\Http\Middleware;

use App\Helpers\ApiResponse;
use App\Helpers\Trans;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureUserIsVerified
{
    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user || $user->verified_at === null) {
            if ($request->expectsJson() || $request->is('api/*')) {
                return ApiResponse::error(Trans::get('api.user_not_verified'), null, 403);
            }

            return redirect()->route('verification.notice');
        }

        return $next($request);
    }
}
