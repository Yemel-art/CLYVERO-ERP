<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;

/*
 * Web routes intentionally minimal. This is an API-first application;
 * the frontend is a separate Next.js project.
 */

Route::get('/', function () {
    return response()->json([
        'success' => true,
        'message' => 'Clyvero ERP API. See /api/v1/* for endpoints.',
        'data'    => [
            'app'     => config('app.name'),
            'env'     => app()->environment(),
            'version' => 'v1',
        ],
    ]);
});
