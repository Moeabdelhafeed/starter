<?php

use App\Helpers\ApiResponse;
use App\Http\Middleware\EnsureUserIsActive;
use App\Http\Middleware\EnsureUserIsVerified;
use App\Http\Middleware\GuestOnly;
use App\Http\Middleware\HandleInertiaRequests;
use App\Http\Middleware\IdentifyDevice;
use App\Http\Middleware\PurgeDeletedUsersAfterResponse;
use App\Http\Middleware\SetLocaleMiddleware;
use App\Http\Middleware\WebLocale;
use App\Http\Middleware\XApiTokenMiddlleware;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Middleware\AddLinkHeadersForPreloadedAssets;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Spatie\Permission\Middleware\PermissionMiddleware;
use Spatie\Permission\Middleware\RoleMiddleware;
use Spatie\Permission\Middleware\RoleOrPermissionMiddleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withBroadcasting(
        __DIR__.'/../routes/channels.php',
        ['prefix' => 'api', 'middleware' => ['api', XApiTokenMiddlleware::class, 'auth:sanctum']],
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
            WebLocale::class,
            EnsureUserIsActive::class,
            HandleInertiaRequests::class,
            AddLinkHeadersForPreloadedAssets::class,
            PurgeDeletedUsersAfterResponse::class,
        ]);

        $middleware->api(append: [
            XApiTokenMiddlleware::class,
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
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        // Normalize $request->validate()'s default {"message","errors"} shape into the
        // {success,message,errors,data} envelope every other API error already uses.
        // Scoped to api/* only — web/Inertia validation redirects are untouched.
        $exceptions->render(function (ValidationException $e, Request $request) {
            if ($request->is('api/*')) {
                return ApiResponse::error($e->getMessage(), $e->errors(), $e->status);
            }
        });
    })->create();
