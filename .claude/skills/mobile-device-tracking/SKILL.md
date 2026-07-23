---
name: mobile-device-tracking
description: "Use whenever working on device/guest identity in this starter's mobile API: the X-Device-Id/X-Platform/X-FCM-Token headers, the IdentifyDevice middleware resolution order, guest creation (POST /api/guest), the unified user_devices registry, multi-session device management (GET/DELETE /api/devices), FCM token storage/sends, or the guest-only route middleware. Trigger on: 'guest user', 'device header', 'FCM token', 'multi-session', 'kick other devices', 401/403 on api requests missing device headers, push notification sends. For login/register/OTP/social-auth logic itself, see mobile-auth-identity instead."
metadata:
  author: project
---

# Mobile API — Guest Tracking & Device Headers

Every api request (mobile or web SPA) carries:

- `X-Device-Id` — UUID generated client-side on first launch and persisted locally (mobile keychain / web `localStorage`).
- `X-Platform` — one of `web`, `ios`, `android`.
- `X-FCM-Token` — required when platform is `ios` or `android`. Web may omit. Updates `user_devices.fcm_token` on every hit (throttled per minute, plus immediately when the token value changes).

Headers are validated by the global `IdentifyDevice` middleware (registered in `bootstrap/app.php` on the api middleware group right after `XApiTokenMiddlleware`). Missing/invalid → 422 with `errors.device_id`, `errors.platform`, or `errors.fcm_token`.

## Resolution order

`IdentifyDevice` resolves the caller in four steps:

1. **Valid Bearer** → `Laravel\Sanctum\PersonalAccessToken::findToken` resolves the real user. Attached via `setUserResolver`. `user_devices` row keyed by `device_id` is upserted with the latest fcm + last_seen.
2. **Claimed device** — `user_devices.device_id` row exists for a registered (non-guest) user. Without a valid Bearer the caller can't act as that user. Request flagged via `attributes->set('device_claimed', true)`. **No row update** (don't let unauthed callers mutate someone else's fcm). Guest-only routes 403.
3. **Existing guest** — `users.guest_id = $deviceId AND is_guest = true` → attached. `user_devices` row touched/created (throttled).
4. **No user attached** — middleware does NOT auto-create guests anymore. Client must call `POST /api/guest` to create one. Downstream `$request->user()` returns null for endpoints that don't gate on auth.

Downstream code (`SetLocaleMiddleware`, controllers reading `$request->user()`) sees the resolved user when one exists. Otherwise null — handle gracefully.

## Explicit guest creation

`POST /api/guest` (public, throttle:api). Body: none. Headers: standard device headers. Returns the existing guest if one already matches the UUID (idempotent), else creates one. 403 when the device is claimed by a real user OR when `APP_GUESTS=false`.

Frontend contract: call once on first app launch before any other endpoint that relies on `$request->user()`. After register/login/logout cycles, the guest may need to be recreated — frontend should detect null `$request->user()` and call `POST /api/guest` again.

## `fcm_token` is header-only

`fcm_token` is **never** read from the request body. The login / register / firebase-login endpoints no longer accept it as input. Mobile clients send `X-FCM-Token` once per request and the middleware (or `trackDevice` on token issuance) persists it. `User::fcmTokens()` reads from `user_devices.fcm_token` for both guest and registered rows — same code path for push sends.

## Users table flags

- `is_guest` (bool, indexed) — `true` for guest rows, `false` for real users.
- `platform` (string, nullable) — `web`, `ios`, or `android`. Set on guest creation, left null on real-user rows unless populated.
- `guest_id` (string 64, unique nullable) — the UUID from `X-Device-Id`. Lookup key for both lazy upserts and login-time conversion.
- `last_seen_at` (timestamp, indexed) — touched by the middleware on every guest hit, throttled to once per 60 seconds per row.

Guest rows are created with `name='Guest'`, `is_active=true`, `verified_at=now()`, role `user@api`. No email/phone/password/username.

## `config` exposes `app_users` + `app_guests`

`GET /api/config` response includes `app_users` and `app_guests` booleans so the frontend can adapt UI dynamically (e.g. hide login screens when `app_users=false`, render guest-only flows when `app_guests=true`).

## Login conversion (no double rows)

When a real auth flow issues a Sanctum token, `trackDevice` in `AppUserController` reads `X-Device-Id` from the request, persists it on the new `user_devices` row, and calls `User::convertFromGuest($deviceId)` to **forceDelete** any guest row that shared the same `guest_id`. The device promotes from guest to real user without leaving an orphan.

## `user_devices` row layout

`user_devices` is the unified device registry — used for **both** guests and registered users. One row per `device_id`, FK to `users.id`. The `personal_access_token_id` column is **nullable** (guests have no Sanctum token). Auth-flow `trackDevice` runs `User::convertFromGuest($deviceId)` first (which deletes the guest user and cascade-drops the guest `user_devices` row), then inserts a fresh row with `personal_access_token_id` set. Same physical device keeps the same `device_id` across guest → user transition.

`User::fcmTokens()` always pulls from this table — guest sends, multi-session sends, all share one path.

## `guest-only` middleware

Aliased `guest-only`. Use per-route to reject Bearer-bearing requests AND any authed user. Returns 403 with `errors.auth = guest_only_route` for non-guests. Run AFTER `IdentifyDevice` (api group already does this); applied per-route inside route definitions:

```php
Route::middleware('guest-only')->group(function () {
    // routes only guests can hit
});
```

## Sessions / Devices (`MULTI_SESSION_ENABLED`, default `true`)

Every Sanctum token issued by `login` / `firebase-login` / verify-OTP path is recorded in a `user_devices` row (FK-cascade on `personal_access_token_id` so revoking a token auto-drops its device). Columns: `fcm_token`, `device_name`, `platform`, `ip`, `user_agent`, `last_seen_at`. Login response includes `token_id` so clients can identify themselves later.

- **Multi-session ON:** new logins append a device row. Tokens stack. The user manages them via `GET /api/devices` (returns rows with `is_current: bool`) and `DELETE /api/devices/{id}` (revokes the underlying token + broadcasts a kick).
- **Multi-session OFF:** `trackDevice` revokes every other token belonging to the user before issuing the new one and broadcasts `device.revoked` on `private-user.{userId}` for each kicked token id.

**Per-device FCM:** the legacy `users.fcm_token` column was dropped. Source of truth is `user_devices.fcm_token`. Use `$user->fcmTokens()` (returns array) for any push send so multi-session users get notified everywhere; `FCMHelper::send()` already accepts arrays.

**Remote-logout broadcast:** `App\Events\DeviceRevoked` (`ShouldBroadcastNow`) on `private-user.{userId}`, event name `.device.revoked`, payload `{ token_id }`. Frontend composable `useDeviceRevocation.ts` listens and clears local creds when the token id matches the locally stored `current_token_id`.

**DevSettings → Sessions** toggles `MULTI_SESSION_ENABLED` at runtime (writes `.env`, runs `config:clear`).

## Frontend recipes

**Mobile:** generate UUID once at install, store in secure storage, send both headers on every request.

**Web SPA:**
```js
let deviceId = localStorage.getItem('device_id');
if (!deviceId) {
    deviceId = crypto.randomUUID();
    localStorage.setItem('device_id', deviceId);
}
axios.defaults.headers.common['X-Device-Id'] = deviceId;
axios.defaults.headers.common['X-Platform'] = 'web';
// X-FCM-Token only required on ios/android; web skips it.
```

**Mobile:**
```js
axios.defaults.headers.common['X-Device-Id'] = installUuid;
axios.defaults.headers.common['X-Platform'] = 'ios'; // or 'android'
axios.defaults.headers.common['X-FCM-Token'] = currentFcmToken;
// Refresh X-FCM-Token whenever Firebase rotates the device token.
```

Set once at app boot (refresh `X-FCM-Token` whenever Firebase rotates it). Frontend reads `config.app_guests` to decide whether to render guest-mode UI vs login-required gates.

## Env flags

- `APP_USERS` — enables real-user auth flow (login/register/etc).
- `APP_GUESTS` — enables guest creation in `IdentifyDevice`. When `false`, headers still required but no guest row is created.

Both are independent. Either, both, or neither can be on. Toggleable via DevSettings UI.
