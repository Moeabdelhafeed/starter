<?php

namespace App\Providers;

use App\Helpers\Trans;
use App\Http\Inertia\ResponseFactory;
use App\Services\Ai\AgentDriver;
use App\Services\Ai\OllamaDriver;
use Carbon\CarbonImmutable;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;
use Inertia\ResponseFactory as BaseResponseFactory;
use InvalidArgumentException;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        /*
         * `Inertia::scroll()` returns our ScrollProp, which merges only for the
         * InfiniteScroll component's own page fetch. The package's prop appends on
         * every other request too, so the redirect after a write told the client to
         * stack the restored pages onto the pages it already had. See
         * App\Http\Inertia\ScrollProp — a singleton over the package's own, so no
         * controller has to know and no new list can opt out by forgetting.
         */
        $this->app->singleton(BaseResponseFactory::class, ResponseFactory::class);

        /*
         * The AI assistant's model backend, chosen by config('ai.driver').
         *
         * Bound by interface so the agent, its tools and its permission gating
         * never name a provider — adding a hosted one later is a new class and
         * a new case here, not a change to anything else.
         */
        $this->app->bind(AgentDriver::class, fn (): AgentDriver => match ((string) config('ai.driver')) {
            'ollama' => new OllamaDriver,
            default => throw new InvalidArgumentException('Unknown AI driver ['.config('ai.driver').'].'),
        });
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->neutralizeTestingModeInProduction();
        $this->configureDefaults();
        $this->configureAuthorization();
        $this->configureRateLimiting();
        $this->configureScrollPagination();
    }

    /**
     * `->scrollPaginate($perPage)` — `paginate()` for a list rendered through
     * `Inertia::scroll()` + `<InfiniteScroll>`.
     *
     * A create/update/delete redirects back to the list, and a plain `paginate()` answers
     * that full response with page 1 only, while the client still remembers it had loaded
     * up to page N — its next fetch appended page N+1 after page 1 and the admin lost the
     * rows in between (and the one they had just edited). `resources/js/app.ts` sends the
     * reached page of every scroll prop on each non-GET visit (`X-Inertia-Scroll-Restore`,
     * keyed by page name; the header survives the redirect like Inertia's own do), and this
     * answers with pages 1..N as a paginator sitting on page N — so the metadata the client
     * syncs to (`currentPage` N, `nextPage` N+1) matches the rows it is looking at.
     *
     * Partial reloads (the infinite scroll's own page fetches) never carry the header, so
     * they keep paging normally. Capped so a forged header can't ask for the whole table.
     */
    protected function configureScrollPagination(): void
    {
        Builder::macro('scrollPaginate', function (int $perPage, string $pageName = 'page'): LengthAwarePaginator {
            /** @var Builder $this */
            $restore = json_decode((string) request()->header('X-Inertia-Scroll-Restore'), true);
            $pages = min((int) ($restore[$pageName] ?? 1), 50);

            if ($pages <= 1 || request()->hasHeader('X-Inertia-Partial-Data')) {
                return $this->paginate($perPage, ['*'], $pageName)->withQueryString();
            }

            $total = $this->toBase()->getCountForPagination();
            $items = $this->forPage(1, $perPage * $pages)->get();

            return (new LengthAwarePaginator($items, $total, $perPage, $pages, [
                'path' => Paginator::resolveCurrentPath(),
                'pageName' => $pageName,
            ]))->withQueryString();
        });
    }

    /**
     * `IS_TESTING=true` disables every rate limiter, echoes OTP codes back in API
     * responses and puts the failing SQL in 5xx bodies. All three are fine on a
     * staging box and catastrophic on a live one, and the flag has shipped set to
     * true in a production env file before — so production wins over the flag,
     * loudly, instead of trusting whatever was deployed.
     */
    protected function neutralizeTestingModeInProduction(): void
    {
        if (app()->isProduction() && config('app.is_testing')) {
            config(['app.is_testing' => false]);

            Log::warning('IS_TESTING=true was ignored: the flag is disabled in production. Remove it from the production environment file.');
        }
    }

    /**
     * The super admin passes every permission check by role, not by grant.
     *
     * Without this, a permission added after the role was seeded is one the
     * super admin does not hold — so shipping a new feature would lock the
     * owner out of it until someone remembered to re-run the seeder on the
     * live database. Returning null (rather than false) for everyone else
     * leaves the normal Spatie checks to decide.
     */
    protected function configureAuthorization(): void
    {
        Gate::before(fn ($user, string $ability): ?bool => $user->hasRole('super_admin') ? true : null);
    }

    protected function configureDefaults(): void
    {
        Date::use(CarbonImmutable::class);

        // Behind a TLS-terminating proxy the app sees plain HTTP, so generated
        // URLs (and the redirects built from them) would downgrade the scheme.
        if (app()->isProduction()) {
            URL::forceScheme('https');
        }

        // Block migrate:fresh / migrate:refresh / db:wipe in production so they
        // can't be run by accident. The deploy panel sets ALLOW_DESTRUCTIVE_MIGRATIONS=true
        // inline (only on the destructive deploy option) to intentionally bypass this.
        DB::prohibitDestructiveCommands(
            app()->isProduction() && ! config('features.allow_destructive_migrations'),
        );

        Password::defaults(fn (): ?Password => app()->isProduction()
            ? Password::min(12)
                ->mixedCase()
                ->letters()
                ->numbers()
                ->symbols()
                ->uncompromised()
            : null
        );
    }

    /**
     * Whether this install is a testing/seeding environment (`IS_TESTING=true`).
     *
     * The same flag opens `POST /api/media` and `POST /api/translations`, so anything it
     * unlocks is already a non-production concern — rate limiting a client that is only
     * provisioning content serves nobody.
     */
    private static function isTesting(): bool
    {
        return config('app.is_testing');
    }

    /**
     * Configure the rate limiters for the application.
     */
    protected function configureRateLimiting(): void
    {
        // Default API rate limit (general endpoints)
        RateLimiter::for('api', function (Request $request) {
            // A testing install is one a client app is seeding against: it uploads every
            // media key and posts every translation key once per locale, hundreds of calls
            // in a burst, and a 429 halfway through leaves the CMS half-populated. The same
            // flag already opens the write endpoints those calls use.
            if (self::isTesting()) {
                return Limit::none();
            }

            [$limit, $decayMinutes] = config('auth.rate_limits.api');

            return Limit::perMinutes($decayMinutes, $limit)
                ->by($request->user()?->id ?: $request->ip())
                ->response(function (Request $request, array $headers) {
                    return response()->json([
                        'success' => false,
                        'message' => Trans::get('api.too_many_requests'),
                        'errors' => null,
                        'data' => null,
                    ], 429, $headers);
                });
        });

        // Stricter rate limit for authentication endpoints
        RateLimiter::for('auth', function (Request $request) {
            if (self::isTesting()) {
                return Limit::none();
            }

            [$limit, $decayMinutes] = config('auth.rate_limits.auth');

            // App-store reviewers get headroom, not a blank cheque: their address is
            // published in the store listing, so removing the limit entirely turns a
            // known email into an unlimited password oracle.
            if ($this->isReviewerIdentifier($request->input('identifier'))) {
                $limit = max($limit, 30);
            }

            $respond = fn (Request $request, array $headers) => response()->json([
                'success' => false,
                'message' => Trans::get('api.too_many_login_attempts'),
                'errors' => null,
                'data' => null,
            ], 429, $headers);

            // Two limits: one per account so a single identifier can't be sprayed,
            // one per IP so an attacker can't walk the user table from one host.
            // Keying the per-account limit by IP too keeps a stranger from locking
            // a victim out of their own account with five junk attempts.
            return [
                Limit::perMinutes($decayMinutes, $limit)
                    ->by(self::identifierKey($request).'|'.$request->ip())
                    ->response($respond),
                Limit::perMinutes($decayMinutes, $limit * 4)
                    ->by('ip|'.$request->ip())
                    ->response($respond),
            ];
        });

        // Very strict rate limit for OTP/password reset
        RateLimiter::for('otp', function (Request $request) {
            if (self::isTesting()) {
                return Limit::none();
            }

            [$limit, $decayMinutes] = config('auth.rate_limits.otp');

            if ($this->isReviewerIdentifier($request->input('identifier'))) {
                $limit = max($limit, 20);
            }

            $respond = fn (Request $request, array $headers) => response()->json([
                'success' => false,
                'message' => Trans::get('api.too_many_otp_requests'),
                'errors' => null,
                'data' => null,
            ], 429, $headers);

            return [
                Limit::perMinutes($decayMinutes, $limit)
                    ->by(self::identifierKey($request).'|'.$request->ip())
                    ->response($respond),
                Limit::perMinutes($decayMinutes, $limit * 4)
                    ->by('ip|'.$request->ip())
                    ->response($respond),
            ];
        });
    }

    /**
     * Normalized identifier used as part of a rate-limit key. Empty for requests
     * that carry no identifier at all, which then fall back to the per-IP limit.
     */
    private static function identifierKey(Request $request): string
    {
        return strtolower(trim((string) $request->input('identifier')));
    }

    private function isReviewerIdentifier(?string $identifier): bool
    {
        if (! $identifier) {
            return false;
        }

        return in_array(strtolower(trim($identifier)), config('auth.reviewer_emails', []), true);
    }
}
