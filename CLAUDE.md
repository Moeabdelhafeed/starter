# Inertia Starter — Project Rules

## Stack

- **Backend:** Laravel 13, PHP 8.2+, MySQL
- **Frontend:** Vue 3 (`<script setup>` + TypeScript), Inertia.js v3, Tailwind CSS v4
- **Auth:** Sanctum (dual guard: `web` for admin, `api` for mobile app), Spatie Permission for roles
- **UI:** Reka UI (headless), lucide-vue-next (icons), vue-i18n (translations)
- **Build:** Vite 7, Wayfinder (route generation), Ziggy (route helpers)

---

## Skills — Activate Proactively

Domain-specific conventions live in `.claude/skills/`. Activate the relevant skill _before_ touching that part of the app — don't wait until you're stuck or something breaks:

| Skill                    | Covers                                                                                                                                                                                                                                                                                  |
| ------------------------ | --------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| `admin-feature-crud`     | New admin feature scaffolding: directory structure, dual guard, web route conventions, controller pattern (index/store/update/destroy/bulk), RoleSeeder permission wiring, the New Feature Checklist, model traits (`reference/traits.md`), soft deletes (`reference/soft-deletes.md`). |
| `vue-admin-ui-patterns`  | Vue page/table/modal/form conventions, the mandatory method-spoofed PUT/DELETE workaround, `ImageUpload`/`VideoUpload` components, sticky-actions tables, the table/grid view toggle, Inertia shared props.                                                                             |
| `styling-rtl-responsive` | RTL logical-property rules (banned `ml-*`/`mr-*`/etc.), semantic theme color tokens, icon library, layout consistency, mobile-first responsive breakpoints.                                                                                                                             |
| `translations-i18n`      | The three translation systems (Vue i18n `t()`, PHP `__('admin.key')`, DB-backed `Trans::get('api.key')`), `app`/`web` sub-groups, placeholder protection in the CMS.                                                                                                                    |
| `mobile-auth-identity`   | Mobile API auth: `routes/api.php` auth endpoints, rate limiting, `AUTH_IDENTIFIERS`/`AUTH_MODE`, register/login/OTP, the field-keyed error convention, account deletion, Firebase social auth.                                                                                          |
| `mobile-device-tracking` | `X-Device-Id`/`X-Platform`/`X-FCM-Token` headers, `IdentifyDevice` resolution order, guest users, multi-session device management, FCM.                                                                                                                                                 |
| `dynamic-storage-media`  | The keyed media store (Dynamic Storage): `MediaItem`, `POST`/`GET /api/media`, the admin Media CMS.                                                                                                                                                                                     |
| `realtime-broadcasting`  | Pusher broadcast events, private channel auth, `ShouldBroadcastNow`, Echo listeners.                                                                                                                                                                                                    |

## Dual Guard System

- **Web guard (`web`):** Admin panel users. Roles: `super_admin`, `fallback`, custom roles.
- **API guard (`api`):** Mobile app users. Role: `user`.
- Permissions are web-guard only. Each feature has one permission (e.g. `users`, `roles`, `translations`).

---

## Global Reference

### Configuration — never call `env()` outside `config/`

**`env()` returns `null` once `php artisan config:cache` has run.** Every flag below is therefore
read through a config file, and application code must do the same:

| Read this                                                                                                                                                      | Not this                                         |
| -------------------------------------------------------------------------------------------------------------------------------------------------------------- | ------------------------------------------------ |
| `config('features.pages')`, `config('features.app_users')`, …                                                                                                  | `env('HAS_PAGES')`                               |
| `config('auth.mode')`, `config('auth.identifiers')`, `config('auth.fields.email')`, `config('auth.rate_limits.otp')`, `config('auth.allowed_phone_countries')` | `env('AUTH_MODE')`, `env('AUTH_IDENTIFIERS')`, … |
| `config('app.is_testing')`, `config('app.x_api_token')`                                                                                                        | `env('IS_TESTING')`, `env('APP_X_API_TOKEN')`    |
| `config('admin.email')`, `config('admin.password')`                                                                                                            | `env('ADMIN_EMAIL')`                             |

New flag? Add it to `config/features.php` (feature toggles) or `config/auth.php` (anything about
identity, tokens or limits) with a comment, add it to `.env.example`, and read it via `config()`.
`tests/Feature/ConfigCacheTest.php` fails the build if an `env()` call reappears under `app/` or
`routes/`. The single exception is `Admin/DevSetting/DevSettingController.php`, which is a
local-only `.env` file editor and reads the raw file on purpose.

Two production guards back this up: `AppServiceProvider` force-disables `IS_TESTING` in production
(it otherwise removes every rate limit, echoes OTP codes in API responses and puts the failing SQL
in 5xx bodies), and `php artisan app:assert-production-safety` fails a deploy when debug mode,
testing mode, a blank API token or the example admin password are live.

### Environment Variables

Key `.env` flags that affect behavior:

- `APP_USERS` — enables mobile app user module and API auth routes.
- `HAS_TRANSLATIONS` — enables app translations feature (admin panel routes, navbar links, and API endpoints for translations/languages).
- `HAS_NOTIFICATION_TEMPLATES` — enables the notification templates feature (admin panel routes + navbar link). Default `true`. Gated in `routes/web.php`, exposed as Inertia shared prop `has_notification_templates`, toggleable via DevSettings.
- `HAS_PAGES` — enables the pages feature (admin CRUD, public `/p/{slug}` route, and API page endpoints). Default `true`. Gated in `routes/web.php` + `routes/api.php`, exposed as `has_pages`, toggleable via DevSettings.
- `PROTECTED_PAGES` — comma-separated page slugs the CMS may never delete (default `terms,privacy`). Edited in DevSettings → Environment, where the CMS follows the list: adding a slug runs `PageSeeder` so the page exists immediately, removing one deletes that page and its translations. Only slugs removed by that request are deleted — never everything the list omits, because `PROTECTED_PAGES=` reads as an empty list rather than the config default. Empty means nothing is protected. See "Rows the CMS may not delete" below.
- `HAS_APP_SETTINGS` — enables the App Settings feature (admin CRUD for social/contact/app-store/google-play/app-gallery link blocks + the public `GET /api/app-settings` endpoint). Default `true`. Gated in `routes/web.php` + `routes/api.php`, exposed as `has_app_settings`, toggleable via DevSettings. Items use `HasImage` + `HasTranslations` (translatable `text`).
- `HAS_ACTIVITY_LOGS` — enables the activity logs admin feature (routes, navbar link, and the Dashboard's "Recent Activity"/"Total activities" widgets). Default `true`. Models still record logs via `LogsActivity` regardless of the flag; only display surfaces are gated. Exposed as `has_activity_logs`, toggleable via DevSettings.
- `HAS_DYNAMIC_STORAGE` — enables the Dynamic Storage feature (see `dynamic-storage-media` skill). Default `true`. Gated in `routes/web.php` + `routes/api.php`, exposed as `has_dynamic_storage`, toggleable via DevSettings.
- `ALLOW_CONTENT_SEEDING` — opens `POST`/`DELETE` on `/api/translations` and `/api/media` so a client app can seed content into MySQL. Default `true`. Per flavor in the Deploy panel and settable on production — seed once, then turn it back off. See "Content-provisioning endpoints" below.
- `MEDIA_MAX_IMAGE_KB` / `MEDIA_MAX_VIDEO_KB` / `MEDIA_MAX_FILE_KB` — per-kind upload size caps (KB) for Dynamic Storage. Defaults `2048` / `20480` / `10240`. Read via `config('dynamic-storage.*')`.
- `AUTH_IDENTIFIERS` — comma-separated login identifiers (e.g. `email`, `email,phone`). See `mobile-auth-identity` skill.
- `HAS_EMAIL_FIELD` / `HAS_PHONE_FIELD` / `HAS_USERNAME_FIELD` — toggle extra profile fields (only for non-identifier fields).
- `IS_TESTING` — testing mode flag. It exposes the OTP in API responses, and two things follow from that, both so a tester can walk the shipped flow without a real mailbox:
    - **Every OTP is the ascending sequence of `Otp::LENGTH` digits** — `123456` today, `1234` if the constant is ever 4. `Otp::generate()` is the single generator (verify, login, reset-password and identifier-change all call it); it is `random_int` of the same length in every other install. Never generate a code inline.
    - **`IS_TESTING` changes the code, never the flow.** `POST /api/register` always sends a verify OTP, issues no token and leaves `verified_at` null; the client then calls `login` (a token is issued even unverified) and `verify-otp`. There is no flag to turn that off — an account is usable only once its identifier is proven.
- `IS_OTP_WHATSAPP` — OTP delivery method (WhatsApp vs SMS, only when identifier is `phone`).
- `SOCIAL_AUTH_PROVIDERS` — comma-separated allowed social providers (e.g., `google.com,apple.com`). Empty = all allowed.
- `SOCIAL_AUTH_MAX_ACCOUNTS` — max social accounts per user (`0` = unlimited, `1` = one only).
- `ADMIN_EMAIL` / `ADMIN_PASSWORD` — initial super admin credentials (seeder).
- `APP_X_API_TOKEN` — API token for X-API-TOKEN header validation.
- `BROADCAST_CONNECTION` — broadcasting driver (`pusher`, `log`, or `null`). See `realtime-broadcasting` skill.
- `PUSHER_APP_ID` / `PUSHER_APP_KEY` / `PUSHER_APP_SECRET` / `PUSHER_APP_CLUSTER` — Pusher credentials.
- `ALLOWED_PHONE_COUNTRIES` — comma-separated ISO country codes for phone validation (e.g., `JO,US,SA`) or `all`.
- `ALLOWED_EMAIL_DOMAINS` — comma-separated domains for email validation (e.g., `gmail.com,yahoo.com`) or `all`.
- `RATE_LIMIT_API` / `RATE_LIMIT_API_DECAY` — general API rate limit (requests per decay minutes, default: 60/1).
- `RATE_LIMIT_AUTH` / `RATE_LIMIT_AUTH_DECAY` — authentication rate limit (default: 5/1).
- `RATE_LIMIT_OTP` / `RATE_LIMIT_OTP_DECAY` — OTP request rate limit (default: 3/5).
- `MULTI_SESSION_ENABLED` — multi-device sessions (default: `true`). See `mobile-device-tracking` skill.
- `ACCOUNT_DELETION_RETENTION_DAYS` — days a user-initiated soft deletion is retained before permanent purge (default: 30).

### Files That Must Stay in Sync

- `public/images/logo.png` ↔ `resources/js/resources/images/logo.png`
- `public/images/logo-dark.png` ↔ `resources/js/resources/images/logo-dark.png` (dark-mode logo; shown via `dark:block`, falls back to the light logo if absent)
- `public/favicon.ico` ↔ `resources/js/resources/favicon.ico`

The DevSettings logo/favicon upload handles both locations automatically.

### Media serialization — one shape everywhere

Every stored asset lives on a morph model and is serialized by that model, never hand-built in a controller:

- `Image::toApiArray()` → `{ id, url, type, blurhash, image_api }` (`url` is the stored path, `image_api` the public URL).
- `Video::toApiArray()` → `{ id, url, type, video_api, thumbnail }` — `thumbnail` is an Image morph, so it is a full Image array (or `null`).
- `MediaFile::toApiArray()` → `{ id, url, type, name, size, file_api }`.

A field holding one asset returns the array directly (`image` on app-settings items, pages, languages). A record that can be any type returns `{ type, image|video|file }` — `MediaItem::toApi()`, `GalleryItem::toApi()` — nested, not flattened, because the morph carries its own `id`/`type` which would collide with the record's.

Writes go through the traits: `HasImage::saveImage()`, `HasVideo::saveVideo($file, $folder, ?$thumbnail)`, `HasFile::saveFile()`. `MediaItem::saveMedia($file, $type, ?$thumbnail)` and `GalleryItem::saveMedia(...)` both accept an optional video thumbnail; nothing else may touch `Storage` directly (`ImageUploadService` is the only exception, and the traits call it).

### Identifiers: declared type, one canonical phone format

Endpoints taking an `identifier` / `new_identifier` require a **`type`** (`email`, `phone`, or `username` on login/check). The kind is never inferred from the value — inferring is what let a number with no country code be silently reinterpreted as a local one.

- Phones must arrive with their country code and are stored in E.164 via `App\Helpers\PhoneNumber::normalize()` (`966501234567`, `+966 50 123 4567` → `+966501234567`; `0501234567`, `501234567`, `77…` → rejected).
- `App\Rules\AllowedPhoneCountry` then enforces `ALLOWED_PHONE_COUNTRIES`.
- **Normalize before validating**, not after: `unique:users,phone` has to compare the stored form, or the same number in another notation slips past as "available".
- Lookups go through `findUserByIdentifier($value, $type)`, which normalizes the same way — one human can only ever match one row.
- The identifier rules themselves live in **`App\Helpers\AuthIdentity`** (`identifiers()`,
  `isIdentifier()`, `hasField()`, `types()`, `normalize()`, `emailRule()`, `phoneRule()`, `rule()`,
  `otpLoginRule()`). There used to be three drifting copies of this logic; add to the helper, never
  re-derive it in a controller.
- On `POST /api/forgot-password`, `type` is what the user typed and `channel` is where the OTP goes (optional, defaults email > phone). The old `type` on that endpoint meant the channel.

### Admin panel security model

Route-level `permission:` middleware is the first gate, but it cannot express "who may act on
_this_ row", so the user-facing modules add ownership checks the routes cannot:

- `super_admin` passes every ability through a `Gate::before` in `AppServiceProvider`. Without it,
  a permission added after the role was seeded is one the owner does not hold, and shipping a
  feature would lock them out of it until someone re-ran the seeder on the live database.
- `Admin/User/UserController` only ever resolves users holding a **web**-guard role;
  `Admin/AppUser/AppUserController` only resolves app users and guests. The `{user}` binding is
  shared between them, so without this the app-user screen is a back door into the admin table.
- A non-super*admin cannot edit, delete, deactivate, re-role or force-delete a `super_admin`, and
  cannot assign the `super_admin` role. The assignable-role list is filtered for the UI \_and*
  validated server-side.
- A role holder cannot edit the role they themselves hold (that is the self-grant path into every
  other permission).
- `dev-settings` is `local` environment **and** `role:super_admin`.

Admin-facing rate limits and session handling: `POST /login` is throttled, the session id is
rotated on login, and the invalid-credentials message is identical for an unknown address and a
wrong password so the form cannot enumerate accounts.

### Content-provisioning endpoints need ALLOW_CONTENT_SEEDING

`POST`/`DELETE` on `/api/translations` and `/api/media` are authenticated only by the shared
`X-API-TOKEN`, which ships inside every mobile binary. They sit behind the `content-seeding`
middleware (`config('features.content_seeding')`, from `ALLOW_CONTENT_SEEDING`) and answer
**403** otherwise. Reads are unaffected.

**Deliberately not `IS_TESTING`.** The two answer different questions: testing mode says "this
install is not real" — it echoes OTP codes, drops rate limits, and is force-disabled in
production — while seeding says "fill the content tables now", which a _production_ install
legitimately needs exactly once, when the client app pushes its translations and media in.
Tying them together meant a live site could never be seeded through the API at all.

**It defaults to `true`, and only the production flavor gets a choice.** dev, staging and uat
are written as `true` whatever the form sends — there is no live content there to protect, so
offering a switch would only be a way to break seeding by accident. The Deploy panel shows the
select on the production tab alone; the others show a line saying it is always on.

Production is where you turn it _off_, once the live content is in — which is why the flag is
allowed there at all and not locked the way `APP_DEBUG` and `IS_TESTING` are. Its select offers
**Enabled / Disabled only, no "inherit base"**: the base is always `true`, so inherit would be
a second word for enabled. There is no base toggle in DevSettings → Environment either — the
only place worth deciding this is the production flavor. `normalizeFlavorEnv()` reads an
`inherit` left in an older `.deploy.json` as enabled. The deploy panel shows a red warning while a flavor has it on, because for as
long as it is on, anyone holding the token from a decompiled build can write and delete your
content.

### Firebase is optional

A project may run with no service-account JSON at all — `FIREBASE_CREDENTIALS` unset, or the file simply missing on a fresh server — and everything except social sign-in and push must keep working.

Resolving Kreait's contracts **throws** when credentials are absent, so nothing may type-hint `Kreait\Firebase\Contract\Auth` / `Messaging`: method injection blows up in the container before the controller body runs, which is how a plain phone-number change used to 500. Go through `App\Helpers\Firebase` instead:

- `Firebase::available(): bool` — cached per process; checks the configured path before trying to resolve.
- `Firebase::auth(): ?Auth` / `Firebase::messaging(): ?Messaging` — null when unconfigured.
- `Firebase::forget()` — test seam.

Behaviour with no credentials: `GET /api/config` reports `social_auth_available: false`, `POST /api/firebase-login` and `/api/link-social-account` answer **503** with `api.social_auth_unavailable`, `FCMHelper` returns `['success' => false, 'message' => 'Firebase is not configured']`, and identifier changes revoke social tokens only if Firebase is there (`Firebase::auth()?->revokeRefreshTokens(...)`).

### API Docs — Postman / OpenAPI (auto-generated via Scribe)

`Starter.postman_collection.json` and the OpenAPI spec are **generated from `routes/api.php`** (route signatures, FormRequest rules, controller docblocks) via `knuckleswtf/scribe` — **never hand-edit them.** Config lives in `config/scribe.php`.

- **Regenerate:** `composer api-docs` (runs `scribe:generate`, then `scribe:polish`, then `app:sync-postman-collection`, then copies the fresh collection over `Starter.postman_collection.json` at the project root). Run this whenever you add/change an API route. The SSH deploy flow (DevSettings → Deploy) runs the same three artisan commands remotely, gated by the "Generate API Docs" checkbox in the deploy modal.
- **Every endpoint needs both a title and a group, or docs look unprofessional:** the docblock's first line is the title shown in Postman/OpenAPI (keep it a short verb phrase, e.g. `Login`, not a paragraph); the full description goes below a blank line. Every endpoint needs `@group GroupName` so it lands in a Postman folder instead of the catch-all `Endpoints` group. Current groups, in `config/scribe.php` → `groups.order`: Authentication, Social Login, Profile & Account, Devices & Guests, Translations, Dynamic Storage, Pages, App Settings, Languages, Broadcasting.
- **A group description is NOT a separate `@groupDescription` tag — Scribe has no such tag.** It's plain text placed directly under `@group GroupName`, still inside that same tag (no blank `*` line in between), on just the first endpoint of the group:
    ```php
    /**
     * Endpoint Title
     *
     * @group GroupName
     * This text is the group description. It has to be right here, immediately after
     * the group name, no blank line — a blank `*` line (or a separate `@groupDescription`
     * tag) silently drops it and the group description just renders empty everywhere
     * (Postman folder, HTML docs, OpenAPI) with no error. Every endpoint in this codebase
     * had this exact bug until it was caught and fixed project-wide.
     *
     * @urlParam ...
     */
    ```
- **Every endpoint needs real example values, or docs look unprofessional:** Scribe auto-generates request-body examples from Faker when a param has no explicit example — producing garbage like `{"identifier":"b","password":"]|{+-0pBNvYg"}`. Always add `@bodyParam`/`@queryParam`/`@urlParam` tags with a trailing `Example: <realistic value>` (e.g. `@bodyParam identifier string required The login identifier. Example: jane@example.com`) for every param the route accepts — pull the type/required-ness from the actual `$request->validate()` rules, don't guess.
- **`scribe:polish` (`app/Console/Commands/PolishApiDocs.php`) fixes Scribe quirks that make the Postman collection render as plain text instead of syntax-highlighted JSON:** raw request bodies are missing `body.options.raw.language`, and every `@response`-declared example ships with an empty `header` array (no `Content-Type`) and no `_postman_previewlanguage` marker, since Scribe's `@response` tag has no way to declare either. Always run it after `scribe:generate` (already wired into `composer api-docs` and the deploy flow) — never regenerate with bare `php artisan scribe:generate` alone if you want docs someone will actually read.
- **`app:sync-postman-collection` (`app/Console/Commands/SyncPostmanCollection.php`) turns the per-request hardcoded placeholders into reusable Postman collection variables**, so importing the collection means filling in a few values once (Collection → Variables tab) instead of editing every request. It replaces the `X-API-TOKEN`, `X-Device-Id`, `X-Platform` (and `X-FCM-Token`, where present) header values with `{{xApiToken}}` / `{{deviceId}}` / `{{platform}}` / `{{fcmToken}}`, points the collection-level bearer auth at `{{bearerToken}}`, and appends all of them — plus Scribe's own `{{baseUrl}}` — to the collection's `variable` array, using whatever literal value was already there as the default. Run after `scribe:polish` (already wired into `composer api-docs` and the deploy flow); it's idempotent, so running it twice on an already-synced collection is a no-op.
- **Vendor/framework routes with no useful docblock** (e.g. `api/broadcasting/auth`, registered by `withBroadcasting()` in `bootstrap/app.php` against `Illuminate\Broadcasting\BroadcastController`) can't be annotated in place. Exclude them in `config/scribe.php` → `routes[].exclude` (pattern needs the full URI including the `api/` prefix, e.g. `'POST api/broadcasting/auth'` — `Route::uri()` includes it) and hand-write a real entry in `.scribe/endpoints/custom.0.yaml` instead (title/group/description/params/responses, see the existing Broadcasting entry for the format). Skip only the exclude step and you get a duplicate (auto-discovered + custom) side by side.
- **Live docs** (no auth, intentionally public — see `intro_text` in the config): `GET /docs` (HTML), `GET /docs.postman` (collection), `GET /docs.openapi` (OpenAPI 3.0.3 spec). Assets are generated output (`resources/views/scribe/`, `public/vendor/scribe/`) — gitignored, rebuilt by `scribe:generate`, not committed.
- **Every endpoint requires** `X-API-TOKEN`, `X-Device-Id`, `X-Platform` headers (see `mobile-device-tracking` skill) — baked into the generated docs as **placeholders** (`config/scribe.php` → `strategies.headers`), never the real `.env` secret. Do not change that to `env('APP_X_API_TOKEN')` — `/docs.postman` is public, so that would leak the live token to anyone who visits it. The HTML docs and OpenAPI spec keep showing the literal placeholder text (e.g. `{YOUR_APP_X_API_TOKEN}`); only the Postman collection turns them into fillable `{{xApiToken}}`-style collection variables, via `sync-postman-collection` above.
- **Auth-mode caveat:** Scribe only documents routes that are _currently registered_, so whichever `AUTH_MODE` the local `.env` holds decides which auth endpoints exist in the docs — generating under `otp` silently drops `register` and the entire password-reset flow (5 endpoints), and generating under `password` drops `verify-login`. **`composer api-docs` therefore pins `AUTH_MODE=password` for the doc build only** (a shell env var beats `.env`, since Laravel's dotenv is immutable), so the password flow is always documented no matter what the machine is set to. The otp-only `verify-login` is hand-written in `.scribe/endpoints/custom.0.yaml` in the starter kit so both modes stay covered. Other flags (`HAS_PAGES=false` etc.) still behave the old way — regenerate against the config you want documented.
- Bearer-token response examples need a real Sanctum PAT in `SCRIBE_AUTH_KEY` (`.env`, not committed) to hit `@authenticated` endpoints during generation — without it, those examples show a 401.

**Method override on the API:** the production host blocks real `PUT`/`PATCH`/`DELETE`. Mobile/API clients must send those as **`POST` + an `X-HTTP-Method-Override` header** carrying the real verb (e.g. `X-HTTP-Method-Override: DELETE`). Laravel's kernel has method override enabled, so it routes the request to the matching `Route::delete(...)`. The Postman collection already uses this pattern for `update-profile` (PUT) and the delete endpoints — mirror it when adding new `PUT`/`DELETE` API requests, and tell mobile devs to do the same. (Routes themselves stay `Route::put`/`Route::delete`.)

**Pagination on API list endpoints:** default to unpaginated (`->get()`) — a client that doesn't ask for a page gets everything. Support paging as an opt-in via a `per_page` query param: absent, or literally `"all"`, returns the full list; any other value paginates:

```php
$query = Model::query()->...;

if ($request->has('per_page') && $request->input('per_page') !== 'all') {
    $items = $query->paginate((int) $request->input('per_page'))
        ->through(fn ($item) => $this->present($item));
} else {
    $items = $query->get()->map(fn ($item) => $this->present($item));
}

return ApiResponse::success($items, 'Retrieved successfully.');
```

Passing the paginator straight into `ApiResponse::success()` nests Laravel's standard paginator shape (`current_page`, `data`, `per_page`, `total`, `last_page`, `next_page_url`, etc.) under the response's `data` key — no extra wiring needed. No forced default page size, no `min`/`max` clamp — trust the caller. Apply this to every new API list endpoint from the start, not just the ones that grow large enough to need it later.

The shape above lives in **`App\Traits\PaginatesApiLists`** — `use` the trait and call
`$this->paginated($request, $query, fn ($row) => [...])`. It is applied to `GET /api/pages` and
`GET /api/languages`. Endpoints whose response is a keyed map or grouped object rather than a flat
list — `GET /api/app-settings` (grouped by block type) and `GET /api/translations` (a key → value
map) — are deliberately exempt: paginating them would break the shape their clients index into.

### Admin UI — design system

`resources/css/app.css` defines the full token set for light and dark, including `success`,
`warning` and `info` alongside `primary`/`destructive`. Raw palette classes (`bg-red-50`,
`text-emerald-600`) are banned: they have no dark-mode counterpart and render as a light block on a
near-black card.

Shared components live in `resources/js/components/ui/` — `PageHeader`, `Badge`, `EmptyState`,
`FormField`, `BaseModal`, `ConfirmDialog`, `Skeleton`/`TableSkeleton`, `Toaster` — with the
`useToast`, `useTheme` and `useSidebar` composables. `BaseModal` is the only modal shell: it carries
`role="dialog"`, `aria-modal`, Escape-to-close, a focus trap, focus restore, body scroll lock and a
mobile bottom-sheet layout. See the `vue-admin-ui-patterns` and `styling-rtl-responsive` skills for
the full contracts and the accessibility floor.

The layout owns the app shell (persistent sidebar on `lg+`, off-canvas drawer below it, top bar with
the notification bell, locale switch, theme toggle and user menu). A page renders one container and
starts with a `PageHeader`:

```vue
<div class="mx-auto flex w-full max-w-[1300px] flex-col gap-5 px-4 py-6 md:py-8 text-start">
```

### Global search — the command palette

`Cmd/Ctrl+K` (or the search button in the top bar) opens a palette that searches the whole CMS at
once. Two kinds of hit share one list: **Go to** entries are matched locally against the same
permission-filtered destinations the sidebar renders, so jumping to a screen never waits on the
network, and record hits come from `GET /search` (`Admin\Search\SearchController`).

- **Every module is gated twice**: its feature flag has to be on _and_ the signed-in admin has to
  hold its permission. The route itself carries no `permission:` middleware because one endpoint
  spans ten modules — the controller filters each one against the caller. An admin can never see a
  row from a screen they cannot open, and the `users` / `app_users` groups are scoped by guard so
  the shared `users` table cannot leak admin accounts into the app-user module or vice versa.
- **Results deep-link to the module's own list**, as `?search={term}&highlight={id}`: `search`
  guarantees the row is on the first page, `highlight` makes it glow on arrival (see
  `useHighlight`). There are no bespoke detail pages to link to, and this reuses machinery that
  already exists.
- `LIKE` wildcards in the term are escaped. A bare `%` matches nothing, not everything.
- Five rows per module, and the palette is a jump list — it is not a report, and it is not a
  replacement for a module's own filters.

Adding a module? Add a private method to `SearchController` following the existing shape, gate it
with `allows(permission, featureFlag)`, and add its key to `GROUP_ICONS` in `CommandPalette.vue`.
The group label is looked up as an i18n key, so a module whose locale key differs from its group
key goes in `GROUP_LABEL_KEYS`.

### DevSettings: a Save button, and a search that reaches every card

Two things make a nine-section panel usable, and both are wired once in
`pages/DevSetting/Index.vue`.

**Toggles are a draft until you press Save.** `updateEnv()` takes a `values` map (and an
optional `base` map for `.env.production`) and writes the lot in one request, because a
single write rewrites `.env`, mirrors it into `.env.production` and clears the config
cache — doing that per click made a pass over eleven switches eleven round trips of it,
slow enough to watch. `EnvironmentSection.vue` keeps a `draft` seeded from the props,
sends only the keys that actually changed, and re-seeds from the props on every reload,
so the server stays the source of truth. The card shows the dirty count, a per-row
"unsaved" chip and a Discard button.

- The key whitelists are a **security boundary**, not tidiness: this endpoint writes
  arbitrary env values, so unknown keys fail validation loudly rather than being
  intersected away — a silently dropped key is a save that reported success and did
  nothing. `onlyKeysRule()` validates array _keys_, which `array` and `field.*` cannot.
  `$envToggles` guards `values`; the narrower `self::BASE_TOGGLES` guards `base`
  (`$productionOnlyKeys` also holds the two URLs, which belong to `updateUrls()`).
- Nothing half-applies: a batch naming `.env.production` when there is no such file is
  refused whole.
- `updateProductionEnv()` and its route are gone — folded into `base`.
- `tests/Feature/Admin/EnvToggleBatchTest.php` pins the whitelists. **Every case there
  is rejected by the validator and additionally asserts `.env` is unchanged byte for
  byte** — a test that reached the controller body would edit the developer's own
  machine, so that second assertion is the guard, not a nicety.
- The switches are grouped (`TOGGLE_GROUPS`) into feature modules vs behaviour. A key
  the controller exposes but no group lists still renders, under `env_group_other` —
  otherwise a flag would be written by a screen that never shows it.

**Search covers all nine sections at once.** Sections are `v-if`'d, so a search cannot
read labels out of the DOM — eight of nine are not mounted. `settings-index.ts` is that
missing index: one entry per `SettingsCard`, with the section it lives in and the
`anchor` the card renders as `id="setting-{anchor}"`. Results replace the section;
picking one mounts the section, scrolls to the card and rings it for two seconds.

`label` is an i18n key so results read like the card's own heading; `keywords` is
deliberately **not** translated — it carries the env var names and English jargon a
developer actually types (`HAS_PAGES`, `pusher`), which no translation should hide.
Terms are ANDed, so a second word narrows.

Adding a card? Give it an `anchor` and add the matching entry. A card missing from the
index is simply unreachable by search, with no error anywhere.

The field itself is `SettingsSearch.vue`, placed **inside both navs** — the sidebar on
`lg+` and the bottom pill below it — rather than in a bar above the content, so you
search from the same place the sections are listed. Defined once and rendered twice;
don't inline a second copy.

The palette (`Cmd/Ctrl+K`) searches the same index, gated on `is_local` **and**
`super_admin`, and links to `…/dev-settings#{anchor}`. `resolveHash()` turns a bare
anchor back into its section through `settingsIndex`, so the URL never encodes where a
setting lives — one place knows. It runs on mount _and_ on `hashchange`, because
arriving from the palette while already on the page changes only the hash.

### The database list and phpMyAdmin

A DevSettings section that reads the live Hostinger account, `local` +
`role:super_admin` like the rest of the panel, so it does not exist on a deployed
install.

**It lists the whole account** and opens phpMyAdmin.
`GET /api/hosting/v1/accounts/{username}/databases/{name}/phpmyadmin-link` returns a
`…/signon.php?sid=…` URL, which **is a live credential for that database** — anyone
holding it is signed in. So it is fetched per click and handed straight to the browser:
never written to `.deploy.json`, never logged, never part of the page payload. The tab is
opened synchronously with a placeholder and redirected once the link arrives, or Safari
treats the post-`await` `window.open` as a popup.

The hosting username belongs to the plan, not the site, so the account list is
de-duplicated before asking each one for its databases — otherwise every database on a
multi-site plan is listed once per site.

`tests/Feature/Admin/HostingerDatabasesTest.php` fakes every HTTP call and blocks strays,
so nothing there can reach the real account.

There is deliberately **no SSH console**. One was built and removed: the panel already
reaches these servers through Deploy, and a box that runs arbitrary remote commands is a
standing hazard for the convenience it buys. `remotePaths()` and `resolveFlavorSsh()`
survive as `runSshDeploy()` helpers.

### Provisioning a deploy flavor on Hostinger

A flavor needs a domain and five `DB_*` values, which otherwise means clicking through
hPanel and typing them into the Deploy panel. With `HOSTINGER_API_TOKEN` set (hPanel → API,
read via `config('services.hostinger.token')`), each flavor card grows a **Provision**
button that creates them and writes them into `.deploy.json` itself.

- **Per flavor, and never part of a deploy.** It creates real, billable infrastructure on a
  live account, so it only runs from a button pressed for one flavor. Most projects use one
  or two flavors; the rest are simply left alone. With no token the buttons do not appear.
- **One picker, two groups, and the mode is read off the choice.** _Attach to an existing
  website_ lists the sites already on the account; _Create a new website_ lists the free
  Hostinger subdomain, every domain, and "Other domain". Picking from the first group sends
  `mode: existing`, anything else sends `mode: website` and reveals the prefix, plan and
  datacenter fields. This used to be a radio pair above two separate dropdowns, which asked
  you to classify the decision before making it — the question is only ever "which host does
  this flavor live on".
  Existing entries carry an `existing:` prefix in the option value because **the same domain
  legitimately appears in both groups**: `example.com` can be attached to as a website, or
  used as the base for `dev.example.com`. Without the prefix the two collide on one value.
  Creating covers a whole domain, a subdomain of one (`dev` + `example.com`) or a generated
  free host; it needs the plan's `order_id` and, only for a plan's first website, a
  `datacenter_code`. There is no separate "subdomain" mode: a subdomain here _is_ a website
  whose name has a dot in it.
  The domain list is **everything on the account, subdomains included**, so a prefix on
  `crm.example.com` reaches `api.crm.example.com`. It merges the account's portfolio (`GET /api/domains/v1/portfolio`, a different API group
  from the rest) with the domains of sites already hosted, since the two barely overlap: a
  domain bought elsewhere and pointed here is hosted but not registered. A domain that already
  serves a website is listed but disabled, and "Other domain" takes anything the apex
  heuristic (label counting, not a public suffix list) gets wrong. "Free Hostinger subdomain"
  calls `POST /domains/free-subdomains`, which takes no input and generates a
  `*.hostingersite.com` host that resolves with no DNS to point — the quickest throwaway
  target for a dev or staging flavor.
- `App\Services\Hostinger` wraps the three endpoints used (list websites, create subdomain,
  create database) over `Http`. The SDK is deliberately not a dependency: it ships every
  product group for the two this panel touches.
- **Both creating modes call create-website, never the subdomains endpoint.** Hostinger can
  make a subdomain either way and they are not equivalent: `POST /websites` with the full host
  (hPanel's "add a website") gives an **independent website** with its own dashboard and its
  own document root, while `POST /websites/{parent}/subdomains` (hPanel's Domains →
  Subdomains) makes a **subfolder of the parent** — no dashboard of its own, served from the
  parent's `public_html/{directory}`. The second is what the API docs make look like the
  obvious choice for a subdomain, and it is the wrong one here: a flavor needs its own root.
- **A subdomain is not served from `~/domains/{subdomain}/public_html`.** Hostinger creates
  that directory, but the vhost's real root is under the _parent_ — `root_directory` comes
  back as `~/domains/{parent}/public_html/{directory}`. Deploying to the derived path writes
  to a folder nothing reads and publishes nothing, with no error anywhere. Provisioning
  therefore records the API's `root_directory` as the flavor's `document_root` in
  `.deploy.json`, and `runSshDeploy()` uses it when set, falling back to the derived path for
  a hand-configured apex domain. That path reaches a remote shell, so it is pattern-checked
  at save time _and_ again at deploy time.
- **Every field on the wire is snake_case, in both directions.** The SDK's docs list camelCase
  PHP properties (`websiteDomain`, `orderId`, `isEnabled`) but it serializes through Symfony's
  `CamelCaseToSnakeCaseNameConverter`, so the JSON the API sends and accepts is
  `website_domain`, `order_id`, `is_enabled`. Sending camelCase fails with a validation error
  naming a field you think you sent; reading camelCase silently yields nulls.
- Provisioning also adds the **Laravel scheduler cron** (opt-out checkbox, on by default):
  `{REMOTE_PHP} {document_root}/backend/artisan schedule:run` every minute, matching what the
  account's other projects run. Deploy puts the app in `backend/` under the document root and
  copies only `public/` up a level, so artisan is there, not at the root. The cron list belongs
  to the _account_, not the site, so the command is matched before creating — provisioning a
  flavor twice would otherwise leave two schedulers racing on one install. A cron failure is
  reported in the flash and never fatal: the hosting and database already exist by then, and a
  cron job is the one part that is trivial to add by hand.
- **`DB_HOST` is `127.0.0.1`, never the API's `host`.** The create-database response reports
  the _remote_ MySQL hostname (`srv1568.hstgr.io`, hPanel's Remote MySQL value), which only
  answers connections from an allow-listed external IP. The deployed app runs on that same
  server, so the flavor's `.env` gets the loopback — matching what `.env.production` ships.
- **The hosting username belongs to the plan, not the site.** Every website on an order shares
  it, so `createWebsite()` falls back to the username of any site on the same `order_id` when
  the new one has not surfaced yet. That matters because the username exists only to build the
  database endpoint's path: without the fallback a lagging list aborts provisioning _after_
  the website was created, leaving a site with no database and nothing written to
  `.deploy.json`.
- **The lists lag the writes.** A website or database is readable a second or two after it is
  created, so `createWebsite()` and `createDatabase()` poll (6 tries, 1.5s apart) instead of
  reading once — a single read reported a site that plainly existed as missing and aborted
  provisioning _after_ creating real hosting. `new Hostinger(pollDelayMs: 0)` in tests.
- **Every create endpoint answers with an empty body**, so `createWebsite()` re-lists to learn
  the hosting username it landed under (needed as a path segment by everything
  account-scoped), and `createDatabase()` creates and then re-lists — Hostinger prefixes the account username onto the name and user you ask for
  (`dev` → `u983470049_dev`), and the connection host is not derivable at all.
- The database password is generated by `Hostinger::databasePassword()` and written to
  `.deploy.json`, because the API never returns it. **Not `Str::password()`**: that guarantees
  one character per _group_ it is given and both letter cases share one group, so it can
  return a password with no uppercase — which Hostinger rejects ("Must contain at least one
  uppercase letter"), rarely enough to look intermittent. The generator satisfies each rule by
  construction and stays alphanumeric, since the value is written into `.deploy.json` and then
  into a generated `.env` on the target. `.deploy.json` is gitignored and holds live credentials.
- The token is entered in **DevSettings → Deployment → Hostinger API** (a masked field plus a
  Test connection button that really calls `listWebsites` and names the account it sees).
  Saving an empty field removes it and switches provisioning back off. It is never sent back
  to the browser — the page only receives a `hostingerConfigured` boolean.
- **`HOSTINGER_API_TOKEN` is local-only and must never appear in `.env.production`.**
  `buildFlavorEnv()` starts from that whole file and only overrides specific keys, so
  anything in it is copied verbatim onto every deploy target — where the deploy panel does
  not even exist (it is gated to the local environment), leaving an unusable credential that
  can create and delete databases, subdomains and websites across the entire hosting
  account. `DevSettingController::$neverSyncToProductionKeys` stops `setEnvValue()` copying
  it across.
- `tests/Feature/Admin/HostingerProvisionTest.php` fakes every request and calls
  `Http::preventStrayRequests()`. **No test drives a successful provision over HTTP** — that
  would create real hosting and overwrite the developer's own `.deploy.json`.

### Deploy flavors: only production is production

`APP_ENV` is derived from the flavor and never typed by hand (`appEnvForFlavor()`): the
`production` flavor deploys as `production`, and dev/staging/uat deploy under their own
names. So those three are neither `local` nor `production` — `IS_TESTING=true` works on
them (echoed OTP codes, no rate limiting), while the `local`-only developer surfaces
(DevSettings, the seeded admin credentials the login page prints) stay off, because
`app()->environment('local')` is false there.

In the Deploy panel the production flavor's `APP_DEBUG` and `IS_TESTING` overrides are
therefore shown **locked**, with the reason, rather than offered and then ignored — and
`saveDeployConfig()` writes `false` for both on that flavor whatever arrives, so a
hand-edited `.deploy.json` cannot smuggle one in either.

The production flavor being `production` is load-bearing in the other direction:
`AppServiceProvider::neutralizeTestingModeInProduction()` keys off it and force-disables
`IS_TESTING` there, so the live site cannot be talked into echoing OTP codes by an env file
that shipped with the flag on. `app:assert-production-safety` reads the raw env file rather
than config, so a deploy still fails loudly instead of relying on the runtime override.

### Rows the CMS may not delete

Two kinds of row exist because _code_ depends on them, not because an admin created them. Both are
fully editable and switchable, and neither can be deleted or renamed out from under the code.

- **Pages in `PROTECTED_PAGES`** — a mobile build links to Terms and Privacy by slug, so a deleted
  page is a dead screen in a shipped binary. **Deactivating counts as deleting**: the API's
  `show()` filters on `active()`, so an inactive page answers 404 exactly like a missing one — a
  protected page cannot be switched off, in the editor, the row toggle, or a bulk update. `App\Helpers\ProtectedPages` reads the list,
  the list is edited in DevSettings → Environment and the CMS follows it: adding a slug
  creates the page (`PageSeeder`), **removing one deletes it**, both on save. Never per row
  in the CMS: an admin who could untick protection could delete the page
  a moment later. The slug is frozen too — renaming is otherwise the way out of the list.
- **Notification templates with a non-null `system_type`** — the copy behind a notification the
  application fires itself, found with `NotificationTemplate::forSystemType('order_shipped')`. The
  admin owns the words, in every language; the code owns the key and the trigger. A controller may
  never set `system_type` — only a seeder.

Both guards are registered in `booting()`, not `booted()`, so they run before `HasTranslations`'
own `deleting` listener: a guard that ran after it would cancel the delete once the row's copy was
already gone. Each returns `false` to refuse and **nothing** otherwise — any non-null return halts
the rest of the chain and the translation cleanup never runs.

Adding a feature that sends its own notification? It gets a `system_type`. See the
`admin-feature-crud` skill, "Automatic notifications are system templates", for the seeder shape
and the null-handling rule. `tests/Feature/Admin/ProtectedPagesTest.php` and
`SystemNotificationTemplateTest.php` pin both.

**The activity log can't be deleted at all.** An audit trail an admin can delete from records
nothing, so there are no delete routes and no delete or bulk-delete in the panel (View is the
only row action; search, filters and CSV export stay). `ActivityLog::booted()` throws on
`deleting` rather than returning `false`: with no delete path left in the CMS, anything that
reaches it is a bug that should be loud, not a silent no-op. `ActivityLogReadOnlyTest.php` pins it.

### Timezones: the database holds UTC, the admin sees their own

`config('app.timezone')` stays `UTC` and every stored datetime is UTC. Two pieces bridge that to
whoever is looking:

- **Display** — `useDateFormat().formatDate()` renders a stored UTC value in the viewer's locale
  and chosen timezone. Never print a raw DB datetime.
- **Writes** — the `HasUserTimezone` trait converts a user-entered wall-clock datetime to UTC on
  `saving`, for the fields the model lists in `$userTimezoneDates` and only when they are dirty.
  The timezone it converts from arrives as the `X-Timezone` header, set on every Inertia request in
  `resources/js/app.ts` from the picker in the corner toolbar.

The picker (`Shared/TimezonePicker.vue`) defaults to `auto`, which follows the browser. Choosing a
zone persists in `localStorage` and changes both what is displayed and what the next write converts
from. A missing or bogus header falls back to UTC and shifts nothing.

Any feature that lets an admin pick a date and time MUST use the trait — see the
`admin-feature-crud` skill's traits reference. `tests/Feature/HasUserTimezoneTest.php` pins the
behaviour.

### Server-side rendering

SSR is off by default (`INERTIA_SSR_ENABLED=false`) — an admin panel rarely needs it and it costs
you a second process — but it **works**, and the build is verified against it.

If you turn it on, remember that `resources/js/ssr.ts` must install everything `resources/js/app.ts`
installs. A missing plugin does not fail the build; it throws at render time for every page whose
components touch it. Three separate omissions used to make SSR 500 on every authenticated page:
vue-i18n was never installed, Ziggy's `route()` had no server-side definition (the `@routes` Blade
directive only covers the browser, so the route list is shared as an Inertia prop instead), and
Laravel Echo was called during render. Echo is browser-only — `useDeviceRevocation` and
`useAdminNotifications` no-op when `window` is undefined; keep it that way.

```bash
npm run build:ssr && node bootstrap/ssr/ssr.js   # then POST a page object to :13714/render
```

### Testing

`php artisan test` runs against SQLite in memory. `phpunit.xml` pins the feature flags,
`AUTH_MODE`, the allow-lists and `APP_X_API_TOKEN`, so the suite tests one known configuration
rather than whatever the developer's `.env` happens to hold.

Shared helpers in `tests/Pest.php`: `adminUser()` (admin with an active web role),
`adminWithPermissions([...])` (an admin holding exactly those permissions, for gate tests) and
`apiHeaders()` (the `X-API-TOKEN` / `X-Device-Id` / `X-Platform` / `Accept-Language` set every API
request needs). Every model has a factory. `php artisan db:seed --class=DemoSeeder` fills a local
database with believable demo content and is idempotent.

### There is a knowledge graph of this repo

`graphify-out/` holds a navigable graph of the codebase — `graph.json` (2,866 nodes /
5,135 edges, 245 communities), `graph.html` to browse it, and `GRAPH_REPORT.md` naming
the god nodes, cross-community bridges and weak spots. It is **generated output and
gitignored**; rebuild with `/graphify . --update`, which re-extracts only what changed.

Nodes for code come from a deterministic AST pass (no LLM, no API key); the handful of
prose nodes come from `CLAUDE.md`, `README.md` and friends. The build deliberately
excludes `vendor/`, `node_modules/`, build output and the vendored agent-skill mirrors
(`.claude/`, `.cursor/`, `.codex/`, `.gemini/`, `.agents/`) — those are ~140 files of
generic framework advice that would swamp the project's own 400.

Ask questions of it with `graphify query "<question>"` rather than rebuilding. One
caveat from the last build: ~620 edges dangle, pointing at framework symbols
(`Illuminate\*`) that live in the excluded `vendor/`. That is expected, not corruption.

===

<laravel-boost-guidelines>
=== foundation rules ===

# Laravel Boost Guidelines

The Laravel Boost guidelines are specifically curated by Laravel maintainers for this application. These guidelines should be followed closely to ensure the best experience when building Laravel applications.

## Foundational Context

This application is a Laravel application and its main Laravel ecosystems package & versions are below. You are an expert with them all. Ensure you abide by these specific packages & versions.

- php - 8.4
- inertiajs/inertia-laravel (INERTIA_LARAVEL) - v3
- laravel/framework (LARAVEL) - v13
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
- @inertiajs/vue3 (INERTIA_VUE) - v3
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

# Inertia v3

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

# Laravel 13

- CRITICAL: ALWAYS use `search-docs` tool for version-specific Laravel documentation and updated code examples.
- Since Laravel 11, Laravel has a new streamlined file structure which this project uses.
- This project requires PHP 8.3+ (`composer.json` pins `^8.3`, matching what laravel/framework ^13 installs).

## Laravel 13 Structure

- Since Laravel 11, middleware are no longer registered in `app/Http/Kernel.php`.
- Middleware are configured declaratively in `bootstrap/app.php` using `Application::configure()->withMiddleware()`.
- `bootstrap/app.php` is the file to register middleware, exceptions, and routing files.
- `bootstrap/providers.php` contains application specific service providers.
- The `app/Console/Kernel.php` file no longer exists; use `bootstrap/app.php` or `routes/console.php` for console configuration.
- Console commands in `app/Console/Commands/` are automatically available and do not require manual registration.

## Database

- When modifying a column, the migration must include all of the attributes that were previously defined on the column. Otherwise, they will be dropped and lost.
- Laravel 12+ allows limiting eagerly loaded records natively, without external packages: `$query->latest()->limit(10);`.

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

### A scrolling panel shows where its content continues

The navbar's link list carries `scroll-shadows` (utility in `resources/css/app.css`). A soft
shadow appears at whichever edge still has content past it — under the header when the list is
scrolled down, above the pinned footer buttons while there is more below — and disappears at
each end, so "there is more here" is answered without scrolling to find out.

It is pure CSS and has no state to keep in sync: two `background-attachment: local` layers
painted in `--color-card` travel with the content and sit over two `scroll` layers, uncovering
a shadow exactly when that edge is not the end. The shadow is mixed from `--color-foreground`,
so it darkens on the light theme and lightens on the dark one instead of being a black smudge
nobody can see. Reuse the utility on any panel whose edges are covered by fixed furniture;
don't hand-roll scroll listeners for this.

### The deploy modal picks which seeders run

The Deploy modal lists every seeder in `database/seeders/` (discovered at runtime, so a new
one appears without touching the UI) with a humanised label and its class docblock's first
sentence as a hint, plus select-all / clear. `DatabaseSeeder` is excluded — "all of them" is
what the Migrate & Seed option already means. The request carries `seeders: string[]`; each
runs on the target as its own `db:seed --class=… --force`, logged line by line.

- It replaced a single "run seeders" checkbox, which on a live site meant re-seeding demo
  content, translations and notification templates just to refresh roles and permissions. The
  common case — a plain Migrate plus the roles-and-permissions seeder — is now expressible.
- **A seeder name reaches a remote shell**, so it is whitelisted against the discovered
  classes in validation AND `escapeshellarg`-ed in the command. Both layers, deliberately:
  the modal would otherwise be arbitrary remote execution.
- A legacy `run_seeders: true` payload still means "run DatabaseSeeder", and says so in the
  deploy log, so an older saved target does not silently stop seeding.

### Scrollbars are thin and themed, once, globally

`resources/css/app.css` sets `scrollbar-width: thin` and a `--color-primary` thumb on `*`,
with `::-webkit-scrollbar` rules behind it for engines that lack the standard properties
(Safari < 18.2, Chrome < 121). Both properties sit on `*` rather than `:root` because
`scrollbar-width` doesn't inherit, and resolving `--color-primary` per element is what makes
dark mode work without a second rule. The `scrollbar-none` utility hides a bar completely,
for a chip/tab strip the user swipes — not for anything whose only route to the rest of its
content is the scrollbar. Don't style scrollbars per component.

### Lists survive a write, and the row you touched glows

Every admin list is `Inertia::scroll()` + `<InfiniteScroll>`. Two things make a create/update/delete feel right on one, and both are wired once, globally:

- **`->scrollPaginate($perPage)` instead of `->paginate($perPage)->withQueryString()`** (Builder macro, `AppServiceProvider::configureScrollPagination()`). A write redirects back to the list; that full response used to carry page 1 only while the InfiniteScroll component still remembered it had loaded page N, so its next fetch appended page N+1 after page 1 and the rows in between (the edited one included) were gone. `resources/js/app.ts` now sends `X-Inertia-Scroll-Restore` (`{pageName: reachedPage}`) on every non-GET visit — the header survives the redirect like Inertia's own — and the macro answers with pages 1..N as a paginator sitting on page N. Partial reloads (the scroll's own page fetches) ignore it; a forged value is capped at 50 pages. Any new list must use the macro, or it regresses to the old behaviour.
- **`->with('highlight', $model->id)` on the success redirect of every `store()`/`update()`**, shared as the `highlight` prop by `HandleInertiaRequests`. `installHighlight()` (`composables/useHighlight.ts`) turns that — or a `?highlight={id}` deep link from a notification — into a 6-second glow on the row carrying `v-highlight="row.id"`, and scrolls it into view. Every row/card in a `*Table.vue` carries the directive; a table with no per-row id (translations) is the one exception. Don't bind the glow classes by hand — the directive is the single mechanism.

- **A scroll prop must never merge except for the scroll component's own fetch.**
  `Inertia::scroll()` marks its prop mergeable and the package's `ScrollProp` appends
  **whenever `X-Inertia-Infinite-Scroll-Merge-Intent` is absent** — which is every
  request that is not InfiniteScroll asking for the next page, including the redirect
  after a write. Combined with the macro above, that redirect returned pages 1..N _and_
  told the client to append them onto the pages 1..N it already held: every row twice.
  The package's escape hatch is naming the prop in the visit's `reset:` array, which is
  one array in one options object per call site, on nine lists, remembered forever — and
  two lists never had it. `App\Http\Inertia\ScrollProp` decides it from the header
  instead, and `AppServiceProvider::register()` binds `App\Http\Inertia\ResponseFactory`
  over the package's so `Inertia::scroll()` returns it everywhere. **A new list needs no
  new spelling and cannot opt out.** Keep passing `reset: ['<scrollProp>', 'success',
'error', 'filters']` on writes anyway: it also resyncs the client's page counter
  (`scrollProps.<name>.reset`), which matters when a bulk delete shortens the list enough
  to move where the next page begins.

`tests/Feature/Admin/InfiniteScrollRestoreTest.php` pins all three, the last one across
every admin list rather than the one that happened to break.

### One device row per phone, one row per FCM token

`user_devices` is unique on `(user_id, device_id)`, and `UserDevice::claimFcmToken()` takes a
token off every other row the moment one reports it. Both exist because
`User::fcmTokens()` feeds `FCMHelper::send()` → `sendMulticast()`, which delivers **one push
per token it is handed**: a token stored twice is the same notification twice on the same
handset, which is exactly what customers saw — the same message a dozen times in the tray.

Two writers were creating the duplicates, and both are fixed:

- **Every sign-in inserted a row.** `AppUserController::trackDevice()` created a
  `user_devices` row per issued token while `IdentifyDevice` already kept one for that phone,
  so N sign-ins on one handset meant N rows carrying the same FCM token. It now
  `updateOrCreate`s on `device_id` and repoints `personal_access_token_id` at the newest
  token — one row per phone in the sessions list, too.
- **`IdentifyDevice::touchDevice()` read-then-created**, which two concurrent requests from
  the same phone both pass. It now `updateOrCreate`s; the unique index is the real guard.

A token moving is the other half: a reinstall hands the same token to a fresh `X-Device-Id`
and a shared phone hands it to another user. Without releasing it from the old row the push
is delivered twice, or keeps reaching the account that signed out. `claimFcmToken()` is
called after every device write, and `fcmTokens()` is `->unique()` as a backstop.

The migration dedupes what is already stored (newest row per device wins) before adding the
index. `tests/Feature/Api/DeviceTokenUniquenessTest.php` pins all of it.

### The app is told which FCM topics to subscribe to

`GET /api/config` carries `fcm_topics`: **only the per-language variants a device can
subscribe to**, as `{ name, base, lang }` — `users_en`, `users_ar`, `guests_en`… `base` is
the group (`guests`, `users`, or whatever `FCM_TOPICS` lists) and `lang` its language code.
A client subscribes to the variant matching the device's state and its current language, and
re-subscribes when either changes.

- **The bare base is never published.** A device has one language at a time, so one
  subscribed to both `users` and `users_ar` would receive every broadcast twice. (The one
  exception: an install with no active languages has no variants, so the bases are published
  and `sendTargets()` delivers to them directly.)
- **`all` is not a topic.** It is an option in the CMS notification-template picker meaning
  "every base, in every language", and it is stripped out of `bases()` even if an admin types
  it into `FCM_TOPICS` — otherwise the install would grow `all_en`/`all_ar` topics no device
  ever subscribes to. `FcmTopics::published()` excludes it, `all()` (the real topics) excludes
  it, `structured()` lists it first so the picker can offer it, and `selectable()` is what the
  template validation accepts.
- **A send fans out; a template stores one choice.** `FcmTopics::sendTargets()` is the whole
  rule and `SendNotificationTemplate` loops over it, taking each language's copy of the title
  and body: `users_ar` → itself alone; `users` → `users_en` + `users_ar`; `all` → every base ×
  every active language.

Publishing the list rather than letting clients hardcode names is the point: topic names are
per-install, a project can add one from DevSettings, and a new language silently adds a
variant per base. A build with `users_en` baked in stops receiving anything the day either of
those happens, with no error anywhere.
