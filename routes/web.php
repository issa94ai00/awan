<?php

use App\Http\Controllers\HomeController;
use App\Http\Controllers\CategoryController;
use App\Http\Controllers\ProductController;
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

// Admin Routes
Route::prefix('admin')->middleware(['admin'])->group(function () {
    Route::get('/', function () {
        return redirect()->route('admin.dashboard');
    });
    Route::get('/dashboard', [AdminController::class, 'dashboard'])->name('admin.dashboard');

    // Categories
    Route::get('/categories', [AdminController::class, 'categoriesIndex'])->name('admin.categories.index');
    Route::get('/categories/create', [AdminController::class, 'categoriesCreate'])->name('admin.categories.create');
    Route::post('/categories', [AdminController::class, 'categoriesStore'])->name('admin.categories.store');
    Route::get('/categories/{category}/edit', [AdminController::class, 'categoriesEdit'])->name('admin.categories.edit');
    Route::put('/categories/{category}', [AdminController::class, 'categoriesUpdate'])->name('admin.categories.update');
    Route::delete('/categories/{category}', [AdminController::class, 'categoriesDestroy'])->name('admin.categories.destroy');

    // Products
    Route::get('/products', [AdminController::class, 'productsIndex'])->name('admin.products.index');
    Route::get('/products/create', [AdminController::class, 'productsCreate'])->name('admin.products.create');
    Route::post('/products', [AdminController::class, 'productsStore'])->name('admin.products.store');
    Route::get('/products/{product}/edit', [AdminController::class, 'productsEdit'])->name('admin.products.edit');
    Route::put('/products/{product}', [AdminController::class, 'productsUpdate'])->name('admin.products.update');
    Route::delete('/products/{product}', [AdminController::class, 'productsDestroy'])->name('admin.products.destroy');

    // Inquiries
    Route::get('/inquiries', [AdminController::class, 'inquiriesIndex'])->name('admin.inquiries.index');
    Route::get('/inquiries/{inquiry}', [AdminController::class, 'inquiriesShow'])->name('admin.inquiries.show');
    Route::put('/inquiries/{inquiry}', [AdminController::class, 'inquiriesUpdate'])->name('admin.inquiries.update');
    Route::delete('/inquiries/{inquiry}', [AdminController::class, 'inquiriesDestroy'])->name('admin.inquiries.destroy');

    // Settings
    Route::get('/settings', [AdminController::class, 'settingsIndex'])->name('admin.settings.index');
    Route::put('/settings', [AdminController::class, 'settingsUpdate'])->name('admin.settings.update');

    // Visitors
    Route::get('/visitors', [AdminController::class, 'visitorsIndex'])->name('admin.visitors.index');

    // POS
    Route::get('/pos', [AdminController::class, 'posIndex'])->name('admin.pos');

    // Business Modules
    Route::prefix('accounting')->group(function () {
        Route::get('/', [\App\Http\Controllers\Admin\AccountingController::class, 'index'])->name('admin.accounting.index');
        Route::get('/journal', [\App\Http\Controllers\Admin\AccountingController::class, 'journal'])->name('admin.accounting.journal');
        Route::get('/ledger', [\App\Http\Controllers\Admin\AccountingController::class, 'ledger'])->name('admin.accounting.ledger');
        Route::get('/trial-balance', [\App\Http\Controllers\Admin\AccountingController::class, 'trialBalance'])->name('admin.accounting.trial-balance');
    });

    Route::prefix('inventory')->group(function () {
        Route::get('/', [\App\Http\Controllers\Admin\InventoryController::class, 'index'])->name('admin.inventory.index');
        Route::get('/movements', [\App\Http\Controllers\Admin\InventoryController::class, 'movements'])->name('admin.inventory.movements');
    });

    Route::prefix('sales')->group(function () {
        Route::get('/', [\App\Http\Controllers\Admin\SalesController::class, 'index'])->name('admin.sales.index');
        Route::get('/invoices', [\App\Http\Controllers\Admin\SalesController::class, 'invoices'])->name('admin.sales.invoices');
        Route::get('/customers', [\App\Http\Controllers\Admin\SalesController::class, 'customers'])->name('admin.sales.customers');
    });

    Route::prefix('purchases')->group(function () {
        Route::get('/', [\App\Http\Controllers\Admin\PurchaseController::class, 'index'])->name('admin.purchases.index');
        Route::get('/suppliers', [\App\Http\Controllers\Admin\PurchaseController::class, 'suppliers'])->name('admin.purchases.suppliers');
        Route::get('/orders', [\App\Http\Controllers\Admin\PurchaseController::class, 'orders'])->name('admin.purchases.orders');
    });

    Route::prefix('hr')->group(function () {
        Route::get('/', [\App\Http\Controllers\Admin\HrController::class, 'index'])->name('admin.hr.index');
        Route::get('/employees', [\App\Http\Controllers\Admin\HrController::class, 'employees'])->name('admin.hr.employees');
        Route::get('/attendance', [\App\Http\Controllers\Admin\HrController::class, 'attendance'])->name('admin.hr.attendance');
        Route::get('/leaves', [\App\Http\Controllers\Admin\HrController::class, 'leaveRequests'])->name('admin.hr.leaves');
    });

    Route::prefix('crm')->group(function () {
        Route::get('/', [\App\Http\Controllers\Admin\CrmController::class, 'index'])->name('admin.crm.index');
        Route::get('/customers', [\App\Http\Controllers\Admin\CrmController::class, 'customers'])->name('admin.crm.customers');
        Route::get('/tickets', [\App\Http\Controllers\Admin\CrmController::class, 'tickets'])->name('admin.crm.tickets');
    });

    Route::prefix('reports')->group(function () {
        Route::get('/', [\App\Http\Controllers\Admin\ReportsController::class, 'index'])->name('admin.reports.index');
        Route::get('/export', [\App\Http\Controllers\Admin\ReportsController::class, 'export'])->name('admin.reports.export');
    });

    // Profile
    Route::get('/profile', [AdminController::class, 'profileEdit'])->name('admin.profile.edit');
    Route::put('/profile', [AdminController::class, 'profileUpdate'])->name('admin.profile.update');
});

// Auth Routes (from Breeze)
Route::middleware('auth')->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
});

require __DIR__.'/auth.php';
