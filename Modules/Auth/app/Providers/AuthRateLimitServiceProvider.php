<?php

namespace Modules\Auth\App\Providers;

use App\Support\ApiResponse;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

class AuthRateLimitServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        RateLimiter::for('verification-resend', function ($request) {
            $key = $request->user()?->id ? 'user:'.$request->user()->id : 'ip:'.$request->ip();

            return Limit::perMinutes(15, 1)
                ->by($key)
                ->response(function ($request, $headers) {
                    $retryAfter = $headers['Retry-After'] ?? null;
                    $message = $retryAfter
                        ? "Too many attempts. Please try again in {$retryAfter} seconds."
                        : 'Too many attempts. Please try again later.';

                    return ApiResponse::error(
                        message: $message,
                        status: 429,
                        errorCode: 'TOO_MANY_REQUESTS',
                        meta: array_filter(['retry_after' => $retryAfter ? (int) $retryAfter : null])
                    )->withHeaders($headers);
                });
        });

        RateLimiter::for('password-reset', function ($request) {
            $key = $request->input('email') ? 'email:'.$request->input('email') : 'ip:'.$request->ip();

            return Limit::perMinute(1)
                ->by($key)
                ->response(function ($request, $headers) {
                    $retryAfter = $headers['Retry-After'] ?? null;
                    $message = $retryAfter
                        ? "Too many password reset requests. Please try again in {$retryAfter} seconds."
                        : 'Too many attempts. Please try again later.';

                    return ApiResponse::error(
                        message: $message,
                        status: 429,
                        errorCode: 'TOO_MANY_REQUESTS',
                        meta: array_filter(['retry_after' => $retryAfter ? (int) $retryAfter : null])
                    )->withHeaders($headers);
                });
        });
    }

    /**
     * Register the service provider.
     */
    public function register(): void {}

    /**
     * Get the services provided by the provider.
     */
    public function provides(): array
    {
        return [];
    }
}
