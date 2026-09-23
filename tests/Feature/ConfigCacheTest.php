<?php

use Illuminate\Support\Facades\File;

/**
 * `php artisan config:cache` stops loading the .env file entirely, so any `env()`
 * call left outside config/ starts returning null in production — silently. In this
 * codebase that is not a cosmetic problem: routes/web.php and routes/api.php register
 * whole features behind `config('features.*')`, and the moment one of those reads a
 * bare `env()` instead, the feature's routes simply stop existing on a cached deploy.
 * That failure mode has hit this project before, hence a guard rather than a habit.
 *
 * Scanning is done over PHP tokens rather than with a text search so that the word
 * "env(" inside a comment or a string is not mistaken for a call.
 *
 * @return array<int, string> "path:line" for every runtime env() call found
 */
function runtimeEnvCalls(): array
{
    $exempt = [
        // A local-only editor for the .env file itself: reading the raw environment
        // is its entire job, and it never runs on a config-cached install.
        base_path('app/Http/Controllers/Admin/DevSetting/DevSettingController.php'),
    ];

    $offenders = [];

    $files = array_merge(
        File::allFiles(base_path('app')),
        File::allFiles(base_path('routes')),
    );

    foreach ($files as $file) {
        if ($file->getExtension() !== 'php' || in_array($file->getRealPath(), $exempt, true)) {
            continue;
        }

        // Drop whitespace and comments so "the next token" means the next real one.
        $tokens = array_values(array_filter(
            token_get_all(File::get($file->getRealPath())),
            fn ($token) => ! is_array($token) || ! in_array($token[0], [T_WHITESPACE, T_COMMENT, T_DOC_COMMENT], true),
        ));

        foreach ($tokens as $index => $token) {
            if (! is_array($token) || $token[0] !== T_STRING || strtolower($token[1]) !== 'env') {
                continue;
            }

            if (($tokens[$index + 1] ?? null) !== '(') {
                continue;
            }

            // `$obj->env(...)`, `Foo::env(...)` and `function env(...)` are not the helper.
            $previous = $tokens[$index - 1] ?? null;
            if (is_array($previous) && in_array($previous[0], [T_OBJECT_OPERATOR, T_NULLSAFE_OBJECT_OPERATOR, T_DOUBLE_COLON, T_FUNCTION], true)) {
                continue;
            }

            $offenders[] = str_replace(base_path().'/', '', $file->getRealPath()).':'.$token[2];
        }
    }

    return $offenders;
}

it('scans a real set of php files', function () {
    // A broken glob above would turn the guard below into a test that can never fail.
    expect(count(File::allFiles(base_path('app'))))->toBeGreaterThan(50);
});

it('never calls env() outside config/, so config:cache cannot unregister a feature', function () {
    expect(runtimeEnvCalls())->toBe([]);
});
