<?php

declare(strict_types=1);

use App\Exceptions\DomainException;
use App\Http\Middleware\ForceJsonResponse;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // The production app is reachable only through the internal Caddy
        // reverse proxy. Trust its forwarded client IP and HTTPS headers so
        // rate limits, secure URLs, and audit logs use the real request data.
        $middleware->trustProxies(at: env('TRUSTED_PROXIES', '*'));

        // Force JSON responses for everything under /api so we never accidentally
        // return an HTML error page to an API client (per API Blueprint §5).
        $middleware->api(prepend: [
            ForceJsonResponse::class,
        ]);

        // Named throttle limiters configured in AppServiceProvider.
        $middleware->alias([
            'throttle' => \Illuminate\Routing\Middleware\ThrottleRequests::class,
            'tenant' => \App\Http\Middleware\SetSchoolTenant::class,
            'platform_admin' => \App\Http\Middleware\EnsureSuperAdministrator::class,
        ]);

        // Tenant context must exist before implicit route-model binding so a
        // UUID from another school resolves as 404 instead of being exposed.
        $middleware->prependToPriorityList(
            \Illuminate\Routing\Middleware\SubstituteBindings::class,
            \App\Http\Middleware\SetSchoolTenant::class,
        );
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        // Domain exceptions render themselves through the standard envelope.
        $exceptions->render(function (DomainException $e, Request $request) {
            if ($request->is('api/*')) {
                return $e->render();
            }
            return null;
        });

        // Validation errors → 422 with standard envelope.
        $exceptions->render(function (ValidationException $e, Request $request) {
            if ($request->is('api/*')) {
                $errors = collect($e->errors())->map(
                    fn (array $messages): array => array_map(
                        fn (string $message): string => __($message),
                        $messages,
                    ),
                )->all();

                return response()->json([
                    'success' => false,
                    'message' => __('api.validation_failed'),
                    'errors'  => $errors,
                ], 422);
            }
            return null;
        });

        // 401 for unauthenticated API requests.
        $exceptions->render(function (AuthenticationException $e, Request $request) {
            if ($request->is('api/*')) {
                return response()->json([
                    'success'    => false,
                    'message'    => __('api.authentication_required'),
                    'error_code' => 'unauthenticated',
                ], 401);
            }
            return null;
        });

        // Don't report DomainExceptions (they're expected business outcomes).
        $exceptions->dontReport([
            DomainException::class,
        ]);
    })->create();
