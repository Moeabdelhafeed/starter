<?php

namespace App\Http\Controllers\Api\AppUser;

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
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Kreait\Firebase\Contract\Auth as FirebaseAuth;
use Kreait\Firebase\Exception\Auth\FailedToVerifyToken;
use Spatie\Permission\Models\Role;

class AppUserController extends Controller
{
    public function register(Request $request)
    {
        $identifiers = $this->getAuthIdentifiers();

        $rules = [
            'policy_agreed' => 'required|boolean',
            'name' => 'required|string|max:255',
            'password' => 'required|string|min:8|max:255|confirmed',
            'fcm_token' => 'nullable|string|max:255',
        ];

        // Each identifier field is required
        foreach ($identifiers as $field) {
            $rules[$field] = match ($field) {
                'email' => $this->getEmailValidationRule(true),
                'phone' => $this->getPhoneValidationRule(true),
                'username' => 'required|string|alpha_dash|max:255|unique:users,username',
            };
        }

        // Non-identifier fields that are enabled are optional
        foreach (['email', 'phone', 'username'] as $field) {
            if (! $this->isIdentifier($field) && $this->hasField($field)) {
                $rules[$field] = match ($field) {
                    'email' => $this->getEmailValidationRule(false),
                    'phone' => $this->getPhoneValidationRule(false),
                    'username' => 'nullable|string|alpha_dash|max:255|unique:users,username',
                };
            }
        }

        $request->validate($rules);

        if (! $request->policy_agreed) {
            return ApiResponse::error(Trans::get('api.policy_not_agreed'), null, 401);
        }

        $role = Role::where('name', 'user')->where('guard_name', 'api')->first();
        if (! $role) {
            return ApiResponse::error(Trans::get('api.user_role_not_found'), null, 500);
        }

        if ($request->fcm_token) {
            User::where('fcm_token', $request->fcm_token)->update(['fcm_token' => null]);
        }

        $userData = [
            'name' => $request->name,
            'password' => Hash::make($request->password),
            'fcm_token' => $request->fcm_token,
        ];

        // Add identifier fields
        foreach ($identifiers as $field) {
            $userData[$field] = $request->$field;
        }

        // Add optional non-identifier fields
        foreach (['email', 'phone', 'username'] as $field) {
            if (! $this->isIdentifier($field) && $this->hasField($field) && $request->$field) {
                $userData[$field] = $request->$field;
            }
        }

        $user = User::create($userData);
        $user->assignRole($role);

        $token = $user->createToken('user_token', ['*'], now()->addDays(1))->plainTextToken;

        $otp = $this->sendOtpToUser($user, 'verify');

        $responseData = [
            'token' => $token,
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
            'fcm_token' => 'nullable|string|max:255',
            'remember_me' => 'boolean',
        ];

        $request->validate($rules);

        $user = $this->findUserByIdentifier($request->identifier);

        if (! $user) {
            return ApiResponse::error(Trans::get('api.user_not_found'), null, 404);
        }

        if (! $user->is_active) {
            return ApiResponse::error(__('admin.account_is_inactive'), null, 403);
        }

        if (! Hash::check($request->password, $user->password)) {
            return ApiResponse::error(Trans::get('api.invalid_credentials'), null, 401);
        }

        if (! $user->hasRole('user', 'api')) {
            return ApiResponse::error(Trans::get('api.unauthorized_access'), null, 403);
        }

        if ($request->fcm_token) {
            User::where('fcm_token', $request->fcm_token)->update(['fcm_token' => null]);
            $user->fcm_token = $request->fcm_token;
            $user->save();
        }

        if ($user->verified_at === null) {
            $token = $user->createToken('user_token', ['*'], now()->addDays(1))->plainTextToken;

            $otp = $this->sendOtpToUser($user, 'verify');

            $responseData = [
                'user' => $user->fresh(),
                'token' => $token,
                'is_verified' => false,
                'otp_expires_in_minutes' => 5,
            ];

            if (filter_var(env('IS_TESTING'), FILTER_VALIDATE_BOOLEAN)) {
                $responseData['otp'] = $otp ? $otp->otp : null;
            }

            return ApiResponse::success($responseData, Trans::get('api.user_not_verified'));
        }

        $days = $request->remember_me ? 30 : 1;
        $token = $user->createToken('user_token', ['*'], now()->addDays($days))->plainTextToken;

        return ApiResponse::success([
            'token' => $token,
            'user' => $user->fresh(),
            'is_verified' => true,
        ], Trans::get('api.login_successful'));
    }

    public function checkIdentifier(Request $request)
    {
        $request->validate([
            'identifier' => 'required|string|max:255',
        ]);

        $user = $this->findUserByIdentifier($request->identifier);

        return ApiResponse::success([
            'exists' => $user !== null,
        ]);
    }

    public function logout(Request $request)
    {
        $request->user()->currentAccessToken()->delete();

        return ApiResponse::success(null, Trans::get('api.logout_successful'));
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
            return ApiResponse::error(Trans::get('api.invalid_otp'), null, 401);
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
        ]);

        $user = $this->findUserByIdentifier($request->identifier);

        if (! $user) {
            return ApiResponse::error(Trans::get('api.user_not_found'), null, 404);
        }

        if (! $user->is_active) {
            return ApiResponse::error(__('admin.account_is_inactive'), null, 403);
        }

        $otp = $this->sendOtpToUser($user, 'reset_password');

        $responseData = [
            'identifier' => $this->getOtpIdentifierValue($user),
            'otp_expires_in_minutes' => 5,
        ];

        if (filter_var(env('IS_TESTING'), FILTER_VALIDATE_BOOLEAN)) {
            $responseData['otp'] = $otp ? $otp->otp : null;
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
            return ApiResponse::error(Trans::get('api.user_not_found'), null, 404);
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
            return ApiResponse::error(Trans::get('api.invalid_otp'), null, 401);
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
            return ApiResponse::error(Trans::get('api.user_not_found'), null, 404);
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
            return ApiResponse::error(Trans::get('api.invalid_otp'), null, 401);
        }

        $user->tokens()->delete();
        $user->password = Hash::make($request->password);
        $user->save();

        $otpRecord->delete();

        return ApiResponse::success(null, Trans::get('api.password_changed_successfully'));
    }

    public function changePassword(Request $request)
    {
        $request->validate([
            'old_password' => 'required|string',
            'password' => 'required|string|min:8|max:255|confirmed',
        ]);

        $user = $request->user();

        if (! Hash::check($request->old_password, $user->password)) {
            return ApiResponse::error(Trans::get('api.invalid_old_password'), null, 401);
        }

        $user->password = Hash::make($request->password);
        $user->save();

        $user->tokens()->where('id', '!=', $user->currentAccessToken()->id)->delete();

        return ApiResponse::success(null, Trans::get('api.password_changed_successfully'));
    }

    public function deleteAccount(Request $request)
    {
        $user = $request->user();
        $user->tokens()->delete();
        $user->delete();

        return ApiResponse::success(null, Trans::get('api.account_deleted_successfully'));
    }

    public function requestEmailChange(Request $request)
    {
        if (! $this->isIdentifier('email')) {
            return ApiResponse::error(Trans::get('api.email_change_not_available'), null, 400);
        }

        $request->validate([
            'new_email' => $this->getEmailValidationRule(true),
        ]);

        $user = $request->user();
        $user->otps()->where('type', 'change_email')->delete();

        $otp = Otp::create([
            'user_id' => $user->id,
            'type' => 'change_email',
            'identifier' => $request->new_email,
            'otp' => (string) random_int(100000, 999999),
            'expires_at' => now()->addMinutes(5),
        ]);

        EmailHelper::send(
            $request->new_email,
            Trans::get('api.otp_subject_change_email'),
            'emails.otp',
            ['otp' => $otp->otp, 'name' => $user->name]
        );

        $responseData = [
            'new_email' => $request->new_email,
            'otp_expires_in_minutes' => 5,
        ];

        if (filter_var(env('IS_TESTING'), FILTER_VALIDATE_BOOLEAN)) {
            $responseData['otp'] = $otp->otp;
        }

        return ApiResponse::success($responseData, Trans::get('api.email_change_otp_sent'));
    }

    public function verifyEmailChange(Request $request)
    {
        if (! $this->isIdentifier('email')) {
            return ApiResponse::error(Trans::get('api.email_change_not_available'), null, 400);
        }

        $request->validate([
            'new_email' => $this->getEmailValidationRule(true),
            'otp' => 'required|string|max:10',
        ]);

        $user = $request->user();
        $otpRecord = $user->otps()
            ->where('otp', $request->otp)
            ->where('identifier', $request->new_email)
            ->where('type', 'change_email')
            ->where('expires_at', '>', now())
            ->first();

        if (! $otpRecord) {
            return ApiResponse::error(Trans::get('api.invalid_otp'), null, 401);
        }

        $user->email = $request->new_email;
        $user->save();

        $otpRecord->delete();

        return ApiResponse::success(['user' => $user->fresh()], Trans::get('api.email_changed_successfully'));
    }

    public function updateProfile(Request $request)
    {
        $user = $request->user();

        $rules = [
            'name' => 'nullable|string|max:255',
        ];

        // Allow editing non-identifier fields that are enabled
        foreach (['email', 'phone', 'username'] as $field) {
            if (! $this->isIdentifier($field) && $this->hasField($field)) {
                $rules[$field] = match ($field) {
                    'email' => $this->getEmailValidationRule(false, $user->id),
                    'phone' => $this->getPhoneValidationRule(false, $user->id),
                    'username' => 'nullable|string|alpha_dash|max:255|unique:users,username,'.$user->id,
                };
            }
        }

        $validated = $request->validate($rules);

        $user->fill(array_filter($validated, fn ($v) => $v !== null));
        $user->save();

        return ApiResponse::success(['user' => $user->fresh()], Trans::get('api.profile_updated'));
    }

    // --- Private Helpers ---

    private function getAuthIdentifiers(): array
    {
        $value = env('AUTH_IDENTIFIERS', 'email');

        return array_map('trim', explode(',', $value));
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

    private function findUserByIdentifier(string $value): ?User
    {
        $fields = $this->getAuthIdentifiers();

        return User::where(function ($query) use ($fields, $value) {
            foreach ($fields as $field) {
                $query->orWhere($field, $value);
            }
        })->first();
    }

    private function getOtpIdentifierValue(User $user): string
    {
        $identifiers = $this->getAuthIdentifiers();

        // Return the first available identifier value (priority: email > phone > username)
        foreach (['email', 'phone', 'username'] as $field) {
            if (in_array($field, $identifiers) && $user->$field) {
                return $user->$field;
            }
        }

        return $user->{$identifiers[0]};
    }

    private function sendOtpToUser(User $user, string $type = 'verify'): ?Otp
    {
        $identifiers = $this->getAuthIdentifiers();

        $user->otps()->where('type', $type)->delete();

        $otp = $user->otps()->create([
            'identifier' => $this->getOtpIdentifierValue($user),
            'otp' => (string) random_int(100000, 999999),
            'type' => $type,
            'expires_at' => now()->addMinutes(5),
        ]);

        $subject = match ($type) {
            'verify' => Trans::get('api.otp_subject_verify'),
            'reset_password' => Trans::get('api.otp_subject_reset'),
            default => Trans::get('api.otp_subject_verify'),
        };

        // Send OTP via the best available channel (priority: email > phone)
        if (in_array('email', $identifiers) && $user->email) {
            EmailHelper::send($user->email, $subject, 'emails.otp', [
                'otp' => $otp->otp,
                'name' => $user->name,
            ]);
        } elseif (in_array('phone', $identifiers) && $user->phone) {
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
            'fcm_token' => 'nullable|string|max:255',
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
            return ApiResponse::error(Trans::get('api.invalid_firebase_token'), null, 401);
        }

        if (! $email) {
            return ApiResponse::error(Trans::get('api.firebase_email_required'), null, 400);
        }

        // Check if provider is allowed
        if (! $this->isProviderAllowed($provider)) {
            return ApiResponse::error(Trans::get('api.social_provider_not_allowed'), null, 400);
        }

        $role = Role::where('name', 'user')->where('guard_name', 'api')->first();
        if (! $role) {
            return ApiResponse::error(Trans::get('api.user_role_not_found'), null, 500);
        }

        if ($request->fcm_token) {
            User::where('fcm_token', $request->fcm_token)->update(['fcm_token' => null]);
        }

        // Find existing social account by provider_id
        $socialAccount = SocialAccount::where('provider', $provider)
            ->where('provider_id', $uid)
            ->first();

        if ($socialAccount) {
            // Social account already linked - login as that user
            $user = $socialAccount->user;
            $user->update([
                'fcm_token' => $request->fcm_token ?? $user->fcm_token,
            ]);

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
                return ApiResponse::error(Trans::get('api.account_exists_use_password'), null, 409);
            }

            if ($existingUser) {
                // Account exists but no password (social-only) - link this provider
                $user = $existingUser;
                $user->update([
                    'fcm_token' => $request->fcm_token ?? $user->fcm_token,
                ]);

                // Check if user already has this provider linked
                if (! $user->hasSocialProvider($provider)) {
                    // Check max accounts limit
                    if (! $this->canLinkMoreSocialAccounts($user)) {
                        return ApiResponse::error(Trans::get('api.social_max_accounts_reached'), null, 400);
                    }

                    $user->socialAccounts()->create([
                        'provider' => $provider,
                        'provider_id' => $uid,
                        'email' => $email,
                        'name' => $name,
                    ]);
                }

                if (! $user->is_active) {
                    return ApiResponse::error(__('admin.account_is_inactive'), null, 403);
                }
            } else {
                // Create new user
                $user = User::create([
                    'name' => $name ?? explode('@', $email)[0],
                    'email' => $email,
                    'phone' => $phone,
                    'fcm_token' => $request->fcm_token,
                    'is_active' => true,
                    'verified_at' => now(), // Firebase already verified the user
                ]);

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

        return ApiResponse::success([
            'token' => $token,
            'user' => $user->fresh()->load('socialAccounts'),
            'is_verified' => true,
            'is_new_user' => $user->wasRecentlyCreated,
        ], Trans::get('api.login_successful'));
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
            return ApiResponse::error(Trans::get('api.invalid_firebase_token'), null, 401);
        }

        // Check if provider is allowed
        if (! $this->isProviderAllowed($provider)) {
            return ApiResponse::error(Trans::get('api.social_provider_not_allowed'), null, 400);
        }

        $user = $request->user();

        // Check if this social account is already linked to another user
        $existingLink = SocialAccount::where('provider', $provider)
            ->where('provider_id', $uid)
            ->where('user_id', '!=', $user->id)
            ->first();

        if ($existingLink) {
            return ApiResponse::error(Trans::get('api.social_account_already_linked'), null, 409);
        }

        // Check if user already has this provider linked
        if ($user->hasSocialProvider($provider)) {
            return ApiResponse::error(Trans::get('api.social_provider_already_linked'), null, 409);
        }

        // Check if email matches (optional security check)
        if ($email && $email !== $user->email) {
            return ApiResponse::error(Trans::get('api.social_email_mismatch'), null, 400);
        }

        // Check max accounts limit
        if (! $this->canLinkMoreSocialAccounts($user)) {
            return ApiResponse::error(Trans::get('api.social_max_accounts_reached'), null, 400);
        }

        // Link the social account
        $user->socialAccounts()->create([
            'provider' => $provider,
            'provider_id' => $uid,
            'email' => $email,
            'name' => $name,
        ]);

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
            return ApiResponse::error(Trans::get('api.social_provider_not_linked'), null, 404);
        }

        // Check if user has password or other social accounts
        $otherSocialAccounts = $user->socialAccounts()->where('provider', '!=', $request->provider)->count();

        if (! $user->password && $otherSocialAccounts === 0) {
            return ApiResponse::error(Trans::get('api.cannot_unlink_social_only'), null, 400);
        }

        $socialAccount->delete();

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
