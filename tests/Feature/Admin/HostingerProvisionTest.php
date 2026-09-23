<?php

use App\Services\Hostinger;
use Illuminate\Support\Facades\Http;

/**
 * Provisioning creates real, billable infrastructure on a live Hostinger account and
 * then rewrites `.deploy.json` — the developer's actual deploy targets, SSH passwords
 * and all.
 *
 * So: every request is faked and strays are blocked, and **no test drives a successful
 * provision over HTTP**. The controller is exercised only up to the points where it
 * refuses (wrong role, bad input, no token); the work itself is asserted against
 * `Hostinger` directly, which touches nothing but the fake.
 */
beforeEach(function () {
    // Any request this file did not explicitly fake is a bug, not a live API call.
    Http::preventStrayRequests();
    config(['services.hostinger.token' => 'test-token']);
});

it('reports itself unconfigured without a token', function () {
    config(['services.hostinger.token' => null]);

    expect(Hostinger::configured())->toBeFalse();
});

it('refuses to call the API at all without a token', function () {
    config(['services.hostinger.token' => '']);

    expect(fn () => (new Hostinger(pollDelayMs: 0))->websites())
        ->toThrow(RuntimeException::class, 'No Hostinger API token');
});

it('lists the account websites with the username each one needs', function () {
    Http::fake([
        'developers.hostinger.com/api/hosting/v1/websites*' => Http::response(['data' => [
            [
                'domain' => 'example.com', 'username' => 'u983470049', 'is_enabled' => true,
                'parent_domain' => null, 'root_directory' => '/home/u983470049/domains/example.com/public_html',
                'order_id' => 12345,
            ],
            // No username: an account-scoped call could not be built for it.
            ['domain' => 'orphan.com', 'username' => null],
        ]]),
    ]);

    expect((new Hostinger(pollDelayMs: 0))->websites())->toBe([
        [
            'domain' => 'example.com', 'username' => 'u983470049', 'parent_domain' => null,
            'is_enabled' => true, 'root_directory' => '/home/u983470049/domains/example.com/public_html',
            'order_id' => 12345,
        ],
    ]);
});

it('reports the document root a host is actually served from', function () {
    // A subdomain lives under its PARENT's public_html, not ~/domains/{subdomain}. Deploy
    // derives the second path, which exists but serves nothing — hence reading the real one.
    Http::fake([
        '*/api/hosting/v1/websites*' => Http::response(['data' => [[
            'domain' => 'dev.example.com', 'username' => 'u1', 'parent_domain' => 'example.com',
            'root_directory' => '/home/u1/domains/example.com/public_html/dev', 'order_id' => 1,
        ]]]),
    ]);

    expect((new Hostinger(pollDelayMs: 0))->documentRoot('dev.example.com'))->toBe('/home/u1/domains/example.com/public_html/dev')
        ->and((new Hostinger(pollDelayMs: 0))->documentRoot('nope.example.com'))->toBeNull();
});

it('sends the bearer token', function () {
    Http::fake(['*' => Http::response(['data' => []])]);

    (new Hostinger(pollDelayMs: 0))->websites();

    Http::assertSent(fn ($request) => $request->hasHeader('Authorization', 'Bearer test-token'));
});

it('reads back the prefixed name and host the create call does not return', function () {
    // Hostinger answers the create with an empty body and prefixes the account
    // username onto whatever name and user were asked for.
    Http::fake([
        '*/databases' => Http::sequence()
            ->push([])
            ->push(['data' => [
                ['name' => 'u983470049_other', 'user' => 'u983470049_other', 'host' => 'srv1.hostinger.io', 'port' => 3306],
                ['name' => 'u983470049_dev', 'user' => 'u983470049_dev', 'host' => 'srv1.hostinger.io', 'port' => 3306],
            ]]),
    ]);

    expect((new Hostinger(pollDelayMs: 0))->createDatabase('u983470049', 'dev', 'dev', 'secret-pw', 'dev.example.com'))->toBe([
        // The loopback, not the `host` the API reported: that one is for remote clients.
        'DB_HOST' => '127.0.0.1',
        'DB_PORT' => '3306',
        'DB_DATABASE' => 'u983470049_dev',
        'DB_USERNAME' => 'u983470049_dev',
        // Never returned by the API — it is only ever the value that was sent.
        'DB_PASSWORD' => 'secret-pw',
    ]);
});

it('fails loudly when the created database is not in the list afterwards', function () {
    // One poll attempt: the create, then a single empty list.
    Http::fake([
        '*/databases' => Http::sequence()->push([])->push(['data' => []]),
    ]);

    expect(fn () => (new Hostinger(pollAttempts: 1, pollDelayMs: 0))->createDatabase('u983470049', 'dev', 'dev', 'pw', 'dev.example.com'))
        ->toThrow(RuntimeException::class, 'is not in the account');
});

it('surfaces the API message when a name is already taken', function () {
    Http::fake([
        '*/databases' => Http::response([
            'message' => 'The given data was invalid.',
            'errors' => ['name' => ['Database name is already taken.']],
        ], 422),
    ]);

    expect(fn () => (new Hostinger(pollDelayMs: 0))->createDatabase('u983470049', 'dev', 'dev', 'pw', 'dev.example.com'))
        ->toThrow(RuntimeException::class, 'Database name is already taken.');
});

it('lists the hosting plans a new website can be created under', function () {
    Http::fake([
        '*/orders*' => Http::response(['data' => [
            ['id' => 12345, 'plan' => ['name' => 'Premium Web Hosting'], 'status' => 'active'],
            ['plan' => ['name' => 'Orphan with no id']],
        ]]),
    ]);

    expect((new Hostinger(pollDelayMs: 0))->orders())->toBe([
        ['id' => 12345, 'plan' => 'Premium Web Hosting', 'status' => 'active'],
    ]);
});

it('lists a plan datacenters for the plan it was asked about', function () {
    Http::fake([
        '*/datacenters*' => Http::response(['data' => [['code' => 'us-east', 'title' => 'Boston']]]),
    ]);

    expect((new Hostinger(pollDelayMs: 0))->datacenters(12345))->toBe([['code' => 'us-east', 'title' => 'Boston']]);

    Http::assertSent(fn ($request) => str_contains($request->url(), 'order_id=12345'));
});

it('creates a website and reads back the username it landed under', function () {
    // The create answers with an empty body, so the hosting username — which every
    // account-scoped path needs — only exists in the website list afterwards.
    Http::fake([
        '*/api/hosting/v1/websites*' => Http::sequence()
            ->push([])
            ->push(['data' => [['domain' => 'app.example.com', 'username' => 'u983470049', 'is_enabled' => true]]]),
    ]);

    expect((new Hostinger(pollDelayMs: 0))->createWebsite('app.example.com', 12345, 'us-east'))->toBe('u983470049');

    Http::assertSent(fn ($request) => $request->method() !== 'POST' || (
        $request['domain'] === 'app.example.com'
        && $request['order_id'] === 12345
        && $request['datacenter_code'] === 'us-east'
    ));
});

it('omits the datacenter when none was chosen', function () {
    Http::fake([
        '*/api/hosting/v1/websites*' => Http::sequence()
            ->push([])
            ->push(['data' => [['domain' => 'app.example.com', 'username' => 'u1']]]),
    ]);

    (new Hostinger(pollDelayMs: 0))->createWebsite('app.example.com', 12345);

    // A plan that already has a website rejects the field rather than ignoring it.
    Http::assertSent(fn ($request) => $request->method() !== 'POST' || ! isset($request['datacenter_code']));
});

it('lists the registered domains, skipping unclaimed free ones', function () {
    Http::fake([
        '*/api/domains/v1/portfolio*' => Http::response([
            ['id' => 1, 'domain' => 'example.com', 'status' => 'active', 'expires_at' => '2027-01-01T00:00:00Z'],
            // `domain` is null until a free domain is claimed.
            ['id' => 2, 'domain' => null, 'status' => 'pending'],
        ]),
    ]);

    expect((new Hostinger(pollDelayMs: 0))->domains())->toBe([
        ['domain' => 'example.com', 'status' => 'active', 'expires_at' => '2027-01-01T00:00:00Z'],
    ]);
});

it('asks Hostinger to generate a free subdomain', function () {
    Http::fake(['*/free-subdomains' => Http::response(['domain' => 'abc123.hostingersite.com'])]);

    expect((new Hostinger(pollDelayMs: 0))->generateFreeSubdomain())->toBe('abc123.hostingersite.com');

    Http::assertSent(fn ($request) => $request->method() === 'POST'
        && $request->url() === 'https://developers.hostinger.com/api/hosting/v1/domains/free-subdomains');
});

it('accepts a free subdomain wrapped in a data envelope', function () {
    Http::fake(['*/free-subdomains' => Http::response(['data' => ['domain' => 'xyz.hostingersite.com']])]);

    expect((new Hostinger(pollDelayMs: 0))->generateFreeSubdomain())->toBe('xyz.hostingersite.com');
});

it('fails when no domain comes back for the free subdomain', function () {
    Http::fake(['*/free-subdomains' => Http::response([])]);

    expect(fn () => (new Hostinger(pollDelayMs: 0))->generateFreeSubdomain())
        ->toThrow(RuntimeException::class, 'returned no domain');
});

it('sends every field in snake_case, which is what the wire format is', function () {
    // The SDK documents camelCase PHP properties but serializes through
    // CamelCaseToSnakeCaseNameConverter. Sending `websiteDomain` is what produced
    // "The website domain field is required" against the live API.
    Http::fake([
        '*/databases' => Http::sequence()
            ->push([])
            ->push(['data' => [['name' => 'u1_starter', 'user' => 'u1_starter', 'host' => 'h', 'port' => 3306]]]),
    ]);

    (new Hostinger(pollDelayMs: 0))->createDatabase('u1', 'starter', 'starter', 'pw', 'example.com');

    Http::assertSent(fn ($request) => $request->method() !== 'POST' || (
        $request['website_domain'] === 'example.com' && ! isset($request['websiteDomain'])
    ));
});

it('generates a database password that satisfies every Hostinger rule', function () {
    // Str::password() guarantees one character per *group*, and both letter cases share one
    // group — so it can return a password with no uppercase, which the API rejects with
    // "Must contain at least one uppercase letter". Generated ones must never be able to.
    foreach (range(1, 200) as $ignored) {
        $password = Hostinger::databasePassword();

        expect(strlen($password))->toBeGreaterThanOrEqual(8)
            ->and($password)->toMatch('/[a-z]/')
            ->and($password)->toMatch('/[A-Z]/')
            ->and($password)->toMatch('/[0-9]/')
            // Alphanumeric only: it is written to .deploy.json and then into a remote .env.
            ->and($password)->toMatch('/^[A-Za-z0-9]+$/');
    }
});

it('never returns a password shorter than the minimum, whatever length is asked for', function () {
    expect(strlen(Hostinger::databasePassword(3)))->toBe(8);
});

it('waits for a new website to appear in the list instead of failing on the first read', function () {
    // The create lands before the list catches up: a single read reported a site that
    // plainly existed as missing, which aborted provisioning after creating real hosting.
    Http::fake([
        '*/api/hosting/v1/websites*' => Http::sequence()
            ->push([])
            ->push(['data' => []])
            ->push(['data' => []])
            ->push(['data' => [['domain' => 'late.example.com', 'username' => 'u1']]]),
    ]);

    expect((new Hostinger(pollDelayMs: 0))->createWebsite('late.example.com', 12345))->toBe('u1');
});

it('falls back to the plan username when the new site has not surfaced yet', function () {
    // The username belongs to the plan, not the site — so a lagging list must not stop the
    // database being created, which is the only thing the username is needed for.
    Http::fake([
        '*/api/hosting/v1/websites*' => Http::sequence()
            ->push([])
            ->whenEmpty(Http::response(['data' => [
                ['domain' => 'other.example.com', 'username' => 'u983470049', 'order_id' => 12345],
            ]])),
    ]);

    expect((new Hostinger(pollAttempts: 2, pollDelayMs: 0))->createWebsite('ghost.example.com', 12345))
        ->toBe('u983470049');
});

it('gives up only when the account has no site on that plan at all', function () {
    Http::fake([
        '*/api/hosting/v1/websites*' => Http::sequence()
            ->push([])
            ->whenEmpty(Http::response(['data' => [
                // A different plan: its username says nothing about where this site landed.
                ['domain' => 'elsewhere.example.com', 'username' => 'u111', 'order_id' => 99999],
            ]])),
    ]);

    expect(fn () => (new Hostinger(pollAttempts: 2, pollDelayMs: 0))->createWebsite('ghost.example.com', 12345))
        ->toThrow(RuntimeException::class, 'no hosting username could be found');
});

it('waits for a new database to appear too', function () {
    Http::fake([
        '*/databases' => Http::sequence()
            ->push([])
            ->push(['data' => []])
            ->push(['data' => [['name' => 'u1_late', 'user' => 'u1_late', 'host' => 'h', 'port' => 3306]]]),
    ]);

    expect((new Hostinger(pollDelayMs: 0))->createDatabase('u1', 'late', 'late', 'pw', 'example.com'))
        ->toMatchArray(['DB_DATABASE' => 'u1_late']);
});

it('never uses the remote MySQL hostname as DB_HOST', function () {
    // `host` is hPanel's Remote MySQL value — reachable only from an allow-listed IP. The
    // deployed app runs on the server itself, so the .env it gets must say 127.0.0.1.
    Http::fake([
        '*/databases' => Http::sequence()
            ->push([])
            ->push(['data' => [['name' => 'u1_x', 'user' => 'u1_x', 'host' => 'srv1568.hstgr.io', 'port' => 3306]]]),
    ]);

    expect((new Hostinger(pollDelayMs: 0))->createDatabase('u1', 'x', 'x', 'pw', 'example.com'))
        ->toMatchArray(['DB_HOST' => '127.0.0.1']);
});

it('adds a scheduler cron job', function () {
    Http::fake([
        '*/cron-jobs' => Http::sequence()
            ->push(['data' => []])
            ->push([]),
    ]);

    expect((new Hostinger(pollDelayMs: 0))->createCronJob('u1', '* * * * *', '/usr/bin/php /home/u1/x/backend/artisan schedule:run'))
        ->toBeTrue();

    Http::assertSent(fn ($request) => $request->method() !== 'POST' || (
        $request['time'] === '* * * * *'
        && str_ends_with($request['command'], 'artisan schedule:run')
    ));
});

it('does not schedule the same command twice', function () {
    // The cron list belongs to the account, shared by every site on the plan — provisioning
    // a flavor again would otherwise leave two schedulers racing on one install.
    $command = '/usr/bin/php /home/u1/x/backend/artisan schedule:run';

    Http::fake([
        '*/cron-jobs' => Http::response(['data' => [
            ['uid' => 'abc', 'time' => '* * * * *', 'command' => $command],
        ]]),
    ]);

    expect((new Hostinger(pollDelayMs: 0))->createCronJob('u1', '* * * * *', $command))->toBeFalse();

    Http::assertNotSent(fn ($request) => $request->method() === 'POST');
});
