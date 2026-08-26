<?php

declare(strict_types=1);

use App\Http\Controllers\Api\V1\ParentController;
use Illuminate\Support\Facades\Route;

Route::prefix('parents')->group(function (): void {
    Route::get('/statistics',          [ParentController::class, 'statistics']);
    Route::post('/{parentId}/restore', [ParentController::class, 'restore'])
        ->where('parentId', '[0-9a-f-]{36}');

    Route::get('/',                    [ParentController::class, 'index']);
    Route::post('/',                   [ParentController::class, 'store']);
    Route::get('/{parent}',            [ParentController::class, 'show']);
    Route::put('/{parent}',            [ParentController::class, 'update']);
    Route::patch('/{parent}',          [ParentController::class, 'update']);
    Route::delete('/{parent}',         [ParentController::class, 'archive']);

    Route::post('/{parent}/children',                  [ParentController::class, 'attachChild']);
    Route::delete('/{parent}/children/{studentId}',    [ParentController::class, 'detachChild'])
        ->where('studentId', '[0-9a-f-]{36}');
});
