<?php

use App\Http\Controllers\HomeController;
use App\Http\Controllers\CategoryController;
use App\Http\Controllers\ProductController;
use App\Http\Controllers\AdminController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\Admin\ProductionController;
use App\Http\Controllers\Admin\QuoteController;
use App\Http\Controllers\Admin\SalesOrderController;
use App\Http\Controllers\Admin\InvoiceController;
use App\Http\Controllers\Admin\PaymentController;
use App\Http\Controllers\Admin\PurchaseReceiptController;
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

    // Invoice Routes - Blade views for invoice creation
    Route::get('/invoices', [InvoiceController::class, 'index'])->name('admin.sales.invoices');
    Route::get('/invoices/create', [InvoiceController::class, 'create'])->name('admin.invoices.create');
    Route::post('/invoices', [InvoiceController::class, 'store'])->name('admin.invoices.store');
    Route::get('/invoices/search-products', [InvoiceController::class, 'searchProducts'])->name('admin.invoices.search-products');

    // WMS Routes - Serve Blade views for Vue component mounting
    Route::view('/wms', 'admin.wms.index')->name('admin.wms.index');
    Route::view('/wms/warehouses', 'admin.wms.warehouses')->name('admin.wms.warehouses.index');
    Route::view('/wms/warehouses/create', 'admin.wms.warehouse-form')->name('admin.wms.warehouses.create');
    Route::view('/wms/warehouses/{id}/edit', 'admin.wms.warehouse-form')->name('admin.wms.warehouses.edit');
    Route::view('/wms/bins', 'admin.wms.bins')->name('admin.wms.bins.index');
    Route::view('/wms/bins/create', 'admin.wms.bin-form')->name('admin.wms.bins.create');
    Route::view('/wms/bins/{id}/edit', 'admin.wms.bin-form')->name('admin.wms.bins.edit');
    Route::view('/wms/picking', 'admin.wms.picking')->name('admin.wms.picking.index');
    Route::view('/wms/picking/create', 'admin.wms.picking-form')->name('admin.wms.picking.create');
    Route::view('/wms/packing', 'admin.wms.packing')->name('admin.wms.packing.index');
    Route::view('/wms/packing/create', 'admin.wms.packing-form')->name('admin.wms.packing.create');
    Route::view('/wms/cycle-counts', 'admin.wms.cycle-counts')->name('admin.wms.cycle-counts.index');
    Route::view('/wms/cycle-counts/create', 'admin.wms.cycle-count-form')->name('admin.wms.cycle-counts.create');
    Route::view('/wms/performance', 'admin.wms.performance')->name('admin.wms.performance');

    // Analytics Routes - Serve Blade views for Vue component mounting
    Route::view('/analytics', 'admin.analytics.index')->name('admin.analytics.index');
    Route::view('/analytics/sales', 'admin.analytics.sales')->name('admin.analytics.sales');
    Route::view('/analytics/inventory', 'admin.analytics.inventory')->name('admin.analytics.inventory');
    Route::view('/analytics/warehouse', 'admin.analytics.warehouse')->name('admin.analytics.warehouse');
    Route::view('/analytics/financial', 'admin.analytics.financial')->name('admin.analytics.financial');
    Route::view('/analytics/metrics', 'admin.analytics.metrics')->name('admin.analytics.metrics');
    Route::view('/analytics/reports', 'admin.analytics.reports')->name('admin.analytics.reports');
    Route::view('/analytics/dashboards', 'admin.analytics.dashboards')->name('admin.analytics.dashboards');

    // Notifications Routes - Serve Blade views for Vue component mounting
    Route::view('/notifications', 'admin.notifications.index')->name('admin.notifications.index');
    Route::view('/notifications/templates', 'admin.notifications.templates')->name('admin.notifications.templates');
    Route::view('/notifications/templates/create', 'admin.notifications.template-form')->name('admin.notifications.templates.create');
    Route::view('/notifications/templates/{id}/edit', 'admin.notifications.template-form')->name('admin.notifications.templates.edit');
    Route::view('/notifications/preferences', 'admin.notifications.preferences')->name('admin.notifications.preferences');

    // Workflows Routes - Serve Blade views for Vue component mounting
    Route::view('/workflows', 'admin.workflows.index')->name('admin.workflows.index');
    Route::view('/workflows/create', 'admin.workflows.form')->name('admin.workflows.create');
    Route::view('/workflows/{id}', 'admin.workflows.show')->name('admin.workflows.show');
    Route::view('/workflows/{id}/edit', 'admin.workflows.form')->name('admin.workflows.edit');
    Route::view('/workflows/{id}/steps', 'admin.workflows.steps')->name('admin.workflows.steps');
    Route::view('/workflows/{id}/executions', 'admin.workflows.executions')->name('admin.workflows.executions');

    // Audit Routes - Serve Blade views for Vue component mounting
    Route::view('/audit', 'admin.audit.index')->name('admin.audit.index');
    Route::view('/audit/entity-logs', 'admin.audit.entity-logs')->name('admin.audit.entity-logs');
    Route::view('/audit/user-activity/{id}', 'admin.audit.user-activity')->name('admin.audit.user-activity');
    Route::view('/audit/module-logs/{module}', 'admin.audit.module-logs')->name('admin.audit.module-logs');
    Route::view('/audit/statistics', 'admin.audit.statistics')->name('admin.audit.statistics');

    // RMA Routes - Serve Blade views for Vue component mounting
    Route::view('/rma', 'admin.rma.index')->name('admin.rma.index');
    Route::view('/rma/create', 'admin.rma.form')->name('admin.rma.create');
    Route::view('/rma/{id}', 'admin.rma.show')->name('admin.rma.show');
    Route::view('/rma/{id}/edit', 'admin.rma.form')->name('admin.rma.edit');

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

