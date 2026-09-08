<?php

declare(strict_types=1);

use App\Http\Controllers\Api\V1\TimetableController;
use Illuminate\Support\Facades\Route;

Route::prefix('timetable')->group(function (): void {
    Route::get('/generation-config/{academicYearId}', [TimetableController::class, 'generationConfig'])
        ->where('academicYearId', '[0-9a-f-]{36}');
    Route::get('/school/{academicYearId}/slots', [TimetableController::class, 'schoolSlots'])
        ->where('academicYearId', '[0-9a-f-]{36}');
    Route::get('/my-slots', [TimetableController::class, 'mySlots']);
    Route::put('/generation-config', [TimetableController::class, 'saveGenerationConfig']);
    Route::post('/generate', [TimetableController::class, 'generate']);

    Route::get('/classes/{class}/slots',    [TimetableController::class, 'classSlots']);
    Route::get('/teachers/{teacherId}/slots', [TimetableController::class, 'teacherSlots'])
        ->where('teacherId', '[0-9a-f-]{36}');

    Route::post('/slots',           [TimetableController::class, 'store']);
    Route::put('/slots/{slot}',     [TimetableController::class, 'update']);
    Route::patch('/slots/{slot}',   [TimetableController::class, 'update']);
    Route::delete('/slots/{slot}',  [TimetableController::class, 'destroy']);
});
