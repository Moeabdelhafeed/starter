# Inertia Starter — Project Rules

## Stack

- **Backend:** Laravel 13, PHP 8.2+, MySQL
- **Frontend:** Vue 3 (`<script setup>` + TypeScript), Inertia.js v3, Tailwind CSS v4
- **Auth:** Sanctum (dual guard: `web` for admin, `api` for mobile app), Spatie Permission for roles
- **UI:** Reka UI (headless), lucide-vue-next (icons), vue-i18n (translations)
- **Build:** Vite 7, Wayfinder (route generation), Ziggy (route helpers)

---

## Skills — Activate Proactively

Domain-specific conventions live in `.claude/skills/`. Activate the relevant skill *before* touching that part of the app — don't wait until you're stuck or something breaks:

| Skill | Covers |
|---|---|
| `admin-feature-crud` | New admin feature scaffolding: directory structure, dual guard, web route conventions, controller pattern (index/store/update/destroy/bulk), RoleSeeder permission wiring, the New Feature Checklist, model traits (`reference/traits.md`), soft deletes (`reference/soft-deletes.md`). |
| `vue-admin-ui-patterns` | Vue page/table/modal/form conventions, the mandatory method-spoofed PUT/DELETE workaround, `ImageUpload`/`VideoUpload` components, sticky-actions tables, the table/grid view toggle, Inertia shared props. |
| `styling-rtl-responsive` | RTL logical-property rules (banned `ml-*`/`mr-*`/etc.), semantic theme color tokens, icon library, layout consistency, mobile-first responsive breakpoints. |
| `translations-i18n` | The three translation systems (Vue i18n `t()`, PHP `__('admin.key')`, DB-backed `Trans::get('api.key')`), `app`/`web` sub-groups, placeholder protection in the CMS. |
| `mobile-auth-identity` | Mobile API auth: `routes/api.php` auth endpoints, rate limiting, `AUTH_IDENTIFIERS`/`AUTH_MODE`, register/login/OTP, the field-keyed error convention, account deletion, Firebase social auth. |
| `mobile-device-tracking` | `X-Device-Id`/`X-Platform`/`X-FCM-Token` headers, `IdentifyDevice` resolution order, guest users, multi-session device management, FCM. |
| `dynamic-storage-media` | The keyed media store (Dynamic Storage): `MediaItem`, `POST`/`GET /api/media`, the admin Media CMS. |
| `realtime-broadcasting` | Pusher broadcast events, private channel auth, `ShouldBroadcastNow`, Echo listeners. |

## Dual Guard System

- **Web guard (`web`):** Admin panel users. Roles: `super_admin`, `fallback`, custom roles.
- **API guard (`api`):** Mobile app users. Role: `user`.
- Permissions are web-guard only. Each feature has one permission (e.g. `users`, `roles`, `translations`).

---

## Global Reference

### Environment Variables

Key `.env` flags that affect behavior:
- `APP_USERS` — enables mobile app user module and API auth routes.
- `HAS_TRANSLATIONS` — enables app translations feature (admin panel routes, navbar links, and API endpoints for translations/languages).
- `HAS_NOTIFICATION_TEMPLATES` — enables the notification templates feature (admin panel routes + navbar link). Default `true`. Gated in `routes/web.php`, exposed as Inertia shared prop `has_notification_templates`, toggleable via DevSettings.
- `HAS_PAGES` — enables the pages feature (admin CRUD, public `/p/{slug}` route, and API page endpoints). Default `true`. Gated in `routes/web.php` + `routes/api.php`, exposed as `has_pages`, toggleable via DevSettings.
- `HAS_APP_SETTINGS` — enables the App Settings feature (admin CRUD for social/contact/app-store/google-play/app-gallery link blocks + the public `GET /api/app-settings` endpoint). Default `true`. Gated in `routes/web.php` + `routes/api.php`, exposed as `has_app_settings`, toggleable via DevSettings. Items use `HasImage` + `HasTranslations` (translatable `text`).
- `HAS_ACTIVITY_LOGS` — enables the activity logs admin feature (routes + navbar link). Default `true`. Models still record logs via `LogsActivity`; only the admin viewer is gated. Exposed as `has_activity_logs`, toggleable via DevSettings.
- `HAS_DYNAMIC_STORAGE` — enables the Dynamic Storage feature (see `dynamic-storage-media` skill). Default `true`. Gated in `routes/web.php` + `routes/api.php`, exposed as `has_dynamic_storage`, toggleable via DevSettings.
- `MEDIA_MAX_IMAGE_KB` / `MEDIA_MAX_VIDEO_KB` / `MEDIA_MAX_FILE_KB` — per-kind upload size caps (KB) for Dynamic Storage. Defaults `2048` / `20480` / `10240`. Read via `config('dynamic-storage.*')`.
- `AUTH_IDENTIFIERS` — comma-separated login identifiers (e.g. `email`, `email,phone`). See `mobile-auth-identity` skill.
- `HAS_EMAIL_FIELD` / `HAS_PHONE_FIELD` / `HAS_USERNAME_FIELD` — toggle extra profile fields (only for non-identifier fields).
- `IS_TESTING` — testing mode flag (exposes OTP in API responses).
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

### API Docs — Postman / OpenAPI (auto-generated via Scribe)

`Starter.postman_collection.json` and the OpenAPI spec are **generated from `routes/api.php`** (route signatures, FormRequest rules, controller docblocks) via `knuckleswtf/scribe` — **never hand-edit them.** Config lives in `config/scribe.php`.

- **Regenerate:** `composer api-docs` (runs `php artisan scribe:generate`, then copies the fresh collection over `Starter.postman_collection.json` at the project root). Run this whenever you add/change an API route.
- **Live docs** (no auth, intentionally public — see `intro_text` in the config): `GET /docs` (HTML), `GET /docs.postman` (collection), `GET /docs.openapi` (OpenAPI 3.0.3 spec). Assets are generated output (`resources/views/scribe/`, `public/vendor/scribe/`) — gitignored, rebuilt by `scribe:generate`, not committed.
- **Every endpoint requires** `X-API-TOKEN`, `X-Device-Id`, `X-Platform` headers (see `mobile-device-tracking` skill) — baked into the generated docs as **placeholders** (`config/scribe.php` → `strategies.headers`), never the real `.env` secret. Do not change that to `env('APP_X_API_TOKEN')` — `/docs.postman` is public, so that would leak the live token to anyone who visits it.
- **Auth-mode caveat:** Scribe only documents routes that are *currently registered*, so toggles like `AUTH_MODE=otp` (hides `register`/`forgot-password`) or `HAS_PAGES=false` will make those endpoints disappear from the generated docs until the flag is flipped back and regenerated. Regenerate against the config you want documented.
- Bearer-token response examples need a real Sanctum PAT in `SCRIBE_AUTH_KEY` (`.env`, not committed) to hit `@authenticated` endpoints during generation — without it, those examples show a 401.

**Method override on the API:** the production host blocks real `PUT`/`PATCH`/`DELETE`. Mobile/API clients must send those as **`POST` + an `X-HTTP-Method-Override` header** carrying the real verb (e.g. `X-HTTP-Method-Override: DELETE`). Laravel's kernel has method override enabled, so it routes the request to the matching `Route::delete(...)`. The Postman collection already uses this pattern for `update-profile` (PUT) and the delete endpoints — mirror it when adding new `PUT`/`DELETE` API requests, and tell mobile devs to do the same. (Routes themselves stay `Route::put`/`Route::delete`.)

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
