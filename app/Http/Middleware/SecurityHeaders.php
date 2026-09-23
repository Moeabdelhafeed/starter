<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Baseline response headers for the admin panel and public pages.
 *
 * These are the defences a browser can apply that the application cannot:
 * refusing to be framed (clickjacking), refusing to re-sniff a response's type,
 * and not leaking the full admin URL in the Referer of outbound links. HSTS is
 * only sent over HTTPS, since announcing it over plain HTTP is meaningless and
 * pinning it from a local dev server would break `http://localhost`.
 */
class SecurityHeaders
{
    /**
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        $response->headers->set('X-Content-Type-Options', 'nosniff');
        $response->headers->set('X-Frame-Options', 'SAMEORIGIN');
        $response->headers->set('Referrer-Policy', 'strict-origin-when-cross-origin');
        $response->headers->set('X-Permitted-Cross-Domain-Policies', 'none');

        if ($request->isSecure()) {
            $response->headers->set('Strict-Transport-Security', 'max-age=31536000; includeSubDomains');
        }

        return $response;
    }
}
