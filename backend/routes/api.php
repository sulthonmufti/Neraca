<?php

use Illuminate\Support\Facades\Route;
use App\Modules\Sales\Http\Controllers\SaleController;
use App\Modules\Cash\Http\Controllers\CashController;

Route::middleware(['auth:sanctum'])->prefix('v1')->group(function () {
    // Endpoint Transaksi Penjualan (Sales)
    Route::post('/sales', [SaleController::class, 'store']);
    Route::post('/sales/{id}/void', [SaleController::class, 'voidSale']);
    Route::post('/sales/{id}/void', [SaleController::class, 'voidSale']);
    Route::post('/sales/{id}/returns', [SaleController::class, 'returnSale']);

    // Endpoint Shift & Mutasi Kas (Cash)
    Route::post('/shifts/open', [CashController::class, 'openShift']);
    Route::post('/shifts/{id}/movements', [CashController::class, 'storeMovement']);
    Route::post('/shifts/{id}/close', [CashController::class, 'closeShift']);
});