<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;
use Symfony\Component\HttpFoundation\Response;

/**
 * Force every API request to be treated as JSON so the framework never
 * tries to render an HTML error page for an API client.
 *
 * Source: API Blueprint §4 (standard headers) + §5 (standard envelope).
 */
class ForceJsonResponse
{
    public function handle(Request $request, Closure $next): Response
    {
        $request->headers->set('Accept', 'application/json');
        $request->headers->set('X-Requested-With', 'XMLHttpRequest');

        // Public endpoints (login, OTP and password reset) do not pass through
        // the tenant middleware, so honour the same language header here.
        $locale = strtolower((string) $request->header('X-Locale', config('app.locale', 'fr')));
        App::setLocale(in_array($locale, ['en', 'fr'], true) ? $locale : 'fr');

        return $next($request);
    }
}
