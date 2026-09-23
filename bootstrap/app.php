<?php

use App\Helpers\ApiResponse;
use App\Helpers\Trans;
use App\Http\Middleware\EnsureContentSeeding;
use App\Http\Middleware\EnsureUserIsActive;
use App\Http\Middleware\EnsureUserIsVerified;
use App\Http\Middleware\GuestOnly;
use App\Http\Middleware\HandleInertiaRequests;
use App\Http\Middleware\IdentifyDevice;
use App\Http\Middleware\PurgeDeletedUsersAfterResponse;
use App\Http\Middleware\SecurityHeaders;
use App\Http\Middleware\SetLocaleMiddleware;
use App\Http\Middleware\WebLocale;
use App\Http\Middleware\XApiTokenMiddleware;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Exceptions\ThrottleRequestsException;
use Illuminate\Http\Middleware\AddLinkHeadersForPreloadedAssets;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Spatie\Permission\Exceptions\UnauthorizedException;
use Spatie\Permission\Middleware\PermissionMiddleware;
use Spatie\Permission\Middleware\RoleMiddleware;
use Spatie\Permission\Middleware\RoleOrPermissionMiddleware;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\MethodNotAllowedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withBroadcasting(
        __DIR__.'/../routes/channels.php',
        ['prefix' => 'api', 'middleware' => ['api', 'auth:sanctum']],
    )
    ->withMiddleware(function (Middleware $middleware): void {

        $middleware->trustProxies(at: '*', headers: Request::HEADER_X_FORWARDED_FOR |
            Request::HEADER_X_FORWARDED_HOST |
            Request::HEADER_X_FORWARDED_PORT |
            Request::HEADER_X_FORWARDED_PROTO |
            Request::HEADER_X_FORWARDED_AWS_ELB
        );

        $middleware->redirectTo(
            guests: fn () => route('login'),
            users: fn () => route('dashboard'),
        );

        $middleware->web(append: [
            SecurityHeaders::class,
            WebLocale::class,
            EnsureUserIsActive::class,
            HandleInertiaRequests::class,
            AddLinkHeadersForPreloadedAssets::class,
            PurgeDeletedUsersAfterResponse::class,
        ]);

        $middleware->api(append: [
            SecurityHeaders::class,
            XApiTokenMiddleware::class,
            IdentifyDevice::class,
            SetLocaleMiddleware::class,
            PurgeDeletedUsersAfterResponse::class,
        ]);

        $middleware->alias([
            'role' => RoleMiddleware::class,
            'permission' => PermissionMiddleware::class,
            'role_or_permission' => RoleOrPermissionMiddleware::class,
            'verified' => EnsureUserIsVerified::class,
            'active' => EnsureUserIsActive::class,
            'identify-device' => IdentifyDevice::class,
            'guest-only' => GuestOnly::class,
            'content-seeding' => EnsureContentSeeding::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        // Every framework-thrown API error answers in the same {success,message,errors,data}
        // envelope as the controllers, so a client never has to parse a second shape.
        //
        // The locale comes off the Accept-Language header rather than app()->getLocale():
        // an unknown or method-mismatched route never reaches SetLocaleMiddleware, so the
        // app locale is still the boot default at this point.
        $apiError = function (Request $request, string $key, string $fallback, int $status, array $headers = []) {
            $message = Trans::getOr($key, $fallback, [], Trans::localeFromHeader($request->header('Accept-Language')));

            return ApiResponse::error($message, null, $status)->withHeaders($headers);
        };

        // Normalize $request->validate()'s default {"message","errors"} shape into the
        // {success,message,errors,data} envelope every other API error already uses.
        // Scoped to api/* only — web/Inertia validation redirects are untouched.
        $exceptions->render(function (ValidationException $e, Request $request) {
            if ($request->is('api/*')) {
                return ApiResponse::error($e->getMessage(), $e->errors(), $e->status);
            }
        });

        $exceptions->render(function (AuthenticationException $e, Request $request) use ($apiError) {
            if ($request->is('api/*')) {
                return $apiError($request, 'api.unauthenticated', 'Unauthenticated.', 401);
            }
        });

        $exceptions->render(function (UnauthorizedException $e, Request $request) use ($apiError) {
            if ($request->is('api/*')) {
                return $apiError($request, 'api.unauthorized_access', 'Unauthorized access.', 403);
            }
        });

        $exceptions->render(function (AuthorizationException $e, Request $request) use ($apiError) {
            if ($request->is('api/*')) {
                return $apiError($request, 'api.unauthorized_access', 'Unauthorized access.', 403);
            }
        });

        // Route-model-binding and findOrFail misses. Laravel would otherwise convert these
        // into a bare {"message": "No query results for model [...]"} — a second shape and
        // a leaked class name.
        $exceptions->render(function (ModelNotFoundException $e, Request $request) use ($apiError) {
            if ($request->is('api/*')) {
                return $apiError($request, 'api.not_found', 'Resource not found.', 404);
            }
        });

        $exceptions->render(function (NotFoundHttpException $e, Request $request) use ($apiError) {
            if ($request->is('api/*')) {
                return $apiError($request, 'api.not_found', 'Resource not found.', 404);
            }
        });

        // A real PUT/DELETE against a host that only routes POST + X-HTTP-Method-Override
        // lands here, so the message has to be readable rather than framework HTML.
        $exceptions->render(function (MethodNotAllowedHttpException $e, Request $request) use ($apiError) {
            if ($request->is('api/*')) {
                return $apiError($request, 'api.method_not_allowed', 'Method not allowed.', 405, $e->getHeaders());
            }
        });

        // Keeps Retry-After / X-RateLimit-* — the client needs them to back off.
        $exceptions->render(function (ThrottleRequestsException $e, Request $request) use ($apiError) {
            if ($request->is('api/*')) {
                return $apiError($request, 'api.too_many_requests', 'Too many requests. Please try again later.', 429, $e->getHeaders());
            }
        });

        // A crashed API request answers in the same envelope as every other error, plus a
        // `debug` block carrying the real exception — the failing SQL and its bindings
        // included — so whoever is testing reads the cause off the response instead of
        // going to the server for the log. Gated by APP_DEBUG / IS_TESTING: with both off
        // the caller only gets "Server error".
        //
        // `respond()` runs after the framework has already rendered the exception, so the
        // status it decided is authoritative: 401/403/404/422/429 pass through untouched
        // and only genuine 5xx crashes are rewritten.
        $exceptions->respond(function (Response $response, Throwable $e, Request $request) {
            if (! $request->is('api/*') || $response->getStatusCode() < 500) {
                return $response;
            }

            return ApiResponse::exception($e, $response->getStatusCode());
        });
    })->create();
