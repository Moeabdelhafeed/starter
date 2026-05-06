<?php

namespace App\Http\Controllers\Api\AppUser;

use App\Events\DeviceRevoked;
use App\Helpers\ApiResponse;
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

        $user = User::create($userData);
        $user->assignRole($role);

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

    public function login(Request $request)
    {
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

    public function authConfig()
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
        ]);
    }

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

    public function logout(Request $request)
    {
        $request->user()->currentAccessToken()->delete();

        return ApiResponse::success(null, Trans::get('api.logout_successful'));
    }

    /**
     * List the authenticated user's active devices. The current device is
     * flagged so the client can highlight it / disable its revoke button.
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
    public function revokeDevice(Request $request, int $deviceId)
    {
        $device = $request->user()->devices()->findOrFail($deviceId);
        $tokenId = $device->personal_access_token_id;

        $request->user()->tokens()->where('id', $tokenId)->delete();

        broadcast(new DeviceRevoked($request->user()->id, (int) $tokenId));

        return ApiResponse::success(null, Trans::get('api.device_revoked'));
    }

    public function verifyOtp(Request $request)
    {
        $request->validate([
            'otp' => 'required|string|max:10',
        ]);

        $user = $request->user();
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

    public function deleteAccount(Request $request)
    {
        $user = $request->user();
        $user->tokens()->delete();
        $user->markAccountDeleted();

        return ApiResponse::success(null, Trans::get('api.account_deleted_successfully'));
    }

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
                broadcast(new DeviceRevoked($user->id, (int) $sibling->id));
                $sibling->delete(); // FK cascade drops user_devices row
            }
        }

        $deviceId = trim((string) $request->header('X-Device-Id')) ?: null;
        $platform = strtolower(trim((string) $request->header('X-Platform'))) ?: $request->input('platform');
        $fcmToken = trim((string) $request->header('X-FCM-Token')) ?: null;

        // Convert before inserting so the guest user's cascade drops its
        // user_devices row first; otherwise we'd race the new row against
        // the device_id of the soon-to-be-deleted guest device.
        if ($deviceId) {
            User::convertFromGuest($deviceId);
        }

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
                // Create new user
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

                $user = User::create($userData);
                $user->verified_at = now(); // Firebase already verified the user
                $user->save();

                $user->assignRole($role);

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
