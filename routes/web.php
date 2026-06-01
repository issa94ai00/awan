<?php

use App\Http\Controllers\HomeController;
use App\Http\Controllers\CategoryController;
use App\Http\Controllers\ProductController;
use App\Http\Controllers\AdminController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\Admin\ProductionController;
use App\Http\Controllers\Admin\QuoteController;
use App\Http\Controllers\Admin\SalesOrderController;
use App\Http\Controllers\Admin\PaymentController;
use App\Http\Controllers\Admin\PurchaseReceiptController;
use App\Http\Controllers\Admin\PayrollController;
use App\Http\Controllers\Admin\RoleController;
use App\Http\Controllers\Admin\PermissionController;
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
        return redirect()->route('admin.vue');
    });
    
    // Vue SPA Route - Main admin entry point
    Route::get('/vue', function () {
        return view('vue');
    })->name('admin.vue');
    
    // Redirect dashboard to Vue
    Route::get('/dashboard', function () {
        return redirect()->route('admin.vue');
    })->name('admin.dashboard');

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
        
        // Quotes (عروض أسعار)
        Route::get('/quotes', [QuoteController::class, 'index'])->name('admin.quotes.index');
        Route::get('/quotes/create', [QuoteController::class, 'create'])->name('admin.quotes.create');
        Route::post('/quotes', [QuoteController::class, 'store'])->name('admin.quotes.store');
        Route::get('/quotes/{quote}', [QuoteController::class, 'show'])->name('admin.quotes.show');
        Route::get('/quotes/{quote}/edit', [QuoteController::class, 'edit'])->name('admin.quotes.edit');
        Route::put('/quotes/{quote}', [QuoteController::class, 'update'])->name('admin.quotes.update');
        Route::delete('/quotes/{quote}', [QuoteController::class, 'destroy'])->name('admin.quotes.destroy');
        Route::post('/quotes/{quote}/convert-to-sales-order', [QuoteController::class, 'convertToSalesOrder'])->name('admin.quotes.convert-to-sales-order');
        
        // Sales Orders (طلبات بيع)
        Route::get('/sales-orders', [SalesOrderController::class, 'index'])->name('admin.sales-orders.index');
        Route::get('/sales-orders/create', [SalesOrderController::class, 'create'])->name('admin.sales-orders.create');
        Route::post('/sales-orders', [SalesOrderController::class, 'store'])->name('admin.sales-orders.store');
        Route::get('/sales-orders/{salesOrder}', [SalesOrderController::class, 'show'])->name('admin.sales-orders.show');
        Route::get('/sales-orders/{salesOrder}/edit', [SalesOrderController::class, 'edit'])->name('admin.sales-orders.edit');
        Route::put('/sales-orders/{salesOrder}', [SalesOrderController::class, 'update'])->name('admin.sales-orders.update');
        Route::delete('/sales-orders/{salesOrder}', [SalesOrderController::class, 'destroy'])->name('admin.sales-orders.destroy');
        Route::post('/sales-orders/{salesOrder}/convert-to-invoice', [SalesOrderController::class, 'convertToInvoice'])->name('admin.sales-orders.convert-to-invoice');
        
        // Payments (مدفوعات)
        Route::get('/payments', [PaymentController::class, 'index'])->name('admin.payments.index');
        Route::get('/payments/create', [PaymentController::class, 'create'])->name('admin.payments.create');
        Route::post('/payments', [PaymentController::class, 'store'])->name('admin.payments.store');
        Route::get('/payments/{payment}', [PaymentController::class, 'show'])->name('admin.payments.show');
        Route::get('/payments/{payment}/edit', [PaymentController::class, 'edit'])->name('admin.payments.edit');
        Route::put('/payments/{payment}', [PaymentController::class, 'update'])->name('admin.payments.update');
        Route::delete('/payments/{payment}', [PaymentController::class, 'destroy'])->name('admin.payments.destroy');
    });

    Route::prefix('purchases')->group(function () {
        Route::get('/', [\App\Http\Controllers\Admin\PurchaseController::class, 'index'])->name('admin.purchases.index');
        Route::get('/suppliers', [\App\Http\Controllers\Admin\PurchaseController::class, 'suppliers'])->name('admin.purchases.suppliers');
        Route::get('/orders', [\App\Http\Controllers\Admin\PurchaseController::class, 'orders'])->name('admin.purchases.orders');
        
        // Purchase Receipts (إيصالات استلام)
        Route::get('/receipts', [PurchaseReceiptController::class, 'index'])->name('admin.purchase-receipts.index');
        Route::get('/receipts/create', [PurchaseReceiptController::class, 'create'])->name('admin.purchase-receipts.create');
        Route::post('/receipts', [PurchaseReceiptController::class, 'store'])->name('admin.purchase-receipts.store');
        Route::get('/receipts/{receipt}', [PurchaseReceiptController::class, 'show'])->name('admin.purchase-receipts.show');
        Route::get('/receipts/{receipt}/edit', [PurchaseReceiptController::class, 'edit'])->name('admin.purchase-receipts.edit');
        Route::put('/receipts/{receipt}', [PurchaseReceiptController::class, 'update'])->name('admin.purchase-receipts.update');
        Route::delete('/receipts/{receipt}', [PurchaseReceiptController::class, 'destroy'])->name('admin.purchase-receipts.destroy');
    });

    Route::prefix('hr')->group(function () {
        Route::get('/', [\App\Http\Controllers\Admin\HrController::class, 'index'])->name('admin.hr.index');
        Route::get('/employees', [\App\Http\Controllers\Admin\HrController::class, 'employees'])->name('admin.hr.employees');
        Route::get('/attendance', [\App\Http\Controllers\Admin\HrController::class, 'attendance'])->name('admin.hr.attendance');
        Route::get('/leaves', [\App\Http\Controllers\Admin\HrController::class, 'leaveRequests'])->name('admin.hr.leaves');
        
        // Payrolls (رواتب)
        Route::get('/payrolls', [PayrollController::class, 'index'])->name('admin.payrolls.index');
        Route::get('/payrolls/create', [PayrollController::class, 'create'])->name('admin.payrolls.create');
        Route::post('/payrolls', [PayrollController::class, 'store'])->name('admin.payrolls.store');
        Route::get('/payrolls/{payroll}', [PayrollController::class, 'show'])->name('admin.payrolls.show');
        Route::get('/payrolls/{payroll}/edit', [PayrollController::class, 'edit'])->name('admin.payrolls.edit');
        Route::put('/payrolls/{payroll}', [PayrollController::class, 'update'])->name('admin.payrolls.update');
        Route::delete('/payrolls/{payroll}', [PayrollController::class, 'destroy'])->name('admin.payrolls.destroy');
        Route::post('/payrolls/auto-generate', [PayrollController::class, 'autoGenerate'])->name('admin.payrolls.auto-generate');
    });

    Route::prefix('crm')->group(function () {
        Route::get('/', [\App\Http\Controllers\Admin\CrmController::class, 'index'])->name('admin.crm.index');
        Route::get('/customers', [\App\Http\Controllers\Admin\CrmController::class, 'customers'])->name('admin.crm.customers');
        Route::get('/tickets', [\App\Http\Controllers\Admin\CrmController::class, 'tickets'])->name('admin.crm.tickets');
    });

    Route::prefix('reports')->group(function () {
        Route::get('/', [\App\Http\Controllers\Admin\ReportsController::class, 'index'])->name('admin.reports.index');
        Route::get('/sales', [\App\Http\Controllers\Admin\ReportsController::class, 'salesReport'])->name('admin.reports.sales');
        Route::get('/inventory', [\App\Http\Controllers\Admin\ReportsController::class, 'inventoryReport'])->name('admin.reports.inventory');
        Route::get('/financial', [\App\Http\Controllers\Admin\ReportsController::class, 'financialReport'])->name('admin.reports.financial');
        Route::get('/payroll', [\App\Http\Controllers\Admin\ReportsController::class, 'payrollReport'])->name('admin.reports.payroll');
        Route::get('/export', [\App\Http\Controllers\Admin\ReportsController::class, 'export'])->name('admin.reports.export');
    });

    // Production
    Route::prefix('production')->group(function () {
        Route::get('/', [ProductionController::class, 'index'])->name('admin.production.index');
        Route::get('/create', [ProductionController::class, 'create'])->name('admin.production.create');
        Route::post('/', [ProductionController::class, 'store'])->name('admin.production.store');
        Route::get('/{productionOrder}', [ProductionController::class, 'show'])->name('admin.production.show');
        Route::get('/{productionOrder}/edit', [ProductionController::class, 'edit'])->name('admin.production.edit');
        Route::put('/{productionOrder}', [ProductionController::class, 'update'])->name('admin.production.update');
        Route::delete('/{productionOrder}', [ProductionController::class, 'destroy'])->name('admin.production.destroy');
        Route::put('/{productionOrder}/status', [ProductionController::class, 'updateStatus'])->name('admin.production.update-status');
    });

    // Profile
    Route::get('/profile', [AdminController::class, 'profileEdit'])->name('admin.profile.edit');
    Route::put('/profile', [AdminController::class, 'profileUpdate'])->name('admin.profile.update');

    // Stock Alerts
    Route::get('/stock-alerts', [AdminController::class, 'stockAlerts'])->name('admin.stock-alerts');

    // Roles & Permissions
    Route::prefix('roles')->middleware(['permission:roles.view'])->group(function () {
        Route::get('/', [RoleController::class, 'index'])->name('admin.roles.index');
        Route::get('/create', [RoleController::class, 'create'])->name('admin.roles.create')->middleware('permission:roles.create');
        Route::post('/', [RoleController::class, 'store'])->name('admin.roles.store')->middleware('permission:roles.create');
        Route::get('/{role}', [RoleController::class, 'show'])->name('admin.roles.show');
        Route::get('/{role}/edit', [RoleController::class, 'edit'])->name('admin.roles.edit')->middleware('permission:roles.edit');
        Route::put('/{role}', [RoleController::class, 'update'])->name('admin.roles.update')->middleware('permission:roles.edit');
        Route::delete('/{role}', [RoleController::class, 'destroy'])->name('admin.roles.destroy')->middleware('permission:roles.delete');
    });

    Route::prefix('permissions')->middleware(['permission:permissions.view'])->group(function () {
        Route::get('/', [PermissionController::class, 'index'])->name('admin.permissions.index');
        Route::get('/create', [PermissionController::class, 'create'])->name('admin.permissions.create')->middleware('permission:permissions.assign');
        Route::post('/', [PermissionController::class, 'store'])->name('admin.permissions.store')->middleware('permission:permissions.assign');
        Route::get('/{permission}/edit', [PermissionController::class, 'edit'])->name('admin.permissions.edit')->middleware('permission:permissions.assign');
        Route::put('/{permission}', [PermissionController::class, 'update'])->name('admin.permissions.update')->middleware('permission:permissions.assign');
        Route::delete('/{permission}', [PermissionController::class, 'destroy'])->name('admin.permissions.destroy')->middleware('permission:permissions.assign');
    });
});

// Auth Routes (from Breeze)
Route::middleware('auth')->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
});

require __DIR__.'/auth.php';
