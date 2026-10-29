<?php

use Illuminate\Support\Facades\Route;
use App\Modules\Sales\Http\Controllers\SaleController;

Route::middleware(['auth:sanctum'])->prefix('v1')->group(function () {
    Route::post('/sales', [SaleController::class, 'store']);
});