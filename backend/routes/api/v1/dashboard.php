<?php

declare(strict_types=1);

use App\Http\Controllers\Api\V1\DashboardController;
use Illuminate\Support\Facades\Route;

Route::prefix('dashboard')->group(function (): void {
    Route::get('/admin',     [DashboardController::class, 'admin']);
    Route::get('/secretary', [DashboardController::class, 'secretary']);
    Route::get('/teacher',   [DashboardController::class, 'teacher']);
    Route::get('/parent',    [DashboardController::class, 'parent']);
});
