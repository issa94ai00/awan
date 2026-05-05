<?php

use App\Http\Controllers\HomeController;
use App\Http\Controllers\CategoryController;
use App\Http\Controllers\ProductController;
use App\Http\Controllers\SearchController;
use App\Http\Controllers\AdminController;
use App\Http\Controllers\ProfileController;
use Illuminate\Support\Facades\Route;

// Public Routes
Route::get('/', [HomeController::class, 'index'])->name('home');

Route::get('/vision', function () {
    return view('vision');
})->name('vision');

Route::get('/about', function () {
    return view('about');
})->name('about');

Route::get('/contact', function () {
    return view('contact');
})->name('contact');

Route::post('/contact', function () {
    // Handle contact form submission
    return redirect()->route('contact')->with('success', 'تم إرسال رسالتك بنجاح، سنتواصل معك قريباً.');
})->name('contact.store');

Route::get('/inquiry', function () {
    return view('inquiry');
})->name('inquiry.create');

Route::post('/inquiry', [HomeController::class, 'storeInquiry'])->name('inquiry.store');

Route::get('/featured-products', [HomeController::class, 'featuredProducts'])
    ->name('featured.products');

Route::get('/categories', [CategoryController::class, 'index'])
    ->name('categories.index');

Route::get('/category/{category}', [CategoryController::class, 'show'])
    ->name('category.show');

Route::get('/product/{product}', [ProductController::class, 'show'])
    ->name('product.show');

// Search API routes
Route::get('/api/search', [SearchController::class, 'search'])
    ->name('api.search');

Route::get('/api/suggestions', [SearchController::class, 'suggestions'])
    ->name('api.suggestions');

// Auth Routes (from Breeze)
Route::middleware('auth')->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
});

// Admin Routes
Route::prefix('admin')->name('admin.')->middleware(['auth', 'admin'])->group(function () {
    // Dashboard
    Route::get('/', [AdminController::class, 'dashboard'])->name('dashboard');

    // Categories
    Route::get('/categories', [AdminController::class, 'categoriesIndex'])->name('categories.index');
    Route::get('/categories/create', [AdminController::class, 'categoriesCreate'])->name('categories.create');
    Route::post('/categories', [AdminController::class, 'categoriesStore'])->name('categories.store');
    Route::get('/categories/{category}/edit', [AdminController::class, 'categoriesEdit'])->name('categories.edit');
    Route::put('/categories/{category}', [AdminController::class, 'categoriesUpdate'])->name('categories.update');
    Route::delete('/categories/{category}', [AdminController::class, 'categoriesDestroy'])->name('categories.destroy');

    // Products
    Route::get('/products', [AdminController::class, 'productsIndex'])->name('products.index');
    Route::get('/products/create', [AdminController::class, 'productsCreate'])->name('products.create');
    Route::post('/products', [AdminController::class, 'productsStore'])->name('products.store');
    Route::get('/products/{product}/edit', [AdminController::class, 'productsEdit'])->name('products.edit');
    Route::put('/products/{product}', [AdminController::class, 'productsUpdate'])->name('products.update');
    Route::delete('/products/{product}', [AdminController::class, 'productsDestroy'])->name('products.destroy');

    // Inquiries
    Route::get('/inquiries', [AdminController::class, 'inquiriesIndex'])->name('inquiries.index');
    Route::get('/inquiries/{inquiry}', [AdminController::class, 'inquiriesShow'])->name('inquiries.show');
    Route::put('/inquiries/{inquiry}', [AdminController::class, 'inquiriesUpdate'])->name('inquiries.update');
    Route::delete('/inquiries/{inquiry}', [AdminController::class, 'inquiriesDestroy'])->name('inquiries.destroy');

    // Settings
    Route::get('/settings', [AdminController::class, 'settingsIndex'])->name('settings.index');
    Route::put('/settings', [AdminController::class, 'settingsUpdate'])->name('settings.update');

    // Visitors
    Route::get('/visitors', [AdminController::class, 'visitorsIndex'])->name('visitors.index');

    // Profile
    Route::get('/profile', [AdminController::class, 'profileEdit'])->name('profile.edit');
    Route::put('/profile', [AdminController::class, 'profileUpdate'])->name('profile.update');
});

require __DIR__.'/auth.php';
