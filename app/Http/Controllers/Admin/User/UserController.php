<?php

namespace App\Http\Controllers\Admin\User;

use App\Http\Controllers\Controller;
use App\Models\Role;
use App\Models\User;
use App\Traits\Exportable;
use App\Traits\HasSoftDeleteActions;
use Illuminate\Http\Request;
use Inertia\Inertia;

class UserController extends Controller
{
    use HasSoftDeleteActions;

    protected string $model = User::class;

    public function index(Request $request)
    {
        $search = $request->input('search');
        $role = $request->input('role');
        $isActive = $request->input('is_active');
        $trashed = $request->input('trashed');

        $users = User::query()->whereHas('roles', function ($query) {
            $query->where('guard_name', 'web');
        })
            ->when($trashed === 'only', fn ($q) => $q->onlyTrashed())
            ->when($trashed === 'with', fn ($q) => $q->withTrashed())
            ->when($search, function ($query, $search) {
                $query->where(function ($q) use ($search) {
                    $q->where('name', 'like', "%{$search}%")
                        ->orWhere('email', 'like', "%{$search}%");
                });
            })
            ->with(['roles', 'image'])
            ->when($role, function ($query) use ($role) {
                $query->role($role); // Spatie scope (DB-level filter)
            })
            ->when($isActive !== null && $isActive !== 'all', function ($query) use ($isActive) {
                $query->where('is_active', $isActive);
            })
            ->latest()
            ->paginate(10)
            ->withQueryString();

        return Inertia::render('User/Index', [
            'users' => Inertia::scroll($users),
            'roles' => Role::where('guard_name', 'web')->get(),
            'filters' => [
                'search' => $search,
                'role' => $role,
                'is_active' => $isActive,
                'trashed' => $trashed,
            ],
            'hasSoftDeletes' => true,
            'hasExport' => in_array(Exportable::class, class_uses_recursive(User::class)),
        ]);
    }

    public function export(Request $request)
    {
        $search = $request->input('search');
        $role = $request->input('role');
        $isActive = $request->input('is_active');

        return User::query()->whereHas('roles', function ($q) {
            $q->where('guard_name', 'web');
        })
            ->when($search, function ($query, $search) {
                $query->where(function ($q) use ($search) {
                    $q->where('name', 'like', "%{$search}%")
                        ->orWhere('email', 'like', "%{$search}%");
                });
            })
            ->when($role, fn ($q) => $q->role($role))
            ->when($isActive !== null && $isActive !== 'all', fn ($q) => $q->where('is_active', $isActive))
            ->latest()
            ->exportCsv('users-'.now()->format('Y-m-d-His').'.csv');
    }

    public function update(Request $request, User $user)
    {
        if ($user->id === auth()->id() && array_key_exists('is_active', $request->all()) && ! $request->boolean('is_active')) {
            return redirect()->back()->with('error', __('admin.cannot_deactivate_self'));
        }

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', 'unique:users,email,'.$user->id],
            'password' => ['nullable', 'string', 'min:8'],
            'role' => ['required', 'string', 'exists:roles,name'],
            'image' => ['nullable', 'image', 'max:2048'],
            'remove_image' => ['nullable', 'boolean'],
            'is_active' => ['boolean'],
        ]);

        $user->forceFill([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'is_active' => $request->boolean('is_active', true),
        ]);

        if ($request->filled('password')) {
            $user->password = bcrypt($validated['password']);
        }

        if ($request->hasFile('image')) {
            $user->saveImage($request->file('image'), 'users');
        } elseif ($request->boolean('remove_image')) {
            $user->deleteImage();
        }

        $user->save();

        if ($user->id === auth()->id()) {
            $user->syncRoles($user->roles->pluck('name')->toArray());
        } else {
            $user->syncRoles([$validated['role']]);
        }

        return redirect()->back()->with('success', __('admin.updated_successfully'));
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'string', 'min:8'],
            'role' => ['required', 'string', 'exists:roles,name'],
            'image' => ['nullable', 'image', 'max:2048'],
            'is_active' => ['boolean'],
        ]);

        $user = User::create([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'is_active' => $request->boolean('is_active', true),
            'password' => bcrypt($validated['password']),
        ]);

        if ($request->hasFile('image')) {
            $user->saveImage($request->file('image'), 'users');
        }

        $user->assignRole($validated['role']);

        return redirect()->back()->with('success', __('admin.created_successfully'));
    }

    public function destroy(User $user)
    {
        if ($user->id === auth()->id()) {
            return redirect()->back()->with('error', __('admin.cannot_delete_self'));
        }

        $user->delete();

        return redirect()->back()->with('success', __('admin.deleted_successfully'));
    }

    public function bulkUpdate(Request $request)
    {
        $validated = $request->validate([
            'ids' => ['required', 'array', 'exists:users,id'],
            'is_active' => ['required', 'boolean'],
        ]);

        $ids = array_filter($validated['ids'], function ($id) {
            return $id != auth()->id();
        });

        User::whereIn('id', $ids)->update(['is_active' => $validated['is_active']]);

        return redirect()->back()->with('success', __('admin.updated_successfully'));
    }

    public function bulkDestroy(Request $request)
    {
        $validated = $request->validate([
            'ids' => ['required', 'array', 'exists:users,id'],
        ]);

        $ids = array_filter($validated['ids'], function ($id) {
            return $id != auth()->id();
        });

        User::whereIn('id', $ids)->delete();

        return redirect()->back()->with('success', __('admin.deleted_successfully'));
    }
}
