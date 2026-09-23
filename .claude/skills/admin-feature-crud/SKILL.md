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

**If the feature is gated by a `HAS_*` DevSettings/env flag (like `HAS_PAGES`, `HAS_APP_SETTINGS`, `HAS_DYNAMIC_STORAGE`), the permission is NOT auto-hidden when the flag is off.** `RolesController` (`app/Http/Controllers/Admin/Roles/RolesController.php`) has a private `disabledFeaturePermissions()` method — a `['permission_name' => bool $enabled]` map — that filters which permissions appear in the Roles create/edit UI (`index()`) and which are preserved-but-hidden on update (`update()`). Add your new `'feature_name' => filter_var(env('HAS_FEATURE_NAME', true), FILTER_VALIDATE_BOOLEAN)` entry there too, or the permission stays selectable/visible forever even with the feature fully disabled (this exact gap existed for `dynamic_storage`/`app_settings` — RoleSeeder created the permission rows unconditionally, per the "always seed the row" rule below, but nobody had wired the corresponding `disabledFeaturePermissions()` entries). The permission row itself should still ALWAYS be seeded unconditionally in `RoleSeeder.php` regardless of the flag — this only controls UI visibility, not existence, so toggling the flag back on later doesn't lose existing role assignments.

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
9. **If gated by a `HAS_*` env flag** (a DevSettings on/off toggle for the whole feature, not just a permission check) — add the flag to `RolesController::disabledFeaturePermissions()` (see "Roles & Permissions" above) so the permission disappears from the Roles UI when the feature is off. Also add the flag to CLAUDE.md's "Environment Variables" reference table.

10. **If the feature sends a notification the code fires itself** — do NOT hardcode the copy, and do
    NOT leave the admin to create the template by hand. Register it as a **system notification
    template**: see "Automatic notifications are system templates" below.

If the model needs soft deletes, see `reference/soft-deletes.md` for the full 5-step wiring (model, migration, `HasSoftDeleteActions` controller trait, routes, frontend `TrashedFilter`/`BulkActions`).


## Automatic notifications are system templates

A notification the application sends on its own — an order shipped, a booking reminder, a password
changed — is **copy the admin owns and wiring the code owns**. Both halves have to hold at once:
the admin has to be able to reword it in every language without a deploy, and the code has to be
able to find the same row tomorrow.

`NotificationTemplate.system_type` is that contract. Non-null means the row exists because code
looks it up:

```php
$template = NotificationTemplate::forSystemType('order_shipped');
```

The CMS keeps it editable and switchable, and refuses to delete it or change its `slug`,
`trigger_model` or `trigger_event` — `NotificationTemplate::booting()` blocks the delete on the
model, the controller reports the refusal, and the table hides the button.
`tests/Feature/Admin/SystemNotificationTemplateTest.php` pins all of it.

**When you add a feature that sends its own notification:**

1. Add a constant for the type next to the code that sends it — the string is an identifier the
   seeder and the sender share, never typed twice.
2. Seed the row in `database/seeders/SystemNotificationTemplateSeeder.php`, keyed on
   `system_type`, with copy in every active language. The starter ships no automatic
   notifications, so **that seeder does not exist yet — create it and register it in
   `DatabaseSeeder`** (after `LanguageSeeder`, like `PageSeeder`):

   ```php
   $template = NotificationTemplate::firstOrCreate(
       ['system_type' => 'order_shipped'],
       ['slug' => 'order_shipped', 'topic' => 'users', 'is_active' => true],
   );

   // firstOrCreate so re-running never overwrites copy the admin has reworded.
   if ($template->wasRecentlyCreated) {
       $template->saveTranslations([
           'title' => ['en' => 'Your order is on its way', 'ar' => '...'],
           'body' => ['en' => '...', 'ar' => '...'],
       ]);
   }
   ```
3. Send through the lookup, and **handle null** — an install whose seeder has not run has no row:

   ```php
   $template = NotificationTemplate::forSystemType(self::ORDER_SHIPPED);

   if ($template?->is_active) {
       SendNotificationTemplate::dispatch($template->id);
   }
   ```
4. Never set `system_type` from a controller. The CMS cannot create one and must not be able to:
   the key belongs to the code that reads it.

The same rule in one line: **if the code decides when a notification goes out, the code owns a
`system_type` for it — the admin owns only the words.**

## Pages the app depends on are protected, not deleted

A mobile build links to Terms and Privacy by slug. A page deleted from the CMS is a dead screen in
a shipped binary that no server-side fix can reach.

`PROTECTED_PAGES` (`config('features.protected_pages')`, read through `App\Helpers\ProtectedPages`)
is the list of slugs that may never be deleted from the CMS — **nor deactivated**, since
`Api\Page\PageController::show()` filters on `active()` and an inactive page answers 404 exactly
like a missing one. `Page::booting()` guards both; `bulkUpdate()` is a query-builder mass update
that fires no model events, so it carries its own check. It is edited in **DevSettings →
Environment → Protected Pages**, where the CMS follows the list — adding a slug runs `PageSeeder`
so the page exists straight away, removing one deletes that page — and not per row in the CMS:
protection is a property of what the app depends on, and an admin who could untick it could delete
the page a moment later. The slug of a protected page is frozen too — renaming it is otherwise the
way out of the list.

Adding a page the app links to by slug? Add it in DevSettings — nothing else. Dropping a slug
**deletes that page and its translations**, with no undo; only the slugs removed by that save are
touched, never every page the list omits. `PageSeeder` itself only ever creates, so
`php artisan db:seed --class=PageSeeder` on a deploy is purely additive — deleting is the
DevSettings action's job, because only it knows what the list just dropped. `tests/Feature/Admin/ProtectedPagesTest.php` pins it.
