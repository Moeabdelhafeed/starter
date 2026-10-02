<?php

namespace App\Http\Controllers\Admin\DevSetting;

use App\Events\TestBroadcast;
use App\Helpers\FCMHelper;
use App\Helpers\FcmTopics;
use App\Helpers\ProtectedPages;
use App\Http\Controllers\Controller;
use App\Models\Page;
use App\Models\Role;
use App\Models\User;
use App\Services\Hostinger;
use Closure;
use Database\Seeders\PageSeeder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Inertia\Inertia;
use phpseclib3\Net\SFTP;
use phpseclib3\Net\SSH2;

class DevSettingController extends Controller
{
    private array $colorVars = [
        'primary',
        'primary-foreground',
        'secondary',
        'secondary-foreground',
        'accent',
        'accent-foreground',
        'destructive',
        'background',
        'foreground',
    ];

    private array $envToggles = [
        'APP_USERS',
        'APP_GUESTS',
        'HAS_TRANSLATIONS',
        'HAS_NOTIFICATION_TEMPLATES',
        'HAS_PAGES',
        'HAS_APP_SETTINGS',
        'HAS_DYNAMIC_STORAGE',
        'HAS_ACTIVITY_LOGS',
        'IS_TESTING',
        'APP_DEBUG',
        'IS_OTP_WHATSAPP',
    ];

    private array $deployFlavors = ['dev', 'staging', 'uat', 'production'];

    /**
     * The toggles the Environment card also offers for .env.production. Only keys that
     * are genuinely booleans there — APP_URL and FRONTEND_URL are in
     * $productionOnlyKeys too but are edited as text by updateUrls().
     */
    private const BASE_TOGGLES = ['IS_TESTING'];

    /**
     * The PHP binary on a Hostinger target. The plain `php` on PATH there is an older
     * build, so both the deploy commands and the scheduler cron name this one.
     */
    private const REMOTE_PHP = '/opt/alt/php84/usr/bin/php';

    /**
     * Laravel's scheduler expects to be woken every minute; it decides itself what is due.
     */
    private const SCHEDULER_CRON = '* * * * *';

    public function index()
    {
        $cssPath = resource_path('css/app.css');
        $css = file_get_contents($cssPath);

        $lightColors = $this->parseColors($css, ':root');
        $darkColors = $this->parseColors($css, '.dark');

        $envValues = [];
        foreach ($this->envToggles as $key) {
            $envValues[$key] = filter_var(env($key), FILTER_VALIDATE_BOOLEAN);
        }

        $firebasePath = storage_path('app/private/firebase-auth.json');

        return Inertia::render('DevSetting/Index', [
            'lightColors' => $lightColors,
            'darkColors' => $darkColors,
            'envValues' => $envValues,
            'envToggles' => $this->envToggles,
            'firebaseConfigExists' => file_exists($firebasePath),
            'firebaseCredentialsPath' => env('FIREBASE_CREDENTIALS', ''),
            'baseFirebaseExists' => file_exists($this->baseFirebasePath()),
            'authConfig' => [
                'identifiers' => array_map('trim', explode(',', env('AUTH_IDENTIFIERS', 'email'))),
                'has_email_field' => filter_var(env('HAS_EMAIL_FIELD', true), FILTER_VALIDATE_BOOLEAN),
                'has_phone_field' => filter_var(env('HAS_PHONE_FIELD', false), FILTER_VALIDATE_BOOLEAN),
                'has_username_field' => filter_var(env('HAS_USERNAME_FIELD', false), FILTER_VALIDATE_BOOLEAN),
                'auth_mode' => strtolower((string) env('AUTH_MODE', 'password')) === 'otp' ? 'otp' : 'password',
            ],
            'socialAuthConfig' => [
                'providers' => array_filter(array_map('trim', explode(',', env('SOCIAL_AUTH_PROVIDERS', 'google.com,apple.com')))),
                'max_accounts' => (int) env('SOCIAL_AUTH_MAX_ACCOUNTS', 0),
                'available_providers' => [
                    ['id' => 'google.com', 'name' => 'Google'],
                    ['id' => 'apple.com', 'name' => 'Apple'],
                    ['id' => 'facebook.com', 'name' => 'Facebook'],
                    ['id' => 'twitter.com', 'name' => 'Twitter'],
                    ['id' => 'github.com', 'name' => 'GitHub'],
                ],
            ],
            'git' => $this->getGitStatus(),
            // ".env.production" is the BASE config inherited by all deploy targets.
            // Exposed to the UI as "base*" so it reads as a foundation, not the prod site.
            'baseMail' => $this->getProductionMail(),
            'localMail' => $this->getLocalMail(),
            'baseTesting' => $this->getProductionEnvValue('IS_TESTING'),
            'deployConfig' => $this->getDeployConfig(),
            'hostingerConfigured' => Hostinger::configured(),
            'deployLog' => session('deploy_log'),
            'availableSeeders' => $this->availableSeeders(),
            'appName' => env('APP_NAME', 'Starter'),
            'apiToken' => [
                'local' => env('APP_X_API_TOKEN', ''),
                'base' => $this->getProductionEnvString('APP_X_API_TOKEN'),
            ],
            'adminCredentials' => [
                'local' => [
                    'ADMIN_EMAIL' => env('ADMIN_EMAIL', ''),
                    'ADMIN_PASSWORD' => env('ADMIN_PASSWORD', ''),
                ],
                'base' => [
                    'ADMIN_EMAIL' => $this->getProductionEnvString('ADMIN_EMAIL'),
                    'ADMIN_PASSWORD' => $this->getProductionEnvString('ADMIN_PASSWORD'),
                ],
            ],
            'urls' => [
                'local' => [
                    'APP_URL' => env('APP_URL', 'http://localhost'),
                    'FRONTEND_URL' => env('FRONTEND_URL', 'http://localhost:5173'),
                ],
                'base' => [
                    'APP_URL' => $this->getProductionEnvString('APP_URL'),
                    'FRONTEND_URL' => $this->getProductionEnvString('FRONTEND_URL'),
                ],
            ],
            'validationConfig' => [
                'allowed_phone_countries' => env('ALLOWED_PHONE_COUNTRIES', 'all'),
                'allowed_email_domains' => env('ALLOWED_EMAIL_DOMAINS', 'all'),
            ],
            'pusherConfig' => [
                'local' => [
                    'app_id' => env('PUSHER_APP_ID', ''),
                    'app_key' => env('PUSHER_APP_KEY', ''),
                    'app_secret' => env('PUSHER_APP_SECRET', ''),
                    'app_cluster' => env('PUSHER_APP_CLUSTER', 'eu'),
                ],
                'base' => $this->getProductionPusher(),
            ],
            'rateLimitConfig' => [
                'api' => [
                    'limit' => (int) env('RATE_LIMIT_API', 60),
                    'decay' => (int) env('RATE_LIMIT_API_DECAY', 1),
                ],
                'auth' => [
                    'limit' => (int) env('RATE_LIMIT_AUTH', 5),
                    'decay' => (int) env('RATE_LIMIT_AUTH_DECAY', 1),
                ],
                'otp' => [
                    'limit' => (int) env('RATE_LIMIT_OTP', 3),
                    'decay' => (int) env('RATE_LIMIT_OTP_DECAY', 5),
                ],
            ],
            'accountDeletionConfig' => [
                'retention_days' => (int) env('ACCOUNT_DELETION_RETENTION_DAYS', 30),
            ],
            'sessionsConfig' => [
                'multi_session_enabled' => filter_var(env('MULTI_SESSION_ENABLED', true), FILTER_VALIDATE_BOOLEAN),
            ],
            'topicsConfig' => [
                'topics' => FcmTopics::bases(),
                'defaults' => FcmTopics::DEFAULTS,
            ],
            'protectedPagesConfig' => [
                'slugs' => ProtectedPages::slugs(),
                // Which of them the CMS actually holds, so a slug that still needs
                // `db:seed --class=PageSeeder` is visible as missing rather than assumed.
                'existing' => Page::whereIn('slug', ProtectedPages::slugs())->pluck('slug')->all(),
            ],
            'reviewerAccounts' => [
                'apple' => [
                    'email' => env('APPLE_REVIEWER_EMAIL', ''),
                    'password' => env('APPLE_REVIEWER_PASSWORD', ''),
                ],
                'google' => [
                    'email' => env('GOOGLE_REVIEWER_EMAIL', ''),
                    'password' => env('GOOGLE_REVIEWER_PASSWORD', ''),
                ],
                'appgallery' => [
                    'email' => env('APPGALLERY_REVIEWER_EMAIL', ''),
                    'password' => env('APPGALLERY_REVIEWER_PASSWORD', ''),
                ],
            ],
        ]);
    }

    public function updateReviewerAccounts(Request $request)
    {
        $validated = $request->validate([
            'apple.email' => ['nullable', 'email', 'max:255'],
            'apple.password' => ['nullable', 'string', 'min:6', 'max:255'],
            'google.email' => ['nullable', 'email', 'max:255'],
            'google.password' => ['nullable', 'string', 'min:6', 'max:255'],
            'appgallery.email' => ['nullable', 'email', 'max:255'],
            'appgallery.password' => ['nullable', 'string', 'min:6', 'max:255'],
        ]);

        $this->setEnvValue('APPLE_REVIEWER_EMAIL', (string) ($validated['apple']['email'] ?? ''));
        $this->setEnvValue('APPLE_REVIEWER_PASSWORD', (string) ($validated['apple']['password'] ?? ''));
        $this->setEnvValue('GOOGLE_REVIEWER_EMAIL', (string) ($validated['google']['email'] ?? ''));
        $this->setEnvValue('GOOGLE_REVIEWER_PASSWORD', (string) ($validated['google']['password'] ?? ''));
        $this->setEnvValue('APPGALLERY_REVIEWER_EMAIL', (string) ($validated['appgallery']['email'] ?? ''));
        $this->setEnvValue('APPGALLERY_REVIEWER_PASSWORD', (string) ($validated['appgallery']['password'] ?? ''));

        $role = Role::where('name', 'user')->where('guard_name', 'api')->first();
        foreach (['apple', 'google', 'appgallery'] as $slot) {
            $email = trim((string) ($validated[$slot]['email'] ?? ''));
            $password = (string) ($validated[$slot]['password'] ?? '');
            if ($email === '' || $password === '') {
                continue;
            }

            $user = User::firstOrNew(['email' => $email]);
            $user->forceFill([
                'name' => ucfirst($slot).' Reviewer',
                'password' => Hash::make($password),
                'is_active' => true,
                'is_reviewer' => true,
                'is_guest' => false,
                'verified_at' => now(),
            ])->save();

            if ($role && ! $user->hasRole($role)) {
                $user->assignRole($role);
            }
        }

        Artisan::call('config:clear');

        return redirect()->back()->with('success', 'Reviewer accounts updated.');
    }

    public function updateTopics(Request $request)
    {
        $validated = $request->validate([
            'topics' => ['array'],
            'topics.*' => ['string', 'regex:'.FcmTopics::NAME_REGEX, 'max:64'],
        ]);

        $clean = array_values(array_unique(array_map(
            fn ($t) => strtolower(trim((string) $t)),
            $validated['topics'] ?? [],
        )));

        $this->setEnvValue('FCM_TOPICS', implode(',', $clean));
        Artisan::call('config:clear');

        return redirect()->back()->with('success', 'Topics updated.');
    }

    /**
     * Rewrites PROTECTED_PAGES and makes the CMS match it: a slug added here is created
     * by PageSeeder, and a slug removed here takes its page with it. The list is the
     * whole gesture — no seeder command to remember either way.
     *
     * **Removing a slug deletes that page and its translations, with no undo** (pages
     * are not soft-deleted). Only the slugs removed by *this* request are deleted, never
     * "everything the list does not name": PROTECTED_PAGES is a string in .env, and a
     * blank one (`PROTECTED_PAGES=`, which reads as an empty list rather than falling
     * back to the config default) would otherwise wipe every protected page at once.
     */
    public function updateProtectedPages(Request $request)
    {
        $validated = $request->validate([
            'slugs' => ['array'],
            'slugs.*' => ['string', 'regex:'.ProtectedPages::SLUG_REGEX, 'max:255'],
        ]);

        $clean = array_values(array_unique(array_map(
            fn ($slug) => strtolower(trim((string) $slug)),
            $validated['slugs'] ?? [],
        )));

        // Read before the write: what the list drops is what gets deleted.
        $previous = ProtectedPages::slugs();

        $this->setEnvValue('PROTECTED_PAGES', implode(',', $clean));
        Artisan::call('config:clear');

        // `config:clear` drops the cached file; this process already holds the old value,
        // and the seeder reads the list through config().
        config(['features.protected_pages' => implode(',', $clean)]);

        // Deleted before seeding: the page is no longer protected as of the line above,
        // so Page::booting()'s guard lets it go.
        $removed = collect($previous)->diff($clean);
        $deleted = collect();

        foreach (Page::whereIn('slug', $removed)->get() as $page) {
            $page->deleteImage();
            $page->delete();
            $deleted->push($page->slug);
        }

        $existing = Page::whereIn('slug', $clean)->pluck('slug');
        (new PageSeeder)->run();
        $created = collect($clean)->diff($existing);

        $notes = collect([
            $created->isNotEmpty() ? 'created '.$created->implode(', ') : null,
            $deleted->isNotEmpty() ? 'deleted '.$deleted->implode(', ') : null,
        ])->filter();

        return redirect()->back()->with('success', $notes->isEmpty()
            ? 'Protected pages updated.'
            : 'Protected pages updated — '.$notes->implode('; ').'.');
    }

    public function testTopicBroadcast(Request $request)
    {
        $validated = $request->validate([
            'topic' => ['required', 'string', 'regex:'.FcmTopics::NAME_REGEX, 'max:64'],
            'title' => ['nullable', 'string', 'max:255'],
            'body' => ['nullable', 'string', 'max:1000'],
        ]);

        if (! FcmTopics::has($validated['topic'])) {
            return redirect()->back()->with('error', 'Topic not registered.');
        }

        $result = FCMHelper::sendToTopic(
            $validated['topic'],
            $validated['title'] ?? 'Test',
            $validated['body'] ?? 'Test broadcast from DevSettings',
        );

        if (! ($result['success'] ?? false)) {
            return redirect()->back()->with('error', $result['message'] ?? 'Topic broadcast failed.');
        }

        return redirect()->back()->with('success', 'Broadcast sent to topic '.$validated['topic']);
    }

    public function updateColors(Request $request)
    {
        $validated = $request->validate([
            'colors' => ['required', 'array'],
            'colors.*' => ['required', 'string', 'regex:/^#[0-9a-fA-F]{6}$/'],
            'mode' => ['required', 'in:light,dark'],
        ]);

        $cssPath = resource_path('css/app.css');
        $css = file_get_contents($cssPath);

        $selector = $validated['mode'] === 'dark' ? '.dark' : ':root';
        $pattern = $selector === ':root'
            ? '/(:root\s*\{)(.*?)(\})/s'
            : '/(\.dark\s*\{)(.*?)(\})/s';

        if (preg_match($pattern, $css, $match)) {
            $block = $match[2];

            foreach ($validated['colors'] as $var => $hex) {
                if (! in_array($var, $this->colorVars)) {
                    continue;
                }

                $oklch = $this->hexToOklch($hex);
                $block = preg_replace(
                    '/(--'.preg_quote($var, '/').':\s*).+?;/',
                    '${1}'.$oklch.';',
                    $block
                );
            }

            $css = preg_replace($pattern, '${1}'.$block.'${3}', $css);
        }

        file_put_contents($cssPath, $css);

        $modeLabel = $validated['mode'] === 'dark' ? 'Dark' : 'Light';

        return redirect()->back()->with('success', $modeLabel.' colors updated.');
    }

    public function buildAssets()
    {
        $output = [];
        $exitCode = 0;
        exec('cd '.base_path().' && npm run build 2>&1', $output, $exitCode);

        if ($exitCode === 0) {
            return redirect()->back()->with('success', 'Assets built successfully.');
        }

        return redirect()->back()->with('error', 'Build failed: '.implode("\n", array_slice($output, -5)));
    }

    /**
     * Write every changed feature toggle in one request.
     *
     * The panel batches them behind a Save button rather than writing on each click:
     * one write rewrites .env, syncs .env.production and clears the config cache, so a
     * pass over eleven switches used to be eleven round trips of that. `base` carries
     * the .env.production overrides the same card edits, so saving it is a single
     * request whichever side was touched.
     */
    public function updateEnv(Request $request)
    {
        $validated = $request->validate([
            'values' => [
                'required',
                'array',
                'min:1',
                $this->onlyKeysRule($this->envToggles),
            ],
            'values.*' => ['required', 'boolean'],
            'base' => [
                'nullable',
                'array',
                $this->onlyKeysRule(self::BASE_TOGGLES),
            ],
            'base.*' => ['required', 'boolean'],
        ]);

        $base = $validated['base'] ?? [];

        // Refuse the whole batch rather than half-applying it: a card that reported
        // success while silently dropping its production half is worse than an error.
        if ($base !== [] && ! file_exists(base_path('.env.production'))) {
            return redirect()->back()->with('error', 'No .env.production found. Save production config first.');
        }

        foreach ($validated['values'] as $key => $on) {
            $this->setEnvValue($key, $on ? 'true' : 'false');
        }

        foreach ($base as $key => $on) {
            $this->writeEnvKey(base_path('.env.production'), $key, $on ? 'true' : 'false');
        }

        Artisan::call('config:clear');

        $count = count($validated['values']) + count($base);

        return redirect()->back()->with('success', $count.' setting'.($count === 1 ? '' : 's').' updated.');
    }

    /**
     * Validate the *keys* of an array payload, which `array` and `field.*` rules cannot
     * express. Unknown keys fail loudly instead of being intersected away — silently
     * dropping one would report a save that never happened.
     *
     * @param  array<int, string>  $allowed
     */
    private function onlyKeysRule(array $allowed): Closure
    {
        return function (string $attribute, mixed $value, Closure $fail) use ($allowed): void {
            $unknown = array_diff(array_keys((array) $value), $allowed);

            if ($unknown !== []) {
                $fail('Unknown '.$attribute.' keys: '.implode(', ', $unknown));
            }
        };
    }

    public function uploadFirebaseJson(Request $request)
    {
        $request->validate([
            'firebase_json' => ['required', 'file', 'mimes:json', 'max:1024'],
        ]);

        $file = $request->file('firebase_json');
        $contents = file_get_contents($file->getRealPath());

        $json = json_decode($contents, true);
        if (! $json || ! isset($json['project_id'])) {
            return redirect()->back()->with('error', 'Invalid Firebase credentials JSON.');
        }

        $destination = storage_path('app/private/firebase-auth.json');
        file_put_contents($destination, $contents);

        $this->setEnvValue('FIREBASE_CREDENTIALS', 'storage/app/private/firebase-auth.json');

        Artisan::call('config:clear');

        return redirect()->back()->with('success', 'Firebase credentials uploaded for project: '.$json['project_id']);
    }

    public function uploadBaseFirebase(Request $request)
    {
        $request->validate([
            'firebase_json' => ['required', 'file', 'mimes:json', 'max:1024'],
        ]);

        $contents = file_get_contents($request->file('firebase_json')->getRealPath());
        $json = json_decode($contents, true);
        if (! $json || ! isset($json['project_id'])) {
            return redirect()->back()->with('error', 'Invalid Firebase credentials JSON.');
        }

        file_put_contents($this->baseFirebasePath(), $contents);

        return redirect()->back()->with('success', 'Base Firebase credentials saved for project: '.$json['project_id']);
    }

    public function deleteBaseFirebase()
    {
        if (file_exists($this->baseFirebasePath())) {
            @unlink($this->baseFirebasePath());
        }

        return redirect()->back()->with('success', 'Base Firebase credentials removed.');
    }

    public function uploadFlavorFirebase(Request $request)
    {
        $validated = $request->validate([
            'flavor' => ['required', 'in:'.implode(',', $this->deployFlavors)],
            'firebase_json' => ['required', 'file', 'mimes:json', 'max:1024'],
        ]);

        $contents = file_get_contents($request->file('firebase_json')->getRealPath());
        $json = json_decode($contents, true);
        if (! $json || ! isset($json['project_id'])) {
            return redirect()->back()->with('error', 'Invalid Firebase credentials JSON.');
        }

        file_put_contents($this->firebasePathForFlavor($validated['flavor']), $contents);

        return redirect()->back()->with('success', ucfirst($validated['flavor']).' Firebase credentials saved for project: '.$json['project_id']);
    }

    public function deleteFlavorFirebase(Request $request)
    {
        $validated = $request->validate([
            'flavor' => ['required', 'in:'.implode(',', $this->deployFlavors)],
        ]);

        $path = $this->firebasePathForFlavor($validated['flavor']);
        if (file_exists($path)) {
            @unlink($path);
        }

        return redirect()->back()->with('success', ucfirst($validated['flavor']).' Firebase credentials removed.');
    }

    public function testFcm(Request $request)
    {
        $validated = $request->validate([
            'token' => ['required', 'string'],
        ]);

        $result = FCMHelper::send(
            $validated['token'],
            'Test Notification',
            'This is a test push notification from '.config('app.name'),
            ['type' => 'test']
        );

        if ($result['success']) {
            return redirect()->back()->with('success', 'Test notification sent successfully.');
        }

        return redirect()->back()->with('error', $result['message'] ?? 'Failed to send test notification.');
    }

    public function updateAuth(Request $request)
    {
        $validated = $request->validate([
            'identifiers' => ['required', 'array', 'min:1'],
            'identifiers.*' => ['required', 'string', 'in:email,phone'],
            'has_email_field' => ['required', 'boolean'],
            'has_phone_field' => ['required', 'boolean'],
            'has_username_field' => ['required', 'boolean'],
            'auth_mode' => ['required', 'in:password,otp'],
        ]);

        $this->setEnvValue('AUTH_IDENTIFIERS', implode(',', $validated['identifiers']));
        $this->setEnvValue('HAS_EMAIL_FIELD', $validated['has_email_field'] ? 'true' : 'false');
        $this->setEnvValue('HAS_PHONE_FIELD', $validated['has_phone_field'] ? 'true' : 'false');
        $this->setEnvValue('HAS_USERNAME_FIELD', $validated['has_username_field'] ? 'true' : 'false');
        $this->setEnvValue('AUTH_MODE', $validated['auth_mode']);

        Artisan::call('config:clear');

        return redirect()->back()->with('success', 'Auth configuration updated.');
    }

    public function updateSocialAuth(Request $request)
    {
        $validated = $request->validate([
            'providers' => ['nullable', 'array'],
            'providers.*' => ['required', 'string', 'in:google.com,apple.com,facebook.com,twitter.com,github.com'],
            'max_accounts' => ['required', 'integer', 'min:0', 'max:10'],
        ]);

        $providers = $validated['providers'] ?? [];
        $this->setEnvValue('SOCIAL_AUTH_PROVIDERS', implode(',', $providers));
        $this->setEnvValue('SOCIAL_AUTH_MAX_ACCOUNTS', (string) $validated['max_accounts']);

        Artisan::call('config:clear');

        return redirect()->back()->with('success', 'Social auth configuration updated.');
    }

    public function updateValidation(Request $request)
    {
        $validated = $request->validate([
            'allowed_phone_countries' => ['required', 'string', 'max:500'],
            'allowed_email_domains' => ['required', 'string', 'max:500'],
        ]);

        $this->setEnvValue('ALLOWED_PHONE_COUNTRIES', $validated['allowed_phone_countries']);
        $this->setEnvValue('ALLOWED_EMAIL_DOMAINS', $validated['allowed_email_domains']);

        Artisan::call('config:clear');

        return redirect()->back()->with('success', __('admin.validation_config_updated'));
    }

    public function updateRateLimiting(Request $request)
    {
        $validated = $request->validate([
            'api_limit' => ['required', 'integer', 'min:1', 'max:1000'],
            'api_decay' => ['required', 'integer', 'min:1', 'max:60'],
            'auth_limit' => ['required', 'integer', 'min:1', 'max:100'],
            'auth_decay' => ['required', 'integer', 'min:1', 'max:60'],
            'otp_limit' => ['required', 'integer', 'min:1', 'max:20'],
            'otp_decay' => ['required', 'integer', 'min:1', 'max:60'],
        ]);

        $this->setEnvValue('RATE_LIMIT_API', (string) $validated['api_limit']);
        $this->setEnvValue('RATE_LIMIT_API_DECAY', (string) $validated['api_decay']);
        $this->setEnvValue('RATE_LIMIT_AUTH', (string) $validated['auth_limit']);
        $this->setEnvValue('RATE_LIMIT_AUTH_DECAY', (string) $validated['auth_decay']);
        $this->setEnvValue('RATE_LIMIT_OTP', (string) $validated['otp_limit']);
        $this->setEnvValue('RATE_LIMIT_OTP_DECAY', (string) $validated['otp_decay']);

        Artisan::call('config:clear');

        return redirect()->back()->with('success', __('admin.rate_limit_config_updated'));
    }

    public function updateAccountDeletionConfig(Request $request)
    {
        $validated = $request->validate([
            'retention_days' => ['required', 'integer', 'min:1', 'max:365'],
        ]);

        $this->setEnvValue('ACCOUNT_DELETION_RETENTION_DAYS', (string) $validated['retention_days']);

        Artisan::call('config:clear');

        return redirect()->back()->with('success', __('admin.account_deletion_config_updated'));
    }

    public function updateSessionsConfig(Request $request)
    {
        $validated = $request->validate([
            'multi_session_enabled' => ['required', 'boolean'],
        ]);

        $this->setEnvValue('MULTI_SESSION_ENABLED', $validated['multi_session_enabled'] ? 'true' : 'false');

        Artisan::call('config:clear');

        return redirect()->back()->with('success', __('admin.sessions_config_updated'));
    }

    public function updatePusher(Request $request)
    {
        $validated = $request->validate([
            'app_id' => ['required', 'string', 'max:255'],
            'app_key' => ['required', 'string', 'max:255'],
            'app_secret' => ['required', 'string', 'max:255'],
            'app_cluster' => ['required', 'string', 'max:50'],
        ]);

        // Backend Pusher config
        $this->setEnvValue('PUSHER_APP_ID', $validated['app_id']);
        $this->setEnvValue('PUSHER_APP_KEY', $validated['app_key']);
        $this->setEnvValue('PUSHER_APP_SECRET', $validated['app_secret']);
        $this->setEnvValue('PUSHER_APP_CLUSTER', $validated['app_cluster']);
        $this->setEnvValue('BROADCAST_CONNECTION', 'pusher');

        // Frontend Vite env variables for Echo
        $this->setEnvValue('VITE_PUSHER_APP_KEY', $validated['app_key']);
        $this->setEnvValue('VITE_PUSHER_APP_CLUSTER', $validated['app_cluster']);

        Artisan::call('config:clear');

        return redirect()->back()->with('success', __('admin.pusher_config_updated'));
    }

    public function testBroadcast(Request $request)
    {
        $validated = $request->validate([
            'user_id' => ['required', 'integer', 'exists:users,id'],
        ]);

        try {
            broadcast(new TestBroadcast(
                $validated['user_id'],
                'Test broadcast message from DevSettings at '.now()->toDateTimeString()
            ));

            return redirect()->back()->with('success', __('admin.broadcast_test_sent'));
        } catch (\Exception $e) {
            Log::error('Broadcast test failed: '.$e->getMessage());

            return redirect()->back()->with('error', __('admin.broadcast_test_failed').': '.$e->getMessage());
        }
    }

    public function updateProductionPusher(Request $request)
    {
        $validated = $request->validate([
            'app_id' => ['required', 'string', 'max:255'],
            'app_key' => ['required', 'string', 'max:255'],
            'app_secret' => ['required', 'string', 'max:255'],
            'app_cluster' => ['required', 'string', 'max:50'],
        ]);

        $prodPath = base_path('.env.production');
        if (! file_exists($prodPath)) {
            return redirect()->back()->with('error', 'No .env.production found.');
        }

        $this->writeEnvKey($prodPath, 'PUSHER_APP_ID', $validated['app_id']);
        $this->writeEnvKey($prodPath, 'PUSHER_APP_KEY', $validated['app_key']);
        $this->writeEnvKey($prodPath, 'PUSHER_APP_SECRET', $validated['app_secret']);
        $this->writeEnvKey($prodPath, 'PUSHER_APP_CLUSTER', $validated['app_cluster']);
        $this->writeEnvKey($prodPath, 'BROADCAST_CONNECTION', 'pusher');
        $this->writeEnvKey($prodPath, 'VITE_PUSHER_APP_KEY', $validated['app_key']);
        $this->writeEnvKey($prodPath, 'VITE_PUSHER_APP_CLUSTER', $validated['app_cluster']);

        return redirect()->back()->with('success', __('admin.production_pusher_config_updated'));
    }

    public function uploadLogo(Request $request)
    {
        $request->validate([
            'logo' => ['required', 'image', 'max:5120'],
        ]);

        $file = $request->file('logo');
        $image = $this->createImageWithAlpha($file);

        if (! $image) {
            return redirect()->back()->with('error', 'Could not process image.');
        }

        imagepng($image, public_path('images/logo.png'));
        imagepng($image, resource_path('js/resources/images/logo.png'));
        imagedestroy($image);

        return redirect()->back()->with('success', 'Logo updated successfully.');
    }

    public function uploadDarkLogo(Request $request)
    {
        $request->validate([
            'logo' => ['required', 'image', 'max:5120'],
        ]);

        $image = $this->createImageWithAlpha($request->file('logo'));

        if (! $image) {
            return redirect()->back()->with('error', 'Could not process image.');
        }

        imagepng($image, public_path('images/logo-dark.png'));
        imagepng($image, resource_path('js/resources/images/logo-dark.png'));
        imagedestroy($image);

        return redirect()->back()->with('success', 'Dark logo updated successfully.');
    }

    public function uploadFavicon(Request $request)
    {
        $request->validate([
            'favicon' => ['required', 'image', 'max:2048'],
        ]);

        $file = $request->file('favicon');
        $image = $this->createImageWithAlpha($file);

        if (! $image) {
            return redirect()->back()->with('error', 'Could not process image.');
        }

        imagepng($image, public_path('favicon.ico'));
        imagepng($image, resource_path('js/resources/favicon.ico'));
        imagedestroy($image);

        return redirect()->back()->with('success', 'Favicon updated successfully.');
    }

    /**
     * Regenerate the API docs (Scribe: /docs, /docs.postman, /docs.openapi) against the
     * current routes + .env, then sync Starter.postman_collection.json — same pipeline as
     * `composer api-docs`, triggerable from the UI instead of the CLI.
     */
    public function generateApiDocs()
    {
        Artisan::call('scribe:generate', ['--no-interaction' => true]);
        Artisan::call('scribe:polish');
        Artisan::call('app:sync-postman-collection');

        $generated = storage_path('app/private/scribe/collection.json');
        if (file_exists($generated)) {
            copy($generated, base_path('Starter.postman_collection.json'));
        }

        return redirect()->back()->with('success', 'API docs generated.');
    }

    public function updateAppName(Request $request)
    {
        $validated = $request->validate([
            'APP_NAME' => ['required', 'string', 'max:255'],
        ]);

        // Update both .env files — app name should be the same everywhere
        $this->writeEnvKey(base_path('.env'), 'APP_NAME', $validated['APP_NAME']);

        $prodPath = base_path('.env.production');
        if (file_exists($prodPath)) {
            $this->writeEnvKey($prodPath, 'APP_NAME', $validated['APP_NAME']);
        }

        Artisan::call('config:clear');

        return redirect()->back()->with('success', 'App name updated.');
    }

    public function generateApiToken(Request $request)
    {
        $validated = $request->validate([
            'target' => ['required', 'in:local,production,both'],
        ]);

        $token = bin2hex(random_bytes(32));
        $target = $validated['target'];

        if ($target === 'local' || $target === 'both') {
            $this->writeEnvKey(base_path('.env'), 'APP_X_API_TOKEN', $token);
            Artisan::call('config:clear');
        }

        if ($target === 'production' || $target === 'both') {
            $this->rebuildProductionEnv(['APP_X_API_TOKEN' => $token]);
        }

        return redirect()->back()->with('success', ucfirst($target).' API token generated.');
    }

    public function updateAdminCredentials(Request $request)
    {
        $validated = $request->validate([
            'target' => ['required', 'in:local,production'],
            'ADMIN_EMAIL' => ['required', 'string', 'email', 'max:255'],
            'ADMIN_PASSWORD' => ['required', 'string', 'max:255', Password::defaults()],
        ]);

        $target = $validated['target'];

        if ($target === 'local') {
            $this->writeEnvKey(base_path('.env'), 'ADMIN_EMAIL', $validated['ADMIN_EMAIL']);
            $this->writeEnvKey(base_path('.env'), 'ADMIN_PASSWORD', $validated['ADMIN_PASSWORD']);
            Artisan::call('config:clear');

            // Update admin user directly (env() won't reflect new values in same request)
            $admin = User::whereHas('roles', fn ($q) => $q->where('name', 'super_admin')->where('guard_name', 'web'))->first();
            if ($admin) {
                $admin->update([
                    'email' => $validated['ADMIN_EMAIL'],
                    'password' => $validated['ADMIN_PASSWORD'],
                ]);
            }
        } else {
            $this->rebuildProductionEnv([
                'ADMIN_EMAIL' => $validated['ADMIN_EMAIL'],
                'ADMIN_PASSWORD' => $validated['ADMIN_PASSWORD'],
            ]);
        }

        return redirect()->back()->with('success', ucfirst($target).' admin credentials updated.');
    }

    public function updateLocalMail(Request $request)
    {
        $validated = $request->validate([
            'MAIL_MAILER' => ['required', 'string', 'max:255'],
            'MAIL_HOST' => ['required', 'string', 'max:255'],
            'MAIL_PORT' => ['required', 'string', 'max:10'],
            'MAIL_USERNAME' => ['required', 'string', 'max:255'],
            'MAIL_PASSWORD' => ['nullable', 'string', 'max:255'],
            'MAIL_ENCRYPTION' => ['required', 'string', 'max:10'],
            'MAIL_FROM_ADDRESS' => ['required', 'string', 'email', 'max:255'],
        ]);

        foreach ($validated as $key => $value) {
            $this->setEnvValue($key, $value ?? '');
        }

        Artisan::call('config:clear');

        return redirect()->back()->with('success', 'Local mail config updated.');
    }

    public function updateProductionMail(Request $request)
    {
        $validated = $request->validate([
            'MAIL_MAILER' => ['required', 'string', 'max:255'],
            'MAIL_HOST' => ['required', 'string', 'max:255'],
            'MAIL_PORT' => ['required', 'string', 'max:10'],
            'MAIL_USERNAME' => ['required', 'string', 'max:255'],
            'MAIL_PASSWORD' => ['nullable', 'string', 'max:255'],
            'MAIL_ENCRYPTION' => ['required', 'string', 'max:10'],
            'MAIL_FROM_ADDRESS' => ['required', 'string', 'email', 'max:255'],
        ]);

        $this->rebuildProductionEnv($validated);

        return redirect()->back()->with('success', 'Production mail config saved.');
    }

    public function pushToGithub()
    {
        set_time_limit(120);

        $base = base_path();

        if (! is_dir($base.'/.git')) {
            return redirect()->back()->with('error', __('admin.no_git_repo'));
        }

        Log::info('[Git Push] Starting push to GitHub');

        // Push only (no commit - use commitChanges for that)
        exec("cd {$base} && git push 2>&1", $pushOutput, $pushExit);

        Log::info('[Git Push] Result', [
            'exit' => $pushExit,
            'output' => implode("\n", array_slice($pushOutput, -5)),
        ]);

        if ($pushExit === 0) {
            $message = implode("\n", array_slice($pushOutput, -3));
            if (str_contains($message, 'Everything up-to-date')) {
                return redirect()->back()->with('success', __('admin.already_up_to_date'));
            }

            return redirect()->back()->with('success', __('admin.pushed_to_github'));
        }

        return redirect()->back()->with('error', 'Push failed: '.implode("\n", array_slice($pushOutput, -5)));
    }

    /**
     * Stores the Hostinger API token in the local `.env`. Sending an empty value clears
     * it, which switches the whole provisioning feature back off.
     *
     * `setEnvValue()` would normally copy a key to `.env.production` as well; this one is
     * in $neverSyncToProductionKeys, because that file is written verbatim onto every
     * deploy target and the panel that uses the token only exists locally.
     */
    public function updateHostingerToken(Request $request)
    {
        $validated = $request->validate([
            // Hostinger issues opaque tokens; the charset guard is only here to keep a
            // pasted newline or quote out of the .env line.
            'token' => ['nullable', 'string', 'max:512', 'regex:/^[A-Za-z0-9._\-|]*$/'],
        ]);

        $token = trim((string) ($validated['token'] ?? ''));

        $this->setEnvValue('HOSTINGER_API_TOKEN', $token);
        Artisan::call('config:clear');

        // The cached file is gone, but this process still holds the old value and the
        // response is rendered from it.
        config(['services.hostinger.token' => $token]);

        return redirect()->back()->with('success', $token === ''
            ? 'Hostinger API token removed.'
            : 'Hostinger API token saved.');
    }

    /**
     * What the provision modal's pickers need: the websites on the account (to attach a
     * flavor to one, or hang a subdomain off it), the hosting plans and the registered
     * domains (to create a new website). Answers empty lists rather than an error when no token is
     * configured, so the panel just hides the feature.
     *
     * Datacenters are fetched separately with `order_id`, since they are per plan and
     * only matter for the first website on a new one.
     *
     * @return JsonResponse
     */
    public function hostingerAccount(Request $request, Hostinger $hostinger)
    {
        if (! Hostinger::configured()) {
            return response()->json([
                'websites' => [], 'orders' => [], 'domains' => [], 'datacenters' => [], 'configured' => false,
            ]);
        }

        $orderId = $request->integer('order_id');

        try {
            return response()->json([
                'websites' => $hostinger->websites(),
                'orders' => $hostinger->orders(),
                'domains' => $hostinger->domains(),
                'datacenters' => $orderId > 0 ? $hostinger->datacenters($orderId) : [],
                'configured' => true,
            ]);
        } catch (\RuntimeException $e) {
            return response()->json([
                'websites' => [], 'orders' => [], 'domains' => [], 'datacenters' => [],
                'configured' => true, 'error' => $e->getMessage(),
            ], 422);
        }
    }

    /**
     * Every database on the Hostinger account, for the Databases section.
     *
     * The hosting username belongs to the plan rather than to a site, so the accounts
     * are collected from the website list and de-duplicated before asking each one for
     * its databases.
     */
    public function hostingerDatabases(Hostinger $hostinger): JsonResponse
    {
        if (! Hostinger::configured()) {
            return response()->json(['databases' => [], 'configured' => false]);
        }

        try {
            $usernames = collect($hostinger->websites())
                ->pluck('username')
                ->filter()
                ->unique()
                ->values();

            $databases = $usernames
                ->flatMap(fn (string $username): array => collect($hostinger->databases($username))
                    ->map(fn (array $row): array => [
                        'username' => $username,
                        'name' => (string) ($row['name'] ?? ''),
                        'user' => (string) ($row['user'] ?? ''),
                        'domain' => (string) ($row['domain'] ?? ''),
                        'host' => (string) ($row['host'] ?? ''),
                        'port' => (int) ($row['port'] ?? 3306),
                        'disk_usage_mb' => (int) ($row['disk_usage_mb'] ?? 0),
                        'max_size_mb' => (int) ($row['max_size_mb'] ?? 0),
                        'created_at' => (string) ($row['created_at'] ?? ''),
                    ])
                    ->all())
                ->sortBy('name')
                ->values();

            return response()->json(['databases' => $databases, 'configured' => true]);
        } catch (\RuntimeException $e) {
            return response()->json(['databases' => [], 'configured' => true, 'error' => $e->getMessage()], 422);
        }
    }

    /**
     * A one-shot phpMyAdmin sign-on URL for one database.
     *
     * Fetched per click and handed straight to the browser to open in a new tab. The URL
     * carries its own session id, so it is a live credential for that database — it is
     * never stored in `.deploy.json`, never logged, and never sent as part of the page
     * payload. See Hostinger::phpMyAdminLink().
     */
    public function phpMyAdminLink(Request $request, Hostinger $hostinger): JsonResponse
    {
        $validated = $request->validate([
            'username' => ['required', 'string', 'max:64'],
            'name' => ['required', 'string', 'max:128'],
        ]);

        if (! Hostinger::configured()) {
            return response()->json(['error' => 'No Hostinger API token configured.'], 422);
        }

        try {
            return response()->json(['link' => $hostinger->phpMyAdminLink($validated['username'], $validated['name'])]);
        } catch (\RuntimeException $e) {
            return response()->json(['error' => $e->getMessage()], 422);
        }
    }

    /**
     * Creates one flavor's hosting on Hostinger and writes the result into
     * `.deploy.json` — the domain and all five DB_* values, which are otherwise typed
     * in by hand after clicking through hPanel.
     *
     * Per flavor and never part of a deploy: this creates real, billable infrastructure
     * on a live account, so it only ever runs from a button an admin pressed for one
     * flavor.
     *
     * `mode` is `existing` (point the flavor at a site that is already there) or `website`
     * (create one — for a whole domain, a subdomain of one, or a generated free host).
     *
     * Creating always goes through **create-website**, never the subdomains endpoint:
     * Hostinger can build a subdomain either way, and only this one produces an
     * independent website with its own hPanel dashboard and its own document root. The
     * subdomains endpoint makes a subfolder of the parent instead, which has no dashboard
     * of its own and is served from the parent's public_html. A subdomain is therefore
     * just a `domain` with a prefix on it, composed before it gets here.
     *
     * The database password is generated here and written to `.deploy.json`, because the
     * API never returns it — asked for it again later, there is nowhere to read it from.
     */
    public function provisionFlavor(Request $request, Hostinger $hostinger)
    {
        if (! Hostinger::configured()) {
            return redirect()->back()->with('error', 'No Hostinger API token. Add HOSTINGER_API_TOKEN below first.');
        }

        $validated = $request->validate([
            'flavor' => ['required', 'in:'.implode(',', $this->deployFlavors)],
            'mode' => ['required', 'in:existing,website'],
            // Known up front only when attaching to a site that already exists. A new
            // website has no username until Hostinger puts it somewhere, so it is read
            // back after creating it.
            'username' => ['required_if:mode,existing', 'nullable', 'string', 'max:255', 'regex:/^[A-Za-z0-9_.-]+$/'],
            // The site the flavor attaches to, or the host the new website is created for.
            // Hostinger generates the name for a free subdomain, so nothing is supplied.
            'free_subdomain' => ['boolean'],
            'domain' => [
                Rule::requiredIf(fn (): bool => ! $request->boolean('free_subdomain')),
                'nullable', 'string', 'max:255', 'regex:/^[a-z0-9]([a-z0-9.-]*[a-z0-9])?$/i', 'not_regex:/^www\./i',
            ],
            'order_id' => ['required_if:mode,website', 'nullable', 'integer', 'min:1'],
            'datacenter_code' => ['nullable', 'string', 'max:32', 'regex:/^[A-Za-z0-9_-]+$/'],
            // Hostinger prefixes the account username onto both; these are the suffixes.
            'db_name' => ['required', 'string', 'max:32', 'regex:/^[a-z0-9_]+$/i'],
            'db_user' => ['required', 'string', 'max:32', 'regex:/^[a-z0-9_]+$/i'],
            'scheduler_cron' => ['boolean'],
        ]);

        $flavor = $validated['flavor'];

        try {
            $domain = (string) ($validated['domain'] ?? '');
            $username = $validated['username'] ?? '';

            if ($validated['mode'] === 'website') {
                if ($request->boolean('free_subdomain')) {
                    $domain = $hostinger->generateFreeSubdomain();
                }

                // "Add a website" is what gives the host its own hPanel dashboard and its
                // own document root. Creating it is also how the username is discovered.
                $username = $hostinger->createWebsite(
                    $domain,
                    (int) $validated['order_id'],
                    $validated['datacenter_code'] ?? null,
                );
            }

            $db = $hostinger->createDatabase(
                $username,
                $validated['db_name'],
                $validated['db_user'],
                Hostinger::databasePassword(),
                $domain,
            );
        } catch (\RuntimeException $e) {
            return redirect()->back()->with('error', $e->getMessage());
        }

        $documentRoot = $hostinger->documentRoot($domain);

        // The scheduler cron, after the database so a failure here cannot cost one. It is
        // reported rather than fatal: the hosting and the database are already made, and
        // a cron job is the one part that is trivial to add by hand afterwards.
        $cronNote = '';

        if ($request->boolean('scheduler_cron', true)) {
            try {
                $cronNote = $documentRoot === null
                    ? ' Scheduler cron skipped: no document root reported yet.'
                    : ($hostinger->createCronJob($username, self::SCHEDULER_CRON, $this->schedulerCommand($documentRoot))
                        ? ' Scheduler cron added.'
                        : ' Scheduler cron already present.');
            } catch (\RuntimeException $e) {
                $cronNote = ' Scheduler cron failed: '.$e->getMessage();
            }
        }

        $config = $this->getDeployConfig();
        $config['flavors'][$flavor]['domain'] = $domain;
        $config['flavors'][$flavor]['db'] = $db;
        // Recorded, not derived: a subdomain is served from the parent's
        // public_html/{directory}, so ~/domains/{domain}/public_html would be a folder
        // nothing serves. Null leaves deploy on its own derivation.
        $config['flavors'][$flavor]['document_root'] = $documentRoot;

        $this->writeDeployConfig($config);

        return redirect()->back()->with('success', "Provisioned {$flavor}: {$domain}, database {$db['DB_DATABASE']}.".$cronNote);
    }

    public function saveDeployConfig(Request $request)
    {
        $validated = $request->validate([
            'share_ssh' => ['required', 'boolean'],
            'ssh.host' => ['nullable', 'string', 'max:255'],
            'ssh.port' => ['nullable', 'integer', 'min:1', 'max:65535'],
            'ssh.username' => ['nullable', 'string', 'max:255'],
            'ssh.password' => ['nullable', 'string', 'max:255'],
            'flavors' => ['required', 'array'],
            // The domain is interpolated into remote shell paths during deploy, so it
            // is constrained to an actual hostname rather than any string.
            'flavors.*.domain' => ['nullable', 'string', 'max:255', 'regex:/^[a-z0-9]([a-z0-9.-]*[a-z0-9])?$/i'],
            // Absolute path on the target, straight from the Hostinger API. It reaches a
            // remote shell, so no quotes, spaces or shell metacharacters.
            'flavors.*.document_root' => ['nullable', 'string', 'max:255', 'regex:/^\/[A-Za-z0-9._\/-]*$/'],
            'flavors.*.ssh.host' => ['nullable', 'string', 'max:255'],
            'flavors.*.ssh.port' => ['nullable', 'integer', 'min:1', 'max:65535'],
            'flavors.*.ssh.username' => ['nullable', 'string', 'max:255'],
            'flavors.*.ssh.password' => ['nullable', 'string', 'max:255'],
            'flavors.*.db.DB_HOST' => ['nullable', 'string', 'max:255'],
            'flavors.*.db.DB_PORT' => ['nullable', 'string', 'max:10'],
            'flavors.*.db.DB_DATABASE' => ['nullable', 'string', 'max:255'],
            'flavors.*.db.DB_USERNAME' => ['nullable', 'string', 'max:255'],
            'flavors.*.db.DB_PASSWORD' => ['nullable', 'string', 'max:255'],
            'flavors.*.env.APP_DEBUG' => ['nullable', 'in:inherit,true,false'],
            'flavors.*.env.IS_TESTING' => ['nullable', 'in:inherit,true,false'],
            'flavors.*.env.ALLOW_CONTENT_SEEDING' => ['nullable', 'in:true,false'],
            'flavors.*.env.FRONTEND_URL' => ['nullable', 'string', 'max:255'],
            'flavors.*.inherit_pusher' => ['nullable', 'boolean'],
            'flavors.*.inherit_mail' => ['nullable', 'boolean'],
            'flavors.*.pusher.PUSHER_APP_ID' => ['nullable', 'string', 'max:255'],
            'flavors.*.pusher.PUSHER_APP_KEY' => ['nullable', 'string', 'max:255'],
            'flavors.*.pusher.PUSHER_APP_SECRET' => ['nullable', 'string', 'max:255'],
            'flavors.*.pusher.PUSHER_APP_CLUSTER' => ['nullable', 'string', 'max:50'],
            'flavors.*.mail.MAIL_MAILER' => ['nullable', 'string', 'max:50'],
            'flavors.*.mail.MAIL_HOST' => ['nullable', 'string', 'max:255'],
            'flavors.*.mail.MAIL_PORT' => ['nullable', 'string', 'max:10'],
            'flavors.*.mail.MAIL_USERNAME' => ['nullable', 'string', 'max:255'],
            'flavors.*.mail.MAIL_PASSWORD' => ['nullable', 'string', 'max:255'],
            'flavors.*.mail.MAIL_ENCRYPTION' => ['nullable', 'string', 'max:10'],
            'flavors.*.mail.MAIL_FROM_ADDRESS' => ['nullable', 'string', 'max:255'],
        ]);

        $clean = [
            'share_ssh' => (bool) $validated['share_ssh'],
            'ssh' => $this->cleanSsh($validated['ssh'] ?? []),
            'flavors' => [],
        ];

        foreach ($this->deployFlavors as $flavor) {
            $fv = $validated['flavors'][$flavor] ?? [];
            $clean['flavors'][$flavor] = [
                'domain' => $fv['domain'] ?? '',
                'document_root' => $fv['document_root'] ?? null,
                'ssh' => $this->cleanSsh($fv['ssh'] ?? []),
                'db' => [
                    'DB_HOST' => $fv['db']['DB_HOST'] ?? '',
                    'DB_PORT' => $fv['db']['DB_PORT'] ?? '3306',
                    'DB_DATABASE' => $fv['db']['DB_DATABASE'] ?? '',
                    'DB_USERNAME' => $fv['db']['DB_USERNAME'] ?? '',
                    'DB_PASSWORD' => $fv['db']['DB_PASSWORD'] ?? '',
                ],
                // Production is forced off for both, whatever arrives: the UI locks them,
                // AppServiceProvider force-disables IS_TESTING at runtime there, and
                // `app:assert-production-safety` fails the deploy on either. Writing a
                // `true` into .deploy.json would only produce a deploy that stops halfway.
                'env' => [
                    'APP_DEBUG' => $flavor === 'production' ? 'false' : ($fv['env']['APP_DEBUG'] ?? 'inherit'),
                    'IS_TESTING' => $flavor === 'production' ? 'false' : ($fv['env']['IS_TESTING'] ?? 'inherit'),
                    // Always on everywhere except production, which is the only flavor
                    // whose content is worth protecting and so the only one that gets a
                    // choice. Seeding a live install once is the whole reason this flag is
                    // separate from IS_TESTING.
                    // On unless production explicitly says otherwise. No `inherit`: the
                    // base is always true, so it would only be a second word for `true`.
                    'ALLOW_CONTENT_SEEDING' => ($flavor === 'production' && ($fv['env']['ALLOW_CONTENT_SEEDING'] ?? '') === 'false')
                        ? 'false'
                        : 'true',
                    'FRONTEND_URL' => $fv['env']['FRONTEND_URL'] ?? '',
                ],
                'inherit_pusher' => (bool) ($fv['inherit_pusher'] ?? true),
                'pusher' => array_merge($this->blankPusher(), $fv['pusher'] ?? []),
                'inherit_mail' => (bool) ($fv['inherit_mail'] ?? true),
                'mail' => array_merge($this->blankMail(), $fv['mail'] ?? []),
            ];
        }

        $this->writeDeployConfig($clean);

        // Keep production APP_URL in sync with the production flavor domain.
        if (! empty($clean['flavors']['production']['domain'])) {
            $this->rebuildProductionEnv([
                'APP_URL' => 'https://'.$clean['flavors']['production']['domain'],
            ]);
        }

        return redirect()->back()->with('success', 'Deploy config saved.');
    }

    public function deploy(Request $request)
    {
        $validated = $request->validate([
            'flavor' => ['required', 'in:'.implode(',', $this->deployFlavors)],
            'migration_option' => ['required', 'in:migrate,fresh_seed,none'],
            // Each name is interpolated into an `artisan db:seed --class=...` run over SSH, so
            // anything outside the discovered list is a command-injection attempt rather than a
            // typo. Whitelisting against the real classes is the only thing standing between the
            // deploy modal and arbitrary remote shell execution.
            'seeders' => ['array'],
            'seeders.*' => [Rule::in(array_column($this->availableSeeders(), 'class'))],
            // Legacy: deploy payloads saved before the seeder picker existed only carried a
            // boolean. Kept so an old target does not silently stop seeding.
            'run_seeders' => ['boolean'],
            'safe_storage_deploy' => ['boolean'],
            'generate_docs' => ['boolean'],
        ]);

        set_time_limit(3000);

        $base = base_path();
        $prodPath = $base.'/.env.production';
        $deployPath = $base.'/.deploy.json';

        if (! file_exists($prodPath)) {
            return redirect()->back()->with('error', 'No .env.production found. Save production config first.');
        }

        if (! file_exists($deployPath)) {
            return redirect()->back()->with('error', 'No deploy config found. Save deployment targets first.');
        }

        $config = $this->getDeployConfig();
        $flavor = $validated['flavor'];
        $flavorCfg = $config['flavors'][$flavor] ?? null;

        if (! $flavorCfg || empty($flavorCfg['domain'])) {
            return redirect()->back()->with('error', 'No domain configured for the '.$flavor.' target.');
        }

        $ssh = self::resolveFlavorSsh($config, $flavorCfg);

        if (empty($ssh['host']) || empty($ssh['username'])) {
            return redirect()->back()->with('error', 'SSH credentials missing for the '.$flavor.' target.');
        }

        // Step 1: Build assets locally
        $buildOutput = [];
        $buildExit = 0;
        exec("cd {$base} && npm run build 2>&1", $buildOutput, $buildExit);
        if ($buildExit !== 0) {
            return redirect()->back()->with('error', 'Build failed: '.implode("\n", array_slice($buildOutput, -5)));
        }

        // Remove hot file
        @unlink($base.'/public/hot');

        // Step 2: Deploy via SSH/SFTP to the resolved target.
        $sshConfig = [
            'ssh_host' => $ssh['host'],
            'ssh_port' => $ssh['port'] ?? 65002,
            'ssh_username' => $ssh['username'],
            'ssh_password' => $ssh['password'] ?? '',
            'domain' => $flavorCfg['domain'],
            // Recorded by provisioning from the Hostinger API; see runSshDeploy().
            'document_root' => $flavorCfg['document_root'] ?? null,
        ];

        $deployOptions = [
            'migration_option' => $validated['migration_option'],
            'seeders' => $validated['seeders'] ?? [],
            'run_seeders' => $validated['run_seeders'] ?? false,
            'safe_storage_deploy' => $validated['safe_storage_deploy'] ?? false,
            'generate_docs' => $validated['generate_docs'] ?? true,
            'flavor' => $flavor,
            'flavor_config' => $flavorCfg,
        ];
        $sshResult = $this->runSshDeploy($sshConfig, $deployOptions);

        if ($sshResult['success']) {
            return redirect()->back()
                ->with('success', ucfirst($flavor).' deployed successfully. '.$sshResult['message'])
                ->with('deploy_log', $sshResult['log'] ?? '');
        }

        return redirect()->back()
            ->with('error', 'Deploy failed: '.$sshResult['message'])
            ->with('deploy_log', $sshResult['log'] ?? '');
    }

    public function initGit(Request $request)
    {
        $validated = $request->validate([
            'url' => ['required', 'string', 'url'],
        ]);

        $base = base_path();
        $url = $validated['url'];
        $commands = [];

        // Initialize git if needed
        if (! is_dir($base.'/.git')) {
            $commands[] = 'git init';
        }

        // Add remote (remove old one first if exists)
        exec("cd {$base} && git remote get-url origin 2>&1", $existingRemote, $exitCode);
        if ($exitCode === 0) {
            $commands[] = 'git remote set-url origin '.escapeshellarg($url);
        } else {
            $commands[] = 'git remote add origin '.escapeshellarg($url);
        }

        $commands[] = 'git add .';
        $commands[] = 'git commit -m "Initial commit"';
        $commands[] = 'git branch -M main';
        $commands[] = 'git push -u origin main';

        $fullCommand = 'cd '.escapeshellarg($base).' && '.implode(' && ', $commands).' 2>&1';

        $output = [];
        $exitCode = 0;
        exec($fullCommand, $output, $exitCode);

        if ($exitCode === 0) {
            return redirect()->back()->with('success', 'Repository initialized and pushed to GitHub.');
        }

        return redirect()->back()->with('error', 'Git error: '.implode("\n", array_slice($output, -5)));
    }

    public function disconnectGit()
    {
        $gitPath = base_path('.git');

        if (! is_dir($gitPath)) {
            return redirect()->back()->with('error', __('admin.no_git_repo'));
        }

        $escaped = escapeshellarg($gitPath);
        exec("rm -rf {$escaped} 2>&1", $output, $exitCode);

        if ($exitCode === 0) {
            return redirect()->back()->with('success', __('admin.git_disconnected'));
        }

        return redirect()->back()->with('error', 'Failed to remove .git directory.');
    }

    public function pullFromGithub()
    {
        set_time_limit(120);
        $base = base_path();

        if (! is_dir($base.'/.git')) {
            return redirect()->back()->with('error', __('admin.no_git_repo'));
        }

        Log::info('[Git Pull] Starting pull from GitHub');

        exec("cd {$base} && git pull 2>&1", $output, $exitCode);

        Log::info('[Git Pull] Result', [
            'exit' => $exitCode,
            'output' => implode("\n", array_slice($output, -5)),
        ]);

        if ($exitCode === 0) {
            $message = implode("\n", array_slice($output, -3));
            if (str_contains($message, 'Already up to date')) {
                return redirect()->back()->with('success', __('admin.already_up_to_date'));
            }

            return redirect()->back()->with('success', __('admin.pulled_from_github'));
        }

        return redirect()->back()->with('error', 'Pull failed: '.implode("\n", array_slice($output, -5)));
    }

    public function fetchRemote()
    {
        $base = base_path();

        if (! is_dir($base.'/.git')) {
            return redirect()->back()->with('error', __('admin.no_git_repo'));
        }

        Log::info('[Git Fetch] Starting fetch from remote');

        exec("cd {$base} && git fetch --all 2>&1", $output, $exitCode);

        Log::info('[Git Fetch] Result', [
            'exit' => $exitCode,
            'output' => implode("\n", array_slice($output, -5)),
        ]);

        if ($exitCode === 0) {
            return redirect()->back()->with('success', __('admin.fetched_from_remote'));
        }

        return redirect()->back()->with('error', 'Fetch failed: '.implode("\n", array_slice($output, -5)));
    }

    public function commitChanges(Request $request)
    {
        $validated = $request->validate([
            'message' => ['required', 'string', 'max:500'],
        ]);

        set_time_limit(120);
        $base = base_path();

        if (! is_dir($base.'/.git')) {
            return redirect()->back()->with('error', __('admin.no_git_repo'));
        }

        Log::info('[Git Commit] Starting commit', ['message' => $validated['message']]);

        // Stage all changes
        exec("cd {$base} && git add -A 2>&1", $addOutput, $addExit);

        if ($addExit !== 0) {
            return redirect()->back()->with('error', 'Stage failed: '.implode("\n", array_slice($addOutput, -5)));
        }

        // Commit with custom message
        $message = escapeshellarg($validated['message']);
        exec("cd {$base} && git commit -m {$message} 2>&1", $commitOutput, $commitExit);

        Log::info('[Git Commit] Result', [
            'exit' => $commitExit,
            'output' => implode("\n", array_slice($commitOutput, -5)),
        ]);

        if ($commitExit !== 0) {
            $commitMsg = implode("\n", array_slice($commitOutput, -5));
            if (str_contains($commitMsg, 'nothing to commit')) {
                return redirect()->back()->with('success', __('admin.nothing_to_commit'));
            }

            return redirect()->back()->with('error', 'Commit failed: '.$commitMsg);
        }

        return redirect()->back()->with('success', __('admin.changes_committed'));
    }

    public function switchBranch(Request $request)
    {
        $validated = $request->validate([
            'branch' => ['required', 'string', 'max:255'],
        ]);

        $base = base_path();

        if (! is_dir($base.'/.git')) {
            return redirect()->back()->with('error', __('admin.no_git_repo'));
        }

        $branch = escapeshellarg($validated['branch']);

        Log::info('[Git Switch] Switching to branch', ['branch' => $validated['branch']]);

        exec("cd {$base} && git checkout {$branch} 2>&1", $output, $exitCode);

        Log::info('[Git Switch] Result', [
            'exit' => $exitCode,
            'output' => implode("\n", array_slice($output, -5)),
        ]);

        if ($exitCode === 0) {
            return redirect()->back()->with('success', __('admin.switched_to_branch', ['branch' => $validated['branch']]));
        }

        return redirect()->back()->with('error', 'Switch failed: '.implode("\n", array_slice($output, -5)));
    }

    public function createBranch(Request $request)
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255', 'regex:/^[a-zA-Z0-9\-_\/]+$/'],
        ]);

        $base = base_path();

        if (! is_dir($base.'/.git')) {
            return redirect()->back()->with('error', __('admin.no_git_repo'));
        }

        $branch = escapeshellarg($validated['name']);

        Log::info('[Git Branch] Creating branch', ['name' => $validated['name']]);

        exec("cd {$base} && git checkout -b {$branch} 2>&1", $output, $exitCode);

        Log::info('[Git Branch] Result', [
            'exit' => $exitCode,
            'output' => implode("\n", array_slice($output, -5)),
        ]);

        if ($exitCode === 0) {
            return redirect()->back()->with('success', __('admin.branch_created', ['branch' => $validated['name']]));
        }

        $errorMsg = implode("\n", array_slice($output, -5));
        if (str_contains($errorMsg, 'already exists')) {
            return redirect()->back()->with('error', __('admin.branch_exists', ['branch' => $validated['name']]));
        }

        return redirect()->back()->with('error', 'Create branch failed: '.$errorMsg);
    }

    public function getFileDiff(Request $request)
    {
        $validated = $request->validate([
            'file' => ['required', 'string', 'max:500'],
            'type' => ['required', 'in:modified,staged,untracked'],
        ]);

        $base = base_path();

        if (! is_dir($base.'/.git')) {
            return response()->json(['diff' => '', 'error' => 'No git repository']);
        }

        $file = $validated['file'];
        $type = $validated['type'];

        // Sanitize file path - only allow files within the project
        $realPath = realpath($base.'/'.$file);
        if (! $realPath || ! str_starts_with($realPath, rtrim($base, '/').'/')) {
            return response()->json(['diff' => '', 'error' => 'Invalid file path']);
        }

        $escapedFile = escapeshellarg($file);
        $diff = '';

        if ($type === 'untracked') {
            // For untracked files, show the file content
            $content = @file_get_contents($realPath);
            if ($content !== false) {
                $lines = explode("\n", $content);
                $diff = implode("\n", array_map(fn ($line) => '+ '.$line, array_slice($lines, 0, 100)));
                if (count($lines) > 100) {
                    $diff .= "\n... (".(count($lines) - 100).' more lines)';
                }
            }
        } elseif ($type === 'staged') {
            exec("cd {$base} && git diff --cached -- {$escapedFile} 2>&1", $output, $exitCode);
            $diff = implode("\n", $output);
        } else {
            exec("cd {$base} && git diff -- {$escapedFile} 2>&1", $output, $exitCode);
            $diff = implode("\n", $output);
        }

        return response()->json(['diff' => $diff]);
    }

    private function getGitStatus(): array
    {
        $base = base_path();
        $isRepo = is_dir($base.'/.git');
        $gh = $this->githubCliStatus();

        if (! $isRepo) {
            return [
                'is_repo' => false,
                'remote_url' => null,
                'current_branch' => null,
                'branches' => [],
                'remote_branches' => [],
                'modified' => [],
                'staged' => [],
                'untracked' => [],
                'ahead' => 0,
                'behind' => 0,
                'commits' => [],
                'gh' => $gh,
            ];
        }

        // Remote URL
        $remoteUrl = null;
        exec("cd {$base} && git remote get-url origin 2>&1", $remoteOutput, $remoteExit);
        if ($remoteExit === 0 && ! empty($remoteOutput[0])) {
            $remoteUrl = $remoteOutput[0];
        }

        // Current branch
        $currentBranch = null;
        exec("cd {$base} && git branch --show-current 2>&1", $branchOutput, $branchExit);
        if ($branchExit === 0 && ! empty($branchOutput[0])) {
            $currentBranch = trim($branchOutput[0]);
        }

        // Local branches
        $branches = [];
        exec("cd {$base} && git branch --format='%(refname:short)' 2>&1", $branchesOutput, $branchesExit);
        if ($branchesExit === 0) {
            $branches = array_filter(array_map('trim', $branchesOutput));
        }

        // Remote branches
        $remoteBranches = [];
        exec("cd {$base} && git branch -r --format='%(refname:short)' 2>&1", $remoteBranchesOutput, $remoteBranchesExit);
        if ($remoteBranchesExit === 0) {
            $remoteBranches = array_values(array_filter(array_map(function ($b) {
                $b = trim($b);
                // Remove origin/ prefix and filter out HEAD
                if (str_starts_with($b, 'origin/') && $b !== 'origin/HEAD') {
                    return substr($b, 7);
                }

                return null;
            }, $remoteBranchesOutput)));
        }

        // Modified files (unstaged)
        $modified = [];
        exec("cd {$base} && git diff --name-only 2>&1", $modifiedOutput, $modifiedExit);
        if ($modifiedExit === 0) {
            $modified = array_filter(array_map('trim', $modifiedOutput));
        }

        // Staged files
        $staged = [];
        exec("cd {$base} && git diff --cached --name-only 2>&1", $stagedOutput, $stagedExit);
        if ($stagedExit === 0) {
            $staged = array_filter(array_map('trim', $stagedOutput));
        }

        // Untracked files
        $untracked = [];
        exec("cd {$base} && git ls-files --others --exclude-standard 2>&1", $untrackedOutput, $untrackedExit);
        if ($untrackedExit === 0) {
            $untracked = array_filter(array_map('trim', $untrackedOutput));
        }

        // Ahead/behind count
        $ahead = 0;
        $behind = 0;
        if ($currentBranch && $remoteUrl) {
            $escapedBranch = escapeshellarg($currentBranch);
            $escapedUpstream = escapeshellarg('origin/'.$currentBranch);
            exec("cd {$base} && git rev-list --left-right --count {$escapedBranch}...{$escapedUpstream} 2>&1", $countOutput, $countExit);
            if ($countExit === 0 && ! empty($countOutput[0])) {
                $parts = preg_split('/\s+/', trim($countOutput[0]));
                if (count($parts) === 2) {
                    $ahead = (int) $parts[0];
                    $behind = (int) $parts[1];
                }
            }
        }

        // Recent commits (last 10)
        $commits = [];
        exec("cd {$base} && git log --oneline -10 --format='%h|%s|%ar' 2>&1", $logOutput, $logExit);
        if ($logExit === 0) {
            foreach ($logOutput as $line) {
                $parts = explode('|', $line, 3);
                if (count($parts) === 3) {
                    $commits[] = [
                        'hash' => $parts[0],
                        'message' => $parts[1],
                        'date' => $parts[2],
                    ];
                }
            }
        }

        return [
            'is_repo' => true,
            'remote_url' => $remoteUrl,
            'current_branch' => $currentBranch,
            'branches' => array_values($branches),
            'remote_branches' => array_values($remoteBranches),
            'modified' => array_values($modified),
            'staged' => array_values($staged),
            'untracked' => array_values($untracked),
            'ahead' => $ahead,
            'behind' => $behind,
            'commits' => $commits,
            'gh' => $gh,
        ];
    }

    /**
     * Whether the GitHub CLI is usable from here.
     *
     * The panel only offers "create repository" when both are true; otherwise it
     * tells the developer which of the two steps is missing instead of failing
     * inside a shell command they cannot see.
     *
     * @return array{installed: bool, authenticated: bool, account: string|null}
     */
    private function githubCliStatus(): array
    {
        exec('command -v gh 2>/dev/null', $which, $whichExit);

        if ($whichExit !== 0) {
            return ['installed' => false, 'authenticated' => false, 'account' => null];
        }

        // `gh auth status` writes to stderr and exits non-zero when signed out.
        exec('gh auth status 2>&1', $status, $statusExit);
        $text = implode("\n", $status);

        preg_match('/account\s+([A-Za-z0-9-]+)/', $text, $matches);

        return [
            'installed' => true,
            'authenticated' => $statusExit === 0,
            'account' => $matches[1] ?? null,
        ];
    }

    /**
     * Create a GitHub repository for this project and push to it.
     *
     * Saves the round trip to github.com: `gh repo create` makes the remote,
     * wires up `origin` and pushes, using whichever account `gh` is already
     * signed in as. Everything the developer types is escaped before it reaches
     * a shell — this endpoint is local-only and super-admin gated, but a repo
     * name is still user input.
     */
    public function createGithubRepo(Request $request)
    {
        $validated = $request->validate([
            // GitHub's own rule: letters, digits, dot, dash, underscore.
            'name' => ['required', 'string', 'max:100', 'regex:/^[A-Za-z0-9._-]+$/'],
            'visibility' => ['required', 'in:private,public'],
            'description' => ['nullable', 'string', 'max:350'],
        ]);

        $gh = $this->githubCliStatus();

        if (! $gh['installed']) {
            return redirect()->back()->with('error', __('admin.gh_not_installed'));
        }

        if (! $gh['authenticated']) {
            return redirect()->back()->with('error', __('admin.gh_not_authenticated'));
        }

        $base = base_path();

        // A repo has to exist locally with at least one commit before gh can
        // push it, and this template is normally a fresh copy with no history.
        $prepare = ['git init -b main'];

        if (is_dir($base.'/.git')) {
            $prepare = [];
        }

        $prepare[] = 'git add .';
        // Nothing to commit is fine: an existing checkout may already be clean.
        $prepare[] = 'git diff --cached --quiet || git commit -m '.escapeshellarg('Initial commit');

        $command = 'cd '.escapeshellarg($base).' && '.implode(' && ', $prepare).' 2>&1';
        exec($command, $prepareOutput, $prepareExit);

        if ($prepareExit !== 0) {
            return redirect()->back()->with('error', 'Git error: '.implode("\n", array_slice($prepareOutput, -5)));
        }

        $create = [
            'gh repo create '.escapeshellarg($validated['name']),
            '--'.$validated['visibility'],
            '--source=.',
            '--remote=origin',
            '--push',
        ];

        if (! empty($validated['description'])) {
            $create[] = '--description '.escapeshellarg($validated['description']);
        }

        $output = [];
        $exitCode = 0;
        exec('cd '.escapeshellarg($base).' && '.implode(' ', $create).' 2>&1', $output, $exitCode);

        if ($exitCode === 0) {
            return redirect()->back()->with('success', __('admin.gh_repo_created', ['name' => $validated['name']]));
        }

        return redirect()->back()->with('error', 'GitHub CLI: '.implode("\n", array_slice($output, -5)));
    }

    public function updateUrls(Request $request)
    {
        $target = $request->input('target');

        // Production APP_URL is intentionally NOT accepted here: it's derived from the
        // production flavor's domain (Deploy Targets → saveDeployConfig) and force-overwritten
        // again on every deploy (buildFlavorEnv), so letting admins hand-set it here would just
        // be silently clobbered — only FRONTEND_URL is a real, standalone production setting.
        $validated = $request->validate([
            'target' => ['required', 'in:local,production'],
            'APP_URL' => $target === 'local' ? ['required', 'string', 'max:255'] : ['prohibited'],
            'FRONTEND_URL' => ['nullable', 'string', 'max:255'],
        ]);

        if ($target === 'local') {
            $this->writeEnvKey(base_path('.env'), 'APP_URL', $validated['APP_URL']);
            $this->writeEnvKey(base_path('.env'), 'FRONTEND_URL', $validated['FRONTEND_URL']);
            Artisan::call('config:clear');
        } else {
            $this->rebuildProductionEnv([
                'FRONTEND_URL' => $validated['FRONTEND_URL'],
            ]);
        }

        return redirect()->back()->with('success', ucfirst($target).' URLs updated.');
    }

    private function getProductionEnvValue(string $key): ?bool
    {
        $prodPath = base_path('.env.production');
        if (! file_exists($prodPath)) {
            return null;
        }

        $content = file_get_contents($prodPath);
        if (preg_match('/^'.preg_quote($key, '/').'\s*=\s*(.*)$/m', $content, $match)) {
            return filter_var(trim($match[1]), FILTER_VALIDATE_BOOLEAN);
        }

        return null;
    }

    /**
     * `{php} {document_root}/backend/artisan schedule:run`.
     *
     * Deploy puts the application in `backend/` under the document root and copies only
     * `public/` up a level, so artisan lives there — not at the document root itself.
     */
    private function schedulerCommand(string $documentRoot): string
    {
        return self::REMOTE_PHP.' '.rtrim($documentRoot, '/').'/backend/artisan schedule:run';
    }

    private function blankSsh(): array
    {
        return ['host' => '', 'port' => 65002, 'username' => '', 'password' => ''];
    }

    private function blankDb(): array
    {
        return ['DB_HOST' => '', 'DB_PORT' => '3306', 'DB_DATABASE' => '', 'DB_USERNAME' => '', 'DB_PASSWORD' => ''];
    }

    private function blankPusher(): array
    {
        return ['PUSHER_APP_ID' => '', 'PUSHER_APP_KEY' => '', 'PUSHER_APP_SECRET' => '', 'PUSHER_APP_CLUSTER' => ''];
    }

    private function blankMail(): array
    {
        return [
            'MAIL_MAILER' => '',
            'MAIL_HOST' => '',
            'MAIL_PORT' => '',
            'MAIL_USERNAME' => '',
            'MAIL_PASSWORD' => '',
            'MAIL_ENCRYPTION' => '',
            'MAIL_FROM_ADDRESS' => '',
        ];
    }

    /**
     * `ALLOW_CONTENT_SEEDING` has only two answers. A `.deploy.json` written before that was
     * true can still hold `inherit`, which would reach a select that no longer offers it.
     *
     * @param  array<string, string>  $env
     * @return array<string, string>
     */
    private function normalizeFlavorEnv(array $env): array
    {
        $env['ALLOW_CONTENT_SEEDING'] = ($env['ALLOW_CONTENT_SEEDING'] ?? '') === 'false' ? 'false' : 'true';

        return $env;
    }

    /**
     * Per-flavor scalar env overrides. Empty string / 'inherit' = use the base
     * .env.production value.
     */
    private function blankFlavorEnv(): array
    {
        return [
            'APP_DEBUG' => 'inherit',   // inherit | true | false
            'IS_TESTING' => 'inherit',  // inherit | true | false
            'ALLOW_CONTENT_SEEDING' => 'true',  // true | false — never inherit; the base is always true
            'FRONTEND_URL' => '',
        ];
    }

    /**
     * APP_ENV is derived from the flavor automatically — never set by hand.
     * Production keeps Laravel's production env; other flavors use their name.
     */
    private function appEnvForFlavor(string $flavor): string
    {
        return $flavor === 'production' ? 'production' : $flavor;
    }

    private function firebasePathForFlavor(string $flavor): string
    {
        return storage_path("app/private/firebase-{$flavor}.json");
    }

    /**
     * Base Firebase credentials uploaded to every deploy target when a flavor
     * has no per-flavor file. Separate from the local firebase-auth.json.
     */
    private function baseFirebasePath(): string
    {
        return storage_path('app/private/firebase-base.json');
    }

    private function defaultFlavorConfig(): array
    {
        return [
            'domain' => '',
            'ssh' => $this->blankSsh(),
            'document_root' => null,
            'db' => $this->blankDb(),
            'env' => $this->blankFlavorEnv(),
            'inherit_pusher' => true,
            'pusher' => $this->blankPusher(),
            'inherit_mail' => true,
            'mail' => $this->blankMail(),
        ];
    }

    private function cleanSsh(array $ssh): array
    {
        return [
            'host' => $ssh['host'] ?? '',
            'port' => (int) ($ssh['port'] ?? 65002),
            'username' => $ssh['username'] ?? '',
            'password' => $ssh['password'] ?? '',
        ];
    }

    /**
     * Seeder classes the deploy modal may run individually.
     *
     * Discovered from disk rather than hardcoded: every project cloned from the starter
     * ships a different set and new seeders get added all the time. `DatabaseSeeder` is
     * excluded because it means "all of them", which the migrate options already cover,
     * and the `Support/` helpers are not seeders at all (the glob skips subdirectories).
     *
     * @return list<array{class: string, label: string, description: string|null}>
     */
    private function availableSeeders(): array
    {
        $seeders = [];

        foreach (glob(database_path('seeders/*.php')) ?: [] as $file) {
            $class = basename($file, '.php');

            if ($class === 'DatabaseSeeder' || ! class_exists('Database\\Seeders\\'.$class)) {
                continue;
            }

            $seeders[] = [
                'class' => $class,
                'label' => trim((string) preg_replace('/(?<!^)[A-Z]/', ' $0', $class)),
                'description' => $this->seederSummary($file),
            ];
        }

        usort($seeders, fn (array $a, array $b) => strcmp($a['class'], $b['class']));

        return $seeders;
    }

    /**
     * First sentence of a seeder's class docblock, used as the picker's hint line.
     *
     * Only a docblock attached to the class itself counts — the boilerplate
     * "Run the database seeds." on `run()` describes nothing.
     */
    private function seederSummary(string $file): ?string
    {
        $contents = (string) file_get_contents($file);

        if (! preg_match('#/\*\*((?:(?!\*/).)*)\*/\s*(?:final\s+|abstract\s+)?class\s#s', $contents, $matches)) {
            return null;
        }

        $body = preg_replace('/^[ \t]*\*[ \t]?/m', '', $matches[1]);
        $paragraph = trim(explode("\n\n", trim((string) $body))[0]);
        $summary = trim((string) preg_replace('/\s+/', ' ', $paragraph));

        return $summary === '' || str_starts_with($summary, '@') ? null : $summary;
    }

    /**
     * `has_config` / `has_firebase` are derived for the UI by getDeployConfig() and are
     * not part of the file, so they are stripped before writing rather than persisted
     * and then read back as if they had been configured.
     *
     * @param  array<string, mixed>  $config
     */
    private function writeDeployConfig(array $config): void
    {
        unset($config['has_config']);

        foreach (array_keys($config['flavors'] ?? []) as $flavor) {
            unset($config['flavors'][$flavor]['has_firebase']);
        }

        file_put_contents(base_path('.deploy.json'), json_encode($config, JSON_PRETTY_PRINT));
    }

    private function getDeployConfig(): array
    {
        $path = base_path('.deploy.json');

        $config = [
            'share_ssh' => true,
            'ssh' => $this->blankSsh(),
            'flavors' => [],
            'has_config' => false,
        ];
        foreach ($this->deployFlavors as $flavor) {
            $config['flavors'][$flavor] = array_merge(
                $this->defaultFlavorConfig(),
                ['has_firebase' => file_exists($this->firebasePathForFlavor($flavor))],
            );
        }

        if (! file_exists($path)) {
            return $config;
        }

        $raw = json_decode(file_get_contents($path), true) ?? [];

        // Migrate the legacy single-target shape ({ssh_host, ssh_port, ...domain})
        // into the production flavor with shared SSH credentials.
        if (! isset($raw['flavors'])) {
            $config['share_ssh'] = true;
            $config['ssh'] = $this->cleanSsh([
                'host' => $raw['ssh_host'] ?? '',
                'port' => $raw['ssh_port'] ?? 65002,
                'username' => $raw['ssh_username'] ?? '',
                'password' => $raw['ssh_password'] ?? '',
            ]);
            $config['flavors']['production']['domain'] = $raw['domain'] ?? '';
            $config['flavors']['production']['db'] = $this->blankDb();
            $config['has_config'] = ! empty($raw['domain']);

            return $config;
        }

        $config['share_ssh'] = (bool) ($raw['share_ssh'] ?? true);
        $config['ssh'] = $this->cleanSsh($raw['ssh'] ?? []);
        foreach ($this->deployFlavors as $flavor) {
            $stored = $raw['flavors'][$flavor] ?? [];
            $config['flavors'][$flavor] = [
                'domain' => $stored['domain'] ?? '',
                'document_root' => $stored['document_root'] ?? null,
                'ssh' => $this->cleanSsh($stored['ssh'] ?? []),
                'db' => array_merge($this->blankDb(), $stored['db'] ?? []),
                'env' => $this->normalizeFlavorEnv(array_merge($this->blankFlavorEnv(), $stored['env'] ?? [])),
                'inherit_pusher' => (bool) ($stored['inherit_pusher'] ?? true),
                'pusher' => array_merge($this->blankPusher(), $stored['pusher'] ?? []),
                'inherit_mail' => (bool) ($stored['inherit_mail'] ?? true),
                'mail' => array_merge($this->blankMail(), $stored['mail'] ?? []),
                'has_firebase' => file_exists($this->firebasePathForFlavor($flavor)),
            ];
        }
        $config['has_config'] = true;

        return $config;
    }

    /**
     * Build a flavor-specific .env from the .env.production base. Overrides DB,
     * pusher, mail and scalar env values — but only the ones explicitly set for
     * the flavor; everything left blank inherits the base. APP_URL is always set
     * to the target domain.
     */
    private function buildFlavorEnv(array $flavor, string $appUrl, string $flavorName): string
    {
        $content = file_get_contents(base_path('.env.production'));

        $notBlank = fn ($v) => $v !== '' && $v !== null;

        $db = $flavor['db'] ?? [];
        $env = $flavor['env'] ?? [];
        // Whole groups inherit the base unless explicitly overridden for the flavor.
        $pusher = ($flavor['inherit_pusher'] ?? true) ? [] : ($flavor['pusher'] ?? []);
        $mail = ($flavor['inherit_mail'] ?? true) ? [] : ($flavor['mail'] ?? []);

        // DB + mail + pusher: copy through only the non-empty keys.
        $overrides = array_filter([
            'DB_HOST' => $db['DB_HOST'] ?? '',
            'DB_PORT' => $db['DB_PORT'] ?? '',
            'DB_DATABASE' => $db['DB_DATABASE'] ?? '',
            'DB_USERNAME' => $db['DB_USERNAME'] ?? '',
            'DB_PASSWORD' => $db['DB_PASSWORD'] ?? '',
            'MAIL_MAILER' => $mail['MAIL_MAILER'] ?? '',
            'MAIL_HOST' => $mail['MAIL_HOST'] ?? '',
            'MAIL_PORT' => $mail['MAIL_PORT'] ?? '',
            'MAIL_USERNAME' => $mail['MAIL_USERNAME'] ?? '',
            'MAIL_PASSWORD' => $mail['MAIL_PASSWORD'] ?? '',
            'MAIL_ENCRYPTION' => $mail['MAIL_ENCRYPTION'] ?? '',
            'MAIL_FROM_ADDRESS' => $mail['MAIL_FROM_ADDRESS'] ?? '',
            'PUSHER_APP_ID' => $pusher['PUSHER_APP_ID'] ?? '',
            'PUSHER_APP_KEY' => $pusher['PUSHER_APP_KEY'] ?? '',
            'PUSHER_APP_SECRET' => $pusher['PUSHER_APP_SECRET'] ?? '',
            'PUSHER_APP_CLUSTER' => $pusher['PUSHER_APP_CLUSTER'] ?? '',
            'FRONTEND_URL' => $env['FRONTEND_URL'] ?? '',
        ], $notBlank);

        // APP_ENV is always derived from the flavor — not user-editable.
        $overrides['APP_ENV'] = $this->appEnvForFlavor($flavorName);

        // When pusher is overridden with a key, mirror to the Vite keys + enable the driver.
        if (! empty($pusher['PUSHER_APP_KEY'] ?? '')) {
            $overrides['VITE_PUSHER_APP_KEY'] = $pusher['PUSHER_APP_KEY'];
            $overrides['BROADCAST_CONNECTION'] = 'pusher';
        }
        if (! empty($pusher['PUSHER_APP_CLUSTER'] ?? '')) {
            $overrides['VITE_PUSHER_APP_CLUSTER'] = $pusher['PUSHER_APP_CLUSTER'];
        }

        // Boolean toggles: inherit | true | false.
        foreach ([
            'APP_DEBUG' => $env['APP_DEBUG'] ?? 'inherit',
            'IS_TESTING' => $env['IS_TESTING'] ?? 'inherit',
            'ALLOW_CONTENT_SEEDING' => $env['ALLOW_CONTENT_SEEDING'] ?? 'inherit',
        ] as $key => $val) {
            if ($val === 'true' || $val === 'false') {
                $overrides[$key] = $val;
            }
        }

        $overrides['APP_URL'] = $appUrl;

        foreach ($overrides as $key => $value) {
            $content = $this->replaceEnvKeyInString($content, $key, (string) $value);
        }

        return $content;
    }

    private function replaceEnvKeyInString(string $env, string $key, string $value): string
    {
        $line = $key.'='.$this->escapeEnvValue($value);

        if (preg_match('/^'.preg_quote($key, '/').'\s*=\s*.*$/m', $env)) {
            return preg_replace_callback(
                '/^'.preg_quote($key, '/').'\s*=\s*.*$/m',
                fn () => $line,
                $env
            );
        }

        return $env."\n".$line;
    }

    /**
     * Which SSH credentials a flavor deploys with.
     *
     * Honours the share toggle, but falls back to whichever scope actually has a host,
     * so a half-filled toggle still connects instead of failing with "credentials
     * missing" while the credentials are plainly on screen.
     *
     * @param  array<string, mixed>  $config  the whole .deploy.json
     * @param  array<string, mixed>  $flavorCfg  one flavor's entry
     * @return array<string, mixed>
     */
    private static function resolveFlavorSsh(array $config, array $flavorCfg): array
    {
        $shared = $config['ssh'] ?? [];
        $own = $flavorCfg['ssh'] ?? [];

        if ($config['share_ssh'] ?? true) {
            return ! empty($shared['host']) ? $shared : $own;
        }

        return ! empty($own['host']) ? $own : $shared;
    }

    /**
     * Where a flavor lives on the target box.
     *
     * Hostinger serves a subdomain from the PARENT's `public_html/{directory}`, not from
     * `~/domains/{subdomain}/public_html` — that second path exists but nothing reads it,
     * so deploying to it silently publishes nothing, with no error anywhere.
     * Provisioning records the API's real `root_directory` as `document_root`; the
     * derived path is the fallback for a hand-configured apex domain.
     *
     * Both values reach a remote shell, so the recorded root is pattern-checked here as
     * well as at save time — `.deploy.json` can be edited by hand.
     *
     * @return array{error: ?string, public: string, backend: string}
     */
    private static function remotePaths(string $domain, ?string $documentRoot): array
    {
        $fail = fn (string $message): array => ['error' => $message, 'public' => '', 'backend' => ''];

        if (! preg_match('/^[a-z0-9]([a-z0-9.-]*[a-z0-9])?$/i', $domain)) {
            return $fail('Configured domain is not a valid hostname.');
        }

        if (is_string($documentRoot) && $documentRoot !== '') {
            if (! preg_match('#^/[A-Za-z0-9._/-]*$#', $documentRoot)) {
                return $fail('Configured document root is not a valid path.');
            }
            $publicPath = rtrim($documentRoot, '/');
        } else {
            $publicPath = "~/domains/{$domain}/public_html";
        }

        return ['error' => null, 'public' => $publicPath, 'backend' => $publicPath.'/backend'];
    }

    private function runSshDeploy(array $config, array $options = []): array
    {
        $base = base_path();
        $php = self::REMOTE_PHP;
        $domain = $config['domain'] ?? null;
        $migrationOption = $options['migration_option'] ?? 'migrate';
        $seeders = $options['seeders'] ?? [];
        // Set once anything runs `--seed`, so the picked seeders can report that they are
        // a repeat rather than looking like the only thing that seeded.
        $seededEverything = false;
        // Legacy boolean from a deploy config saved before the seeder picker existed:
        // "run everything", i.e. plain DatabaseSeeder.
        $runSeeders = $options['run_seeders'] ?? false;

        if (! $domain) {
            return ['success' => false, 'message' => 'No domain configured in SSH settings.'];
        }

        // remotePaths() re-checks the shape of both the domain and the recorded document
        // root: they are built into remote shell commands, and .deploy.json can be
        // edited by hand after the save-time validation ran.
        $paths = self::remotePaths((string) $domain, $config['document_root'] ?? null);

        if ($paths['error'] !== null) {
            return ['success' => false, 'message' => $paths['error']];
        }

        $publicPath = $paths['public'];
        $backendPath = $paths['backend'];
        $zipName = 'deploy_'.time().'.zip';
        $localZip = $base.'/'.$zipName;

        // Step 1: Zip the project locally (exclude unnecessary files)
        $excludes = '--exclude=".git/*" --exclude="node_modules/*" --exclude="vendor/*" --exclude=".deploy.json" --exclude="public/hot"';
        $zipOutput = [];
        exec("cd {$base} && zip -r {$zipName} . {$excludes} 2>&1", $zipOutput, $zipExit);

        if ($zipExit !== 0 || ! file_exists($localZip)) {
            return ['success' => false, 'message' => 'Failed to create zip: '.implode("\n", array_slice($zipOutput, -3))];
        }

        // Step 2: Connect via SSH/SFTP
        $ssh = new SSH2($config['ssh_host'], $config['ssh_port'] ?? 65002);
        $ssh->setTimeout(300);

        if (! $ssh->login($config['ssh_username'], $config['ssh_password'])) {
            @unlink($localZip);

            return ['success' => false, 'message' => 'SSH authentication failed.'];
        }

        $sftp = new SFTP($config['ssh_host'], $config['ssh_port'] ?? 65002);
        if (! $sftp->login($config['ssh_username'], $config['ssh_password'])) {
            @unlink($localZip);

            return ['success' => false, 'message' => 'SFTP authentication failed.'];
        }

        $output = '';

        // Check if first time
        $check = trim($ssh->exec("[ -d {$backendPath} ] && echo 'exists' || echo 'empty'"));
        $isFirstTime = ($check === 'empty');

        // Step 3: Upload zip to server
        $remoteZip = "/tmp/{$zipName}";
        $uploaded = $sftp->put($remoteZip, $localZip, SFTP::SOURCE_LOCAL_FILE);

        // Delete local zip immediately
        @unlink($localZip);

        if (! $uploaded) {
            return ['success' => false, 'message' => 'Failed to upload zip to server.'];
        }

        // Step 4: Wipe public_html except backend/, unzip into backend/.
        // Safe-storage mode (per-deploy toggle): preserve existing
        // storage/app/public data across the wipe so prod uploads aren't
        // destroyed by re-deployment.
        $safeStorage = ! empty($options['safe_storage_deploy']);
        $storageBackupPath = null;
        if ($safeStorage) {
            $storageBackupPath = '/tmp/storage_backup_'.time();
            $ssh->exec("mkdir -p {$storageBackupPath}");
            $ssh->exec("[ -d {$backendPath}/storage/app/public ] && cp -a {$backendPath}/storage/app/public/. {$storageBackupPath}/ 2>/dev/null");
            $output .= "[safe-storage] Backed up storage/app/public to {$storageBackupPath}\n";
        }

        $ssh->exec("find {$publicPath} -mindepth 1 -maxdepth 1 ! -name 'backend' -exec rm -rf {} + 2>/dev/null");
        $ssh->exec("rm -rf {$backendPath}");
        $ssh->exec("mkdir -p {$backendPath}");
        $output .= $ssh->exec("unzip -o {$remoteZip} -d {$backendPath} 2>&1")."\n";
        $ssh->exec("rm -f {$remoteZip}");

        if ($safeStorage && $storageBackupPath) {
            $ssh->exec("mkdir -p {$backendPath}/storage/app/public");
            $ssh->exec("cp -af {$storageBackupPath}/. {$backendPath}/storage/app/public/ 2>/dev/null");
            $ssh->exec("rm -rf {$storageBackupPath}");
            $output .= "[safe-storage] Restored storage/app/public from backup.\n";
        }

        // Step 5: Setup env — build from the .env.production base, overriding DB +
        // mail + pusher + APP_URL for this flavor, and upload it as the deployed
        // .env. If the SFTP string-put fails (flaky on some shared hosts), retry
        // by piping the SAME flavor-built content through the SSH shell instead
        // of falling back to a bare .env.production copy — that copy has none of
        // the flavor's overrides (DB included), so a fallback that used it would
        // silently deploy the wrong database.
        $flavor = $options['flavor'] ?? 'production';
        $flavorEnv = $this->buildFlavorEnv($options['flavor_config'] ?? [], 'https://'.$domain, $flavor);
        $envUploaded = $sftp->put("{$backendPath}/.env", $flavorEnv, SFTP::SOURCE_STRING);
        if ($envUploaded) {
            $output .= "[env] Uploaded .env for '{$flavor}' target ({$domain}).\n";
        } else {
            $output .= "[env] WARNING: SFTP .env upload failed, retrying via SSH shell.\n";
            $encoded = base64_encode($flavorEnv);
            $ssh->exec("echo '{$encoded}' | base64 -d > {$backendPath}/.env");
            $verify = trim($ssh->exec("[ -s {$backendPath}/.env ] && echo ok || echo fail"));
            if ($verify === 'ok') {
                $output .= "[env] .env written via SSH fallback for '{$flavor}' target.\n";
            } else {
                $output .= "[env] ERROR: could not write .env on the server — deploy config (DB included) was NOT applied. Check manually.\n";
            }
        }

        // Firebase credentials precedence: per-flavor file > base file > leave the
        // local firebase-auth.json that shipped in the zip.
        $flavorFirebase = $this->firebasePathForFlavor($flavor);
        $firebaseSource = file_exists($flavorFirebase)
            ? ['path' => $flavorFirebase, 'label' => $flavor]
            : (file_exists($this->baseFirebasePath()) ? ['path' => $this->baseFirebasePath(), 'label' => 'base'] : null);

        if ($firebaseSource) {
            $fbUploaded = $sftp->put("{$backendPath}/storage/app/private/firebase-auth.json", $firebaseSource['path'], SFTP::SOURCE_LOCAL_FILE);
            $output .= $fbUploaded
                ? "[firebase] Uploaded {$firebaseSource['label']} Firebase credentials.\n"
                : "[firebase] WARNING: {$firebaseSource['label']} Firebase upload failed.\n";
        }

        // Step 6: Composer install
        $output .= $ssh->exec("cd {$backendPath} && {$php} /usr/local/bin/composer install --no-dev --optimize-autoloader --ignore-platform-reqs 2>&1")."\n";

        // Step 7: Remove hot file
        $ssh->exec("rm -f {$backendPath}/public/hot");

        // Step 8: Laravel commands
        // APP_KEY comes from .env.production and must stay stable across deploys —
        // regenerating it breaks encryption of existing data. Only generate when
        // the deployed .env has no key (self-heal), never overwrite an existing one.
        $output .= $ssh->exec("cd {$backendPath} && grep -q '^APP_KEY=base64:' .env || {$php} artisan key:generate --force 2>&1")."\n";

        // Run migrations based on option.
        // A first deployment has no tables to drop, so the destructive rebuild is pointless
        // there — run the same thing it would have produced instead.
        if ($isFirstTime && $migrationOption === 'fresh_seed') {
            $output .= "First deployment detected. Running migrate --seed instead of migrate:fresh.\n";
            $output .= $ssh->exec("cd {$backendPath} && {$php} artisan migrate --seed --force 2>&1")."\n";
            $seededEverything = true;
            $migrationOption = 'none';
        }

        // DB-based detection: `migrate` only re-runs files absent from the
        // `migrations` tracking table. If the real schema was dropped (empty DB,
        // or a stale `migrations` table left behind after a manual wipe), a plain
        // `migrate` becomes a silent no-op and no tables get created. Probe the
        // live DB for a core table; when it's missing, force a clean rebuild so
        // `migrate` always provisions the schema.
        if ($migrationOption === 'migrate') {
            $schemaProbe = trim($ssh->exec("cd {$backendPath} && {$php} artisan tinker --execute=\"echo Schema::hasTable('users') ? 'has' : 'missing';\" 2>/dev/null"));
            $schemaMissing = ! str_contains($schemaProbe, 'has');

            if ($schemaMissing) {
                // Rebuilding the schema leaves an empty database: no roles, and therefore
                // nobody who can sign in. Seeding is the seeder picker's job now, so say so
                // rather than silently handing back a panel that locks its own admin out.
                $seedFlag = '';
                $output .= "Core schema not found (empty or stale DB). Forcing migrate:fresh to rebuild.\n";
                $output .= $seeders === []
                    ? "No seeders picked — the rebuilt database will be empty. Pick at least the roles and permissions seeder.\n"
                    : '';
                // migrate:fresh is prohibited in production by DB::prohibitDestructiveCommands()
                // and fails even with --force. ALLOW_DESTRUCTIVE_MIGRATIONS=true lifts the guard
                // for this one command (config is never cached, so env() reads it live).
                $output .= $ssh->exec("cd {$backendPath} && ALLOW_DESTRUCTIVE_MIGRATIONS=true {$php} artisan migrate:fresh{$seedFlag} --force 2>&1")."\n";
                $migrationOption = 'none'; // handled above; skip the switch
            }
        }

        $seededEverything = $seededEverything || $migrationOption === 'fresh_seed';

        switch ($migrationOption) {
            case 'fresh_seed':
                // WARNING: This wipes all data! ALLOW_DESTRUCTIVE_MIGRATIONS lifts the
                // production guard (DB::prohibitDestructiveCommands) for this command only.
                $output .= $ssh->exec("cd {$backendPath} && ALLOW_DESTRUCTIVE_MIGRATIONS=true {$php} artisan migrate:fresh --seed --force 2>&1")."\n";
                break;
            case 'migrate':
                $output .= $ssh->exec("cd {$backendPath} && {$php} artisan migrate --force 2>&1")."\n";
                break;
            case 'none':
                // Skip migrations
                $output .= "Skipping migrations (user selected 'none').\n";
                break;
        }

        // Legacy whole-database seed (the old "Run seeders" checkbox), skipped when
        // `--seed` already did it.
        if ($runSeeders && ! $seededEverything) {
            $output .= "[seed] db:seed (all seeders — legacy run_seeders flag)\n";
            $output .= $ssh->exec("cd {$backendPath} && {$php} artisan db:seed --force 2>&1")."\n";
        }

        // Individually picked seeders, in the order the admin listed them. The class names
        // were whitelisted against the discovered seeder list in deploy(); they are quoted
        // here as well so nothing can escape the remote shell command.
        if ($seeders !== []) {
            if ($seededEverything) {
                $output .= '[seed] --seed already ran every seeder; re-running the '.count($seeders)." picked one(s) anyway (seeders are idempotent here).\n";
            }

            foreach ($seeders as $seeder) {
                $output .= "[seed] db:seed --class={$seeder}\n";
                $output .= $ssh->exec("cd {$backendPath} && {$php} artisan db:seed --class=".escapeshellarg($seeder).' --force 2>&1')."\n";
            }
        }

        $output .= $ssh->exec("cd {$backendPath} && {$php} artisan config:clear && {$php} artisan route:clear && {$php} artisan view:clear 2>&1")."\n";

        // Step 8.5: Regenerate API docs (Scribe) against the .env + code that was just deployed,
        // so /docs, /docs.postman, /docs.openapi reflect THIS target's real feature flags
        // (AUTH_MODE, HAS_PAGES, HAS_DYNAMIC_STORAGE, etc.) — not whatever was active on the
        // machine that ran the deploy. Writes public/vendor/scribe/ (must run before Step 9's
        // copy) + resources/views/scribe/ + storage/app/private/scribe/{collection.json,openapi.yaml}.
        // Best-effort: a docs-generation failure must not block the actual deploy.
        // Opt-out per deploy (e.g. production) via the "Generate API Docs" checkbox — when off,
        // any Scribe output already on the target from a previous deploy is left untouched.
        if ($options['generate_docs'] ?? true) {
            $output .= "[scribe] Regenerating API docs for the deployed environment...\n";
            $output .= $ssh->exec("cd {$backendPath} && {$php} artisan scribe:generate --no-interaction && {$php} artisan scribe:polish && {$php} artisan app:sync-postman-collection 2>&1")."\n";
        } else {
            $output .= "[scribe] Skipped (Generate API Docs was off for this deploy).\n";
        }

        // Step 9: Copy public/ contents to public_html/
        $output .= $ssh->exec("cp -rf {$backendPath}/public/* {$publicPath}/ 2>&1")."\n";
        $ssh->exec("cp -f {$backendPath}/public/.htaccess {$publicPath}/.htaccess 2>/dev/null");
        $ssh->exec("rm -f {$publicPath}/hot");

        // Step 10: Create modified index.php
        $indexPhp = <<<'PHPEOF'
<?php

use Illuminate\Http\Request;

define('LARAVEL_START', microtime(true));

if (file_exists($maintenance = __DIR__.'/backend/storage/framework/maintenance.php')) {
    require $maintenance;
}

require __DIR__.'/backend/vendor/autoload.php';

(require_once __DIR__.'/backend/bootstrap/app.php')
    ->handleRequest(Request::capture());
PHPEOF;

        $ssh->exec("cat > {$publicPath}/index.php << 'INDEXEOF'\n{$indexPhp}\nINDEXEOF");

        // Step 11: Storage symlink — ALWAYS recreated, regardless of safe-storage
        // mode. Step 4 unconditionally wipes everything in public_html except
        // backend/ (including any existing public_html/storage symlink), so
        // skipping this step whenever safe-storage is off left every deploy
        // without one — every uploaded file (avatars, attachments, logos) 404'd
        // until the next safe-storage deploy happened to recreate it. Safe-storage
        // mode only controls whether OLD storage/app/public file *contents* are
        // backed up/restored around the wipe (steps 4's start/end); it has no
        // bearing on whether the symlink itself needs rebuilding, since step 4
        // destroys it every single time either way.
        $ssh->exec("rm -rf {$publicPath}/storage");
        $ssh->exec("mkdir -p {$backendPath}/storage/app/public 2>/dev/null");
        $output .= $ssh->exec("ln -sfn {$backendPath}/storage/app/public {$publicPath}/storage 2>&1")."\n";

        // Step 12: .htaccess
        $htaccess = <<<'HTEOF'
<IfModule mod_rewrite.c>
    <IfModule mod_negotiation.c>
        Options -MultiViews -Indexes
    </IfModule>

    RewriteEngine On

    RewriteCond %{HTTP:Authorization} .
    RewriteRule .* - [E=HTTP_AUTHORIZATION:%{HTTP:Authorization}]

    RewriteCond %{REQUEST_FILENAME} !-d
    RewriteCond %{REQUEST_URI} (.+)/$
    RewriteRule ^ %1 [L,R=301]

    RewriteCond %{REQUEST_FILENAME} !-d
    RewriteCond %{REQUEST_FILENAME} !-f
    RewriteRule ^ index.php [L]
</IfModule>
HTEOF;

        $ssh->exec("cat > {$publicPath}/.htaccess << 'HTEOF'\n{$htaccess}\nHTEOF");

        $status = $isFirstTime ? 'Initial setup complete.' : 'Server updated.';

        return ['success' => true, 'message' => $status, 'log' => $output];
    }

    private function getProductionEnvString(string $key): string
    {
        $prodPath = base_path('.env.production');
        if (! file_exists($prodPath)) {
            return '';
        }

        $content = file_get_contents($prodPath);
        if (preg_match('/^'.preg_quote($key, '/').'\s*=\s*(.*)$/m', $content, $match)) {
            $raw = trim($match[1]);

            // Unwrap a quoted value ("..."), reversing escapeEnvValue().
            if (strlen($raw) >= 2 && str_starts_with($raw, '"') && str_ends_with($raw, '"')) {
                $raw = str_replace('\\"', '"', substr($raw, 1, -1));
            }

            return $raw;
        }

        return '';
    }

    private function getLocalMail(): array
    {
        return [
            'MAIL_MAILER' => env('MAIL_MAILER', 'smtp'),
            'MAIL_HOST' => env('MAIL_HOST', ''),
            'MAIL_PORT' => env('MAIL_PORT', '465'),
            'MAIL_USERNAME' => env('MAIL_USERNAME', ''),
            'MAIL_PASSWORD' => env('MAIL_PASSWORD', ''),
            'MAIL_ENCRYPTION' => env('MAIL_ENCRYPTION', 'ssl'),
            'MAIL_FROM_ADDRESS' => env('MAIL_FROM_ADDRESS', ''),
        ];
    }

    private function getProductionMail(): array
    {
        $prodPath = base_path('.env.production');
        $mailKeys = ['MAIL_MAILER', 'MAIL_HOST', 'MAIL_PORT', 'MAIL_USERNAME', 'MAIL_PASSWORD', 'MAIL_ENCRYPTION', 'MAIL_FROM_ADDRESS'];
        $defaults = [
            'MAIL_MAILER' => 'smtp',
            'MAIL_HOST' => '',
            'MAIL_PORT' => '465',
            'MAIL_USERNAME' => '',
            'MAIL_PASSWORD' => '',
            'MAIL_ENCRYPTION' => 'ssl',
            'MAIL_FROM_ADDRESS' => '',
        ];

        if (! file_exists($prodPath)) {
            return $defaults;
        }

        $content = file_get_contents($prodPath);
        foreach ($mailKeys as $key) {
            if (preg_match('/^'.preg_quote($key, '/').'\s*=\s*"?([^"\n]*)"?$/m', $content, $match)) {
                $defaults[$key] = trim($match[1]);
            }
        }

        return $defaults;
    }

    private function getProductionPusher(): array
    {
        $prodPath = base_path('.env.production');
        $pusherKeys = ['PUSHER_APP_ID', 'PUSHER_APP_KEY', 'PUSHER_APP_SECRET', 'PUSHER_APP_CLUSTER'];
        $defaults = [
            'app_id' => '',
            'app_key' => '',
            'app_secret' => '',
            'app_cluster' => 'eu',
        ];

        if (! file_exists($prodPath)) {
            return $defaults;
        }

        $content = file_get_contents($prodPath);
        $keyMap = [
            'PUSHER_APP_ID' => 'app_id',
            'PUSHER_APP_KEY' => 'app_key',
            'PUSHER_APP_SECRET' => 'app_secret',
            'PUSHER_APP_CLUSTER' => 'app_cluster',
        ];

        foreach ($pusherKeys as $key) {
            if (preg_match('/^'.preg_quote($key, '/').'\s*=\s*"?([^"\n]*)"?$/m', $content, $match)) {
                $defaults[$keyMap[$key]] = trim($match[1]);
            }
        }

        return $defaults;
    }

    private function rebuildProductionEnv(array $overrides): void
    {
        $prodPath = base_path('.env.production');

        // If .env.production exists, update it in place. Otherwise, create from local .env.
        $prodEnv = file_exists($prodPath)
            ? file_get_contents($prodPath)
            : file_get_contents(base_path('.env'));

        // Always enforce production-safe defaults on first creation
        if (! file_exists($prodPath)) {
            $overrides = array_merge([
                'APP_ENV' => 'production',
                'APP_DEBUG' => 'false',
                'IS_TESTING' => 'false',
            ], $overrides);
        }

        foreach ($overrides as $key => $value) {
            $line = $key.'='.$this->escapeEnvValue($value ?? '');
            if (preg_match('/^'.preg_quote($key, '/').'\s*=\s*.+$/m', $prodEnv)) {
                // Callback replacement so $ and \ in the value aren't treated as backrefs.
                $prodEnv = preg_replace_callback(
                    '/^'.preg_quote($key, '/').'\s*=\s*.+$/m',
                    fn () => $line,
                    $prodEnv
                );
            } else {
                $prodEnv .= "\n".$line;
            }
        }

        file_put_contents($prodPath, $prodEnv);
    }

    private function createImageWithAlpha($file): ?\GdImage
    {
        $source = imagecreatefromstring(file_get_contents($file->getRealPath()));

        if (! $source) {
            return null;
        }

        $width = imagesx($source);
        $height = imagesy($source);

        $image = imagecreatetruecolor($width, $height);
        imagesavealpha($image, true);
        imagealphablending($image, false);

        $transparent = imagecolorallocatealpha($image, 0, 0, 0, 127);
        imagefill($image, 0, 0, $transparent);

        imagealphablending($image, true);
        imagecopy($image, $source, 0, 0, 0, 0, $width, $height);
        imagesavealpha($image, true);

        imagedestroy($source);

        return $image;
    }

    /**
     * Keys whose production value is set independently of the local one: they are not
     * copied to .env.production by setEnvValue(). updateEnv()'s `base` payload edits the
     * boolean ones there directly (self::BASE_TOGGLES); updateUrls() edits the two URLs.
     */
    private array $productionOnlyKeys = ['IS_TESTING', 'APP_URL', 'FRONTEND_URL'];

    /**
     * Keys that must never reach .env.production at all — a separate list from
     * $productionOnlyKeys, which marks keys that are set *on* production independently
     * rather than mirrored there.
     *
     * `buildFlavorEnv()` starts from the whole .env.production file and only overrides
     * specific keys, so anything left in it is copied verbatim onto every deploy target.
     * The Hostinger token is only ever used by this panel, which is local-only — on a
     * live server it would be an unusable credential that can create and delete
     * databases, subdomains and websites across the entire hosting account.
     */
    private array $neverSyncToProductionKeys = ['HOSTINGER_API_TOKEN'];

    private function setEnvValue(string $key, string $value): void
    {
        $this->writeEnvKey(base_path('.env'), $key, $value);

        // Sync to .env.production unless key is local-only
        $prodPath = base_path('.env.production');
        if (file_exists($prodPath)
            && ! in_array($key, $this->productionOnlyKeys)
            && ! in_array($key, $this->neverSyncToProductionKeys)) {
            $this->writeEnvKey($prodPath, $key, $value);
        }
    }

    /**
     * Quote an env value when it contains spaces or characters (#, ") that would
     * otherwise truncate/break the line. `#` starts a comment in dotenv, so an
     * unquoted password like `Admin@123#` would be silently cut to `Admin@123`.
     */
    private function escapeEnvValue(string $value): string
    {
        // A newline inside a value would close the line and let whatever follows
        // become its own env directive (`pass\nAPP_DEBUG=true`), so line breaks are
        // stripped rather than escaped — no env value legitimately contains one.
        $value = str_replace(["\r", "\n"], '', $value);

        return (str_contains($value, ' ') || str_contains($value, '#') || str_contains($value, '"'))
            ? '"'.addcslashes($value, '"').'"'
            : $value;
    }

    private function writeEnvKey(string $path, string $key, string $value): void
    {
        $original = file_get_contents($path);
        $line = $key.'='.$this->escapeEnvValue($value);

        // Line-based rather than a regex replacement: the value can contain `$` and `\`,
        // which preg_replace would read as backreferences.
        $pattern = '/^'.preg_quote($key, '/').'\s*=/';
        $written = false;
        $lines = [];

        foreach (preg_split("/\r\n|\n|\r/", $original) as $existing) {
            if (! preg_match($pattern, $existing)) {
                $lines[] = $existing;

                continue;
            }

            // First hit takes the new value; any further hit is a duplicate line for the
            // same key and is dropped. Duplicates happen because a key whose value is
            // empty (`SOCIAL_AUTH_PROVIDERS=`, `AWS_BUCKET=`) used not to match at all,
            // so every save appended another line instead of replacing one.
            if (! $written) {
                $lines[] = $line;
                $written = true;
            }
        }

        if (! $written) {
            $lines[] = $line;
        }

        $env = implode("\n", $lines);

        if ($env === $original) {
            return;
        }

        // `php artisan serve` polls this file's mtime and restarts the whole server when
        // it moves. The restarted process cannot rebind the port the one it just killed
        // is still holding, so it takes 8001, then 8002, and the tab you are looking at
        // stops answering — on every save.
        //
        // The restart is not buying anything here. With reload on, `serve` hands the
        // child every non-passthrough variable as `false` (ServeCommand::startProcess),
        // so the child re-reads .env from disk on each request; a value written now is
        // live on the next one. A restart is only needed for ServeCommand's
        // $passthroughVariables — APP_ENV, PATH, the Herd and Xdebug keys — and this
        // panel writes none of them.
        //
        // So: write the new content, then put the timestamp back.
        $mtime = filemtime($path);

        file_put_contents($path, $env);

        if ($mtime !== false) {
            touch($path, $mtime);
        }
    }

    private function parseColors(string $css, string $selector): array
    {
        $colors = [];
        $pattern = $selector === ':root'
            ? '/:root\s*\{([^}]+)\}/'
            : '/\.dark\s*\{([^}]+)\}/';

        if (preg_match($pattern, $css, $match)) {
            $block = $match[1];
            foreach ($this->colorVars as $var) {
                if (preg_match('/--'.preg_quote($var, '/').':\s*(.+?);/', $block, $m)) {
                    $colors[$var] = trim($m[1]);
                }
            }
        }

        return $colors;
    }

    private function hexToOklch(string $hex): string
    {
        $hex = ltrim($hex, '#');
        $r = hexdec(substr($hex, 0, 2)) / 255;
        $g = hexdec(substr($hex, 2, 2)) / 255;
        $b = hexdec(substr($hex, 4, 2)) / 255;

        $r = $r <= 0.04045 ? $r / 12.92 : pow(($r + 0.055) / 1.055, 2.4);
        $g = $g <= 0.04045 ? $g / 12.92 : pow(($g + 0.055) / 1.055, 2.4);
        $b = $b <= 0.04045 ? $b / 12.92 : pow(($b + 0.055) / 1.055, 2.4);

        $x = 0.4124564 * $r + 0.3575761 * $g + 0.1804375 * $b;
        $y = 0.2126729 * $r + 0.7151522 * $g + 0.0721750 * $b;
        $z = 0.0193339 * $r + 0.1191920 * $g + 0.9503041 * $b;

        $l = 0.8189330101 * $x + 0.3618667424 * $y - 0.1288597137 * $z;
        $m = 0.0329845436 * $x + 0.9293118715 * $y + 0.0361456387 * $z;
        $s = 0.0482003018 * $x + 0.2643662691 * $y + 0.6338517070 * $z;

        $l = $l > 0 ? pow($l, 1 / 3) : 0;
        $m = $m > 0 ? pow($m, 1 / 3) : 0;
        $s = $s > 0 ? pow($s, 1 / 3) : 0;

        $L = 0.2104542553 * $l + 0.7936177850 * $m - 0.0040720468 * $s;
        $A = 1.9779984951 * $l - 2.4285922050 * $m + 0.4505937099 * $s;
        $B = 0.0259040371 * $l + 0.7827717662 * $m - 0.8086757660 * $s;

        $C = sqrt($A * $A + $B * $B);
        $H = atan2($B, $A) * (180 / M_PI);
        if ($H < 0) {
            $H += 360;
        }

        return sprintf('oklch(%.4f %.4f %.4f)', $L, $C, $H);
    }
}
