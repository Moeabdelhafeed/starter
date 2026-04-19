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
AUTH_IDENTIFIERS=email          # email, phone, username (comma-separated)
HAS_EMAIL_FIELD=true
HAS_PHONE_FIELD=false
HAS_USERNAME_FIELD=false
IS_OTP_WHATSAPP=false
```

### API Settings
```env
APP_USERS=true                  # Enable/disable app user module
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

The application uses two separate authentication guards:

## Web Guard (`web`)

Used for the admin panel.

- **Users:** Admin users with roles/permissions
- **Roles:** `super_admin`, `fallback`, custom roles
- **Middleware:** `auth`
- **Session-based authentication**

```php
// routes/web.php
Route::middleware('auth')->group(function () {
    Route::get('/', [DashboardController::class, 'index']);
});
```

## API Guard (`api`)

Used for the mobile app REST API.

- **Users:** App users (different user pool)
- **Role:** `user`
- **Middleware:** `auth:sanctum`
- **Token-based authentication** (Bearer token)

```php
// routes/api.php
Route::middleware(['auth:sanctum', 'role:user', 'active'])->group(function () {
    Route::get('/profile', [ProfileController::class, 'show']);
});
```

## Key Differences

| Aspect | Web Guard | API Guard |
|--------|-----------|-----------|
| Users | Admin users | App users |
| Auth Method | Session | Bearer Token |
| Permissions | Yes (Spatie) | No |
| Middleware | `auth` | `auth:sanctum` |
MD
        ];
    }

    private function getAdminAuthContent(): array
    {
        return [
            'title' => 'Admin Authentication',
            'content' => <<<'MD'
# Admin Authentication

## Login Flow

1. User visits `/login`
2. Submits email and password
3. System validates credentials against `users` table
4. Checks if user has web-guard role
5. Creates session and redirects to dashboard

## Protected Routes

All admin routes require authentication:

```php
Route::middleware('auth')->group(function () {
    // Dashboard - no permission required
    Route::get('/', [DashboardController::class, 'index']);

    // Feature routes - permission required
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

## Headers Required

All API requests must include:

```http
X-API-TOKEN: your-api-token
Accept: application/json
Content-Type: application/json
```

For protected routes, add:
```http
Authorization: Bearer {token}
```

## Registration

```http
POST /api/register

{
    "name": "John Doe",
    "email": "john@example.com",    // Required if in AUTH_IDENTIFIERS
    "phone": "+962791234567",       // Required if in AUTH_IDENTIFIERS
    "password": "password123",
    "password_confirmation": "password123"
}
```

## Login

```http
POST /api/login

{
    "identifier": "john@example.com",  // Can be email, phone, or username
    "password": "password123"
}
```

Response:
```json
{
    "success": true,
    "message": "Login successful",
    "data": {
        "user": { ... },
        "token": "1|abc123..."
    }
}
```

## Auth Identifiers

Configure which fields users can log in with:

```env
AUTH_IDENTIFIERS=email,phone
```

- `email` - Login with email address
- `phone` - Login with phone number
- `username` - Login with username

Multiple identifiers can be combined.
MD
        ];
    }

    private function getSocialAuthContent(): array
    {
        return [
            'title' => 'Social Authentication',
            'content' => <<<'MD'
# Social Authentication

Firebase-based social login for mobile apps.

## Requirements

- `AUTH_IDENTIFIERS` must include `email`
- Firebase credentials configured
- Providers enabled in Firebase console

## Configuration

```env
SOCIAL_AUTH_PROVIDERS=google.com,apple.com,facebook.com
SOCIAL_AUTH_MAX_ACCOUNTS=0  # 0 = unlimited
```

## Available Providers

- `google.com` - Google Sign-In
- `apple.com` - Apple Sign-In
- `facebook.com` - Facebook Login
- `twitter.com` - Twitter Login
- `github.com` - GitHub Login

## Endpoints

### Login/Register
```http
POST /api/firebase-login

{
    "id_token": "firebase-id-token"
}
```

### Link Social Account (authenticated)
```http
POST /api/link-social-account

{
    "id_token": "firebase-id-token"
}
```

### Unlink Social Account
```http
DELETE /api/unlink-social-account

{
    "provider": "google.com"
}
```

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

## How It Works

1. User registers with identifier (email/phone)
2. OTP is generated and stored in `otps` table
3. OTP is sent via appropriate channel
4. User verifies OTP to activate account

## OTP Delivery Priority

When multiple identifiers are configured:

1. **Email** - Sent via EmailHelper
2. **Phone** - Sent via SMS or WhatsApp (based on `IS_OTP_WHATSAPP`)
3. **Username only** - OTP stored but not delivered (testing mode)

## Endpoints

### Request OTP
```http
POST /api/forgot-password

{
    "identifier": "john@example.com"
}
```

### Verify OTP
```http
POST /api/verify-otp

{
    "identifier": "john@example.com",
    "otp": "123456"
}
```

### Reset Password
```http
POST /api/reset-password

{
    "identifier": "john@example.com",
    "otp": "123456",
    "password": "newpassword123",
    "password_confirmation": "newpassword123"
}
```

## Testing Mode

When `IS_TESTING=true`, OTP is included in the API response:

```json
{
    "success": true,
    "message": "OTP sent",
    "data": {
        "otp": "123456"  // Only in testing mode
    }
}
```
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
| `api` | General API endpoints | 60 req/1 min | `throttle:api` |
| `auth` | Login/register attempts | 5 req/1 min | `throttle:auth` |
| `otp` | OTP/verification requests | 3 req/5 min | `throttle:otp` |

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
                'message' => __('api.too_many_requests'),
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

// OTP routes (strictest)
Route::middleware('throttle:otp')->group(function () {
    Route::post('/forgot-password', [AppUserController::class, 'forgotPassword']);
});

// Standard API routes
Route::middleware('throttle:api')->group(function () {
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

## Public Endpoints

### Register
```http
POST /api/register
```

### Login
```http
POST /api/login
```

### Firebase Login
```http
POST /api/firebase-login
```

### Forgot Password
```http
POST /api/forgot-password
```

### Verify OTP
```http
POST /api/verify-otp
```

### Reset Password
```http
POST /api/reset-password
```

## Protected Endpoints

### Logout
```http
POST /api/logout
Authorization: Bearer {token}
```

### Refresh Token
```http
POST /api/refresh-token
Authorization: Bearer {token}
```

### Verify Account
```http
POST /api/verify
Authorization: Bearer {token}

{
    "otp": "123456"
}
```
MD
        ];
    }

    private function getApiUserEndpointsContent(): array
    {
        return [
            'title' => 'User Endpoints',
            'content' => <<<'MD'
# User Endpoints

All require `Authorization: Bearer {token}`

## Profile

### Get Profile
```http
GET /api/profile
```

### Update Profile
```http
PUT /api/update-profile

{
    "name": "John Doe"
}
```

### Update Profile Image
```http
POST /api/update-profile-image
Content-Type: multipart/form-data

image: [file]
```

### Change Password
```http
PUT /api/change-password

{
    "current_password": "old123",
    "password": "new123",
    "password_confirmation": "new123"
}
```

### Delete Account
```http
DELETE /api/delete-account
```

## Social Accounts (verified users only)

### List Linked Accounts
```http
GET /api/social-accounts
```

### Link Account
```http
POST /api/link-social-account

{
    "id_token": "firebase-token"
}
```

### Unlink Account
```http
DELETE /api/unlink-social-account

{
    "provider": "google.com"
}
```
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
```

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
