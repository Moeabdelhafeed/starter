<?php

namespace App\Http\Middleware;

use App\Helpers\ApiResponse;
use App\Helpers\Trans;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class XApiTokenMiddleware
{
    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $xApiToken = (string) $request->header('X-API-TOKEN', '');
        $expected = (string) config('app.x_api_token', '');

        if ($xApiToken === '' || $expected === '' || ! hash_equals($expected, $xApiToken)) {
            return ApiResponse::error(Trans::get('api.unauthorized_x_api_token'), null, 401);
        }

        return $next($request);
    }
}
