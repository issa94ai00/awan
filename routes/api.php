<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\CategoryController;
use App\Http\Controllers\Api\CompanyController;
use App\Http\Controllers\Api\ProductController;
use App\Http\Controllers\Api\SearchController;
use App\Http\Controllers\Api\InquiryController;
use App\Http\Controllers\Api\InvoiceController;
use App\Http\Controllers\Api\HomeController;
use App\Http\Controllers\Api\PosController;
use App\Http\Controllers\Api\ProductionController;
use App\Http\Controllers\Api\QuoteController;
use App\Http\Controllers\Api\SalesOrderController;
use App\Http\Controllers\Api\PaymentController;
use App\Http\Controllers\Api\PurchaseReceiptController;
use App\Http\Controllers\Api\PayrollController;
use App\Http\Controllers\Api\UploadController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "api" middleware group. Make something great!
|
*/

// Public API Routes
Route::prefix('v1')->group(function () {
    
    // Home & Featured Content
    Route::get('/home', [HomeController::class, 'index'])->name('api.home');
    Route::get('/featured-products', [HomeController::class, 'featuredProducts'])->name('api.featured-products');
    
    // Categories
    Route::get('/categories', [CategoryController::class, 'index'])->name('api.categories.index');
    Route::get('/categories/{category}', [CategoryController::class, 'show'])->name('api.categories.show');
    Route::get('/categories/{category}/products', [CategoryController::class, 'products'])->name('api.categories.products');
    
    // Products
    Route::get('/products', [ProductController::class, 'index'])->name('api.products.index');
    Route::get('/products/{product}', [ProductController::class, 'show'])->name('api.products.show');
    Route::get('/products/{product}/related', [ProductController::class, 'related'])->name('api.products.related');
    
    // Search
    Route::get('/search', [SearchController::class, 'search'])->name('api.search');
    Route::get('/search/suggestions', [SearchController::class, 'suggestions'])->name('api.search.suggestions');
    
    // Inquiries
    Route::post('/inquiries', [InquiryController::class, 'store'])->name('api.inquiries.store');
    
    // Authentication
    Route::post('/auth/register', [AuthController::class, 'register'])->name('api.auth.register');
    Route::post('/auth/login', [AuthController::class, 'login'])->name('api.auth.login');
    Route::post('/auth/logout', [AuthController::class, 'logout'])->middleware('auth:sanctum')->name('api.auth.logout');
    Route::get('/auth/user', [AuthController::class, 'user'])->middleware('auth:sanctum')->name('api.auth.user');
    Route::put('/auth/profile', [AuthController::class, 'updateProfile'])->middleware('auth:sanctum')->name('api.auth.profile');
    Route::post('/auth/change-password', [AuthController::class, 'changePassword'])->middleware('auth:sanctum')->name('api.auth.change-password');
    
    // Protected Routes (require authentication)
    Route::middleware('auth:sanctum')->group(function () {
        
        // File Upload
        Route::post('/upload', [UploadController::class, 'upload'])->name('api.upload');
        Route::delete('/upload', [UploadController::class, 'delete'])->name('api.upload.delete');
        
        // Admin Products API
        Route::prefix('admin')->group(function () {
            Route::get('/products', [ProductController::class, 'index'])->name('api.admin.products.index');
            Route::get('/products/{product}', [ProductController::class, 'show'])->name('api.admin.products.show');
            Route::post('/products', [ProductController::class, 'store'])->name('api.admin.products.store');
            Route::put('/products/{product}', [ProductController::class, 'update'])->name('api.admin.products.update');
            Route::delete('/products/{product}', [ProductController::class, 'destroy'])->name('api.admin.products.destroy');
            
            // Admin Categories API
            Route::get('/categories', [CategoryController::class, 'index'])->name('api.admin.categories.index');
            Route::get('/categories/{category}', [CategoryController::class, 'show'])->name('api.admin.categories.show');
            Route::post('/categories', [CategoryController::class, 'store'])->name('api.admin.categories.store');
            Route::put('/categories/{category}', [CategoryController::class, 'update'])->name('api.admin.categories.update');
            Route::delete('/categories/{category}', [CategoryController::class, 'destroy'])->name('api.admin.categories.destroy');
        });
        
        // User Inquiries
        Route::get('/user/inquiries', [InquiryController::class, 'index'])->name('api.user.inquiries.index');
        Route::get('/user/inquiries/{inquiry}', [InquiryController::class, 'show'])->name('api.user.inquiries.show');
        
        // User Favorites (if implemented)
        // Route::get('/user/favorites', [FavoriteController::class, 'index']);
        // Route::post('/user/favorites/{product}', [FavoriteController::class, 'toggle']);

        // POS Invoices
        Route::get('/invoices', [InvoiceController::class, 'index'])->name('api.invoices.index');
        Route::post('/invoices', [InvoiceController::class, 'store'])->name('api.invoices.store');
        Route::get('/invoices/{invoice}', [InvoiceController::class, 'show'])->name('api.invoices.show');
        Route::put('/invoices/{invoice}/status', [InvoiceController::class, 'updateStatus'])->name('api.invoices.update-status');
        Route::delete('/invoices/{invoice}', [InvoiceController::class, 'destroy'])->name('api.invoices.destroy');
        Route::get('/invoices/summary/stats', [InvoiceController::class, 'summary'])->name('api.invoices.summary');

        // POS / Flutter POS System
        Route::get('/pos/options', [PosController::class, 'options'])->name('api.pos.options');
        Route::get('/pos/products/lookup', [PosController::class, 'productLookup'])->name('api.pos.products.lookup');
        Route::get('/pos/customers', [PosController::class, 'customers'])->name('api.pos.customers.index');
        Route::post('/pos/customers', [PosController::class, 'customerStore'])->name('api.pos.customers.store');
        Route::get('/pos/customers/{customer}', [PosController::class, 'customerShow'])->name('api.pos.customers.show');

        // Companies
        Route::post('/companies', [CompanyController::class, 'store'])->name('api.companies.store');

        // Production / Manufacturing
        Route::get('/production', [ProductionController::class, 'index'])->name('api.production.index');
        Route::post('/production', [ProductionController::class, 'store'])->name('api.production.store');
        Route::get('/production/{productionOrder}', [ProductionController::class, 'show'])->name('api.production.show');
        Route::put('/production/{productionOrder}', [ProductionController::class, 'update'])->name('api.production.update');
        Route::delete('/production/{productionOrder}', [ProductionController::class, 'destroy'])->name('api.production.destroy');
        Route::put('/production/{productionOrder}/status', [ProductionController::class, 'updateStatus'])->name('api.production.update-status');
        Route::get('/production/stats', [ProductionController::class, 'stats'])->name('api.production.stats');

        // Quotes (عروض أسعار)
        Route::get('/quotes', [QuoteController::class, 'index'])->name('api.quotes.index');
        Route::post('/quotes', [QuoteController::class, 'store'])->name('api.quotes.store');
        Route::get('/quotes/{quote}', [QuoteController::class, 'show'])->name('api.quotes.show');
        Route::put('/quotes/{quote}', [QuoteController::class, 'update'])->name('api.quotes.update');
        Route::delete('/quotes/{quote}', [QuoteController::class, 'destroy'])->name('api.quotes.destroy');
        Route::post('/quotes/{quote}/convert-to-sales-order', [QuoteController::class, 'convertToSalesOrder'])->name('api.quotes.convert-to-sales-order');

        // Sales Orders (طلبات بيع)
        Route::get('/sales-orders', [SalesOrderController::class, 'index'])->name('api.sales-orders.index');
        Route::post('/sales-orders', [SalesOrderController::class, 'store'])->name('api.sales-orders.store');
        Route::get('/sales-orders/{salesOrder}', [SalesOrderController::class, 'show'])->name('api.sales-orders.show');
        Route::put('/sales-orders/{salesOrder}', [SalesOrderController::class, 'update'])->name('api.sales-orders.update');
        Route::delete('/sales-orders/{salesOrder}', [SalesOrderController::class, 'destroy'])->name('api.sales-orders.destroy');
        Route::post('/sales-orders/{salesOrder}/convert-to-invoice', [SalesOrderController::class, 'convertToInvoice'])->name('api.sales-orders.convert-to-invoice');

        // Payments (مدفوعات)
        Route::get('/payments', [PaymentController::class, 'index'])->name('api.payments.index');
        Route::post('/payments', [PaymentController::class, 'store'])->name('api.payments.store');
        Route::get('/payments/{payment}', [PaymentController::class, 'show'])->name('api.payments.show');
        Route::put('/payments/{payment}', [PaymentController::class, 'update'])->name('api.payments.update');
        Route::delete('/payments/{payment}', [PaymentController::class, 'destroy'])->name('api.payments.destroy');

        // Purchase Receipts (إيصالات استلام)
        Route::get('/purchase-receipts', [PurchaseReceiptController::class, 'index'])->name('api.purchase-receipts.index');
        Route::post('/purchase-receipts', [PurchaseReceiptController::class, 'store'])->name('api.purchase-receipts.store');
        Route::get('/purchase-receipts/{receipt}', [PurchaseReceiptController::class, 'show'])->name('api.purchase-receipts.show');
        Route::put('/purchase-receipts/{receipt}', [PurchaseReceiptController::class, 'update'])->name('api.purchase-receipts.update');
        Route::delete('/purchase-receipts/{receipt}', [PurchaseReceiptController::class, 'destroy'])->name('api.purchase-receipts.destroy');

        // Payrolls (رواتب)
        Route::get('/payrolls', [PayrollController::class, 'index'])->name('api.payrolls.index');
        Route::post('/payrolls', [PayrollController::class, 'store'])->name('api.payrolls.store');
        Route::get('/payrolls/{payroll}', [PayrollController::class, 'show'])->name('api.payrolls.show');
        Route::put('/payrolls/{payroll}', [PayrollController::class, 'update'])->name('api.payrolls.update');
        Route::delete('/payrolls/{payroll}', [PayrollController::class, 'destroy'])->name('api.payrolls.destroy');
        Route::post('/payrolls/auto-generate', [PayrollController::class, 'autoGenerate'])->name('api.payrolls.auto-generate');
    });
});

// Fallback for undefined API routes
Route::fallback(function () {
    return response()->json([
        'success' => false,
        'message' => 'API endpoint not found',
        'data' => null
    ], 404);
});
