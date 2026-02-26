<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\BrandController;
use App\Http\Controllers\CategoryController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\GradeController;
use App\Http\Controllers\MasterProductController;
use App\Http\Controllers\ProductController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\ReportController;
use App\Http\Controllers\SaleController;
use App\Http\Controllers\SalesRepController;
use App\Http\Controllers\StoreSettingController;
use App\Http\Controllers\SupplierController;
use App\Http\Controllers\TaxController;
use App\Http\Controllers\UnitController;
use App\Http\Controllers\UserController;
use Illuminate\Support\Facades\Route;

/**
 * Authentication Routes (Public)
 */
Route::middleware('throttle:login')->group(function () {
    Route::post('/login', [AuthController::class, 'login'])->name('login');
});

Route::middleware('throttle:register')->group(function () {
    Route::post('/register', [AuthController::class, 'register'])->name('register');
});

/**
 * Protected Routes (Require Authentication)
 */
Route::middleware(['auth:sanctum', 'throttle:api'])->group(function () {

    // Auth Routes
    Route::post('/logout', [AuthController::class, 'logout'])->name('logout');
    Route::get('/me', [AuthController::class, 'me'])->name('me');
    Route::get('/dashboard/summary', [DashboardController::class, 'summary']);

    // User Routes
    Route::get('/user/all', [UserController::class, 'all']);
    Route::apiResource('user', UserController::class);
    Route::get('/user/all/paginated', [UserController::class, 'getAllPaginated']);

    // Sensitive operations (dengan rate limit lebih ketat)
    Route::middleware('throttle:sensitive')->group(function () {
        Route::post('/change-password', [AuthController::class, 'changePassword'])->name('change-password');
        Route::put('/user/{id}/update-password', [UserController::class, 'updatePassword']);
        Route::delete('/user/{id}', [UserController::class, 'destroy']);
    });

    // Profile Routes
    Route::get('/profile', [ProfileController::class, 'show']);
    Route::post('/profile', [ProfileController::class, 'update']);

    // Role & Permission Routes
    Route::get('/roles/capabilities', [\App\Http\Controllers\RoleController::class, 'capabilities'])->name('roles.capabilities');
    Route::apiResource('roles', \App\Http\Controllers\RoleController::class);
    Route::apiResource('permissions', \App\Http\Controllers\PermissionController::class);

    // ============================================
    // Master Data Routes
    // ============================================

    // Brands (Merk)
    Route::get('/brands/all', [BrandController::class, 'all']);
    Route::post('/brands/quick', [BrandController::class, 'quickStore']);
    Route::apiResource('brands', BrandController::class);

    // Categories (Kategori)
    Route::get('/categories/all', [CategoryController::class, 'all']);
    Route::post('/categories/quick', [CategoryController::class, 'quickStore']);
    Route::apiResource('categories', CategoryController::class);

    // Grades
    Route::get('/grades/all', [GradeController::class, 'all']);
    Route::post('/grades/quick', [GradeController::class, 'quickStore']);
    Route::apiResource('grades', GradeController::class);

    // Units (Satuan)
    Route::get('/units/all', [UnitController::class, 'all']);
    Route::post('/units/quick', [UnitController::class, 'quickStore']);
    Route::apiResource('units', UnitController::class);

    // Sales Reps (Sales)
    Route::get('/sales-reps/all', [SalesRepController::class, 'all']);
    Route::post('/sales-reps/quick', [SalesRepController::class, 'quickStore']);
    Route::apiResource('sales-reps', SalesRepController::class);

    // Suppliers (Suplier)
    Route::get('/suppliers/all', [SupplierController::class, 'all']);
    Route::post('/suppliers/quick', [SupplierController::class, 'quickStore']);
    Route::apiResource('suppliers', SupplierController::class);

    // Taxes (Pajak)
    Route::get('/taxes/all', [TaxController::class, 'all']);
    Route::post('/taxes/quick', [TaxController::class, 'quickStore']);
    Route::apiResource('taxes', TaxController::class);

    // Master Products (Katalog)
    Route::get('/master-products/all', [MasterProductController::class, 'all']);
    Route::get('/master-products/search', [MasterProductController::class, 'search']);
    Route::apiResource('master-products', MasterProductController::class);

    // ============================================
    // Purchase Routes (Pembelian)
    // ============================================
    Route::get('/purchases/items', [\App\Http\Controllers\PurchaseController::class, 'indexItems']);
    Route::get('/purchases/generate-invoice', [\App\Http\Controllers\PurchaseController::class, 'generateInvoiceNumber']);
    Route::post('/purchases/{id}/items', [\App\Http\Controllers\PurchaseController::class, 'addItem']);
    Route::put('/purchases/{purchaseId}/items/{itemId}', [\App\Http\Controllers\PurchaseController::class, 'updateItem']);
    Route::delete('/purchases/{purchaseId}/items/{itemId}', [\App\Http\Controllers\PurchaseController::class, 'removeItem']);
    Route::apiResource('purchases', \App\Http\Controllers\PurchaseController::class);

    // ============================================
    // Product Search Routes (POS)
    // ============================================
    Route::get('/products', [ProductController::class, 'index']);
    Route::get('/products/stock-details', [ProductController::class, 'stockDetails']);
    Route::get('/products/scan', [ProductController::class, 'scan']);
    Route::get('/products/search', [ProductController::class, 'search']);

    // ============================================
    // Sales Routes (Penjualan / POS)
    // ============================================
    // Sales Stats
    Route::get('/sales/stats', [SaleController::class, 'stats']);

    // Store Settings
    Route::get('/store-settings', [StoreSettingController::class, 'index']);
    Route::post('/store-settings', [StoreSettingController::class, 'update']);

    Route::get('/sales', [SaleController::class, 'index']);
    Route::post('/sales', [SaleController::class, 'store']);
    Route::put('/sales/{id}', [SaleController::class, 'update']);
    Route::get('/sales/{id}', [SaleController::class, 'show']);
    Route::delete('/sales/{id}', [SaleController::class, 'destroy']);

    // ============================================
    // Report Routes
    // ============================================
    Route::get('/reports/sales', [ReportController::class, 'sales']);
    Route::get('/reports/purchases', [ReportController::class, 'purchases']);
    Route::get('/reports/profit', [ReportController::class, 'profit']);

    // ============================================
    // Service Routes (Servis HP)
    // ============================================
    Route::get('/services', [\App\Http\Controllers\ServiceController::class, 'index']);
    Route::get('/services/{id}', [\App\Http\Controllers\ServiceController::class, 'show']);
    Route::post('/services', [\App\Http\Controllers\ServiceController::class, 'store']);
    Route::put('/services/{id}', [\App\Http\Controllers\ServiceController::class, 'update']);
    Route::patch('/services/{id}/status', [\App\Http\Controllers\ServiceController::class, 'updateStatus']);
    Route::post('/services/{id}/parts', [\App\Http\Controllers\ServiceController::class, 'addPart']);
    Route::delete('/services/{id}/parts/{partId}', [\App\Http\Controllers\ServiceController::class, 'removePart']);
    Route::delete('/services/{id}', [\App\Http\Controllers\ServiceController::class, 'destroy']);

    // Add your other protected routes here...
});
