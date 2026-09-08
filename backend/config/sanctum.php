<?php

declare(strict_types=1);

use Laravel\Sanctum\Sanctum;

return [
    /*
    | Stateful Domains
    | Domains that should receive a stateful session cookie when calling
    | the API from a first-party SPA (Next.js dev server, prod URL, etc.).
    */
    'stateful' => explode(',', env('SANCTUM_STATEFUL_DOMAINS', sprintf(
        '%s%s',
        'localhost,localhost:3000,127.0.0.1,127.0.0.1:3000,::1',
        Sanctum::currentApplicationUrlWithPort()
            ? ','.Sanctum::currentApplicationUrlWithPort()
            : ''
    ))),

    /*
    | Authentication Guards
    */
    'guard' => ['web'],

    /*
    | Token Expiration (minutes).
    | Standard login → 12h. Remember-me handled in code (30 days).
    */
    'expiration' => 60 * 12,

    /*
    | Token prefix to make grep-friendly tokens in logs/leaks.
    */
        'token_prefix' => env('SANCTUM_TOKEN_PREFIX', 'cly_'),

    /*
    | Middleware
    */
    'middleware' => [
        'authenticate_session' => Laravel\Sanctum\Http\Middleware\AuthenticateSession::class,
        'encrypt_cookies'      => Illuminate\Cookie\Middleware\EncryptCookies::class,
        'validate_csrf_token'  => Illuminate\Foundation\Http\Middleware\ValidateCsrfToken::class,
    ],
];
