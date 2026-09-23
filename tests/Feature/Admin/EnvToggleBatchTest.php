<?php

use Illuminate\Support\Facades\Route;
use Illuminate\Testing\TestResponse;

/**
 * DevSettings writes the feature toggles in one batched request behind a Save button,
 * rather than one request per click.
 *
 * `updateEnv()` rewrites the developer's real .env (and mirrors into .env.production),
 * so nothing here may be allowed to reach the controller body: every case below is
 * rejected by the validator, and each one additionally asserts that .env is unchanged
 * byte for byte. That second assertion is the guard — if a whitelist is ever loosened
 * by accident, the test fails instead of quietly editing the machine it runs on.
 */

// DevSettings routes only exist in the local environment (routes/web.php), so the app is
// rebuilt as `local` for this file. The in-memory sqlite database goes with the old app,
// hence the manual migrate.
beforeEach(function () {
    $_SERVER['APP_ENV'] = $_ENV['APP_ENV'] = 'local';
    putenv('APP_ENV=local');
    $this->refreshApplication();
    $this->artisan('migrate');
    // CSRF is only skipped while the app believes it is running tests, which the
    // environment switch above just made false.
    $this->startSession();
});

afterEach(function () {
    $_SERVER['APP_ENV'] = $_ENV['APP_ENV'] = 'testing';
    putenv('APP_ENV=testing');
});

/** @param  array<string, mixed>  $payload */
function putEnvToggles(array $payload): TestResponse
{
    return test()->actingAs(adminUser())->put(route('dev_settings.env'), array_merge([
        '_token' => csrf_token(),
    ], $payload));
}

/**
 * Run a request that must not write, and prove it didn't.
 *
 * @param  array<string, mixed>  $payload
 */
function expectNoEnvWrite(array $payload, string $invalidField): void
{
    $before = file_get_contents(base_path('.env'));

    putEnvToggles($payload)->assertSessionHasErrors($invalidField);

    expect(file_get_contents(base_path('.env')))->toBe($before);
}

it('refuses a key that is not a known feature toggle', function () {
    // Not in $envToggles. Accepting it would let this endpoint set *any* env value,
    // including APP_KEY or the database credentials.
    expectNoEnvWrite(['values' => ['SOMETHING_ELSE' => true]], 'values');
});

it('refuses a whole batch when only one of its keys is unknown', function () {
    // The valid key alongside it must not be written either: a partial apply would
    // report failure while having changed the file.
    expectNoEnvWrite(['values' => ['HAS_PAGES' => true, 'DB_PASSWORD' => false]], 'values');
});

it('refuses a non-boolean toggle value', function () {
    expectNoEnvWrite(['values' => ['HAS_PAGES' => 'probably']], 'values.HAS_PAGES');
});

it('refuses an empty batch', function () {
    expectNoEnvWrite(['values' => []], 'values');
});

it('refuses a production override that is not a production toggle', function () {
    // APP_URL is in $productionOnlyKeys but is not a boolean — it belongs to
    // updateUrls(), and self::BASE_TOGGLES is the narrower list this endpoint accepts.
    expectNoEnvWrite(
        ['values' => ['HAS_PAGES' => true], 'base' => ['APP_URL' => true]],
        'base',
    );
});

it('refuses a non-boolean production override', function () {
    expectNoEnvWrite(
        ['values' => ['HAS_PAGES' => true], 'base' => ['IS_TESTING' => 'yes please']],
        'base.IS_TESTING',
    );
});

it('no longer exposes the single-key production env endpoint', function () {
    // Folded into updateEnv()'s `base` payload so saving the card is one request.
    expect(Route::has('dev_settings.production_env'))->toBeFalse();
});

it('offers every toggle it accepts to the UI', function () {
    // The panel groups these by hand in EnvironmentSection.vue; a key added to the
    // controller and to no group still has to render, which `env_group_other` covers.
    $this->actingAs(adminUser())
        ->get(route('dev_settings'))
        ->assertInertia(fn ($page) => $page
            ->has('envToggles')
            ->where('envToggles', fn ($toggles) => collect($toggles)->contains('HAS_PAGES')
                && collect($toggles)->contains('IS_TESTING'))
        );
});
