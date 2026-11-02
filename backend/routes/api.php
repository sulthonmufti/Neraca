<?php

use Illuminate\Support\Facades\Route;
use App\Modules\Sales\Http\Controllers\SaleController;
use App\Modules\Cash\Http\Controllers\CashController;

Route::middleware(['auth:sanctum'])->prefix('v1')->group(function () {
    Route::post('/sales', [SaleController::class, 'store']);

    Route::post('/shifts/open', [CashController::class, 'openShift']);
    Route::post('/shifts/{id}/close', [CashController::class, 'closeShift']);
});