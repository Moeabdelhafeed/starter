<?php

namespace Database\Seeders;

use App\Models\ActivityLog;
use App\Models\AppSetting;
use App\Models\NotificationTemplate;
use App\Models\Page;
use App\Models\User;
use Illuminate\Database\Seeder;

/**
 * Believable demo data for a local or staging environment.
 *
 * Deliberately NOT called from DatabaseSeeder — a production deploy runs
 * `migrate --seed` and must not end up with fake users. Run it explicitly:
 *
 *     php artisan db:seed --class=DemoSeeder
 *
 * Idempotent: every record is keyed on a natural unique column, so running it
 * twice adds nothing.
 */
class DemoSeeder extends Seeder
{
    /**
     * Admin panel accounts, one per role. Passwords are all `password`.
     *
     * @var array<int, array{name: string, email: string, role: string}>
     */
    private array $admins = [
        ['name' => 'Dana Editor', 'email' => 'editor@demo.test', 'role' => 'editor'],
        ['name' => 'Sami Support', 'email' => 'support@demo.test', 'role' => 'support'],
        ['name' => 'Lina Viewer', 'email' => 'viewer@demo.test', 'role' => 'fallback'],
    ];

    /**
     * @var array<int, array{slug: string, name: array{en: string, ar: string}, content: array{en: string, ar: string}}>
     */
    private array $pages = [
        [
            'slug' => 'about-us',
            'name' => ['en' => 'About Us', 'ar' => 'من نحن'],
            'content' => ['en' => 'We build things people actually use.', 'ar' => 'نبني أشياء يستخدمها الناس فعلاً.'],
        ],
        [
            'slug' => 'privacy-policy',
            'name' => ['en' => 'Privacy Policy', 'ar' => 'سياسة الخصوصية'],
            'content' => ['en' => 'We collect the minimum we need to run the app.', 'ar' => 'نجمع الحد الأدنى الذي نحتاجه لتشغيل التطبيق.'],
        ],
    ];

    /**
     * @var array<int, array{type: string, url: string, text: array{en: string, ar: string}}>
     */
    private array $appSettings = [
        ['type' => 'social', 'url' => 'https://instagram.com/demo', 'text' => ['en' => 'Instagram', 'ar' => 'إنستغرام']],
        ['type' => 'social', 'url' => 'https://x.com/demo', 'text' => ['en' => 'X', 'ar' => 'إكس']],
        ['type' => 'contact', 'url' => 'mailto:hello@demo.test', 'text' => ['en' => 'Email us', 'ar' => 'راسلنا']],
        ['type' => 'app_store', 'url' => 'https://apps.apple.com/app/id000000000', 'text' => ['en' => 'App Store', 'ar' => 'آب ستور']],
        ['type' => 'google_play', 'url' => 'https://play.google.com/store/apps/details?id=test.demo', 'text' => ['en' => 'Google Play', 'ar' => 'جوجل بلاي']],
    ];

    /**
     * @var array<int, array{slug: string, topic: string, title: array{en: string, ar: string}, body: array{en: string, ar: string}}>
     */
    private array $templates = [
        [
            'slug' => 'welcome-aboard',
            'topic' => 'users',
            'title' => ['en' => 'Welcome aboard', 'ar' => 'أهلاً بك'],
            'body' => ['en' => 'Thanks for joining. Tap to finish setting up your profile.', 'ar' => 'شكراً لانضمامك. اضغط لإكمال ملفك الشخصي.'],
        ],
        [
            'slug' => 'weekly-digest',
            'topic' => 'users',
            'title' => ['en' => 'Your weekly digest', 'ar' => 'ملخصك الأسبوعي'],
            'body' => ['en' => 'Here is what happened while you were away.', 'ar' => 'إليك ما حدث أثناء غيابك.'],
        ],
    ];

    public function run(): void
    {
        $this->seedAdmins();
        $this->seedAppUsers();
        $this->seedPages();
        $this->seedAppSettings();
        $this->seedNotificationTemplates();
        $this->seedActivityLogs();

        $this->command?->info('Demo data seeded. Admin logins use the password "password".');
    }

    private function seedAdmins(): void
    {
        foreach ($this->admins as $admin) {
            if (User::where('email', $admin['email'])->withTrashed()->exists()) {
                continue;
            }

            User::factory()
                ->withRole($admin['role'])
                ->create([
                    'name' => $admin['name'],
                    'email' => $admin['email'],
                ]);
        }
    }

    private function seedAppUsers(): void
    {
        $existing = User::realUsers()->where('email', 'like', '%@demo.app')->count();

        if ($existing < 8) {
            User::factory()
                ->count(8 - $existing)
                ->appUser()
                ->sequence(fn ($sequence) => ['email' => 'user'.($existing + $sequence->index + 1).'@demo.app'])
                ->create();
        }

        $guests = User::guests()->count();

        if ($guests < 3) {
            User::factory()->count(3 - $guests)->guest()->appUser()->create();
        }
    }

    private function seedPages(): void
    {
        foreach ($this->pages as $data) {
            if (Page::where('slug', $data['slug'])->exists()) {
                continue;
            }

            $page = Page::factory()->create(['slug' => $data['slug']]);

            $this->translate($page, 'name', $data['name']);
            $this->translate($page, 'content', $data['content']);
        }
    }

    private function seedAppSettings(): void
    {
        foreach ($this->appSettings as $index => $data) {
            if (AppSetting::where('type', $data['type'])->where('url', $data['url'])->exists()) {
                continue;
            }

            $setting = AppSetting::factory()->create([
                'type' => $data['type'],
                'url' => $data['url'],
                'sort_order' => $index,
            ]);

            $this->translate($setting, 'text', $data['text']);
        }
    }

    private function seedNotificationTemplates(): void
    {
        foreach ($this->templates as $data) {
            if (NotificationTemplate::where('slug', $data['slug'])->exists()) {
                continue;
            }

            $template = NotificationTemplate::factory()->create([
                'slug' => $data['slug'],
                'topic' => $data['topic'],
            ]);

            $this->translate($template, 'title', $data['title']);
            $this->translate($template, 'body', $data['body']);
        }
    }

    /**
     * A short audit trail so the Dashboard's "Recent Activity" widget has something
     * to show. Only seeded on an empty table — logs are append-only in real use.
     */
    private function seedActivityLogs(): void
    {
        if (ActivityLog::exists()) {
            return;
        }

        $causer = User::whereHas('roles', fn ($query) => $query->where('guard_name', 'web'))->first();

        foreach (Page::all() as $page) {
            ActivityLog::factory()->forSubject($page, $causer)->action('created')->create();
        }

        foreach (User::realUsers()->latest('id')->limit(5)->get() as $user) {
            ActivityLog::factory()->forSubject($user, $causer)->action('updated')->create();
        }
    }

    /**
     * Write one translatable field in every locale the demo copy covers, skipping
     * locales the install does not have enabled.
     *
     * @param  array<string, string>  $values
     */
    private function translate(object $model, string $field, array $values): void
    {
        foreach ($values as $locale => $value) {
            $model->setTranslation($field, $locale, $value);
        }
    }
}
