<?php

namespace App\Providers;

use App\Helpers\Trans;
use Carbon\CarbonImmutable;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->configureDefaults();
        $this->configureRateLimiting();
    }

    protected function configureDefaults(): void
    {
        Date::use(CarbonImmutable::class);

        DB::prohibitDestructiveCommands(
            app()->isProduction(),
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
     * Configure the rate limiters for the application.
     */
    protected function configureRateLimiting(): void
    {
        // Default API rate limit (general endpoints)
        RateLimiter::for('api', function (Request $request) {
            $limit = (int) env('RATE_LIMIT_API', 60);
            $decayMinutes = (int) env('RATE_LIMIT_API_DECAY', 1);

            return Limit::perMinutes($decayMinutes, $limit)
                ->by($request->user()?->id ?: $request->ip())
                ->response(function (Request $request, array $headers) {
                    return response()->json([
                        'success' => false,
                        'message' => Trans::get('api.too_many_requests'),
                    ], 429, $headers);
                });
        });

        // Stricter rate limit for authentication endpoints
        RateLimiter::for('auth', function (Request $request) {
            if ($this->isReviewerIdentifier($request->input('identifier'))) {
                return Limit::none();
            }

            $limit = (int) env('RATE_LIMIT_AUTH', 5);
            $decayMinutes = (int) env('RATE_LIMIT_AUTH_DECAY', 1);

            return Limit::perMinutes($decayMinutes, $limit)
                ->by($request->input('identifier') ?: $request->ip())
                ->response(function (Request $request, array $headers) {
                    return response()->json([
                        'success' => false,
                        'message' => Trans::get('api.too_many_login_attempts'),
                    ], 429, $headers);
                });
        });

        // Very strict rate limit for OTP/password reset
        RateLimiter::for('otp', function (Request $request) {
            if ($this->isReviewerIdentifier($request->input('identifier'))) {
                return Limit::none();
            }

            $limit = (int) env('RATE_LIMIT_OTP', 3);
            $decayMinutes = (int) env('RATE_LIMIT_OTP_DECAY', 5);

            return Limit::perMinutes($decayMinutes, $limit)
                ->by($request->input('identifier') ?: $request->ip())
                ->response(function (Request $request, array $headers) {
                    return response()->json([
                        'success' => false,
                        'message' => Trans::get('api.too_many_otp_requests'),
                    ], 429, $headers);
                });
        });
    }

    private function isReviewerIdentifier(?string $identifier): bool
    {
        if (! $identifier) {
            return false;
        }
        $normalized = strtolower(trim($identifier));
        $reviewers = array_filter([
            strtolower(trim((string) env('APPLE_REVIEWER_EMAIL'))),
            strtolower(trim((string) env('GOOGLE_REVIEWER_EMAIL'))),
        ]);

        return in_array($normalized, $reviewers, true);
    }
}
