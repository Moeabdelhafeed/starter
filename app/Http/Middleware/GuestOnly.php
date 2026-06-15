<?php

namespace App\Http\Middleware;

use App\Helpers\ApiResponse;
use App\Helpers\Trans;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class GuestOnly
{
    /**
     * Reject authed (Bearer-bearing) users. Used per-route on endpoints
     * reserved for guests. Run AFTER `IdentifyDevice` so the request resolver
     * already carries a guest user when applicable.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        $deviceClaimed = (bool) $request->attributes->get('device_claimed');

        // Reject when:
        //   - an authenticated non-guest user is attached (valid Bearer), OR
        //   - the device is claimed by a registered user (device_id present in
        //     user_devices), even without a valid Bearer — they need to log in
        //     again rather than fall through to a fresh guest.
        if ($deviceClaimed || ($user && ! $user->is_guest)) {
            return ApiResponse::error(
                Trans::get('api.guest_only_route'),
                ['auth' => [Trans::get('api.guest_only_route')]],
                403,
            );
        }

        return $next($request);
    }
}
