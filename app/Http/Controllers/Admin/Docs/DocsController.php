<?php

namespace App\Http\Controllers\Admin\Docs;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Inertia\Inertia;

class DocsController extends Controller
{
    /**
     * Show the documentation index.
     */
    public function index(Request $request)
    {
        $sections = $this->getSections();
        $currentSection = $request->query('section', 'getting-started');

        return Inertia::render('Docs/Index', [
            'sections' => $sections,
            'currentSection' => $currentSection,
            'content' => $this->getContent($currentSection),
        ]);
    }

    /**
     * Get documentation sections.
     *
     * @return array<int, array{id: string, title: string, icon: string, items: array}>
     */
    private function getSections(): array
    {
        return [
            [
                'id' => 'getting-started',
                'title' => 'Getting Started',
                'icon' => 'Rocket',
                'items' => [
                    ['id' => 'introduction', 'title' => 'Introduction'],
                    ['id' => 'installation', 'title' => 'Installation'],
                    ['id' => 'directory-structure', 'title' => 'Directory Structure'],
                    ['id' => 'configuration', 'title' => 'Configuration'],
                ],
            ],
            [
                'id' => 'authentication',
                'title' => 'Authentication',
                'icon' => 'Shield',
                'items' => [
                    ['id' => 'dual-guard', 'title' => 'Dual Guard System'],
                    ['id' => 'admin-auth', 'title' => 'Admin Authentication'],
                    ['id' => 'api-auth', 'title' => 'API Authentication'],
                    ['id' => 'social-auth', 'title' => 'Social Authentication'],
                    ['id' => 'otp-verification', 'title' => 'OTP Verification'],
                    ['id' => 'rate-limiting', 'title' => 'Rate Limiting'],
                ],
            ],
            [
                'id' => 'features',
                'title' => 'Features',
                'icon' => 'Sparkles',
                'items' => [
                    ['id' => 'users-roles', 'title' => 'Users & Roles'],
                    ['id' => 'translations', 'title' => 'Translations'],
                    ['id' => 'activity-logs', 'title' => 'Activity Logs'],
                    ['id' => 'pages', 'title' => 'Pages'],
                    ['id' => 'soft-deletes', 'title' => 'Soft Deletes'],
                ],
            ],
            [
                'id' => 'api',
                'title' => 'API Reference',
                'icon' => 'Code',
                'items' => [
                    ['id' => 'api-overview', 'title' => 'Overview'],
                    ['id' => 'api-auth-endpoints', 'title' => 'Auth Endpoints'],
                    ['id' => 'api-user-endpoints', 'title' => 'User Endpoints'],
                    ['id' => 'api-public-endpoints', 'title' => 'Public Endpoints'],
                    ['id' => 'api-responses', 'title' => 'Response Format'],
                ],
            ],
            [
                'id' => 'traits',
                'title' => 'Traits',
                'icon' => 'Puzzle',
                'items' => [
                    ['id' => 'has-image', 'title' => 'HasImage'],
                    ['id' => 'has-video', 'title' => 'HasVideo'],
                    ['id' => 'has-translations', 'title' => 'HasTranslations'],
                    ['id' => 'logs-activity', 'title' => 'LogsActivity'],
                    ['id' => 'has-soft-delete-actions', 'title' => 'HasSoftDeleteActions'],
                    ['id' => 'notifies-admin', 'title' => 'NotifiesAdmin'],
                ],
            ],
            [
                'id' => 'frontend',
                'title' => 'Frontend',
                'icon' => 'Layout',
                'items' => [
                    ['id' => 'vue-components', 'title' => 'Vue Components'],
                    ['id' => 'ui-components', 'title' => 'UI Components'],
                    ['id' => 'modals', 'title' => 'Modals'],
                    ['id' => 'forms', 'title' => 'Forms'],
                    ['id' => 'rtl-support', 'title' => 'RTL Support'],
                ],
            ],
            [
                'id' => 'broadcasting',
                'title' => 'Broadcasting',
                'icon' => 'Radio',
                'items' => [
                    ['id' => 'pusher-setup', 'title' => 'Pusher Setup'],
                    ['id' => 'creating-events', 'title' => 'Creating Events'],
                    ['id' => 'listening-events', 'title' => 'Listening to Events'],
                ],
            ],
            [
                'id' => 'deployment',
                'title' => 'Deployment',
                'icon' => 'Upload',
                'items' => [
                    ['id' => 'environment-setup', 'title' => 'Environment Setup'],
                    ['id' => 'ssh-deployment', 'title' => 'SSH Deployment'],
                    ['id' => 'production-config', 'title' => 'Production Config'],
                ],
            ],
        ];
    }

    /**
     * Get content for a specific section.
     *
     * @return array{title: string, content: string}
     */
    private function getContent(string $section): array
    {
        $contents = [
            'getting-started' => $this->getGettingStartedContent(),
            'introduction' => $this->getIntroductionContent(),
            'installation' => $this->getInstallationContent(),
            'directory-structure' => $this->getDirectoryStructureContent(),
            'configuration' => $this->getConfigurationContent(),
            'authentication' => $this->getAuthenticationContent(),
            'dual-guard' => $this->getDualGuardContent(),
            'admin-auth' => $this->getAdminAuthContent(),
            'api-auth' => $this->getApiAuthContent(),
            'social-auth' => $this->getSocialAuthContent(),
            'otp-verification' => $this->getOtpVerificationContent(),
            'rate-limiting' => $this->getRateLimitingContent(),
            'features' => $this->getFeaturesContent(),
            'users-roles' => $this->getUsersRolesContent(),
            'translations' => $this->getTranslationsContent(),
            'activity-logs' => $this->getActivityLogsContent(),
            'pages' => $this->getPagesContent(),
            'soft-deletes' => $this->getSoftDeletesContent(),
            'api' => $this->getApiOverviewContent(),
            'api-overview' => $this->getApiOverviewContent(),
            'api-auth-endpoints' => $this->getApiAuthEndpointsContent(),
            'api-user-endpoints' => $this->getApiUserEndpointsContent(),
            'api-public-endpoints' => $this->getApiPublicEndpointsContent(),
            'api-responses' => $this->getApiResponsesContent(),
            'traits' => $this->getTraitsContent(),
            'has-image' => $this->getHasImageContent(),
            'has-video' => $this->getHasVideoContent(),
            'has-translations' => $this->getHasTranslationsContent(),
            'logs-activity' => $this->getLogsActivityContent(),
            'has-soft-delete-actions' => $this->getHasSoftDeleteActionsContent(),
            'notifies-admin' => $this->getNotifiesAdminContent(),
            'frontend' => $this->getFrontendContent(),
            'vue-components' => $this->getVueComponentsContent(),
            'ui-components' => $this->getUiComponentsContent(),
            'modals' => $this->getModalsContent(),
            'forms' => $this->getFormsContent(),
            'rtl-support' => $this->getRtlSupportContent(),
            'broadcasting' => $this->getBroadcastingContent(),
            'pusher-setup' => $this->getPusherSetupContent(),
            'creating-events' => $this->getCreatingEventsContent(),
            'listening-events' => $this->getListeningEventsContent(),
            'deployment' => $this->getDeploymentContent(),
            'environment-setup' => $this->getEnvironmentSetupContent(),
            'ssh-deployment' => $this->getSshDeploymentContent(),
            'production-config' => $this->getProductionConfigContent(),
        ];

        return $contents[$section] ?? $this->getIntroductionContent();
    }

    private function getGettingStartedContent(): array
    {
        return $this->getIntroductionContent();
    }

    private function getIntroductionContent(): array
    {
        return [
            'title' => 'Introduction',
            'content' => <<<'MD'
# Welcome to the Starter Kit

This is a comprehensive Laravel + Vue 3 + Inertia.js starter template designed to accelerate your web application development.

## What's Included

- **Laravel 13** with PHP 8.2+ support
- **Vue 3** with `<script setup>` and TypeScript
- **Inertia.js v3** for seamless SPA experience
- **Tailwind CSS v4** with dark mode support
- **Sanctum** dual-guard authentication (web + API)
- **Spatie Permission** for roles and permissions
- **Multi-language support** with RTL

## Key Features

1. **Dual Authentication System** - Separate guards for admin panel and mobile app API
2. **Role-Based Access Control** - Granular permissions for each feature
3. **Activity Logging** - Track all user actions automatically
4. **Translatable Content** - Database-driven translations with fallbacks
5. **Real-time Broadcasting** - Pusher integration for WebSocket features
6. **Developer Tools** - DevSettings panel for quick configuration

## Tech Stack

| Layer | Technology |
|-------|------------|
| Backend | Laravel 13, PHP 8.2+ |
| Frontend | Vue 3, Inertia.js v3 |
| Styling | Tailwind CSS v4 |
| Database | MySQL |
| Auth | Sanctum, Spatie Permission |
| Build | Vite 7, Wayfinder |
MD
        ];
    }

    private function getInstallationContent(): array
    {
        return [
            'title' => 'Installation',
            'content' => <<<'MD'
# Installation

## Requirements

- PHP 8.2+
- Composer 2.x
- Node.js 18+
- MySQL 8.0+

## Quick Start

1. **Clone the repository**
```bash
git clone https://github.com/your-repo/starter.git
cd starter
```

2. **Install PHP dependencies**
```bash
composer install
```

3. **Install Node dependencies**
```bash
npm install
```

4. **Copy environment file**
```bash
cp .env.example .env
```

5. **Generate application key**
```bash
php artisan key:generate
```

6. **Configure your database** in `.env`
```env
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=starter
DB_USERNAME=root
DB_PASSWORD=
```

7. **Run migrations and seeders**
```bash
php artisan migrate --seed
```

8. **Build frontend assets**
```bash
npm run build
```

9. **Start the development server**
```bash
php artisan serve
```

## Default Admin Credentials

- **Email:** admin@admin.com
- **Password:** Admin@123#

You can change these in the `.env` file or via the DevSettings panel.
MD
        ];
    }

    private function getDirectoryStructureContent(): array
    {
        return [
            'title' => 'Directory Structure',
            'content' => <<<'MD'
# Directory Structure

## Backend Structure

```
app/
├── Http/
│   └── Controllers/
│       ├── Admin/           # Web admin panel controllers
│       │   ├── Auth/        # Login/logout
│       │   ├── User/        # User management
│       │   ├── Roles/       # Role management
│       │   └── ...
│       └── Api/             # Mobile app API controllers
│
├── Models/                  # Eloquent models
├── Traits/                  # Reusable traits
│   ├── HasImage.php
│   ├── HasVideo.php
│   ├── HasTranslations.php
│   ├── LogsActivity.php
│   └── HasSoftDeleteActions.php
│
└── Helpers/                 # Helper classes
    ├── ApiResponse.php
    ├── EmailHelper.php
    ├── FCMHelper.php
    └── Trans.php
```

## Frontend Structure

```
resources/js/
├── pages/                   # Inertia pages
│   ├── Dashboard/
│   ├── User/
│   ├── Roles/
│   └── ...
│
├── components/
│   ├── Shared/              # Shared components
│   │   ├── Navbar.vue
│   │   ├── DeleteModal.vue
│   │   ├── BulkActions.vue
│   │   └── ...
│   ├── ui/                  # UI primitives
│   │   ├── Button.vue
│   │   ├── Input.vue
│   │   └── ...
│   └── {feature-name}/      # Feature-specific components
│
├── composables/             # Vue composables
├── layouts/                 # Layout components
└── locales/                 # Translation files
    ├── en.json
    └── ar.json
```

## Key Files

| File | Purpose |
|------|---------|
| `bootstrap/app.php` | Application bootstrap, middleware config |
| `routes/web.php` | Admin panel routes |
| `routes/api.php` | Mobile API routes |
| `routes/channels.php` | Broadcast channel authorization |
MD
        ];
    }

    private function getConfigurationContent(): array
    {
        return [
            'title' => 'Configuration',
            'content' => <<<'MD'
# Configuration

## Environment Variables

Key `.env` variables that control application behavior:

### Application
```env
APP_NAME=Starter
APP_ENV=local
APP_DEBUG=true
APP_URL=http://localhost
APP_SETUP_COMPLETE=true
```

### Authentication
```env
AUTH_IDENTIFIERS=email          # email and/or phone (comma-separated). Username NOT allowed here.
HAS_EMAIL_FIELD=true
HAS_PHONE_FIELD=false
HAS_USERNAME_FIELD=false        # Enables `username` field at register + login alias.
IS_OTP_WHATSAPP=false
```

### Feature Toggles
```env
APP_USERS=true                  # Enable/disable app user module
HAS_TRANSLATIONS=true           # Enable/disable app translations
IS_TESTING=true                 # Expose OTP in API responses
APP_X_API_TOKEN=your-token      # X-API-TOKEN header value
```

### Social Authentication
```env
SOCIAL_AUTH_PROVIDERS=google.com,apple.com
SOCIAL_AUTH_MAX_ACCOUNTS=0      # 0 = unlimited
```

### Validation Rules
```env
ALLOWED_PHONE_COUNTRIES=all     # Or: JO,US,SA
ALLOWED_EMAIL_DOMAINS=all       # Or: gmail.com,yahoo.com
```

### Broadcasting
```env
BROADCAST_CONNECTION=pusher     # Or: log, null
QUEUE_CONNECTION=sync           # Always use sync with ShouldBroadcastNow
```

## DevSettings Panel

When `APP_ENV=local`, access the DevSettings panel to configure:

- Theme colors (light/dark)
- Environment toggles
- Authentication settings
- Pusher credentials
- Mail configuration
- Git operations
- Deployment settings
MD
        ];
    }

    private function getAuthenticationContent(): array
    {
        return $this->getDualGuardContent();
    }

    private function getDualGuardContent(): array
    {
        return [
            'title' => 'Dual Guard System',
            'content' => <<<'MD'
# Dual Guard System

The application uses two Laravel guards on the **same `users` table**, distinguished by Spatie role + guard_name.

## Web Guard (`web`)

Admin panel. Session-based.

- **Users:** Admin users (any role on the `web` guard).
- **Protected roles:** `super_admin` (full access), `fallback` (reassignment target when a role is deleted).
- **Middleware:** `auth`, `EnsureUserIsActive`, `permission:*` (Spatie).
- **Pages:** Inertia.js + Vue, served from `routes/web.php`.

```php
Route::middleware('auth')->group(function () {
    Route::get('/', [DashboardController::class, 'index']);
});
```

## API Guard (`api`)

Mobile app REST API. Token-based via Sanctum.

- **Users:** App users with the `user` role on the `api` guard.
- **Middleware:** `auth:sanctum`, `role:user`, `active`. Most write endpoints add `verified`.
- **Auth flow:** `register` → `login` → token. See **API Authentication**.

```php
Route::middleware(['auth:sanctum', 'role:user', 'active'])->group(function () {
    Route::get('/user', fn (Request $request) => $request->user());
});
```

## Key Differences

| Aspect | Web Guard | API Guard |
|--------|-----------|-----------|
| Users | Admin users | App users |
| Role | Any (e.g. `super_admin`) | `user` (api guard) |
| Auth method | Session | Bearer token (Sanctum) |
| Permissions | Yes (Spatie, web-guard) | No (role check only) |
| Routes file | `routes/web.php` | `routes/api.php` |

## Same Table, Different Audience

Both guards use the `users` table. API auth flows (login, lookup, forgot-password, etc.) are scoped to the `user` api-guard role to avoid leaking admin records into mobile app responses.
MD
        ];
    }

    private function getAdminAuthContent(): array
    {
        return [
            'title' => 'Admin Authentication',
            'content' => <<<'MD'
# Admin Authentication

This page covers the **admin panel** (web guard). For the mobile-app API auth, see **API Authentication**.

## Stack

- **Guard:** `web` (session-based)
- **Pages:** Inertia.js + Vue
- **Middleware:** `auth`, `EnsureUserIsActive`, `permission:*` (Spatie)
- **Routes:** `routes/web.php`
- **Login URL:** `/login`

## Login Flow

1. User visits `/login` (Inertia page).
2. Submits `email` + `password` to `POST /login`.
3. `AuthController::login` validates credentials against the `users` table.
4. `EnsureUserIsActive` middleware blocks deactivated accounts (`is_active=false`).
5. Web guard creates a session; user is redirected to `/` (dashboard).

Inertia shares `auth.user`, `auth.roles`, and `auth.permissions` on every response so the UI can render conditionally.

## Protected Routes

```php
Route::middleware('auth')->group(function () {
    // Dashboard — no permission required
    Route::get('/', [DashboardController::class, 'index']);

    // Feature routes — permission required
    Route::prefix('users')
        ->middleware('permission:users')
        ->group(function () {
            Route::get('/', [UserController::class, 'index']);
            // ...
        });
});
```

## Roles & Permissions

### Protected Roles (Cannot be deleted)
- `super_admin` - Full access
- `fallback` - Reassignment target when deleting roles

### Adding a New Permission

1. Add to `RoleSeeder.php`:
```php
$perm = Permission::firstOrCreate([
    'name' => 'my_feature',
    'guard_name' => 'web',
]);
$perm->assignRole($super_admin);
```

2. Run seeder:
```bash
php artisan db:seed --class=RoleSeeder
```

3. Use in routes:
```php
Route::middleware('permission:my_feature')->group(...);
```

4. Check in Vue:
```vue
<div v-if="page.props.auth.permissions.includes('my_feature')">
    <!-- Content -->
</div>
```
MD
        ];
    }

    private function getApiAuthContent(): array
    {
        return [
            'title' => 'API Authentication',
            'content' => <<<'MD'
# API Authentication

This page explains the **concepts** behind API auth. For exact request/response shapes, see **API Reference → Auth Endpoints / User Endpoints / Public Endpoints**.

## Headers

Every request:
```http
X-API-TOKEN: your-api-token
Accept: application/json
Content-Type: application/json
Accept-Language: en      # or ar
```

Protected routes also need:
```http
Authorization: Bearer {token}
```

## Auth Model

- **Guard:** `api` (Sanctum). API users are scoped to the `user` role on the `api` guard. Admin/web-guard users are not reachable through API auth flows.
- **Token:** Bearer token returned at the **top level** of the response AND duplicated inside `data.token`. Issued by `login` and `firebase-login`.
- **Register does NOT issue a token** — it only creates the account and sends a verify OTP. The client must call `login` afterwards.

## Bootstrap: Auth Config

`GET /api/auth-config` exposes the live auth configuration so the mobile/web client can adapt its UI. Fetch on app boot. Returns: `identifiers`, `has_username_field`, `has_email_field`, `has_phone_field`, `social_providers`, `max_social_accounts`, `social_auth_available`, `is_otp_whatsapp`.

## Identifier Model

Configured via `.env`:

```env
AUTH_IDENTIFIERS=email,phone     # email and/or phone, comma-separated. Username NOT allowed here.
HAS_USERNAME_FIELD=true          # Optional. When true: required at register, login alias, editable.
HAS_EMAIL_FIELD=false            # Only when email is NOT an identifier (extra profile field).
HAS_PHONE_FIELD=false            # Only when phone is NOT an identifier (extra profile field).
```

Behavior:
- **Identifier (`email` / `phone`)** — primary login key. OTP-protected to change.
- **Username** — separate column. When enabled it is required at register, doubles as a login alias (login `identifier` is also matched against the username column), and is editable directly via `update-profile` (no OTP).
- Login lookup detects the value's kind (email format → email column; phone format → phone column; alpha → username column) and queries a single column. No OR-collisions.

Format rules:
- Email values are lowercased on every write and lookup.
- Username regex: `/^[A-Za-z][A-Za-z0-9_-]*$/` with `min:3`. Email/phone-shaped values are rejected.
- Username uniqueness is scoped to api-guard users only.

## Registration Flow

1. Client `POST /api/register` with `identifier` (email or phone), `password`, `policy_agreed`, plus `username` when enabled.
2. Server validates, creates the user, sends a `verify` OTP via the matching channel (email → EmailHelper; phone → SMS, or WhatsApp when `IS_OTP_WHATSAPP=true`).
3. **No token** in response. Client calls `login` to receive a Bearer token.

## Login + Verification Flow

1. Client `POST /api/login` with `identifier` (email/phone/username) + password.
2. Verified user → token + user.
3. Unverified user → token + `is_verified=false` + a fresh `verify` OTP (reuses the most-recent OTP if it is < 60s old to avoid spam).
4. Client calls `POST /api/verify-otp` with the OTP and the Bearer token. Account becomes verified.
5. If the OTP expired, client calls `POST /api/send-otp` (auth required) to receive a new one. Rate-limited via `throttle:otp`.

## Forgot Password Flow

Client may inspect available delivery channels first, then choose one:

1. (Optional) `POST /api/check-identifier` with `identifier` → response includes `available_channels` (e.g. `["email","phone"]`) listing which OTP destinations the user has populated.
2. `POST /api/forgot-password` with `identifier` and optional `type` (`"email"` or `"phone"`).
   - If `type` is omitted → channel auto-picked, priority `email > phone`.
   - If `type` is provided → must be one of the user's populated channels; otherwise 422 `errors.type`.
   - Response includes the actual `channel` used. Rate-limited via `throttle:otp`.
3. `POST /api/verify-forgot-password-otp` with `identifier` + `otp`.
4. `POST /api/change-forgot-password` with `identifier` + `otp` + new `password`. All existing tokens revoked.

## Identifier Change Flow (Email or Phone)

1. `POST /api/request-identifier-change` with `new_identifier` (auth + verified). Kind auto-detected; OTP sent via the matching channel. Rate-limited via `throttle:otp`.
2. `POST /api/verify-identifier-change` with `new_identifier` + `otp`. Updates the column.

Username is changed via `update-profile` directly (no OTP — no delivery channel).

## Profile Updates

`PUT /api/update-profile` (auth + verified):
- `name` always editable.
- `username` editable when `HAS_USERNAME_FIELD=true`.
- `email`/`phone` editable only when they are **NOT** identifiers (i.e., enabled as `HAS_*_FIELD` extras).
- Empty strings ignored.

Identifier email/phone changes go through the OTP-protected identifier-change flow.

## Error Format (Field-Keyed)

Errors tied to a specific request input field are returned as **HTTP 422** with the message keyed under the param name. Frontend can show inline messages next to the matching input.

```json
{
    "success": false,
    "message": "Invalid OTP.",
    "errors": { "otp": ["Invalid OTP."] },
    "data": null
}
```

Mapping:

| Endpoint | Trigger | Error Key |
|----------|---------|-----------|
| login | invalid credentials | `password` |
| register / login / forgot-password / verify-forgot-password-otp / change-forgot-password | identifier validation / not found | `identifier` |
| verify-otp / verify-forgot-password-otp / change-forgot-password / verify-identifier-change | invalid OTP | `otp` |
| change-password | wrong current password | `old_password` |
| request-identifier-change / verify-identifier-change | new identifier validation | `new_identifier` |
| firebase-login / link-social-account | Firebase / provider issues | `token` |
| unlink-social-account | provider issues | `provider` |

State / config errors (account inactive, unauthorized, social_auth_requires_email, etc.) return plain top-level `message` with `errors: null`.

## Where to Find Endpoints

- **API Reference → Auth Endpoints** — public auth-related endpoints (register, login, firebase-login, check-identifier, forgot-password flow).
- **API Reference → User Endpoints** — authed endpoints (logout, send-otp, verify-otp, update-profile, change-password, identifier-change, social accounts, etc.).
- **API Reference → Public Endpoints** — unrelated public APIs (translations, languages, pages).
- **Authentication → Social Authentication** — Firebase social login concepts.
- **Authentication → OTP Verification** — OTP types and flows in detail.
- **Authentication → Rate Limiting** — limiter config and applied middleware.
MD
        ];
    }

    private function getSocialAuthContent(): array
    {
        return [
            'title' => 'Social Authentication',
            'content' => <<<'MD'
# Social Authentication

Firebase-based social login for mobile apps. Endpoints, payloads, and error formats live in **API Reference → Auth Endpoints / User Endpoints**. This page documents the **concepts**.

## Requirements

- `AUTH_IDENTIFIERS` must include `email` (Firebase auth is keyed off the email claim).
- Firebase project credentials at `storage/app/private/firebase-auth.json` (or `FIREBASE_CREDENTIALS` path).
- Providers enabled in Firebase console.

## Configuration

```env
SOCIAL_AUTH_PROVIDERS=google.com,apple.com,facebook.com
SOCIAL_AUTH_MAX_ACCOUNTS=0     # 0 = unlimited; 1 = single linked provider; etc.
```

## Available Providers

- `google.com` — Google Sign-In
- `apple.com` — Apple Sign-In
- `facebook.com` — Facebook Login
- `twitter.com` — Twitter Login
- `github.com` — GitHub Login

## Endpoints (Quick Reference)

| Endpoint | Body | Auth |
|----------|------|------|
| `POST /api/firebase-login` | `token` (Firebase ID token), optional `fcm_token` | public |
| `GET /api/social-accounts` | — | Bearer + verified |
| `POST /api/link-social-account` | `token` | Bearer + verified |
| `DELETE /api/unlink-social-account` | `provider` (e.g. `google.com`) | Bearer + verified |

Field-keyed errors use `errors.token` for Firebase / provider issues and `errors.provider` for unlink issues.

## Behavior Rules

1. New users via social auth are auto-verified
2. Existing users with password must use password login
3. Cannot link same provider twice
4. Cannot unlink last social account without password
MD
        ];
    }

    private function getOtpVerificationContent(): array
    {
        return [
            'title' => 'OTP Verification',
            'content' => <<<'MD'
# OTP Verification

## OTP Types

The system uses **three OTP types**, all stored in the `otps` table with a `type` column:

| Type | Purpose | Sender | Verifier |
|------|---------|--------|----------|
| `verify` | Activate a newly-registered or unverified account | `POST /api/send-otp` (auth + on register) | `POST /api/verify-otp` |
| `reset_password` | Reset a forgotten password | `POST /api/forgot-password` (public) | `POST /api/verify-forgot-password-otp` + `POST /api/change-forgot-password` |
| `change_identifier` | Confirm an email/phone identifier change for an authenticated user | `POST /api/request-identifier-change` (auth + verified) | `POST /api/verify-identifier-change` |

## OTP Delivery

For `verify` and `reset_password` types — channel priority `email > phone` from the user's populated columns:

1. **Email** — `EmailHelper::send`
2. **Phone** — SMS (`SendSMS::send`) by default; WhatsApp (`SendWhatsapp::send`) when `IS_OTP_WHATSAPP=true`

For `change_identifier` — the channel is determined by the kind of `new_identifier` (email format → email; phone format → phone). Email values are lowercased before storage and delivery.

`AUTH_IDENTIFIERS` is always email and/or phone, so OTP-required flows always have a channel. Username is never an identifier; it is a separate field (when `HAS_USERNAME_FIELD=true`) used as a login alias only, never an OTP destination.

## Account Verification Flow (`verify` type)

1. User registers with email/phone identifier → unverified account is created and a `verify` OTP is sent.
2. User calls `POST /api/login` → receives a Bearer token. If unverified, login also re-issues a fresh `verify` OTP.
3. User calls `POST /api/verify-otp` with the OTP and the Bearer token to mark the account as verified.
4. (Optional) If the OTP expired, the user can call `POST /api/send-otp` (auth required) to receive a new one.

```http
POST /api/send-otp
Authorization: Bearer {token}
```

```http
POST /api/verify-otp
Authorization: Bearer {token}

{
    "otp": "123456"
}
```

## Password Reset Flow (`reset_password` type)

1. `POST /api/forgot-password` with `identifier` → OTP sent.
2. `POST /api/verify-forgot-password-otp` with `identifier` + `otp` → OTP confirmed.
3. `POST /api/change-forgot-password` with `identifier` + `otp` + new `password` → password updated, all existing tokens revoked.

```http
POST /api/forgot-password

{
    "identifier": "john@example.com"
}
```

```http
POST /api/verify-forgot-password-otp

{
    "identifier": "john@example.com",
    "otp": "123456"
}
```

```http
POST /api/change-forgot-password

{
    "identifier": "john@example.com",
    "otp": "123456",
    "password": "newpassword123",
    "password_confirmation": "newpassword123"
}
```

## Rate Limiting

| Endpoint | Limiter | Default |
|----------|---------|---------|
| `POST /api/send-otp` | `throttle:otp` | 3 / 5 min |
| `POST /api/forgot-password` | `throttle:otp` | 3 / 5 min |
| `POST /api/request-identifier-change` | `throttle:otp` | 3 / 5 min |
| `POST /api/verify-otp` | `throttle:api` | 60 / 1 min |
| `POST /api/verify-forgot-password-otp` | `throttle:api` | 60 / 1 min |
| `POST /api/change-forgot-password` | `throttle:api` | 60 / 1 min |
| `POST /api/verify-identifier-change` | `throttle:api` | 60 / 1 min |

Only **OTP-sending** endpoints are throttled aggressively (to prevent SMS/email spam). Verification endpoints use the standard API limit so users can retry codes freely.

## Testing Mode

When `IS_TESTING=true`, the OTP is included in the API response (top-level of `data`) on every send:

```json
{
    "success": true,
    "message": "OTP sent",
    "errors": null,
    "data": {
        "otp_expires_in_minutes": 5,
        "otp": "123456"
    }
}
```

Disable in production by setting `IS_TESTING=false`.
MD
        ];
    }

    private function getRateLimitingContent(): array
    {
        return [
            'title' => 'Rate Limiting',
            'content' => <<<'MD'
# API Rate Limiting

The API uses three separate rate limiters to protect against abuse while allowing legitimate usage.

## Rate Limiters

| Limiter | Purpose | Default | Middleware |
|---------|---------|---------|------------|
| `api` | General API endpoints + OTP **verification** | 60 req/1 min | `throttle:api` |
| `auth` | Login/register attempts | 5 req/1 min | `throttle:auth` |
| `otp` | OTP **sending** only (`send-otp`, `forgot-password`) | 3 req/5 min | `throttle:otp` |

**Important:** `throttle:otp` is applied **only to OTP-sending endpoints** (`send-otp` and `forgot-password`) to protect against SMS/email spam. OTP **verification** endpoints (`verify-otp`, `verify-forgot-password-otp`, `change-forgot-password`) use the standard `throttle:api` so users can retry codes without getting locked out.

## Configuration

Rate limits can be configured via environment variables:

```env
# General API (translations, pages, etc.)
RATE_LIMIT_API=60
RATE_LIMIT_API_DECAY=1

# Authentication (login, register)
RATE_LIMIT_AUTH=5
RATE_LIMIT_AUTH_DECAY=1

# OTP requests (strictest)
RATE_LIMIT_OTP=3
RATE_LIMIT_OTP_DECAY=5
```

Or via the DevSettings CMS under **Validation Settings > API Rate Limiting**.

## Implementation

Rate limiters are defined in `AppServiceProvider::configureRateLimiting()`:

```php
RateLimiter::for('api', function (Request $request) {
    $limit = (int) env('RATE_LIMIT_API', 60);
    $decayMinutes = (int) env('RATE_LIMIT_API_DECAY', 1);

    return Limit::perMinutes($decayMinutes, $limit)
        ->by($request->user()?->id ?: $request->ip())
        ->response(function (Request $request, array $headers) {
            return response()->json([
                'success' => false,
                'message' => Trans::get('api.too_many_requests'),
            ], 429, $headers);
        });
});
```

## Applying Rate Limits

Use the `throttle` middleware in routes:

```php
// Auth routes (stricter)
Route::middleware('throttle:auth')->group(function () {
    Route::post('/register', [AppUserController::class, 'register']);
    Route::post('/login', [AppUserController::class, 'login']);
});

// OTP-sending only (strictest)
Route::middleware('throttle:otp')->group(function () {
    Route::post('/forgot-password', [AppUserController::class, 'forgotPassword']);
});

// Authenticated send-otp also uses throttle:otp
Route::middleware(['auth:sanctum'])->group(function () {
    Route::post('/send-otp', [AppUserController::class, 'sendOtp'])
        ->middleware('throttle:otp');
});

// OTP verification + everything else uses standard throttle:api
Route::middleware('throttle:api')->group(function () {
    Route::post('/verify-forgot-password-otp', [AppUserController::class, 'verifyForgotPasswordOtp']);
    Route::post('/change-forgot-password', [AppUserController::class, 'changeForgotPassword']);
    Route::get('/translations', [TranslationController::class, 'index']);
});
```

## Rate Limit Headers

When a rate limit is applied, the response includes headers:

- `X-RateLimit-Limit` - Maximum requests allowed
- `X-RateLimit-Remaining` - Requests remaining
- `Retry-After` - Seconds until reset (when exceeded)

## Exceeded Response

When rate limit is exceeded, HTTP 429 is returned:

```json
{
    "success": false,
    "message": "Too many requests. Please try again later."
}
```

The message is translated based on the request's `Accept-Language` header.
MD
        ];
    }

    private function getFeaturesContent(): array
    {
        return $this->getUsersRolesContent();
    }

    private function getUsersRolesContent(): array
    {
        return [
            'title' => 'Users & Roles',
            'content' => <<<'MD'
# Users & Roles

## User Management

Admin users are managed via the Users page:

- Create/Edit/Delete users
- Assign roles
- Toggle active status
- Bulk actions (activate, deactivate, delete)

## Role Management

Roles are managed via the Roles page:

- Create custom roles
- Assign permissions to roles
- Protected roles cannot be deleted

### Protected Roles

| Role | Purpose |
|------|---------|
| `super_admin` | Full access to all features |
| `fallback` | Reassignment target when deleting other roles |

## Permissions

Permissions are feature-based:

- `users` - User management
- `roles` - Role management
- `translations` - Translation management
- `activity_logs` - Activity log viewing
- `pages` - Page management
- `app_users` - App user management

### Adding a New Permission

```php
// database/seeders/RoleSeeder.php

$perm = Permission::firstOrCreate([
    'name' => 'my_feature',
    'guard_name' => 'web',
]);
$perm->assignRole($super_admin);
```

Run: `php artisan db:seed --class=RoleSeeder`
MD
        ];
    }

    private function getTranslationsContent(): array
    {
        return [
            'title' => 'Translations',
            'content' => <<<'MD'
# Translations

This project uses **three translation systems** for different purposes.

## Enabling/Disabling App Translations

The app translations feature can be toggled via environment variable or DevSettings:

```env
HAS_TRANSLATIONS=true   # Enable app translations (default)
HAS_TRANSLATIONS=false  # Disable app translations
```

When disabled:
- Admin panel hides Translations and Languages pages
- Navbar hides Translations and Languages links (Pages and Activity Logs still visible)
- API endpoints `/api/translations` and `/api/languages` return 404

Configure in DevSettings → Environment → "App Translations" toggle.

## Decision Tree: Which System to Use?

**Where is the string displayed?**

1. **Frontend (Vue components)** → Use `t('key')` with JSON files
2. **Admin Backend (flash messages, notifications)** → Use `__('admin.key')` with PHP files
3. **API Responses (mobile app)** → Use `Trans::get('api.key')` with database

## Why Three Systems?

| System | Purpose | Editable by Admin? | Cache |
|--------|---------|-------------------|-------|
| Vue i18n | Frontend UI labels | No (requires deploy) | Build-time |
| PHP Lang | Admin panel messages | No (requires deploy) | Runtime |
| Database | API responses | **Yes (via CMS)** | 1 hour |

**Key insight:** API translations are database-driven so admins can customize mobile app messages without deploying code.

## 1. Frontend (Vue)

For buttons, labels, headings in Vue components.

**Location:** `resources/js/locales/en.json` + `ar.json`

```vue
<template>
    <Button>{{ t('save_changes') }}</Button>
</template>

<script setup>
import { useI18n } from 'vue-i18n';
const { t } = useI18n();
</script>
```

**Adding translations:**
```json
// en.json
{ "save_changes": "Save Changes" }

// ar.json
{ "save_changes": "حفظ التغييرات" }
```

## 2. Backend (Admin PHP)

For flash messages, validation, admin notifications.

**Location:** `lang/en/admin.php` + `lang/ar/admin.php`

```php
return back()->with('success', __('admin.updated_successfully'));
```

**Adding translations:**
```php
// lang/en/admin.php
'my_message' => 'Operation completed.',

// lang/ar/admin.php
'my_message' => 'تمت العملية.',
```

## 3. API (Database-driven)

For mobile app response messages. **Admins can edit these from the CMS.**

**Storage:** `translation_keys` + `translation_values` tables

```php
use App\Helpers\Trans;

return ApiResponse::success($data, Trans::get('api.login_successful'));
return ApiResponse::error(Trans::get('api.user_not_found'), null, 404);
```

**Adding via Seeder (developers):**
```php
// database/seeders/TranslationSeeder.php
'my_message' => [
    'en' => 'Success!',
    'ar' => 'نجاح!',
],
```
Run: `php artisan db:seed --class=TranslationSeeder`

**Adding via CMS (admins):**
1. Go to Translations page
2. Click Create Translation
3. Set group to `api`, enter key
4. Enter values for each language

## How Trans Helper Works

- Checks database first (1-hour cache)
- Falls back to default language if locale not found
- Falls back to file-based translation if not in database
- Cache clears automatically when updated in CMS
MD
        ];
    }

    private function getActivityLogsContent(): array
    {
        return [
            'title' => 'Activity Logs',
            'content' => <<<'MD'
# Activity Logs

## Overview

Activity logs track all create/update/delete actions on models.

## Using LogsActivity Trait

```php
use App\Traits\LogsActivity;

class Product extends Model
{
    use LogsActivity;

    // Customize which events to log (optional)
    protected static array $logEvents = ['created', 'updated', 'deleted'];

    // Fields to ignore in logs (optional)
    protected static array $logIgnoreFields = ['password', 'remember_token'];
}
```

## What's Logged

- **Action:** created, updated, deleted
- **Causer:** User who performed the action
- **Target:** Model class and ID
- **Old Values:** Previous state (for updates)
- **New Values:** New state
- **IP Address & User Agent**
- **Timestamp**

## Viewing Logs

Access via Activity Logs page in admin panel.

Features:
- Filter by action, target type, user
- View detailed changes
- Bulk delete old logs

## Log Entry Structure

```php
[
    'action' => 'updated',
    'model_type' => 'App\\Models\\User',
    'model_id' => 1,
    'causer_id' => 2,
    'causer_type' => 'App\\Models\\User',
    'old_values' => ['name' => 'Old Name'],
    'new_values' => ['name' => 'New Name'],
    'ip_address' => '127.0.0.1',
    'user_agent' => 'Mozilla/5.0...',
]
```
MD
        ];
    }

    private function getPagesContent(): array
    {
        return [
            'title' => 'Pages',
            'content' => <<<'MD'
# Pages (CMS)

## Overview

Manage static pages like Terms of Service, Privacy Policy, etc.

## Features

- Create/Edit/Delete pages
- Translatable title and content
- Rich text editor for content
- Custom slugs
- Active/Inactive status

## API Endpoint

```http
GET /api/pages
GET /api/pages/{slug}
```

Response:
```json
{
    "success": true,
    "data": {
        "id": 1,
        "slug": "terms",
        "title_api": "Terms of Service",
        "content_api": "<p>...</p>",
        "is_active": true
    }
}
```

## Using HasTranslations

Pages use the `HasTranslations` trait for multilingual support:

```php
class Page extends Model
{
    use HasTranslations;

    protected $translatable = ['title', 'content'];
}
```

Access translations:
- `$page->title_api` - Returns title in current locale
- `$page->getTranslation('title', 'ar')` - Get specific translation
MD
        ];
    }

    private function getSoftDeletesContent(): array
    {
        return [
            'title' => 'Soft Deletes',
            'content' => <<<'MD'
# Soft Deletes

## Enabling Soft Deletes

### 1. Add to Model

```php
use Illuminate\Database\Eloquent\SoftDeletes;

class Product extends Model
{
    use SoftDeletes;
}
```

### 2. Add Migration

```php
$table->softDeletes();
```

### 3. Add HasSoftDeleteActions to Controller

```php
use App\Traits\HasSoftDeleteActions;

class ProductController extends Controller
{
    use HasSoftDeleteActions;

    protected string $model = Product::class;
}
```

### 4. Add Routes

```php
Route::prefix('products')->group(function () {
    // Bulk routes BEFORE parameterized routes
    Route::post('/bulk-restore', [ProductController::class, 'bulkRestore']);
    Route::post('/bulk-force-delete', [ProductController::class, 'bulkForceDelete']);

    // Single item routes
    Route::post('/{product}/restore', [ProductController::class, 'restore'])->withTrashed();
    Route::delete('/{product}/force-delete', [ProductController::class, 'forceDelete'])->withTrashed();
});
```

### 5. Frontend Integration

Use `TrashedFilter` component and pass `hasSoftDeletes: true` to the page.

## Available Components

- `TrashedFilter` - Dropdown to filter active/trashed items
- `RestoreModal` - Confirmation for single restore
- `BulkRestoreModal` - Confirmation for bulk restore
- `BulkActions` - Supports restore/forceDelete actions
MD
        ];
    }

    private function getApiOverviewContent(): array
    {
        return [
            'title' => 'API Overview',
            'content' => <<<'MD'
# API Overview

## Base URL

Development: `http://localhost:8000/api`
Production: `https://your-domain.com/api`

## Required Headers

All requests:
```http
X-API-TOKEN: your-token
Accept: application/json
Content-Type: application/json
```

Protected routes add:
```http
Authorization: Bearer {user-token}
```

## Response Format

### Success Response
```json
{
    "success": true,
    "message": "Operation successful",
    "data": { ... }
}
```

### Error Response
```json
{
    "success": false,
    "message": "Error message",
    "errors": {
        "field": ["Error detail"]
    }
}
```

## User Serialization

In **API responses only**, the `User` model strips identifier-like fields (`email`, `phone`, `username`) when they are NOT configured — i.e., neither listed in `AUTH_IDENTIFIERS` nor enabled via `HAS_*_FIELD`. The mobile app only sees fields it can actually use.

Examples:
- `AUTH_IDENTIFIERS=email`, all `HAS_*_FIELD=false` → response contains `email` only (no `phone`, no `username`).
- `AUTH_IDENTIFIERS=username`, `HAS_PHONE_FIELD=true` → response contains `username` and `phone` (no `email`).
- `AUTH_IDENTIFIERS=email,phone,username` → all three appear.

Admin/web responses (anything not under `api/*`) keep every column so the admin panel can manage all fields.

## Rate Limiting

- Default: 60 requests per minute
- Auth endpoints: 5 requests per minute

## Versioning

Currently v1 (no prefix). Future versions may use `/api/v2/...`

## Postman Collection

Download from DevSettings → "Postman Collection"
MD
        ];
    }

    private function getApiAuthEndpointsContent(): array
    {
        return [
            'title' => 'Auth Endpoints',
            'content' => <<<'MD'
# Auth Endpoints

## OTP Types

The system uses **two OTP types**, stored in the same `otps` table but with different purposes:

| Type | Purpose | Sent by | Verified by |
|------|---------|---------|-------------|
| `verify` | Activate a newly-registered (or unverified) account | `POST /api/send-otp` (auth required) | `POST /api/verify-otp` |
| `reset_password` | Reset a forgotten password | `POST /api/forgot-password` (public) | `POST /api/verify-forgot-password-otp` + `POST /api/change-forgot-password` |

**Rate limits:** only the **sending** endpoints (`send-otp` and `forgot-password`) use the strict `throttle:otp` limiter (default 3 req / 5 min). Verification endpoints use the normal `throttle:api` so users can retry codes freely.

## Public Endpoints

### Register
```http
POST /api/register

{
    "policy_agreed": true,
    "name": "John Doe",
    "identifier": "user@example.com",
    "username": "jdoe",
    "password": "password123",
    "password_confirmation": "password123",
    "fcm_token": "optional"
}
```
Creates a user and sends a verify OTP. Does **not** issue a token.

- `identifier` is always email or phone (kind auto-detected, must match `AUTH_IDENTIFIERS`). Email values are normalized to lowercase before storage and lookup.
- `username` is **required when `HAS_USERNAME_FIELD=true`** — separate field, not an identifier, but the user can also log in with it. Format: must start with a letter, then letters/digits/`_`/`-`. Min length 3. Email/phone-shaped values are rejected.
- `username` uniqueness is scoped to api-guard users only (admin users with the same column value do not conflict).

### Login
```http
POST /api/login

{
    "identifier": "user@example.com",
    "password": "password123",
    "fcm_token": "optional",
    "remember_me": true
}
```
Returns a Bearer `token` (top level + inside `data`). The `identifier` value is searched across configured email/phone identifiers and (when `HAS_USERNAME_FIELD=true`) the `username` column too — so users can log in with any of those values. On unverified accounts, also triggers a new verify OTP.

### Firebase Login
```http
POST /api/firebase-login

{
    "token": "firebase-id-token",
    "fcm_token": "optional"
}
```
Social auth (Google/Apple/etc.) via Firebase ID token. Auto-verified, returns a token.

### Auth Config
```http
GET /api/auth-config
```
Returns the current auth configuration so the client can adapt its UI (which inputs to render, which channels are available, etc.). No body, no token, public.

Response:
```json
{
    "success": true,
    "data": {
        "identifiers": ["email", "phone"],
        "has_username_field": true,
        "has_email_field": false,
        "has_phone_field": false,
        "social_providers": ["google.com", "apple.com"],
        "max_social_accounts": 0,
        "social_auth_available": true,
        "is_otp_whatsapp": false
    }
}
```

Fetch this on app boot. Use it to:
- Build the register form (which fields are required vs optional vs hidden).
- Label the single `identifier` input on register/login/forgot-password.
- Decide whether to show username/social-login UI.
- Decide whether the OTP body says "We sent a code to your email/phone".

### Check Identifier
```http
POST /api/check-identifier

{
    "identifier": "user@example.com"
}
```
Detects kind (email/phone/username) from the value and queries the matching column when configured (identifier OR `HAS_*_FIELD` extra). Scoped to api-guard users.

Response:
```json
{
    "success": true,
    "data": {
        "exists": true,
        "available_channels": ["email", "phone"]
    }
}
```

`available_channels` lists which OTP delivery destinations are populated on the user record (`"email"`, `"phone"`, both, or neither). Used by the client to:
- Decide which `type` to pass to `forgot-password`.
- Pre-submit uniqueness checks before `register`, `update-profile` (username change), or `request-identifier-change`.

When the user is not found: `{ "exists": false, "available_channels": [] }`.

### Forgot Password
```http
POST /api/forgot-password

{
    "identifier": "user@example.com",
    "type": "email"      // optional — "email" or "phone"
}
```
Sends a reset-password OTP. Channel selection:
- `type` omitted → priority `email > phone` from the user's populated columns.
- `type` provided → must be one of the user's populated channels (use `check-identifier` first to discover `available_channels`).

Response includes the actual `channel` used so the client knows where to look. Rate-limited via `throttle:otp`.

**Errors:**
- 422 `errors.identifier` — user not found OR neither email nor phone populated on the user record.
- 422 `errors.type` — requested `type` is not populated for this user.

### Verify Forgot Password OTP
```http
POST /api/verify-forgot-password-otp

{
    "identifier": "user@example.com",
    "otp": "123456"
}
```

### Change Forgot Password
```http
POST /api/change-forgot-password

{
    "identifier": "user@example.com",
    "otp": "123456",
    "password": "newpassword",
    "password_confirmation": "newpassword"
}
```

## Protected Auth Endpoints

See **User Endpoints** section for the full list of authenticated endpoints (`logout`, `send-otp`, `verify-otp`, `change-password`, `update-profile`, `request-identifier-change`, `verify-identifier-change`, `delete-account`, `social-accounts`, `link-social-account`, `unlink-social-account`, `user`).
MD
        ];
    }

    private function getApiUserEndpointsContent(): array
    {
        return [
            'title' => 'User Endpoints',
            'content' => <<<'MD'
# User Endpoints

All endpoints below require `Authorization: Bearer {token}` and the `user` API role.

## Account

### Current User
```http
GET /api/user
```
Returns the authenticated user record.

### Logout
```http
POST /api/logout
```
Revokes the current Bearer token.

### Delete Account
```http
DELETE /api/delete-account
```
Requires verified account. **Permanently** deletes the user record (bypasses `SoftDeletes` via `forceDelete()`) and revokes all tokens. **Cannot be restored** from the admin trash filter — the row is removed from the database. Use this for GDPR / account-deletion compliance.

## Profile

### Update Profile
```http
PUT /api/update-profile

{
    "name": "John Doe",
    "phone": "+1234567890",
    "username": "jdoe"
}
```
Requires verified account. Field rules:

- **`name`** — always editable.
- **`username`** — always editable when `HAS_USERNAME_FIELD=true` (username is never an identifier). Format: must start with a letter, then letters/digits/`_`/`-`. Min length 3. Email/phone-shaped values rejected.
- **`email` / `phone`** — editable here **only when they are NOT identifiers**, i.e., enabled as `HAS_EMAIL_FIELD` / `HAS_PHONE_FIELD` extras. Email values are lowercased on save. When email/phone is an identifier, use `request-identifier-change` (OTP-protected).
- Empty strings are ignored (not written to DB).

## Password

### Change Password
```http
POST /api/change-password

{
    "old_password": "current_password",
    "password": "new_password",
    "password_confirmation": "new_password"
}
```
Requires verified account. Wrong `old_password` returns `errors.old_password` (422). Revokes all other tokens, keeps the current one.

## Identifier Change Flow

Used for changing the user's **email or phone identifier** — both are always OTP-protected. Username is not an identifier and is changed via `update-profile` directly.

### Request Identifier Change
```http
POST /api/request-identifier-change

{
    "new_identifier": "newemail@example.com"
}
```
Requires verified account. The kind of `new_identifier` (email / phone) is auto-detected and must match one of the configured identifiers in `AUTH_IDENTIFIERS`. Sends an OTP via the matching channel (email → `EmailHelper`; phone → SMS, or WhatsApp when `IS_OTP_WHATSAPP=true`). Email values are lowercased before storage. Rate-limited via `throttle:otp`.

Errors:
- 422 `errors.new_identifier` — invalid format, not a configured identifier, or fails uniqueness/format rules.

### Verify Identifier Change
```http
POST /api/verify-identifier-change

{
    "new_identifier": "newemail@example.com",
    "otp": "123456"
}
```
Confirms the OTP and updates the user's email or phone column. Invalid OTP returns `errors.otp` (422).

## OTP (Account Verification)

### Send OTP
```http
POST /api/send-otp
```
No body. Sends a fresh `verify` OTP via the user's primary identifier channel (email/phone). Rate-limited via `throttle:otp`.

### Verify OTP
```http
POST /api/verify-otp

{
    "otp": "123456"
}
```
Marks the authenticated user as verified. Required before any `verified` route. Invalid OTP returns `errors.otp` (422).

## Social Accounts

All require verified account.

### List Linked Accounts
```http
GET /api/social-accounts
```

### Link Social Account
```http
POST /api/link-social-account

{
    "token": "firebase-id-token"
}
```
Links a social provider to the current user. Constraint failures return `errors.token` (422): invalid token, provider not allowed, account already linked elsewhere, provider already linked to this user, email mismatch, max accounts reached.

### Unlink Social Account
```http
DELETE /api/unlink-social-account

{
    "provider": "google.com"
}
```
Unlinks a provider. Returns `errors.provider` (422) if the provider is not linked or if it is the only login method (no password and no other social accounts).
MD
        ];
    }

    private function getApiPublicEndpointsContent(): array
    {
        return [
            'title' => 'Public Endpoints',
            'content' => <<<'MD'
# Public Endpoints

These endpoints require only the `X-API-TOKEN` header (no Bearer token). All use `throttle:api` rate limiting.

## Translations

Available only when `HAS_TRANSLATIONS=true`. Translations are split into **groups**:

- `api` — backend API response messages (seeded by `TranslationSeeder`, also editable in the CMS). **Server-controlled.** Cannot be created/modified via API.
- `app` — strings the mobile app contributes / consumes.
- `web` — strings the web frontend contributes / consumes.

The API endpoints below operate on `app` and `web` only. Pick the group via the `group` parameter (default `app`).

### Fetching Translations

```http
GET /api/translations?group=app
Accept-Language: en
```

Returns all keys for the chosen group in the **current locale** (`Accept-Language`). `group` is optional and accepts `app` or `web` (default `app`).

Response:
```json
{
    "success": true,
    "data": {
        "group": "app",
        "locale": "en",
        "translations": {
            "welcome_screen_title": "Welcome",
            "tap_to_continue": "Tap to continue",
            ...
        }
    }
}
```

The client fetches this on boot and on language switch, then merges into its i18n store.

### Adding Translations

```http
POST /api/translations
Accept-Language: en

{
    "group": "app",
    "translations": {
        "welcome_screen_title": "Welcome",
        "tap_to_continue": "Tap to continue"
    }
}
```

- `group` (optional, default `app`): `app` or `web`.
- `translations` (required): flat object of `key → value` pairs.

Behavior:
- New key → created in the chosen group. Header's locale gets the value; **all other active locales are seeded as `null`** (admin can fill them later in the CMS).
- Existing key → only the header's locale value is updated. Other locales are untouched.
- `api`-group keys are server-controlled and cannot be created/modified here.

Re-call with `Accept-Language: ar` and the same key/group to populate the Arabic value:
```http
POST /api/translations
Accept-Language: ar

{
    "group": "app",
    "translations": {
        "welcome_screen_title": "مرحباً"
    }
}
```

Response:
```json
{
    "success": true,
    "data": {
        "group": "app",
        "locale": "en",
        "created": 1,
        "updated": 1
    }
}
```

This lets app/web developers add new strings without redeploying the backend. Admins can later refine the values in the CMS.

### Adding Translations from a Seeder (developers)

For server-controlled `api` keys (anything used by `Trans::get('api.*')`), add to `database/seeders/TranslationSeeder.php`:

```php
'my_new_message' => [
    'en' => 'Welcome, :name!',
    'ar' => 'مرحباً، :name!',
],
```

Then re-run:
```bash
php artisan db:seed --class=TranslationSeeder
```

The seeder uses `firstOrCreate`, so existing keys are not overwritten.

### Placeholders (`:name`, `:count`, …)

Translation values may contain Laravel-style placeholders like `:name` or `:count`. These are filled in at render time via `Trans::get('api.welcome', ['name' => $user->name])`.

**CMS rule:** the admin Translations page rejects any save where a locale value is missing a placeholder that exists in the default-language value. The edit modal also shows a "Required placeholders" badge listing the tokens that must remain. Admins can type freely around the placeholder, but they cannot delete or rename it. This protects runtime substitutions from breaking when content editors localize strings.

### List Languages

```http
GET /api/languages
```

Returns active languages with `code`, `name`, `native_name`, `direction` (`ltr`/`rtl`), and `is_default`.

## Pages (CMS)

Static content pages managed via the admin panel.

### List Active Pages
```http
GET /api/pages
```
Returns all active pages with translated `title` and `content`.

### Get Page by Slug
```http
GET /api/pages/{slug}
```
Returns a single page by slug (e.g. `terms`, `privacy`). Returns 404 if the page doesn't exist or is inactive.

## Authentication-Adjacent

These are also public but documented in **Auth Endpoints**:
- `POST /api/register`
- `POST /api/login`
- `POST /api/firebase-login`
- `POST /api/check-identifier`
- `POST /api/forgot-password`
- `POST /api/verify-forgot-password-otp`
- `POST /api/change-forgot-password`
MD
        ];
    }

    private function getApiResponsesContent(): array
    {
        return [
            'title' => 'Response Format',
            'content' => <<<'MD'
# Response Format

## ApiResponse Helper

```php
use App\Helpers\ApiResponse;

// Success
return ApiResponse::success($data, 'Message');

// Error
return ApiResponse::error('Error message', $errors, 422);

// Success with Bearer token (auth endpoints)
return ApiResponse::success($data, 'Logged in', $token);
```

## Token Responses

Endpoints that issue a Bearer token (`login`, `firebase-login`) return the token at the **top level** of the response AND duplicated inside `data.token`:

```json
{
    "success": true,
    "message": "Login successful",
    "errors": null,
    "token": "1|abc123...",
    "data": {
        "user": { ... },
        "token": "1|abc123..."
    }
}
```

`register` does **not** return a token — call `login` afterwards.

## HTTP Status Codes

| Code | Meaning |
|------|---------|
| 200 | Success |
| 201 | Created |
| 400 | Bad Request |
| 401 | Unauthorized |
| 403 | Forbidden |
| 404 | Not Found |
| 422 | Validation Error |
| 429 | Too Many Requests |
| 500 | Server Error |

## Pagination

```json
{
    "success": true,
    "data": {
        "data": [...],
        "current_page": 1,
        "last_page": 5,
        "per_page": 15,
        "total": 75
    }
}
```

## Field-Keyed Errors (Frontend Convention)

Any error tied to a specific request input field is returned as HTTP **422** with the error keyed under the **same name as the input parameter**. The frontend can look up `errors.{paramName}` to show inline messages next to the matching input.

```json
{
    "success": false,
    "message": "Invalid OTP.",
    "errors": {
        "otp": ["Invalid OTP."]
    },
    "data": null
}
```

Examples:

| Endpoint | Trigger | Error Key |
|----------|---------|-----------|
| `login` | invalid credentials | `password` |
| `login` / `forgot-password` / `verify-forgot-password-otp` / `change-forgot-password` / `register` | identifier validation / user not found | `identifier` |
| `verify-otp` / `verify-forgot-password-otp` / `change-forgot-password` / `verify-identifier-change` | invalid OTP | `otp` |
| `change-password` | wrong current password | `old_password` |
| `request-identifier-change` / `verify-identifier-change` | invalid format / wrong kind / unique-fail | `new_identifier` |
| `firebase-login` / `link-social-account` | invalid token, provider issues, email mismatch, etc. | `token` |
| `unlink-social-account` | provider not linked, cannot unlink last | `provider` |

State / config errors (account inactive, unauthorized access, feature disabled, etc.) remain plain top-level error messages with `errors: null`.

## Validation Errors

```json
{
    "success": false,
    "message": "The given data was invalid.",
    "errors": {
        "email": ["The email has already been taken."],
        "password": ["The password must be at least 8 characters."]
    }
}
```
MD
        ];
    }

    private function getTraitsContent(): array
    {
        return $this->getHasImageContent();
    }

    private function getHasImageContent(): array
    {
        return [
            'title' => 'HasImage Trait',
            'content' => <<<'MD'
# HasImage Trait

Provides image upload functionality with automatic blurhash generation.

## Usage

```php
use App\Traits\HasImage;

class Product extends Model
{
    use HasImage;
}
```

## Methods

### Save Image
```php
$product->saveImage($request->file('image'), 'products');
```

- Deletes existing image if present
- Stores new image in `storage/app/public/{folder}`
- Creates `images` record with blurhash
- Returns the Image model

### Delete Image
```php
$product->deleteImage();
```

- Removes file from storage
- Deletes `images` record

### Get Image URL
```php
$product->image->image_api  // Full public URL
```

## Database Structure

The `images` table:
```php
Schema::create('images', function (Blueprint $table) {
    $table->id();
    $table->morphs('imageable');
    $table->string('url');
    $table->string('type')->nullable();
    $table->string('blurhash')->nullable();
    $table->timestamps();
});
```

## Blurhash

Blurhash is automatically generated for placeholder previews. Use it in Vue:
```vue
<img :src="product.image?.image_api" :style="{ backgroundImage: `url(${blurhashToDataUrl(product.image?.blurhash)})` }" />
```
MD
        ];
    }

    private function getHasVideoContent(): array
    {
        return [
            'title' => 'HasVideo Trait',
            'content' => <<<'MD'
# HasVideo Trait

Provides video upload functionality with optional thumbnail support.

## Usage

```php
use App\Traits\HasVideo;

class Course extends Model
{
    use HasVideo;
}
```

## Methods

### Save Video
```php
// Without thumbnail
$course->saveVideo($request->file('video'), 'courses');

// With thumbnail
$course->saveVideo(
    $request->file('video'),
    'courses',
    $request->file('thumbnail')
);
```

### Delete Video
```php
$course->deleteVideo();
```

- Removes video file
- Removes thumbnail image (if exists)
- Deletes `videos` record

### Access Video
```php
$course->video->video_api     // Full video URL
$course->video->image->image_api  // Thumbnail URL (if exists)
```

## Database Structure

```php
Schema::create('videos', function (Blueprint $table) {
    $table->id();
    $table->morphs('videoable');
    $table->string('url');
    $table->timestamps();
});
```

The Video model uses `HasImage` trait for thumbnails.
MD
        ];
    }

    private function getHasTranslationsContent(): array
    {
        return [
            'title' => 'HasTranslations Trait',
            'content' => <<<'MD'
# HasTranslations Trait

Provides multi-language support for model attributes.

## Usage

```php
use App\Traits\HasTranslations;

class Product extends Model
{
    use HasTranslations;

    protected $translatable = ['name', 'description'];
}
```

## Methods

### Save Translations
```php
$product->saveTranslations([
    'name' => ['en' => 'Product Name', 'ar' => 'اسم المنتج'],
    'description' => ['en' => 'Description', 'ar' => 'الوصف'],
]);
```

### Get Translation
```php
// Current locale
$product->getTranslation('name');

// Specific locale
$product->getTranslation('name', 'ar');
```

### Get All Translations
```php
$product->getAllTranslations();
// Returns: ['name' => ['en' => '...', 'ar' => '...'], ...]
```

## API Attributes

The trait auto-appends `{field}_api` attributes:
```php
$product->name_api        // Returns name in current locale
$product->description_api // Returns description in current locale
```

## Validation Pattern

```php
$validated = $request->validate([
    'translations' => ['required', 'array'],
    'translations.name' => ['required', 'array'],
    'translations.name.*' => ['nullable', 'string', 'max:255'],
]);

$product->saveTranslations($validated['translations']);
```

## Frontend Component

```vue
<TranslatableInput
    v-model="form.translations.name"
    :languages="languages"
    :label="t('name')"
    :required="true"
/>
```
MD
        ];
    }

    private function getLogsActivityContent(): array
    {
        return [
            'title' => 'LogsActivity Trait',
            'content' => <<<'MD'
# LogsActivity Trait

Automatically logs model changes for auditing.

## Usage

```php
use App\Traits\LogsActivity;

class Product extends Model
{
    use LogsActivity;
}
```

## Customization

### Control Which Events to Log

```php
protected static array $logEvents = ['created', 'updated', 'deleted'];
```

### Ignore Certain Fields

```php
protected static array $logIgnoreFields = [
    'password',
    'remember_token',
    'updated_at',
];
```

## What Gets Logged

For each logged event:
- Action type (created, updated, deleted)
- Model type and ID
- User who made the change
- Old values (for updates/deletes)
- New values (for creates/updates)
- IP address
- User agent
- Timestamp

## Viewing Logs

Activity logs are viewable in the admin panel under "Activity Logs".

Features:
- Filter by action, target, user
- View detailed before/after comparison
- Bulk delete old logs

## Database Table

```sql
activity_logs (
    id,
    action,
    model_type,
    model_id,
    causer_type,
    causer_id,
    old_values (JSON),
    new_values (JSON),
    ip_address,
    user_agent,
    created_at
)
```
MD
        ];
    }

    private function getHasSoftDeleteActionsContent(): array
    {
        return [
            'title' => 'HasSoftDeleteActions Trait',
            'content' => <<<'MD'
# HasSoftDeleteActions Trait

Provides controller actions for soft-delete operations.

## Usage

```php
use App\Traits\HasSoftDeleteActions;

class ProductController extends Controller
{
    use HasSoftDeleteActions;

    protected string $model = Product::class;
}
```

## Provided Methods

### restore(Model $model)
Restores a single soft-deleted record.

### forceDelete(Model $model)
Permanently deletes a record.

### bulkRestore(Request $request)
Restores multiple records by IDs.

### bulkForceDelete(Request $request)
Permanently deletes multiple records by IDs.

## Route Setup

```php
Route::prefix('products')->group(function () {
    // Bulk routes FIRST
    Route::post('/bulk-restore', [ProductController::class, 'bulkRestore']);
    Route::post('/bulk-force-delete', [ProductController::class, 'bulkForceDelete']);

    // Single routes with withTrashed()
    Route::post('/{product}/restore', [ProductController::class, 'restore'])
        ->withTrashed();
    Route::delete('/{product}/force-delete', [ProductController::class, 'forceDelete'])
        ->withTrashed();
});
```

## Frontend Integration

Pass `hasSoftDeletes: true` to the page:

```php
return Inertia::render('Product/Index', [
    'products' => $products,
    'hasSoftDeletes' => true,
]);
```
MD
        ];
    }

    private function getNotifiesAdminContent(): array
    {
        return [
            'title' => 'NotifiesAdmin Trait',
            'content' => <<<'MD'
# NotifiesAdmin Trait

Automatically creates admin notifications when model events occur. Notifications are fully translated based on the admin's locale.

## Usage

```php
use App\Traits\NotifiesAdmin;

class Order extends Model
{
    use NotifiesAdmin;
}
```

## Customization

### Control Which Events Trigger Notifications

```php
protected static array $notifyEvents = ['created', 'deleted'];
```

### Custom Notification Type

```php
protected static string $notifyType = 'orders';
```

### Conditional Notifications

```php
protected function shouldNotify(string $event): bool
{
    return $this->status === 'pending';
}
```

## Translations

Notifications are stored with translation keys and translated on-the-fly based on the admin's current locale.

### Adding Model Name Translations

When using the trait on a new model, add translations for the model name:

**lang/en/admin.php:**
```php
'model_order' => 'Order',
'model_product' => 'Product',
```

**lang/ar/admin.php:**
```php
'model_order' => 'طلب',
'model_product' => 'منتج',
```

The key format is `model_{snake_case_class_name}`.

### How It Works

1. When a model event occurs (created/deleted), the trait stores translation keys:
   - `title_key`: e.g., `admin.notification_created`
   - `message_key`: e.g., `admin.notification_created_message`
   - `model_key`: e.g., `admin.model_order`

2. When an admin views notifications, the keys are translated using their current locale.

3. This ensures notifications appear in Arabic for Arabic users and English for English users.

### Available Translation Keys

| Key | English | Arabic |
|-----|---------|--------|
| `notification_created` | New :model | :model جديد |
| `notification_updated` | :model Updated | تم تحديث :model |
| `notification_deleted` | :model Deleted | تم حذف :model |
| `notification_created_message` | :name has been registered. | تم تسجيل :name. |
| `notification_updated_message` | :name has been updated. | تم تحديث :name. |
| `notification_deleted_message` | :name has been deleted. | تم حذف :name. |

## Frontend Component

The `NotificationBell` component in the Navbar displays notifications:

- Shows unread count badge
- Sidebar with recent notifications
- Mark as read functionality
- Fully RTL-compatible

## Database Structure

```sql
admin_notifications (
    id,
    type,           -- e.g., 'orders', 'app_users'
    title_key,      -- Translation key for title
    message_key,    -- Translation key for message
    model_key,      -- Translation key for model name
    action,         -- 'created', 'updated', 'deleted'
    notifiable_type,
    notifiable_id,
    data (JSON),    -- Additional data including 'name'
    read_at,
    created_at
)
```
MD
        ];
    }

    private function getFrontendContent(): array
    {
        return $this->getVueComponentsContent();
    }

    private function getVueComponentsContent(): array
    {
        return [
            'title' => 'Vue Components',
            'content' => <<<'MD'
# Vue Components

## Page Structure

Every feature page follows this pattern:

```
Filters → BulkActions → Create Button → Table → Modals
```

## Component Organization

```
components/
├── Shared/              # Reusable across features
│   ├── Navbar.vue
│   ├── DeleteModal.vue
│   ├── BulkDeleteModal.vue
│   ├── BulkActions.vue
│   ├── RestoreModal.vue
│   ├── TrashedFilter.vue
│   └── ...
│
├── ui/                  # UI primitives
│   ├── Button.vue
│   ├── Input.vue
│   ├── Checkbox.vue
│   ├── Table/
│   │   ├── Table.vue
│   │   ├── TableHead.vue
│   │   ├── TableBody.vue
│   │   └── TableCell.vue
│   └── ...
│
└── {feature-name}/      # Feature-specific
    ├── {Feature}Filters.vue
    ├── {Feature}Table.vue
    ├── {Feature}CreateModal.vue
    └── {Feature}EditModal.vue
```

## Layout Usage

```vue
<script setup>
import Default from '@/layouts/default.vue';

defineOptions({
    layout: Default,
});
</script>
```

## Inertia Page Props

```vue
const page = usePage();

// Available props
page.props.auth.user
page.props.auth.roles
page.props.auth.permissions
page.props.locale
page.props.success
page.props.error
```
MD
        ];
    }

    private function getUiComponentsContent(): array
    {
        return [
            'title' => 'UI Components',
            'content' => <<<'MD'
# UI Components

## Button

```vue
<Button>Default</Button>
<Button variant="outline">Outline</Button>
<Button variant="ghost">Ghost</Button>
<Button variant="destructive">Destructive</Button>
<Button size="sm">Small</Button>
<Button size="lg">Large</Button>
<Button :disabled="loading">
    <Loader2 v-if="loading" class="animate-spin" />
    Submit
</Button>
```

## Input

```vue
<Input v-model="form.name" placeholder="Enter name" />
<Input type="email" v-model="form.email" />
<Input type="password" v-model="form.password" />
```

## Checkbox

```vue
<Checkbox v-model="form.active" />
<Checkbox :checked="item.active" @update:checked="toggle" />
```

## Table Components

```vue
<Table>
    <TableHead>
        <TableRow>
            <TableHeader>Name</TableHeader>
            <TableHeader>Status</TableHeader>
        </TableRow>
    </TableHead>
    <TableBody>
        <TableRow v-for="item in items">
            <TableCell>{{ item.name }}</TableCell>
            <TableCell>{{ item.status }}</TableCell>
        </TableRow>
    </TableBody>
</Table>
```

## TranslatableInput

```vue
<TranslatableInput
    v-model="form.translations.name"
    :languages="languages"
    :label="t('name')"
    :required="true"
/>
```
MD
        ];
    }

    private function getModalsContent(): array
    {
        return [
            'title' => 'Modals',
            'content' => <<<'MD'
# Modals

**NEVER use Radix/Shadcn Dialog.** Use the custom Teleport pattern.

## Basic Modal Structure

```vue
<Teleport to="body">
    <Transition
        enter-active-class="transition duration-200 ease-out"
        enter-from-class="opacity-0"
        enter-to-class="opacity-100"
        leave-active-class="transition duration-150 ease-in"
        leave-from-class="opacity-100"
        leave-to-class="opacity-0"
    >
        <div v-if="isOpen" class="fixed inset-0 z-50 overflow-y-auto" @click.self="close">
            <!-- Backdrop -->
            <div class="absolute inset-0 bg-black/50 backdrop-blur-sm"></div>

            <!-- Modal -->
            <div class="relative flex min-h-full items-center justify-center p-4">
                <div class="relative w-full max-w-lg rounded-2xl bg-card p-6 shadow-xl">
                    <!-- Header -->
                    <div class="mb-4 flex items-center justify-between">
                        <h2 class="text-xl font-semibold">{{ title }}</h2>
                        <button @click="close">
                            <X class="size-5" />
                        </button>
                    </div>

                    <!-- Content -->
                    <slot />
                </div>
            </div>
        </div>
    </Transition>
</Teleport>
```

## Available Shared Modals

- `DeleteModal` - Single item delete confirmation
- `BulkDeleteModal` - Bulk delete confirmation
- `RestoreModal` - Single item restore confirmation
- `BulkRestoreModal` - Bulk restore confirmation
- `ForceDeleteModal` - Permanent delete confirmation
- `BulkForceDeleteModal` - Bulk permanent delete

## Usage

```vue
<DeleteModal
    :is-open="showDeleteModal"
    :processing="deleteForm.processing"
    @confirm="deleteItem"
    @cancel="showDeleteModal = false"
/>
```
MD
        ];
    }

    private function getFormsContent(): array
    {
        return [
            'title' => 'Forms',
            'content' => <<<'MD'
# Forms

## Using Inertia useForm

```vue
<script setup>
import { useForm } from '@inertiajs/vue3';

const form = useForm({
    name: '',
    email: '',
    password: '',
});

const submit = () => {
    form.post(route('users.store'), {
        preserveScroll: true,
        preserveState: true,
        reset: ['users', 'success', 'error'],
        onSuccess: () => {
            closeModal();
        },
    });
};
</script>
```

## File Uploads

```vue
const form = useForm({
    name: '',
    image: null,
});

// For POST
form.post(route('users.store'), {
    forceFormData: true,
});

// For PUT (method spoofing)
form.transform(data => ({
    ...data,
    _method: 'PUT',
})).post(route('users.update', user.id), {
    forceFormData: true,
});
```

## Validation Errors

```vue
<div>
    <Input v-model="form.email" />
    <p v-if="form.errors.email" class="text-sm text-red-500">
        {{ form.errors.email }}
    </p>
</div>
```

## Required Options

Every Inertia request should include:

```js
{
    preserveScroll: true,
    preserveState: true,
    reset: ['dataKey', 'success', 'error', 'filters'],
}
```
MD
        ];
    }

    private function getRtlSupportContent(): array
    {
        return [
            'title' => 'RTL Support',
            'content' => <<<'MD'
# RTL Support

This project supports Arabic (RTL) natively.

## Tailwind Logical Properties

**ALWAYS use logical properties:**

| Instead of | Use |
|------------|-----|
| `ml-*` | `ms-*` |
| `mr-*` | `me-*` |
| `pl-*` | `ps-*` |
| `pr-*` | `pe-*` |
| `left-*` | `start-*` |
| `right-*` | `end-*` |

## Directional Prefixes

When behavior differs by direction:

```vue
<div class="ltr:text-left rtl:text-right">
    <!-- Content -->
</div>

<Icon class="ltr:rotate-0 rtl:rotate-180" />
```

## Theme Colors

Use semantic tokens, not hardcoded colors:

```vue
<!-- Good -->
<div class="bg-primary text-primary-foreground">

<!-- Bad -->
<div class="bg-blue-500 text-white">
```

## Layout Direction

The layout automatically sets direction based on locale:

```vue
<div :dir="page.props.locale.dir">
```

## Icons

Icons that indicate direction should flip:

```vue
<ChevronRight class="rtl:rotate-180" />
<ArrowRight class="rtl:rotate-180" />
```
MD
        ];
    }

    private function getBroadcastingContent(): array
    {
        return $this->getPusherSetupContent();
    }

    private function getPusherSetupContent(): array
    {
        return [
            'title' => 'Pusher Setup',
            'content' => <<<'MD'
# Pusher Setup

## Configuration

### Environment Variables

```env
BROADCAST_CONNECTION=pusher
QUEUE_CONNECTION=sync

PUSHER_APP_ID=your-app-id
PUSHER_APP_KEY=your-app-key
PUSHER_APP_SECRET=your-app-secret
PUSHER_APP_CLUSTER=eu

VITE_PUSHER_APP_KEY="${PUSHER_APP_KEY}"
VITE_PUSHER_APP_CLUSTER="${PUSHER_APP_CLUSTER}"
```

### DevSettings

Configure Pusher credentials via DevSettings → Notifications → Pusher Broadcasting.

## Important: Use ShouldBroadcastNow

With `QUEUE_CONNECTION=sync`, always use `ShouldBroadcastNow`:

```php
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;

class MyEvent implements ShouldBroadcastNow
{
    // ...
}
```

## Channel Authorization

Private channels are authorized in `routes/channels.php`:

```php
Broadcast::channel('user.{userId}', function ($user, $userId) {
    return (int) $user->id === (int) $userId;
});
```

## Test Broadcast

Use DevSettings → Notifications → Test Broadcast to verify configuration.
MD
        ];
    }

    private function getCreatingEventsContent(): array
    {
        return [
            'title' => 'Creating Events',
            'content' => <<<'MD'
# Creating Broadcast Events

## Event Structure

```php
<?php

namespace App\Events;

use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class OrderStatusChanged implements ShouldBroadcastNow
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public int $userId,
        public string $status,
        public array $orderData,
    ) {}

    public function broadcastOn(): array
    {
        return [
            new PrivateChannel('user.'.$this->userId),
        ];
    }

    public function broadcastAs(): string
    {
        return 'order.status';
    }

    public function broadcastWith(): array
    {
        return [
            'status' => $this->status,
            'order' => $this->orderData,
            'timestamp' => now()->toISOString(),
        ];
    }
}
```

## Dispatching Events

```php
broadcast(new OrderStatusChanged(
    userId: $user->id,
    status: 'shipped',
    orderData: $order->toArray(),
));
```

## Channel Types

- `Channel` - Public channel
- `PrivateChannel` - Requires authentication
- `PresenceChannel` - Authenticated with user info
MD
        ];
    }

    private function getListeningEventsContent(): array
    {
        return [
            'title' => 'Listening to Events',
            'content' => <<<'MD'
# Listening to Events (Frontend)

## Using @laravel/echo-vue

```vue
<script setup>
import { useEchoChannel } from '@laravel/echo-vue';
import { usePage } from '@inertiajs/vue3';

const page = usePage();
const userId = page.props.auth.user.id;

// Subscribe to private channel
const channel = useEchoChannel(`private-user.${userId}`);

// Listen for events
channel.listen('.order.status', (event) => {
    console.log('Order status:', event.status);
    // Update UI, show notification, etc.
});
</script>
```

## Event Name Format

The event name in `.listen()` must match `broadcastAs()`:

```php
// PHP
public function broadcastAs(): string
{
    return 'order.status';
}
```

```js
// Vue - prefix with dot
channel.listen('.order.status', callback);
```

## Cleanup

Echo Vue handles cleanup automatically when component unmounts.

## Mobile App (Flutter/React Native)

Use Pusher client libraries:
- Flutter: `pusher_client`
- React Native: `pusher-js`

```dart
// Flutter example
channel.bind('order.status', (event) {
    print(event.data);
});
```
MD
        ];
    }

    private function getDeploymentContent(): array
    {
        return $this->getEnvironmentSetupContent();
    }

    private function getEnvironmentSetupContent(): array
    {
        return [
            'title' => 'Environment Setup',
            'content' => <<<'MD'
# Environment Setup

## Environment Files

| File | Purpose |
|------|---------|
| `.env` | Local development |
| `.env.example` | Template for new installs |
| `.env.production` | Production settings (managed by DevSettings) |

## Key Differences

### Local (.env)
```env
APP_ENV=local
APP_DEBUG=true
APP_URL=http://localhost
IS_TESTING=true
```

### Production (.env.production)
```env
APP_ENV=production
APP_DEBUG=false
APP_URL=https://your-domain.com
IS_TESTING=false
```

## Synced Variables

These are synced across both files by DevSettings:
- `APP_NAME`
- `AUTH_IDENTIFIERS`
- `HAS_*_FIELD`
- `SOCIAL_AUTH_*`
- `ALLOWED_*`

## Managing Production Settings

Use DevSettings panel to configure:
- Production database credentials
- Production mail settings
- Production Pusher credentials

These are stored in `.env.production` and used during deployment.
MD
        ];
    }

    private function getSshDeploymentContent(): array
    {
        return [
            'title' => 'SSH Deployment',
            'content' => <<<'MD'
# SSH Deployment

## Configuration

Configure in DevSettings → Deployment → Server SSH:

- **SSH Host:** server hostname
- **SSH Port:** usually 22
- **SSH Username:** server user
- **SSH Password:** server password
- **Domain:** public_html path or domain folder

## Deployment Process

1. Creates zip of project (excluding node_modules, vendor)
2. Uploads via SFTP
3. Extracts on server
4. Runs migrations
5. Runs composer install
6. Clears caches

## What Gets Deployed

- All PHP files
- Compiled assets (public/build)
- Configuration files
- `.env.production` → `.env`

## Excluded from Deployment

- `node_modules/`
- `vendor/` (composer install runs on server)
- `.git/`
- Local `.env`
- Test files

## Post-Deployment

The following commands run on the server:
```bash
composer install --no-dev
php artisan migrate --force
php artisan config:clear
php artisan cache:clear
php artisan view:clear
```
MD
        ];
    }

    private function getProductionConfigContent(): array
    {
        return [
            'title' => 'Production Config',
            'content' => <<<'MD'
# Production Configuration

## Database

Configure in DevSettings → Deployment → Production Database:

```env
DB_HOST=your-host
DB_PORT=3306
DB_DATABASE=your_database
DB_USERNAME=your_username
DB_PASSWORD=your_password
```

## Mail

Configure in DevSettings → Mail → Production Mail:

```env
MAIL_MAILER=smtp
MAIL_HOST=smtp.your-provider.com
MAIL_PORT=465
MAIL_USERNAME=your@email.com
MAIL_PASSWORD=your-password
MAIL_ENCRYPTION=ssl
MAIL_FROM_ADDRESS=noreply@your-domain.com
```

## Pusher

Configure in DevSettings → Notifications → Production Pusher:

Use separate Pusher app for production.

## Security Checklist

- [ ] `APP_DEBUG=false`
- [ ] `IS_TESTING=false`
- [ ] Strong `APP_KEY`
- [ ] Strong `APP_X_API_TOKEN`
- [ ] Strong admin password
- [ ] HTTPS enabled
- [ ] Database credentials secured
- [ ] Mail credentials secured
MD
        ];
    }
}
