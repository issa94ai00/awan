<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\CategoryController;
use App\Http\Controllers\Api\ProductController;
use App\Http\Controllers\Api\SearchController;
use App\Http\Controllers\Api\InquiryController;
use App\Http\Controllers\Api\InvoiceController;
use App\Http\Controllers\Api\HomeController;
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
