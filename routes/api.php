<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\CategoryController;
use App\Http\Controllers\Api\ProductController;
use App\Http\Controllers\Api\SearchController;
use App\Http\Controllers\Api\InquiryController;
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
    Route::get('/home', [HomeController::class, 'index']);
    Route::get('/featured-products', [HomeController::class, 'featuredProducts']);
    
    // Categories
    Route::get('/categories', [CategoryController::class, 'index']);
    Route::get('/categories/{category}', [CategoryController::class, 'show']);
    Route::get('/categories/{category}/products', [CategoryController::class, 'products']);
    
    // Products
    Route::get('/products', [ProductController::class, 'index']);
    Route::get('/products/{product}', [ProductController::class, 'show']);
    Route::get('/products/{product}/related', [ProductController::class, 'related']);
    
    // Search
    Route::get('/search', [SearchController::class, 'search']);
    Route::get('/search/suggestions', [SearchController::class, 'suggestions']);
    
    // Inquiries
    Route::post('/inquiries', [InquiryController::class, 'store']);
    
    // Authentication
    Route::post('/auth/register', [AuthController::class, 'register']);
    Route::post('/auth/login', [AuthController::class, 'login']);
    Route::post('/auth/logout', [AuthController::class, 'logout'])->middleware('auth:sanctum');
    Route::get('/auth/user', [AuthController::class, 'user'])->middleware('auth:sanctum');
    Route::put('/auth/profile', [AuthController::class, 'updateProfile'])->middleware('auth:sanctum');
    Route::post('/auth/change-password', [AuthController::class, 'changePassword'])->middleware('auth:sanctum');
    
    // Protected Routes (require authentication)
    Route::middleware('auth:sanctum')->group(function () {
        
        // User Inquiries
        Route::get('/user/inquiries', [InquiryController::class, 'index']);
        Route::get('/user/inquiries/{inquiry}', [InquiryController::class, 'show']);
        
        // User Favorites (if implemented)
        // Route::get('/user/favorites', [FavoriteController::class, 'index']);
        // Route::post('/user/favorites/{product}', [FavoriteController::class, 'toggle']);
        
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
