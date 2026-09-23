<?php

namespace App\Http\Controllers\Api\AppUser;

use App\Events\DeviceRevoked;
use App\Helpers\ApiResponse;
use App\Helpers\AuthIdentity;
use App\Helpers\Broadcaster;
use App\Helpers\EmailHelper;
use App\Helpers\FcmTopics;
use App\Helpers\Firebase;
use App\Helpers\PhoneNumber;
use App\Helpers\SendSMS;
use App\Helpers\SendWhatsapp;
use App\Helpers\Trans;
use App\Http\Controllers\Controller;
use App\Models\Otp;
use App\Models\Role;
use App\Models\SocialAccount;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\Rules\Unique;
use Kreait\Firebase\Exception\Auth\FailedToVerifyToken;
use Laravel\Sanctum\PersonalAccessToken;

class AppUserController extends Controller
{
    /** Lifetime of an ordinary session token. */
    private const TOKEN_DAYS = 1;

    /** Lifetime of a token the user asked to be remembered, and of social/OTP logins. */
    private const TOKEN_DAYS_REMEMBERED = 30;

    /**
     * Register
     *
     * Create a new mobile-app account. Only available when AUTH_MODE=password (route disappears entirely
     * under AUTH_MODE=otp — login covers registration there instead).
     *
     * **Registering sends a verify OTP and issues no token.** The account is created unverified;
     * the client then calls `POST /api/login` (a token is issued even unverified) and
     * `POST /api/verify-otp` with the code. There is no variant of this — an account is usable
     * only once its email or phone has been proven.
     *
     * `IS_TESTING=true` changes the code, not the flow: it is the ascending sequence `123456` and
     * comes back as `data.otp`, so a tester can walk the same screens without a real mailbox.
     *
     * @group Authentication
     * Register, log in, and manage OTP-based verification and password resets for mobile-app users.
     *
     * @bodyParam policy_agreed boolean required Must be accepted. Example: true
     * @bodyParam name string required The user's display name. Example: Jane Doe
     * @bodyParam identifier string required Email or phone, depending on AUTH_IDENTIFIERS config. A phone MUST carry its country code (`966500000000` or `+966500000000`); it is stored in E.164. Example: jane@example.com
     * @bodyParam type string required Which identifier is being sent — `email` or `phone` (never inferred from the value). Example: email
     * @bodyParam password string required Minimum 8 characters. Example: SecurePass123!
     * @bodyParam password_confirmation string required Must match `password`. Example: SecurePass123!
     * @bodyParam username string Only present when HAS_USERNAME_FIELD=true. Must start with a letter; letters/digits/underscores/dashes only. Example: janedoe
     * @bodyParam email string Only present when HAS_EMAIL_FIELD=true and email is not configured as the identifier. Example: jane@example.com
     * @bodyParam phone string Only present when HAS_PHONE_FIELD=true and phone is not configured as the identifier. Example: +15551234567
     *
     * @response 200 scenario="Registered — verify OTP sent" {"success": true, "message": "User registered successfully.", "data": {"user": {"id": 42, "name": "Jane Doe", "email": "jane@example.com", "phone": null, "username": null, "is_active": true, "verified_at": null, "created_at": "2026-07-19T10:00:00.000000Z"}, "otp_expires_in_minutes": 5}, "errors": null}
     * @response 200 scenario="Registered under IS_TESTING=true — code echoed back" {"success": true, "message": "User registered successfully.", "data": {"user": {"id": 42, "name": "Jane Doe", "email": "jane@example.com", "is_active": true, "verified_at": null, "created_at": "2026-07-19T10:00:00.000000Z"}, "otp_expires_in_minutes": 5, "otp": "123456"}, "errors": null}
     * @response 422 scenario="Validation failed" {"success": false, "message": "The given data was invalid.", "errors": {"identifier": ["The identifier field is required."], "password": ["The password field is required."]}, "data": null}
     * @response 422 scenario="Identifier does not match any configured kind" {"success": false, "message": "The given data was invalid.", "errors": {"identifier": ["The identifier must be a valid email or phone."]}, "data": null}
     * @response 500 scenario="API user role missing (RoleSeeder not run)" {"success": false, "message": "User role not found for api guard.", "errors": null, "data": null}
     */
    public function register(Request $request)
    {
        $identifiers = AuthIdentity::identifiers();

        $baseRules = [
            'policy_agreed' => 'required|accepted',
            'name' => 'required|string|max:255',
            'identifier' => 'required|string|max:255',
            'type' => ['required', Rule::in($identifiers)],
            'password' => ['required', 'string', 'max:255', 'confirmed', Password::defaults()],
        ];

        // Username field — when enabled, required at register and usable for login.
        // Format: must start with a letter and contain letters/digits/underscores/dashes only.
        // Excludes email (`@`) and phone (digits-only / `+`) formats.
        if (AuthIdentity::hasField('username')) {
            $baseRules['username'] = ['required', 'string', 'max:255', 'min:3', 'regex:/^[A-Za-z][A-Za-z0-9_-]*$/', $this->uniqueApiUsernameRule()];
        }

        // Other non-identifier extras (email/phone) keep their own keys, optional.
        foreach (['email', 'phone'] as $field) {
            if (! AuthIdentity::isIdentifier($field) && AuthIdentity::hasField($field)) {
                $baseRules[$field] = match ($field) {
                    'email' => AuthIdentity::emailRule(false),
                    'phone' => AuthIdentity::phoneRule(false),
                };
            }
        }

        $validator = Validator::make($request->all(), $baseRules);

        if ($validator->fails()) {
            return ApiResponse::error(Trans::get('api.validation_failed'), $validator->errors()->toArray(), 422);
        }

        // The client declares which identifier it is sending — never inferred from the
        // string's shape.
        $detectedField = $request->type;

        if (! $detectedField || ! in_array($detectedField, $identifiers, true)) {
            return ApiResponse::error(
                Trans::get('api.validation_failed'),
                ['type' => [Trans::get('api.invalid_identifier')]],
                422,
            );
        }

        $identifierRule = match ($detectedField) {
            'email' => AuthIdentity::emailRule(true),
            'phone' => AuthIdentity::phoneRule(true),
        };

        // Validate (and uniqueness-check) the canonical value: `966…` and `+966…` are the
        // same account, so checking the raw string would let a duplicate slip through.
        $identifierValidator = Validator::make(
            [$detectedField => AuthIdentity::normalize($request->identifier, $detectedField) ?? $request->identifier],
            [$detectedField => $identifierRule],
        );

        if ($identifierValidator->fails()) {
            return ApiResponse::error(
                Trans::get('api.validation_failed'),
                ['identifier' => $identifierValidator->errors()->get($detectedField)],
                422,
            );
        }

        $role = Role::where('name', 'user')->where('guard_name', 'api')->first();
        if (! $role) {
            return ApiResponse::error(Trans::get('api.user_role_not_found'), null, 500);
        }

        $userData = [
            'name' => $request->name,
            'password' => Hash::make($request->password),
            $detectedField => AuthIdentity::normalize($request->identifier, $detectedField),
        ];

        if (AuthIdentity::hasField('username')) {
            $userData['username'] = $request->username;
        }

        // Other non-identifier extras (email/phone) when supplied.
        foreach (['email', 'phone'] as $field) {
            if (! AuthIdentity::isIdentifier($field) && AuthIdentity::hasField($field) && $request->$field) {
                $userData[$field] = AuthIdentity::normalize($request->$field, $field);
            }
        }

        // Promote any existing guest row tied to this device IN PLACE — keeps
        // the same `users.id` so anything FK-attached to the guest (cart,
        // favorites, etc.) carries over to the registered account.
        //
        // One transaction: a half-registered account — a row with no role, or a token
        // with no device — is unusable and unrecoverable by the client, which would
        // just retry into a "already taken" error.
        $user = DB::transaction(fn (): User => $this->promoteGuestOrCreate($request, $userData, $role));

        // Always. An account is usable only once its identifier is proven, so there is no
        // branch here and no flag to get wrong. The client continues with login +
        // verify-otp — the same pair an identifier *change* goes through.
        $otp = $this->sendOtpToUser($user, 'verify');

        $responseData = [
            'user' => $user->fresh(),
            'otp_expires_in_minutes' => 5,
        ];

        // Testing mode already generates a predictable code; handing it back is what lets
        // a tester finish the flow with no mailbox.
        if (config('app.is_testing')) {
            $responseData['otp'] = $otp ? $otp->otp : null;
        }

        return ApiResponse::success($responseData, Trans::get('api.user_registered'));
    }

    /**
     * Resend Verification OTP
     *
     * Resend the `verify` OTP to the authenticated (but unverified) user's delivery channel.
     *
     * @group Authentication
     *
     * @response 200 scenario="Success" {"success": true, "message": "Verification code sent successfully.", "data": {"user": {"id": 42, "name": "Jane Doe", "email": "jane@example.com", "verified_at": null}, "otp_expires_in_minutes": 5}, "errors": null}
     * @response 404 scenario="No user resolved (missing/invalid Bearer)" {"success": false, "message": "User not found.", "errors": null, "data": null}
     * @response 400 scenario="Account already verified" {"success": false, "message": "Account is already verified.", "errors": null, "data": null}
     * @response 400 scenario="No deliverable channel (username-only account)" {"success": false, "message": "OTP not available for this identifier type.", "errors": null, "data": null}
     */
    public function sendOtp(Request $request)
    {
        $user = $request->user();

        if (! $user) {
            return ApiResponse::error(Trans::get('api.user_not_found'), null, 404);
        }

        if ($user->verified_at) {
            return ApiResponse::error(Trans::get('api.already_verified'), null, 400);
        }

        $otp = $this->sendOtpToUser($user, 'verify');

        if (! $otp) {
            return ApiResponse::error(Trans::get('api.otp_not_available'), null, 400);
        }

        $responseData = [
            'user' => $user->fresh(),
            'otp_expires_in_minutes' => 5,
        ];

        if (config('app.is_testing')) {
            $responseData['otp'] = $otp->otp;
        }

        return ApiResponse::success($responseData, Trans::get('api.otp_sent'));
    }

    /**
     * Login
     *
     * Behavior branches on AUTH_MODE (see GET /api/config → auth_mode):
     * - `password` (default): body is `identifier` + `password` (+ optional `remember_me`). Issues a Sanctum
     *   token immediately. An unverified account still gets a token (usable with verify-otp) plus a fresh
     *   verify OTP.
     * - `otp`: body is `identifier` only (+ optional `name` for first-time users). Auto-creates the user if
     *   missing (promoting any existing guest tied to the same X-Device-Id), sends a `login` OTP, and does
     *   NOT issue a token — client follows with POST /api/verify-login.
     *
     * Under AUTH_MODE=otp, only `identifier` (+ optional `name` string for first-time users) apply —
     * `password`/`remember_me` are ignored.
     *
     * @group Authentication
     *
     * @bodyParam identifier string required Email, phone, or username. A phone MUST carry its country code (`966500000000` or `+966500000000`); it is stored in E.164. Example: jane@example.com
     * @bodyParam type string required Which identifier is being sent — `email`, `phone`, or `username` (never inferred from the value). Example: email
     * @bodyParam password string required Example: SecurePass123!
     * @bodyParam remember_me boolean Extends the issued token's lifetime to 30 days. Example: false
     *
     * @response 200 scenario="password mode — verified" {"success": true, "message": "Login successful.", "token": "1|abcdef123456", "data": {"user": {"id": 42, "name": "Jane Doe", "email": "jane@example.com", "verified_at": "2026-07-18T09:00:00.000000Z"}, "is_verified": true, "account_restored": false, "token_id": 7, "token": "1|abcdef123456"}, "errors": null}
     * @response 200 scenario="password mode — unverified (fresh OTP sent)" {"success": true, "message": "Account not verified. Please check your email.", "token": "1|abcdef123456", "data": {"user": {"id": 42, "name": "Jane Doe", "verified_at": null}, "is_verified": false, "token_id": 7, "otp_expires_in_minutes": 5, "token": "1|abcdef123456"}, "errors": null}
     * @response 200 scenario="otp mode — OTP sent, no token yet" {"success": true, "message": "Login code sent. Enter the code to complete sign in.", "data": {"identifier": "jane@example.com", "channel": "email", "otp_expires_in_minutes": 5}, "errors": null}
     * @response 422 scenario="missing required fields" {"success": false, "message": "The identifier field is required. (and 1 more error)", "errors": {"identifier": ["The identifier field is required."], "password": ["The password field is required."]}, "data": null}
     * @response 422 scenario="password mode — user not found" {"success": false, "message": "User not found.", "errors": {"identifier": ["User not found."]}, "data": null}
     * @response 422 scenario="password mode — invalid password" {"success": false, "message": "Invalid credentials.", "errors": {"password": ["Invalid credentials."]}, "data": null}
     * @response 422 scenario="otp mode — identifier is not a valid email/phone" {"success": false, "message": "The identifier must be a valid email or phone.", "errors": {"identifier": ["The identifier must be a valid email or phone."]}, "data": null}
     * @response 403 scenario="account suspended (admin-trashed)" {"success": false, "message": "Your account is suspended. Please contact support.", "errors": null, "data": null}
     * @response 403 scenario="account inactive" {"success": false, "message": "Your account is inactive.", "errors": null, "data": null}
     * @response 403 scenario="user role missing/wrong guard" {"success": false, "message": "Unauthorized access.", "errors": null, "data": null}
     */
    public function login(Request $request)
    {
        if ($this->isOtpMode()) {
            return $this->loginViaOtp($request);
        }

        $rules = [
            'identifier' => 'required|string|max:255',
            'type' => ['required', Rule::in(AuthIdentity::types())],
            'password' => 'required|string|max:255',
            'remember_me' => 'boolean',
        ];

        $request->validate($rules);

        $user = $this->findUserByIdentifier($request->identifier, $request->type, withTrashed: true);

        if (! $user) {
            return ApiResponse::error(
                Trans::get('api.user_not_found'),
                ['identifier' => [Trans::get('api.user_not_found')]],
                422,
            );
        }

        if ($user->trashed()) {
            return ApiResponse::error(Trans::get('api.account_suspended'), null, 403);
        }

        if (! $user->is_active) {
            return ApiResponse::error(__('admin.account_is_inactive'), null, 403);
        }

        if (! Hash::check($request->password, $user->password)) {
            return ApiResponse::error(
                Trans::get('api.invalid_credentials'),
                ['password' => [Trans::get('api.invalid_credentials')]],
                422,
            );
        }

        if (! $user->hasRole('user', 'api')) {
            return ApiResponse::error(Trans::get('api.unauthorized_access'), null, 403);
        }

        $accountRestored = false;
        if ($user->isPendingDeletion()) {
            $user->restoreAccount();
            $accountRestored = true;
        }

        if ($user->verified_at === null) {
            [$token, $tokenId] = $this->issueToken($user, $request);

            // Reuse a recent verify OTP (< 60s old) to avoid spamming SMS/email when
            // the client calls login immediately after register.
            $otp = $user->otps()
                ->where('type', 'verify')
                ->where('created_at', '>', now()->subSeconds(60))
                ->latest()
                ->first()
                ?? $this->sendOtpToUser($user, 'verify');

            $responseData = [
                'user' => $user->fresh(),
                'is_verified' => false,
                'token_id' => $tokenId,
                'otp_expires_in_minutes' => 5,
            ];

            if (config('app.is_testing')) {
                $responseData['otp'] = $otp ? $otp->otp : null;
            }

            return ApiResponse::success($responseData, Trans::get('api.user_not_verified'), $token);
        }

        [$token, $tokenId] = $this->issueToken(
            $user,
            $request,
            $request->remember_me ? self::TOKEN_DAYS_REMEMBERED : self::TOKEN_DAYS,
        );

        return ApiResponse::success([
            'user' => $user->fresh(),
            'is_verified' => true,
            'account_restored' => $accountRestored,
            'token_id' => $tokenId,
        ], Trans::get($accountRestored ? 'api.account_restored' : 'api.login_successful'), $token);
    }

    /**
     * Get App Config
     *
     * Live app configuration for mobile/web clients to adapt their UI on boot. Always available (not
     * gated on APP_USERS). Public, no auth required beyond the standard device/API-token headers.
     *
     * @group Authentication
     *
     * `fcm_topics` lists the topics this install broadcasts on, per language: `name` is what
     * to pass to Firebase, `base` is the audience (`guests`, `users`, or whatever
     * `FCM_TOPICS` holds) and `lang` its language code. Subscribe to the variant whose base
     * matches the device's state and whose lang matches its current language, and
     * re-subscribe when either changes. Bare bases are not published — a device has one
     * language at a time — except on an install with no active languages, where the bases
     * are the only topics there are and `lang` is null.
     *
     * @response 200 scenario="Success" {"success": true, "message": "Operation successful", "data": {"identifiers": ["email"], "has_username_field": false, "has_email_field": false, "has_phone_field": false, "social_providers": ["google.com", "apple.com"], "max_social_accounts": 0, "social_auth_available": true, "is_otp_whatsapp": false, "multi_session": true, "app_users": true, "app_guests": true, "auth_mode": "otp", "allowed_email_domains": "all", "allowed_phone_countries": "all", "fcm_topics": [{"name": "guests_en", "base": "guests", "lang": "en"}, {"name": "guests_ar", "base": "guests", "lang": "ar"}, {"name": "users_en", "base": "users", "lang": "en"}, {"name": "users_ar", "base": "users", "lang": "ar"}]}, "errors": null}
     */
    public function config()
    {
        $identifiers = AuthIdentity::identifiers();

        return ApiResponse::success([
            'identifiers' => $identifiers,
            'has_username_field' => config('auth.fields.username'),
            'has_email_field' => config('auth.fields.email'),
            'has_phone_field' => config('auth.fields.phone'),
            'social_providers' => array_values(array_filter(array_map('trim', explode(',', (string) config('auth.social_providers'))))),
            'max_social_accounts' => (int) config('auth.social_max_accounts'),
            'social_auth_available' => in_array('email', $identifiers, true) && Firebase::available(),
            'is_otp_whatsapp' => config('auth.otp_whatsapp'),
            'multi_session' => (bool) config('auth.multi_session_enabled'),
            'app_users' => config('features.app_users'),
            'app_guests' => config('features.app_guests'),
            'auth_mode' => $this->isOtpMode() ? 'otp' : 'password',
            'allowed_email_domains' => $this->parseAllowedList(config('auth.allowed_email_domains')),
            'allowed_phone_countries' => $this->parseAllowedList(config('auth.allowed_phone_countries')),
            // Which FCM topics this install broadcasts on, so a client can subscribe to the
            // right ones instead of hardcoding names that only exist in one project. Each
            // entry carries its `base` (`guests`/`users`, or whatever FCM_TOPICS lists) and
            // its `lang`. Only the per-language variants are published: a device has one
            // language at a time, and one subscribed to both `users` and `users_ar` would
            // get every broadcast twice. The CMS's `all` choice is not here either — it is a
            // picker option, not a topic. Subscribe to the variant of each base that matches
            // the device's state and current language, and re-subscribe when either changes;
            // a send to a base fans out to every variant, so nothing is missed.
            'fcm_topics' => FcmTopics::published(),
        ]);
    }

    /**
     * Normalize an ALLOWED_* env value: the literal string "all" (or empty) means
     * unrestricted; otherwise a comma-separated list becomes a trimmed array.
     *
     * @return string|array<int, string>
     */
    private function parseAllowedList(?string $value): string|array
    {
        $value = trim((string) $value);

        if ($value === '' || strtolower($value) === 'all') {
            return 'all';
        }

        return array_values(array_filter(array_map('trim', explode(',', $value))));
    }

    private function isOtpMode(): bool
    {
        return config('auth.mode') === 'otp';
    }

    /**
     * OTP-mode login: accept identifier only, optionally `name` for first-time
     * users. Auto-create when no row matches (promotes any existing guest with
     * the same X-Device-Id in place). Send a `login` OTP to the user's channel
     * and return without issuing a token. Client follows with `/api/verify-login`.
     */
    private function loginViaOtp(Request $request)
    {
        $request->validate([
            'identifier' => 'required|string|max:255',
            'type' => ['required', Rule::in(AuthIdentity::types())],
            'name' => 'nullable|string|max:255',
        ]);

        $identifiers = AuthIdentity::identifiers();
        $kind = $request->type;

        if (! $kind || ! in_array($kind, $identifiers, true)) {
            return ApiResponse::error(
                Trans::get('api.invalid_identifier'),
                ['identifier' => [Trans::get('api.invalid_identifier')]],
                422,
            );
        }

        // OTP-mode login is both register + sign-in. Skip uniqueness (existing
        // identifiers MUST pass — they belong to the returning user). Apply only
        // shape/format + optional domain/country guards.
        $identifierRule = AuthIdentity::otpLoginRule($kind);
        $identifierValidator = Validator::make([$kind => $request->identifier], [$kind => $identifierRule]);
        if ($identifierValidator->fails()) {
            return ApiResponse::error(
                Trans::get('api.validation_failed'),
                ['identifier' => $identifierValidator->errors()->get($kind)],
                422,
            );
        }

        // Store the canonical form (lowercased email, E.164 phone) — the lookups all
        // normalise, so a raw `213…` row would never be found again by `+213…`.
        $identifier = AuthIdentity::normalize($request->identifier, $kind);

        $user = $this->findUserByIdentifier($identifier, $kind, withTrashed: true);

        if ($user && $user->trashed()) {
            return ApiResponse::error(Trans::get('api.account_suspended'), null, 403);
        }
        if ($user && ! $user->is_active) {
            return ApiResponse::error(__('admin.account_is_inactive'), null, 403);
        }

        if (! $user) {
            $role = Role::where('name', 'user')->where('guard_name', 'api')->first();
            if (! $role) {
                return ApiResponse::error(Trans::get('api.user_role_not_found'), null, 500);
            }
            $userData = [
                'name' => $request->input('name') ?: 'User',
                $kind => $identifier,
                'is_active' => true,
            ];
            $user = $this->promoteGuestOrCreate($request, $userData, $role);
        }

        $otp = $this->sendOtpToUser($user, 'login');

        $responseData = [
            'identifier' => $identifier,
            'channel' => $kind,
            'otp_expires_in_minutes' => 5,
        ];
        if (config('app.is_testing')) {
            $responseData['otp'] = $otp ? $otp->otp : null;
        }

        return ApiResponse::success($responseData, Trans::get('api.login_otp_sent'));
    }

    /**
     * OTP-mode verify-login: consume the `login` OTP, stamp `verified_at`,
     * issue a Sanctum token, and track the device. Reviewer accounts auto-pass
     * any OTP value (consistent with verifyOtp).
     */
    /**
     * Verify Login OTP
     *
     * OTP-mode only: consume the `login` OTP sent by POST /api/login and issue a Sanctum token.
     * Reviewer accounts auto-pass any OTP value.
     *
     * @group Authentication
     *
     * @bodyParam identifier string required A phone MUST carry its country code (`966500000000` or `+966500000000`); it is stored in E.164. Example: jane@example.com
     * @bodyParam type string required Which identifier is being sent — `email`, `phone`, or `username` (never inferred from the value). Example: email
     * @bodyParam otp string required Example: 482913
     *
     * @response 200 scenario="Success" {"success": true, "message": "Login successful.", "token": "1|abcdef123456", "data": {"user": {"id": 42, "name": "Jane Doe", "verified_at": "2026-07-19T10:00:00.000000Z"}, "is_verified": true, "account_restored": false, "token_id": 7, "token": "1|abcdef123456"}, "errors": null}
     * @response 404 scenario="Wrong auth mode (route logically disabled)" {"success": false, "message": "Endpoint not available in the current auth mode.", "errors": null, "data": null}
     * @response 422 scenario="User not found" {"success": false, "message": "User not found.", "errors": {"identifier": ["User not found."]}, "data": null}
     * @response 422 scenario="Invalid or expired OTP (also returned once the code has been guessed wrong 5 times, which destroys it)" {"success": false, "message": "Invalid or expired verification code.", "errors": {"otp": ["Invalid or expired verification code."]}, "data": null}
     * @response 403 scenario="Account suspended/inactive/wrong role" {"success": false, "message": "Your account is suspended. Please contact support.", "errors": null, "data": null}
     */
    public function verifyLogin(Request $request)
    {
        if (! $this->isOtpMode()) {
            return ApiResponse::error(Trans::get('api.endpoint_not_available'), null, 404);
        }

        $request->validate([
            'identifier' => 'required|string|max:255',
            'type' => ['required', Rule::in(AuthIdentity::types())],
            'otp' => 'required|string|max:10',
        ]);

        $user = $this->findUserByIdentifier($request->identifier, $request->type, withTrashed: true);
        if (! $user) {
            return ApiResponse::error(
                Trans::get('api.user_not_found'),
                ['identifier' => [Trans::get('api.user_not_found')]],
                422,
            );
        }
        if ($user->trashed()) {
            return ApiResponse::error(Trans::get('api.account_suspended'), null, 403);
        }
        if (! $user->is_active) {
            return ApiResponse::error(__('admin.account_is_inactive'), null, 403);
        }
        if (! $user->hasRole('user', 'api')) {
            return ApiResponse::error(Trans::get('api.unauthorized_access'), null, 403);
        }

        if (! $user->is_reviewer) {
            $otpRecord = Otp::attempt($user, $request->otp, 'login');

            if (! $otpRecord) {
                return $this->invalidOtp();
            }

            $otpRecord->delete();
        }

        if (! $user->verified_at) {
            $user->forceFill(['verified_at' => now()])->save();
        }

        $accountRestored = false;
        if ($user->isPendingDeletion()) {
            $user->restoreAccount();
            $accountRestored = true;
        }

        [$token, $tokenId] = $this->issueToken($user, $request, self::TOKEN_DAYS_REMEMBERED);

        return ApiResponse::success([
            'user' => $user->fresh(),
            'is_verified' => true,
            'account_restored' => $accountRestored,
            'token_id' => $tokenId,
        ], Trans::get($accountRestored ? 'api.account_restored' : 'api.login_successful'), $token);
    }

    /**
     * Explicit guest creation. Idempotent — returns existing guest when
     * the device's X-Device-Id already maps to one. 403 when the device is
     * claimed by a registered user. 403 when `APP_GUESTS=false`.
     */
    /**
     * Create Guest Session
     *
     * Explicit guest creation. Idempotent — returns the existing guest if X-Device-Id already matches one.
     *
     * @group Devices & Guests
     * Manage guest sessions and the authenticated user's registered devices/sessions.
     *
     * @response 200 scenario="Success" {"success": true, "message": "Guest created.", "data": {"user": {"id": 99, "name": "Guest", "is_guest": true, "guest_id": "11111111-1111-4111-8111-111111111111", "platform": "web", "is_active": true}}, "errors": null}
     * @response 403 scenario="Guests disabled (APP_GUESTS=false)" {"success": false, "message": "Guest mode is disabled.", "errors": null, "data": null}
     * @response 403 scenario="Device already claimed by a registered user" {"success": false, "message": "This endpoint is only available for guests.", "errors": {"auth": ["This endpoint is only available for guests."]}, "data": null}
     */
    public function createGuest(Request $request)
    {
        if (! config('features.app_guests')) {
            return ApiResponse::error(Trans::get('api.guests_disabled'), null, 403);
        }

        $deviceId = trim((string) $request->header('X-Device-Id'));
        $platform = strtolower(trim((string) $request->header('X-Platform')));

        if ($request->attributes->get('device_claimed')) {
            return ApiResponse::error(Trans::get('api.guest_only_route'), ['auth' => [Trans::get('api.guest_only_route')]], 403);
        }

        if ($request->user() && ! $request->user()->is_guest) {
            return ApiResponse::error(Trans::get('api.guest_only_route'), ['auth' => [Trans::get('api.guest_only_route')]], 403);
        }

        $user = User::findOrCreateGuest($platform, $deviceId);

        return ApiResponse::success(['user' => $user->fresh()], Trans::get('api.guest_created'));
    }

    /**
     * Check Identifier
     *
     * Pre-submit uniqueness/state check for an email/phone/username value — used before register,
     * update-profile (username), request-identifier-change, and to pick a forgot-password `type`.
     *
     * @group Authentication
     *
     * @bodyParam identifier string required Email, phone, or username value to look up. A phone MUST carry its country code (`966500000000` or `+966500000000`); it is stored in E.164. Example: jane@example.com
     * @bodyParam type string required Which identifier is being sent — `email`, `phone`, or `username` (never inferred from the value). Example: email
     *
     * @response 200 scenario="Match found" {"success": true, "message": "Operation successful", "data": {"exists": true, "pending_deletion": false, "suspended": false, "available_channels": ["email"], "has_password": true, "social_providers": ["google.com"], "verified": true, "is_guest": false}, "errors": null}
     * @response 200 scenario="No match" {"success": true, "message": "Operation successful", "data": {"exists": false, "pending_deletion": false, "suspended": false, "available_channels": [], "has_password": false, "social_providers": [], "verified": false, "is_guest": false}, "errors": null}
     * @response 422 scenario="Missing identifier" {"success": false, "message": "The identifier field is required.", "errors": {"identifier": ["The identifier field is required."]}, "data": null}
     */
    public function checkIdentifier(Request $request)
    {
        $request->validate([
            'identifier' => 'required|string|max:255',
            'type' => ['required', 'in:email,phone,username'],
        ]);

        $kind = $request->type;

        // Resolve the column to query based on detected kind. Supports identifier columns
        // AND non-identifier extras (HAS_*_FIELD) for pre-submit uniqueness checks
        // (e.g. before request-identifier-change or update-profile username change).
        $column = null;
        if ($kind === 'email' && AuthIdentity::hasField('email')) {
            $column = 'email';
        } elseif ($kind === 'phone' && AuthIdentity::hasField('phone')) {
            $column = 'phone';
        } elseif ($kind === 'username' && config('auth.fields.username')) {
            $column = 'username';
        }

        $value = $column ? AuthIdentity::normalize((string) $request->identifier, $column) : null;

        // A malformed value can't exist, but say so explicitly: this endpoint runs before
        // submit, so the client can show "include your country code" inline instead of
        // reporting the number as simply available.
        if ($column === 'phone' && $value === null) {
            return ApiResponse::error(
                Trans::get('api.validation_failed'),
                ['identifier' => [Trans::get('api.phone_country_code_required')]],
                422,
            );
        }

        if ($value === null) {
            $column = null;
        }

        $emptyResponse = [
            'exists' => false,
            'pending_deletion' => false,
            'suspended' => false,
            'available_channels' => [],
            'has_password' => false,
            'social_providers' => [],
            'verified' => false,
            'is_guest' => false,
        ];

        if (! $column) {
            return ApiResponse::success($emptyResponse);
        }

        if ($column === 'email') {
            $value = strtolower($value);
        }

        $user = User::withTrashed()->where($column, $value)
            ->whereHas('roles', fn ($q) => $q->where('name', 'user')->where('guard_name', 'api'))
            ->with('socialAccounts:user_id,provider')
            ->first();

        if (! $user) {
            return ApiResponse::success($emptyResponse);
        }

        // Tell the client which OTP delivery channels are populated on the user record.
        // Used by the frontend to pick a `type` for forgot-password.
        $channels = [];
        if ($user->email) {
            $channels[] = 'email';
        }
        if ($user->phone) {
            $channels[] = 'phone';
        }

        return ApiResponse::success([
            'exists' => true,
            'pending_deletion' => $user->isPendingDeletion(),
            'suspended' => $user->trashed(),
            'available_channels' => $channels,
            'has_password' => $user->password !== null,
            'social_providers' => $user->socialAccounts->pluck('provider')->values()->all(),
            'verified' => $user->verified_at !== null,
            'is_guest' => (bool) $user->is_guest,
        ]);
    }

    /**
     * Logout
     *
     * Revoke the current Sanctum token. Requires auth:sanctum.
     *
     * @group Authentication
     *
     * @response 200 scenario="Success" {"success": true, "message": "Logout successful.", "data": null, "errors": null}
     */
    public function logout(Request $request)
    {
        $request->user()->currentAccessToken()->delete();

        return ApiResponse::success(null, Trans::get('api.logout_successful'));
    }

    /**
     * List the authenticated user's active devices. The current device is
     * flagged so the client can highlight it / disable its revoke button.
     */
    /**
     * List Devices
     *
     * List the authenticated user's active devices/sessions (only meaningful when MULTI_SESSION_ENABLED=true —
     * otherwise there is always exactly one).
     *
     * @group Devices & Guests
     *
     * @response 200 scenario="Success" {"success": true, "message": "Operation successful", "data": {"devices": [{"id": 3, "device_name": null, "platform": "ios", "ip": "10.0.0.1", "user_agent": "MyApp/1.0", "last_seen_at": "2026-07-19T09:00:00.000000Z", "created_at": "2026-07-10T08:00:00.000000Z", "is_current": true}, {"id": 2, "device_name": null, "platform": "android", "ip": "10.0.0.2", "user_agent": "MyApp/1.0", "last_seen_at": "2026-07-15T09:00:00.000000Z", "created_at": "2026-07-01T08:00:00.000000Z", "is_current": false}]}, "errors": null}
     */
    public function devices(Request $request)
    {
        $currentTokenId = $request->user()->currentAccessToken()?->id;

        $devices = $request->user()->devices()
            ->orderByDesc('last_seen_at')
            ->get()
            ->map(fn ($d) => [
                'id' => $d->id,
                'device_name' => $d->device_name,
                'platform' => $d->platform,
                'ip' => $d->ip,
                'user_agent' => $d->user_agent,
                'last_seen_at' => $d->last_seen_at,
                'created_at' => $d->created_at,
                'is_current' => $d->personal_access_token_id === $currentTokenId,
            ]);

        return ApiResponse::success(['devices' => $devices]);
    }

    /**
     * Revoke a specific device. Deletes the underlying Sanctum token (FK
     * cascade drops the device row) and broadcasts `device.revoked` so the
     * kicked client clears its local creds.
     */
    /**
     * Revoke Device
     *
     * Revoke a specific device by id (from GET /api/devices). Deletes its Sanctum token (FK cascade drops
     * the device row) and broadcasts `device.revoked` on private-user.{userId} so that client clears its
     * local credentials.
     *
     * @group Devices & Guests
     *
     * @urlParam deviceId integer required The device row id (from GET /api/devices). Example: 3
     *
     * @response 200 scenario="Success" {"success": true, "message": "Device signed out.", "data": null, "errors": null}
     * @response 404 scenario="Device id not found for this user" {"success": false, "message": "Resource not found.", "errors": null, "data": null}
     */
    public function revokeDevice(Request $request, int $deviceId)
    {
        $device = $request->user()->devices()->findOrFail($deviceId);
        $tokenId = $device->personal_access_token_id;

        $request->user()->tokens()->where('id', $tokenId)->delete();

        Broadcaster::safe(new DeviceRevoked($request->user()->id, (int) $tokenId));

        return ApiResponse::success(null, Trans::get('api.device_revoked'));
    }

    /**
     * Verify OTP
     *
     * Consume a `verify` OTP (sent by register or send-otp) and stamp the account verified.
     * Reviewer accounts bypass the OTP check entirely.
     *
     * @group Authentication
     *
     * @bodyParam otp string required Example: 482913
     *
     * @response 200 scenario="Success" {"success": true, "message": "Code verified successfully.", "data": {"user": {"id": 42, "name": "Jane Doe", "verified_at": "2026-07-19T10:00:00.000000Z"}}, "errors": null}
     * @response 422 scenario="Invalid or expired OTP (also returned once the code has been guessed wrong 5 times, which destroys it)" {"success": false, "message": "Invalid or expired verification code.", "errors": {"otp": ["Invalid or expired verification code."]}, "data": null}
     */
    public function verifyOtp(Request $request)
    {
        $request->validate([
            'otp' => 'required|string|max:10',
        ]);

        $user = $request->user();

        // Reviewer bypass — any OTP value accepted, just stamp verified.
        if ($user->is_reviewer) {
            if (! $user->verified_at) {
                $user->verified_at = now();
                $user->save();
            }

            return ApiResponse::success(['user' => $user->fresh()], Trans::get('api.otp_verified'));
        }

        $otpRecord = Otp::attempt($user, $request->otp, 'verify');

        if (! $otpRecord) {
            return $this->invalidOtp();
        }

        $user->verified_at = now();
        $user->save();

        $otpRecord->delete();

        return ApiResponse::success(['user' => $user->fresh()], Trans::get('api.otp_verified'));
    }

    /**
     * Forgot Password
     *
     * Send a `reset_password` OTP. Only meaningful under AUTH_MODE=password (route disappears under
     * AUTH_MODE=otp). Channel is auto-picked (`email` > `phone` priority) unless `type` is explicitly passed.
     *
     * @group Authentication
     *
     * @bodyParam identifier string required A phone MUST carry its country code (`966500000000` or `+966500000000`); it is stored in E.164. Example: jane@example.com
     * @bodyParam type string required Which identifier is being sent — `email`, `phone`, or `username` (never inferred from the value). Example: email
     * @bodyParam channel string Where to send the code — `email` or `phone`; must be one of the user's populated channels. Auto-picked (email > phone) if omitted. Example: email
     *
     * @response 200 scenario="Success" {"success": true, "message": "Password reset code sent.", "data": {"identifier": "jane@example.com", "channel": "email", "otp_expires_in_minutes": 5}, "errors": null}
     * @response 422 scenario="User not found" {"success": false, "message": "User not found.", "errors": {"identifier": ["User not found."]}, "data": null}
     * @response 422 scenario="Requested `type` channel not populated on this user" {"success": false, "message": "OTP not available for this identifier type.", "errors": {"type": ["OTP not available for this identifier type."]}, "data": null}
     * @response 403 scenario="Account inactive" {"success": false, "message": "Your account is inactive.", "errors": null, "data": null}
     */
    public function forgotPassword(Request $request)
    {
        $request->validate([
            'identifier' => 'required|string|max:255',
            'type' => ['required', Rule::in(AuthIdentity::types())],
            'channel' => 'nullable|in:email,phone',
        ]);

        $user = $this->findUserByIdentifier($request->identifier, $request->type);

        if (! $user) {
            return ApiResponse::error(
                Trans::get('api.user_not_found'),
                ['identifier' => [Trans::get('api.user_not_found')]],
                422,
            );
        }

        if (! $user->is_active) {
            return ApiResponse::error(__('admin.account_is_inactive'), null, 403);
        }

        // Which contact the code goes to — a separate question from `type` (what the
        // user typed to identify themselves). A user found by phone can still ask for
        // the code by email if the account carries one.
        // - If client passed `channel`, use it (must be populated on the user).
        // - Else default priority: email > phone.
        $available = array_values(array_filter([
            $user->email ? 'email' : null,
            $user->phone ? 'phone' : null,
        ]));

        if ($request->channel) {
            if (! in_array($request->channel, $available, true)) {
                return ApiResponse::error(
                    Trans::get('api.otp_not_available'),
                    ['channel' => [Trans::get('api.otp_not_available')]],
                    422,
                );
            }
            $channel = $request->channel;
        } else {
            $channel = $available[0] ?? null;
        }

        if (! $channel) {
            return ApiResponse::error(
                Trans::get('api.otp_not_available'),
                ['identifier' => [Trans::get('api.otp_not_available')]],
                422,
            );
        }

        $otp = $this->sendOtpToUser($user, 'reset_password', $channel);

        if (! $otp) {
            return ApiResponse::error(
                Trans::get('api.otp_not_available'),
                ['identifier' => [Trans::get('api.otp_not_available')]],
                422,
            );
        }

        $responseData = [
            'identifier' => $user->{$channel},
            'channel' => $channel,
            'otp_expires_in_minutes' => 5,
        ];

        if (config('app.is_testing')) {
            $responseData['otp'] = $otp->otp;
        }

        return ApiResponse::success($responseData, Trans::get('api.forgot_password_otp_sent'));
    }

    /**
     * Verify Forgot-Password OTP
     *
     * Verify a `reset_password` OTP (from forgot-password) WITHOUT consuming it — the OTP is echoed back
     * so the client can pass it again to POST /api/change-forgot-password, which performs the actual reset.
     *
     * @group Authentication
     *
     * @bodyParam identifier string required A phone MUST carry its country code (`966500000000` or `+966500000000`); it is stored in E.164. Example: jane@example.com
     * @bodyParam type string required Which identifier is being sent — `email`, `phone`, or `username` (never inferred from the value). Example: email
     * @bodyParam otp string required Example: 482913
     *
     * @response 200 scenario="Success" {"success": true, "message": "Code verified successfully.", "data": {"identifier": "jane@example.com", "otp": "482913"}, "errors": null}
     * @response 422 scenario="User not found" {"success": false, "message": "User not found.", "errors": {"identifier": ["User not found."]}, "data": null}
     * @response 422 scenario="Invalid or expired OTP (also returned once the code has been guessed wrong 5 times, which destroys it)" {"success": false, "message": "Invalid or expired verification code.", "errors": {"otp": ["Invalid or expired verification code."]}, "data": null}
     * @response 403 scenario="Account inactive" {"success": false, "message": "Your account is inactive.", "errors": null, "data": null}
     */
    public function verifyForgotPasswordOtp(Request $request)
    {
        $request->validate([
            'identifier' => 'required|string|max:255',
            'type' => ['required', Rule::in(AuthIdentity::types())],
            'otp' => 'required|string|max:10',
        ]);

        $user = $this->findUserByIdentifier($request->identifier, $request->type);

        if (! $user) {
            return ApiResponse::error(
                Trans::get('api.user_not_found'),
                ['identifier' => [Trans::get('api.user_not_found')]],
                422,
            );
        }

        if (! $user->is_active) {
            return ApiResponse::error(__('admin.account_is_inactive'), null, 403);
        }

        $otpRecord = Otp::attempt($user, $request->otp, 'reset_password');

        if (! $otpRecord) {
            return $this->invalidOtp();
        }

        return ApiResponse::success([
            'identifier' => $this->getOtpIdentifierValue($user),
            'otp' => $request->otp,
        ], Trans::get('api.otp_verified'));
    }

    /**
     * Reset Password
     *
     * Complete a password reset: re-verifies the `reset_password` OTP and sets the new password.
     * Revokes all of the user's existing tokens.
     *
     * @group Authentication
     *
     * @bodyParam identifier string required A phone MUST carry its country code (`966500000000` or `+966500000000`); it is stored in E.164. Example: jane@example.com
     * @bodyParam type string required Which identifier is being sent — `email`, `phone`, or `username` (never inferred from the value). Example: email
     * @bodyParam otp string required Example: 482913
     * @bodyParam password string required Minimum 8 characters. Example: NewSecurePass456!
     * @bodyParam password_confirmation string required Must match `password`. Example: NewSecurePass456!
     *
     * @response 200 scenario="Success" {"success": true, "message": "Password changed successfully.", "data": null, "errors": null}
     * @response 422 scenario="User not found" {"success": false, "message": "User not found.", "errors": {"identifier": ["User not found."]}, "data": null}
     * @response 422 scenario="Invalid or expired OTP (also returned once the code has been guessed wrong 5 times, which destroys it)" {"success": false, "message": "Invalid or expired verification code.", "errors": {"otp": ["Invalid or expired verification code."]}, "data": null}
     * @response 403 scenario="Account inactive" {"success": false, "message": "Your account is inactive.", "errors": null, "data": null}
     */
    public function changeForgotPassword(Request $request)
    {
        $request->validate([
            'identifier' => 'required|string|max:255',
            'type' => ['required', Rule::in(AuthIdentity::types())],
            'otp' => 'required|string|max:10',
            'password' => ['required', 'string', 'max:255', 'confirmed', Password::defaults()],
        ]);

        $user = $this->findUserByIdentifier($request->identifier, $request->type);

        if (! $user) {
            return ApiResponse::error(
                Trans::get('api.user_not_found'),
                ['identifier' => [Trans::get('api.user_not_found')]],
                422,
            );
        }

        if (! $user->is_active) {
            return ApiResponse::error(__('admin.account_is_inactive'), null, 403);
        }

        $otpRecord = Otp::attempt($user, $request->otp, 'reset_password');

        if (! $otpRecord) {
            return $this->invalidOtp();
        }

        // Revoking sessions, setting the password and consuming the code have to
        // land together: a partial write here either leaves the old sessions
        // alive against a new password, or burns the code without changing it.
        DB::transaction(function () use ($user, $request, $otpRecord): void {
            $user->tokens()->delete();
            $user->password = Hash::make($request->password);
            $user->save();

            $otpRecord->delete();
        });

        return ApiResponse::success(null, Trans::get('api.password_changed_successfully'));
    }

    /**
     * Change Password
     *
     * Change the authenticated user's password. `old_password` is required unless the account has no
     * password yet (social-only account setting its first password). Revokes every OTHER token (keeps
     * the current session alive).
     *
     * @group Authentication
     *
     * @bodyParam old_password string Required unless the account has no password yet (social-only account setting its first password). Example: OldPass123!
     * @bodyParam password string required Minimum 8 characters. Example: NewSecurePass456!
     * @bodyParam password_confirmation string required Must match `password`. Example: NewSecurePass456!
     *
     * @response 200 scenario="Success — had a password" {"success": true, "message": "Password changed successfully.", "data": null, "errors": null}
     * @response 200 scenario="Success — first password (social-only account)" {"success": true, "message": "Password set successfully.", "data": null, "errors": null}
     * @response 422 scenario="Wrong current password" {"success": false, "message": "Old password is incorrect.", "errors": {"old_password": ["Old password is incorrect."]}, "data": null}
     */
    public function changePassword(Request $request)
    {
        $user = $request->user();
        $hasPassword = $user->password !== null;

        $request->validate([
            // Social-only accounts (no password set yet) can call this endpoint
            // without `old_password` to set their initial password.
            'old_password' => $hasPassword ? 'required|string' : 'nullable|string',
            'password' => ['required', 'string', 'max:255', 'confirmed', Password::defaults()],
        ]);

        if ($hasPassword && ! Hash::check($request->old_password, $user->password)) {
            return ApiResponse::error(
                Trans::get('api.invalid_old_password'),
                ['old_password' => [Trans::get('api.invalid_old_password')]],
                422,
            );
        }

        $currentTokenId = $user->currentAccessToken()->id;

        DB::transaction(function () use ($user, $request, $currentTokenId): void {
            $user->password = Hash::make($request->password);
            $user->save();

            $user->tokens()->where('id', '!=', $currentTokenId)->delete();
        });

        return ApiResponse::success(null, Trans::get(
            $hasPassword ? 'api.password_changed_successfully' : 'api.password_set_successfully'
        ));
    }

    /**
     * Delete Account
     *
     * Delete the authenticated account. Guests are force-deleted immediately (no retention). Real users
     * are soft-marked (`account_deleted_at`) and restorable by logging back in within
     * ACCOUNT_DELETION_RETENTION_DAYS (default 30) before the purge middleware force-deletes the row.
     * All tokens are revoked. Real users must be verified.
     *
     * @group Profile & Account
     * View and update the authenticated user's profile, identifiers, and account lifecycle.
     *
     * @response 200 scenario="Success" {"success": true, "message": "Account deleted successfully.", "data": null, "errors": null}
     * @response 404 scenario="No user resolved" {"success": false, "message": "User not found.", "errors": null, "data": null}
     * @response 403 scenario="Real user not yet verified" {"success": false, "message": "Account not verified. Please check your email.", "errors": null, "data": null}
     */
    public function deleteAccount(Request $request)
    {
        $user = $request->user();

        if (! $user) {
            return ApiResponse::error(Trans::get('api.user_not_found'), null, 404);
        }

        // Guests get force-deleted (no soft-delete retention — anonymous row,
        // nothing to recover). Cascade drops their user_devices row, freeing
        // the device_id for a fresh guest on next hit. Identified by the
        // X-Device-Id header via IdentifyDevice middleware.
        if ($user->is_guest) {
            $user->forceDelete();

            return ApiResponse::success(null, Trans::get('api.account_deleted_successfully'));
        }

        // Real users still need verification + valid Bearer to delete.
        if (! $request->bearerToken() || ! $user->verified_at) {
            return ApiResponse::error(Trans::get('api.user_not_verified'), null, 403);
        }

        $user->tokens()->delete();
        $user->markAccountDeleted();

        return ApiResponse::success(null, Trans::get('api.account_deleted_successfully'));
    }

    /**
     * Request Identifier Change
     *
     * Start an email/phone identifier change: sends an OTP to `new_identifier`. Rate-limited via
     * throttle:otp (3/5min). Blocked while the account is pending self-deletion or admin-trashed.
     * Email changes on password-less accounts with linked social providers are blocked until a
     * password is set first (email change wipes social links).
     *
     * @group Profile & Account
     *
     * @bodyParam new_identifier string required Email or phone, depending on AUTH_IDENTIFIERS config. A phone MUST carry its country code (`966500000000` or `+966500000000`); it is stored in E.164. Example: newemail@example.com
     * @bodyParam type string required Which identifier is being sent — `email` or `phone` (never inferred from the value). Example: email
     *
     * @response 200 scenario="Success" {"success": true, "message": "Verification code sent to your new identifier.", "data": {"new_identifier": "new@example.com", "otp_expires_in_minutes": 5}, "errors": null}
     * @response 422 scenario="new_identifier is not a valid/configured kind" {"success": false, "message": "The identifier must be a valid email or phone.", "errors": {"new_identifier": ["The identifier must be a valid email or phone."]}, "data": null}
     * @response 422 scenario="new_identifier already taken" {"success": false, "message": "The given data was invalid.", "errors": {"new_identifier": ["The email has already been taken."]}, "data": null}
     * @response 422 scenario="Password required before email change (social-only account)" {"success": false, "message": "Set a password before changing your email — your linked social accounts will be unlinked.", "errors": {"new_identifier": ["Set a password before changing your email — your linked social accounts will be unlinked."]}, "data": null}
     * @response 403 scenario="Account pending deletion or suspended" {"success": false, "message": "Your account is in a frozen state and cannot be edited. Please log in to restore it or contact support.", "errors": {"new_identifier": ["Your account is in a frozen state and cannot be edited. Please log in to restore it or contact support."]}, "data": null}
     */
    public function requestIdentifierChange(Request $request)
    {
        $identifiers = AuthIdentity::identifiers();

        $request->validate([
            'new_identifier' => 'required|string|max:255',
            'type' => ['required', Rule::in($identifiers)],
        ]);

        $newIdentifier = trim($request->new_identifier);
        $kind = $request->type;

        if (! $kind || ! in_array($kind, $identifiers, true)) {
            return ApiResponse::error(
                Trans::get('api.invalid_identifier'),
                ['type' => [Trans::get('api.invalid_identifier')]],
                422,
            );
        }

        // Stored (and matched) in canonical form, so the OTP row and the eventual write
        // both carry the same string the lookup will later use.
        $normalized = AuthIdentity::normalize($newIdentifier, $kind);

        if ($normalized === null) {
            return ApiResponse::error(
                Trans::get('api.validation_failed'),
                ['new_identifier' => [Trans::get($kind === 'phone' ? 'api.phone_country_code_required' : 'api.invalid_identifier')]],
                422,
            );
        }

        $newIdentifier = $normalized;

        $user = $request->user();

        // Block identifier changes on accounts that are pending self-deletion
        // or admin-trashed — the row is in a frozen state, no field edits.
        if ($user->isPendingDeletion() || $user->trashed()) {
            return ApiResponse::error(
                Trans::get('api.account_in_frozen_state'),
                ['new_identifier' => [Trans::get('api.account_in_frozen_state')]],
                403,
            );
        }

        // Email change wipes social accounts (linked under the old email).
        // If the user has no password set, they would be locked out — force
        // them to set a password before changing email. Phone change does
        // not touch social accounts so it's always allowed.
        if ($kind === 'email' && $user->password === null && $user->socialAccounts()->exists()) {
            return ApiResponse::error(
                Trans::get('api.set_password_before_email_change'),
                ['new_identifier' => [Trans::get('api.set_password_before_email_change')]],
                422,
            );
        }

        $rule = match ($kind) {
            'email' => AuthIdentity::emailRule(true, $user->id),
            'phone' => AuthIdentity::phoneRule(true, $user->id),
        };

        $validator = Validator::make([$kind => $newIdentifier], [$kind => $rule]);

        if ($validator->fails()) {
            return ApiResponse::error(
                Trans::get('api.validation_failed'),
                ['new_identifier' => $validator->errors()->get($kind)],
                422,
            );
        }

        // Replace the pending code atomically — a delete that commits without its
        // replacement leaves the user with no code and no way to ask for another until
        // the OTP throttle window clears.
        $otp = DB::transaction(function () use ($user, $newIdentifier): Otp {
            $user->otps()->where('type', 'change_identifier')->delete();

            return Otp::create([
                'user_id' => $user->id,
                'type' => 'change_identifier',
                'identifier' => $newIdentifier,
                'otp' => Otp::generate(),
                'expires_at' => now()->addMinutes(5),
            ]);
        });

        if ($kind === 'email') {
            EmailHelper::send(
                $newIdentifier,
                Trans::get('api.otp_subject_change_identifier'),
                'emails.otp',
                ['otp' => $otp->otp, 'name' => $user->name]
            );
        } elseif ($kind === 'phone') {
            if (config('auth.otp_whatsapp')) {
                SendWhatsapp::send($newIdentifier, 'Your OTP is: '.$otp->otp);
            } else {
                SendSMS::send($newIdentifier, 'Your OTP is: '.$otp->otp);
            }
        }

        $responseData = [
            'new_identifier' => $newIdentifier,
            'otp_expires_in_minutes' => 5,
        ];

        if (config('app.is_testing')) {
            $responseData['otp'] = $otp->otp;
        }

        return ApiResponse::success($responseData, Trans::get('api.identifier_change_otp_sent'));
    }

    /**
     * Verify Identifier Change
     *
     * Confirm the OTP from request-identifier-change and apply the new email/phone. An email change
     * wipes all linked social accounts (they were authorized against the old email) and best-effort
     * revokes their Firebase refresh tokens.
     *
     * @group Profile & Account
     *
     * @bodyParam new_identifier string required Email or phone, depending on AUTH_IDENTIFIERS config. A phone MUST carry its country code (`966500000000` or `+966500000000`); it is stored in E.164. Example: newemail@example.com
     * @bodyParam type string required Which identifier is being sent — `email` or `phone` (never inferred from the value). Example: email
     * @bodyParam otp string required Example: 482913
     *
     * @response 200 scenario="Success — phone change" {"success": true, "message": "Identifier changed successfully.", "data": {"user": {"id": 42, "phone": "+15551234567"}, "unlinked_providers": []}, "errors": null}
     * @response 200 scenario="Success — email change (social accounts unlinked)" {"success": true, "message": "Identifier changed successfully.", "data": {"user": {"id": 42, "email": "new@example.com"}, "unlinked_providers": ["google.com"]}, "errors": null}
     * @response 422 scenario="Invalid or expired OTP (also returned once the code has been guessed wrong 5 times, which destroys it)" {"success": false, "message": "Invalid or expired verification code.", "errors": {"otp": ["Invalid or expired verification code."]}, "data": null}
     * @response 403 scenario="Account pending deletion or suspended" {"success": false, "message": "Your account is in a frozen state and cannot be edited. Please log in to restore it or contact support.", "errors": {"new_identifier": ["Your account is in a frozen state and cannot be edited. Please log in to restore it or contact support."]}, "data": null}
     */
    public function verifyIdentifierChange(Request $request)
    {
        $identifiers = AuthIdentity::identifiers();

        $request->validate([
            'new_identifier' => 'required|string|max:255',
            'type' => ['required', Rule::in($identifiers)],
            'otp' => 'required|string|max:10',
        ]);

        $newIdentifier = trim($request->new_identifier);
        $kind = $request->type;

        if (! $kind || ! in_array($kind, $identifiers, true)) {
            return ApiResponse::error(
                Trans::get('api.invalid_identifier'),
                ['type' => [Trans::get('api.invalid_identifier')]],
                422,
            );
        }

        // Stored (and matched) in canonical form, so the OTP row and the eventual write
        // both carry the same string the lookup will later use.
        $normalized = AuthIdentity::normalize($newIdentifier, $kind);

        if ($normalized === null) {
            return ApiResponse::error(
                Trans::get('api.validation_failed'),
                ['new_identifier' => [Trans::get($kind === 'phone' ? 'api.phone_country_code_required' : 'api.invalid_identifier')]],
                422,
            );
        }

        $newIdentifier = $normalized;

        $user = $request->user();

        if (! $user->is_active) {
            return ApiResponse::error(__('admin.account_is_inactive'), null, 403);
        }

        if ($user->isPendingDeletion() || $user->trashed()) {
            return ApiResponse::error(
                Trans::get('api.account_in_frozen_state'),
                ['new_identifier' => [Trans::get('api.account_in_frozen_state')]],
                403,
            );
        }

        if ($kind === 'email' && $user->password === null && $user->socialAccounts()->exists()) {
            return ApiResponse::error(
                Trans::get('api.set_password_before_email_change'),
                ['new_identifier' => [Trans::get('api.set_password_before_email_change')]],
                422,
            );
        }

        $otpRecord = Otp::attempt($user, $request->otp, 'change_identifier', $newIdentifier);

        if (! $otpRecord) {
            return $this->invalidOtp();
        }

        // Email identifier change invalidates every linked social provider —
        // the social accounts were authorized against the old email. Wipe them
        // so the user re-links explicitly with the new email. That has to land in
        // the same transaction as the email write: a committed new email with the
        // old provider links still attached is exactly the takeover this prevents.
        $rows = $kind === 'email'
            ? $user->socialAccounts()->get(['provider', 'provider_id'])
            : collect();

        DB::transaction(function () use ($user, $kind, $newIdentifier, $otpRecord, $rows): void {
            $user->{$kind} = $newIdentifier;
            $user->save();

            $otpRecord->delete();

            if ($rows->isNotEmpty()) {
                $user->socialAccounts()->delete();
            }
        });

        // Revoke the Firebase refresh tokens so the old social tokens can't be used by an
        // in-flight session to silently re-link before the user notices. After the commit:
        // a remote call must not hold a transaction open, and a failure here is survivable.
        $unlinkedProviders = $rows->pluck('provider')->all();

        foreach ($rows as $row) {
            try {
                Firebase::auth()?->revokeRefreshTokens($row->provider_id);
            } catch (\Throwable $e) {
                report($e); // non-fatal — local row already deleted
            }
        }

        return ApiResponse::success([
            'user' => $user->fresh()->load('socialAccounts'),
            'unlinked_providers' => $unlinkedProviders,
        ], Trans::get('api.identifier_changed_successfully'));
    }

    /**
     * Update Profile
     *
     * Update the authenticated user's profile. `name` always editable. `username` editable when
     * HAS_USERNAME_FIELD=true (username is never an identifier). `email`/`phone` editable here ONLY
     * when NOT configured as an identifier (HAS_EMAIL_FIELD/HAS_PHONE_FIELD extras) — otherwise use
     * request-identifier-change instead.
     *
     * @group Profile & Account
     *
     * @bodyParam name string The user's display name. Example: Jane A. Doe
     * @bodyParam username string Only when HAS_USERNAME_FIELD=true. Example: janedoe
     * @bodyParam email string Only when HAS_EMAIL_FIELD=true and email is not configured as the identifier. Example: jane@example.com
     * @bodyParam phone string Only when HAS_PHONE_FIELD=true and phone is not configured as the identifier. Example: +15551234567
     *
     * @response 200 scenario="Success" {"success": true, "message": "Profile updated successfully.", "data": {"user": {"id": 42, "name": "Jane A. Doe", "username": "janedoe"}}, "errors": null}
     * @response 422 scenario="Validation failed" {"success": false, "message": "The username has already been taken.", "errors": {"username": ["The username has already been taken."]}, "data": null}
     */
    public function updateProfile(Request $request)
    {
        $user = $request->user();

        $rules = [
            'name' => 'nullable|string|max:255',
        ];

        // Username is always editable when enabled (never an identifier).
        // Same format rule as register — disallow email/phone-shaped values.
        if (AuthIdentity::hasField('username')) {
            $rules['username'] = ['nullable', 'string', 'max:255', 'min:3', 'regex:/^[A-Za-z][A-Za-z0-9_-]*$/', $this->uniqueApiUsernameRule($user->id)];
        }

        // Email/phone editable only when enabled as non-identifier extras (HAS_*_FIELD).
        // Identifier email/phone changes go through request-identifier-change.
        foreach (['email', 'phone'] as $field) {
            if (! AuthIdentity::isIdentifier($field) && AuthIdentity::hasField($field)) {
                $rules[$field] = match ($field) {
                    'email' => AuthIdentity::emailRule(false, $user->id),
                    'phone' => AuthIdentity::phoneRule(false, $user->id),
                };
            }
        }

        // Normalize before validating: `unique:users,phone` has to compare the E.164 form
        // that is actually stored, otherwise the same number in another notation passes.
        if ($request->filled('phone')) {
            $request->merge(['phone' => PhoneNumber::normalize($request->input('phone')) ?? $request->input('phone')]);
        }

        $validated = $request->validate($rules);

        $user->fill(array_filter($validated, fn ($v) => $v !== null && $v !== ''));
        $user->save();

        return ApiResponse::success(['user' => $user->fresh()], Trans::get('api.profile_updated'));
    }

    // --- Private Helpers ---

    private function generateUniqueUsername(string $email): string
    {
        $base = preg_replace('/[^A-Za-z0-9_-]/', '_', explode('@', $email)[0]);
        $base = trim($base, '_-') ?: 'user';

        $candidate = $base;
        $i = 1;
        while (User::where('username', $candidate)->exists()) {
            $candidate = $base.'_'.$i;
            $i++;
        }

        return $candidate;
    }

    private function uniqueApiUsernameRule(?int $excludeId = null): Unique
    {
        // Username must be unique among api-guard users. Admin/web-guard users may share
        // the column without conflicting.
        $rule = Rule::unique('users', 'username')
            ->where(fn ($q) => $q->whereExists(fn ($s) => $s
                ->from('model_has_roles')
                ->whereColumn('model_has_roles.model_id', 'users.id')
                ->where('model_has_roles.model_type', User::class)
                ->whereExists(fn ($r) => $r
                    ->from('roles')
                    ->whereColumn('roles.id', 'model_has_roles.role_id')
                    ->where('roles.name', 'user')
                    ->where('roles.guard_name', 'api')
                )
            ));

        if ($excludeId) {
            $rule->ignore($excludeId);
        }

        return $rule;
    }

    /**
     * The single answer to every failed OTP check: wrong code, expired code, and a code
     * whose row burned through Otp::MAX_ATTEMPTS all read identically, so a caller
     * cannot use the response to tell how close a guess was or whether a code is live.
     */
    private function invalidOtp(): JsonResponse
    {
        return ApiResponse::error(
            Trans::get('api.invalid_otp'),
            ['otp' => [Trans::get('api.invalid_otp')]],
            422,
        );
    }

    /**
     * Issue a Sanctum token and register its device row as one unit — a token with no
     * `user_devices` row is invisible to the devices list and can never be revoked.
     *
     * @return array{0: string, 1: int} The plain-text token and its PersonalAccessToken id.
     */
    private function issueToken(User $user, Request $request, int $days = self::TOKEN_DAYS): array
    {
        return DB::transaction(function () use ($user, $request, $days): array {
            $token = $user->createToken('user_token', ['*'], now()->addDays($days))->plainTextToken;

            return [$token, $this->trackDevice($user, $token, $request)];
        });
    }

    /**
     * Records a `user_devices` row for the just-issued Sanctum token. When
     * single-session mode is on, revokes every other token belonging to the
     * user and broadcasts `device.revoked` so existing clients clear their
     * local creds.
     *
     * Returns the resolved PersonalAccessToken id so callers can echo it back
     * to the client (clients store it locally to detect kicks targeting them).
     */
    private function trackDevice(User $user, string $plainToken, Request $request): int
    {
        $accessToken = PersonalAccessToken::findToken($plainToken);

        if (! config('auth.multi_session_enabled')) {
            $siblings = $user->tokens()->where('id', '!=', $accessToken->id)->get();
            foreach ($siblings as $sibling) {
                Broadcaster::safe(new DeviceRevoked($user->id, (int) $sibling->id));
                $sibling->delete(); // FK cascade drops user_devices row
            }
        }

        $deviceId = trim((string) $request->header('X-Device-Id')) ?: null;
        $platform = strtolower(trim((string) $request->header('X-Platform'))) ?: $request->input('platform');
        $fcmToken = trim((string) $request->header('X-FCM-Token')) ?: null;

        // Updated, not inserted: `IdentifyDevice` already keeps a row for this phone, and
        // every sign-in used to add another one beside it carrying the same FCM token. A
        // customer who had signed in a dozen times got the same push a dozen times, one per
        // row, and the sessions list showed one handset over and over. The row now follows
        // the newest token for that device.
        $device = $user->devices()->updateOrCreate(
            ['device_id' => $deviceId],
            [
                'personal_access_token_id' => $accessToken->id,
                'fcm_token' => $fcmToken,
                'device_name' => $request->input('device_name'),
                'platform' => $platform,
                'ip' => $request->ip(),
                'user_agent' => substr((string) $request->userAgent(), 0, 512),
                'last_seen_at' => now(),
            ],
        );

        $device->claimFcmToken();

        return (int) $accessToken->id;
    }

    /**
     * Promote-in-place: if a guest user already exists for this device's
     * `X-Device-Id`, mutate that row into a real user. Same `users.id`,
     * so anything FK-linked to the guest (cart, favorites, etc.) survives
     * the registration. Falls back to a fresh `User::create` when no guest
     * is found for the device.
     *
     * @param  array<string, mixed>  $userData
     */
    private function promoteGuestOrCreate(Request $request, array $userData, Role $role): User
    {
        $deviceId = trim((string) $request->header('X-Device-Id'));
        $guest = $deviceId !== ''
            ? User::where('guest_id', $deviceId)->where('is_guest', true)->first()
            : null;

        if (! $guest) {
            $user = User::create($userData);
            $user->assignRole($role);

            return $user;
        }

        $guest->forceFill(array_merge($userData, [
            'is_guest' => false,
            'guest_id' => null,
            'is_active' => true,
        ]))->save();

        if (! $guest->hasRole($role)) {
            $guest->assignRole($role);
        }

        return $guest->refresh();
    }

    private function findUserByIdentifier(string $value, string $type, bool $withTrashed = false): ?User
    {
        // The client declares the column; the value is normalized the same way it was
        // stored (lowercased email, E.164 phone) so a lookup can only match one row.
        $column = in_array($type, AuthIdentity::types(), true) ? $type : null;
        $value = $column ? AuthIdentity::normalize($value, $column) : null;

        if (! $column || $value === null) {
            return null;
        }

        $query = $withTrashed ? User::withTrashed() : User::query();

        return $query->where($column, $value)
            ->whereHas('roles', fn ($q) => $q->where('name', 'user')->where('guard_name', 'api'))
            ->first();
    }

    private function getOtpIdentifierValue(User $user): ?string
    {
        $identifiers = AuthIdentity::identifiers();

        // Priority: email > phone (only deliverable channels).
        foreach (['email', 'phone'] as $field) {
            if (in_array($field, $identifiers) && $user->$field) {
                return $user->$field;
            }
        }

        return null;
    }

    private function sendOtpToUser(User $user, string $type = 'verify', ?string $forceChannel = null): ?Otp
    {
        // Reviewer bypass: stamp verified, never send a real OTP. Test
        // accounts for Apple / Google Play stores can't access real SMS or
        // email infra during review.
        if ($user->is_reviewer) {
            if ($type === 'verify' && ! $user->verified_at) {
                $user->forceFill(['verified_at' => now()])->save();
            }

            return null;
        }

        $identifiers = AuthIdentity::identifiers();

        // If a channel is forced (e.g. forgot-password with username + type chosen), use it.
        if ($forceChannel && in_array($forceChannel, ['email', 'phone'], true) && $user->{$forceChannel}) {
            $deliveryValue = $user->{$forceChannel};
        } else {
            $deliveryValue = $this->getOtpIdentifierValue($user);
        }

        if (! $deliveryValue) {
            return null;
        }

        $user->otps()->where('type', $type)->delete();

        $otp = $user->otps()->create([
            'identifier' => $deliveryValue,
            'otp' => Otp::generate(),
            'type' => $type,
            'expires_at' => now()->addMinutes(5),
        ]);

        $subject = match ($type) {
            'verify' => Trans::get('api.otp_subject_verify'),
            'reset_password' => Trans::get('api.otp_subject_reset'),
            default => Trans::get('api.otp_subject_verify'),
        };

        // Determine actual delivery channel:
        // - $forceChannel takes precedence when set.
        // - else priority: email (if identifier and populated) > phone (if identifier and populated).
        $useEmail = $forceChannel === 'email'
            || ($forceChannel === null && in_array('email', $identifiers) && $user->email);
        $usePhone = $forceChannel === 'phone'
            || ($forceChannel === null && ! $useEmail && in_array('phone', $identifiers) && $user->phone);

        if ($useEmail && $user->email) {
            EmailHelper::send($user->email, $subject, 'emails.otp', [
                'otp' => $otp->otp,
                'name' => $user->name,
            ]);
        } elseif ($usePhone && $user->phone) {
            if (config('auth.otp_whatsapp')) {
                SendWhatsapp::send($user->phone, 'Your OTP is: '.$otp->otp);
            } else {
                SendSMS::send($user->phone, 'Your OTP is: '.$otp->otp);
            }
        }
        // username-only: OTP is stored but not sent (available in testing mode via response)

        return $otp;
    }

    // --- Firebase Social Auth ---

    /**
     * Firebase Login
     *
     * Login or register via a Firebase ID token (Google/Apple/Facebook/etc). Requires email configured
     * as an identifier (AUTH_IDENTIFIERS). The token's `email_verified` claim must be true — an account
     * is matched by email, so an unverified provider email would be a takeover. New users auto-verify
     * and get the `user` role; existing password accounts block social login; social-only accounts link
     * the provider automatically.
     *
     * @group Social Login
     * Login and account linking via Firebase-verified social providers (Google, Apple, etc.).
     *
     * @bodyParam token string required Firebase ID token from the client SDK. Example: eyJhbGciOiJSUzI1NiIsInR5cCI6IkpXVCJ9...
     *
     * @response 200 scenario="Success — existing or new social user" {"success": true, "message": "Login successful.", "token": "1|abcdef123456", "data": {"user": {"id": 42, "name": "Jane Doe", "email": "jane@example.com"}, "is_verified": true, "token_id": 7, "is_new_user": false, "provider": "google.com", "provider_already_linked": true, "linked_providers": ["google.com"], "token": "1|abcdef123456"}, "errors": null}
     * @response 422 scenario="Invalid/expired Firebase token" {"success": false, "message": "Invalid or expired Firebase token.", "errors": {"token": ["Invalid or expired Firebase token."]}, "data": null}
     * @response 422 scenario="Token has no email claim" {"success": false, "message": "Email is required for social login.", "errors": {"token": ["Email is required for social login."]}, "data": null}
     * @response 422 scenario="Provider email is not verified (email_verified claim false)" {"success": false, "message": "Your social account email is not verified.", "errors": {"token": ["Your social account email is not verified."]}, "data": null}
     * @response 422 scenario="Provider not in SOCIAL_AUTH_PROVIDERS" {"success": false, "message": "This social provider is not allowed.", "errors": {"token": ["This social provider is not allowed."]}, "data": null}
     * @response 422 scenario="Email already registered with a password" {"success": false, "message": "Account already exists. Please login with your password.", "errors": {"token": ["Account already exists. Please login with your password."]}, "data": null}
     * @response 422 scenario="SOCIAL_AUTH_MAX_ACCOUNTS reached" {"success": false, "message": "Maximum number of social accounts reached.", "errors": {"token": ["Maximum number of social accounts reached."]}, "data": null}
     * @response 400 scenario="Social auth unavailable (email not an identifier)" {"success": false, "message": "Social authentication is not available. Email must be configured as a login identifier.", "errors": null, "data": null}
     * @response 403 scenario="Account inactive / wrong role" {"success": false, "message": "Your account is inactive.", "errors": null, "data": null}
     */
    public function firebaseLogin(Request $request)
    {
        // Firebase is optional: with no service-account JSON the rest of the API keeps
        // working, so this answers 503 instead of crashing the request.
        $firebaseAuth = Firebase::auth();

        if (! $firebaseAuth) {
            return ApiResponse::error(Trans::get('api.social_auth_unavailable'), null, 503);
        }

        // Social auth requires email as an identifier
        if (! AuthIdentity::isIdentifier('email')) {
            return ApiResponse::error(Trans::get('api.social_auth_requires_email'), null, 400);
        }

        $request->validate([
            'token' => 'required|string',
        ]);

        try {
            $verifiedToken = $firebaseAuth->verifyIdToken($request->token);

            $uid = $verifiedToken->claims()->get('sub');
            $email = $verifiedToken->claims()->get('email');
            $emailVerified = filter_var($verifiedToken->claims()->get('email_verified'), FILTER_VALIDATE_BOOLEAN);
            $name = $verifiedToken->claims()->get('name');
            $phone = $verifiedToken->claims()->get('phone_number');
            $firebaseData = $verifiedToken->claims()->get('firebase');
            $provider = $firebaseData['sign_in_provider'] ?? 'firebase';
        } catch (FailedToVerifyToken $e) {
            return ApiResponse::error(
                Trans::get('api.invalid_firebase_token'),
                ['token' => [Trans::get('api.invalid_firebase_token')]],
                422,
            );
        }

        if (! $email) {
            return ApiResponse::error(
                Trans::get('api.firebase_email_required'),
                ['token' => [Trans::get('api.firebase_email_required')]],
                422,
            );
        }

        // The email is the only thing tying a provider identity to an account here: an
        // unverified one is a string the caller typed, and accepting it hands them any
        // account carrying that address — or reserves a stranger's address on a new one.
        // Some providers (a custom SAML/OIDC tenant, an unconfirmed Firebase
        // email/password user) will happily mint tokens with email_verified false.
        if (! $emailVerified) {
            $message = Trans::getOr('api.social_email_not_verified', 'Your social account email is not verified.');

            return ApiResponse::error($message, ['token' => [$message]], 422);
        }

        // Check if provider is allowed
        if (! $this->isProviderAllowed($provider)) {
            return ApiResponse::error(
                Trans::get('api.social_provider_not_allowed'),
                ['token' => [Trans::get('api.social_provider_not_allowed')]],
                422,
            );
        }

        $role = Role::where('name', 'user')->where('guard_name', 'api')->first();
        if (! $role) {
            return ApiResponse::error(Trans::get('api.user_role_not_found'), null, 500);
        }

        $providerAlreadyLinked = false;

        // Find existing social account by provider_id
        $socialAccount = SocialAccount::where('provider', $provider)
            ->where('provider_id', $uid)
            ->first();

        if ($socialAccount) {
            // Social account already linked - login as that user
            $user = $socialAccount->user;
            $providerAlreadyLinked = true;

            if (! $user->is_active) {
                return ApiResponse::error(__('admin.account_is_inactive'), null, 403);
            }

            if (! $user->hasRole('user', 'api')) {
                return ApiResponse::error(Trans::get('api.unauthorized_access'), null, 403);
            }
        } else {
            // Check if email exists with password account
            $existingUser = User::where('email', $email)->first();

            if ($existingUser && $existingUser->password) {
                // Account exists with password - block social login
                return ApiResponse::error(
                    Trans::get('api.account_exists_use_password'),
                    ['token' => [Trans::get('api.account_exists_use_password')]],
                    422,
                );
            }

            if ($existingUser) {
                // Account exists but no password (social-only) - link this provider
                $user = $existingUser;

                // Check if user already has this provider linked
                if (! $user->hasSocialProvider($provider)) {
                    // Check max accounts limit
                    if (! $this->canLinkMoreSocialAccounts($user)) {
                        return ApiResponse::error(
                            Trans::get('api.social_max_accounts_reached'),
                            ['token' => [Trans::get('api.social_max_accounts_reached')]],
                            422,
                        );
                    }

                    try {
                        DB::transaction(function () use ($user, $provider, $uid, $email, $name) {
                            $user->socialAccounts()->create([
                                'provider' => $provider,
                                'provider_id' => $uid,
                                'email' => $email,
                                'name' => $name,
                            ]);
                        });
                    } catch (QueryException $e) {
                        if ($e->getCode() === '23000') {
                            $providerAlreadyLinked = true;
                        } else {
                            throw $e;
                        }
                    }
                } else {
                    $providerAlreadyLinked = true;
                }

                if (! $user->is_active) {
                    return ApiResponse::error(__('admin.account_is_inactive'), null, 403);
                }
            } else {
                // Create new user — promote in place if a guest exists for this device.
                $userData = [
                    'name' => $name ?? explode('@', $email)[0],
                    'email' => $email,
                    'phone' => $phone,
                    'is_active' => true,
                ];

                // Auto-generate a unique username from the email prefix when HAS_USERNAME_FIELD is on.
                if (AuthIdentity::hasField('username')) {
                    $userData['username'] = $this->generateUniqueUsername($email);
                }

                // One transaction: an account created without its social link has no way
                // back in — the provider is the only credential it has.
                $user = DB::transaction(function () use ($request, $userData, $role, $provider, $uid, $email, $name): User {
                    $user = $this->promoteGuestOrCreate($request, $userData, $role);
                    $user->verified_at = now(); // Firebase already verified the user
                    $user->save();

                    $user->socialAccounts()->create([
                        'provider' => $provider,
                        'provider_id' => $uid,
                        'email' => $email,
                        'name' => $name,
                    ]);

                    return $user;
                });
            }
        }

        // Ensure verified (social login = verified)
        if (! $user->verified_at) {
            $user->verified_at = now();
            $user->save();
        }

        [$token, $tokenId] = $this->issueToken($user, $request, self::TOKEN_DAYS_REMEMBERED);

        $fresh = $user->fresh()->load('socialAccounts');

        return ApiResponse::success([
            'user' => $fresh,
            'is_verified' => true,
            'token_id' => $tokenId,
            'is_new_user' => $user->wasRecentlyCreated,
            'provider' => $provider,
            'provider_already_linked' => $providerAlreadyLinked,
            'linked_providers' => $fresh->socialAccounts->pluck('provider')->all(),
        ], Trans::get($providerAlreadyLinked ? 'api.social_provider_already_linked' : 'api.login_successful'), $token);
    }

    /**
     * Link Social Account
     *
     * Link a Firebase social account to the authenticated (already logged in) user. Requires the
     * verified token's email to match the user's account email.
     *
     * @group Social Login
     *
     * @bodyParam token string required Firebase ID token from the client SDK. Example: eyJhbGciOiJSUzI1NiIsInR5cCI6IkpXVCJ9...
     *
     * @response 200 scenario="Success" {"success": true, "message": "Social account linked successfully.", "data": {"user": {"id": 42, "email": "jane@example.com"}}, "errors": null}
     * @response 422 scenario="Invalid/expired Firebase token" {"success": false, "message": "Invalid or expired Firebase token.", "errors": {"token": ["Invalid or expired Firebase token."]}, "data": null}
     * @response 422 scenario="Provider already linked to another user" {"success": false, "message": "This social account is already linked to another user.", "errors": {"token": ["This social account is already linked to another user."]}, "data": null}
     * @response 422 scenario="Provider already linked to you" {"success": false, "message": "This provider is already linked to your account. Logged in.", "errors": {"token": ["This provider is already linked to your account. Logged in."]}, "data": null}
     * @response 422 scenario="Token email does not match account email" {"success": false, "message": "Social account email does not match your account email.", "errors": {"token": ["Social account email does not match your account email."]}, "data": null}
     * @response 422 scenario="SOCIAL_AUTH_MAX_ACCOUNTS reached" {"success": false, "message": "Maximum number of social accounts reached.", "errors": {"token": ["Maximum number of social accounts reached."]}, "data": null}
     * @response 400 scenario="Social auth unavailable (email not an identifier)" {"success": false, "message": "Social authentication is not available. Email must be configured as a login identifier.", "errors": null, "data": null}
     */
    public function linkSocialAccount(Request $request)
    {
        // Firebase is optional: with no service-account JSON the rest of the API keeps
        // working, so this answers 503 instead of crashing the request.
        $firebaseAuth = Firebase::auth();

        if (! $firebaseAuth) {
            return ApiResponse::error(Trans::get('api.social_auth_unavailable'), null, 503);
        }

        // Social auth requires email as an identifier
        if (! AuthIdentity::isIdentifier('email')) {
            return ApiResponse::error(Trans::get('api.social_auth_requires_email'), null, 400);
        }

        $request->validate([
            'token' => 'required|string',
        ]);

        try {
            $verifiedToken = $firebaseAuth->verifyIdToken($request->token);

            $uid = $verifiedToken->claims()->get('sub');
            $email = $verifiedToken->claims()->get('email');
            $name = $verifiedToken->claims()->get('name');
            $firebaseData = $verifiedToken->claims()->get('firebase');
            $provider = $firebaseData['sign_in_provider'] ?? 'firebase';
        } catch (FailedToVerifyToken $e) {
            return ApiResponse::error(
                Trans::get('api.invalid_firebase_token'),
                ['token' => [Trans::get('api.invalid_firebase_token')]],
                422,
            );
        }

        // Check if provider is allowed
        if (! $this->isProviderAllowed($provider)) {
            return ApiResponse::error(
                Trans::get('api.social_provider_not_allowed'),
                ['token' => [Trans::get('api.social_provider_not_allowed')]],
                422,
            );
        }

        $user = $request->user();

        // Check if this social account is already linked to another user
        $existingLink = SocialAccount::where('provider', $provider)
            ->where('provider_id', $uid)
            ->where('user_id', '!=', $user->id)
            ->first();

        if ($existingLink) {
            return ApiResponse::error(
                Trans::get('api.social_account_already_linked'),
                ['token' => [Trans::get('api.social_account_already_linked')]],
                422,
            );
        }

        // Check if user already has this provider linked
        if ($user->hasSocialProvider($provider)) {
            return ApiResponse::error(
                Trans::get('api.social_provider_already_linked'),
                ['token' => [Trans::get('api.social_provider_already_linked')]],
                422,
            );
        }

        // Check if email matches (optional security check)
        if ($email && $email !== $user->email) {
            return ApiResponse::error(
                Trans::get('api.social_email_mismatch'),
                ['token' => [Trans::get('api.social_email_mismatch')]],
                422,
            );
        }

        // Check max accounts limit
        if (! $this->canLinkMoreSocialAccounts($user)) {
            return ApiResponse::error(
                Trans::get('api.social_max_accounts_reached'),
                ['token' => [Trans::get('api.social_max_accounts_reached')]],
                422,
            );
        }

        // Link the social account inside a transaction so concurrent calls
        // race-cleanly against the DB unique indexes (provider, provider_id) and
        // (user_id, provider). One winner, one 422.
        try {
            DB::transaction(function () use ($user, $provider, $uid, $email, $name) {
                $user->socialAccounts()->create([
                    'provider' => $provider,
                    'provider_id' => $uid,
                    'email' => $email,
                    'name' => $name,
                ]);
            });
        } catch (QueryException $e) {
            // 23000 = integrity constraint violation (duplicate unique index).
            if ($e->getCode() === '23000') {
                return ApiResponse::error(
                    Trans::get('api.social_account_already_linked'),
                    ['token' => [Trans::get('api.social_account_already_linked')]],
                    422,
                );
            }
            throw $e;
        }

        return ApiResponse::success([
            'user' => $user->fresh()->load('socialAccounts'),
        ], Trans::get('api.social_account_linked'));
    }

    /**
     * Unlink Social Account
     *
     * Unlink a social provider by name. Blocked if it's the user's last auth method (no password AND
     * no other linked provider) — set a password first.
     *
     * @group Social Login
     *
     * @bodyParam provider string required Example: google.com
     *
     * @response 200 scenario="Success" {"success": true, "message": "Social account unlinked successfully.", "data": {"user": {"id": 42, "email": "jane@example.com"}}, "errors": null}
     * @response 422 scenario="Provider not linked to this user" {"success": false, "message": "This social provider is not linked to your account.", "errors": {"provider": ["This social provider is not linked to your account."]}, "data": null}
     * @response 422 scenario="Would remove the user's last auth method" {"success": false, "message": "Cannot unlink social account. Please set a password first.", "errors": {"provider": ["Cannot unlink social account. Please set a password first."]}, "data": null}
     */
    public function unlinkSocialAccount(Request $request)
    {
        $request->validate([
            'provider' => 'required|string',
        ]);

        $user = $request->user();

        // Check if user has this provider linked
        $socialAccount = $user->socialAccounts()->where('provider', $request->provider)->first();

        if (! $socialAccount) {
            return ApiResponse::error(
                Trans::get('api.social_provider_not_linked'),
                ['provider' => [Trans::get('api.social_provider_not_linked')]],
                422,
            );
        }

        // Re-check conditions inside a transaction with row lock so a concurrent
        // unlink can't drop the second-to-last provider while we drop the last.
        try {
            DB::transaction(function () use ($user, $request) {
                $locked = $user->socialAccounts()
                    ->where('provider', $request->provider)
                    ->lockForUpdate()
                    ->first();

                if (! $locked) {
                    abort(422, 'race');
                }

                $otherCount = $user->socialAccounts()
                    ->where('provider', '!=', $request->provider)
                    ->lockForUpdate()
                    ->count();

                if (! $user->password && $otherCount === 0) {
                    abort(422, 'last');
                }

                $locked->delete();
            });
        } catch (\Throwable $e) {
            if ($e->getMessage() === 'last') {
                return ApiResponse::error(
                    Trans::get('api.cannot_unlink_social_only'),
                    ['provider' => [Trans::get('api.cannot_unlink_social_only')]],
                    422,
                );
            }
            if ($e->getMessage() === 'race') {
                return ApiResponse::error(
                    Trans::get('api.social_provider_not_linked'),
                    ['provider' => [Trans::get('api.social_provider_not_linked')]],
                    422,
                );
            }
            throw $e;
        }

        return ApiResponse::success([
            'user' => $user->fresh()->load('socialAccounts'),
        ], Trans::get('api.social_account_unlinked'));
    }

    /**
     * List Social Accounts
     *
     * List the authenticated user's linked social accounts plus the account/provider limits.
     *
     * @group Social Login
     *
     * @response 200 scenario="Success" {"success": true, "message": "Social accounts retrieved successfully.", "data": {"social_accounts": [{"id": 1, "provider": "google.com", "email": "jane@example.com", "name": "Jane Doe"}], "allowed_providers": ["google.com", "apple.com"], "max_accounts": 0, "can_link_more": true}, "errors": null}
     */
    public function getSocialAccounts(Request $request)
    {
        $user = $request->user();

        return ApiResponse::success([
            'social_accounts' => $user->socialAccounts,
            'allowed_providers' => $this->getAllowedProviders(),
            'max_accounts' => $this->getMaxSocialAccounts(),
            'can_link_more' => $this->canLinkMoreSocialAccounts($user),
        ], Trans::get('api.social_accounts_retrieved'));
    }

    // --- Social Auth Helpers ---

    private function getAllowedProviders(): array
    {
        $value = (string) config('auth.social_providers');

        if (empty($value)) {
            return [];
        }

        return array_map('trim', explode(',', $value));
    }

    private function isProviderAllowed(string $provider): bool
    {
        $allowed = $this->getAllowedProviders();

        // If no providers configured, allow all
        if (empty($allowed)) {
            return true;
        }

        return in_array($provider, $allowed);
    }

    private function getMaxSocialAccounts(): int
    {
        return (int) config('auth.social_max_accounts'); // 0 = unlimited
    }

    private function canLinkMoreSocialAccounts(User $user): bool
    {
        $max = $this->getMaxSocialAccounts();

        // 0 = unlimited
        if ($max === 0) {
            return true;
        }

        return $user->socialAccounts()->count() < $max;
    }
}
