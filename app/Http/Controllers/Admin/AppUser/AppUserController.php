<?php

namespace App\Http\Controllers\Admin\AppUser;

use App\Http\Controllers\Controller;
use App\Models\User;
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
        $trashed = $request->input('trashed');

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
                        ->orWhere('username', 'like', "%{$search}%");
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
            ->latest()
            ->paginate(10)
            ->withQueryString();

        return Inertia::render('AppUser/Index', [
            'users' => Inertia::scroll($users),
            'filters' => [
                'search' => $search,
                'is_active' => $isActive,
                'is_verified' => $isVerified,
                'trashed' => $trashed,
            ],
            'hasSoftDeletes' => true,
        ]);
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
