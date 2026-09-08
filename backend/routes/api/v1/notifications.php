<?php

declare(strict_types=1);

use App\Http\Controllers\Api\V1\NotificationController;
use Illuminate\Support\Facades\Route;

Route::prefix('notifications')->group(function (): void {
    Route::get('/',                                 [NotificationController::class, 'index']);
    Route::post('/mark-all-read',                   [NotificationController::class, 'markAllRead']);
    Route::post('/{notification}/read',             [NotificationController::class, 'markRead']);
    Route::post('/broadcast',                       [NotificationController::class, 'broadcast']);
});
