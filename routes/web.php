<?php

use App\Http\Controllers\HomeController;
use App\Http\Controllers\CategoryController;
use App\Http\Controllers\ProductController;
use App\Http\Controllers\AdminController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\Admin\ProductionController;
use App\Http\Controllers\Admin\QuoteController;
use App\Http\Controllers\Admin\InvoiceController;
use App\Http\Controllers\Admin\PaymentController;
use App\Http\Controllers\Admin\PayrollController;
use App\Http\Controllers\Admin\RoleController;
use App\Http\Controllers\Admin\PermissionController;
use App\Http\Controllers\CartController;
use Illuminate\Support\Facades\Route;

// Language Switch Route
Route::get('/lang/{locale}', function ($locale) {
    if (in_array($locale, ['en', 'ar'])) {
        session(['locale' => $locale]);
        app()->setLocale($locale);
    }
    return redirect()->back();
})->name('lang.switch');

// Public Routes
Route::get('/', function () { return view('vue'); })->name('home');
Route::get('/vision', function () { return view('vue'); })->name('vision');
Route::get('/about', function () { return view('vue'); })->name('about');
Route::get('/contact', function () { return view('vue'); })->name('contact');

Route::post('/contact', function () {
    // Handle contact form submission
    return redirect()->route('contact')->with('success', 'تم إرسال رسالتك بنجاح، سنتواصل معك قريباً.');
})->name('contact.store');

Route::get('/inquiry', function () { return view('vue'); })->name('inquiry.create');
Route::post('/inquiry', [HomeController::class, 'storeInquiry'])->name('inquiry.store');

// Purchase Request Page
Route::get('/purchase-request', function () { return view('vue'); })->name('purchase-request.create');
Route::get('/customer-orders', function () { return view('vue'); })->name('customer-orders');
Route::get('/featured-products', function () { return view('vue'); })->name('featured.products');
Route::get('/categories', function () { return view('vue'); })->name('categories.index');
Route::get('/category/{category}', function () { return view('vue'); })->name('category.show');
Route::get('/product/{product}', function () { return view('vue'); })->name('product.show');

// Cart Routes
Route::get('/cart', function () { return view('vue'); })->name('cart.index');
Route::post('/cart/add', [CartController::class, 'add'])->name('cart.add');
Route::post('/cart/update/{id}', [CartController::class, 'update'])->name('cart.update');
Route::post('/cart/remove/{id}', [CartController::class, 'remove'])->name('cart.remove');
Route::post('/cart/clear', [CartController::class, 'clear'])->name('cart.clear');
Route::get('/cart/count', [CartController::class, 'getCartCount'])->name('cart.count');

// Admin Routes
// Serve the Vue SPA for admin routes and let the SPA handle authentication.
Route::prefix('admin')->group(function () {
    // Vue SPA Route - Main admin entry point
    Route::get('/vue', function () {
        return view('vue');
    })->name('admin.vue');

    // Named route for admin dashboard
    Route::get('/dashboard', function () {
        return view('vue');
    })->name('admin.dashboard');

    // Preserve legacy route names for admin inquiries while serving the SPA.
    Route::view('/inquiries', 'vue')->name('admin.inquiries.index');
    Route::view('/inquiries/{id}', 'vue')->name('admin.inquiries.show');

    // Preserve legacy route names for admin inquiries while serving the SPA.
    Route::view('/inquiries', 'vue')->name('admin.inquiries.index');
    Route::view('/inquiries/{id}', 'vue')->name('admin.inquiries.show');

    // Serve the SPA on all other admin GET routes so the front-end router preserves the requested path.
    Route::get('/{path?}', function () {
        return view('vue');
    })->where('path', '.*');
});

// Auth Routes (from Breeze)
Route::middleware('auth')->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
});

require __DIR__.'/auth.php';

// Ensure the named login route serves the SPA login view so `route('login')` points to the SPA
Route::get('/login', function () {
    return view('vue');
})->name('login');

// Ensure the named dashboard route exists and redirects to the Admin SPA
Route::get('/dashboard', function () {
    return redirect()->route('admin.vue');
})->name('dashboard');

// Catch-all for Vue SPA public routes - any unmatched GET falls through to Vue Router
Route::get('/{path?}', function () {
    return view('vue');
})->where('path', '.*');

