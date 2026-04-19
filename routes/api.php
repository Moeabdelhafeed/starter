<?php

use App\Helpers\ApiResponse;
use App\Http\Controllers\Api\AppUser\AppUserController;
use App\Http\Controllers\Api\Language\LanguageController as ApiLanguageController;
use App\Http\Controllers\Api\Page\PageController as ApiPageController;
use App\Http\Controllers\Api\Translations\TranslationController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

if (env('APP_USERS') === true) {

    // Authentication routes (stricter rate limit)
    Route::middleware('throttle:auth')->group(function () {
        Route::post('/register', [AppUserController::class, 'register']);
        Route::post('/login', [AppUserController::class, 'login']);
        Route::post('/firebase-login', [AppUserController::class, 'firebaseLogin']);
    });

    // OTP routes (very strict rate limit)
    Route::middleware('throttle:otp')->group(function () {
        Route::post('/forgot-password', [AppUserController::class, 'forgotPassword']);
        Route::post('/verify-forgot-password-otp', [AppUserController::class, 'verifyForgotPasswordOtp']);
        Route::post('/change-forgot-password', [AppUserController::class, 'changeForgotPassword']);
    });

    // Public routes with standard rate limit
    Route::middleware('throttle:api')->group(function () {
        Route::post('/check-identifier', [AppUserController::class, 'checkIdentifier']);
    });

    // Protected routes
    Route::middleware(['auth:sanctum', 'role:user', 'active', 'throttle:api'])->group(function () {
        Route::post('/logout', [AppUserController::class, 'logout']);

        // OTP verification (stricter limit)
        Route::middleware('throttle:otp')->group(function () {
            Route::post('/send-otp', [AppUserController::class, 'sendOtp']);
            Route::post('/verify-otp', [AppUserController::class, 'verifyOtp']);
        });

        Route::post('/change-password', [AppUserController::class, 'changePassword'])->middleware('verified');
        Route::put('/update-profile', [AppUserController::class, 'updateProfile'])->middleware('verified');
        Route::post('/request-email-change', [AppUserController::class, 'requestEmailChange'])->middleware('verified');
        Route::post('/verify-email-change', [AppUserController::class, 'verifyEmailChange'])->middleware('verified');
        Route::delete('/delete-account', [AppUserController::class, 'deleteAccount'])->middleware('verified');
        Route::get('/social-accounts', [AppUserController::class, 'getSocialAccounts'])->middleware('verified');
        Route::post('/link-social-account', [AppUserController::class, 'linkSocialAccount'])->middleware('verified');
        Route::delete('/unlink-social-account', [AppUserController::class, 'unlinkSocialAccount'])->middleware('verified');

        Route::get('/user', function (Request $request) {
            return ApiResponse::success($request->user());
        });
    });

}

// Public routes with standard rate limit
Route::middleware('throttle:api')->group(function () {
    // Translation routes (conditional)
    if (filter_var(env('HAS_TRANSLATIONS', true), FILTER_VALIDATE_BOOLEAN)) {
        Route::get('/translations', [TranslationController::class, 'index']);
        Route::post('/translations', [TranslationController::class, 'store']);

        // Language routes
        Route::get('/languages', [ApiLanguageController::class, 'index']);
    }

    // Page routes
    Route::get('/pages', [ApiPageController::class, 'index']);
    Route::get('/pages/{slug}', [ApiPageController::class, 'show']);
});
