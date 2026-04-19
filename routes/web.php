<?php

use App\Http\Controllers\Admin\ActivityLog\ActivityLogController;
use App\Http\Controllers\Admin\AppUser\AppUserController;
use App\Http\Controllers\Admin\Auth\AuthController;
use App\Http\Controllers\Admin\Dashboard\DashboardController;
use App\Http\Controllers\Admin\DevSetting\DevSettingController;
use App\Http\Controllers\Admin\Docs\DocsController;
use App\Http\Controllers\Admin\Language\LanguageController;
use App\Http\Controllers\Admin\Locale\LocaleController;
use App\Http\Controllers\Admin\Notification\NotificationController;
use App\Http\Controllers\Admin\Page\PageController;
use App\Http\Controllers\Admin\Profile\ProfileController;
use App\Http\Controllers\Admin\Roles\RolesController;
use App\Http\Controllers\Admin\Translation\TranslationController;
use App\Http\Controllers\Admin\User\UserController;
use Illuminate\Support\Facades\Route;

// locale
Route::post('/set-locale', [LocaleController::class, 'setLocale'])->name('locale.post');

// guest
Route::middleware('guest')->group(function () {
    Route::get('/login', [AuthController::class, 'index'])->name('login');
    Route::post('/login', [AuthController::class, 'login'])->name('login.post');
});

// authenticated
Route::middleware('auth')->group(function () {
    Route::get('/', [DashboardController::class, 'index'])->name('dashboard');
    Route::post('/logout', [AuthController::class, 'logout'])->name('logout');

    // profile
    Route::get('/profile', [ProfileController::class, 'index'])->name('profile');
    Route::put('/profile', [ProfileController::class, 'update'])->name('profile.update');

    // translations
    if (filter_var(env('HAS_TRANSLATIONS', true), FILTER_VALIDATE_BOOLEAN)) {
        Route::prefix('translations')->middleware('permission:translations')->group(function () {
            Route::get('/', [TranslationController::class, 'index'])->name('translations');
            Route::post('/edit', [TranslationController::class, 'edit'])->name('translations.edit');
            Route::delete('/bulk-destroy', [TranslationController::class, 'bulkDestroy'])->name('translations.bulk-destroy');
            Route::delete('/{translation}', [TranslationController::class, 'destroy'])->name('translations.destroy');
        });

        // languages
        Route::prefix('languages')->middleware('permission:translations')->group(function () {
            Route::get('/', [LanguageController::class, 'index'])->name('languages');
            Route::post('/', [LanguageController::class, 'store'])->name('languages.store');
            Route::put('/{language}', [LanguageController::class, 'update'])->name('languages.update');
            Route::delete('/{language}', [LanguageController::class, 'destroy'])->name('languages.destroy');
        });
    }

    // users
    Route::prefix('users')->middleware('permission:users')->group(function () {
        Route::get('/', [UserController::class, 'index'])->name('users');
        Route::post('/', [UserController::class, 'store'])->name('users.store');
        Route::put('/bulk-update', [UserController::class, 'bulkUpdate'])->name('users.bulk-update');
        Route::delete('/bulk-destroy', [UserController::class, 'bulkDestroy'])->name('users.bulk-destroy');
        Route::post('/bulk-restore', [UserController::class, 'bulkRestore'])->name('users.bulk-restore');
        Route::post('/bulk-force-delete', [UserController::class, 'bulkForceDelete'])->name('users.bulk-force-delete');
        Route::put('/{user}', [UserController::class, 'update'])->name('users.update');
        Route::delete('/{user}', [UserController::class, 'destroy'])->name('users.destroy');
        Route::post('/{user}/restore', [UserController::class, 'restore'])->name('users.restore')->withTrashed();
        Route::delete('/{user}/force-delete', [UserController::class, 'forceDelete'])->name('users.force-delete')->withTrashed();
    });

    // roles
    Route::prefix('roles')->middleware('permission:roles')->group(function () {
        Route::get('/', [RolesController::class, 'index'])->name('roles');
        Route::post('/', [RolesController::class, 'store'])->name('roles.store');
        Route::put('/bulk-update', [RolesController::class, 'bulkUpdate'])->name('roles.bulk-update');
        Route::delete('/bulk-destroy', [RolesController::class, 'bulkDestroy'])->name('roles.bulk-destroy');
        Route::put('/{role}', [RolesController::class, 'update'])->name('roles.update');
        Route::delete('/{role}', [RolesController::class, 'destroy'])->name('roles.destroy');
    });

    // activity logs
    Route::prefix('activity-logs')->middleware('permission:activity_logs')->group(function () {
        Route::get('/', [ActivityLogController::class, 'index'])->name('activity_logs');
        Route::delete('/bulk-destroy', [ActivityLogController::class, 'bulkDestroy'])->name('activity_logs.bulk-destroy');
        Route::delete('/{id}', [ActivityLogController::class, 'destroy'])->name('activity_logs.destroy');
    });

    // notifications (sidebar only - available to all authenticated users)
    Route::prefix('notifications')->group(function () {
        Route::get('/recent', [NotificationController::class, 'recent'])->name('notifications.recent');
        Route::post('/mark-all-read', [NotificationController::class, 'markAllAsRead'])->name('notifications.mark_all_read');
        Route::post('/{notification}/mark-read', [NotificationController::class, 'markAsRead'])->name('notifications.mark_read');
    });

    // pages
    Route::prefix('pages')->middleware('permission:pages')->group(function () {
        Route::get('/', [PageController::class, 'index'])->name('pages');
        Route::post('/', [PageController::class, 'store'])->name('pages.store');
        Route::put('/bulk-update', [PageController::class, 'bulkUpdate'])->name('pages.bulk-update');
        Route::delete('/bulk-destroy', [PageController::class, 'bulkDestroy'])->name('pages.bulk-destroy');
        Route::get('/{page}/edit', [PageController::class, 'edit'])->name('pages.edit');
        Route::put('/{page}', [PageController::class, 'update'])->name('pages.update');
        Route::delete('/{page}', [PageController::class, 'destroy'])->name('pages.destroy');
    });

    // app users
    if (env('APP_USERS') === true) {
        Route::prefix('app-users')->middleware('permission:app_users')->group(function () {
            Route::get('/', [AppUserController::class, 'index'])->name('app_users');
            Route::put('/bulk-update', [AppUserController::class, 'bulkUpdate'])->name('app_users.bulk-update');
            Route::delete('/bulk-destroy', [AppUserController::class, 'bulkDestroy'])->name('app_users.bulk-destroy');
            Route::post('/bulk-restore', [AppUserController::class, 'bulkRestore'])->name('app_users.bulk-restore');
            Route::post('/bulk-force-delete', [AppUserController::class, 'bulkForceDelete'])->name('app_users.bulk-force-delete');
            Route::put('/{user}', [AppUserController::class, 'update'])->name('app_users.update');
            Route::delete('/{user}', [AppUserController::class, 'destroy'])->name('app_users.destroy');
            Route::post('/{user}/restore', [AppUserController::class, 'restore'])->name('app_users.restore')->withTrashed();
            Route::delete('/{user}/force-delete', [AppUserController::class, 'forceDelete'])->name('app_users.force-delete')->withTrashed();
        });
    }

    // documentation (local env only)
    if (app()->environment('local')) {
        Route::get('/docs', [DocsController::class, 'index'])->name('docs');
    }

    // developer settings (local env only)
    if (app()->environment('local')) {
        Route::prefix('dev-settings')->group(function () {
            Route::get('/', [DevSettingController::class, 'index'])->name('dev_settings');
            Route::put('/colors', [DevSettingController::class, 'updateColors'])->name('dev_settings.colors');
            Route::post('/build', [DevSettingController::class, 'buildAssets'])->name('dev_settings.build');
            Route::put('/env', [DevSettingController::class, 'updateEnv'])->name('dev_settings.env');
            Route::put('/production-env', [DevSettingController::class, 'updateProductionEnv'])->name('dev_settings.production_env');
            Route::put('/urls', [DevSettingController::class, 'updateUrls'])->name('dev_settings.urls');
            Route::post('/firebase', [DevSettingController::class, 'uploadFirebaseJson'])->name('dev_settings.firebase');
            Route::post('/test-fcm', [DevSettingController::class, 'testFcm'])->name('dev_settings.test_fcm');
            Route::put('/auth', [DevSettingController::class, 'updateAuth'])->name('dev_settings.auth');
            Route::put('/social-auth', [DevSettingController::class, 'updateSocialAuth'])->name('dev_settings.social_auth');
            Route::put('/validation', [DevSettingController::class, 'updateValidation'])->name('dev_settings.validation');
            Route::put('/rate-limiting', [DevSettingController::class, 'updateRateLimiting'])->name('dev_settings.rate_limiting');
            Route::put('/pusher', [DevSettingController::class, 'updatePusher'])->name('dev_settings.pusher');
            Route::put('/production-pusher', [DevSettingController::class, 'updateProductionPusher'])->name('dev_settings.production_pusher');
            Route::post('/test-broadcast', [DevSettingController::class, 'testBroadcast'])->name('dev_settings.test_broadcast');
            Route::post('/git', [DevSettingController::class, 'initGit'])->name('dev_settings.git');
            Route::delete('/git', [DevSettingController::class, 'disconnectGit'])->name('dev_settings.git_disconnect');
            Route::post('/push', [DevSettingController::class, 'pushToGithub'])->name('dev_settings.push');
            Route::post('/pull', [DevSettingController::class, 'pullFromGithub'])->name('dev_settings.pull');
            Route::post('/fetch', [DevSettingController::class, 'fetchRemote'])->name('dev_settings.fetch');
            Route::post('/commit', [DevSettingController::class, 'commitChanges'])->name('dev_settings.commit');
            Route::post('/branch/switch', [DevSettingController::class, 'switchBranch'])->name('dev_settings.branch_switch');
            Route::post('/branch/create', [DevSettingController::class, 'createBranch'])->name('dev_settings.branch_create');
            Route::get('/diff', [DevSettingController::class, 'getFileDiff'])->name('dev_settings.diff');
            Route::put('/production-db', [DevSettingController::class, 'updateProductionDb'])->name('dev_settings.production_db');
            Route::put('/app-name', [DevSettingController::class, 'updateAppName'])->name('dev_settings.app_name');
            Route::post('/api-token', [DevSettingController::class, 'generateApiToken'])->name('dev_settings.api_token');
            Route::put('/admin-credentials', [DevSettingController::class, 'updateAdminCredentials'])->name('dev_settings.admin_credentials');
            Route::put('/local-mail', [DevSettingController::class, 'updateLocalMail'])->name('dev_settings.local_mail');
            Route::put('/production-mail', [DevSettingController::class, 'updateProductionMail'])->name('dev_settings.production_mail');
            Route::post('/deploy', [DevSettingController::class, 'deploy'])->name('dev_settings.deploy');
            Route::put('/deploy-config', [DevSettingController::class, 'saveDeployConfig'])->name('dev_settings.deploy_config');
            Route::post('/logo', [DevSettingController::class, 'uploadLogo'])->name('dev_settings.logo');
            Route::post('/favicon', [DevSettingController::class, 'uploadFavicon'])->name('dev_settings.favicon');
            Route::get('/postman', [DevSettingController::class, 'downloadPostman'])->name('dev_settings.postman');
        });
    }
});
