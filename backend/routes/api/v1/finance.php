<?php

declare(strict_types=1);

use App\Http\Controllers\Api\V1\FinanceController;
use Illuminate\Support\Facades\Route;

Route::prefix('finance')->group(function (): void {
    Route::get('/summary', [FinanceController::class, 'summary']);
    // Fees
    Route::get('/fees',          [FinanceController::class, 'listFees']);
    Route::post('/fees',         [FinanceController::class, 'storeFee']);
    Route::put('/fees/{fee}',    [FinanceController::class, 'updateFee']);
    Route::patch('/fees/{fee}',  [FinanceController::class, 'updateFee']);
    Route::delete('/fees/{fee}', [FinanceController::class, 'destroyFee']);

    // Invoices
    Route::get('/invoices',                            [FinanceController::class, 'listInvoices']);
    Route::post('/invoices/generate',                  [FinanceController::class, 'generateInvoice']);
    Route::get('/invoices/{invoice}',                  [FinanceController::class, 'showInvoice']);
    Route::post('/invoices/{invoice}/cancel',          [FinanceController::class, 'cancelInvoice']);
    Route::post('/invoices/{invoice}/payments',        [FinanceController::class, 'recordPayment']);

    // Payments
    Route::delete('/payments/{payment}',               [FinanceController::class, 'voidPayment']);
    Route::get('/payments/{payment}/receipt',           [FinanceController::class, 'receipt']);

    // Student balance
    Route::get('/students/{studentId}/balance',        [FinanceController::class, 'studentBalance'])
        ->where('studentId', '[0-9a-f-]{36}');
});
