<?php

namespace App\Http\Middleware;

use App\Helpers\ApiResponse;
use App\Helpers\Trans;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class XApiTokenMiddlleware
{
    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $xApiToken = $request->header('X-API-TOKEN');
        if (! $xApiToken) {
            return ApiResponse::error(Trans::get('api.unauthorized_x_api_token'), null, 401);
        }
        if ($xApiToken !== env('APP_X_API_TOKEN')) {
            return ApiResponse::error(Trans::get('api.unauthorized_x_api_token'), null, 401);
        }

        return $next($request);
    }
}
