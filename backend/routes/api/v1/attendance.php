<?php

declare(strict_types=1);

use App\Http\Controllers\Api\V1\AttendanceController;
use Illuminate\Support\Facades\Route;

Route::prefix('attendance')->group(function (): void {
    Route::get('/sessions',                       [AttendanceController::class, 'index']);
    Route::post('/sessions',                      [AttendanceController::class, 'open']);
    Route::get('/sessions/{session}',             [AttendanceController::class, 'show']);
    Route::post('/sessions/{session}/records',    [AttendanceController::class, 'record']);
    Route::post('/sessions/{session}/close',      [AttendanceController::class, 'close']);
    Route::post('/sessions/{session}/reopen',     [AttendanceController::class, 'reopen']);
    Route::get('/sessions/{session}/stats',       [AttendanceController::class, 'stats']);

    Route::get('/students/{studentId}/summary',   [AttendanceController::class, 'studentSummary'])
        ->where('studentId', '[0-9a-f-]{36}');
});
