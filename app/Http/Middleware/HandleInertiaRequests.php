<?php

namespace App\Http\Middleware;

use App\Models\AdminNotification;
use Illuminate\Http\Request;
use Inertia\Middleware;

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
        return [
            ...parent::share($request),
            'name' => config('app.name'),
            'auth' => [
                'user' => $request->user() ? $request->user()->load('image') : null,
                'roles' => $request->user() ? $request->user()->getRoleNames() : [],
                'permissions' => $request->user() ? $request->user()->getAllPermissions()->pluck('name') : [],
            ],
            'notifications' => [
                'unread_count' => $request->user()
                    ? AdminNotification::forUser($request->user())->unread()->count()
                    : 0,
            ],
            'locale' => ['code' => app()->getLocale(), 'dir' => app()->getLocale() == 'ar' ? 'rtl' : 'ltr', 'name' => app()->getLocale() == 'ar' ? 'عربي' : 'English'],
            'success' => session('success'),
            'error' => session('error'),
            'app_users' => filter_var(env('APP_USERS'), FILTER_VALIDATE_BOOLEAN),
            'app_guests' => filter_var(env('APP_GUESTS', false), FILTER_VALIDATE_BOOLEAN),
            'has_translations' => filter_var(env('HAS_TRANSLATIONS', true), FILTER_VALIDATE_BOOLEAN),
            'has_notification_templates' => filter_var(env('HAS_NOTIFICATION_TEMPLATES', true), FILTER_VALIDATE_BOOLEAN),
            'is_local' => app()->environment('local'),
            'is_testing' => filter_var(env('IS_TESTING', false), FILTER_VALIDATE_BOOLEAN),
            'multi_session' => (bool) config('auth.multi_session_enabled'),
            'admin_credentials' => (app()->environment('local') && ! $request->user()) ? [
                'email' => env('ADMIN_EMAIL'),
                'password' => env('ADMIN_PASSWORD'),
            ] : null,
            'auth_identifiers' => array_map('trim', explode(',', env('AUTH_IDENTIFIERS', 'email'))),
            'auth_fields' => [
                'email' => in_array('email', array_map('trim', explode(',', env('AUTH_IDENTIFIERS', 'email')))) || filter_var(env('HAS_EMAIL_FIELD', true), FILTER_VALIDATE_BOOLEAN),
                'phone' => in_array('phone', array_map('trim', explode(',', env('AUTH_IDENTIFIERS', 'email')))) || filter_var(env('HAS_PHONE_FIELD', false), FILTER_VALIDATE_BOOLEAN),
                'username' => in_array('username', array_map('trim', explode(',', env('AUTH_IDENTIFIERS', 'email')))) || filter_var(env('HAS_USERNAME_FIELD', false), FILTER_VALIDATE_BOOLEAN),
            ],
        ];
    }
}
