<?php

namespace App\Http\Controllers\Admin\User;

use App\Http\Controllers\Controller;
use App\Models\Role;
use App\Models\User;
use App\Traits\Exportable;
use App\Traits\HasSoftDeleteActions;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Inertia\Inertia;

class UserController extends Controller
{
    use HasSoftDeleteActions {
        restore as protected restoreModel;
        forceDelete as protected forceDeleteModel;
        bulkRestore as protected bulkRestoreModels;
        bulkForceDelete as protected bulkForceDeleteModels;
    }

    protected string $model = User::class;

    public function index(Request $request)
    {
        $users = $this->filteredQuery($request)
            ->with(['roles', 'image'])
            ->scrollPaginate(10);

        return Inertia::render('User/Index', [
            'users' => Inertia::scroll($users),
            'roles' => $this->assignableRoles(),
            'filters' => [
                'search' => $request->input('search'),
                'role' => $request->input('role'),
                'is_active' => $request->input('is_active'),
                'trashed' => $request->input('trashed'),
            ],
            'hasSoftDeletes' => true,
            'hasExport' => in_array(Exportable::class, class_uses_recursive(User::class)),
        ]);
    }

    public function export(Request $request)
    {
        return $this->filteredQuery($request)
            ->exportCsv('users-'.now()->format('Y-m-d-His').'.csv');
    }

    /**
     * The admin-panel user list the current filters describe. Shared by index()
     * and export() so the CSV always matches what is on screen.
     */
    protected function filteredQuery(Request $request): Builder
    {
        $search = $request->input('search');
        $role = $request->input('role');
        $isActive = $request->input('is_active');
        $trashed = $request->input('trashed');

        return User::query()->whereHas('roles', function ($query) {
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
            ->when($role, function ($query) use ($role) {
                $query->role($role); // Spatie scope (DB-level filter)
            })
            ->when($isActive !== null && $isActive !== 'all', function ($query) use ($isActive) {
                $query->where('is_active', $isActive);
            })
            ->latest();
    }

    public function update(Request $request, User $user)
    {
        $this->authorizeTarget($user);

        if ($user->id === auth()->id() && array_key_exists('is_active', $request->all()) && ! $request->boolean('is_active')) {
            return redirect()->back()->with('error', __('admin.cannot_deactivate_self'));
        }

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', 'unique:users,email,'.$user->id],
            'password' => ['nullable', 'string', Password::defaults()],
            'role' => ['required', 'string', Rule::in($this->assignableRoleNames())],
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

        return redirect()->back()->with('success', __('admin.updated_successfully'))->with('highlight', $user->id);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'string', Password::defaults()],
            'role' => ['required', 'string', Rule::in($this->assignableRoleNames())],
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

        return redirect()->back()->with('success', __('admin.created_successfully'))->with('highlight', $user->id);
    }

    public function destroy(User $user)
    {
        $this->authorizeTarget($user);

        if ($user->id === auth()->id()) {
            return redirect()->back()->with('error', __('admin.cannot_delete_self'));
        }

        $user->delete();

        return redirect()->back()->with('success', __('admin.deleted_successfully'));
    }

    public function restore(Request $request, $id)
    {
        $this->authorizeTargetId($id);

        return $this->restoreModel($request, $id);
    }

    public function forceDelete(Request $request, $id)
    {
        $this->authorizeTargetId($id);

        return $this->forceDeleteModel($request, $id);
    }

    public function bulkRestore(Request $request)
    {
        return $this->bulkRestoreModels($this->scopeBulkRequest($request));
    }

    public function bulkForceDelete(Request $request)
    {
        return $this->bulkForceDeleteModels($this->scopeBulkRequest($request));
    }

    public function bulkUpdate(Request $request)
    {
        $validated = $request->validate([
            'ids' => ['required', 'array', 'exists:users,id'],
            'is_active' => ['required', 'boolean'],
        ]);

        $ids = array_filter($this->manageableIds($validated['ids']), fn ($id) => $id != auth()->id());

        User::whereIn('id', $ids)->update(['is_active' => $validated['is_active']]);

        return redirect()->back()->with('success', __('admin.updated_successfully'));
    }

    public function bulkDestroy(Request $request)
    {
        $validated = $request->validate([
            'ids' => ['required', 'array', 'exists:users,id'],
        ]);

        $ids = array_filter($this->manageableIds($validated['ids']), fn ($id) => $id != auth()->id());

        User::whereIn('id', $ids)->delete();

        return redirect()->back()->with('success', __('admin.deleted_successfully'));
    }

    /**
     * Rows this controller may act on: admin-panel accounts (a `web`-guard role)
     * only, and — unless the actor is a super_admin — never a super_admin. The
     * `{user}` binding itself is unscoped, so every write goes through this.
     */
    protected function manageableQuery(): Builder
    {
        return User::query()
            ->withTrashed()
            ->whereHas('roles', fn ($q) => $q->where('guard_name', 'web'))
            ->unless(
                auth()->user()->hasRole('super_admin'),
                fn ($q) => $q->whereDoesntHave('roles', fn ($r) => $r->where('name', 'super_admin'))
            );
    }

    protected function authorizeTarget(User $user): void
    {
        $this->authorizeTargetId($user->getKey());
    }

    protected function authorizeTargetId(int|string $id): void
    {
        abort_unless(
            $this->manageableQuery()->whereKey($id)->exists(),
            403,
            __('admin.not_authorized_for_user')
        );
    }

    /**
     * @param  array<int, mixed>  $ids
     * @return array<int, int>
     */
    protected function manageableIds(array $ids): array
    {
        return $this->manageableQuery()->whereIn('id', $ids)->pluck('id')->all();
    }

    /**
     * Narrow a bulk request's `ids` to the rows this controller may act on before
     * handing it to the shared soft-delete actions.
     */
    protected function scopeBulkRequest(Request $request): Request
    {
        return $request->merge([
            'ids' => $this->manageableIds((array) $request->input('ids', [])),
        ]);
    }

    /**
     * Web-guard roles the current admin is allowed to grant.
     *
     * @return Collection<int, Role>
     */
    protected function assignableRoles(): Collection
    {
        return Role::where('guard_name', 'web')
            ->unless(auth()->user()->hasRole('super_admin'), fn ($q) => $q->where('name', '!=', 'super_admin'))
            ->get();
    }

    /**
     * @return array<int, string>
     */
    protected function assignableRoleNames(): array
    {
        return $this->assignableRoles()->pluck('name')->all();
    }
}
