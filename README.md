# Inertia Starter Kit

A powerful Laravel + Vue 3 + Inertia.js starter template with everything you need to build modern web applications.

## Features

- **Dual-Guard Authentication** - Sanctum-powered auth for both admin panel (web) and mobile API
- **Role-Based Access Control** - Spatie Permission with dynamic permissions per feature
- **Multi-Language Support** - Full RTL support with database-driven translations (English & Arabic included)
- **Modern UI** - Tailwind CSS v4 with dark mode, Reka UI components, and Lucide icons
- **API Ready** - RESTful API with proper validation, rate limiting, and documentation
- **Firebase Integration** - Social authentication (Google, Apple, Facebook) and push notifications
- **Real-Time Broadcasting** - Pusher integration for WebSocket-based features
- **Activity Logging** - Track all model changes with detailed audit logs
- **Soft Deletes UI** - Built-in trash/restore functionality with TrashedFilter component

## Tech Stack

### Backend
- Laravel 13 (PHP 8.2+)
- MySQL
- Laravel Sanctum
- Spatie Permission
- Firebase Admin SDK

### Frontend
- Vue 3 (`<script setup>` + TypeScript)
- Inertia.js v3
- Tailwind CSS v4
- Reka UI (headless components)
- vue-i18n

### Build Tools
- Vite 7
- Laravel Wayfinder (route generation)
- Ziggy (route helpers)

## Requirements

- PHP 8.2+
- Composer
- Node.js 18+
- MySQL 8.0+

## Installation

### Quick Install (Recommended)

Run the automated installer:

```bash
./install.sh
```

This will:
1. Install Composer & NPM dependencies
2. Generate application key
3. Ask for database configuration
4. Run migrations and seeders
5. Build assets
6. Start the development server

To run the server again later:
```bash
composer dev
```

---

### Manual Installation

1. **Clone the repository**
   ```bash
   git clone <repository-url> my-app
   cd my-app
   ```

2. **Install PHP dependencies**
   ```bash
   composer install
   ```

3. **Install Node dependencies**
   ```bash
   npm install
   ```

4. **Configure environment**
   ```bash
   cp .env.example .env
   ```

5. **Edit `.env` with your settings**
   ```env
   APP_NAME=MyApp
   APP_URL=http://localhost:8000

   DB_DATABASE=your_database
   DB_USERNAME=your_username
   DB_PASSWORD=your_password

   ADMIN_EMAIL=admin@example.com
   ADMIN_PASSWORD=YourSecurePassword123
   ```

6. **Generate application key**
   ```bash
   php artisan key:generate
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
   # Terminal 1: Laravel server
   php artisan serve

   # Terminal 2: Vite dev server
   npm run dev
   ```

10. **Access the application**
    - Admin Panel: http://localhost:8000
    - Login with your configured admin credentials

## Configuration

### Environment Variables

Key `.env` flags:

| Variable | Description |
|----------|-------------|
| `APP_USERS` | Enable mobile app user module and API auth routes |
| `AUTH_IDENTIFIERS` | Comma-separated login identifiers (email, phone, username) |
| `IS_TESTING` | Expose OTP codes in API responses for development |
| `SOCIAL_AUTH_PROVIDERS` | Allowed social login providers |
| `BROADCAST_CONNECTION` | Broadcasting driver (pusher, log, null) |

### Authentication Options

Configure user authentication fields in `.env`:

```env
# Login identifiers (at least one required)
AUTH_IDENTIFIERS=email

# Additional profile fields
HAS_EMAIL_FIELD=true
HAS_PHONE_FIELD=false
HAS_USERNAME_FIELD=false
```

### Firebase (Optional)

For push notifications and social auth:

1. Create a Firebase project
2. Download service account JSON
3. Upload via Developer Settings or place at `storage/app/private/firebase-auth.json`

### Pusher Broadcasting (Optional)

For real-time features:

```env
BROADCAST_CONNECTION=pusher
PUSHER_APP_ID=your_app_id
PUSHER_APP_KEY=your_app_key
PUSHER_APP_SECRET=your_app_secret
PUSHER_APP_CLUSTER=eu
```

## Project Structure

```
app/
├── Http/Controllers/
│   ├── Admin/          # Admin panel controllers (Inertia)
│   └── Api/            # Mobile app API controllers
├── Models/             # Eloquent models
├── Traits/             # HasImage, HasVideo, LogsActivity, HasTranslations
└── Helpers/            # ApiResponse, EmailHelper, FCMHelper

resources/js/
├── pages/              # Vue pages (one per feature)
├── components/         # Vue components
│   ├── Shared/         # Reusable components (Navbar, Modals, etc.)
│   └── ui/             # UI primitives (Button, Input, Table, etc.)
├── layouts/            # Page layouts
├── locales/            # Translation files (en.json, ar.json)
└── composables/        # Vue composables
```

## Available Commands

```bash
# Development
php artisan serve          # Start Laravel server
npm run dev               # Start Vite dev server
composer run dev          # Start both servers

# Build
npm run build             # Build production assets

# Database
php artisan migrate       # Run migrations
php artisan db:seed       # Run seeders
php artisan migrate:fresh --seed  # Reset database

# Code Quality
vendor/bin/pint           # Format PHP code
npm run lint              # Lint frontend code

# Testing
php artisan test          # Run tests
```

## API Documentation

Import `Starter.postman_collection.json` into Postman. Set these variables:

- `{{base_url}}` - e.g., `http://localhost:8000`
- `{{x-api-token}}` - Value from `APP_X_API_TOKEN` in `.env`
- `{{token}}` - Bearer token from login/register response

## Developer Settings

When `APP_ENV=local`, access Developer Settings to configure:

- Theme colors (light/dark)
- Branding (logo, favicon)
- Authentication settings
- Firebase credentials
- Pusher broadcasting
- Production database & mail
- SSH deployment
- Git integration

## Documentation

In-app documentation is available at `/docs` when `APP_ENV=local`.

## How to Modify

- **Adding Logic**: Controllers are in `app/Http/Controllers`
- **Updating UI**: Pages in `resources/js/pages`, components in `resources/js/components`
- **Translations**: Vue files in `resources/js/locales/`, PHP files in `lang/`
- **Styling**: Use logical Tailwind classes (`ms-4` instead of `ml-4`) for RTL compatibility

## License

This project is proprietary software.
