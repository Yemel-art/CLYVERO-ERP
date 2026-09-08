<?php

declare(strict_types=1);

use App\Http\Controllers\Api\V1\PlatformSchoolController;
use Illuminate\Support\Facades\Route;

Route::middleware('platform_admin')->prefix('platform')->group(function (): void {
    Route::get('/overview', [PlatformSchoolController::class, 'overview']);
    Route::get('/schools', [PlatformSchoolController::class, 'index']);
    Route::post('/schools', [PlatformSchoolController::class, 'store']);
    Route::patch('/schools/{school}', [PlatformSchoolController::class, 'update']);
    Route::post('/schools/{school}/logo', [PlatformSchoolController::class, 'uploadLogo']);
    Route::get('/schools/{school}/administrator-credentials', [PlatformSchoolController::class, 'administratorCredentials']);
    Route::patch('/schools/{school}/administrator-credentials', [PlatformSchoolController::class, 'updateAdministratorCredentials']);
    Route::patch('/schools/{school}/status', [PlatformSchoolController::class, 'updateStatus']);
});
