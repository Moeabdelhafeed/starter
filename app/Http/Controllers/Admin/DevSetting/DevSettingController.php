<?php

namespace App\Http\Controllers\Admin\DevSetting;

use App\Events\TestBroadcast;
use App\Helpers\FCMHelper;
use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Log;
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
        'HAS_TRANSLATIONS',
        'IS_TESTING',
        'APP_DEBUG',
        'IS_OTP_WHATSAPP',
    ];

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
            'authConfig' => [
                'identifiers' => array_map('trim', explode(',', env('AUTH_IDENTIFIERS', 'email'))),
                'has_email_field' => filter_var(env('HAS_EMAIL_FIELD', true), FILTER_VALIDATE_BOOLEAN),
                'has_phone_field' => filter_var(env('HAS_PHONE_FIELD', false), FILTER_VALIDATE_BOOLEAN),
                'has_username_field' => filter_var(env('HAS_USERNAME_FIELD', false), FILTER_VALIDATE_BOOLEAN),
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
            'productionDb' => $this->getProductionDb(),
            'productionMail' => $this->getProductionMail(),
            'localMail' => $this->getLocalMail(),
            'productionTesting' => $this->getProductionEnvValue('IS_TESTING'),
            'deployConfig' => $this->getDeployConfig(),
            'deployLog' => session('deploy_log'),
            'appName' => env('APP_NAME', 'Starter'),
            'apiToken' => [
                'local' => env('APP_X_API_TOKEN', ''),
                'production' => $this->getProductionEnvString('APP_X_API_TOKEN'),
            ],
            'adminCredentials' => [
                'local' => [
                    'ADMIN_EMAIL' => env('ADMIN_EMAIL', ''),
                    'ADMIN_PASSWORD' => env('ADMIN_PASSWORD', ''),
                ],
                'production' => [
                    'ADMIN_EMAIL' => $this->getProductionEnvString('ADMIN_EMAIL'),
                    'ADMIN_PASSWORD' => $this->getProductionEnvString('ADMIN_PASSWORD'),
                ],
            ],
            'urls' => [
                'local' => [
                    'APP_URL' => env('APP_URL', 'http://localhost'),
                    'FRONTEND_URL' => env('FRONTEND_URL', 'http://localhost:5173'),
                ],
                'production' => [
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
                'production' => $this->getProductionPusher(),
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
        ]);
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

    public function updateEnv(Request $request)
    {
        $validated = $request->validate([
            'key' => ['required', 'string', 'in:'.implode(',', $this->envToggles)],
            'value' => ['required'],
        ]);

        $key = $validated['key'];
        $value = filter_var($validated['value'], FILTER_VALIDATE_BOOLEAN) ? 'true' : 'false';

        $this->setEnvValue($key, $value);

        Artisan::call('config:clear');

        return redirect()->back()->with('success', $key.' updated to '.$value.'.');
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
        ]);

        $this->setEnvValue('AUTH_IDENTIFIERS', implode(',', $validated['identifiers']));
        $this->setEnvValue('HAS_EMAIL_FIELD', $validated['has_email_field'] ? 'true' : 'false');
        $this->setEnvValue('HAS_PHONE_FIELD', $validated['has_phone_field'] ? 'true' : 'false');
        $this->setEnvValue('HAS_USERNAME_FIELD', $validated['has_username_field'] ? 'true' : 'false');

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

    public function downloadPostman()
    {
        $path = base_path('Starter.postman_collection.json');

        if (! file_exists($path)) {
            return redirect()->back()->with('error', 'Postman collection file not found.');
        }

        return response()->download($path);
    }

    public function updateProductionDb(Request $request)
    {
        $validated = $request->validate([
            'DB_HOST' => ['required', 'string', 'max:255'],
            'DB_PORT' => ['required', 'string', 'max:10'],
            'DB_DATABASE' => ['required', 'string', 'max:255'],
            'DB_USERNAME' => ['required', 'string', 'max:255'],
            'DB_PASSWORD' => ['nullable', 'string', 'max:255'],
        ]);

        $this->rebuildProductionEnv($validated);

        return redirect()->back()->with('success', 'Production database config saved.');
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
            'ADMIN_PASSWORD' => ['required', 'string', 'min:8', 'max:255'],
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

    public function saveDeployConfig(Request $request)
    {
        $validated = $request->validate([
            'ssh_host' => ['required', 'string', 'max:255'],
            'ssh_port' => ['required', 'integer', 'min:1', 'max:65535'],
            'ssh_username' => ['required', 'string', 'max:255'],
            'ssh_password' => ['required', 'string', 'max:255'],
            'domain' => ['required', 'string', 'max:255'],
        ]);

        file_put_contents(base_path('.deploy.json'), json_encode($validated, JSON_PRETTY_PRINT));

        // Sync domain to production APP_URL
        $this->rebuildProductionEnv([
            'APP_URL' => 'https://'.$validated['domain'],
        ]);

        return redirect()->back()->with('success', 'Deploy config saved.');
    }

    public function deploy(Request $request)
    {
        $validated = $request->validate([
            'migration_option' => ['required', 'in:migrate,migrate_seed,fresh_seed,none'],
            'run_seeders' => ['boolean'],
        ]);

        set_time_limit(600);

        $base = base_path();
        $prodPath = $base.'/.env.production';
        $deployPath = $base.'/.deploy.json';

        if (! file_exists($prodPath)) {
            return redirect()->back()->with('error', 'No .env.production found. Save production config first.');
        }

        if (! file_exists($deployPath)) {
            return redirect()->back()->with('error', 'No SSH config found. Save SSH settings first.');
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

        // Step 2: Deploy via SCP with options
        $config = json_decode(file_get_contents($deployPath), true);
        $deployOptions = [
            'migration_option' => $validated['migration_option'],
            'run_seeders' => $validated['run_seeders'] ?? false,
        ];
        $sshResult = $this->runSshDeploy($config, $deployOptions);

        if ($sshResult['success']) {
            return redirect()->back()
                ->with('success', 'Deployed successfully. '.$sshResult['message'])
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
        if (! $realPath || ! str_starts_with($realPath, $base)) {
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
            exec("cd {$base} && git rev-list --left-right --count {$currentBranch}...origin/{$currentBranch} 2>&1", $countOutput, $countExit);
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
        ];
    }

    private function getProductionDb(): array
    {
        $prodPath = base_path('.env.production');
        $dbKeys = ['DB_HOST', 'DB_PORT', 'DB_DATABASE', 'DB_USERNAME', 'DB_PASSWORD'];
        $defaults = [
            'DB_HOST' => '',
            'DB_PORT' => '3306',
            'DB_DATABASE' => '',
            'DB_USERNAME' => '',
            'DB_PASSWORD' => '',
        ];

        if (! file_exists($prodPath)) {
            return $defaults;
        }

        $content = file_get_contents($prodPath);
        foreach ($dbKeys as $key) {
            if (preg_match('/^'.preg_quote($key, '/').'\s*=\s*(.*)$/m', $content, $match)) {
                $defaults[$key] = trim($match[1]);
            }
        }

        return $defaults;
    }

    public function updateProductionEnv(Request $request)
    {
        $request->validate([
            'key' => ['required', 'string', 'in:'.implode(',', $this->productionOnlyKeys)],
            'value' => ['required'],
        ]);

        $prodPath = base_path('.env.production');
        if (! file_exists($prodPath)) {
            return redirect()->back()->with('error', 'No .env.production found. Save production config first.');
        }

        $key = $request->input('key');
        $value = $request->input('value');

        // Boolean toggles
        if (in_array($key, ['IS_TESTING'])) {
            $value = filter_var($value, FILTER_VALIDATE_BOOLEAN) ? 'true' : 'false';
        }

        $this->writeEnvKey($prodPath, $key, $value);

        return redirect()->back()->with('success', 'Production '.$key.' updated.');
    }

    public function updateUrls(Request $request)
    {
        $validated = $request->validate([
            'target' => ['required', 'in:local,production'],
            'APP_URL' => ['required', 'string', 'max:255'],
            'FRONTEND_URL' => ['nullable', 'string', 'max:255'],
        ]);

        $target = $validated['target'];

        if ($target === 'local') {
            $this->writeEnvKey(base_path('.env'), 'APP_URL', $validated['APP_URL']);
            $this->writeEnvKey(base_path('.env'), 'FRONTEND_URL', $validated['FRONTEND_URL']);
            Artisan::call('config:clear');
        } else {
            $this->rebuildProductionEnv([
                'APP_URL' => $validated['APP_URL'],
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

    private function getDeployConfig(): array
    {
        $path = base_path('.deploy.json');
        $defaults = [
            'ssh_host' => '',
            'ssh_port' => 65002,
            'ssh_username' => '',
            'ssh_password' => '',
            'domain' => '',
        ];

        if (! file_exists($path)) {
            return array_merge($defaults, ['has_config' => false]);
        }

        $config = json_decode(file_get_contents($path), true) ?? [];

        return array_merge($defaults, $config, ['has_config' => true]);
    }

    private function runSshDeploy(array $config, array $options = []): array
    {
        $base = base_path();
        $php = '/opt/alt/php84/usr/bin/php';
        $domain = $config['domain'] ?? null;
        $migrationOption = $options['migration_option'] ?? 'migrate_seed';
        $runSeeders = $options['run_seeders'] ?? false;

        if (! $domain) {
            return ['success' => false, 'message' => 'No domain configured in SSH settings.'];
        }

        $publicPath = "~/domains/{$domain}/public_html";
        $backendPath = "{$publicPath}/backend";
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

        // Step 4: Wipe public_html except backend/, unzip into backend/
        $ssh->exec("find {$publicPath} -mindepth 1 -maxdepth 1 ! -name 'backend' -exec rm -rf {} + 2>/dev/null");
        $ssh->exec("rm -rf {$backendPath}");
        $ssh->exec("mkdir -p {$backendPath}");
        $output .= $ssh->exec("unzip -o {$remoteZip} -d {$backendPath} 2>&1")."\n";
        $ssh->exec("rm -f {$remoteZip}");

        // Step 5: Setup env
        $output .= $ssh->exec("cd {$backendPath} && cp .env.production .env 2>&1")."\n";

        // Step 6: Composer install
        $output .= $ssh->exec("cd {$backendPath} && {$php} /usr/local/bin/composer install --no-dev --optimize-autoloader --ignore-platform-reqs 2>&1")."\n";

        // Step 7: Remove hot file
        $ssh->exec("rm -f {$backendPath}/public/hot");

        // Step 8: Laravel commands
        if ($isFirstTime) {
            $output .= $ssh->exec("cd {$backendPath} && {$php} artisan key:generate --force 2>&1")."\n";
        }

        // Run migrations based on option
        // If first time deployment, fresh_seed doesn't make sense (no tables to drop)
        // So we treat it as migrate_seed for initial setup
        if ($isFirstTime && $migrationOption === 'fresh_seed') {
            $output .= "First deployment detected. Running migrate --seed instead of migrate:fresh.\n";
            $migrationOption = 'migrate_seed';
        }

        switch ($migrationOption) {
            case 'fresh_seed':
                // WARNING: This wipes all data!
                $output .= $ssh->exec("cd {$backendPath} && {$php} artisan migrate:fresh --seed --force 2>&1")."\n";
                break;
            case 'migrate_seed':
                $output .= $ssh->exec("cd {$backendPath} && {$php} artisan migrate --seed --force 2>&1")."\n";
                break;
            case 'migrate':
                $output .= $ssh->exec("cd {$backendPath} && {$php} artisan migrate --force 2>&1")."\n";
                break;
            case 'none':
                // Skip migrations
                $output .= "Skipping migrations (user selected 'none').\n";
                break;
        }

        // Run seeders separately if requested and not already run
        if ($runSeeders && ! in_array($migrationOption, ['fresh_seed', 'migrate_seed'])) {
            $output .= $ssh->exec("cd {$backendPath} && {$php} artisan db:seed --force 2>&1")."\n";
        }

        $output .= $ssh->exec("cd {$backendPath} && {$php} artisan config:clear && {$php} artisan route:clear && {$php} artisan view:clear 2>&1")."\n";

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

        // Step 11: Storage symlink
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
            return trim($match[1]);
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
            $value = $value ?? '';
            if (preg_match('/^'.preg_quote($key, '/').'\s*=\s*.+$/m', $prodEnv)) {
                $prodEnv = preg_replace(
                    '/^'.preg_quote($key, '/').'\s*=\s*.+$/m',
                    $key.'='.$value,
                    $prodEnv
                );
            } else {
                $prodEnv .= "\n".$key.'='.$value;
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

    private array $productionOnlyKeys = ['IS_TESTING', 'APP_URL', 'FRONTEND_URL'];

    private function setEnvValue(string $key, string $value): void
    {
        $this->writeEnvKey(base_path('.env'), $key, $value);

        // Sync to .env.production unless key is local-only
        $prodPath = base_path('.env.production');
        if (file_exists($prodPath) && ! in_array($key, $this->productionOnlyKeys)) {
            $this->writeEnvKey($prodPath, $key, $value);
        }
    }

    private function writeEnvKey(string $path, string $key, string $value): void
    {
        $env = file_get_contents($path);

        // Quote values that contain spaces or special characters
        $escaped = (str_contains($value, ' ') || str_contains($value, '#') || str_contains($value, '"'))
            ? '"'.addcslashes($value, '"').'"'
            : $value;

        $line = $key.'='.$escaped;

        if (preg_match('/^'.preg_quote($key, '/').'\s*=\s*.+$/m', $env)) {
            $env = preg_replace(
                '/^'.preg_quote($key, '/').'\s*=\s*.+$/m',
                $line,
                $env
            );
        } else {
            $env .= "\n".$line;
        }

        file_put_contents($path, $env);
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
