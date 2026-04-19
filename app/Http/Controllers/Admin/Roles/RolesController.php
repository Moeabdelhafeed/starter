<?php

namespace App\Http\Controllers\Admin\Roles;

use App\Http\Controllers\Controller;
use App\Models\Role;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Spatie\Permission\Models\Permission;

class RolesController extends Controller
{
    public function index(Request $request)
    {
        $search = $request->input('search');
        $isActive = $request->input('is_active');

        $roles = Role::query()
            ->where('guard_name', 'web')
            ->when($search, function ($query, $search) {
                $query->where('name', 'like', "%{$search}%");
            })
            ->when($isActive !== null && $isActive !== 'all', function ($query) use ($isActive) {
                $query->where('is_active', $isActive);
            })
            ->with('permissions')
            ->withCount('users')
            ->latest()
            ->paginate(10)
            ->withQueryString()
            ->through(fn ($role) => [
                'id' => $role->id,
                'name' => $role->name,
                'is_active' => $role->is_active,
                'users_count' => $role->users_count,
                'permissions' => $role->permissions->map(fn ($permission) => [
                    'id' => $permission->id,
                    'name' => $permission->name,
                ]),
            ]);

        return Inertia::render('Roles/Index', [
            'roles' => Inertia::scroll($roles),
            'permissions' => Permission::all(),
            'filters' => [
                'search' => $search,
                'is_active' => $isActive,
            ],
            'currentUserRoles' => auth()->user()->roles->pluck('name'),
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255', 'unique:roles,name'],
            'permissions' => ['array'],
            'permissions.*' => ['string', 'exists:permissions,name'],
            'is_active' => ['boolean'],
        ]);

        $role = Role::create(['name' => $validated['name']]);
        $role->is_active = $request->boolean('is_active', true);
        $role->save();

        if (! empty($validated['permissions'])) {
            $role->syncPermissions($validated['permissions']);
        }

        return redirect()->back()->with('success', __('admin.created_successfully'));
    }

    public function update(Request $request, Role $role)
    {
        if ($role->isProtected()) {
            return redirect()->back()->with('error', __('admin.cannot_update_protected_role'));
        }

        if (array_key_exists('is_active', $request->all()) && ! $request->boolean('is_active')) {
            if (auth()->user()->hasRole($role->name)) {
                return redirect()->back()->with('error', __('admin.cannot_update_current_role'));
            }
        }

        $validated = $request->validate([
            'permissions' => ['array'],
            'permissions.*' => ['string', 'exists:permissions,name'],
            'is_active' => ['boolean'],
        ]);

        if (isset($validated['permissions'])) {
            $role->syncPermissions($validated['permissions']);
        }

        if (isset($validated['is_active'])) {
            $role->is_active = $validated['is_active'];
            $role->save();
        }

        return redirect()->back()->with('success', __('admin.updated_successfully'));
    }

    public function destroy(Role $role)
    {
        if ($role->isProtected()) {
            return redirect()->back()->with('error', __('admin.cannot_delete_protected_role'));
        }

        if (auth()->user()->hasRole($role->name)) {
            return redirect()->back()->with('error', __('admin.cannot_delete_current_role'));
        }

        // Reassign users to fallback role if they have the role being deleted
        if ($role->users()->count() > 0) {
            $fallbackRole = Role::where('name', 'fallback')->first();
            if ($fallbackRole) {
                foreach ($role->users as $user) {
                    $user->assignRole($fallbackRole);
                }
            }
        }

        $role->delete();

        return redirect()->back()->with('success', __('admin.deleted_successfully'));
    }

    public function bulkUpdate(Request $request)
    {
        $validated = $request->validate([
            'ids' => ['required', 'array', 'exists:roles,id'],
            'is_active' => ['required', 'boolean'],
        ]);

        $currentUserRoles = auth()->user()->roles->pluck('name')->toArray();

        // Get IDs that are NOT protected
        $ids = Role::whereIn('id', $validated['ids'])
            ->whereNotIn('name', Role::$protectedRoles)
            ->where(function ($query) use ($currentUserRoles, $validated) {
                // If turning off, don't turn off current user's roles
                if (! $validated['is_active']) {
                    $query->whereNotIn('name', $currentUserRoles);
                }
            })
            ->pluck('id');

        Role::whereIn('id', $ids)->update(['is_active' => $validated['is_active']]);

        return redirect()->back()->with('success', __('admin.updated_successfully'));
    }

    public function bulkDestroy(Request $request)
    {
        $validated = $request->validate([
            'ids' => ['required', 'array', 'exists:roles,id'],
        ]);

        $currentUserRoles = auth()->user()->roles->pluck('name')->toArray();

        $rolesToDelete = Role::whereIn('id', $validated['ids'])
            ->whereNotIn('name', Role::$protectedRoles)
            ->whereNotIn('name', $currentUserRoles)
            ->get();

        $fallbackRole = Role::where('name', 'fallback')->first();

        foreach ($rolesToDelete as $role) {
            if ($role->users()->count() > 0 && $fallbackRole) {
                foreach ($role->users as $user) {
                    $user->assignRole($fallbackRole);
                }
            }
            $role->delete();
        }

        return redirect()->back()->with('success', __('admin.deleted_successfully'));
    }
}
