<?php

use App\Http\Controllers\Admin\ActivityLog\ActivityLogController;
use App\Http\Controllers\Admin\AppSetting\AppSettingController;
use App\Http\Controllers\Admin\AppUser\AppUserController;
use App\Http\Controllers\Admin\Auth\AuthController;
use App\Http\Controllers\Admin\Dashboard\DashboardController;
use App\Http\Controllers\Admin\DevSetting\DevSettingController;
use App\Http\Controllers\Admin\Language\LanguageController;
use App\Http\Controllers\Admin\Locale\LocaleController;
use App\Http\Controllers\Admin\Media\MediaController;
use App\Http\Controllers\Admin\Notification\NotificationController;
use App\Http\Controllers\Admin\NotificationTemplate\NotificationTemplateController;
use App\Http\Controllers\Admin\Page\PageController;
use App\Http\Controllers\Admin\Profile\ProfileController;
use App\Http\Controllers\Admin\Roles\RolesController;
use App\Http\Controllers\Admin\Search\SearchController;
use App\Http\Controllers\Admin\Translation\TranslationController;
use App\Http\Controllers\Admin\User\UserController;
use Illuminate\Support\Facades\Broadcast;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Route;

// locale
Route::post('/set-locale', [LocaleController::class, 'setLocale'])->name('locale.post');

// public pages — render an active Page record by slug (terms, privacy, etc.).
if (config('features.pages')) {
    Route::get('/p/{slug}', [App\Http\Controllers\Page\PageController::class, 'show'])->name('public_page.show');
}

// guest
Route::middleware('guest')->group(function () {
    Route::get('/login', [AuthController::class, 'index'])->name('login');
    Route::post('/login', [AuthController::class, 'login'])->middleware('throttle:10,1')->name('login.post');
});

// Web channel-auth endpoint for Pusher private channels. Uses session auth.
// Mobile clients still hit the api-prefixed endpoint registered via
// withBroadcasting() in bootstrap/app.php. Throttled to absorb Pusher
// reconnect storms without inviting abuse.
Route::post('/broadcasting/auth', fn () => Broadcast::auth(request()))
    ->middleware(['auth', 'throttle:60,1,broadcast-auth']);

// authenticated
Route::middleware('auth')->group(function () {
    Route::get('/', [DashboardController::class, 'index'])->name('dashboard');
    Route::post('/logout', [AuthController::class, 'logout'])->name('logout');

    /**
     * Drops everything in the cache store. Nothing here is a source of truth — the
     * language list, the permission registry and the notification-trigger model scan
     * are all rebuilt from the database on the next request — so this is a way to
     * recover from a stale entry without a shell, which a local-only DevSettings
     * button would not give you on a deployed site. Throttled because flushing is
     * cheap to ask for and not free to serve.
     */
    Route::post('/clear-cache', function () {
        Cache::flush();

        return back()->with('success', __('admin.cache_cleared'));
    })->middleware('throttle:10,1,cache-clear')->name('cache.clear');

    // profile
    // Command palette (Cmd/Ctrl+K). Returns JSON, not an Inertia page. No
    // permission gate here on purpose — SearchController filters every module
    // by the caller's own permissions, so an admin only ever sees what they
    // could already open.
    Route::get('/search', SearchController::class)->middleware('throttle:60,1,admin-search')->name('search');

    Route::get('/profile', [ProfileController::class, 'index'])->name('profile');
    Route::put('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::put('/profile/password', [ProfileController::class, 'updatePassword'])->name('profile.password');

    // translations
    if (config('features.translations')) {
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
        Route::get('/export', [UserController::class, 'export'])->name('users.export');
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
    if (config('features.activity_logs')) {
        Route::prefix('activity-logs')->middleware('permission:activity_logs')->group(function () {
            Route::get('/', [ActivityLogController::class, 'index'])->name('activity_logs');
            Route::get('/export', [ActivityLogController::class, 'export'])->name('activity_logs.export');
            // Read-only: an audit trail an admin could delete from records nothing.
        });
    }

    // notifications (sidebar only - available to all authenticated users)
    Route::prefix('notifications')->group(function () {
        Route::get('/recent', [NotificationController::class, 'recent'])->name('notifications.recent');
        Route::post('/mark-all-read', [NotificationController::class, 'markAllAsRead'])->name('notifications.mark_all_read');
        Route::post('/{notification}/mark-read', [NotificationController::class, 'markAsRead'])->name('notifications.mark_read');
    });

    // notification templates
    if (config('features.notification_templates')) {
        Route::prefix('notification-templates')->middleware('permission:notification_templates')->group(function () {
            Route::get('/', [NotificationTemplateController::class, 'index'])->name('notification_templates');
            Route::post('/', [NotificationTemplateController::class, 'store'])->name('notification_templates.store');
            Route::delete('/bulk-destroy', [NotificationTemplateController::class, 'bulkDestroy'])->name('notification_templates.bulk-destroy');
            Route::post('/{notification_template}/send', [NotificationTemplateController::class, 'sendNow'])->name('notification_templates.send');
            Route::put('/{notification_template}', [NotificationTemplateController::class, 'update'])->name('notification_templates.update');
            Route::delete('/{notification_template}', [NotificationTemplateController::class, 'destroy'])->name('notification_templates.destroy');
        });
    }

    // pages
    if (config('features.pages')) {
        Route::prefix('pages')->middleware('permission:pages')->group(function () {
            Route::get('/', [PageController::class, 'index'])->name('pages');
            Route::post('/', [PageController::class, 'store'])->name('pages.store');
            Route::put('/bulk-update', [PageController::class, 'bulkUpdate'])->name('pages.bulk-update');
            Route::delete('/bulk-destroy', [PageController::class, 'bulkDestroy'])->name('pages.bulk-destroy');
            Route::get('/{page}/edit', [PageController::class, 'edit'])->name('pages.edit');
            Route::put('/{page}', [PageController::class, 'update'])->name('pages.update');
            Route::delete('/{page}', [PageController::class, 'destroy'])->name('pages.destroy');
        });
    }

    if (config('features.app_settings')) {
        Route::prefix('app-settings')->middleware('permission:app_settings')->group(function () {
            Route::get('/', [AppSettingController::class, 'index'])->name('app_settings');
            Route::post('/', [AppSettingController::class, 'store'])->name('app_settings.store');
            Route::put('/bulk-update', [AppSettingController::class, 'bulkUpdate'])->name('app_settings.bulk-update');
            Route::delete('/bulk-destroy', [AppSettingController::class, 'bulkDestroy'])->name('app_settings.bulk-destroy');
            Route::put('/{appSetting}', [AppSettingController::class, 'update'])->name('app_settings.update');
            Route::delete('/{appSetting}', [AppSettingController::class, 'destroy'])->name('app_settings.destroy');
        });
    }

    // dynamic storage (keyed media) — view + filter + download + replace (no delete)
    if (config('features.dynamic_storage')) {
        Route::prefix('media')->middleware('permission:dynamic_storage')->group(function () {
            Route::get('/', [MediaController::class, 'index'])->name('media');
            Route::put('/{media}/remove', [MediaController::class, 'removeMedia'])->name('media.remove');
            Route::put('/{media}', [MediaController::class, 'update'])->name('media.update');
        });
    }

    // app users (and/or guests)
    if (config('features.app_users') || config('features.app_guests')) {
        Route::prefix('app-users')->middleware('permission:app_users')->group(function () {
            Route::get('/', [AppUserController::class, 'index'])->name('app_users');
            Route::get('/export', [AppUserController::class, 'export'])->name('app_users.export');
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

    // developer settings (local env only)
    if (app()->environment('local')) {
        Route::prefix('dev-settings')->middleware('role:super_admin')->group(function () {
            Route::get('/', [DevSettingController::class, 'index'])->name('dev_settings');
            Route::put('/colors', [DevSettingController::class, 'updateColors'])->name('dev_settings.colors');
            Route::post('/build', [DevSettingController::class, 'buildAssets'])->name('dev_settings.build');
            Route::put('/env', [DevSettingController::class, 'updateEnv'])->name('dev_settings.env');
            Route::put('/urls', [DevSettingController::class, 'updateUrls'])->name('dev_settings.urls');
            Route::post('/firebase', [DevSettingController::class, 'uploadFirebaseJson'])->name('dev_settings.firebase');
            Route::post('/test-fcm', [DevSettingController::class, 'testFcm'])->name('dev_settings.test_fcm');
            Route::put('/auth', [DevSettingController::class, 'updateAuth'])->name('dev_settings.auth');
            Route::put('/social-auth', [DevSettingController::class, 'updateSocialAuth'])->name('dev_settings.social_auth');
            Route::put('/validation', [DevSettingController::class, 'updateValidation'])->name('dev_settings.validation');
            Route::put('/rate-limiting', [DevSettingController::class, 'updateRateLimiting'])->name('dev_settings.rate_limiting');
            Route::put('/account-deletion', [DevSettingController::class, 'updateAccountDeletionConfig'])->name('dev_settings.account_deletion');
            Route::put('/sessions', [DevSettingController::class, 'updateSessionsConfig'])->name('dev_settings.sessions');
            Route::put('/topics', [DevSettingController::class, 'updateTopics'])->name('dev_settings.topics');
            Route::put('/protected-pages', [DevSettingController::class, 'updateProtectedPages'])->name('dev_settings.protected_pages');
            Route::put('/hostinger/token', [DevSettingController::class, 'updateHostingerToken'])->name('dev_settings.hostinger_token');
            Route::get('/hostinger/account', [DevSettingController::class, 'hostingerAccount'])->name('dev_settings.hostinger_account');
            Route::post('/hostinger/provision', [DevSettingController::class, 'provisionFlavor'])->name('dev_settings.hostinger_provision');
            Route::get('/databases', [DevSettingController::class, 'hostingerDatabases'])->name('dev_settings.databases');
            Route::post('/databases/phpmyadmin', [DevSettingController::class, 'phpMyAdminLink'])->name('dev_settings.phpmyadmin_link');
            Route::post('/test-topic', [DevSettingController::class, 'testTopicBroadcast'])->name('dev_settings.test_topic');
            Route::put('/reviewer-accounts', [DevSettingController::class, 'updateReviewerAccounts'])->name('dev_settings.reviewer_accounts');
            Route::put('/pusher', [DevSettingController::class, 'updatePusher'])->name('dev_settings.pusher');
            Route::put('/production-pusher', [DevSettingController::class, 'updateProductionPusher'])->name('dev_settings.production_pusher');
            Route::post('/test-broadcast', [DevSettingController::class, 'testBroadcast'])->name('dev_settings.test_broadcast');
            Route::post('/git', [DevSettingController::class, 'initGit'])->name('dev_settings.git');
            Route::post('/github-repo', [DevSettingController::class, 'createGithubRepo'])->name('dev_settings.github_repo');
            Route::delete('/git', [DevSettingController::class, 'disconnectGit'])->name('dev_settings.git_disconnect');
            Route::post('/push', [DevSettingController::class, 'pushToGithub'])->name('dev_settings.push');
            Route::post('/pull', [DevSettingController::class, 'pullFromGithub'])->name('dev_settings.pull');
            Route::post('/fetch', [DevSettingController::class, 'fetchRemote'])->name('dev_settings.fetch');
            Route::post('/commit', [DevSettingController::class, 'commitChanges'])->name('dev_settings.commit');
            Route::post('/branch/switch', [DevSettingController::class, 'switchBranch'])->name('dev_settings.branch_switch');
            Route::post('/branch/create', [DevSettingController::class, 'createBranch'])->name('dev_settings.branch_create');
            Route::get('/diff', [DevSettingController::class, 'getFileDiff'])->name('dev_settings.diff');
            Route::put('/app-name', [DevSettingController::class, 'updateAppName'])->name('dev_settings.app_name');
            Route::post('/api-token', [DevSettingController::class, 'generateApiToken'])->name('dev_settings.api_token');
            Route::put('/admin-credentials', [DevSettingController::class, 'updateAdminCredentials'])->name('dev_settings.admin_credentials');
            Route::put('/local-mail', [DevSettingController::class, 'updateLocalMail'])->name('dev_settings.local_mail');
            Route::put('/production-mail', [DevSettingController::class, 'updateProductionMail'])->name('dev_settings.production_mail');
            Route::post('/deploy', [DevSettingController::class, 'deploy'])->name('dev_settings.deploy');
            Route::put('/deploy-config', [DevSettingController::class, 'saveDeployConfig'])->name('dev_settings.deploy_config');
            Route::post('/base-firebase', [DevSettingController::class, 'uploadBaseFirebase'])->name('dev_settings.base_firebase');
            Route::post('/base-firebase-delete', [DevSettingController::class, 'deleteBaseFirebase'])->name('dev_settings.base_firebase_delete');
            Route::post('/flavor-firebase', [DevSettingController::class, 'uploadFlavorFirebase'])->name('dev_settings.flavor_firebase');
            Route::post('/flavor-firebase-delete', [DevSettingController::class, 'deleteFlavorFirebase'])->name('dev_settings.flavor_firebase_delete');
            Route::post('/logo', [DevSettingController::class, 'uploadLogo'])->name('dev_settings.logo');
            Route::post('/dark-logo', [DevSettingController::class, 'uploadDarkLogo'])->name('dev_settings.dark_logo');
            Route::post('/favicon', [DevSettingController::class, 'uploadFavicon'])->name('dev_settings.favicon');
            Route::post('/generate-api-docs', [DevSettingController::class, 'generateApiDocs'])->name('dev_settings.generate_api_docs');
        });
    }
});
