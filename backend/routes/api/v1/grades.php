<?php

declare(strict_types=1);

use App\Http\Controllers\Api\V1\GradesController;
use Illuminate\Support\Facades\Route;

Route::prefix('grades')->group(function (): void {
    Route::post('/grade-sheets',                       [GradesController::class, 'storeGradeSheet']);
    Route::get('/grade-sheets',                        [GradesController::class, 'gradeSheets']);
    Route::put('/grade-sheets/{sheetId}',              [GradesController::class, 'updateGradeSheet']);
    Route::get('/assessments',                       [GradesController::class, 'index']);
    Route::post('/assessments',                      [GradesController::class, 'store']);
    Route::get('/assessments/{assessment}',          [GradesController::class, 'show']);
    Route::put('/assessments/{assessment}',          [GradesController::class, 'update']);
    Route::patch('/assessments/{assessment}',        [GradesController::class, 'update']);
    Route::delete('/assessments/{assessment}',       [GradesController::class, 'destroy']);
    Route::post('/assessments/{assessment}/publish', [GradesController::class, 'publish']);
    Route::post('/assessments/{assessment}/entries', [GradesController::class, 'recordEntries']);

    Route::get('/students/{studentId}/terms/{termId}/report', [GradesController::class, 'termReport'])
        ->where(['studentId' => '[0-9a-f-]{36}', 'termId' => '[0-9a-f-]{36}']);

    Route::get('/classes/{classId}/terms/{termId}/ranking',   [GradesController::class, 'classRanking'])
        ->where(['classId'   => '[0-9a-f-]{36}', 'termId' => '[0-9a-f-]{36}']);
});
