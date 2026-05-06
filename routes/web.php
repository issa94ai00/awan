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

require __DIR__.'/auth.php';
