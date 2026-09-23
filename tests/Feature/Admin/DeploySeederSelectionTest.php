<?php

use Illuminate\Testing\TestResponse;

/**
 * The DevSettings deploy modal lets an admin pick individual seeders instead of the old
 * all-or-nothing "Run seeders" checkbox. A seeder name ends up in an `artisan db:seed
 * --class=...` over SSH, so the whitelist is a security boundary, not a nicety.
 *
 * Nothing here runs a deploy: every POST carries a deliberately invalid `flavor`, which
 * stops the request inside the validator — one step before `deploy()` starts building
 * assets and opening SSH connections. What each case asserts is which *other* fields the
 * validator did or did not complain about.
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
function postDeploy(array $payload): TestResponse
{
    return test()->actingAs(adminUser())->post(route('dev_settings.deploy'), array_merge([
        'flavor' => 'not-a-real-flavor',
        'migration_option' => 'migrate',
        '_token' => csrf_token(),
    ], $payload));
}

it('exposes the discovered seeders to the deploy UI', function () {
    $this->actingAs(adminUser())
        ->get(route('dev_settings'))
        ->assertOk()
        ->assertInertia(function ($page) {
            $seeders = $page->toArray()['props']['availableSeeders'];
            $classes = array_column($seeders, 'class');

            expect($classes)->toContain('RoleSeeder')
                // "all of them" is what the migrate options mean; it is not a picker entry.
                ->not->toContain('DatabaseSeeder')
                ->and($seeders[0])->toHaveKeys(['class', 'label', 'description'])
                ->and($classes)->toBe(collect($classes)->sort()->values()->all());
        });
});

it('refuses a seeder name that is not a discovered class', function () {
    postDeploy(['seeders' => ['RoleSeeder; rm -rf /']])
        ->assertSessionHasErrors('seeders.0');
});

it('accepts a real seeder class', function () {
    postDeploy(['seeders' => ['RoleSeeder']])
        ->assertSessionHasErrors('flavor')
        ->assertSessionDoesntHaveErrors('seeders.0');
});

it('accepts a legacy run_seeders payload', function () {
    // A deploy config saved before the picker existed. It must not fail validation — the
    // deploy translates it into a plain `db:seed` on the target.
    postDeploy(['run_seeders' => true])
        ->assertSessionHasErrors('flavor')
        ->assertSessionDoesntHaveErrors('run_seeders');
});

it('no longer accepts the migrate-and-seed option', function () {
    // Seeding is the picker's job now: "migrate and seed everything" was the blunt version
    // of it, and on a live site it re-seeded demo content just to refresh roles.
    postDeploy(['migration_option' => 'migrate_seed'])
        ->assertSessionHasErrors('migration_option');
});

it('still accepts the three options that are left', function (string $option) {
    // The deliberately invalid flavor stops the request in the validator, so an absent
    // complaint about migration_option is the assertion.
    postDeploy(['migration_option' => $option])
        ->assertSessionDoesntHaveErrors('migration_option');
})->with(['migrate', 'fresh_seed', 'none']);
