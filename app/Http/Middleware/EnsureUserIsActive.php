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
            $isInactive = ! $user->is_active;

            // For web requests (admin panel), we also check if they have an active web role
            $isWebRequest = ! $request->expectsJson();
            $missingWebRole = $isWebRequest && ! $user->roles()->where('guard_name', 'web')->where('is_active', true)->exists();

            if ($isInactive || $missingWebRole) {

                // Handle API requests
                if ($request->expectsJson()) {
                    if ($user->currentAccessToken()) {
                        $user->currentAccessToken()->delete();
                    }

                    return ApiResponse::error(__('admin.account_is_inactive'), null, 403);
                }

                // Handle Web requests (Admin Panel)
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
