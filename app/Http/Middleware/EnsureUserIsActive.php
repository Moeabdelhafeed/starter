<?php

namespace App\Http\Middleware;

use App\Helpers\ApiResponse;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class EnsureUserIsActive
{
    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user) {
            // An API caller is anything under api/* or resolved via a Sanctum
            // token. Don't rely on Accept/expectsJson() — clients may omit it,
            // which previously mis-routed API users into the web logout branch
            // (Sanctum's RequestGuard has no logout()).
            $isApi = $request->is('api/*') || (bool) $user->currentAccessToken();

            $isInactive = ! $user->is_active;

            // Web (admin panel) users must additionally have an active web role.
            $missingWebRole = ! $isApi
                && ! $user->roles()->where('guard_name', 'web')->where('is_active', true)->exists();

            if ($isInactive || $missingWebRole) {

                // API requests: revoke the token + 403.
                if ($isApi) {
                    if ($user->currentAccessToken()) {
                        $user->currentAccessToken()->delete();
                    }

                    return ApiResponse::error(__('admin.account_is_inactive'), null, 403);
                }

                // Web requests (Admin Panel): session logout + redirect.
                Auth::logout();
                $request->session()->invalidate();
                $request->session()->regenerateToken();

                return redirect()->route('login')->withErrors([
                    'email' => __('admin.account_is_inactive'),
                ]);
            }
        }

        return $next($request);
    }
}
