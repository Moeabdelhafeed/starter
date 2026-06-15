<?php

use App\Helpers\ApiResponse;
use App\Http\Controllers\Api\AppUser\AppUserController;
use App\Http\Controllers\Api\Language\LanguageController as ApiLanguageController;
use App\Http\Controllers\Api\Page\PageController as ApiPageController;
use App\Http\Controllers\Api\Translations\TranslationController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::get('/config', [AppUserController::class, 'config']);
Route::post('/guest', [AppUserController::class, 'createGuest'])->middleware('throttle:api');

if (env('APP_USERS') === true) {

    $authMode = strtolower((string) env('AUTH_MODE', 'password')) === 'otp' ? 'otp' : 'password';

    // Authentication routes (stricter rate limit). Register + password reset
    // flows live only in password mode; OTP mode replaces them with the
    // identifier-only /login + /verify-login pair.
    Route::middleware('throttle:auth')->group(function () use ($authMode) {
        Route::post('/login', [AppUserController::class, 'login']);
        Route::post('/firebase-login', [AppUserController::class, 'firebaseLogin']);

        if ($authMode === 'password') {
            Route::post('/register', [AppUserController::class, 'register']);
        } else {
            Route::post('/verify-login', [AppUserController::class, 'verifyLogin']);
        }
    });

    if ($authMode === 'password') {
        // OTP-sending routes (strict rate limit — protect from spam)
        Route::middleware('throttle:otp')->group(function () {
            Route::post('/forgot-password', [AppUserController::class, 'forgotPassword']);
        });

        // Forgot-password verification + change
        Route::middleware('throttle:api')->group(function () {
            Route::post('/verify-forgot-password-otp', [AppUserController::class, 'verifyForgotPasswordOtp']);
            Route::post('/change-forgot-password', [AppUserController::class, 'changeForgotPassword']);
        });
    }

    // Identifier checks stay available in both modes.
    Route::middleware('throttle:api')->group(function () {
        Route::post('/check-identifier', [AppUserController::class, 'checkIdentifier']);
    });

    // Protected routes
    Route::middleware(['auth:sanctum', 'role:user', 'active', 'throttle:api'])->group(function () use ($authMode) {
        Route::post('/logout', [AppUserController::class, 'logout']);

        // OTP-sending (strict rate limit). Verification stays on standard throttle:api.
        Route::post('/send-otp', [AppUserController::class, 'sendOtp'])->middleware('throttle:otp');
        Route::post('/verify-otp', [AppUserController::class, 'verifyOtp']);

        if ($authMode === 'password') {
            Route::post('/change-password', [AppUserController::class, 'changePassword'])->middleware('verified');
        }
        Route::put('/update-profile', [AppUserController::class, 'updateProfile'])->middleware('verified');
        Route::post('/request-identifier-change', [AppUserController::class, 'requestIdentifierChange'])->middleware(['verified', 'throttle:otp']);
        Route::post('/verify-identifier-change', [AppUserController::class, 'verifyIdentifierChange'])->middleware('verified');
        Route::get('/social-accounts', [AppUserController::class, 'getSocialAccounts'])->middleware('verified');
        Route::post('/link-social-account', [AppUserController::class, 'linkSocialAccount'])->middleware('verified');
        Route::delete('/unlink-social-account', [AppUserController::class, 'unlinkSocialAccount'])->middleware('verified');

        Route::get('/devices', [AppUserController::class, 'devices'])->middleware('verified');
        Route::delete('/devices/{deviceId}', [AppUserController::class, 'revokeDevice'])->middleware('verified');
    });
}

// Delete-account works for both real users (soft-delete + token revoke) and
// guests (force-delete by UUID). IdentifyDevice already attached the right
// user. Real users still go through the verified gate; guests skip it.
Route::delete('/delete-account', [AppUserController::class, 'deleteAccount']);

Route::get('/user', function (Request $request) {
    $user = $request->user();

    if (! $user) {
        return ApiResponse::error('No user resolved', null, 401);
    }

    return ApiResponse::success($user);
});

// Translation + language routes — bypass throttling when IS_TESTING=true
// so bulk seeding from the client is not blocked.
if (filter_var(env('HAS_TRANSLATIONS', true), FILTER_VALIDATE_BOOLEAN)) {

    Route::get('/translations', [TranslationController::class, 'index']);
    Route::post('/translations', [TranslationController::class, 'store']);
    Route::get('/languages', [ApiLanguageController::class, 'index']);
}

// Public routes with standard rate limit
Route::middleware('throttle:api')->group(function () {

    // Page routes
    Route::get('/pages', [ApiPageController::class, 'index']);
    Route::get('/pages/{slug}', [ApiPageController::class, 'show']);
});
