<?php

use Illuminate\Support\Facades\Route;
use App\Modules\Core\Http\Controllers\AuthController;
use App\Modules\Sales\Http\Controllers\SaleController;
use App\Modules\Cash\Http\Controllers\CashController;
use App\Modules\Inventory\Http\Controllers\GoodsReceiptController;
use App\Modules\Inventory\Http\Controllers\SupplierController;
use App\Modules\Inventory\Http\Controllers\StockLevelController;
use App\Modules\Inventory\Http\Controllers\StockOpnameController;
use App\Modules\Member\Http\Controllers\MemberController;
use App\Modules\Reporting\Http\Controllers\ReportController;
use App\Modules\Catalog\Http\Controllers\PosCatalogController;

// Route Publik (Tanpa Token)
Route::prefix('v1')->group(function () {
    Route::post('/auth/login', [AuthController::class, 'login']);
});

// Route Terproteksi (Wajib Bearer Token Sanctum)
Route::middleware(['auth:sanctum'])->prefix('v1')->group(function () {
    // Autentikasi
    Route::post('/auth/logout', [AuthController::class, 'logout']);

    // Endpoint Transaksi Penjualan (Sales)
    Route::post('/sales', [SaleController::class, 'store']);
    Route::post('/sales/{id}/void', [SaleController::class, 'voidSale']);
    Route::post('/sales/{id}/returns', [SaleController::class, 'returnSale']);
    Route::get('/sales/hold', [SaleController::class, 'listHold']);
    Route::post('/sales/{id}/pay', [SaleController::class, 'payHold']);

    // Endpoint Shift & Mutasi Kas (Cash)
    Route::get('/shifts/current', [CashController::class, 'currentShift']);
    Route::post('/shifts/open', [CashController::class, 'openShift']);
    Route::post('/shifts/{id}/movements', [CashController::class, 'storeMovement']);
    Route::post('/shifts/{id}/close', [CashController::class, 'closeShift']);

    // Endpoint Inventory: Supplier & Goods Receipts
    Route::get('/suppliers', [SupplierController::class, 'index']);
    Route::post('/suppliers', [SupplierController::class, 'store']);
    Route::get('/inventory/goods-receipts', [GoodsReceiptController::class, 'index']);
    Route::post('/inventory/goods-receipts', [GoodsReceiptController::class, 'store']);
    Route::get('/inventory/stock-opnames', [StockOpnameController::class, 'index']);
    Route::post('/inventory/stock-opnames', [StockOpnameController::class, 'store']);
    Route::get('/inventory/stocks', [StockLevelController::class, 'index']);
    Route::put('/inventory/stocks/reorder-point', [StockLevelController::class, 'updateReorderPoint']);

    // Endpoint Modul Member Dasar
    Route::get('/member/departments', [MemberController::class, 'listDepartments']);
    Route::post('/member/departments', [MemberController::class, 'storeDepartment']);
    Route::get('/member/types', [MemberController::class, 'listMemberTypes']);
    Route::post('/member/types', [MemberController::class, 'storeMemberType']);
    Route::get('/members/lookup', [MemberController::class, 'lookupByCode']);
    Route::get('/members', [MemberController::class, 'index']);
    Route::post('/members', [MemberController::class, 'store']);

    // Endpoint Modul Laporan Dasar / Reporting
    Route::get('/reports/sales/summary', [ReportController::class, 'summary']);
    Route::get('/reports/sales/by-product', [ReportController::class, 'byProduct']);
    Route::get('/reports/sales/by-payment-method', [ReportController::class, 'byPaymentMethod']);
    Route::get('/reports/sales/by-cashier', [ReportController::class, 'byCashier']);

    // Endpoint Pendukung Layar Kasir (POS)
    Route::get('/pos/catalog', [PosCatalogController::class, 'catalog']);
    Route::get('/payment-methods', [PosCatalogController::class, 'paymentMethods']);
});