<?php

namespace App\Http\Controllers\Api\AppUser;

use App\Events\DeviceRevoked;
use App\Helpers\ApiResponse;
use App\Helpers\Broadcaster;
use App\Helpers\EmailHelper;
use App\Helpers\SendSMS;
use App\Helpers\SendWhatsapp;
use App\Helpers\Trans;
use App\Http\Controllers\Controller;
use App\Models\Otp;
use App\Models\SocialAccount;
use App\Models\User;
use App\Rules\AllowedEmailDomain;
use App\Rules\AllowedPhoneCountry;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Unique;
use Kreait\Firebase\Contract\Auth as FirebaseAuth;
use Kreait\Firebase\Exception\Auth\FailedToVerifyToken;
use Laravel\Sanctum\PersonalAccessToken;
use Spatie\Permission\Models\Role;

class AppUserController extends Controller
{
    /**
     * Register
     *
     * Create a new mobile-app account. Only available when AUTH_MODE=password (route disappears entirely
     * under AUTH_MODE=otp — login covers registration there instead). Does NOT issue a token — call
     * POST /api/login afterwards to obtain one. When the resolved identifier kind is `username` (no OTP
     * channel), the account is auto-verified and no OTP is sent.
     *
     * @group Authentication
     *
     * @groupDescription Register, log in, and manage OTP-based verification and password resets for mobile-app users.
     *
     * @response 200 scenario="Registered — OTP sent (email/phone identifier)" {"success": true, "message": "User registered successfully.", "data": {"user": {"id": 42, "name": "Jane Doe", "email": "jane@example.com", "phone": null, "username": null, "is_active": true, "verified_at": null, "created_at": "2026-07-19T10:00:00.000000Z"}, "otp_expires_in_minutes": 5}, "errors": null}
     * @response 422 scenario="Validation failed" {"success": false, "message": "The given data was invalid.", "errors": {"identifier": ["The identifier field is required."], "password": ["The password field is required."]}, "data": null}
     * @response 422 scenario="Identifier does not match any configured kind" {"success": false, "message": "The given data was invalid.", "errors": {"identifier": ["The identifier must be a valid email or phone."]}, "data": null}
     * @response 500 scenario="API user role missing (RoleSeeder not run)" {"success": false, "message": "User role not found for api guard.", "errors": null, "data": null}
     */
    public function register(Request $request)
    {
        $identifiers = $this->getAuthIdentifiers();

        $baseRules = [
            'policy_agreed' => 'required|accepted',
            'name' => 'required|string|max:255',
            'identifier' => 'required|string|max:255',
            'password' => 'required|string|min:8|max:255|confirmed',
        ];

        // Username field — when enabled, required at register and usable for login.
        // Format: must start with a letter and contain letters/digits/underscores/dashes only.
        // Excludes email (`@`) and phone (digits-only / `+`) formats.
        if ($this->hasField('username')) {
            $baseRules['username'] = ['required', 'string', 'max:255', 'min:3', 'regex:/^[A-Za-z][A-Za-z0-9_-]*$/', $this->uniqueApiUsernameRule()];
        }

        // Other non-identifier extras (email/phone) keep their own keys, optional.
        foreach (['email', 'phone'] as $field) {
            if (! $this->isIdentifier($field) && $this->hasField($field)) {
                $baseRules[$field] = match ($field) {
                    'email' => $this->getEmailValidationRule(false),
                    'phone' => $this->getPhoneValidationRule(false),
                };
            }
        }

        $validator = Validator::make($request->all(), $baseRules);

        if ($validator->fails()) {
            return ApiResponse::error(Trans::get('api.validation_failed'), $validator->errors()->toArray(), 422);
        }

        // Resolve identifier kind. Identifiers are limited to email/phone now.
        if (count($identifiers) === 1) {
            $detectedField = $identifiers[0];
        } else {
            $detectedField = $this->detectIdentifierKind($request->identifier);

            if (! $detectedField || ! in_array($detectedField, $identifiers, true)) {
                return ApiResponse::error(
                    Trans::get('api.validation_failed'),
                    ['identifier' => [Trans::get('api.invalid_identifier')]],
                    422,
                );
            }
        }

        $identifierRule = match ($detectedField) {
            'email' => $this->getEmailValidationRule(true),
            'phone' => $this->getPhoneValidationRule(true),
        };

        $identifierValidator = Validator::make(
            [$detectedField => $request->identifier],
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
            $detectedField => $request->identifier,
        ];

        if ($this->hasField('username')) {
            $userData['username'] = $request->username;
        }

        // Other non-identifier extras (email/phone) when supplied.
        foreach (['email', 'phone'] as $field) {
            if (! $this->isIdentifier($field) && $this->hasField($field) && $request->$field) {
                $userData[$field] = $request->$field;
            }
        }

        // Promote any existing guest row tied to this device IN PLACE — keeps
        // the same `users.id` so anything FK-attached to the guest (cart,
        // favorites, etc.) carries over to the registered account.
        $user = $this->promoteGuestOrCreate($request, $userData, $role);

        $otp = $this->sendOtpToUser($user, 'verify');

        $responseData = [
            'user' => $user->fresh(),
            'otp_expires_in_minutes' => 5,
        ];

        if (filter_var(env('IS_TESTING'), FILTER_VALIDATE_BOOLEAN)) {
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
     * @response 400 scenario="No deliverable channel (username-only account)" {"success": false, "message": "OTP not available for this identifier type.", "errors": null, "data": null}
     */
    public function sendOtp(Request $request)
    {
        $user = $request->user();

        if (! $user) {
            return ApiResponse::error(Trans::get('api.user_not_found'), null, 404);
        }

        $otp = $this->sendOtpToUser($user, 'verify');

        if (! $otp) {
            return ApiResponse::error(Trans::get('api.otp_not_available'), null, 400);
        }

        $responseData = [
            'user' => $user->fresh(),
            'otp_expires_in_minutes' => 5,
        ];

        if (filter_var(env('IS_TESTING'), FILTER_VALIDATE_BOOLEAN)) {
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
     * @group Authentication
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
            'password' => 'required|string|max:255',
            'remember_me' => 'boolean',
        ];

        $request->validate($rules);

        $user = $this->findUserByIdentifier($request->identifier, withTrashed: true);

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
            $token = $user->createToken('user_token', ['*'], now()->addDays(1))->plainTextToken;
            $tokenId = $this->trackDevice($user, $token, $request);

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

            if (filter_var(env('IS_TESTING'), FILTER_VALIDATE_BOOLEAN)) {
                $responseData['otp'] = $otp ? $otp->otp : null;
            }

            return ApiResponse::success($responseData, Trans::get('api.user_not_verified'), $token);
        }

        $days = $request->remember_me ? 30 : 1;
        $token = $user->createToken('user_token', ['*'], now()->addDays($days))->plainTextToken;
        $tokenId = $this->trackDevice($user, $token, $request);

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
     * @response 200 scenario="Success" {"success": true, "message": "Operation successful", "data": {"identifiers": ["email"], "has_username_field": false, "has_email_field": false, "has_phone_field": false, "social_providers": ["google.com", "apple.com"], "max_social_accounts": 0, "social_auth_available": true, "is_otp_whatsapp": false, "multi_session": true, "app_users": true, "app_guests": true, "auth_mode": "otp", "allowed_email_domains": "all", "allowed_phone_countries": "all"}, "errors": null}
     */
    public function config()
    {
        $identifiers = $this->getAuthIdentifiers();

        return ApiResponse::success([
            'identifiers' => $identifiers,
            'has_username_field' => filter_var(env('HAS_USERNAME_FIELD', false), FILTER_VALIDATE_BOOLEAN),
            'has_email_field' => filter_var(env('HAS_EMAIL_FIELD', false), FILTER_VALIDATE_BOOLEAN),
            'has_phone_field' => filter_var(env('HAS_PHONE_FIELD', false), FILTER_VALIDATE_BOOLEAN),
            'social_providers' => array_values(array_filter(array_map('trim', explode(',', env('SOCIAL_AUTH_PROVIDERS', ''))))),
            'max_social_accounts' => (int) env('SOCIAL_AUTH_MAX_ACCOUNTS', 0),
            'social_auth_available' => in_array('email', $identifiers, true),
            'is_otp_whatsapp' => filter_var(env('IS_OTP_WHATSAPP', false), FILTER_VALIDATE_BOOLEAN),
            'multi_session' => (bool) config('auth.multi_session_enabled'),
            'app_users' => filter_var(env('APP_USERS', true), FILTER_VALIDATE_BOOLEAN),
            'app_guests' => filter_var(env('APP_GUESTS', true), FILTER_VALIDATE_BOOLEAN),
            'auth_mode' => $this->isOtpMode() ? 'otp' : 'password',
            'allowed_email_domains' => $this->parseAllowedList(env('ALLOWED_EMAIL_DOMAINS', 'all')),
            'allowed_phone_countries' => $this->parseAllowedList(env('ALLOWED_PHONE_COUNTRIES', 'all')),
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
        return strtolower((string) env('AUTH_MODE', 'password')) === 'otp';
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
            'name' => 'nullable|string|max:255',
        ]);

        $identifiers = $this->getAuthIdentifiers();
        $kind = $this->detectIdentifierKind($request->identifier);

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
        $identifierRule = $this->getOtpLoginIdentifierRule($kind);
        $identifierValidator = Validator::make([$kind => $request->identifier], [$kind => $identifierRule]);
        if ($identifierValidator->fails()) {
            return ApiResponse::error(
                Trans::get('api.validation_failed'),
                ['identifier' => $identifierValidator->errors()->get($kind)],
                422,
            );
        }

        $user = $this->findUserByIdentifier($request->identifier, withTrashed: true);

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
                $kind => $request->identifier,
                'is_active' => true,
            ];
            $user = $this->promoteGuestOrCreate($request, $userData, $role);
        }

        $otp = $this->sendOtpToUser($user, 'login');

        $responseData = [
            'identifier' => $request->identifier,
            'channel' => $kind,
            'otp_expires_in_minutes' => 5,
        ];
        if (filter_var(env('IS_TESTING'), FILTER_VALIDATE_BOOLEAN)) {
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
     * @response 200 scenario="Success" {"success": true, "message": "Login successful.", "token": "1|abcdef123456", "data": {"user": {"id": 42, "name": "Jane Doe", "verified_at": "2026-07-19T10:00:00.000000Z"}, "is_verified": true, "account_restored": false, "token_id": 7, "token": "1|abcdef123456"}, "errors": null}
     * @response 404 scenario="Wrong auth mode (route logically disabled)" {"success": false, "message": "Endpoint not available in the current auth mode.", "errors": null, "data": null}
     * @response 422 scenario="User not found" {"success": false, "message": "User not found.", "errors": {"identifier": ["User not found."]}, "data": null}
     * @response 422 scenario="Invalid or expired OTP" {"success": false, "message": "Invalid or expired verification code.", "errors": {"otp": ["Invalid or expired verification code."]}, "data": null}
     * @response 403 scenario="Account suspended/inactive/wrong role" {"success": false, "message": "Your account is suspended. Please contact support.", "errors": null, "data": null}
     */
    public function verifyLogin(Request $request)
    {
        if (! $this->isOtpMode()) {
            return ApiResponse::error(Trans::get('api.endpoint_not_available'), null, 404);
        }

        $request->validate([
            'identifier' => 'required|string|max:255',
            'otp' => 'required|string|max:10',
        ]);

        $user = $this->findUserByIdentifier($request->identifier, withTrashed: true);
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
            $otpRecord = $user->otps()
                ->where('otp', $request->otp)
                ->where('type', 'login')
                ->where('expires_at', '>', now())
                ->first();

            if (! $otpRecord) {
                return ApiResponse::error(
                    Trans::get('api.invalid_otp'),
                    ['otp' => [Trans::get('api.invalid_otp')]],
                    422,
                );
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

        $token = $user->createToken('user_token', ['*'], now()->addDays(30))->plainTextToken;
        $tokenId = $this->trackDevice($user, $token, $request);

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
     *
     * @groupDescription Manage guest sessions and the authenticated user's registered devices/sessions.
     *
     * @response 200 scenario="Success" {"success": true, "message": "Guest created.", "data": {"user": {"id": 99, "name": "Guest", "is_guest": true, "guest_id": "11111111-1111-4111-8111-111111111111", "platform": "web", "is_active": true}}, "errors": null}
     * @response 403 scenario="Guests disabled (APP_GUESTS=false)" {"success": false, "message": "Guest mode is disabled.", "errors": null, "data": null}
     * @response 403 scenario="Device already claimed by a registered user" {"success": false, "message": "This endpoint is only available for guests.", "errors": {"auth": ["This endpoint is only available for guests."]}, "data": null}
     */
    public function createGuest(Request $request)
    {
        if (! filter_var(env('APP_GUESTS', true), FILTER_VALIDATE_BOOLEAN)) {
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
     * @response 200 scenario="Match found" {"success": true, "message": "Operation successful", "data": {"exists": true, "pending_deletion": false, "suspended": false, "available_channels": ["email"], "has_password": true, "social_providers": ["google.com"], "verified": true, "is_guest": false}, "errors": null}
     * @response 200 scenario="No match" {"success": true, "message": "Operation successful", "data": {"exists": false, "pending_deletion": false, "suspended": false, "available_channels": [], "has_password": false, "social_providers": [], "verified": false, "is_guest": false}, "errors": null}
     * @response 422 scenario="Missing identifier" {"success": false, "message": "The identifier field is required.", "errors": {"identifier": ["The identifier field is required."]}, "data": null}
     */
    public function checkIdentifier(Request $request)
    {
        $request->validate([
            'identifier' => 'required|string|max:255',
        ]);

        $value = trim($request->identifier);
        $kind = $this->detectIdentifierKind($value);

        // Resolve the column to query based on detected kind. Supports identifier columns
        // AND non-identifier extras (HAS_*_FIELD) for pre-submit uniqueness checks
        // (e.g. before request-identifier-change or update-profile username change).
        $column = null;
        if ($kind === 'email' && $this->hasField('email')) {
            $column = 'email';
        } elseif ($kind === 'phone' && $this->hasField('phone')) {
            $column = 'phone';
        } elseif ($kind === null && filter_var(env('HAS_USERNAME_FIELD', false), FILTER_VALIDATE_BOOLEAN)) {
            $column = 'username';
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
     * @response 200 scenario="Success" {"success": true, "message": "Device signed out.", "data": null, "errors": null}
     * @response 404 scenario="Device id not found for this user (findOrFail — Laravel default shape, NOT the ApiResponse envelope)" {"message": "No query results for model [App\\Models\\UserDevice] 999"}
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
     * @response 200 scenario="Success" {"success": true, "message": "Code verified successfully.", "data": {"user": {"id": 42, "name": "Jane Doe", "verified_at": "2026-07-19T10:00:00.000000Z"}}, "errors": null}
     * @response 422 scenario="Invalid or expired OTP" {"success": false, "message": "Invalid or expired verification code.", "errors": {"otp": ["Invalid or expired verification code."]}, "data": null}
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

        $otpRecord = $user->otps()
            ->where('otp', $request->otp)
            ->where('type', 'verify')
            ->where('expires_at', '>', now())
            ->first();

        if (! $otpRecord) {
            return ApiResponse::error(
                Trans::get('api.invalid_otp'),
                ['otp' => [Trans::get('api.invalid_otp')]],
                422,
            );
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
     * @response 200 scenario="Success" {"success": true, "message": "Password reset code sent.", "data": {"identifier": "jane@example.com", "channel": "email", "otp_expires_in_minutes": 5}, "errors": null}
     * @response 422 scenario="User not found" {"success": false, "message": "User not found.", "errors": {"identifier": ["User not found."]}, "data": null}
     * @response 422 scenario="Requested `type` channel not populated on this user" {"success": false, "message": "OTP not available for this identifier type.", "errors": {"type": ["OTP not available for this identifier type."]}, "data": null}
     * @response 403 scenario="Account inactive" {"success": false, "message": "Your account is inactive.", "errors": null, "data": null}
     */
    public function forgotPassword(Request $request)
    {
        $request->validate([
            'identifier' => 'required|string|max:255',
            'type' => 'nullable|in:email,phone',
        ]);

        $user = $this->findUserByIdentifier($request->identifier);

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

        // Resolve which channel to use.
        // - If client passed `type`, use it (must be populated on the user).
        // - Else default priority: email > phone.
        $available = array_values(array_filter([
            $user->email ? 'email' : null,
            $user->phone ? 'phone' : null,
        ]));

        if ($request->type) {
            if (! in_array($request->type, $available, true)) {
                return ApiResponse::error(
                    Trans::get('api.otp_not_available'),
                    ['type' => [Trans::get('api.otp_not_available')]],
                    422,
                );
            }
            $channel = $request->type;
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

        if (filter_var(env('IS_TESTING'), FILTER_VALIDATE_BOOLEAN)) {
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
     * @response 200 scenario="Success" {"success": true, "message": "Code verified successfully.", "data": {"identifier": "jane@example.com", "otp": "482913"}, "errors": null}
     * @response 422 scenario="User not found" {"success": false, "message": "User not found.", "errors": {"identifier": ["User not found."]}, "data": null}
     * @response 422 scenario="Invalid or expired OTP" {"success": false, "message": "Invalid or expired verification code.", "errors": {"otp": ["Invalid or expired verification code."]}, "data": null}
     * @response 403 scenario="Account inactive" {"success": false, "message": "Your account is inactive.", "errors": null, "data": null}
     */
    public function verifyForgotPasswordOtp(Request $request)
    {
        $request->validate([
            'identifier' => 'required|string|max:255',
            'otp' => 'required|string|max:10',
        ]);

        $user = $this->findUserByIdentifier($request->identifier);

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

        $otpRecord = $user->otps()
            ->where('otp', $request->otp)
            ->where('type', 'reset_password')
            ->where('expires_at', '>', now())
            ->first();

        if (! $otpRecord) {
            return ApiResponse::error(
                Trans::get('api.invalid_otp'),
                ['otp' => [Trans::get('api.invalid_otp')]],
                422,
            );
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
     * @response 200 scenario="Success" {"success": true, "message": "Password changed successfully.", "data": null, "errors": null}
     * @response 422 scenario="User not found" {"success": false, "message": "User not found.", "errors": {"identifier": ["User not found."]}, "data": null}
     * @response 422 scenario="Invalid or expired OTP" {"success": false, "message": "Invalid or expired verification code.", "errors": {"otp": ["Invalid or expired verification code."]}, "data": null}
     * @response 403 scenario="Account inactive" {"success": false, "message": "Your account is inactive.", "errors": null, "data": null}
     */
    public function changeForgotPassword(Request $request)
    {
        $request->validate([
            'identifier' => 'required|string|max:255',
            'otp' => 'required|string|max:10',
            'password' => 'required|string|min:8|max:255|confirmed',
        ]);

        $user = $this->findUserByIdentifier($request->identifier);

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

        $otpRecord = $user->otps()
            ->where('otp', $request->otp)
            ->where('type', 'reset_password')
            ->where('expires_at', '>', now())
            ->first();

        if (! $otpRecord) {
            return ApiResponse::error(
                Trans::get('api.invalid_otp'),
                ['otp' => [Trans::get('api.invalid_otp')]],
                422,
            );
        }

        $user->tokens()->delete();
        $user->password = Hash::make($request->password);
        $user->save();

        $otpRecord->delete();

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
            'password' => 'required|string|min:8|max:255|confirmed',
        ]);

        if ($hasPassword && ! Hash::check($request->old_password, $user->password)) {
            return ApiResponse::error(
                Trans::get('api.invalid_old_password'),
                ['old_password' => [Trans::get('api.invalid_old_password')]],
                422,
            );
        }

        $user->password = Hash::make($request->password);
        $user->save();

        $user->tokens()->where('id', '!=', $user->currentAccessToken()->id)->delete();

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
     *
     * @groupDescription View and update the authenticated user's profile, identifiers, and account lifecycle.
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
     * @response 200 scenario="Success" {"success": true, "message": "Verification code sent to your new identifier.", "data": {"new_identifier": "new@example.com", "otp_expires_in_minutes": 5}, "errors": null}
     * @response 422 scenario="new_identifier is not a valid/configured kind" {"success": false, "message": "The identifier must be a valid email or phone.", "errors": {"new_identifier": ["The identifier must be a valid email or phone."]}, "data": null}
     * @response 422 scenario="new_identifier already taken" {"success": false, "message": "The given data was invalid.", "errors": {"new_identifier": ["The email has already been taken."]}, "data": null}
     * @response 422 scenario="Password required before email change (social-only account)" {"success": false, "message": "Set a password before changing your email — your linked social accounts will be unlinked.", "errors": {"new_identifier": ["Set a password before changing your email — your linked social accounts will be unlinked."]}, "data": null}
     * @response 403 scenario="Account pending deletion or suspended" {"success": false, "message": "Your account is in a frozen state and cannot be edited. Please log in to restore it or contact support.", "errors": {"new_identifier": ["Your account is in a frozen state and cannot be edited. Please log in to restore it or contact support."]}, "data": null}
     */
    public function requestIdentifierChange(Request $request)
    {
        $identifiers = $this->getAuthIdentifiers();

        $request->validate([
            'new_identifier' => 'required|string|max:255',
        ]);

        $newIdentifier = trim($request->new_identifier);
        $kind = $this->detectIdentifierKind($newIdentifier);

        if (! $kind || ! in_array($kind, $identifiers, true)) {
            return ApiResponse::error(
                Trans::get('api.invalid_identifier'),
                ['new_identifier' => [Trans::get('api.invalid_identifier')]],
                422,
            );
        }

        if ($kind === 'email') {
            $newIdentifier = strtolower($newIdentifier);
        }

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
            'email' => $this->getEmailValidationRule(true, $user->id),
            'phone' => $this->getPhoneValidationRule(true, $user->id),
        };

        $validator = Validator::make([$kind => $newIdentifier], [$kind => $rule]);

        if ($validator->fails()) {
            return ApiResponse::error(
                Trans::get('api.validation_failed'),
                ['new_identifier' => $validator->errors()->get($kind)],
                422,
            );
        }

        $user->otps()->where('type', 'change_identifier')->delete();

        $otp = Otp::create([
            'user_id' => $user->id,
            'type' => 'change_identifier',
            'identifier' => $newIdentifier,
            'otp' => (string) random_int(100000, 999999),
            'expires_at' => now()->addMinutes(5),
        ]);

        if ($kind === 'email') {
            EmailHelper::send(
                $newIdentifier,
                Trans::get('api.otp_subject_change_identifier'),
                'emails.otp',
                ['otp' => $otp->otp, 'name' => $user->name]
            );
        } elseif ($kind === 'phone') {
            if (filter_var(env('IS_OTP_WHATSAPP'), FILTER_VALIDATE_BOOLEAN)) {
                SendWhatsapp::send($newIdentifier, 'Your OTP is: '.$otp->otp);
            } else {
                SendSMS::send($newIdentifier, 'Your OTP is: '.$otp->otp);
            }
        }

        $responseData = [
            'new_identifier' => $newIdentifier,
            'otp_expires_in_minutes' => 5,
        ];

        if (filter_var(env('IS_TESTING'), FILTER_VALIDATE_BOOLEAN)) {
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
     * @response 200 scenario="Success — phone change" {"success": true, "message": "Identifier changed successfully.", "data": {"user": {"id": 42, "phone": "+15551234567"}, "unlinked_providers": []}, "errors": null}
     * @response 200 scenario="Success — email change (social accounts unlinked)" {"success": true, "message": "Identifier changed successfully.", "data": {"user": {"id": 42, "email": "new@example.com"}, "unlinked_providers": ["google.com"]}, "errors": null}
     * @response 422 scenario="Invalid or expired OTP" {"success": false, "message": "Invalid or expired verification code.", "errors": {"otp": ["Invalid or expired verification code."]}, "data": null}
     * @response 403 scenario="Account pending deletion or suspended" {"success": false, "message": "Your account is in a frozen state and cannot be edited. Please log in to restore it or contact support.", "errors": {"new_identifier": ["Your account is in a frozen state and cannot be edited. Please log in to restore it or contact support."]}, "data": null}
     */
    public function verifyIdentifierChange(Request $request, FirebaseAuth $firebaseAuth)
    {
        $identifiers = $this->getAuthIdentifiers();

        $request->validate([
            'new_identifier' => 'required|string|max:255',
            'otp' => 'required|string|max:10',
        ]);

        $newIdentifier = trim($request->new_identifier);
        $kind = $this->detectIdentifierKind($newIdentifier);

        if (! $kind || ! in_array($kind, $identifiers, true)) {
            return ApiResponse::error(
                Trans::get('api.invalid_identifier'),
                ['new_identifier' => [Trans::get('api.invalid_identifier')]],
                422,
            );
        }

        if ($kind === 'email') {
            $newIdentifier = strtolower($newIdentifier);
        }

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

        $otpRecord = $user->otps()
            ->where('otp', $request->otp)
            ->where('identifier', $newIdentifier)
            ->where('type', 'change_identifier')
            ->where('expires_at', '>', now())
            ->first();

        if (! $otpRecord) {
            return ApiResponse::error(
                Trans::get('api.invalid_otp'),
                ['otp' => [Trans::get('api.invalid_otp')]],
                422,
            );
        }

        $user->{$kind} = $newIdentifier;
        $user->save();

        $otpRecord->delete();

        // Email identifier change invalidates every linked social provider —
        // the social accounts were authorized against the old email. Wipe them
        // so the user re-links explicitly with the new email. Also revoke
        // the Firebase refresh tokens so the old social tokens can't be used
        // by an in-flight session to silently re-link before the user notices.
        $unlinkedProviders = [];
        if ($kind === 'email') {
            $rows = $user->socialAccounts()->get(['provider', 'provider_id']);
            $unlinkedProviders = $rows->pluck('provider')->all();
            $user->socialAccounts()->delete();

            foreach ($rows as $row) {
                try {
                    $firebaseAuth->revokeRefreshTokens($row->provider_id);
                } catch (\Throwable $e) {
                    report($e); // non-fatal — local row already deleted
                }
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
        if ($this->hasField('username')) {
            $rules['username'] = ['nullable', 'string', 'max:255', 'min:3', 'regex:/^[A-Za-z][A-Za-z0-9_-]*$/', $this->uniqueApiUsernameRule($user->id)];
        }

        // Email/phone editable only when enabled as non-identifier extras (HAS_*_FIELD).
        // Identifier email/phone changes go through request-identifier-change.
        foreach (['email', 'phone'] as $field) {
            if (! $this->isIdentifier($field) && $this->hasField($field)) {
                $rules[$field] = match ($field) {
                    'email' => $this->getEmailValidationRule(false, $user->id),
                    'phone' => $this->getPhoneValidationRule(false, $user->id),
                };
            }
        }

        $validated = $request->validate($rules);

        $user->fill(array_filter($validated, fn ($v) => $v !== null && $v !== ''));
        $user->save();

        return ApiResponse::success(['user' => $user->fresh()], Trans::get('api.profile_updated'));
    }

    // --- Private Helpers ---

    private function getAuthIdentifiers(): array
    {
        $value = env('AUTH_IDENTIFIERS', 'email');

        // Identifiers are limited to email/phone. Username is no longer a primary identifier.
        $list = array_map('trim', explode(',', $value));
        $allowed = array_values(array_intersect($list, ['email', 'phone']));

        return ! empty($allowed) ? $allowed : ['email'];
    }

    private function isIdentifier(string $field): bool
    {
        return in_array($field, $this->getAuthIdentifiers());
    }

    private function hasField(string $field): bool
    {
        if ($this->isIdentifier($field)) {
            return true;
        }

        return filter_var(env('HAS_'.strtoupper($field).'_FIELD', false), FILTER_VALIDATE_BOOLEAN);
    }

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

    private function detectIdentifierKind(string $value): ?string
    {
        if (filter_var($value, FILTER_VALIDATE_EMAIL)) {
            return 'email';
        }

        if (preg_match('/^\+?[0-9]{6,}$/', $value)) {
            return 'phone';
        }

        return null;
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

        $user->devices()->create([
            'personal_access_token_id' => $accessToken->id,
            'device_id' => $deviceId,
            'fcm_token' => $fcmToken,
            'device_name' => $request->input('device_name'),
            'platform' => $platform,
            'ip' => $request->ip(),
            'user_agent' => substr((string) $request->userAgent(), 0, 512),
            'last_seen_at' => now(),
        ]);

        return (int) $accessToken->id;
    }

    /**
     * Wipe the guest row keyed by the request's X-Device-Id header. Called
     * from register / firebaseLogin (new-user branch) — the same physical
     * device is being promoted from anonymous tracking to a real account.
     * NOT called from login: a registered user logging in from a shared
     * device must not nuke a co-resident guest.
     */
    private function convertGuestForRequest(Request $request): void
    {
        $deviceId = trim((string) $request->header('X-Device-Id'));
        if ($deviceId !== '') {
            User::convertFromGuest($deviceId);
        }
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

    private function findUserByIdentifier(string $value, bool $withTrashed = false): ?User
    {
        $value = trim($value);
        $identifiers = $this->getAuthIdentifiers();
        $hasUsername = filter_var(env('HAS_USERNAME_FIELD', false), FILTER_VALIDATE_BOOLEAN);
        $kind = $this->detectIdentifierKind($value);

        // Detect kind from format and query the single matching column.
        // Email format → email column. Phone format → phone column. Else (alpha) → username column.
        // Email is lowercased to keep lookups case-insensitive across DB engines.
        $column = null;
        if ($kind && in_array($kind, $identifiers, true)) {
            $column = $kind;
        } elseif ($kind === null && $hasUsername) {
            $column = 'username';
        }

        if (! $column) {
            return null;
        }

        if ($column === 'email') {
            $value = strtolower($value);
        }

        $query = $withTrashed ? User::withTrashed() : User::query();

        return $query->where($column, $value)
            ->whereHas('roles', fn ($q) => $q->where('name', 'user')->where('guard_name', 'api'))
            ->first();
    }

    private function getOtpIdentifierValue(User $user): ?string
    {
        $identifiers = $this->getAuthIdentifiers();

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

        $identifiers = $this->getAuthIdentifiers();

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
            'otp' => (string) random_int(100000, 999999),
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
            if (filter_var(env('IS_OTP_WHATSAPP'), FILTER_VALIDATE_BOOLEAN)) {
                SendWhatsapp::send($user->phone, 'Your OTP is: '.$otp->otp);
            } else {
                SendSMS::send($user->phone, 'Your OTP is: '.$otp->otp);
            }
        }
        // username-only: OTP is stored but not sent (available in testing mode via response)

        return $otp;
    }

    /**
     * Identifier rules used by OTP-mode login. No uniqueness check (the
     * identifier may belong to an existing user — that's the login case).
     * Still applies email format / phone country / allowed domain guards.
     *
     * @return array<int, mixed>
     */
    private function getOtpLoginIdentifierRule(string $kind): array
    {
        if ($kind === 'email') {
            $rules = ['required', 'string', 'email', 'max:255'];
            $domains = env('ALLOWED_EMAIL_DOMAINS', 'all');
            if ($domains !== 'all') {
                $rules[] = new AllowedEmailDomain($domains);
            }

            return $rules;
        }

        $countries = env('ALLOWED_PHONE_COUNTRIES', 'all');

        return ['required', 'string', 'max:255', new AllowedPhoneCountry($countries)];
    }

    private function getPhoneValidationRule(bool $required, ?int $excludeId = null): array
    {
        $countries = env('ALLOWED_PHONE_COUNTRIES', 'all');
        $base = $required ? 'required' : 'nullable';
        $unique = $excludeId ? "unique:users,phone,{$excludeId}" : 'unique:users,phone';

        $rules = [$base, 'string', 'max:255', $unique];

        // Always add phone validation rule (validates digits only + country if restricted)
        $rules[] = new AllowedPhoneCountry($countries);

        return $rules;
    }

    private function getEmailValidationRule(bool $required, ?int $excludeId = null): string|array
    {
        $domains = env('ALLOWED_EMAIL_DOMAINS', 'all');
        $base = $required ? 'required' : 'nullable';
        $unique = $excludeId ? "unique:users,email,{$excludeId}" : 'unique:users,email';

        $rules = [$base, 'string', 'email', 'max:255', $unique];

        // Apply domain restriction if configured
        if ($domains !== 'all') {
            $rules[] = new AllowedEmailDomain($domains);
        }

        return $rules;
    }

    // --- Firebase Social Auth ---

    /**
     * Firebase Login
     *
     * Login or register via a Firebase ID token (Google/Apple/Facebook/etc). Requires email configured
     * as an identifier (AUTH_IDENTIFIERS). New users auto-verify and get the `user` role; existing
     * password accounts block social login; social-only accounts link the provider automatically.
     *
     * @group Social Login
     *
     * @groupDescription Login and account linking via Firebase-verified social providers (Google, Apple, etc.).
     *
     * @response 200 scenario="Success — existing or new social user" {"success": true, "message": "Login successful.", "token": "1|abcdef123456", "data": {"user": {"id": 42, "name": "Jane Doe", "email": "jane@example.com"}, "is_verified": true, "token_id": 7, "is_new_user": false, "provider": "google.com", "provider_already_linked": true, "linked_providers": ["google.com"], "token": "1|abcdef123456"}, "errors": null}
     * @response 422 scenario="Invalid/expired Firebase token" {"success": false, "message": "Invalid or expired Firebase token.", "errors": {"token": ["Invalid or expired Firebase token."]}, "data": null}
     * @response 422 scenario="Token has no email claim" {"success": false, "message": "Email is required for social login.", "errors": {"token": ["Email is required for social login."]}, "data": null}
     * @response 422 scenario="Provider not in SOCIAL_AUTH_PROVIDERS" {"success": false, "message": "This social provider is not allowed.", "errors": {"token": ["This social provider is not allowed."]}, "data": null}
     * @response 422 scenario="Email already registered with a password" {"success": false, "message": "Account already exists. Please login with your password.", "errors": {"token": ["Account already exists. Please login with your password."]}, "data": null}
     * @response 422 scenario="SOCIAL_AUTH_MAX_ACCOUNTS reached" {"success": false, "message": "Maximum number of social accounts reached.", "errors": {"token": ["Maximum number of social accounts reached."]}, "data": null}
     * @response 400 scenario="Social auth unavailable (email not an identifier)" {"success": false, "message": "Social authentication is not available. Email must be configured as a login identifier.", "errors": null, "data": null}
     * @response 403 scenario="Account inactive / wrong role" {"success": false, "message": "Your account is inactive.", "errors": null, "data": null}
     */
    public function firebaseLogin(Request $request, FirebaseAuth $firebaseAuth)
    {
        // Social auth requires email as an identifier
        if (! $this->isIdentifier('email')) {
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
                if ($this->hasField('username')) {
                    $userData['username'] = $this->generateUniqueUsername($email);
                }

                $user = $this->promoteGuestOrCreate($request, $userData, $role);
                $user->verified_at = now(); // Firebase already verified the user
                $user->save();

                // Create social account
                $user->socialAccounts()->create([
                    'provider' => $provider,
                    'provider_id' => $uid,
                    'email' => $email,
                    'name' => $name,
                ]);
            }
        }

        // Ensure verified (social login = verified)
        if (! $user->verified_at) {
            $user->verified_at = now();
            $user->save();
        }

        $token = $user->createToken('user_token', ['*'], now()->addDays(30))->plainTextToken;
        $tokenId = $this->trackDevice($user, $token, $request);

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
     * @response 200 scenario="Success" {"success": true, "message": "Social account linked successfully.", "data": {"user": {"id": 42, "email": "jane@example.com"}}, "errors": null}
     * @response 422 scenario="Invalid/expired Firebase token" {"success": false, "message": "Invalid or expired Firebase token.", "errors": {"token": ["Invalid or expired Firebase token."]}, "data": null}
     * @response 422 scenario="Provider already linked to another user" {"success": false, "message": "This social account is already linked to another user.", "errors": {"token": ["This social account is already linked to another user."]}, "data": null}
     * @response 422 scenario="Provider already linked to you" {"success": false, "message": "This provider is already linked to your account. Logged in.", "errors": {"token": ["This provider is already linked to your account. Logged in."]}, "data": null}
     * @response 422 scenario="Token email does not match account email" {"success": false, "message": "Social account email does not match your account email.", "errors": {"token": ["Social account email does not match your account email."]}, "data": null}
     * @response 422 scenario="SOCIAL_AUTH_MAX_ACCOUNTS reached" {"success": false, "message": "Maximum number of social accounts reached.", "errors": {"token": ["Maximum number of social accounts reached."]}, "data": null}
     * @response 400 scenario="Social auth unavailable (email not an identifier)" {"success": false, "message": "Social authentication is not available. Email must be configured as a login identifier.", "errors": null, "data": null}
     */
    public function linkSocialAccount(Request $request, FirebaseAuth $firebaseAuth)
    {
        // Social auth requires email as an identifier
        if (! $this->isIdentifier('email')) {
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
        $value = env('SOCIAL_AUTH_PROVIDERS', 'google.com,apple.com');

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
        return (int) env('SOCIAL_AUTH_MAX_ACCOUNTS', 0); // 0 = unlimited
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
