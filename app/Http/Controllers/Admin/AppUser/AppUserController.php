<?php

namespace App\Http\Controllers\Admin\AppUser;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Traits\Exportable;
use App\Traits\HasSoftDeleteActions;
use Illuminate\Http\Request;
use Inertia\Inertia;

class AppUserController extends Controller
{
    use HasSoftDeleteActions;

    protected string $model = User::class;

    public function index(Request $request)
    {
        $search = $request->input('search');
        $isActive = $request->input('is_active');
        $isVerified = $request->input('is_verified');
        $pendingDeletion = $request->input('pending_deletion');
        $trashed = $request->input('trashed');
        $userType = $request->input('user_type');
        $platform = $request->input('platform');

        $users = User::query()->whereHas('roles', function ($query) {
            $query->where('guard_name', 'api');
        })
            ->when($trashed === 'only', fn ($q) => $q->onlyTrashed())
            ->when($trashed === 'with', fn ($q) => $q->withTrashed())
            ->when($search, function ($query, $search) {
                $query->where(function ($q) use ($search) {
                    $q->where('name', 'like', "%{$search}%")
                        ->orWhere('email', 'like', "%{$search}%")
                        ->orWhere('phone', 'like', "%{$search}%")
                        ->orWhere('username', 'like', "%{$search}%")
                        ->orWhere('guest_id', 'like', "%{$search}%");
                });
            })
            ->when($isActive !== null && $isActive !== 'all', function ($query) use ($isActive) {
                $query->where('is_active', $isActive);
            })
            ->when($isVerified !== null && $isVerified !== 'all', function ($query) use ($isVerified) {
                if ($isVerified == '1') {
                    $query->whereNotNull('verified_at');
                } else {
                    $query->whereNull('verified_at');
                }
            })
            ->when($pendingDeletion === 'only', fn ($q) => $q->whereNotNull('account_deleted_at'))
            ->when($pendingDeletion === 'exclude', fn ($q) => $q->whereNull('account_deleted_at'))
            ->when($userType === 'guest', fn ($q) => $q->where('is_guest', true))
            ->when($userType === 'user', fn ($q) => $q->where('is_guest', false))
            ->when(in_array($platform, ['web', 'ios', 'android'], true), fn ($q) => $q->where('platform', $platform))
            ->latest()
            ->paginate(10)
            ->withQueryString();

        $countsRaw = User::query()
            ->whereHas('roles', fn ($q) => $q->where('guard_name', 'api'))
            ->selectRaw('is_guest, platform, count(*) as total')
            ->groupBy('is_guest', 'platform')
            ->get();

        $stats = [
            'guests' => (int) $countsRaw->where('is_guest', 1)->sum('total'),
            'users' => (int) $countsRaw->where('is_guest', 0)->sum('total'),
            'by_platform' => [
                'web' => (int) $countsRaw->where('platform', 'web')->sum('total'),
                'ios' => (int) $countsRaw->where('platform', 'ios')->sum('total'),
                'android' => (int) $countsRaw->where('platform', 'android')->sum('total'),
            ],
        ];

        return Inertia::render('AppUser/Index', [
            'users' => Inertia::scroll($users),
            'filters' => [
                'search' => $search,
                'is_active' => $isActive,
                'is_verified' => $isVerified,
                'trashed' => $trashed,
                'pending_deletion' => $pendingDeletion,
                'user_type' => $userType,
                'platform' => $platform,
            ],
            'stats' => $stats,
            'hasSoftDeletes' => true,
            'hasExport' => in_array(Exportable::class, class_uses_recursive(User::class)),
        ]);
    }

    public function export(Request $request)
    {
        $search = $request->input('search');
        $isActive = $request->input('is_active');
        $isVerified = $request->input('is_verified');
        $userType = $request->input('user_type');
        $platform = $request->input('platform');

        return User::query()->whereHas('roles', function ($q) {
            $q->where('guard_name', 'api');
        })
            ->when($search, function ($query, $search) {
                $query->where(function ($q) use ($search) {
                    $q->where('name', 'like', "%{$search}%")
                        ->orWhere('email', 'like', "%{$search}%")
                        ->orWhere('phone', 'like', "%{$search}%")
                        ->orWhere('username', 'like', "%{$search}%")
                        ->orWhere('guest_id', 'like', "%{$search}%");
                });
            })
            ->when($isActive !== null && $isActive !== 'all', fn ($q) => $q->where('is_active', $isActive))
            ->when($isVerified !== null && $isVerified !== 'all', function ($query) use ($isVerified) {
                $isVerified == '1' ? $query->whereNotNull('verified_at') : $query->whereNull('verified_at');
            })
            ->when($userType === 'guest', fn ($q) => $q->where('is_guest', true))
            ->when($userType === 'user', fn ($q) => $q->where('is_guest', false))
            ->when(in_array($platform, ['web', 'ios', 'android'], true), fn ($q) => $q->where('platform', $platform))
            ->latest()
            ->exportCsv('app-users-'.now()->format('Y-m-d-His').'.csv');
    }

    public function update(Request $request, User $user)
    {
        $rules = [
            'name' => ['required', 'string', 'max:255'],
            'password' => ['nullable', 'string', 'min:8'],
            'is_active' => ['boolean'],
        ];

        if ($this->hasField('email')) {
            $rules['email'] = ['nullable', 'string', 'email', 'max:255', 'unique:users,email,'.$user->id];
        }
        if ($this->hasField('phone')) {
            $rules['phone'] = ['nullable', 'string', 'max:255', 'unique:users,phone,'.$user->id];
        }
        if ($this->hasField('username')) {
            $rules['username'] = ['nullable', 'string', 'alpha_dash', 'max:255', 'unique:users,username,'.$user->id];
        }

        $validated = $request->validate($rules);

        $user->forceFill([
            'name' => $validated['name'],
            'is_active' => $request->boolean('is_active', true),
        ]);

        if ($this->hasField('email') && array_key_exists('email', $validated)) {
            $user->email = $validated['email'];
        }
        if ($this->hasField('phone') && array_key_exists('phone', $validated)) {
            $user->phone = $validated['phone'];
        }
        if ($this->hasField('username') && array_key_exists('username', $validated)) {
            $user->username = $validated['username'];
        }

        if ($request->filled('password')) {
            $user->password = bcrypt($validated['password']);
        }

        $user->save();

        return redirect()->back()->with('success', __('admin.updated_successfully'));
    }

    private function hasField(string $field): bool
    {
        $identifiers = array_map('trim', explode(',', env('AUTH_IDENTIFIERS', 'email')));
        if (in_array($field, $identifiers)) {
            return true;
        }

        return filter_var(env('HAS_'.strtoupper($field).'_FIELD', false), FILTER_VALIDATE_BOOLEAN);
    }

    public function destroy(User $user)
    {
        $user->delete();

        return redirect()->back()->with('success', __('admin.deleted_successfully'));
    }

    public function bulkUpdate(Request $request)
    {
        $validated = $request->validate([
            'ids' => ['required', 'array', 'exists:users,id'],
            'is_active' => ['required', 'boolean'],
        ]);

        User::whereIn('id', $validated['ids'])->update(['is_active' => $validated['is_active']]);

        return redirect()->back()->with('success', __('admin.updated_successfully'));
    }

    public function bulkDestroy(Request $request)
    {
        $validated = $request->validate([
            'ids' => ['required', 'array', 'exists:users,id'],
        ]);

        User::whereIn('id', $validated['ids'])->delete();

        return redirect()->back()->with('success', __('admin.deleted_successfully'));
    }
}
