<?php

declare(strict_types=1);

use App\Http\Controllers\Api\V1\AuthController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Storage;

/*
|--------------------------------------------------------------------------
| API Routes — Version 1
|--------------------------------------------------------------------------
*/

Route::prefix('v1')->group(function (): void {

    // ─── Public auth routes ────────────────────────────────────────
    Route::post('/login', [AuthController::class, 'login'])
        ->middleware('throttle:login');

    Route::post('/login/verify-otp', [AuthController::class, 'verifyLoginOtp'])
        ->middleware('throttle:otp-verify');

    Route::post('/forgot-password', [AuthController::class, 'forgotPassword'])
        ->middleware('throttle:password-reset');

    Route::post('/reset-password', [AuthController::class, 'resetPassword'])
        ->middleware('throttle:password-reset');

    Route::get('/media/{directory}/{filename}', function (string $directory, string $filename) {
        abort_unless(in_array($directory, ['students', 'teachers', 'schools'], true), 404);
        abort_unless(preg_match('/^[A-Za-z0-9][A-Za-z0-9._-]*$/', $filename), 404);
        $path = $directory . '/' . $filename;
        abort_unless(Storage::disk('public')->exists($path), 404);
        $mime = Storage::disk('public')->mimeType($path) ?: 'application/octet-stream';
        return response(Storage::disk('public')->get($path), 200, [
            'Content-Type' => $mime,
            'Cache-Control' => 'private, max-age=1800',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    })->middleware('signed:relative')->name('public.media');

    // ─── Authenticated routes ──────────────────────────────────────
    Route::middleware(['auth:sanctum', 'throttle:api'])->group(function (): void {
        Route::post('/logout',        [AuthController::class, 'logout']);
        Route::get('/me',             [AuthController::class, 'me']);
        Route::patch('/me',           [AuthController::class, 'updateProfile']);
        Route::put('/me/password',    [AuthController::class, 'changePassword'])
            ->middleware('throttle:password-change');
        Route::post('/refresh-token', [AuthController::class, 'refreshToken']);

        require __DIR__ . '/api/v1/platform.php';

        Route::middleware('tenant')->group(function (): void {

        // Phase 2 — Students
        require __DIR__ . '/api/v1/students.php';
        require __DIR__ . '/api/v1/teachers.php';
        require __DIR__ . '/api/v1/parents.php';
        require __DIR__ . '/api/v1/academic.php';
        require __DIR__ . '/api/v1/attendance.php';
        require __DIR__ . '/api/v1/grades.php';
        require __DIR__ . '/api/v1/timetable.php';
        require __DIR__ . '/api/v1/finance.php';
        require __DIR__ . '/api/v1/reports.php';
        require __DIR__ . '/api/v1/dashboard.php';
        require __DIR__ . '/api/v1/admin.php';
            require __DIR__ . '/api/v1/notifications.php';
        });
    });
});

Route::fallback(function (Request $request) {
    return response()->json([
        'success'    => false,
        'message'    => 'The requested endpoint does not exist.',
        'error_code' => 'route_not_found',
    ], 404);
});
