# Model Traits — Always Use Them

## `HasImage`

Use on any model that has an image:
```php
use App\Traits\HasImage;
class MyModel extends Model { use HasImage; }
```
- `$model->saveImage($file, 'folder')` — deletes old, stores new, creates Image record with blurhash.
- `$model->deleteImage()` — deletes file + record.
- `$model->image` — the morphOne relationship.
- Never call `Storage::disk()->delete()` or `->image()->create()` directly in controllers.

### Image Model

- Polymorphic (`imageable` morph).
- Auto-generates blurhash on creation (via `creating` boot event).
- Fillable: `url`, `type`, `blurhash`.
- Appended: `image_api` (full public URL).

## `HasVideo`

Use on any model that has a video:
- `$model->saveVideo($file, 'folder', $optionalThumbnail)` — stores video, optionally saves thumbnail via HasImage on the Video model.
- `$model->deleteVideo()` — deletes video file + thumbnail image + records.

## `LogsActivity`

Use on any model that should be audited:
- Auto-logs created/updated/deleted events to `activity_logs` table.
- Filters out sensitive fields (password, tokens, timestamps).
- Customizable via static `$logEvents` and `$logIgnoreFields`.

## `NotifiesAdmin`

Use on any model that should trigger admin notifications:
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

## `Exportable`

Adds a CSV download to any model. Excel opens the file directly (UTF-8 BOM, comma-delimited):
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

## `HasUserTimezone` — MANDATORY for user-set date/time fields

Any time a CMS feature lets a user pick a date/time (publish date, schedule, start/end, etc.), the model MUST use this trait. Never store a user-entered datetime raw. The DB always holds **UTC**; the trait converts the value from the admin's active timezone to UTC on save, and the frontend (`useDateFormat`) converts back to the viewer's timezone for display. This is the single fix for "the time is off by N hours" bugs.

```php
use App\Traits\HasUserTimezone;

class Event extends Model {
    use HasUserTimezone;

    // List every column that holds a user-entered datetime.
    protected array $userTimezoneDates = ['starts_at', 'ends_at', 'published_at'];

    protected function casts(): array {
        return ['starts_at' => 'datetime', 'ends_at' => 'datetime', 'published_at' => 'datetime'];
    }
}
```

How it works:
- The admin's active timezone rides on every request as the `X-Timezone` header (set in [resources/js/app.ts](../../../../resources/js/app.ts) from the timezone picker; updated live by [useDateFormat](../../../../resources/js/composables/useDateFormat.ts) `setTimezone`).
- On `saving`, for each `$userTimezoneDates` field that is **dirty**, the trait interprets the value as wall-clock time in that timezone and stores it as UTC (`Y-m-d H:i:s`). Fields the user didn't change are left alone.
- Missing/invalid header → falls back to `config('app.timezone')` (UTC) and no shift happens.

Rules:
- `config('app.timezone')` MUST stay `UTC`. Do not change it.
- Validate the field as usual (`['date']` / `['date_format:...']`) — the trait only converts, it does not validate.
- Frontend: render stored UTC datetimes through `useDateFormat().formatDate(...)`; never display a raw DB datetime.
- Do NOT also hand-convert in the controller — the trait owns the conversion. Double-converting shifts twice.

## `BlocksRestoreIfParentTrashed`

Use on any soft-deletable child model whose parent must be alive for the row to make sense:
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

## `HasTranslations`

Use on any model that needs translatable fields:
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
- **Completeness reporting:** `$model->missingTranslationLocales()` returns active locales missing a required translation; serialized rows expose `missing_translations` (array of locale codes) automatically when `translations` is eager-loaded, so admin tables can flag incomplete rows. `Model::incompleteTranslationCount()` counts rows needing attention (used for navbar warning badges). By default every translatable field is required for completeness; narrow it with `protected array $translationRequired = ['name'];`. The navbar surfaces these counts via the `translation_warnings` Inertia shared prop (see [HandleInertiaRequests](../../../../app/Http/Middleware/HandleInertiaRequests.php)).

### Controller pattern for translatable models

```php
// Store
$brand = Brand::create(['slug' => $validated['slug']]);
$brand->saveTranslations($validated['translations']);

// Index - always eager load translations
$brands = Brand::with('translations')->paginate(10);
$languages = Language::active()->get(['id', 'code', 'name', 'native_name', 'direction']);
```

### Validation pattern

```php
$validated = $request->validate([
    'translations' => ['required', 'array'],
    'translations.name' => ['required', 'array'],
    'translations.name.*' => ['nullable', 'string', 'max:255'],
    'translations.description' => ['nullable', 'array'],
    'translations.description.*' => ['nullable', 'string'],
]);
```

### Frontend form pattern

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

### Table display — use `{field}_api` attributes

```vue
<TableCell>{{ brand.name_api }}</TableCell>
<TableCell>{{ brand.description_api }}</TableCell>
```
