<?php

namespace App\Http\Controllers\Admin\AppUser;

use App\Helpers\AuthIdentity;
use App\Helpers\PhoneNumber;
use App\Http\Controllers\Controller;
use App\Models\User;
use App\Rules\AllowedPhoneCountry;
use App\Traits\Exportable;
use App\Traits\HasSoftDeleteActions;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Validation\Rules\Password;
use Inertia\Inertia;

class AppUserController extends Controller
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
            ->scrollPaginate(10);

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
                'search' => $request->input('search'),
                'is_active' => $request->input('is_active'),
                'is_verified' => $request->input('is_verified'),
                'trashed' => $request->input('trashed'),
                'pending_deletion' => $request->input('pending_deletion'),
                'user_type' => $request->input('user_type'),
                'platform' => $request->input('platform'),
            ],
            'stats' => $stats,
            'hasSoftDeletes' => true,
            'hasExport' => in_array(Exportable::class, class_uses_recursive(User::class)),
        ]);
    }

    public function export(Request $request)
    {
        return $this->filteredQuery($request)
            ->exportCsv('app-users-'.now()->format('Y-m-d-His').'.csv');
    }

    /**
     * The app-user list the current filters describe. Shared by index() and
     * export() so the CSV always matches what is on screen.
     */
    protected function filteredQuery(Request $request): Builder
    {
        $search = $request->input('search');
        $isActive = $request->input('is_active');
        $isVerified = $request->input('is_verified');
        $pendingDeletion = $request->input('pending_deletion');
        $trashed = $request->input('trashed');
        $userType = $request->input('user_type');
        $platform = $request->input('platform');

        return User::query()->whereHas('roles', function ($query) {
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
            ->latest();
    }

    public function update(Request $request, User $user)
    {
        $this->authorizeTarget($user);

        $rules = [
            'name' => ['required', 'string', 'max:255'],
            'password' => ['nullable', 'string', Password::defaults()],
            'is_active' => ['boolean'],
        ];

        if (AuthIdentity::hasField('email')) {
            $rules['email'] = ['nullable', 'string', 'email', 'max:255', 'unique:users,email,'.$user->id];
        }
        if (AuthIdentity::hasField('phone')) {
            // Normalize BEFORE validating: `unique:users,phone` has to compare the
            // stored E.164 form, or the same number in another notation slips past
            // as "available". An unparseable value stays as typed so
            // AllowedPhoneCountry reports it on the `phone` field.
            if ($request->filled('phone')) {
                $request->merge([
                    'phone' => PhoneNumber::normalize($request->input('phone')) ?? $request->input('phone'),
                ]);
            }

            $rules['phone'] = [
                'nullable',
                'string',
                'max:255',
                'unique:users,phone,'.$user->id,
                new AllowedPhoneCountry(config('auth.allowed_phone_countries')),
            ];
        }
        if (AuthIdentity::hasField('username')) {
            $rules['username'] = ['nullable', 'string', 'alpha_dash', 'max:255', 'unique:users,username,'.$user->id];
        }

        $validated = $request->validate($rules);

        $user->forceFill([
            'name' => $validated['name'],
            'is_active' => $request->boolean('is_active', true),
        ]);

        if (AuthIdentity::hasField('email') && array_key_exists('email', $validated)) {
            $user->email = $validated['email'];
        }
        if (AuthIdentity::hasField('phone') && array_key_exists('phone', $validated)) {
            $user->phone = $validated['phone'];
        }
        if (AuthIdentity::hasField('username') && array_key_exists('username', $validated)) {
            $user->username = $validated['username'];
        }

        if ($request->filled('password')) {
            $user->password = bcrypt($validated['password']);
        }

        $user->save();

        return redirect()->back()->with('success', __('admin.updated_successfully'))->with('highlight', $user->id);
    }

    public function destroy(User $user)
    {
        $this->authorizeTarget($user);

        if ($user->is_reviewer) {
            return redirect()->back()->with('error', __('admin.cannot_delete_reviewer'));
        }

        // Guests are anonymous tracking rows — soft-delete adds no value.
        // Force-delete so the row is gone for good and the device_id is freed.
        $user->is_guest ? $user->forceDelete() : $user->delete();

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

        User::whereIn('id', $this->manageableIds($validated['ids']))
            ->update(['is_active' => $validated['is_active']]);

        return redirect()->back()->with('success', __('admin.updated_successfully'));
    }

    public function bulkDestroy(Request $request)
    {
        $validated = $request->validate([
            'ids' => ['required', 'array', 'exists:users,id'],
        ]);

        $ids = $this->manageableIds($validated['ids']);

        // Reviewer rows are protected (Apple / Google Play test accounts).
        // Soft-delete real users; force-delete guests in the same batch.
        User::whereIn('id', $ids)->where('is_reviewer', false)->where('is_guest', false)->delete();
        User::whereIn('id', $ids)->where('is_reviewer', false)->where('is_guest', true)
            ->get()
            ->each(fn (User $u) => $u->forceDelete());

        return redirect()->back()->with('success', __('admin.deleted_successfully'));
    }

    /**
     * Rows this controller may act on: app accounts only — an `api`-guard role or
     * a guest tracking row. The `{user}` binding itself is unscoped, so without
     * this an `app_users` holder could reach an admin-panel account.
     */
    protected function manageableQuery(): Builder
    {
        return User::query()
            ->withTrashed()
            ->where(fn ($q) => $q->where('is_guest', true)
                ->orWhereHas('roles', fn ($r) => $r->where('guard_name', 'api')));
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
}
