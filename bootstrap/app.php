<?php

use App\Support\ApiResponse;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Auth\Middleware\Authenticate;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Modules\Admin\app\Http\Middleware\EnsureUserIsAdmin;
use Modules\Auth\app\Exceptions\AuthException;
use Modules\Auth\app\Http\Middleware\EnsureEmailIsVerifiedMiddleware;
use Modules\Events\app\Exceptions\EventException;
use Modules\Organizer\app\Exceptions\OrganizerException;
use Modules\Organizer\app\Http\Middleware\EnsureOrganizerRole;
use Modules\Shared\app\Exceptions\ImageUploadException;
use Modules\Shared\app\Http\Middleware\EnsureFeatureActive;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\MethodNotAllowedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\Exception\TooManyRequestsHttpException;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->api(prepend: [
            'throttle:api',
        ]);

        $middleware->alias([
            'auth:sanctum' => Authenticate::class.':sanctum',
        ]);

        $middleware->alias([
            'verified' => EnsureEmailIsVerifiedMiddleware::class,
            'organizer.role' => EnsureOrganizerRole::class,
            'admin.auth' => EnsureUserIsAdmin::class,
            'feature' => EnsureFeatureActive::class,
            'auth.feature' => EnsureFeatureActive::class,
        ]);

        // API-only app: Never redirect unauthenticated requests to a login page
        $middleware->redirectGuestsTo(fn () => null);

        // Trust all upstream proxies so X-Forwarded-For headers (from Nginx, AWS ALB, Cloudflare, load test tools) are respected
        $middleware->trustProxies(at: '*');
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        // API-only app: Always render exceptions as JSON
        $exceptions->shouldRenderJsonWhen(fn () => true);

        $exceptions->dontReport([
            AuthException::class,
        ]);

        $exceptions->render(function (AuthException $e, Request $request) {
            return ApiResponse::error(
                message: $e->getMessage(),
                status: $e->status(),
                errorCode: $e->errorCode(),
                meta: $e->meta(),
            );
        });

        $exceptions->render(function (OrganizerException $e, Request $request) {
            return ApiResponse::error(
                message: $e->getMessage(),
                status: $e->status(),
                errorCode: $e->errorCode(),
                meta: $e->meta(),
            );
        });

        $exceptions->render(function (EventException $e, Request $request) {
            return ApiResponse::error(
                message: $e->getMessage(),
                status: $e->status(),
                errorCode: $e->errorCode(),
                meta: $e->meta(),
            );
        });

        $exceptions->render(function (ImageUploadException $e, Request $request) {
            return ApiResponse::error(
                message: $e->getMessage(),
                status: $e->status(),
                errorCode: $e->errorCode(),
                meta: $e->meta(),
            );
        });

        // Standardize all API errors
        $exceptions->render(function (Throwable $e, Request $request) {
            if ($e instanceof HttpResponseException) {
                return $e->getResponse();
            }

            if ($e instanceof NotFoundHttpException) {
                return ApiResponse::error('Resource not found.', 404, 'RESOURCE_NOT_FOUND');
            }

            if ($e instanceof AuthenticationException) {
                return ApiResponse::error('Unauthenticated.', 401, 'UNAUTHENTICATED');
            }

            if ($e instanceof AccessDeniedHttpException) {
                return ApiResponse::error('Unauthorized action.', 403, 'UNAUTHORIZED');
            }

            if ($e instanceof MethodNotAllowedHttpException) {
                return ApiResponse::error('The '.$request->method().' method is not supported for this route.', 405, 'METHOD_NOT_ALLOWED');
            }

            if ($e instanceof TooManyRequestsHttpException) {
                return ApiResponse::error('Too many requests. Please try again later.', 429, 'TOO_MANY_REQUESTS');
            }

            if ($e instanceof ValidationException) {
                return ApiResponse::error('Validation failed.', 422, 'VALIDATION_FAILED', $e->errors());
            }

            // Generic Server Error Fallback
            return ApiResponse::error(
                'An unexpected error occurred.',
                500,
                'INTERNAL_SERVER_ERROR',
                [],
                config('app.debug', false) ? [
                    'debug_info' => $e->getMessage() ?: get_class($e),
                    'exception' => get_class($e),
                    'file' => $e->getFile().':'.$e->getLine(),
                ] : []
            );
        });
    })
    ->booting(function () {
        // Strictly protect the dev/local database from being targeted when testing
        if (app()->environment('testing')) {
            config([
                'database.default' => 'mysql_testing',
                'database.connections.mysql_testing' => [
                    'driver' => 'mysql',
                    'host' => env('DB_HOST', 'mysql'),
                    'port' => env('DB_PORT', '3306'),
                    'database' => 'eventos_testing',
                    'username' => env('DB_USERNAME', 'eventos_user'),
                    'password' => env('DB_PASSWORD', 'secret'),
                    'charset' => 'utf8mb4',
                    'collation' => 'utf8mb4_unicode_ci',
                    'prefix' => '',
                    'strict' => true,
                    'engine' => null,
                ],
            ]);
        }

        Model::preventLazyLoading(
            ! app()->isProduction()
        );
    })
    ->create();
