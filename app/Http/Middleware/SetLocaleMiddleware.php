<?php

namespace App\Http\Middleware;

use App\Helpers\ApiResponse;
use App\Helpers\Trans;
use App\Models\Language;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;
use Symfony\Component\HttpFoundation\Response;

class SetLocaleMiddleware
{
    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $locale = $request->header('Accept-Language');

        if (! $locale) {
            return ApiResponse::error(Trans::get('api.please_set_the_accept_language_header'), null, 401);
        }

        $isValid = Language::where('code', $locale)->where('is_active', true)->exists();

        if ($isValid) {
            App::setLocale($locale);
        } else {
            $default = Language::getDefault();
            App::setLocale($default?->code ?? 'en');
        }

        $response = $next($request);

        if ($isValid) {
            $user = $request->user();
            if ($user && $user->current_lang !== $locale) {
                $user->forceFill(['current_lang' => $locale])->saveQuietly();
            }
        }

        return $response;
    }
}
