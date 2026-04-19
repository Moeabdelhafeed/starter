<?php

namespace App\Http\Controllers\Admin\Dashboard;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\Language;
use App\Models\Page;
use App\Models\TranslationKey;
use App\Models\User;
use Inertia\Inertia;
use Spatie\Permission\Models\Role;

class DashboardController extends Controller
{
    public function index()
    {
        // Admin users (web guard with non-user roles)
        $adminCount = User::whereHas('roles', function ($query) {
            $query->where('guard_name', 'web');
        })->count();

        // App users (api guard with user role)
        $appUserCount = User::whereHas('roles', function ($query) {
            $query->where('name', 'user')->where('guard_name', 'api');
        })->count();

        // Other statistics
        $roleCount = Role::where('guard_name', 'web')->count();
        $languageCount = Language::where('is_active', true)->count();
        $pageCount = Page::count();
        $translationCount = TranslationKey::count();
        $activityCount = ActivityLog::count();

        // Recent activity logs
        $recentActivities = ActivityLog::latest()
            ->take(5)
            ->get(['id', 'causer_name', 'action', 'subject_type', 'created_at']);

        return Inertia::render('Dashboard', [
            'stats' => [
                'admins' => $adminCount,
                'appUsers' => $appUserCount,
                'roles' => $roleCount,
                'languages' => $languageCount,
                'pages' => $pageCount,
                'translations' => $translationCount,
                'activities' => $activityCount,
            ],
            'recentActivities' => $recentActivities,
            'isLocal' => app()->environment('local'),
        ]);
    }
}
