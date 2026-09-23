<?php

namespace App\Services;

use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * The slice of the Hostinger API the deploy panel provisions a flavor with:
 * list the websites on the account, create a subdomain, create a database.
 *
 * Deliberately hand-rolled over `Http` rather than pulling in hostinger/api-php-sdk —
 * three endpoints do not justify a dependency, and the SDK ships every product group
 * (ecommerce, billing, VPS, DNS) for the two this panel touches.
 *
 * **Every field on the wire is snake_case, in both directions.** The published SDK documents
 * its PHP properties in camelCase (`websiteDomain`, `isEnabled`) but serializes through
 * Symfony's CamelCaseToSnakeCaseNameConverter, so the JSON the API actually sends and accepts
 * is `website_domain`, `is_enabled`. Sending camelCase gets "The website domain field is
 * required"; reading camelCase silently yields nulls.
 *
 * Both create endpoints answer with an empty success body, so nothing about what was
 * actually made comes back from them. `createDatabase()` therefore creates and then
 * re-lists: Hostinger prefixes the account username onto the name and user you ask for
 * (`dev` becomes `u983470049_dev`), and the connection host is not derivable at all.
 */
class Hostinger
{
    private const BASE = 'https://developers.hostinger.com';

    /**
     * How long to keep re-reading a list after a create, and how long to wait between
     * tries. Both create endpoints answer with an empty body, so the only way to learn
     * what was made is to list it back — and the list is a moment behind the write.
     *
     * @param  int  $pollDelayMs  0 in tests, where the fake answers instantly.
     */
    public function __construct(
        private readonly int $pollAttempts = 6,
        private readonly int $pollDelayMs = 1500,
    ) {}

    /**
     * Calls $read until it returns something other than null, then returns it.
     *
     * @template T
     *
     * @param  callable(): ?T  $read
     * @return ?T
     */
    private function poll(callable $read): mixed
    {
        for ($attempt = 1; $attempt <= $this->pollAttempts; $attempt++) {
            $found = $read();

            if ($found !== null) {
                return $found;
            }

            if ($attempt < $this->pollAttempts && $this->pollDelayMs > 0) {
                usleep($this->pollDelayMs * 1000);
            }
        }

        return null;
    }

    public static function configured(): bool
    {
        return filled(config('services.hostinger.token'));
    }

    /**
     * Every website on the account, each already carrying the hosting username the
     * account-scoped endpoints need as a path segment.
     *
     * @return array<int, array{domain: string, username: string, parent_domain: ?string, is_enabled: bool}>
     */
    public function websites(): array
    {
        $rows = $this->request()->get(self::BASE.'/api/hosting/v1/websites', ['per_page' => 100]);

        return collect($this->data($rows, 'list websites'))
            ->filter(fn (array $site): bool => filled($site['domain'] ?? null) && filled($site['username'] ?? null))
            ->map(fn (array $site): array => [
                'domain' => $site['domain'],
                'username' => $site['username'],
                'parent_domain' => $site['parent_domain'] ?? null,
                'is_enabled' => (bool) ($site['is_enabled'] ?? true),
                // The vhost's real document root. For a subdomain this is NOT
                // ~/domains/{subdomain}/public_html — Hostinger puts it under the parent
                // as {parent}/public_html/{directory}, and deploying to the guessed path
                // writes to a directory nothing serves.
                'root_directory' => $site['root_directory'] ?? null,
                'order_id' => isset($site['order_id']) ? (int) $site['order_id'] : null,
            ])
            ->values()
            ->all();
    }

    /**
     * The hosting plans on the account. Creating a website has to name the order it
     * belongs to, and the plan name is the only thing that tells two of them apart.
     *
     * @return array<int, array{id: int, plan: string, status: string}>
     */
    public function orders(): array
    {
        $response = $this->request()->get(self::BASE.'/api/hosting/v1/orders', ['per_page' => 100]);

        return collect($this->data($response, 'list orders'))
            ->filter(fn (array $order): bool => filled($order['id'] ?? null))
            ->map(fn (array $order): array => [
                'id' => (int) $order['id'],
                'plan' => (string) ($order['plan']['name'] ?? 'Hosting'),
                'status' => (string) ($order['status'] ?? ''),
            ])
            ->values()
            ->all();
    }

    /**
     * Domains registered in the account's portfolio, for picking one to host rather than
     * typing it. A domain registered elsewhere but pointed at Hostinger will not be here,
     * so this is a convenience list and never a validation whitelist.
     *
     * Lives under a different API group (`/api/domains/v1`) than everything else here.
     *
     * @return array<int, array{domain: string, status: string, expires_at: ?string}>
     */
    public function domains(): array
    {
        $response = $this->request()->get(self::BASE.'/api/domains/v1/portfolio');

        return collect($this->data($response, 'list domains'))
            // `domain` is null for an unclaimed free domain.
            ->filter(fn (array $row): bool => filled($row['domain'] ?? null))
            ->map(fn (array $row): array => [
                'domain' => (string) $row['domain'],
                'status' => (string) ($row['status'] ?? ''),
                'expires_at' => $row['expires_at'] ?? null,
            ])
            ->values()
            ->all();
    }

    /**
     * Datacenters a given plan may place a website in. Only needed for the first website
     * on a new plan; afterwards the plan already has one and the field is ignored.
     *
     * @return array<int, array{code: string, title: string}>
     */
    public function datacenters(int $orderId): array
    {
        $response = $this->request()->get(self::BASE.'/api/hosting/v1/datacenters', ['order_id' => $orderId]);

        return collect($this->data($response, 'list datacenters'))
            ->filter(fn (array $row): bool => filled($row['code'] ?? null))
            ->map(fn (array $row): array => [
                'code' => (string) $row['code'],
                'title' => (string) ($row['title'] ?? $row['code']),
            ])
            ->values()
            ->all();
    }

    /**
     * Asks Hostinger for one of its own free subdomains (`something.hostingersite.com`)
     * and returns it. Takes no input — the name is generated — so calling this twice
     * produces two different hosts.
     *
     * For a throwaway target: it works immediately with no DNS to point, which a real
     * domain always needs first.
     */
    public function generateFreeSubdomain(): string
    {
        $response = $this->request()->post(self::BASE.'/api/hosting/v1/domains/free-subdomains');

        $this->assertOk($response, 'generate a free subdomain');

        $domain = $response->json('domain') ?? $response->json('data.domain');

        if (! filled($domain)) {
            throw new RuntimeException('Hostinger returned no domain for the free subdomain.');
        }

        return (string) $domain;
    }

    /**
     * Creates a website for a domain or subdomain that is not on the account yet, and
     * returns the hosting username it landed under — which the database endpoints need
     * as a path segment and which the create call does not return.
     *
     * The domain must already resolve to Hostinger (registered or pointed there); this
     * creates the hosting entry for it, not the domain itself.
     */
    public function createWebsite(string $domain, int $orderId, ?string $datacenterCode = null): string
    {
        $payload = ['domain' => $domain, 'order_id' => $orderId];

        if (filled($datacenterCode)) {
            $payload['datacenter_code'] = $datacenterCode;
        }

        $response = $this->request()->post(self::BASE.'/api/hosting/v1/websites', $payload);

        $this->assertOk($response, "create website {$domain}");

        // The list lags the write, so this is polled rather than read once.
        $sites = $this->poll(function () use ($domain): ?array {
            $sites = $this->websites();

            return collect($sites)->contains(fn (array $site): bool => $site['domain'] === $domain) ? $sites : null;
        }) ?? $this->websites();

        $username = collect($sites)->first(fn (array $site): bool => $site['domain'] === $domain)['username'] ?? null;

        // The hosting username belongs to the plan, not to the site: every website on an
        // order shares it. So a site that has not surfaced yet does not have to block the
        // database — which is the only reason this lookup exists. Scoped to the same order
        // in case the account holds more than one plan.
        $username ??= collect($sites)
            ->first(fn (array $site): bool => $site['order_id'] === $orderId)['username'] ?? null;

        if ($username === null) {
            throw new RuntimeException("Hostinger created {$domain}, but no hosting username could be found for plan {$orderId}. Re-run this with \"Existing website\" once {$domain} appears.");
        }

        return $username;
    }

    /**
     * Creates a database and its user, then reads back what Hostinger actually named
     * them. The password is not returned by the API, so it is passed back out as given.
     *
     * The returned block is what a flavor's `.env` needs, so `DB_HOST` is the loopback the
     * app on the server uses — not the remote hostname the API reports.
     *
     * @return array{DB_HOST: string, DB_PORT: string, DB_DATABASE: string, DB_USERNAME: string, DB_PASSWORD: string}
     */
    public function createDatabase(string $username, string $name, string $user, string $password, string $websiteDomain): array
    {
        $response = $this->request()->post(
            self::BASE."/api/hosting/v1/accounts/{$username}/databases",
            ['name' => $name, 'user' => $user, 'password' => $password, 'website_domain' => $websiteDomain],
        );

        $this->assertOk($response, "create database {$name}");

        // Polled for the same reason as the website list: the write lands before the read
        // catches up. Hostinger prefixes the account username, so the row is matched on
        // the suffix rather than on equality with what was asked for.
        $created = $this->poll(fn (): ?array => collect($this->databases($username))
            ->first(fn (array $row): bool => ($row['name'] ?? '') === $name || str_ends_with($row['name'] ?? '', "_{$name}")));

        if ($created === null) {
            throw new RuntimeException("Hostinger reported the database was created, but {$name} is not in the account's database list.");
        }

        return [
            // NOT $created['host']. The API returns the *remote* MySQL hostname
            // (`srv1568.hstgr.io`, hPanel's Remote MySQL value), which only works for
            // connections from outside and only once that IP is allow-listed. The
            // deployed app runs on the same box, so it connects over the loopback —
            // which is also what .env.production ships with.
            'DB_HOST' => '127.0.0.1',
            'DB_PORT' => (string) ($created['port'] ?? 3306),
            'DB_DATABASE' => (string) $created['name'],
            'DB_USERNAME' => (string) ($created['user'] ?? $user),
            'DB_PASSWORD' => $password,
        ];
    }

    /**
     * The account's cron jobs.
     *
     * @return array<int, array{uid: string, time: string, command: string}>
     */
    public function cronJobs(string $username): array
    {
        $response = $this->request()->get(self::BASE."/api/hosting/v1/accounts/{$username}/cron-jobs");

        return collect($this->data($response, 'list cron jobs'))
            ->filter(fn (array $row): bool => filled($row['command'] ?? null))
            ->map(fn (array $row): array => [
                'uid' => (string) ($row['uid'] ?? ''),
                'time' => (string) ($row['time'] ?? ''),
                'command' => (string) $row['command'],
            ])
            ->values()
            ->all();
    }

    /**
     * Adds a cron job, unless the exact command is already scheduled.
     *
     * Matching on the command rather than creating blindly: the account's cron list is
     * shared by every site on the plan, and provisioning a flavor twice would otherwise
     * leave two identical schedulers running against the same install.
     *
     * @return bool whether a job was created (false when one already existed)
     */
    public function createCronJob(string $username, string $time, string $command): bool
    {
        $existing = collect($this->cronJobs($username))
            ->contains(fn (array $row): bool => $row['command'] === $command);

        if ($existing) {
            return false;
        }

        $response = $this->request()->post(
            self::BASE."/api/hosting/v1/accounts/{$username}/cron-jobs",
            ['time' => $time, 'command' => $command],
        );

        $this->assertOk($response, 'create the cron job');

        return true;
    }

    /**
     * A password Hostinger's database rules accept by construction: at least 8 characters,
     * with at least one lowercase letter, one uppercase letter and one number.
     *
     * Not `Str::password()`: that guarantees one character from each *group* it is given,
     * and letters are a single group holding both cases — so it can return a password with
     * no uppercase at all, which the API rejects. Rare enough to look like an intermittent
     * failure rather than a bug.
     *
     * Alphanumeric only. The result is written to `.deploy.json` and then into a generated
     * `.env` on the target, where a symbol would have to survive two layers of quoting.
     */
    public static function databasePassword(int $length = 24): string
    {
        $lower = 'abcdefghijklmnopqrstuvwxyz';
        $upper = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ';
        $digits = '0123456789';
        $all = $lower.$upper.$digits;

        $pick = fn (string $pool): string => $pool[random_int(0, strlen($pool) - 1)];

        // One of each required class first, then fill, then shuffle so the classes are not
        // always in the same positions.
        $chars = [$pick($lower), $pick($upper), $pick($digits)];

        for ($i = count($chars); $i < max(8, $length); $i++) {
            $chars[] = $pick($all);
        }

        // Fisher-Yates over random_int rather than str_shuffle(), which uses the seeded
        // Mt19937 generator and is not suitable for anything secret.
        for ($i = count($chars) - 1; $i > 0; $i--) {
            $j = random_int(0, $i);
            [$chars[$i], $chars[$j]] = [$chars[$j], $chars[$i]];
        }

        return implode('', $chars);
    }

    /**
     * The document root Hostinger actually serves a host from, or null if it does not
     * know the host yet. Deploy must use this rather than deriving a path from the
     * domain: a subdomain's root lives under its *parent's* public_html.
     */
    public function documentRoot(string $domain): ?string
    {
        $site = collect($this->websites())->first(fn (array $row): bool => $row['domain'] === $domain);

        return $site['root_directory'] ?? null;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function databases(string $username): array
    {
        $response = $this->request()->get(self::BASE."/api/hosting/v1/accounts/{$username}/databases");

        return $this->data($response, 'list databases');
    }

    /**
     * A single sign-on URL into phpMyAdmin for one database.
     *
     * The URL carries its own session id (`…/signon.php?sid=…`), so it *is* a
     * credential for that database: anyone holding it is signed in. It is therefore
     * fetched on demand and handed straight to the browser — never written to
     * `.deploy.json`, never logged, and never cached. Hostinger expires it on its own
     * schedule, so a stale one is refetched rather than stored.
     *
     * `$name` must be the full prefixed name the list endpoint returns
     * (`u983470049_starter`), not the suffix that was asked for at create time.
     */
    public function phpMyAdminLink(string $username, string $name): string
    {
        $response = $this->request()->get(
            self::BASE."/api/hosting/v1/accounts/{$username}/databases/".rawurlencode($name).'/phpmyadmin-link'
        );

        $this->assertOk($response, "get phpMyAdmin link for {$name}");

        $link = (string) ($response->json('link') ?? '');

        if ($link === '') {
            throw new RuntimeException("Hostinger returned no phpMyAdmin link for {$name}.");
        }

        return $link;
    }

    private function request(): PendingRequest
    {
        $token = (string) config('services.hostinger.token');

        if ($token === '') {
            throw new RuntimeException('No Hostinger API token. Add HOSTINGER_API_TOKEN in Developer Settings → Deployment.');
        }

        return Http::withToken($token)->acceptJson()->timeout(30);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function data(Response $response, string $what): array
    {
        $this->assertOk($response, $what);

        $body = $response->json();

        // Endpoints answer either `{data: [...]}` or a bare array, depending on whether
        // the collection is paginated.
        return is_array($body['data'] ?? null) ? $body['data'] : (is_array($body) ? $body : []);
    }

    private function assertOk(Response $response, string $what): void
    {
        if ($response->successful()) {
            return;
        }

        // The API puts the useful part in `message`; `errors` carries per-field detail
        // such as a name already taken.
        $message = $response->json('message') ?: $response->body();
        $errors = collect((array) $response->json('errors'))
            ->flatten()
            ->implode(' ');

        throw new RuntimeException(trim("Hostinger could not {$what}: {$message} {$errors}"));
    }
}
