<?php

namespace App\Console\Commands;

use Dotenv\Dotenv;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Env;

#[Signature('app:assert-production-safety')]
#[Description('Fail the build when a production environment is configured in a way that leaks data or disables protections.')]
class AssertProductionSafety extends Command
{
    /**
     * Passwords shipped in .env.example / install.sh. A live install still using one
     * of these has an admin account anyone can log into.
     *
     * @var array<int, string>
     */
    private const DEFAULT_ADMIN_PASSWORDS = ['Admin@123#', 'password', 'secret'];

    public function handle(): int
    {
        if (! $this->getLaravel()->environment('production')) {
            $this->components->info('Not a production environment ('.app()->environment().') — production safety checks skipped.');

            return self::SUCCESS;
        }

        $failures = array_values(array_filter([
            config('app.debug')
                ? 'APP_DEBUG is true — stack traces, env values and SQL are served to anyone who triggers an error. Set APP_DEBUG=false.'
                : null,

            // Read raw, not through config(): AppServiceProvider force-disables the flag
            // in production, so config('app.is_testing') is always false here and would
            // hide an env file that still ships IS_TESTING=true.
            $this->rawEnvIsTrue('IS_TESTING')
                ? 'IS_TESTING is true — rate limiting is off, OTP codes come back in API responses and 5xx bodies leak SQL. Set IS_TESTING=false.'
                : null,

            filled(config('app.x_api_token'))
                ? null
                : 'APP_X_API_TOKEN is empty — the X-API-TOKEN gate on every API route accepts nothing. Set a random 64-char token.',

            in_array((string) config('admin.password'), self::DEFAULT_ADMIN_PASSWORDS, true)
                ? 'ADMIN_PASSWORD is still the starter default — change it, then re-run the seeder.'
                : null,

            filled(config('app.key'))
                ? null
                : 'APP_KEY is empty — sessions and encrypted cookies cannot be signed. Run `php artisan key:generate`.',

            // Also read raw: Laravel forces secure cookies on in production, so
            // config('session.secure') can't tell an explicit setting from the default.
            // The env file should still say it, for the day the app runs elsewhere.
            $this->rawEnvIsTrue('SESSION_SECURE_COOKIE')
                ? null
                : 'SESSION_SECURE_COOKIE is not true — the admin session cookie can travel over plain HTTP. Set SESSION_SECURE_COOKIE=true.',
        ]));

        if ($failures !== []) {
            $this->newLine();
            $this->components->error(count($failures).' production safety check(s) failed');

            foreach ($failures as $failure) {
                $this->components->twoColumnDetail('<fg=red>✗</>', $failure);
            }

            $this->newLine();

            return self::FAILURE;
        }

        $this->newLine();
        $this->components->info('<fg=green>All production safety checks passed</>');
        $this->components->bulletList([
            'APP_DEBUG off',
            'IS_TESTING off',
            'APP_X_API_TOKEN set',
            'ADMIN_PASSWORD changed from the default',
            'APP_KEY set',
            'SESSION_SECURE_COOKIE on',
        ]);

        return self::SUCCESS;
    }

    /**
     * A boolean flag as the deployed environment actually declares it.
     *
     * Falls back to parsing the .env file because `php artisan config:cache`
     * skips loading environment variables altogether — env() returns null on a
     * cached production install, which would make these checks pass blindly.
     */
    private function rawEnvIsTrue(string $key): bool
    {
        $value = Env::get($key);

        if ($value === null && is_file($path = base_path('.env'))) {
            $value = Dotenv::parse((string) file_get_contents($path))[$key] ?? null;
        }

        return filter_var($value, FILTER_VALIDATE_BOOLEAN);
    }
}
