<?php

declare(strict_types=1);

use App\Http\Controllers\Api\V1\AcademicController;
use App\Http\Controllers\Api\V1\ProgressionController;
use Illuminate\Support\Facades\Route;

// ─── Academic Years ─────────────────────────────────────────────────
Route::prefix('academic-years')->group(function (): void {
    Route::get('/',                       [AcademicController::class, 'years']);
    Route::post('/',                      [AcademicController::class, 'storeYear']);
    Route::post('/{year}/activate',       [AcademicController::class, 'activateYear']);

    Route::get('/{year}/terms',           [AcademicController::class, 'terms']);
    Route::post('/{year}/terms',          [AcademicController::class, 'storeTerm']);
    Route::get('/{year}/promotion-policy', [ProgressionController::class, 'policy']);
    Route::put('/{year}/promotion-policy', [ProgressionController::class, 'savePolicy']);
    Route::post('/{year}/academic-decisions/evaluate', [ProgressionController::class, 'evaluate']);
    Route::get('/{year}/academic-decisions', [ProgressionController::class, 'decisions']);
    Route::post('/{fromYear}/rollover/{toYear}', [ProgressionController::class, 'rollover']);
});

Route::patch('/academic-decisions/{decisionId}', [ProgressionController::class, 'updateDecision'])
    ->where('decisionId', '[0-9a-f-]{36}');

// ─── Terms (root) ───────────────────────────────────────────────────
Route::prefix('terms')->group(function (): void {
    Route::post('/{term}/activate',  [AcademicController::class, 'activateTerm']);
    Route::post('/{term}/close',     [AcademicController::class, 'closeTerm']);
});

// ─── Subjects ───────────────────────────────────────────────────────
Route::prefix('subjects')->group(function (): void {
    Route::post('/{subjectId}/restore', [AcademicController::class, 'restoreSubject'])
        ->where('subjectId', '[0-9a-f-]{36}');

    Route::get('/',                  [AcademicController::class, 'subjects']);
    Route::post('/',                 [AcademicController::class, 'storeSubject']);
    Route::get('/{subject}',         [AcademicController::class, 'showSubject']);
    Route::put('/{subject}',         [AcademicController::class, 'updateSubject']);
    Route::patch('/{subject}',       [AcademicController::class, 'updateSubject']);
    Route::delete('/{subject}',      [AcademicController::class, 'archiveSubject']);
});

// ─── Classes ────────────────────────────────────────────────────────
Route::prefix('classes')->group(function (): void {
    Route::post('/{classId}/restore', [AcademicController::class, 'restoreClass'])
        ->where('classId', '[0-9a-f-]{36}');

    Route::get('/',                   [AcademicController::class, 'classes']);
    Route::post('/',                  [AcademicController::class, 'storeClass']);
    Route::get('/{class}',            [AcademicController::class, 'showClass']);
    Route::put('/{class}',            [AcademicController::class, 'updateClass']);
    Route::patch('/{class}',          [AcademicController::class, 'updateClass']);
    Route::delete('/{class}',         [AcademicController::class, 'archiveClass']);

    Route::post('/{class}/subjects',  [AcademicController::class, 'attachSubjectToClass']);
    Route::delete('/{class}/subjects/{subjectId}', [AcademicController::class, 'detachSubjectFromClass'])
        ->where('subjectId', '[0-9a-f-]{36}');
});
