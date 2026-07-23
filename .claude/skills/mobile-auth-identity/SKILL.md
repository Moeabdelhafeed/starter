---
name: mobile-auth-identity
description: "Use whenever touching the mobile-app API auth flow in this starter: routes/api.php auth endpoints, rate limiting, AUTH_IDENTIFIERS/HAS_*_FIELD/AUTH_MODE config, register/login/OTP, the field-keyed error convention (errors.identifier / errors.otp / errors.password etc.), account deletion, or Firebase social auth (google/apple/facebook). Trigger on: 'add an API auth endpoint', register/login/forgot-password/verify-otp work, ApiResponse::error field-key questions, AUTH_MODE=otp vs password, firebase-login, link/unlink social account. For guest tracking, X-Device-Id/X-Platform/X-FCM-Token headers, and multi-session/device management, see mobile-device-tracking instead. Do not use for admin-panel web auth (Sanctum web guard) — see admin-feature-crud."
metadata:
  author: project
---

# Mobile API — Auth & Identity

## API Routes (`routes/api.php`)

- Public: register, login, firebase-login, forgot-password, translations, languages, pages.
- Protected: `middleware(['auth:sanctum', 'role:user', 'active'])`.
- Verified-only routes add `->middleware('verified')`.
- App user routes are conditional: `env('APP_USERS') === true`.
- Responses use `ApiResponse::success($data, $message, $token = null)` / `ApiResponse::error($message, $errors, $status)`.
- When `$token` is passed to `ApiResponse::success()`, it is included **both** at the top level (`response.token`) AND inside `data.token`. Use this for any endpoint that issues a Bearer token (`login`, `firebase-login`).
- `POST /api/register` does **not** issue a token. It creates the user and sends a verification OTP. The client must call `POST /api/login` afterwards to obtain a token (login on an unverified account triggers another verify OTP and returns a token usable with `verify-otp`).
- **Auto-verify on `username` identifier:** when the resolved identifier kind is `username` (no OTP delivery channel), `register` sets `verified_at = now()` immediately and skips OTP. The user can call `login` and receive a token without the verify-OTP step. Email/phone identifiers still go through the normal OTP flow.

## API Rate Limiting

Four custom rate limiters are defined in `AppServiceProvider::configureRateLimiting()`:

| Limiter | Purpose | Default | Middleware |
|---------|---------|---------|------------|
| `api` | General API endpoints + OTP **verification** | 60 req/1 min | `throttle:api` |
| `auth` | Login/register attempts | 5 req/1 min | `throttle:auth` |
| `otp` | OTP **sending** only (`send-otp`, `forgot-password`) | 3 req/5 min | `throttle:otp` |

`throttle:otp` is applied **only to OTP-sending endpoints** to prevent SMS/email spam. OTP **verification** (`verify-otp`, `verify-forgot-password-otp`, `change-forgot-password`) uses standard `throttle:api` so users can retry codes without lockout.

There are **two OTP types** stored in the `otps` table:
- `verify` — activate account (sent via `POST /api/send-otp`, consumed by `POST /api/verify-otp`)
- `reset_password` — password reset (sent via `POST /api/forgot-password`, consumed by `verify-forgot-password-otp` + `change-forgot-password`)

Rate limits are configurable via `.env` (`RATE_LIMIT_*`) or through the DevSettings CMS under "Validation Settings > API Rate Limiting". When rate limit is exceeded, the API returns HTTP 429 with a translated error message.

## Authentication Configuration

The app user (mobile API) auth system uses **email and/or phone as identifiers**, with username as a separate optional field that doubles as a login alias:

- `AUTH_IDENTIFIERS` — comma-separated list of identifiers. **Allowed values: `email`, `phone`** (e.g. `email`, `phone`, `email,phone`). Username is **not** allowed here. Defaults to `email` if empty/invalid.
- `HAS_EMAIL_FIELD` — when `true` AND email is not an identifier, exposes `email` as an optional profile field.
- `HAS_PHONE_FIELD` — when `true` AND phone is not an identifier, exposes `phone` as an optional profile field.
- `HAS_USERNAME_FIELD` — when `true`, the `username` field is **required at register**, **searchable at login** (login alias), and editable via `update-profile`. Username is never an identifier; it is always a separate column.

DevSettings UI under "Authentication Config" lets you toggle these. Admin AppUser table, edit modal, and API endpoints adapt dynamically.

### Auth modes (`AUTH_MODE`)

The app supports two auth modes, toggleable at runtime via DevSettings → Authentication → Login Method:

- **`AUTH_MODE=password`** (default) — classic flow. Endpoints: `register`, `login` (identifier + password), `forgot-password`, `verify-forgot-password-otp`, `change-forgot-password`, `change-password`.
- **`AUTH_MODE=otp`** — passwordless. Endpoints: `login` (identifier-only — auto-creates user if missing, uses `promoteGuestOrCreate` to preserve guest data), `verify-login` (consumes `login`-type OTP, issues token). Forgot/change-password routes disappear. Register endpoint disappears (login covers it).

Surfaced via `GET /api/config` as `auth_mode`. Frontend branches login UI based on this. Reviewer accounts bypass OTP in both modes (sendOtpToUser short-circuits; verify-login auto-passes when `is_reviewer`).

In OTP mode the `users.password` column is null for new accounts (already nullable since social-auth work).

**Registration:** Request body always includes `policy_agreed`, `name`, `identifier` (email or phone), `password`, `password_confirmation`. When `HAS_USERNAME_FIELD=true`, `username` is also required. Optional non-identifier extras (`email`/`phone` when `HAS_*_FIELD=true` and not identifier) keep their own keys.

`identifier` resolution against `AUTH_IDENTIFIERS`:
- **Single identifier configured** → value validated against that field's rules.
- **Multiple identifiers (`email,phone`)** → kind detected from format (email pattern → email, `+` and digits → phone) and validated against the matching field. Falls back to the first configured identifier on detection failure.

**Login:** `identifier` is searched across configured email/phone identifier columns AND the `username` column when `HAS_USERNAME_FIELD=true`. Kind is detected from the value's format and only the matching column is queried (no OR-collisions). Users can log in with any of those values.

**Email case normalization:** the `User` model lowercases `email` on every write (via `setEmailAttribute`). Lookup methods (`findUserByIdentifier`, `checkIdentifier`, identifier-change) lowercase email values before query/storage. Cross-DB safe (MySQL/PostgreSQL).

**Username format:** when enabled, must match `/^[A-Za-z][A-Za-z0-9_-]*$/` with `min:3`. Email/phone-shaped values are rejected. Uniqueness is scoped to api-guard users only (via `whereExists` on `model_has_roles` + `roles`).

## Field-Keyed Error Convention

**Identifier error format:** Auth endpoints (`register`, `login`, `forgot-password`, `verify-forgot-password-otp`, `change-forgot-password`) return identifier-validation errors and `user not found` as HTTP **422** with errors keyed under `identifier`:

```json
{
    "success": false,
    "message": "...",
    "errors": { "identifier": ["..."] },
    "data": null
}
```

**Rule:** Any API error tied to a specific request input field is returned as HTTP **422** with the error keyed under the **same name as the input parameter**. The frontend should look up `errors.{paramName}` to show inline messages next to the matching input.

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

**Config endpoint:** `GET /api/config` (public, throttle:api) exposes the live app configuration so mobile/web clients can adapt their UI on boot. Returns `identifiers`, `has_username_field`, `has_email_field`, `has_phone_field`, `social_providers`, `max_social_accounts`, `social_auth_available`, `is_otp_whatsapp`, `multi_session`, `app_users`, `app_guests`. Always available (not gated on APP_USERS).

**Forgot-password channel:** `forgot-password` takes `identifier` plus an optional `type` (`"email"` or `"phone"`).
- When `type` is omitted → channel auto-picked using priority `email > phone` from the user's populated columns.
- When `type` is provided → must be one of the user's populated channels (else 422 `errors.type`).
- Response includes the actual `channel` used so the client knows where the OTP went.

**Check-identifier reuse:** `POST /api/check-identifier` detects kind and queries the matching column scoped to api-guard users (supports identifier columns AND `HAS_*_FIELD` extras). Response includes:
- `exists` — whether a user matched.
- `pending_deletion` / `suspended` — see Account Deletion below.
- `available_channels` — array listing which OTP delivery channels (`"email"`, `"phone"`) are populated on that user. The client uses this to decide which `type` to pass to `forgot-password`, and as a pre-submit uniqueness check before `register`, `update-profile` (username), or `request-identifier-change`.
- `has_password` — `false` indicates a social-only account. Frontend should hide the password field and present matching social-login buttons.
- `social_providers` — Firebase providers already linked to the user (e.g. `["google.com", "apple.com"]`). Frontend can highlight or pre-select the relevant provider.
- `verified` — `verified_at` populated. Frontend skips OTP verification flow when true.
- `is_guest` — matched row is a guest. Rare (guests have null email/phone/username) but exposed for completeness.

## Account Deletion (user-initiated, restorable)

`DELETE /api/delete-account` sets `users.account_deleted_at = now()` and revokes all tokens. The row stays in DB. `POST /api/check-identifier` returns `pending_deletion: true` so the client can prompt the user. `POST /api/login` with valid credentials clears `account_deleted_at` and returns `account_restored: true`. Purge runs via the global `PurgeDeletedUsersAfterResponse` middleware: every HTTP request fires `Artisan::call('users:purge-deleted')` in the middleware's `terminate()` hook (after the response is sent to the client), throttled to once per hour with a cache lock and capped at 50 rows per run. No cron, no queue worker. The command calls `forceDelete()` on accounts older than the retention window, triggering `User::$cascadeOnDelete`. Retention is configurable via `ACCOUNT_DELETION_RETENTION_DAYS` env var or DevSettings → "Account Deletion Retention" (default 30 days). Admin soft-delete (Laravel `SoftDeletes` / `deleted_at`) is independent and unchanged — admin-trashed rows are NOT auto-purged; admin-trashed users see `suspended: true` from `check-identifier` and a 403 `api.account_suspended` from login.

**Cascade declarations on User:**
- `protected array $cascadeOnDelete = [];` — relations to delete alongside the user. Admin soft-delete stamps each cascaded child's `deleted_at` with the parent's exact timestamp (bypasses child model events). Force-delete (admin or middleware purge) cascades to `forceDelete()` on the relations.
- `protected array $cascadeOnRestore = [];` — relations to restore alongside the user when admin restores from trash. Matches children whose `deleted_at` equals the parent's pre-restore `deleted_at`, so children trashed independently of the user are left alone. Captured via the `restoring` event before Laravel clears `deleted_at`, applied in the `restored` event.

Pair with `BlocksRestoreIfParentTrashed` (see `admin-feature-crud` skill's traits reference) on the child model so admins can't restore an orphaned child while its parent is still trashed.

## Firebase Social Authentication

The app supports Firebase-based social authentication (Google, Apple, Facebook, Twitter, GitHub) for mobile apps. Social auth is **only available when email is configured as an identifier** (`AUTH_IDENTIFIERS` must include `email`).

**Setup:**
1. Configure Firebase credentials in `config/firebase.php` (requires `kreait/laravel-firebase` package).
2. Ensure `AUTH_IDENTIFIERS` includes `email`.
3. Configure allowed providers and account limits in DevSettings under "Social Authentication".

**Database structure:** social accounts live in a separate `social_accounts` table, one-to-many with users: `user_id`, `provider` (e.g. `google.com`), `provider_id` (Firebase UID), `email`, `name`.

**Config env vars:**
- `SOCIAL_AUTH_PROVIDERS` — comma-separated allowed providers (e.g. `google.com,apple.com,facebook.com`). Empty = all allowed.
- `SOCIAL_AUTH_MAX_ACCOUNTS` — max social accounts per user. `0` = unlimited, `1` = one only.

**API endpoints:**
- Public: `POST /api/firebase-login` — login or register via Firebase ID token.
- Protected (verified): `GET /api/social-accounts`, `POST /api/link-social-account`, `DELETE /api/unlink-social-account`.

**Behavior rules:**
1. New user via social auth → creates account with linked social account, auto-verifies, assigns `user` role.
2. Existing user with password → blocks social login, returns "use password" error.
3. Existing social-only user → allows login via any linked social account.
4. Provider restrictions → only providers in `SOCIAL_AUTH_PROVIDERS` are allowed (if configured).
5. Account limits → users cannot exceed `SOCIAL_AUTH_MAX_ACCOUNTS` (if > 0).
6. Same provider twice → users cannot link the same provider twice.
7. Unlink protection → cannot unlink last social account unless user has a password set.

**Account linking flow:** users with password accounts can link multiple social accounts while logged in. Users cannot link a social account already used by another user. Social account email must match the user's account email when linking. Each provider can only be linked once per user.
