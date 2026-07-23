---
name: admin-feature-crud
description: "Use whenever creating or modifying an admin panel feature (Admin/{Feature}Controller, its model, migration, routes, permission, or Vue Index page) in this Laravel + Inertia starter. Covers directory structure, the dual-guard system, web route conventions (bulk-before-parameterized, permission middleware, kebab/snake naming), the standard index/store/update/destroy/bulk controller shape, RoleSeeder permission wiring, and the New Feature Checklist end-to-end. Trigger on: 'add a new feature/module/CRUD', 'add a permission', 'new admin controller', 'RoleSeeder', route ordering questions. For the reusable model traits (HasImage, HasVideo, LogsActivity, NotifiesAdmin, Exportable, HasUserTimezone, BlocksRestoreIfParentTrashed, HasTranslations) see reference/traits.md; for wiring Laravel's SoftDeletes into a feature see reference/soft-deletes.md. Do not use for Vue-only UI work (see vue-admin-ui-patterns) or mobile API endpoints (see mobile-auth-identity)."
metadata:
  author: project
---

# Admin Feature CRUD

## Directory Structure

```
app/Http/Controllers/
├── Admin/          # Web admin panel (Inertia pages)
│   ├── Auth/       # Login/logout
│   ├── {Feature}/  # One folder per feature
│   └── ...
└── Api/            # Mobile app REST API

app/Models/         # Eloquent models
app/Traits/         # HasImage, HasVideo, LogsActivity, HasTranslations, HasSoftDeleteActions, NotifiesAdmin, BlocksRestoreIfParentTrashed, Exportable, HasUserTimezone
app/Helpers/        # ApiResponse, EmailHelper, FCMHelper, SendSMS, SendWhatsapp

resources/js/
├── pages/{Feature}/Index.vue      # One page per feature
├── components/{feature-name}/     # Feature components (kebab-case folder)
├── components/Shared/             # Navbar, DeleteModal, BulkActions, BulkDeleteModal, RestoreModal, BulkRestoreModal, TrashedFilter
├── components/ui/                 # Shadcn-style primitives (Button, Input, Select, Table, Checkbox, TranslatableInput)
├── composables/                   # Vue composables (useTranslations)
├── layouts/default.vue            # Main layout (Navbar + toasts)
├── locales/en.json, ar.json       # Frontend translations
└── resources/                     # Source images (logo, favicon) — synced with public/
```

## Dual Guard System

- **Web guard (`web`):** Admin panel users. Roles: `super_admin`, `fallback`, custom roles.
- **API guard (`api`):** Mobile app users. Role: `user`. (Full mobile API auth: see `mobile-auth-identity` skill.)
- Permissions are web-guard only. Each feature has one permission (e.g. `users`, `roles`, `translations`).

## Web Routes (`routes/web.php`)

- All behind `middleware('auth')`.
- Feature routes wrapped with `middleware('permission:feature_name')`.
- Bulk routes (`/bulk-update`, `/bulk-destroy`) MUST come BEFORE parameterized routes (`/{model}`).
- URL: kebab-case (`/activity-logs`). Route names: snake_case (`activity_logs.store`).
- Profile routes have no permission middleware (any authenticated user).
- DevSettings routes are conditional: `app()->environment('local')`.

## Roles & Permissions (RoleSeeder)

When adding a new feature that needs access control:
1. Add permission: `$perm = Permission::firstOrCreate(['name' => 'feature_name', 'guard_name' => 'web']);`
2. Assign to super_admin: `$perm->assignRole($super_admin);`
3. Re-seed: `php artisan db:seed --class=RoleSeeder`

Protected roles that must never be deleted: `super_admin`, `fallback`.

## Controllers (Admin)

Every admin feature controller follows this structure:
- `index()` — query with search/filters, `->paginate(10)->withQueryString()`, render with `Inertia::render()` and `Inertia::scroll()`.
- `store()` — validate, create, handle image with trait, flash success.
- `update()` — validate, update, handle image with trait, flash success.
- `destroy()` — delete with safety checks, flash success.
- `bulkUpdate()` / `bulkDestroy()` — validate `ids` array, exclude self where relevant.

Flash messages always use `__('admin.key')` — never hardcode text in controllers.

**ALWAYS reach for the traits** in `reference/traits.md` instead of hand-rolling image/video handling, activity logging, admin notifications, CSV export, timezone-safe dates, translations, or restore-blocking. Never call `Storage::disk()->delete()` directly in a controller.

## New Feature Checklist

When creating a new feature, follow this order:

1. **Model + Migration** — `app/Models/{Feature}.php` with `$fillable`, traits (`LogsActivity`, `HasImage` if needed). Migration with `php artisan make:migration`.
2. **Controller** — `app/Http/Controllers/Admin/{Feature}/{Feature}Controller.php` with index/store/update/destroy/bulkDestroy (+ bulkUpdate if needed).
3. **Permission** — Add to `RoleSeeder.php`, assign to `super_admin`, run `php artisan db:seed --class=RoleSeeder`.
4. **Routes** — In `routes/web.php` with `middleware('permission:feature_name')`. Bulk routes BEFORE parameterized routes.
5. **Translations** — Both `en.json` + `ar.json` AND `lang/en/admin.php` + `lang/ar/admin.php` (see `translations-i18n` skill).
6. **Page** — `resources/js/pages/{Feature}/Index.vue` with Default layout (see `vue-admin-ui-patterns` skill).
7. **Components** — In `resources/js/components/{feature-name}/`:
   - `{Feature}Filters.vue`
   - `{Feature}Table.vue`
   - `{Feature}CreateModal.vue`
   - `{Feature}EditModal.vue`
8. **Navbar** — Add link in `Navbar.vue` with permission check: `v-if="page.props.auth.permissions.find(p => p === 'feature_name')"`.

If the model needs soft deletes, see `reference/soft-deletes.md` for the full 5-step wiring (model, migration, `HasSoftDeleteActions` controller trait, routes, frontend `TrashedFilter`/`BulkActions`).
