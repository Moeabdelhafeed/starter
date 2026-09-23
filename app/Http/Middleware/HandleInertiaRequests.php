<?php

namespace App\Http\Middleware;

use App\Models\AdminNotification;
use App\Models\AppSetting;
use App\Models\Page;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Inertia\Middleware;
use Tighten\Ziggy\Ziggy;

class HandleInertiaRequests extends Middleware
{
    /**
     * The root template that's loaded on the first page visit.
     *
     * @see https://inertiajs.com/server-side-setup#root-template
     *
     * @var string
     */
    protected $rootView = 'app';

    /**
     * Determines the current asset version.
     *
     * @see https://inertiajs.com/asset-versioning
     */
    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * Define the props that are shared by default.
     *
     * @see https://inertiajs.com/shared-data
     *
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        // Resolve once: every `$request->user()` call below used to re-walk the
        // roles/permissions relations, costing several queries per request.
        $user = $request->user();
        $user?->loadMissing('image');

        return [
            ...parent::share($request),
            'name' => config('app.name'),
            // Ziggy's route list. The @routes Blade directive covers the browser,
            // but server-side rendering has no such script — without this prop the
            // SSR bundle has no `route()` and every page render throws.
            'ziggy' => fn (): array => [
                ...(new Ziggy)->toArray(),
                'location' => $request->url(),
            ],
            'auth' => [
                // Only the fields the frontend actually reads — the full model
                // shipped every non-hidden column (device ids, OTP state, social
                // account links) into the page payload of every request.
                'user' => $user ? [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                    'image' => $user->image,
                ] : null,
                'roles' => $user ? $user->getRoleNames() : [],
                'permissions' => $user ? $user->getAllPermissions()->pluck('name') : [],
            ],
            'notifications' => [
                'unread_count' => $user
                    ? AdminNotification::forUser($user)->unread()->count()
                    : 0,
            ],
            'locale' => ['code' => app()->getLocale(), 'dir' => app()->getLocale() == 'ar' ? 'rtl' : 'ltr', 'name' => app()->getLocale() == 'ar' ? 'عربي' : 'English'],
            'success' => session('success'),
            // Id of the row a write just created/edited — tables glow it (see useHighlight).
            'highlight' => session('highlight'),
            'error' => session('error'),
            'app_users' => config('features.app_users'),
            'app_guests' => config('features.app_guests'),
            'has_translations' => config('features.translations'),
            'has_notification_templates' => config('features.notification_templates'),
            'has_pages' => config('features.pages'),
            'has_app_settings' => config('features.app_settings'),
            'has_dynamic_storage' => config('features.dynamic_storage'),
            'has_activity_logs' => config('features.activity_logs'),
            'has_ai_agent' => config('features.ai_agent'),
            'translation_warnings' => $this->translationWarnings($user),
            'is_local' => app()->environment('local'),
            'is_testing' => config('app.is_testing'),
            'multi_session' => (bool) config('auth.multi_session_enabled'),
            'admin_credentials' => (app()->environment('local') && ! $request->user()) ? [
                'email' => config('admin.email'),
                'password' => config('admin.password'),
            ] : null,
            'auth_identifiers' => config('auth.identifiers'),
            'auth_fields' => [
                'email' => in_array('email', config('auth.identifiers')) || config('auth.fields.email'),
                'phone' => in_array('phone', config('auth.identifiers')) || config('auth.fields.phone'),
                'username' => in_array('username', config('auth.identifiers')) || config('auth.fields.username'),
            ],
        ];
    }

    /**
     * Per-feature counts of rows missing a translation in some active locale, so
     * the navbar can flag features that need attention. Only computed for the
     * authenticated admin and only for features they can access.
     *
     * incompleteTranslationCount() loads every row plus its translations and
     * filters in PHP, so it is cached for a minute rather than run on every
     * single request. The real fix is to make that trait method SQL-only.
     *
     * @return array<string, int>
     */
    private function translationWarnings(?User $user): array
    {
        if (! $user) {
            return [];
        }

        $warnings = [];

        if (config('features.pages') && $user->can('pages')) {
            $warnings['pages'] = Cache::remember(
                'translation_warnings:pages',
                60,
                fn (): int => Page::incompleteTranslationCount()
            );
        }

        if (config('features.app_settings') && $user->can('app_settings')) {
            $warnings['app_settings'] = Cache::remember(
                'translation_warnings:app_settings',
                60,
                fn (): int => AppSetting::incompleteTranslationCount()
            );
        }

        return $warnings;
    }
}
