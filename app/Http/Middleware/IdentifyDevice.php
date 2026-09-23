<?php

namespace App\Http\Middleware;

use App\Helpers\ApiResponse;
use App\Helpers\Trans;
use App\Models\User;
use App\Models\UserDevice;
use Closure;
use Illuminate\Http\Request;
use Laravel\Sanctum\PersonalAccessToken;
use Symfony\Component\HttpFoundation\Response;

class IdentifyDevice
{
    private const PLATFORMS = ['web', 'ios', 'android'];

    private const FCM_PLATFORMS = ['ios', 'android'];

    /**
     * Universal device identifier. Validates `X-Device-Id` + `X-Platform` on
     * every api request, plus `X-FCM-Token` on mobile platforms. Resolves the
     * caller in this order:
     *   1. Valid Bearer  → real user attached.
     *   2. UUID matches a registered (non-guest) user's `user_devices.device_id`
     *      → device "claimed", no user attached, request flagged so guest-only
     *      routes 403.
     *   3. Existing guest user matches `guest_id` → guest attached.
     *   4. Else → no user attached. Client must call `POST /api/guest` to
     *      explicitly create one (guests are no longer auto-created on every
     *      request).
     *
     * After resolving a user (auth or guest), upserts the matching
     * `user_devices` row keyed by `device_id` with the latest `fcm_token`,
     * `last_seen_at`, etc. Throttled to ~once per minute to avoid hot-path
     * write churn.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $deviceId = trim((string) $request->header('X-Device-Id'));
        $platform = strtolower(trim((string) $request->header('X-Platform')));
        $fcmToken = trim((string) $request->header('X-FCM-Token')) ?: null;

        if ($deviceId === '' || strlen($deviceId) > 64) {
            return ApiResponse::error(
                Trans::get('api.device_id_required'),
                ['device_id' => [Trans::get('api.device_id_required')]],
                422,
            );
        }

        if (! in_array($platform, self::PLATFORMS, true)) {
            return ApiResponse::error(
                Trans::get('api.platform_invalid'),
                ['platform' => [Trans::get('api.platform_invalid')]],
                422,
            );
        }

        if (in_array($platform, self::FCM_PLATFORMS, true) && ! $fcmToken) {
            return ApiResponse::error(
                Trans::get('api.fcm_token_required'),
                ['fcm_token' => [Trans::get('api.fcm_token_required')]],
                422,
            );
        }

        // Valid Bearer → attach the auth user so downstream middleware (e.g.
        // guest-only) can detect authed callers even on routes that don't
        // include auth:sanctum in their stack. Invalid/expired/forged Bearer
        // falls through to guest path so guest-only routes don't 403 on bad tokens.
        $bearer = $request->bearerToken();
        if ($bearer) {
            $token = PersonalAccessToken::findToken($bearer);
            if ($token && $token->tokenable && ! $token->expires_at?->isPast()) {
                $authUser = $token->tokenable->withAccessToken($token);
                $request->setUserResolver(fn () => $authUser);
                $this->touchDevice($request, $authUser, $deviceId, $platform, $fcmToken);

                return $next($request);
            }
        }

        // "Claimed" = device_id is already attached to a registered (non-guest)
        // user via user_devices. Skip guest creation and skip device updates
        // (don't let unauthed callers mutate someone else's row).
        $claimed = UserDevice::where('device_id', $deviceId)
            ->whereHas('user', fn ($q) => $q->where('is_guest', false))
            ->exists();

        if ($claimed) {
            $request->attributes->set('device_claimed', true);

            return $next($request);
        }

        // Find existing guest for this UUID (no auto-create). Client must call
        // POST /api/guest to register a guest before other endpoints can rely
        // on $request->user().
        $guest = User::where('guest_id', $deviceId)->where('is_guest', true)->first();

        if ($guest) {
            $request->setUserResolver(fn () => $guest);
            $this->touchDevice($request, $guest, $deviceId, $platform, $fcmToken);
        }

        return $next($request);
    }

    /**
     * Throttled upsert of the user_devices row keyed by `device_id`. Updates
     * fcm_token / platform / ip / user_agent / last_seen_at. Skips the write
     * if last_seen_at is fresh AND the fcm_token hasn't changed.
     *
     * `updateOrCreate` over a look-then-create: two requests from the same phone arriving
     * together both used to find nothing and both insert, and every duplicate row carried
     * the same FCM token — which is how one notification arrived in the tray a dozen times.
     * The unique index on (user_id, device_id) is the real guard; this is the write that
     * respects it.
     */
    private function touchDevice(Request $request, User $user, string $deviceId, string $platform, ?string $fcmToken): void
    {
        $existing = UserDevice::where('device_id', $deviceId)
            ->where('user_id', $user->id)
            ->first();

        if ($existing) {
            $stale = ! $existing->last_seen_at || $existing->last_seen_at->diffInSeconds(now()) > 60;
            $tokenChanged = $fcmToken !== null && $existing->fcm_token !== $fcmToken;

            if (! $stale && ! $tokenChanged) {
                return;
            }
        }

        $device = UserDevice::updateOrCreate(
            ['user_id' => $user->id, 'device_id' => $deviceId],
            [
                'fcm_token' => $fcmToken ?? $existing?->fcm_token,
                'platform' => $platform,
                'ip' => $request->ip(),
                'user_agent' => substr((string) $request->userAgent(), 0, 512),
                'last_seen_at' => now(),
            ],
        );

        $device->claimFcmToken();
    }
}
