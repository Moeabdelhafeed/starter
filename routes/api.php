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

    // OTP-sending routes (strict rate limit — protect from spam)
    Route::middleware('throttle:otp')->group(function () {
        Route::post('/forgot-password', [AppUserController::class, 'forgotPassword']);
    });

    // Public OTP-verification + identifier checks (standard rate limit — let users retry codes)
    Route::middleware('throttle:api')->group(function () {
        Route::get('/auth-config', [AppUserController::class, 'authConfig']);
        Route::post('/check-identifier', [AppUserController::class, 'checkIdentifier']);
        Route::post('/verify-forgot-password-otp', [AppUserController::class, 'verifyForgotPasswordOtp']);
        Route::post('/change-forgot-password', [AppUserController::class, 'changeForgotPassword']);
    });

    // Protected routes
    Route::middleware(['auth:sanctum', 'role:user', 'active', 'throttle:api'])->group(function () {
        Route::post('/logout', [AppUserController::class, 'logout']);

        // OTP-sending (strict rate limit). Verification stays on standard throttle:api.
        Route::post('/send-otp', [AppUserController::class, 'sendOtp'])->middleware('throttle:otp');
        Route::post('/verify-otp', [AppUserController::class, 'verifyOtp']);

        Route::post('/change-password', [AppUserController::class, 'changePassword'])->middleware('verified');
        Route::put('/update-profile', [AppUserController::class, 'updateProfile'])->middleware('verified');
        Route::post('/request-identifier-change', [AppUserController::class, 'requestIdentifierChange'])->middleware(['verified', 'throttle:otp']);
        Route::post('/verify-identifier-change', [AppUserController::class, 'verifyIdentifierChange'])->middleware('verified');
        Route::delete('/delete-account', [AppUserController::class, 'deleteAccount'])->middleware('verified');
        Route::get('/social-accounts', [AppUserController::class, 'getSocialAccounts'])->middleware('verified');
        Route::post('/link-social-account', [AppUserController::class, 'linkSocialAccount'])->middleware('verified');
        Route::delete('/unlink-social-account', [AppUserController::class, 'unlinkSocialAccount'])->middleware('verified');

        Route::get('/user', function (Request $request) {
            return ApiResponse::success($request->user());
        });
    });

}

// Translation + language routes — bypass throttling when IS_TESTING=true
// so bulk seeding from the client is not blocked.
if (filter_var(env('HAS_TRANSLATIONS', true), FILTER_VALIDATE_BOOLEAN)) {
    Route::middleware('throttle:translations')->group(function () {
        Route::get('/translations', [TranslationController::class, 'index']);
        Route::post('/translations', [TranslationController::class, 'store']);
        Route::get('/languages', [ApiLanguageController::class, 'index']);
    });
}

// Public routes with standard rate limit
Route::middleware('throttle:api')->group(function () {

    // Page routes
    Route::get('/pages', [ApiPageController::class, 'index']);
    Route::get('/pages/{slug}', [ApiPageController::class, 'show']);
});
