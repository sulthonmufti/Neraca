<?php

use Illuminate\Support\Facades\Route;
use App\Modules\Sales\Http\Controllers\SaleController;
use App\Modules\Cash\Http\Controllers\CashController;
use App\Modules\Inventory\Http\Controllers\GoodsReceiptController;
use App\Modules\Inventory\Http\Controllers\SupplierController;

Route::middleware(['auth:sanctum'])->prefix('v1')->group(function () {
    // Endpoint Transaksi Penjualan (Sales)
    Route::post('/sales', [SaleController::class, 'store']);

    // Endpoint Shift & Mutasi Kas (Cash)
    Route::post('/shifts/open', [CashController::class, 'openShift']);
    Route::post('/shifts/{id}/movements', [CashController::class, 'storeMovement']);
    Route::post('/shifts/{id}/close', [CashController::class, 'closeShift']);

    // Endpoint Inventory: Supplier & Goods Receipts
    Route::get('/suppliers', [SupplierController::class, 'index']);
    Route::post('/suppliers', [SupplierController::class, 'store']);
    Route::get('/inventory/goods-receipts', [GoodsReceiptController::class, 'index']);
    Route::post('/inventory/goods-receipts', [GoodsReceiptController::class, 'store']);
});