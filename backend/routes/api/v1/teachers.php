<?php

declare(strict_types=1);

use App\Http\Controllers\Api\V1\TeacherController;
use Illuminate\Support\Facades\Route;

Route::prefix('teachers')->group(function (): void {
    Route::get('/statistics',           [TeacherController::class, 'statistics']);
    Route::post('/{teacherId}/restore', [TeacherController::class, 'restore'])
        ->where('teacherId', '[0-9a-f-]{36}');

    Route::get('/',                     [TeacherController::class, 'index']);
    Route::post('/',                    [TeacherController::class, 'store']);
    Route::get('/{teacher}',            [TeacherController::class, 'show']);
    Route::put('/{teacher}',            [TeacherController::class, 'update']);
    Route::patch('/{teacher}',          [TeacherController::class, 'update']);
    Route::delete('/{teacher}',         [TeacherController::class, 'archive']);

    Route::post('/{teacher}/photo',     [TeacherController::class, 'uploadPhoto']);
    Route::delete('/{teacher}/photo',   [TeacherController::class, 'removePhoto']);
});
