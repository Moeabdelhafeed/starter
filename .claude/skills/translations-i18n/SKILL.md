---
name: translations-i18n
description: "Use whenever adding or displaying any user-visible string, or working with the translations feature/CMS/API in this starter. This project has THREE separate translation systems (Vue i18n t(), PHP __('admin.key'), and DB-backed Trans::get('api.key')) — picking the wrong one is the single most common mistake. Also covers the app/web translation sub-group system (POST/GET/DELETE /api/translations), and placeholder (:token) protection in the Translations CMS. Trigger on: hardcoded UI text, adding a translation key, 'which translation system do I use', sub_group questions, the Translations admin page, or Trans::get / __() / t() calls. Do not use for backend controller/trait patterns (see admin-feature-crud)."
metadata:
  author: project
---

# Translations (MANDATORY)

Every user-visible string MUST be translated. Never hardcode text.

## Translation System Decision Tree

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

## Why Three Systems?

| System | Purpose | Editable by Admin? | Cache |
|--------|---------|-------------------|-------|
| Vue i18n (`t()`) | Frontend UI labels, buttons, headings | No (requires deploy) | Build-time |
| PHP Lang (`__()`) | Admin panel messages, notifications | No (requires deploy) | Runtime |
| Database (`Trans::get()`) | API responses for mobile app | **Yes (via CMS)** | 1 hour |

**Key insight:** API translations are database-driven so admins can customize mobile app messages without deploying code. Frontend and admin translations are file-based for performance.

## Quick Reference

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

## Adding New Translations

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

## How Trans Helper Works

- `Trans::get('api.key')` checks database first (1-hour cache).
- Falls back to default language if current locale not found.
- Falls back to file-based `__('api.key')` if not in database.
- Cache clears automatically when translations are updated in CMS.

## Groups & Sub-groups

**Groups:**
- `api` — API response messages. **Server-controlled.** Editable in the CMS, but cannot be created/modified via API. **Never has a sub-group** (`sub_group = ''`).
- `app` — Mobile-app strings. **Every key lives under a `sub_group`.**
- `web` — Web frontend strings. **Every key lives under a `sub_group`.**

The public translation endpoints work on `app` and `web` only. The group is selected via a `group` parameter (default `app`).

**Sub-groups (`sub_group`):** `app`/`web` keys are organized into free-form sub-groups (e.g. `web/auth`, `web/about_us`) so screens/sections can be fetched together. Sub-groups are created **only** via `POST /api/translations` (the admin CMS does not create them). Uniqueness is `(key, group, sub_group)` — the same key can exist under different sub-groups. Storage: `translation_keys.sub_group` is `string NOT NULL default ''`; the empty-string sentinel (never NULL) keeps the composite unique valid and lets `api` keys stay sub-group-less without duplicating. `Trans::get()` / cache keys are unchanged (they only cover `api`/`admin`/`custom`, which never use sub-groups).

**`POST /api/translations` body:**

```json
{
    "group": "web",
    "sub_group": "auth",
    "translations": {
        "key1": "value1",
        "key2": "value2"
    }
}
```

- `group` (optional, default `app`): `app` or `web`. Other values rejected.
- `sub_group` (**required**): free-form slug matching `^[a-z0-9]([a-z0-9_-]*[a-z0-9])?$` (e.g. `auth`, `about_us`). Missing/invalid → 422 keyed under `sub_group`.
- Locale comes from `Accept-Language`.
- New key → created under the group + sub_group. Header's locale gets the value; all other active locales are seeded as `null` (admin fills them later in the CMS).
- Existing key (same key + group + sub_group) → only the header's locale value is updated. Other locales are untouched.
- Calling with the same key/group/sub_group under a different `Accept-Language` populates that locale.

**`GET /api/translations?group=web`** — fetch keys for the chosen group in the current locale, **nested by sub_group**. Returns `{ group, locale, translations: { sub_group: { key: value, ... }, ... } }`. Keys with an empty sub-group fall under `general`. The client fetches a whole group at once (no per-sub-group filtering on the API).

**`DELETE /api/translations?group=web&sub_group=auth&key=login_title`** — deletes a single translation key at `(key, group, sub_group)`, cascade-deleting all of its locale values. Same `group` restriction as store (`app`/`web` only — `api` keys are server-controlled, not deletable here). 404 if no key matches; 422 (keyed `sub_group`/`key`) if either is missing/malformed.

**Translations CMS (sub-groups):** the admin Translations page shows `sub_group` as a badge/column and offers a **sub-group filter** dropdown (populated from the distinct `app`/`web` sub-groups). Sub-groups are **view + filter only** in the CMS — read-only in the edit modal, never created/edited there (they come from the API).

## Placeholder Protection in CMS

Translation values may contain Laravel `:placeholder` tokens (e.g. `:name`, `:domains`). When admins edit a translation in the Translations CMS, the controller extracts placeholders from the **default-language value** and rejects any locale value that omits one of them (HTTP 422 with `errors.{locale}` per missing locale). The frontend edit modal also surfaces a "Required placeholders" badge listing the tokens and shows inline missing-placeholder warnings as the admin types. Admins can type freely around the placeholder but cannot delete or rename it without saving failing.
