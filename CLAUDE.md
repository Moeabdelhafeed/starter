# Inertia Starter — Project Rules

## Stack

- **Backend:** Laravel 13, PHP 8.2+, MySQL
- **Frontend:** Vue 3 (`<script setup>` + TypeScript), Inertia.js v3, Tailwind CSS v4
- **Auth:** Sanctum (dual guard: `web` for admin, `api` for mobile app), Spatie Permission for roles
- **UI:** Reka UI (headless), lucide-vue-next (icons), vue-i18n (translations)
- **Build:** Vite 7, Wayfinder (route generation), Ziggy (route helpers)

---

## Architecture

### Directory Structure

```
app/Http/Controllers/
├── Admin/          # Web admin panel (Inertia pages)
│   ├── Auth/       # Login/logout
│   ├── {Feature}/  # One folder per feature
│   └── ...
└── Api/            # Mobile app REST API

app/Models/         # Eloquent models
app/Traits/         # HasImage, HasVideo, LogsActivity, HasTranslations, HasSoftDeleteActions, NotifiesAdmin, BlocksRestoreIfParentTrashed, Exportable
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

### Dual Guard System

- **Web guard (`web`):** Admin panel users. Roles: `super_admin`, `fallback`, custom roles.
- **API guard (`api`):** Mobile app users. Role: `user`.
- Permissions are web-guard only. Each feature has one permission (e.g. `users`, `roles`, `translations`).

### Routes

**Web routes** (`routes/web.php`):
- All behind `middleware('auth')`.
- Feature routes wrapped with `middleware('permission:feature_name')`.
- Bulk routes (`/bulk-update`, `/bulk-destroy`) MUST come BEFORE parameterized routes (`/{model}`).
- URL: kebab-case (`/activity-logs`). Route names: snake_case (`activity_logs.store`).
- Profile routes have no permission middleware (any authenticated user).
- DevSettings routes are conditional: `app()->environment('local')`.

**API routes** (`routes/api.php`):
- Public: register, login, firebase-login, forgot-password, translations, languages, pages.
- Protected: `middleware(['auth:sanctum', 'role:user', 'active'])`.
- Verified-only routes add `->middleware('verified')`.
- App user routes are conditional: `env('APP_USERS') === true`.
- Responses use `ApiResponse::success($data, $message, $token = null)` / `ApiResponse::error($message, $errors, $status)`.
- When `$token` is passed to `ApiResponse::success()`, it is included **both** at the top level (`response.token`) AND inside `data.token`. Use this for any endpoint that issues a Bearer token (`login`, `firebase-login`).
- `POST /api/register` does **not** issue a token. It creates the user and sends a verification OTP. The client must call `POST /api/login` afterwards to obtain a token (login on an unverified account triggers another verify OTP and returns a token usable with `verify-otp`).
- **Auto-verify on `username` identifier:** when the resolved identifier kind is `username` (no OTP delivery channel), `register` sets `verified_at = now()` immediately and skips OTP. The user can call `login` and receive a token without the verify-OTP step. Email/phone identifiers still go through the normal OTP flow.

**API Rate Limiting:**
Four custom rate limiters are defined in `AppServiceProvider::configureRateLimiting()`:

| Limiter | Purpose | Default | Middleware |
|---------|---------|---------|------------|
| `api` | General API endpoints + OTP **verification** | 60 req/1 min | `throttle:api` |
| `auth` | Login/register attempts | 5 req/1 min | `throttle:auth` |
| `otp` | OTP **sending** only (`send-otp`, `forgot-password`) | 3 req/5 min | `throttle:otp` |
| `translations` | Translations + languages endpoints (`/translations`, `/languages`) | **unlimited** when `IS_TESTING=true`; otherwise same as `api` | `throttle:translations` |

`throttle:otp` is applied **only to OTP-sending endpoints** to prevent SMS/email spam. OTP **verification** (`verify-otp`, `verify-forgot-password-otp`, `change-forgot-password`) uses standard `throttle:api` so users can retry codes without lockout.

There are **two OTP types** stored in the `otps` table:
- `verify` — activate account (sent via `POST /api/send-otp`, consumed by `POST /api/verify-otp`)
- `reset_password` — password reset (sent via `POST /api/forgot-password`, consumed by `verify-forgot-password-otp` + `change-forgot-password`)

Rate limits are configurable via `.env` variables (see Environment Variables section) or through the DevSettings CMS under "Validation Settings > API Rate Limiting".

When rate limit is exceeded, the API returns HTTP 429 with a translated error message.

### Roles & Permissions (RoleSeeder)

When adding a new feature that needs access control:
1. Add permission: `$perm = Permission::firstOrCreate(['name' => 'feature_name', 'guard_name' => 'web']);`
2. Assign to super_admin: `$perm->assignRole($super_admin);`
3. Re-seed: `php artisan db:seed --class=RoleSeeder`

Protected roles that must never be deleted: `super_admin`, `fallback`.

---

## Backend Patterns

### Controllers (Admin)

Every admin feature controller follows this structure:
- `index()` — query with search/filters, `->paginate(10)->withQueryString()`, render with `Inertia::render()` and `Inertia::scroll()`.
- `store()` — validate, create, handle image with trait, flash success.
- `update()` — validate, update, handle image with trait, flash success.
- `destroy()` — delete with safety checks, flash success.
- `bulkUpdate()` / `bulkDestroy()` — validate `ids` array, exclude self where relevant.

Flash messages always use `__('admin.key')` — never hardcode text in controllers.

### Traits — ALWAYS use them

**`HasImage`** — use on any model that has an image:
```php
use App\Traits\HasImage;
class MyModel extends Model { use HasImage; }
```
- `$model->saveImage($file, 'folder')` — deletes old, stores new, creates Image record with blurhash.
- `$model->deleteImage()` — deletes file + record.
- `$model->image` — the morphOne relationship.
- Never call `Storage::disk()->delete()` or `->image()->create()` directly in controllers.

**`HasVideo`** — use on any model that has a video:
- `$model->saveVideo($file, 'folder', $optionalThumbnail)` — stores video, optionally saves thumbnail via HasImage on the Video model.
- `$model->deleteVideo()` — deletes video file + thumbnail image + records.

**`LogsActivity`** — use on any model that should be audited:
- Auto-logs created/updated/deleted events to `activity_logs` table.
- Filters out sensitive fields (password, tokens, timestamps).
- Customizable via static `$logEvents` and `$logIgnoreFields`.

**`NotifiesAdmin`** — use on any model that should trigger admin notifications:
```php
use App\Traits\NotifiesAdmin;
class Order extends Model {
    use NotifiesAdmin;

    // Optional: customize which events trigger notifications (default: created, deleted)
    protected static array $notifyEvents = ['created', 'deleted'];

    // Optional: customize the notification type (default: snake_case plural of model name)
    protected static string $notifyType = 'orders';

    // Optional: control when to notify (e.g., only for certain conditions)
    protected function shouldNotify(string $event): bool
    {
        return $this->status === 'pending';
    }
}
```
- Automatically creates `AdminNotification` records when model events occur.
- Notification bell icon in navbar shows unread count.
- Full notifications page available at `/notifications` (requires `notifications` permission).
- Types: `app_users` (user registrations), or any custom type you define.
- **Translations are locale-aware**: notifications display in the admin's current language.

**Adding model name translations for NotifiesAdmin:**

When using this trait on a new model, add translations for the model name to both language files:

```php
// lang/en/admin.php
'model_order' => 'Order',
'model_product' => 'Product',

// lang/ar/admin.php
'model_order' => 'طلب',
'model_product' => 'منتج',
```

The key format is `model_{snake_case_class_name}`. The system stores translation keys (not translated text) and translates on-the-fly based on the viewing admin's locale.

**`Exportable`** — adds a CSV download to any model. Excel opens the file directly (UTF-8 BOM, comma-delimited):
```php
use App\Traits\Exportable;

class User extends Model {
    use Exportable;

    protected array $exportable = ['id', 'name', 'email', 'created_at'];        // optional, defaults to id + fillable + created_at
    protected array $exportHeaders = ['email' => 'Email Address'];              // optional friendly labels
}
```
- `protected array $exportable` — column whitelist. Empty = `id` + `$fillable` + `created_at`.
- `protected array $exportHeaders` — per-column display label. Falls back to humanized column key.
- Override `public function toExportRow(): array` for relationship loads or computed fields.
- Stream from a controller scoped to current filters: `return User::query()->where(...)->exportCsv('users.csv');`
- The Inertia page receives `hasExport: true` (controller passes `in_array(Exportable::class, class_uses_recursive(Model::class))`) and renders the shared `<ExportButton route-name="users.export" :filters="filters" :show="hasExport" />` button. URL inherits the active filters so the export matches what the admin sees.
- Every export writes a row to `activity_logs` with `action='exported'`, `subject_type=ModelClass`, `subject_id=null`, and `new_data={ filename, count, filters, columns }` — admin can audit who exported what and when.

**`BlocksRestoreIfParentTrashed`** — use on any soft-deletable child model whose parent must be alive for the row to make sense:
```php
use App\Traits\BlocksRestoreIfParentTrashed;
use Illuminate\Database\Eloquent\SoftDeletes;

class Post extends Model {
    use SoftDeletes, BlocksRestoreIfParentTrashed;

    protected array $blockRestoreIfTrashed = ['user'];

    public function user() { return $this->belongsTo(User::class); }
}
```
- On admin restore, hooks `restoring` and checks each declared relation via `withTrashed()`.
- If any parent is trashed, throws `ValidationException` keyed under the relation name with `admin.cannot_restore_with_trashed_parent`. Admin UI surfaces it as a flash error.
- Bulk-restore should wrap each row in try/catch so one blocked row doesn't abort the batch.

**`HasTranslations`** — use on any model that needs translatable fields:
```php
use App\Traits\HasTranslations;
class Brand extends Model {
    use HasTranslations;
    protected $translatable = ['name', 'description'];
}
```
- Define translatable fields via `protected $translatable = ['field1', 'field2'];`
- `$model->saveTranslations(['name' => ['en' => 'Hello', 'ar' => 'مرحبا'], ...])` — batch save from forms.
- `$model->getTranslation('name', 'en')` — get specific translation.
- `$model->getTranslation('name')` — get translation for current locale (falls back to default language).
- `$model->getAllTranslations()` — get all translations grouped by field for edit forms.
- `$model->translations` — the morphMany relationship.
- Auto-appends `{field}_api` attributes (e.g., `name_api`, `description_api`) returning current locale's value.
- Translations are auto-deleted when model is deleted.

**Controller pattern for translatable models:**
```php
// Store
$brand = Brand::create(['slug' => $validated['slug']]);
$brand->saveTranslations($validated['translations']);

// Index - always eager load translations
$brands = Brand::with('translations')->paginate(10);
$languages = Language::active()->get(['id', 'code', 'name', 'native_name', 'direction']);
```

**Validation pattern:**
```php
$validated = $request->validate([
    'translations' => ['required', 'array'],
    'translations.name' => ['required', 'array'],
    'translations.name.*' => ['nullable', 'string', 'max:255'],
    'translations.description' => ['nullable', 'array'],
    'translations.description.*' => ['nullable', 'string'],
]);
```

**Frontend form pattern:**
```vue
<script setup>
import TranslatableInput from '@/components/ui/translatable-input/TranslatableInput.vue';
import TranslatableTextarea from '@/components/ui/translatable-input/TranslatableTextarea.vue';

const form = useForm({
    slug: '',
    translations: { name: {}, description: {} },
});
</script>

<template>
    <TranslatableInput
        v-model="form.translations.name"
        :languages="languages"
        :label="t('name')"
        :required="true"
    />
    <TranslatableTextarea
        v-model="form.translations.description"
        :languages="languages"
        :label="t('description')"
    />
</template>
```

**Table display — use `{field}_api` attributes:**
```vue
<TableCell>{{ brand.name_api }}</TableCell>
<TableCell>{{ brand.description_api }}</TableCell>
```

### Soft Deletes (Dynamic)

When a model uses Laravel's `SoftDeletes` trait, the UI adapts automatically.

**Step 1: Add SoftDeletes to model:**
```php
use Illuminate\Database\Eloquent\SoftDeletes;

class Product extends Model {
    use SoftDeletes, HasImage, LogsActivity;
}
```

**Step 2: Add migration for deleted_at:**
```php
$table->softDeletes(); // adds deleted_at column
```

**Step 3: Add `HasSoftDeleteActions` trait to controller:**
```php
use App\Traits\HasSoftDeleteActions;

class ProductController extends Controller {
    use HasSoftDeleteActions;

    protected string $model = Product::class;

    public function index(Request $request) {
        $products = Product::query()
            ->when($request->input('trashed') === 'only', fn($q) => $q->onlyTrashed())
            ->when($request->input('trashed') === 'with', fn($q) => $q->withTrashed())
            ->paginate(10);

        return Inertia::render('Product/Index', [
            'products' => $products,
            'filters' => ['trashed' => $request->input('trashed')],
            'hasSoftDeletes' => true, // Tell frontend this model supports soft deletes
        ]);
    }
}
```

**Step 4: Add routes (bulk routes before parameterized):**
```php
Route::prefix('products')->middleware('permission:products')->group(function () {
    Route::get('/', [ProductController::class, 'index'])->name('products.index');
    Route::post('/', [ProductController::class, 'store'])->name('products.store');
    Route::post('/bulk-destroy', [ProductController::class, 'bulkDestroy'])->name('products.bulk_destroy');
    Route::post('/bulk-restore', [ProductController::class, 'bulkRestore'])->name('products.bulk_restore');
    Route::post('/bulk-force-delete', [ProductController::class, 'bulkForceDelete'])->name('products.bulk_force_delete');
    Route::put('/{product}', [ProductController::class, 'update'])->name('products.update');
    Route::delete('/{product}', [ProductController::class, 'destroy'])->name('products.destroy');
    Route::post('/{product}/restore', [ProductController::class, 'restore'])->name('products.restore')->withTrashed();
    Route::delete('/{product}/force-delete', [ProductController::class, 'forceDelete'])->name('products.force_delete')->withTrashed();
});
```

**Step 5: Frontend — use TrashedFilter and conditional BulkActions:**
```vue
<script setup>
import TrashedFilter from '@/components/Shared/TrashedFilter.vue';
import BulkActions from '@/components/Shared/BulkActions.vue';
import RestoreModal from '@/components/Shared/RestoreModal.vue';
import BulkRestoreModal from '@/components/Shared/BulkRestoreModal.vue';

const props = defineProps({
    products: Object,
    filters: Object,
    hasSoftDeletes: Boolean,
});

const trashedFilter = ref(props.filters?.trashed || '');
const isViewingTrashed = computed(() => trashedFilter.value === 'only');

// Conditional bulk actions based on view
const bulkActions = computed(() => ({
    delete: !isViewingTrashed.value,
    statusOn: !isViewingTrashed.value,
    statusOff: !isViewingTrashed.value,
    restore: isViewingTrashed.value,
    forceDelete: isViewingTrashed.value,
}));

const applyTrashedFilter = (value) => {
    router.get(route('products.index'), { trashed: value }, { preserveState: true });
};
</script>

<template>
    <!-- Add TrashedFilter to filters section -->
    <TrashedFilter
        v-if="hasSoftDeletes"
        v-model="trashedFilter"
        @update:modelValue="applyTrashedFilter"
    />

    <!-- BulkActions with conditional buttons -->
    <BulkActions
        :selected-count="selectedIds.length"
        :actions="bulkActions"
        @delete="openBulkDeleteModal"
        @restore="openBulkRestoreModal"
        @forceDelete="openBulkForceDeleteModal"
    />

    <!-- In table, show "Deleted" badge for trashed items -->
    <span v-if="product.deleted_at" class="text-xs text-red-500">{{ t('trashed') }}</span>
</template>
```

**Soft delete components available:**
- `TrashedFilter` — dropdown for active/with trashed/trashed only
- `RestoreModal` — single item restore confirmation
- `BulkRestoreModal` — bulk restore confirmation
- `BulkActions` supports `restore` and `forceDelete` actions

**Edit modal — populate form from translations array:**
```vue
import { useTranslations } from '@/composables/useTranslations';
const { translationsToObject } = useTranslations();

watch(() => props.brand, (newBrand) => {
    if (newBrand) {
        const translations = translationsToObject(newBrand.translations, ['name', 'description']);
        form.translations = translations;
    }
}, { immediate: true });
```

### Image Model

- Polymorphic (`imageable` morph).
- Auto-generates blurhash on creation (via `creating` boot event).
- Fillable: `url`, `type`, `blurhash`.
- Appended: `image_api` (full public URL).

### Inertia Shared Props (HandleInertiaRequests)

Available on every page via `usePage().props`:
- `auth.user` — authenticated user with `image` relationship loaded.
- `auth.roles` — array of role names.
- `auth.permissions` — array of permission names.
- `locale` — `{ code, dir, name }`.
- `success` / `error` — flash messages.
- `app_users`, `has_translations`, `is_local` — feature flags.
- `auth_identifier` — current login identifier (`email`, `phone`, or `username`).
- `auth_fields` — `{ email: bool, phone: bool, username: bool }` — which fields are available.

---

## Frontend Patterns

### Page Structure

Every feature page follows this layout:
```
Filters → BulkActions → Create Button → Table → Modals
```

```vue
<script setup>
import Default from '@/layouts/default.vue';
defineOptions({ layout: Default });
</script>
```

### Modals — Custom Teleport Pattern

**NEVER use Radix/Shadcn Dialog.** Always use:
```vue
<Teleport to="body">
    <Transition enter-active-class="transition duration-200 ease-out" ...>
        <div v-if="isOpen" class="fixed inset-0 z-50 overflow-y-auto" @click.self="close">
            <div class="absolute inset-0 bg-black/50 backdrop-blur-sm"></div>
            <div class="relative flex min-h-full items-center justify-center p-4">
                <div class="relative w-full max-w-lg ... rounded-2xl bg-card p-6 shadow-xl text-start">
                    <!-- content -->
                </div>
            </div>
        </div>
    </Transition>
</Teleport>
```

Bulk delete MUST use `BulkDeleteModal` component — never `confirm()` or `alert()`.

### Forms

- Use `useForm()` from Inertia.
- File uploads: add `forceFormData: true`.
- PUT with files: add `_method: 'PUT'` to form data and submit via `form.post()`.
- Every Inertia request MUST include `reset: ['{dataKey}', 'success', 'error', 'filters']`.
- Every request should include `preserveScroll: true` and `preserveState: true`.

### Tables

- Use `<InfiniteScroll>` from Inertia for pagination.
- Checkbox select-all with computed get/set pattern.
- Status toggles: optimistic update with rollback on error.
- Action buttons: Edit (yellow), Delete (red).

### Filters

- Local refs for each filter field.
- `router.get()` with `preserveState: true, preserveScroll: true`.
- Active filter chips with clear button.
- "Clear Filters" resets all and re-fetches.

---

## Styling Rules

### RTL Support (MANDATORY)

This project supports Arabic (RTL). Use logical properties ONLY:
- `ms-*` / `me-*` instead of `ml-*` / `mr-*`
- `ps-*` / `pe-*` instead of `pl-*` / `pr-*`
- `start-*` / `end-*` instead of `left-*` / `right-*`
- `ltr:` / `rtl:` prefixes when directional behavior differs.

**NEVER use** `ml-*`, `mr-*`, `pl-*`, `pr-*`, `left-*`, `right-*`.

### Theme Colors

Use semantic Tailwind tokens, not hardcoded colors:
- `bg-background`, `bg-card`, `bg-muted`, `bg-primary`, `bg-accent`
- `text-foreground`, `text-muted-foreground`, `text-primary-foreground`
- `border-border`, `border-input`
- **NEVER use `text-white` with `bg-primary`** — use `text-primary-foreground` instead.

### Icons

Use `lucide-vue-next` only. Never import from other icon libraries.

### Layout Consistency

- Pages use `max-w-[1300px]` container.
- Cards use `rounded-3xl border bg-card p-6`.
- Header bars use `rounded-xl border bg-card p-4`.
- Buttons: Edit = yellow outline, Delete = red outline, Create = primary.

### Responsive Design (MANDATORY)

All Vue components MUST be responsive and work on all screen sizes. Follow these patterns:

**Mobile-First Approach:**
- Start with mobile styles, then add larger breakpoint overrides.
- Use Tailwind breakpoints: `sm:` (640px), `md:` (768px), `lg:` (1024px), `xl:` (1280px).

**Grid Layouts:**
```vue
<!-- Single column on mobile, multi-column on larger screens -->
<div class="grid grid-cols-1 gap-4 md:grid-cols-2 lg:grid-cols-3">
```

**Flex Layouts:**
```vue
<!-- Stack on mobile, row on larger screens -->
<div class="flex flex-col gap-4 md:flex-row md:items-center md:justify-between">
```

**Responsive Spacing:**
- Use `p-4 md:p-6` for padding that grows on larger screens.
- Use `gap-3 md:gap-4 lg:gap-6` for responsive gaps.

**Responsive Text:**
- Use `text-sm md:text-base` for body text.
- Use `text-lg md:text-xl lg:text-2xl` for headings.

**Hidden/Visible Elements:**
- Use `hidden md:block` to show elements only on medium+ screens.
- Use `md:hidden` to show elements only on mobile.

**Tables:**
- Tables MUST have horizontal scroll on mobile: `<div class="overflow-x-auto">`.
- Consider card-based layouts for mobile as alternative to tables.

**Forms:**
- Form fields stack vertically on mobile, can be side-by-side on larger screens.
- Buttons should be full-width on mobile: `w-full md:w-auto`.

**Modals:**
- Use `w-full max-w-lg` for responsive modal width.
- Reduce padding on mobile: `p-4 md:p-6`.

**Navigation:**
- Navbar must have mobile menu (hamburger) for small screens.
- Sidebar collapses to icons or hidden on mobile.

**Testing:**
- Always test at 320px (small mobile), 768px (tablet), 1024px (desktop).
- Use browser DevTools responsive mode during development.

---

## Translations (MANDATORY)

Every user-visible string MUST be translated. Never hardcode text.

### Translation System Decision Tree

This project uses **three translation systems** for different purposes. Use this decision tree:

```
Where is the string displayed?
│
├─► Frontend (Vue components)
│   └─► Use: t('key')
│       Files: resources/js/locales/en.json + ar.json
│       Example: {{ t('save_changes') }}
│
├─► Admin Backend (flash messages, notifications, validation)
│   └─► Use: __('admin.key')
│       Files: lang/en/admin.php + lang/ar/admin.php
│       Example: ->with('success', __('admin.created_successfully'))
│
└─► API Responses (mobile app messages)
    └─► Use: Trans::get('api.key')
        Storage: Database (translation_keys + translation_values tables)
        Example: ApiResponse::success($data, Trans::get('api.login_successful'))
```

### Why Three Systems?

| System | Purpose | Editable by Admin? | Cache |
|--------|---------|-------------------|-------|
| Vue i18n (`t()`) | Frontend UI labels, buttons, headings | No (requires deploy) | Build-time |
| PHP Lang (`__()`) | Admin panel messages, notifications | No (requires deploy) | Runtime |
| Database (`Trans::get()`) | API responses for mobile app | **Yes (via CMS)** | 1 hour |

**Key insight:** API translations are database-driven so admins can customize mobile app messages without deploying code. Frontend and admin translations are file-based for performance.

### Quick Reference

**Frontend (Vue):**
```vue
<template>
    <Button>{{ t('save') }}</Button>
</template>
<script setup>
import { useI18n } from 'vue-i18n';
const { t } = useI18n();
</script>
```
Files: `resources/js/locales/en.json` + `ar.json`

**Admin Backend (PHP):**
```php
return back()->with('success', __('admin.updated_successfully'));
```
Files: `lang/en/admin.php` + `lang/ar/admin.php`

**API Responses:**
```php
use App\Helpers\Trans;
return ApiResponse::success($data, Trans::get('api.login_successful'));
return ApiResponse::error(Trans::get('api.user_not_found'), null, 404);
```
Storage: Database (editable in CMS → Translations)

### Adding New Translations

**For Vue (frontend):**
```json
// resources/js/locales/en.json
{ "my_button": "Click Me" }

// resources/js/locales/ar.json
{ "my_button": "اضغط هنا" }
```

**For Admin PHP:**
```php
// lang/en/admin.php
'my_message' => 'Operation completed.',

// lang/ar/admin.php
'my_message' => 'تمت العملية.',
```

**For API (Option 1 - Seeder):**
```php
// database/seeders/TranslationSeeder.php
'my_api_message' => [
    'en' => 'Success!',
    'ar' => 'نجاح!',
],
```
Run: `php artisan db:seed --class=TranslationSeeder`

**For API (Option 2 - CMS):**
1. Go to **Translations** page
2. Click **Create Translation**
3. Set group to `api`, enter the key
4. Enter values for each language

### How Trans Helper Works

- `Trans::get('api.key')` checks database first (1-hour cache)
- Falls back to default language if current locale not found
- Falls back to file-based `__('api.key')` if not in database
- Cache clears automatically when translations are updated in CMS

**How it works:**
- `Trans::get('api.key')` checks the database first (with 1-hour cache)
- Falls back to default language if current locale not found
- Falls back to file-based `__('api.key')` if not in database
- Cache is cleared automatically when translations are updated in CMS

**Groups:**
- `api` — API response messages. **Server-controlled.** Editable in the CMS, but cannot be created/modified via API.
- `app` — Mobile-app strings.
- `web` — Web frontend strings.

The public translation endpoints work on `app` and `web` only. The group is selected via a `group` parameter (default `app`).

**`POST /api/translations` body:**

```json
{
    "group": "app",
    "translations": {
        "key1": "value1",
        "key2": "value2"
    }
}
```

- `group` (optional, default `app`): `app` or `web`. Other values rejected.
- Locale comes from `Accept-Language`.
- New key → created in the chosen group. Header's locale gets the value; all other active locales are seeded as `null` (admin fills them later in the CMS).
- Existing key → only the header's locale value is updated. Other locales are untouched.
- Calling with the same key/group under a different `Accept-Language` populates that locale.

**`GET /api/translations?group=app`** — fetch keys for the chosen group in the current locale. Returns `{ group, locale, translations: { key: value, ... } }`.

**Placeholder protection in CMS:** Translation values may contain Laravel `:placeholder` tokens (e.g. `:name`, `:domains`). When admins edit a translation in the Translations CMS, the controller extracts placeholders from the **default-language value** and rejects any locale value that omits one of them (HTTP 422 with `errors.{locale}` per missing locale). The frontend edit modal also surfaces a "Required placeholders" badge listing the tokens and shows inline missing-placeholder warnings as the admin types. Admins can type freely around the placeholder but cannot delete or rename it without saving failing.

---

## New Feature Checklist

When creating a new feature, follow this order:

1. **Model + Migration** — `app/Models/{Feature}.php` with `$fillable`, traits (`LogsActivity`, `HasImage` if needed). Migration with `php artisan make:migration`.
2. **Controller** — `app/Http/Controllers/Admin/{Feature}/{Feature}Controller.php` with index/store/update/destroy/bulkDestroy (+ bulkUpdate if needed).
3. **Permission** — Add to `RoleSeeder.php`, assign to `super_admin`, run `php artisan db:seed --class=RoleSeeder`.
4. **Routes** — In `routes/web.php` with `middleware('permission:feature_name')`. Bulk routes BEFORE parameterized routes.
5. **Translations** — Both `en.json` + `ar.json` AND `lang/en/admin.php` + `lang/ar/admin.php`.
6. **Page** — `resources/js/pages/{Feature}/Index.vue` with Default layout.
7. **Components** — In `resources/js/components/{feature-name}/`:
   - `{Feature}Filters.vue`
   - `{Feature}Table.vue`
   - `{Feature}CreateModal.vue`
   - `{Feature}EditModal.vue`
8. **Navbar** — Add link in `Navbar.vue` with permission check: `v-if="page.props.auth.permissions.find(p => p === 'feature_name')"`.

---

## Authentication Configuration

The app user (mobile API) auth system uses **email and/or phone as identifiers**, with username as a separate optional field that doubles as a login alias:

- `AUTH_IDENTIFIERS` — comma-separated list of identifiers. **Allowed values: `email`, `phone`** (comma-separated, e.g. `email`, `phone`, `email,phone`). Username is **not** allowed here. Defaults to `email` if empty/invalid.
- `HAS_EMAIL_FIELD` — when `true` AND email is not an identifier, exposes `email` as an optional profile field.
- `HAS_PHONE_FIELD` — when `true` AND phone is not an identifier, exposes `phone` as an optional profile field.
- `HAS_USERNAME_FIELD` — when `true`, the `username` field is **required at register**, **searchable at login** (login alias), and editable via `update-profile`. Username is never an identifier; it is always a separate column.

DevSettings UI under "Authentication Config" lets you toggle these. Admin AppUser table, edit modal, and API endpoints adapt dynamically.

**Registration:** Request body always includes `policy_agreed`, `name`, `identifier` (email or phone), `password`, `password_confirmation`. When `HAS_USERNAME_FIELD=true`, `username` is also required. Optional non-identifier extras (`email`/`phone` when `HAS_*_FIELD=true` and not identifier) keep their own keys.

`identifier` resolution against `AUTH_IDENTIFIERS`:
- **Single identifier configured** → value validated against that field's rules.
- **Multiple identifiers (`email,phone`)** → kind detected from format (email pattern → email, `+` and digits → phone) and validated against the matching field. Falls back to the first configured identifier on detection failure.

**Login:** `identifier` is searched across configured email/phone identifier columns AND the `username` column when `HAS_USERNAME_FIELD=true`. Kind is detected from the value's format and only the matching column is queried (no OR-collisions). Users can log in with any of those values.

**Email case normalization:** the `User` model lowercases `email` on every write (via `setEmailAttribute`). Lookup methods (`findUserByIdentifier`, `checkIdentifier`, identifier-change) lowercase email values before query/storage. Cross-DB safe (MySQL/PostgreSQL).

**Username format:** when enabled, must match `/^[A-Za-z][A-Za-z0-9_-]*$/` with `min:3`. Email/phone-shaped values are rejected. Uniqueness is scoped to api-guard users only (via `whereExists` on `model_has_roles` + `roles`).

**Identifier error format:** Auth endpoints (`register`, `login`, `forgot-password`, `verify-forgot-password-otp`, `change-forgot-password`) return identifier-validation errors and `user not found` as HTTP **422** with errors keyed under `identifier`:

```json
{
    "success": false,
    "message": "...",
    "errors": { "identifier": ["..."] },
    "data": null
}
```

**Field-keyed error convention (frontend display rule):** Any API error tied to a specific request input field is returned as HTTP **422** with the error keyed under the **same name as the input parameter**. The frontend should look up `errors.{paramName}` to show inline messages next to the matching input.

Mapping per endpoint:

| Endpoint | Trigger | Error Key |
|----------|---------|-----------|
| `POST /api/login` | invalid credentials | `errors.password` |
| `POST /api/login`, `forgot-password`, `verify-forgot-password-otp`, `change-forgot-password`, `register` | user not found / identifier validation | `errors.identifier` |
| `POST /api/verify-otp`, `verify-forgot-password-otp`, `change-forgot-password`, `verify-identifier-change` | invalid OTP | `errors.otp` |
| `POST /api/change-password` | wrong current password | `errors.old_password` |
| `POST /api/request-identifier-change`, `verify-identifier-change` | invalid format / wrong kind / unique-fail | `errors.new_identifier` |
| `POST /api/firebase-login`, `link-social-account` | invalid Firebase token, provider not allowed, email mismatch, account exists with password, social max-accounts reached, social account already linked, social provider already linked, missing email in token | `errors.token` |
| `DELETE /api/unlink-social-account` | provider not linked, cannot unlink last social account | `errors.provider` |

State/config errors (`account_is_inactive`, `unauthorized_access`, `social_auth_requires_email`, `email_change_not_available`, `user_role_not_found`, `policy_not_agreed`) stay as plain top-level error messages with `errors: null` since they are not tied to a single input field.

Validation errors thrown by Laravel's request validation (`$request->validate(...)`) follow the same format automatically — keys match the rule field names.

**OTP delivery priority:** When multiple identifiers are configured, OTP is sent via the first available channel:
1. `email` (if email is an identifier) — sent via EmailHelper.
2. `phone` (if phone is an identifier) — sent via SMS or WhatsApp (based on `IS_OTP_WHATSAPP`).
3. `username` only — OTP stored but not delivered (available in testing mode via API response).

**User serialization (API only):** `User::toArray()` strips `email` / `phone` / `username` from API responses when they are NOT configured — i.e. neither listed in `AUTH_IDENTIFIERS` nor enabled via `HAS_*_FIELD`. This keeps mobile-app payloads free of irrelevant columns. Admin/web requests (anything not under `api/*`) keep all columns intact so the admin panel can still manage every field.

**API update-profile endpoint** (`PUT /api/update-profile`):
- `name` — always editable.
- `username` — always editable when `HAS_USERNAME_FIELD=true` (username is never an identifier).
- `email` / `phone` — editable here **only when NOT an identifier** (enabled as `HAS_EMAIL_FIELD` / `HAS_PHONE_FIELD` extras). When email/phone is an identifier, use `request-identifier-change` instead.

**API identifier-change flow** (`POST /api/request-identifier-change` + `POST /api/verify-identifier-change`):
- Used for **email and phone identifier changes only** (always OTP-protected).
- Body: `new_identifier`. Kind auto-detected (email or phone) and must match one of the configured identifiers in `AUTH_IDENTIFIERS`.
- `request-identifier-change` sends an OTP via the matching channel (email → `EmailHelper`; phone → SMS, or WhatsApp when `IS_OTP_WHATSAPP=true`). Rate-limited via `throttle:otp` (3/5min).
- `verify-identifier-change` confirms the OTP and updates the column.
- Username changes go through `update-profile` (no OTP).
- Field-keyed errors under `new_identifier` (validation) or `otp` (invalid OTP).

**Auth config endpoint:** `GET /api/auth-config` (public, throttle:api) exposes the live auth configuration so mobile/web clients can adapt their UI on boot. Returns `identifiers`, `has_username_field`, `has_email_field`, `has_phone_field`, `social_providers`, `max_social_accounts`, `social_auth_available`, `is_otp_whatsapp`, `multi_session`. Conditional on `APP_USERS=true`.

**Sessions / Devices (`MULTI_SESSION_ENABLED`, default `true`):** every Sanctum token issued by `login` / `firebase-login` / verify-OTP path is recorded in a `user_devices` row (FK-cascade on `personal_access_token_id` so revoking a token auto-drops its device). Columns: `fcm_token`, `device_name`, `platform`, `ip`, `user_agent`, `last_seen_at`. Login response includes `token_id` so clients can identify themselves later.

- **Multi-session ON:** new logins append a device row. Tokens stack. The user manages them via `GET /api/devices` (returns rows with `is_current: bool`) and `DELETE /api/devices/{id}` (revokes the underlying token + broadcasts a kick).
- **Multi-session OFF:** `trackDevice` revokes every other token belonging to the user before issuing the new one and broadcasts `device.revoked` on `private-user.{userId}` for each kicked token id.

**Per-device FCM:** the legacy `users.fcm_token` column was dropped. Source of truth is `user_devices.fcm_token`. Use `$user->fcmTokens()` (returns array) for any push send so multi-session users get notified everywhere; `FCMHelper::send()` already accepts arrays.

**Remote-logout broadcast:** `App\Events\DeviceRevoked` (`ShouldBroadcastNow`) on `private-user.{userId}`, event name `.device.revoked`, payload `{ token_id }`. Frontend composable [useDeviceRevocation.ts](resources/js/composables/useDeviceRevocation.ts) listens and clears local creds when the token id matches the locally stored `current_token_id`.

**DevSettings → Sessions** toggles `MULTI_SESSION_ENABLED` at runtime (writes `.env`, runs `config:clear`).

**Account deletion (user-initiated, restorable):** `DELETE /api/delete-account` sets `users.account_deleted_at = now()` and revokes all tokens. The row stays in DB. `POST /api/check-identifier` returns `pending_deletion: true` so the client can prompt the user. `POST /api/login` with valid credentials clears `account_deleted_at` and returns `account_restored: true`. Purge runs via the global `PurgeDeletedUsersAfterResponse` middleware: every HTTP request fires `Artisan::call('users:purge-deleted')` in the middleware's `terminate()` hook (after the response is sent to the client), throttled to once per hour with a cache lock and capped at 50 rows per run. No cron, no queue worker. The command calls `forceDelete()` on accounts older than the retention window, triggering `User::$cascadeOnDelete`. Retention is configurable via `ACCOUNT_DELETION_RETENTION_DAYS` env var or DevSettings → "Account Deletion Retention" (default 30 days). Admin soft-delete (Laravel `SoftDeletes` / `deleted_at`) is independent and unchanged — admin-trashed rows are NOT auto-purged; admin-trashed users see `suspended: true` from `check-identifier` and a 403 `api.account_suspended` from login.

**Cascade declarations on User:**
- `protected array $cascadeOnDelete = [];` — relations to delete alongside the user. Admin soft-delete stamps each cascaded child's `deleted_at` with the parent's exact timestamp (bypasses child model events). Force-delete (admin or middleware purge) cascades to `forceDelete()` on the relations.
- `protected array $cascadeOnRestore = [];` — relations to restore alongside the user when admin restores from trash. Matches children whose `deleted_at` equals the parent's pre-restore `deleted_at`, so children trashed independently of the user are left alone. Captured via the `restoring` event before Laravel clears `deleted_at`, applied in the `restored` event.

Pair with [BlocksRestoreIfParentTrashed](app/Traits/BlocksRestoreIfParentTrashed.php) on the child model so admins can't restore an orphaned child while its parent is still trashed.

**Forgot-password channel:** `forgot-password` takes `identifier` plus an optional `type` (`"email"` or `"phone"`).
- When `type` is omitted → channel auto-picked using priority `email > phone` from the user's populated columns.
- When `type` is provided → must be one of the user's populated channels (else 422 `errors.type`).
- Response includes the actual `channel` used so the client knows where the OTP went.

**Check-identifier reuse:** `POST /api/check-identifier` detects kind and queries the matching column scoped to api-guard users (supports identifier columns AND `HAS_*_FIELD` extras). Response includes:
- `exists` — whether a user matched.
- `available_channels` — array listing which OTP delivery channels (`"email"`, `"phone"`) are populated on that user. The client uses this to decide which `type` to pass to `forgot-password`, and as a pre-submit uniqueness check before `register`, `update-profile` (username), or `request-identifier-change`.

---

## Firebase Social Authentication

The app supports Firebase-based social authentication (Google, Apple, Facebook, Twitter, GitHub) for mobile apps. Social auth is **only available when email is configured as an identifier** (`AUTH_IDENTIFIERS` must include `email`).

### Setup

1. Configure Firebase credentials in `config/firebase.php` (requires `kreait/laravel-firebase` package)
2. Ensure `AUTH_IDENTIFIERS` includes `email`
3. Configure allowed providers and account limits in DevSettings under "Social Authentication"

### Database Structure

Social accounts are stored in a separate `social_accounts` table with one-to-many relationship to users:
- `user_id` — Foreign key to users table
- `provider` — Provider name (e.g., `google.com`, `apple.com`)
- `provider_id` — Firebase UID from the social provider
- `email` — Email from the social provider
- `name` — Name from the social provider

### Configuration (Environment Variables)

- `SOCIAL_AUTH_PROVIDERS` — Comma-separated list of allowed providers (e.g., `google.com,apple.com,facebook.com`). Empty = all providers allowed.
- `SOCIAL_AUTH_MAX_ACCOUNTS` — Maximum number of social accounts per user. `0` = unlimited, `1` = one account only.

### API Endpoints

**Public:**
- `POST /api/firebase-login` — Login or register via Firebase ID token

**Protected (verified):**
- `GET /api/social-accounts` — Get all linked social accounts for the user
- `POST /api/link-social-account` — Link a social account to existing account
- `DELETE /api/unlink-social-account` — Unlink a specific social account by provider

### Behavior Rules

1. **New user via social auth:** Creates account with linked social account, auto-verifies, assigns `user` role
2. **Existing user with password:** Blocks social login, returns "use password" error
3. **Existing social-only user:** Allows login via any linked social account
4. **Provider restrictions:** Only providers in `SOCIAL_AUTH_PROVIDERS` are allowed (if configured)
5. **Account limits:** Users cannot exceed `SOCIAL_AUTH_MAX_ACCOUNTS` (if > 0)
6. **Same provider twice:** Users cannot link the same provider twice
7. **Unlink protection:** Cannot unlink last social account unless user has a password set

### Account Linking Flow

- Users with password accounts can link multiple social accounts while logged in
- Users cannot link a social account already used by another user
- Social account email must match the user's account email when linking
- Each provider can only be linked once per user (e.g., one Google account, one Apple account)

---

## Pusher Broadcasting

The app supports real-time broadcasting via Pusher for WebSocket-based features. Broadcasting is configured with Sanctum authentication for private channels.

### Setup

1. Configure Pusher credentials in DevSettings under "Pusher Broadcasting" (separate configs for local and production)
2. The frontend uses `@laravel/echo-vue` for listening to broadcasts

### Configuration

Broadcasting is configured in `bootstrap/app.php`:
```php
->withBroadcasting(
    __DIR__.'/../routes/channels.php',
    ['prefix' => 'api', 'middleware' => ['api', 'auth:sanctum']],
)
```

### Environment Variables

**Backend (server-side):**
- `BROADCAST_CONNECTION` — Set to `pusher` to enable Pusher broadcasting
- `PUSHER_APP_ID` — Pusher application ID
- `PUSHER_APP_KEY` — Pusher application key
- `PUSHER_APP_SECRET` — Pusher application secret
- `PUSHER_APP_CLUSTER` — Pusher cluster (e.g., `eu`, `us2`, `ap1`)

**Frontend (Vite):**
- `VITE_PUSHER_APP_KEY` — Pusher key for frontend (auto-set when saving config)
- `VITE_PUSHER_APP_CLUSTER` — Pusher cluster for frontend (auto-set when saving config)

### Channel Authorization

Private channels are defined in `routes/channels.php`:
```php
Broadcast::channel('user.{userId}', function ($user, $userId) {
    return (int) $user->id === (int) $userId;
});
```

### Creating Broadcast Events

```php
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;

class OrderStatusChanged implements ShouldBroadcastNow
{
    public function __construct(
        public int $userId,
        public string $status,
    ) {}

    public function broadcastOn(): array
    {
        return [new PrivateChannel('user.'.$this->userId)];
    }

    public function broadcastAs(): string
    {
        return 'order.status';
    }

    public function broadcastWith(): array
    {
        return ['status' => $this->status];
    }
}
```

### Dispatching Events

```php
broadcast(new OrderStatusChanged($user->id, 'shipped'));
```

### Frontend Listening (Vue)

```vue
<script setup>
import { useEchoChannel } from '@laravel/echo-vue';

const channel = useEchoChannel(`private-user.${userId}`);

channel.listen('.order.status', (event) => {
    console.log('Order status:', event.status);
});
</script>
```

### DevSettings Integration

- **Local config:** Pusher credentials for development environment
- **Production config:** Separate Pusher credentials for production (stored in `.env.production`)
- **Test broadcast:** Send a test event to verify configuration works

---

## Environment Variables

Key `.env` flags that affect behavior:
- `APP_USERS` — enables mobile app user module and API auth routes.
- `HAS_TRANSLATIONS` — enables app translations feature (admin panel routes, navbar links, and API endpoints for translations/languages).
- `AUTH_IDENTIFIERS` — comma-separated login identifiers (e.g. `email`, `email,phone`).
- `HAS_EMAIL_FIELD` / `HAS_PHONE_FIELD` / `HAS_USERNAME_FIELD` — toggle extra profile fields (only for non-identifier fields).
- `IS_TESTING` — testing mode flag (exposes OTP in API responses).
- `IS_OTP_WHATSAPP` — OTP delivery method (WhatsApp vs SMS, only when identifier is `phone`).
- `SOCIAL_AUTH_PROVIDERS` — comma-separated allowed social providers (e.g., `google.com,apple.com`). Empty = all allowed.
- `SOCIAL_AUTH_MAX_ACCOUNTS` — max social accounts per user (`0` = unlimited, `1` = one only).
- `ADMIN_EMAIL` / `ADMIN_PASSWORD` — initial super admin credentials (seeder).
- `APP_X_API_TOKEN` — API token for X-API-TOKEN header validation.
- `BROADCAST_CONNECTION` — broadcasting driver (`pusher`, `log`, or `null`).
- `PUSHER_APP_ID` / `PUSHER_APP_KEY` / `PUSHER_APP_SECRET` / `PUSHER_APP_CLUSTER` — Pusher credentials.
- `ALLOWED_PHONE_COUNTRIES` — comma-separated ISO country codes for phone validation (e.g., `JO,US,SA`) or `all`.
- `ALLOWED_EMAIL_DOMAINS` — comma-separated domains for email validation (e.g., `gmail.com,yahoo.com`) or `all`.
- `RATE_LIMIT_API` / `RATE_LIMIT_API_DECAY` — general API rate limit (requests per decay minutes, default: 60/1).
- `RATE_LIMIT_AUTH` / `RATE_LIMIT_AUTH_DECAY` — authentication rate limit (default: 5/1).
- `RATE_LIMIT_OTP` / `RATE_LIMIT_OTP_DECAY` — OTP request rate limit (default: 3/5).
- `MULTI_SESSION_ENABLED` — multi-device sessions (default: `true`). When `false`, every login revokes the user's other tokens and broadcasts `device.revoked` so kicked clients log themselves out.
- `ACCOUNT_DELETION_RETENTION_DAYS` — days a user-initiated soft deletion is retained before permanent purge (default: 30).

---

## Postman Collection

The API is documented in `Starter.postman_collection.json` at the project root. Import it into Postman and set the variables:
- `{{base_url}}` — e.g. `http://localhost:8000`
- `{{x-api-token}}` — value from `APP_X_API_TOKEN` in `.env`
- `{{token}}` — Bearer token from login/register response

When adding new API endpoints, always update this collection file to keep it in sync.

---

## Files That Must Stay in Sync

- `public/images/logo.png` ↔ `resources/js/resources/images/logo.png`
- `public/favicon.ico` ↔ `resources/js/resources/favicon.ico`

The DevSettings logo/favicon upload handles both locations automatically.

---

## Queue & Broadcasting

### Queue Connection

Always use `QUEUE_CONNECTION=sync` in this starter. This ensures:
- Events broadcast immediately without queue workers
- Simpler local development setup
- No need for Redis or database queue tables

### Broadcasting Events

Always use `ShouldBroadcastNow` instead of `ShouldBroadcast`:

```php
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;

class MyEvent implements ShouldBroadcastNow
{
    // Event implementation
}
```

This ensures events are broadcast synchronously without relying on queue workers.

===

<laravel-boost-guidelines>
=== foundation rules ===

# Laravel Boost Guidelines

The Laravel Boost guidelines are specifically curated by Laravel maintainers for this application. These guidelines should be followed closely to ensure the best experience when building Laravel applications.

## Foundational Context

This application is a Laravel application and its main Laravel ecosystems package & versions are below. You are an expert with them all. Ensure you abide by these specific packages & versions.

- php - 8.4
- inertiajs/inertia-laravel (INERTIA_LARAVEL) - v2
- laravel/framework (LARAVEL) - v12
- laravel/prompts (PROMPTS) - v0
- laravel/sanctum (SANCTUM) - v4
- laravel/wayfinder (WAYFINDER) - v0
- tightenco/ziggy (ZIGGY) - v2
- laravel/boost (BOOST) - v2
- laravel/mcp (MCP) - v0
- laravel/pail (PAIL) - v1
- laravel/pint (PINT) - v1
- laravel/sail (SAIL) - v1
- pestphp/pest (PEST) - v4
- phpunit/phpunit (PHPUNIT) - v12
- @inertiajs/vue3 (INERTIA_VUE) - v2
- tailwindcss (TAILWINDCSS) - v4
- vue (VUE) - v3
- @laravel/vite-plugin-wayfinder (WAYFINDER_VITE) - v0
- eslint (ESLINT) - v9
- prettier (PRETTIER) - v3

## Skills Activation

This project has domain-specific skills available. You MUST activate the relevant skill whenever you work in that domain—don't wait until you're stuck.

- `laravel-best-practices` — Apply this skill whenever writing, reviewing, or refactoring Laravel PHP code. This includes creating or modifying controllers, models, migrations, form requests, policies, jobs, scheduled commands, service classes, and Eloquent queries. Triggers for N+1 and query performance issues, caching strategies, authorization and security patterns, validation, error handling, queue and job configuration, route definitions, and architectural decisions. Also use for Laravel code reviews and refactoring existing Laravel code to follow best practices. Covers any task involving Laravel backend PHP code patterns.
- `wayfinder-development` — Use this skill for Laravel Wayfinder which auto-generates typed functions for Laravel controllers and routes. ALWAYS use this skill when frontend code needs to call backend routes or controller actions. Trigger when: connecting any React/Vue/Svelte/Inertia frontend to Laravel controllers, routes, building end-to-end features with both frontend and backend, wiring up forms or links to backend endpoints, fixing route-related TypeScript errors, importing from @/actions or @/routes, or running wayfinder:generate. Use Wayfinder route functions instead of hardcoded URLs. Covers: wayfinder() vite plugin, .url()/.get()/.post()/.form(), query params, route model binding, tree-shaking. Do not use for backend-only task
- `pest-testing` — Use this skill for Pest PHP testing in Laravel projects only. Trigger whenever any test is being written, edited, fixed, or refactored — including fixing tests that broke after a code change, adding assertions, converting PHPUnit to Pest, adding datasets, and TDD workflows. Always activate when the user asks how to write something in Pest, mentions test files or directories (tests/Feature, tests/Unit, tests/Browser), or needs browser testing, smoke testing multiple pages for JS errors, or architecture tests. Covers: test()/it()/expect() syntax, datasets, mocking, browser testing (visit/click/fill), smoke testing, arch(), Livewire component tests, RefreshDatabase, and all Pest 4 features. Do not use for factories, seeders, migrations, controllers, models, or non-test PHP code.
- `inertia-vue-development` — Develops Inertia.js v2 Vue client-side applications. Activates when creating Vue pages, forms, or navigation; using <Link>, <Form>, useForm, or router; working with deferred props, prefetching, or polling; or when user mentions Vue with Inertia, Vue pages, Vue forms, or Vue navigation.
- `tailwindcss-development` — Always invoke when the user's message includes 'tailwind' in any form. Also invoke for: building responsive grid layouts (multi-column card grids, product grids), flex/grid page structures (dashboards with sidebars, fixed topbars, mobile-toggle navs), styling UI components (cards, tables, navbars, pricing sections, forms, inputs, badges), adding dark mode variants, fixing spacing or typography, and Tailwind v3/v4 work. The core use case: writing or fixing Tailwind utility classes in HTML templates (Blade, JSX, Vue). Skip for backend PHP logic, database queries, API routes, JavaScript with no HTML/CSS component, CSS file audits, build tool configuration, and vanilla CSS.

## Conventions

- You must follow all existing code conventions used in this application. When creating or editing a file, check sibling files for the correct structure, approach, and naming.
- Use descriptive names for variables and methods. For example, `isRegisteredForDiscounts`, not `discount()`.
- Check for existing components to reuse before writing a new one.

## Verification Scripts

- Do not create verification scripts or tinker when tests cover that functionality and prove they work. Unit and feature tests are more important.

## Application Structure & Architecture

- Stick to existing directory structure; don't create new base folders without approval.
- Do not change the application's dependencies without approval.

## Frontend Bundling

- If the user doesn't see a frontend change reflected in the UI, it could mean they need to run `npm run build`, `npm run dev`, or `composer run dev`. Ask them.

## Documentation Files

- You must only create documentation files if explicitly requested by the user.

## Replies

- Be concise in your explanations - focus on what's important rather than explaining obvious details.

=== boost rules ===

# Laravel Boost

## Tools

- Laravel Boost is an MCP server with tools designed specifically for this application. Prefer Boost tools over manual alternatives like shell commands or file reads.
- Use `database-query` to run read-only queries against the database instead of writing raw SQL in tinker.
- Use `database-schema` to inspect table structure before writing migrations or models.
- Use `get-absolute-url` to resolve the correct scheme, domain, and port for project URLs. Always use this before sharing a URL with the user.
- Use `browser-logs` to read browser logs, errors, and exceptions. Only recent logs are useful, ignore old entries.

## Searching Documentation (IMPORTANT)

- Always use `search-docs` before making code changes. Do not skip this step. It returns version-specific docs based on installed packages automatically.
- Pass a `packages` array to scope results when you know which packages are relevant.
- Use multiple broad, topic-based queries: `['rate limiting', 'routing rate limiting', 'routing']`. Expect the most relevant results first.
- Do not add package names to queries because package info is already shared. Use `test resource table`, not `filament 4 test resource table`.

### Search Syntax

1. Use words for auto-stemmed AND logic: `rate limit` matches both "rate" AND "limit".
2. Use `"quoted phrases"` for exact position matching: `"infinite scroll"` requires adjacent words in order.
3. Combine words and phrases for mixed queries: `middleware "rate limit"`.
4. Use multiple queries for OR logic: `queries=["authentication", "middleware"]`.

## Artisan

- Run Artisan commands directly via the command line (e.g., `php artisan route:list`). Use `php artisan list` to discover available commands and `php artisan [command] --help` to check parameters.
- Inspect routes with `php artisan route:list`. Filter with: `--method=GET`, `--name=users`, `--path=api`, `--except-vendor`, `--only-vendor`.
- Read configuration values using dot notation: `php artisan config:show app.name`, `php artisan config:show database.default`. Or read config files directly from the `config/` directory.
- To check environment variables, read the `.env` file directly.

## Tinker

- Execute PHP in app context for debugging and testing code. Do not create models without user approval, prefer tests with factories instead. Prefer existing Artisan commands over custom tinker code.
- Always use single quotes to prevent shell expansion: `php artisan tinker --execute 'Your::code();'`
  - Double quotes for PHP strings inside: `php artisan tinker --execute 'User::where("active", true)->count();'`

=== php rules ===

# PHP

- Always use curly braces for control structures, even for single-line bodies.
- Use PHP 8 constructor property promotion: `public function __construct(public GitHub $github) { }`. Do not leave empty zero-parameter `__construct()` methods unless the constructor is private.
- Use explicit return type declarations and type hints for all method parameters: `function isAccessible(User $user, ?string $path = null): bool`
- Use TitleCase for Enum keys: `FavoritePerson`, `BestLake`, `Monthly`.
- Prefer PHPDoc blocks over inline comments. Only add inline comments for exceptionally complex logic.
- Use array shape type definitions in PHPDoc blocks.

=== inertia-laravel/core rules ===

# Inertia

- Inertia creates fully client-side rendered SPAs without modern SPA complexity, leveraging existing server-side patterns.
- Components live in `resources/js/pages` (unless specified in `vite.config.js`). Use `Inertia::render()` for server-side routing instead of Blade views.
- ALWAYS use `search-docs` tool for version-specific Inertia documentation and updated code examples.
- IMPORTANT: Activate `inertia-vue-development` when working with Inertia Vue client-side patterns.

# Inertia v2

- Use all Inertia features from v1 and v2. Check the documentation before making changes to ensure the correct approach.
- New features: deferred props, infinite scroll, merging props, polling, prefetching, once props, flash data.
- When using deferred props, add an empty state with a pulsing or animated skeleton.

=== laravel/core rules ===

# Do Things the Laravel Way

- Use `php artisan make:` commands to create new files (i.e. migrations, controllers, models, etc.). You can list available Artisan commands using `php artisan list` and check their parameters with `php artisan [command] --help`.
- If you're creating a generic PHP class, use `php artisan make:class`.
- Pass `--no-interaction` to all Artisan commands to ensure they work without user input. You should also pass the correct `--options` to ensure correct behavior.

### Model Creation

- When creating new models, create useful factories and seeders for them too. Ask the user if they need any other things, using `php artisan make:model --help` to check the available options.

## APIs & Eloquent Resources

- For APIs, default to using Eloquent API Resources and API versioning unless existing API routes do not, then you should follow existing application convention.

## URL Generation

- When generating links to other pages, prefer named routes and the `route()` function.

## Testing

- When creating models for tests, use the factories for the models. Check if the factory has custom states that can be used before manually setting up the model.
- Faker: Use methods such as `$this->faker->word()` or `fake()->randomDigit()`. Follow existing conventions whether to use `$this->faker` or `fake()`.
- When creating tests, make use of `php artisan make:test [options] {name}` to create a feature test, and pass `--unit` to create a unit test. Most tests should be feature tests.

## Vite Error

- If you receive an "Illuminate\Foundation\ViteException: Unable to locate file in Vite manifest" error, you can run `npm run build` or ask the user to run `npm run dev` or `composer run dev`.

## Deployment

- Laravel can be deployed using [Laravel Cloud](https://cloud.laravel.com/), which is the fastest way to deploy and scale production Laravel applications.

=== laravel/v12 rules ===

# Laravel 12

- CRITICAL: ALWAYS use `search-docs` tool for version-specific Laravel documentation and updated code examples.
- Since Laravel 11, Laravel has a new streamlined file structure which this project uses.

## Laravel 12 Structure

- In Laravel 12, middleware are no longer registered in `app/Http/Kernel.php`.
- Middleware are configured declaratively in `bootstrap/app.php` using `Application::configure()->withMiddleware()`.
- `bootstrap/app.php` is the file to register middleware, exceptions, and routing files.
- `bootstrap/providers.php` contains application specific service providers.
- The `app/Console/Kernel.php` file no longer exists; use `bootstrap/app.php` or `routes/console.php` for console configuration.
- Console commands in `app/Console/Commands/` are automatically available and do not require manual registration.

## Database

- When modifying a column, the migration must include all of the attributes that were previously defined on the column. Otherwise, they will be dropped and lost.
- Laravel 12 allows limiting eagerly loaded records natively, without external packages: `$query->latest()->limit(10);`.

### Models

- Casts can and likely should be set in a `casts()` method on a model rather than the `$casts` property. Follow existing conventions from other models.

=== wayfinder/core rules ===

# Laravel Wayfinder

Use Wayfinder to generate TypeScript functions for Laravel routes. Import from `@/actions/` (controllers) or `@/routes/` (named routes).

=== pint/core rules ===

# Laravel Pint Code Formatter

- If you have modified any PHP files, you must run `vendor/bin/pint --dirty --format agent` before finalizing changes to ensure your code matches the project's expected style.
- Do not run `vendor/bin/pint --test --format agent`, simply run `vendor/bin/pint --format agent` to fix any formatting issues.

=== pest/core rules ===

## Pest

- This project uses Pest for testing. Create tests: `php artisan make:test --pest {name}`.
- Run tests: `php artisan test --compact` or filter: `php artisan test --compact --filter=testName`.
- Do NOT delete tests without approval.

=== inertia-vue/core rules ===

# Inertia + Vue

Vue components must have a single root element.
- IMPORTANT: Activate `inertia-vue-development` when working with Inertia Vue client-side patterns.

</laravel-boost-guidelines>
