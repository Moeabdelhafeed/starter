<?php

use App\Services\Hostinger;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Route;
use Illuminate\Testing\TestResponse;

/**
 * The Databases section reads a live Hostinger account and hands out phpMyAdmin
 * sign-on links.
 *
 * As in HostingerProvisionTest: every HTTP request is faked and strays are blocked, so
 * nothing here can reach the real account.
 */

// DevSettings routes only exist in the local environment (routes/web.php), so the app is
// rebuilt as `local` for this file. The in-memory sqlite database goes with the old app,
// hence the manual migrate.
beforeEach(function () {
    $_SERVER['APP_ENV'] = $_ENV['APP_ENV'] = 'local';
    putenv('APP_ENV=local');
    $this->refreshApplication();
    $this->artisan('migrate');
    $this->startSession();

    Http::preventStrayRequests();
    config(['services.hostinger.token' => 'test-token']);
});

/**
 * `postJson` with the CSRF header. CSRF is only skipped while the app believes it is
 * running tests, which the `local` switch above just made false.
 *
 * @param  array<string, mixed>  $payload
 */
function devPost(string $route, array $payload): TestResponse
{
    return test()->actingAs(adminUser())
        ->withHeaders(['X-CSRF-TOKEN' => csrf_token()])
        ->postJson(route($route), $payload);
}

afterEach(function () {
    $_SERVER['APP_ENV'] = $_ENV['APP_ENV'] = 'testing';
    putenv('APP_ENV=testing');
});

it('registers the database routes only in local', function () {
    expect(Route::has('dev_settings.databases'))->toBeTrue()
        ->and(Route::has('dev_settings.phpmyadmin_link'))->toBeTrue();
});

it('lists the account databases, de-duplicating the plan username', function () {
    // Two sites on one plan share a hosting username; asking twice would list every
    // database twice.
    Http::fake([
        '*/api/hosting/v1/websites*' => Http::response([
            ['domain' => 'a.example.com', 'username' => 'u1'],
            ['domain' => 'b.example.com', 'username' => 'u1'],
        ]),
        '*/api/hosting/v1/accounts/u1/databases' => Http::response([
            ['name' => 'u1_beta', 'user' => 'u1_beta', 'domain' => 'b.example.com', 'host' => 'srv1.hstgr.io', 'port' => 3306, 'disk_usage_mb' => 4, 'max_size_mb' => 9216],
            ['name' => 'u1_alpha', 'user' => 'u1_alpha', 'domain' => 'a.example.com', 'host' => 'srv1.hstgr.io', 'port' => 3306, 'disk_usage_mb' => 1, 'max_size_mb' => 9216],
        ]),
    ]);

    $this->actingAs(adminUser())
        ->getJson(route('dev_settings.databases'))
        ->assertOk()
        ->assertJsonPath('configured', true)
        ->assertJsonCount(2, 'databases')
        // Sorted by name, and each row carries the username its phpMyAdmin link needs.
        ->assertJsonPath('databases.0.name', 'u1_alpha')
        ->assertJsonPath('databases.0.username', 'u1')
        ->assertJsonPath('databases.1.name', 'u1_beta');
});

it('says so rather than failing when there is no Hostinger token', function () {
    config(['services.hostinger.token' => '']);

    $this->actingAs(adminUser())
        ->getJson(route('dev_settings.databases'))
        ->assertOk()
        ->assertJsonPath('configured', false)
        ->assertJsonPath('databases', []);
});

it('fetches a phpMyAdmin sign-on link per request', function () {
    Http::fake([
        '*/databases/u1_alpha/phpmyadmin-link' => Http::response(['link' => 'https://auth-db1.hstgr.io/signon.php?sid=abc']),
    ]);

    devPost('dev_settings.phpmyadmin_link', ['username' => 'u1', 'name' => 'u1_alpha'])
        ->assertOk()
        ->assertJsonPath('link', 'https://auth-db1.hstgr.io/signon.php?sid=abc');
});

it('reports a database whose link the API will not issue', function () {
    Http::fake(['*/phpmyadmin-link' => Http::response(['link' => ''])]);

    devPost('dev_settings.phpmyadmin_link', ['username' => 'u1', 'name' => 'u1_alpha'])
        ->assertStatus(422)
        ->assertJsonPath('error', 'Hostinger returned no phpMyAdmin link for u1_alpha.');
});

it('requires both the account and the database name for a link', function () {
    devPost('dev_settings.phpmyadmin_link', [])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['username', 'name']);
});

it('does not let a guest fetch a phpMyAdmin link', function () {
    $this->withHeaders(['X-CSRF-TOKEN' => csrf_token()])
        ->post(route('dev_settings.phpmyadmin_link'), ['username' => 'u1', 'name' => 'u1_alpha'])
        ->assertRedirect(route('login'));
});

it('encodes the database name into the link path', function () {
    // The name is a path segment, so it is url-encoded rather than interpolated raw.
    Http::fake(['*' => Http::response(['link' => 'https://auth-db1.hstgr.io/signon.php?sid=x'])]);

    (new Hostinger(1, 0))->phpMyAdminLink('u1', 'weird name');

    Http::assertSent(fn ($request): bool => str_contains($request->url(), '/databases/weird%20name/phpmyadmin-link'));
});
