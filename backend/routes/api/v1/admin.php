<?php

declare(strict_types=1);

use App\Http\Controllers\Api\V1\AuditController;
use App\Http\Controllers\Api\V1\SettingsController;
use App\Http\Controllers\Api\V1\UserManagementController;
use Illuminate\Support\Facades\Route;

// Settings (school + app)
Route::prefix('settings')->group(function (): void {
    Route::get('/',  [SettingsController::class, 'show']);
    Route::patch('/', [SettingsController::class, 'update']);
    Route::post('/logo', [SettingsController::class, 'uploadLogo']);
});

// User management (admin only)
Route::prefix('users')->group(function (): void {
    Route::get('/',                       [UserManagementController::class, 'index']);
    Route::post('/',                      [UserManagementController::class, 'store']);
    Route::patch('/{user}',               [UserManagementController::class, 'update']);
    Route::put('/{user}',                 [UserManagementController::class, 'update']);
    Route::post('/{user}/reset-password', [UserManagementController::class, 'resetPassword']);
});

// Audit log
Route::prefix('audit-logs')->group(function (): void {
    Route::get('/', [AuditController::class, 'index']);
});
