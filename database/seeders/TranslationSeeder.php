<?php

namespace Database\Seeders;

use App\Models\Language;
use App\Models\TranslationKey;
use App\Models\TranslationValue;
use Illuminate\Database\Seeder;

class TranslationSeeder extends Seeder
{
    /**
     * API translations with English and Arabic values.
     * Add new API response messages here.
     *
     * @var array<string, array{en: string, ar: string}>
     */
    protected array $apiTranslations = [
        // Authentication & Registration
        'user_registered' => [
            'en' => 'User registered successfully.',
            'ar' => 'تم تسجيل المستخدم بنجاح.',
        ],
        'login_successful' => [
            'en' => 'Login successful.',
            'ar' => 'تم تسجيل الدخول بنجاح.',
        ],
        'logout_successful' => [
            'en' => 'Logout successful.',
            'ar' => 'تم تسجيل الخروج بنجاح.',
        ],
        'invalid_credentials' => [
            'en' => 'Invalid credentials.',
            'ar' => 'بيانات الاعتماد غير صالحة.',
        ],
        'unauthorized_access' => [
            'en' => 'Unauthorized access.',
            'ar' => 'وصول غير مصرح به.',
        ],
        'unauthorized_x_api_token' => [
            'en' => 'Unauthorized X-API-TOKEN.',
            'ar' => 'رمز X-API-TOKEN غير مصرح به.',
        ],
        'policy_not_agreed' => [
            'en' => 'You must agree to the terms and conditions.',
            'ar' => 'يجب الموافقة على الشروط والأحكام.',
        ],
        'user_role_not_found' => [
            'en' => 'User role not found for api guard.',
            'ar' => 'لم يتم العثور على دور المستخدم لحماية API.',
        ],

        // User Status
        'user_not_found' => [
            'en' => 'User not found.',
            'ar' => 'المستخدم غير موجود.',
        ],
        'user_not_verified' => [
            'en' => 'Account not verified. Please check your email.',
            'ar' => 'الحساب غير مفعل. يرجى التحقق من بريدك الإلكتروني.',
        ],
        'profile_updated' => [
            'en' => 'Profile updated successfully.',
            'ar' => 'تم تحديث الملف الشخصي بنجاح.',
        ],
        'account_deleted_successfully' => [
            'en' => 'Account deleted successfully.',
            'ar' => 'تم حذف الحساب بنجاح.',
        ],

        // OTP & Verification
        'otp_sent' => [
            'en' => 'Verification code sent successfully.',
            'ar' => 'تم إرسال رمز التحقق بنجاح.',
        ],
        'otp_verified' => [
            'en' => 'Code verified successfully.',
            'ar' => 'تم التحقق من الرمز بنجاح.',
        ],
        'invalid_otp' => [
            'en' => 'Invalid or expired verification code.',
            'ar' => 'رمز التحقق غير صالح أو منتهي الصلاحية.',
        ],
        'otp_subject_verify' => [
            'en' => 'Your Verification Code',
            'ar' => 'رمز التحقق الخاص بك',
        ],
        'otp_subject_reset' => [
            'en' => 'Password Reset Request',
            'ar' => 'إعادة تعيين كلمة المرور',
        ],
        'otp_subject_change_email' => [
            'en' => 'Change Email Verification',
            'ar' => 'التحقق من تغيير البريد الإلكتروني',
        ],
        'otp_not_available' => [
            'en' => 'OTP not available for this identifier type.',
            'ar' => 'رمز التحقق غير متاح لهذا النوع من المعرفات.',
        ],

        // Password
        'forgot_password_otp_sent' => [
            'en' => 'Password reset code sent.',
            'ar' => 'تم إرسال رمز إعادة تعيين كلمة المرور.',
        ],
        'password_changed_successfully' => [
            'en' => 'Password changed successfully.',
            'ar' => 'تم تغيير كلمة المرور بنجاح.',
        ],
        'invalid_old_password' => [
            'en' => 'Old password is incorrect.',
            'ar' => 'كلمة المرور القديمة غير صحيحة.',
        ],

        // Email Change
        'email_change_otp_sent' => [
            'en' => 'Verification code sent to your new email.',
            'ar' => 'تم إرسال رمز التحقق إلى بريدك الإلكتروني الجديد.',
        ],
        'email_changed_successfully' => [
            'en' => 'Email changed successfully.',
            'ar' => 'تم تغيير البريد الإلكتروني بنجاح.',
        ],
        'email_change_not_available' => [
            'en' => 'Email change is only available when email is a login identifier.',
            'ar' => 'تغيير البريد الإلكتروني متاح فقط عندما يكون البريد الإلكتروني معرف تسجيل الدخول.',
        ],

        // Locale
        'please_set_the_accept_language_header' => [
            'en' => 'Please set the Accept-Language header.',
            'ar' => 'يرجى تحديد اللغة في رأس الطلب (Accept-Language).',
        ],

        // Languages
        'languages' => [
            'en' => 'Languages retrieved successfully.',
            'ar' => 'تم استرجاع اللغات بنجاح.',
        ],

        // Translations
        'translations' => [
            'en' => 'Translations',
            'ar' => 'الترجمات',
        ],
        'translations_added' => [
            'en' => 'Translations added successfully.',
            'ar' => 'تم إضافة الترجمات بنجاح.',
        ],
        'translations_empty' => [
            'en' => 'No translations provided.',
            'ar' => 'لم يتم تقديم ترجمات.',
        ],

        // Pages
        'pages' => [
            'en' => 'Pages retrieved successfully.',
            'ar' => 'تم استرجاع الصفحات بنجاح.',
        ],
        'page' => [
            'en' => 'Page retrieved successfully.',
            'ar' => 'تم استرجاع الصفحة بنجاح.',
        ],
        'page_not_found' => [
            'en' => 'Page not found.',
            'ar' => 'الصفحة غير موجودة.',
        ],

        // Social Authentication
        'social_auth_requires_email' => [
            'en' => 'Social authentication is not available. Email must be configured as a login identifier.',
            'ar' => 'المصادقة الاجتماعية غير متاحة. يجب تكوين البريد الإلكتروني كمعرف تسجيل الدخول.',
        ],
        'invalid_firebase_token' => [
            'en' => 'Invalid or expired Firebase token.',
            'ar' => 'رمز Firebase غير صالح أو منتهي الصلاحية.',
        ],
        'firebase_email_required' => [
            'en' => 'Email is required for social login.',
            'ar' => 'البريد الإلكتروني مطلوب لتسجيل الدخول الاجتماعي.',
        ],
        'social_provider_not_allowed' => [
            'en' => 'This social provider is not allowed.',
            'ar' => 'هذا المزود الاجتماعي غير مسموح به.',
        ],
        'account_exists_use_password' => [
            'en' => 'Account already exists. Please login with your password.',
            'ar' => 'الحساب موجود بالفعل. يرجى تسجيل الدخول بكلمة المرور.',
        ],
        'social_max_accounts_reached' => [
            'en' => 'Maximum number of social accounts reached.',
            'ar' => 'تم الوصول إلى الحد الأقصى لعدد الحسابات الاجتماعية.',
        ],
        'social_account_already_linked' => [
            'en' => 'This social account is already linked to another user.',
            'ar' => 'هذا الحساب الاجتماعي مرتبط بمستخدم آخر.',
        ],
        'social_provider_already_linked' => [
            'en' => 'This social provider is already linked to your account.',
            'ar' => 'هذا المزود الاجتماعي مرتبط بحسابك بالفعل.',
        ],
        'social_email_mismatch' => [
            'en' => 'Social account email does not match your account email.',
            'ar' => 'البريد الإلكتروني للحساب الاجتماعي لا يتطابق مع بريد حسابك.',
        ],
        'social_account_linked' => [
            'en' => 'Social account linked successfully.',
            'ar' => 'تم ربط الحساب الاجتماعي بنجاح.',
        ],
        'social_provider_not_linked' => [
            'en' => 'This social provider is not linked to your account.',
            'ar' => 'هذا المزود الاجتماعي غير مرتبط بحسابك.',
        ],
        'cannot_unlink_social_only' => [
            'en' => 'Cannot unlink social account. Please set a password first.',
            'ar' => 'لا يمكن إلغاء ربط الحساب الاجتماعي. يرجى تعيين كلمة مرور أولاً.',
        ],
        'social_account_unlinked' => [
            'en' => 'Social account unlinked successfully.',
            'ar' => 'تم إلغاء ربط الحساب الاجتماعي بنجاح.',
        ],
        'social_accounts_retrieved' => [
            'en' => 'Social accounts retrieved successfully.',
            'ar' => 'تم استرجاع الحسابات الاجتماعية بنجاح.',
        ],

        // Validation
        'allowed_email_domain' => [
            'en' => 'The email domain must be one of: :domains',
            'ar' => 'يجب أن يكون نطاق البريد الإلكتروني أحد: :domains',
        ],
        'phone_numbers_only' => [
            'en' => 'Phone number must contain only digits (no + or special characters).',
            'ar' => 'يجب أن يحتوي رقم الهاتف على أرقام فقط (بدون + أو أحرف خاصة).',
        ],
        'allowed_phone_country' => [
            'en' => 'Phone number must be from one of the following countries: :countries',
            'ar' => 'يجب أن يكون رقم الهاتف من إحدى الدول التالية: :countries',
        ],
        'invalid_phone_number' => [
            'en' => 'The phone number format is invalid.',
            'ar' => 'صيغة رقم الهاتف غير صالحة.',
        ],
    ];

    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $languages = Language::active()->get();
        $count = 0;

        foreach ($this->apiTranslations as $key => $translations) {
            $translationKey = TranslationKey::firstOrCreate(
                ['key' => $key, 'group' => 'api'],
                ['key' => $key, 'group' => 'api']
            );

            foreach ($languages as $language) {
                $value = $translations[$language->code] ?? $translations['en'];

                TranslationValue::firstOrCreate(
                    [
                        'translation_key_id' => $translationKey->id,
                        'locale' => $language->code,
                    ],
                    [
                        'value' => $value,
                    ]
                );
            }

            $count++;
        }

        $this->command->info("Seeded api translations: {$count} keys");
    }

    /**
     * Seed translations for a newly added language.
     */
    public static function seedForLanguage(string $locale): void
    {
        $seeder = new self;

        foreach ($seeder->apiTranslations as $key => $translations) {
            $translationKey = TranslationKey::where('key', $key)
                ->where('group', 'api')
                ->first();

            if (! $translationKey) {
                $translationKey = TranslationKey::create([
                    'key' => $key,
                    'group' => 'api',
                ]);
            }

            // Use the locale translation if available, otherwise fall back to English
            $value = $translations[$locale] ?? $translations['en'];

            TranslationValue::firstOrCreate(
                [
                    'translation_key_id' => $translationKey->id,
                    'locale' => $locale,
                ],
                [
                    'value' => $value,
                ]
            );
        }
    }
}
