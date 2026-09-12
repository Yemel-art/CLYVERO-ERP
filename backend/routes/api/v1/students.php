<?php

declare(strict_types=1);

use App\Http\Controllers\Api\V1\StudentController;
use App\Http\Controllers\Api\V1\CarteScolaireImportController;
use App\Http\Controllers\Api\V1\StudentImportController;
use Illuminate\Support\Facades\Route;

/*
 * Student endpoints — registered under /api/v1/students.
 * Authorization delegated to App\Policies\StudentPolicy via authorizeResource().
 */

Route::prefix('students')->group(function (): void {
    Route::post('/imports/carte-scolaire/preview', [CarteScolaireImportController::class, 'preview'])
        ->middleware('throttle:10,1');
    Route::post('/imports/carte-scolaire/{import}/approve', [CarteScolaireImportController::class, 'approve'])
        ->middleware('throttle:10,1');
    Route::get('/imports', [StudentImportController::class, 'index']);
    Route::post('/imports/analyze', [StudentImportController::class, 'analyze'])
        ->middleware('throttle:10,1');
    Route::post('/imports/preview', [StudentImportController::class, 'preview'])
        ->middleware('throttle:10,1');
    Route::get('/imports/{studentImport}', [StudentImportController::class, 'show']);
    Route::post('/imports/{studentImport}/confirm', [StudentImportController::class, 'confirm'])
        ->middleware('throttle:10,1');
    Route::get('/statistics', [StudentController::class, 'statistics']);
    Route::post('/{studentId}/restore', [StudentController::class, 'restore'])
        ->where('studentId', '[0-9a-f-]{36}');

    Route::get('/', [StudentController::class, 'index']);
    Route::post('/', [StudentController::class, 'store']);
    Route::get('/{student}/academic-history', [StudentController::class, 'academicHistory']);
    // The cleanup screen intentionally includes archived records. Restrict
    // soft-deleted binding support to this destructive route only.
    Route::delete('/{student}/permanent', [StudentController::class, 'permanentlyDelete'])
        ->withTrashed();
    Route::get('/{student}', [StudentController::class, 'show']);
    Route::put('/{student}', [StudentController::class, 'update']);
    Route::patch('/{student}', [StudentController::class, 'update']);
    Route::delete('/{student}', [StudentController::class, 'archive']);

    Route::post('/{student}/photo', [StudentController::class, 'uploadPhoto']);
    Route::delete('/{student}/photo', [StudentController::class, 'removePhoto']);
});
