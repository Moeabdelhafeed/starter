# Inertia Starter Kit

A Laravel + Vue 3 + Inertia.js starter with a dual-guard admin panel and a documented mobile API.

## Features

- **Dual-Guard Authentication** — Sanctum for both the admin panel (`web`) and the mobile API (`api`)
- **Role-Based Access Control** — Spatie Permission, one permission per feature
- **Multi-Language Support** — full RTL, database-driven translations (English & Arabic seeded)
- **Modern UI** — Tailwind CSS v4 with dark mode, Reka UI components, Lucide icons
- **API Ready** — rate limiting, device tracking, and auto-generated Postman/OpenAPI docs
- **Firebase Integration (optional)** — social sign-in and push notifications; the app runs fine without credentials
- **Real-Time Broadcasting** — Pusher
- **Activity Logging** — model change audit trail
- **Soft Deletes UI** — trash/restore across admin features

## Tech Stack

**Backend** — Laravel 13 (PHP 8.3+), MySQL, Sanctum, Spatie Permission, Scribe
**Frontend** — Vue 3 (`<script setup>` + TypeScript), Inertia.js v3, Tailwind CSS v4, Reka UI, vue-i18n
**Build** — Vite 7, Laravel Wayfinder, Ziggy

## Requirements

- PHP 8.3+ (8.4 recommended)
- Composer 2
- Node.js 20+
- MySQL 8.0+

## Installation

### Quick install

```bash
./install.sh
```

Interactive: copies `.env`, installs dependencies, asks for database credentials, runs
`php artisan migrate --seed`, builds assets, and offers to start the dev server.

### Non-interactive

Edit `.env` first (at minimum `DB_*`, `ADMIN_EMAIL`, `ADMIN_PASSWORD`), then:

```bash
composer setup
```

Which runs: `composer install` → copy `.env.example` → `key:generate` →
`migrate --force --seed` → `npm install` → `npm run build`.

> The `--seed` matters. `DatabaseSeeder` creates the languages, the `super_admin` /
> `fallback` / api-`user` roles, every permission, the super admin account from
> `ADMIN_EMAIL`/`ADMIN_PASSWORD`, and the API translation strings. Without it there are
> no roles and every login fails.

Then start the dev servers:

```bash
composer dev    # laravel serve + queue + pail + vite, all in one
```

Admin panel: <http://localhost:8000>, logging in with `ADMIN_EMAIL` / `ADMIN_PASSWORD`.

> `php artisan serve` watches `.env` and restarts on every change, and DevSettings writes
> `.env` on every save — and the restarted server could not rebind the port the one it just
> killed was still holding, so it moved to 8001, then 8002, and the tab you had open stopped
> answering. DevSettings therefore **restores the file's timestamp after writing it**
> (`DevSettingController::writeEnvKey`). The value still takes effect immediately: `serve`
> hands the child every non-passthrough variable as `false`, so the child re-reads `.env`
> from disk on each request. Only `ServeCommand::$passthroughVariables` (`APP_ENV`, `PATH`,
> the Herd and Xdebug keys) need a real restart, and this panel writes none of them.

### Demo data (local / staging only)

```bash
php artisan db:seed --class=DemoSeeder
```

Adds three admin accounts on different roles, eight app users, three guests, two pages,
five app-setting links, two notification templates and a short activity trail — all built
from the factories in `database/factories`. Every demo login uses the password `password`.
Idempotent, so it is safe to re-run. It is deliberately **not** wired into
`DatabaseSeeder`, so a production `migrate --seed` never creates fake accounts.

## Checks a contributor should run

```bash
composer check        # pint --test + php artisan test + production safety assertions
npm run check         # eslint + vue-tsc --noEmit
```

Individually:

| Command | What it does |
|---|---|
| `composer lint` | Pint, fixing in place |
| `composer test` | `config:clear`, Pint in `--test` mode, then `php artisan test` |
| `php artisan app:assert-production-safety` | Fails when `APP_ENV=production` and `APP_DEBUG`/`IS_TESTING` are on, `APP_X_API_TOKEN`/`APP_KEY` are empty, `ADMIN_PASSWORD` is still the starter default, or `SESSION_SECURE_COOKIE` is not true. No-op outside production. |
| `npm run format` / `format:check` | Prettier over `resources/` |
| `npm run lint` / `lint:check` | ESLint, with and without `--fix` |
| `npm run types` | `vue-tsc --noEmit` (run `npm run build` first — Wayfinder generates the route modules it type-checks against) |

CI (`.github/workflows/tests.yml`) runs exactly these on PHP 8.4 / Node 22.

## Scheduler

The scheduled tasks live in `routes/console.php` (`users:purge-deleted` hourly,
`sanctum:prune-expired` daily). Add one cron entry on the server:

```cron
* * * * * cd /path-to-project && php artisan schedule:run >> /dev/null 2>&1
```

Hosts with no cron still get the account purge via
`App\Http\Middleware\PurgeDeletedUsersAfterResponse`, which runs the same command after a
response at most once an hour. It is a fallback, not a replacement.

## Configuration

Every flag is documented inline in `.env.example`. The ones that change behaviour most:

| Variable | Description |
|---|---|
| `APP_USERS` | Mobile-app user module + API auth routes |
| `AUTH_MODE` | `password` or `otp` |
| `AUTH_IDENTIFIERS` | Comma-separated login identifiers (`email`, `phone`, `username`) |
| `HAS_*` | Per-feature toggles (translations, pages, app settings, dynamic storage, activity logs, notification templates) |
| `IS_TESTING` | Disables rate limiting and returns OTP codes in API responses. **Never true in production.** |
| `RATE_LIMIT_*` | Per-bucket request limits (api / auth / otp) |
| `SANCTUM_TOKEN_EXPIRATION` | Mobile token lifetime in minutes (default 43200 = 30 days; `0` = never) |
| `SESSION_SECURE_COOKIE` | Must be `true` in production |
| `BROADCAST_CONNECTION` | `pusher`, `log`, or `null` |

### Firebase (optional)

Push and social sign-in only. Drop the service-account JSON at
`storage/app/private/firebase-auth.json` (or upload it in Developer Settings). With no
credentials, `GET /api/config` reports `social_auth_available: false`, the social
endpoints answer 503, and everything else keeps working.

### Pusher (optional)

```env
BROADCAST_CONNECTION=pusher
PUSHER_APP_ID=
PUSHER_APP_KEY=
PUSHER_APP_SECRET=
PUSHER_APP_CLUSTER=eu
```

## API Documentation

Generated from `routes/api.php` by Scribe — never hand-edit the collection or the spec:

```bash
composer api-docs
```

Live and **public** (no auth, by design):

- `GET /docs` — HTML documentation
- `GET /docs.postman` — Postman collection
- `GET /docs.openapi` — OpenAPI 3.0.3 spec

Or import `Starter.postman_collection.json` from the project root and fill in the
collection variables once (Collection → Variables):

| Variable | Value |
|---|---|
| `{{baseUrl}}` | e.g. `http://localhost:8000` |
| `{{xApiToken}}` | `APP_X_API_TOKEN` from `.env` |
| `{{bearerToken}}` | token from a login/register response |
| `{{deviceId}}` | any stable per-device string |
| `{{platform}}` | `ios`, `android` or `web` |

`{{fcmToken}}` appears on the requests that accept it.

**Method override:** the production host blocks real `PUT`/`PATCH`/`DELETE`. API clients
send those as `POST` plus an `X-HTTP-Method-Override` header carrying the real verb. The
collection already does this.

## Project Structure

```
app/
├── Console/Commands/   # Artisan commands (docs, purge, production safety)
├── Http/Controllers/
│   ├── Admin/          # Admin panel controllers (Inertia)
│   └── Api/            # Mobile app API controllers
├── Models/
├── Traits/             # HasImage, HasVideo, HasFile, LogsActivity, HasTranslations
└── Helpers/            # ApiResponse, Trans, FCMHelper, Firebase, PhoneNumber

database/
├── factories/          # One per model; models other than User have no HasFactory
├── migrations/
└── seeders/            # DatabaseSeeder (always) + DemoSeeder (opt-in)

resources/js/
├── pages/              # Vue pages (one directory per feature)
├── components/         # Shared/ and ui/
├── layouts/
├── locales/            # en.json, ar.json
└── composables/
```

## Developer Settings

When `APP_ENV=local`, the admin panel exposes Developer Settings for theme colors,
branding, auth settings, Firebase credentials, Pusher, production DB/mail, SSH deploy and
feature toggles.

## How to Modify

- **Backend logic** — `app/Http/Controllers`
- **UI** — `resources/js/pages`, `resources/js/components`
- **Translations** — Vue `resources/js/locales/`, PHP `lang/`, API strings in the admin Translations CMS
- **Styling** — logical Tailwind classes only (`ms-4`, never `ml-4`) so RTL keeps working

Project conventions live in `CLAUDE.md` and `.claude/skills/`.

## License

This project is proprietary software.
