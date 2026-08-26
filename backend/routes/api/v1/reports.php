<?php

declare(strict_types=1);

use App\Http\Controllers\Api\V1\ReportCardController;
use App\Http\Controllers\Api\V1\CertificateController;
use App\Http\Controllers\Api\V1\SchoolHonorRollController;
use Illuminate\Support\Facades\Route;

Route::prefix('reports')->group(function (): void {
    Route::get('/certificates/teachers/{teacher}/employment', [CertificateController::class, 'employment']);
    Route::get('/certificates/students/{student}/school', [CertificateController::class, 'school']);
    Route::get('/honor-roll', [SchoolHonorRollController::class, 'data']);
    Route::get('/honor-roll/download', [SchoolHonorRollController::class, 'download']);
    Route::get('/students/{studentId}/terms/{termId}/data',     [ReportCardController::class, 'data'])
        ->where(['studentId' => '[0-9a-f-]{36}', 'termId' => '[0-9a-f-]{36}']);
    Route::get('/students/{studentId}/terms/{termId}/download', [ReportCardController::class, 'download'])
        ->where(['studentId' => '[0-9a-f-]{36}', 'termId' => '[0-9a-f-]{36}']);
    Route::get('/students/{studentId}/terms/{termId}/preview',  [ReportCardController::class, 'preview'])
        ->where(['studentId' => '[0-9a-f-]{36}', 'termId' => '[0-9a-f-]{36}']);
});
