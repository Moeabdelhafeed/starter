# Agent instructions

**`CLAUDE.md` in this directory is the single source of truth for this project.** Read it in full
before changing anything, and follow it exactly — it overrides any general convention you would
otherwise apply.

This file used to carry its own copy of the guidelines. The copy drifted (it still claimed Laravel
12 and Inertia v2 long after the upgrade), so it is now a pointer instead.

## Quick orientation

- **Stack:** Laravel 13 / PHP 8.3+ / MySQL, Vue 3 (`<script setup>` + TypeScript) with Inertia v3,
  Tailwind v4, Reka UI, Sanctum (dual guard: `web` for the admin panel, `api` for the mobile app),
  Spatie Permission.
- **Domain rules live in `.claude/skills/`.** Activate the relevant one *before* touching that part
  of the app: `admin-feature-crud`, `vue-admin-ui-patterns`, `styling-rtl-responsive`,
  `translations-i18n`, `mobile-auth-identity`, `mobile-device-tracking`, `dynamic-storage-media`,
  `realtime-broadcasting`. `CLAUDE.md` lists what each one covers.
- **Never call `env()` outside `config/`** — it returns `null` once `php artisan config:cache` runs.
  Read `config('features.*')`, `config('auth.*')`, `config('app.is_testing')` instead. A test fails
  the build if an `env()` call reappears.
- **Never send a real `PUT`/`PATCH`/`DELETE` from the frontend** — the production host blocks those
  verbs. POST with `_method` spoofing. See the `vue-admin-ui-patterns` skill.
- **Never use physical-direction Tailwind classes** (`ml-*`, `pr-*`, `left-*`, `text-left`) or raw
  palette colors (`bg-red-50`). The app is RTL and dark-mode aware. See `styling-rtl-responsive`.

## Checks before you call something done

```bash
php artisan test          # Pest, SQLite in memory
vendor/bin/pint --test    # PHP formatting
npm run types             # vue-tsc
npm run lint:check        # eslint
npm run build             # vite
```
