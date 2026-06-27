<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\CategoryController;
use App\Http\Controllers\Api\CompanyController;
use App\Http\Controllers\Api\ProductController;
use App\Http\Controllers\Api\ProductUnitController;
use App\Http\Controllers\Api\ErpUpgradeController;
use App\Http\Controllers\Api\SearchController;
use App\Http\Controllers\Api\InquiryController;
use App\Http\Controllers\Api\InquiryAdminController;
use App\Http\Controllers\Api\InvoiceController;
use App\Http\Controllers\Api\DashboardController;
use App\Http\Controllers\Api\HomeController;
use App\Http\Controllers\Api\PosController;
use App\Http\Controllers\Api\ProductionController;
use App\Http\Controllers\Api\CustomerAuthController;
use App\Http\Controllers\Api\SubscribeController;
use App\Http\Controllers\Api\PurchaseRequestController;
use App\Http\Controllers\Api\QuoteController;
use App\Http\Controllers\Api\SalesOrderController;
use App\Http\Controllers\Api\PaymentController;
use App\Http\Controllers\Api\PurchaseReceiptController;
use App\Http\Controllers\Api\PayrollController;
use App\Http\Controllers\Api\EmployeeController;
use App\Http\Controllers\Api\AttendanceController;
use App\Http\Controllers\Api\LeaveRequestController;use App\Http\Controllers\Api\TicketController;use App\Http\Controllers\Api\UploadController;
use App\Http\Controllers\Api\SupplierController;
use App\Http\Controllers\Api\PurchaseOrderController;
use App\Http\Controllers\Api\StockMovementController;
use App\Http\Controllers\Api\LedgerAccountController;
use App\Http\Controllers\Api\JournalEntryController;
use App\Http\Controllers\Api\SettingsController;
use App\Http\Controllers\Api\SpecialOfferController;
use App\Http\Controllers\Api\InventoryTransferController;
use App\Http\Controllers\Api\EnhancedInventoryController;
use App\Http\Controllers\Api\EnhancedSalesOrderController;
use App\Http\Controllers\Api\RmaController;
use App\Http\Controllers\Api\WmsController;
use App\Http\Controllers\Api\AnalyticsController;
use App\Http\Controllers\Api\NotificationController;
use App\Http\Controllers\Api\WorkflowController;
use App\Http\Controllers\Api\AuditController;
use App\Http\Controllers\ExpenseController;
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
    Route::get('/special-offers', [SpecialOfferController::class, 'activeOffers'])->name('api.special-offers.active');
    Route::get('/settings', [SettingsController::class, 'index'])->name('api.settings.public');

    // Cart API (with Session / Web Middleware)
    Route::middleware('web')->group(function () {
        Route::get('/cart/data', [\App\Http\Controllers\CartController::class, 'getCartData'])->name('api.cart.data');
        Route::post('/cart/add', [\App\Http\Controllers\CartController::class, 'add'])->name('api.cart.add');
        Route::post('/cart/update/{id}', [\App\Http\Controllers\CartController::class, 'update'])->name('api.cart.update');
        Route::post('/cart/remove/{id}', [\App\Http\Controllers\CartController::class, 'remove'])->name('api.cart.remove');
        Route::post('/cart/clear', [\App\Http\Controllers\CartController::class, 'clear'])->name('api.cart.clear');
        Route::get('/cart/count', [\App\Http\Controllers\CartController::class, 'getCartCount'])->name('api.cart.count');
    });
    
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

    // Purchase Requests (public)
    Route::post('/purchase-requests', [PurchaseRequestController::class, 'store'])->name('api.purchase-requests.store');
    Route::get('/purchase-requests/orders', [PurchaseRequestController::class, 'orders'])->name('api.purchase-requests.orders');

    // Customer Auth (public)
    Route::post('/customer/auth/register', [CustomerAuthController::class, 'register'])->name('api.customer.auth.register');
    Route::post('/customer/auth/login', [CustomerAuthController::class, 'login'])->name('api.customer.auth.login');
    Route::post('/customer/auth/logout', [CustomerAuthController::class, 'logout'])->name('api.customer.auth.logout');
    Route::get('/customer/auth/user', [CustomerAuthController::class, 'user'])->name('api.customer.auth.user');

    // Subscribe (public)
    Route::post('/subscribe', [SubscribeController::class, 'store'])->name('api.subscribe.store');
    
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

            // Product Units API
            Route::get('/products/{product}/units', [ProductUnitController::class, 'index'])->name('api.admin.product-units.index');
            Route::post('/products/{product}/units', [ProductUnitController::class, 'store'])->name('api.admin.product-units.store');
            Route::put('/products/{product}/units/{unit}', [ProductUnitController::class, 'update'])->name('api.admin.product-units.update');
            Route::delete('/products/{product}/units/{unit}', [ProductUnitController::class, 'destroy'])->name('api.admin.product-units.destroy');
            Route::get('/units/search-barcode', [ProductUnitController::class, 'searchByBarcode'])->name('api.admin.product-units.search-barcode');

            // Admin Special Offers API
            Route::get('/special-offers', [SpecialOfferController::class, 'index'])->name('api.admin.special-offers.index');
            Route::post('/special-offers', [SpecialOfferController::class, 'store'])->name('api.admin.special-offers.store');
            Route::post('/special-offers/{id}', [SpecialOfferController::class, 'update'])->name('api.admin.special-offers.update');
            Route::delete('/special-offers/{id}', [SpecialOfferController::class, 'destroy'])->name('api.admin.special-offers.destroy');
            Route::post('/special-offers/{id}/toggle-status', [SpecialOfferController::class, 'toggleStatus'])->name('api.admin.special-offers.toggle-status');

            // Admin Inquiries API
            Route::get('/inquiries', [InquiryAdminController::class, 'index'])->name('api.admin.inquiries.index');
            Route::get('/inquiries/{inquiry}', [InquiryAdminController::class, 'show'])->name('api.admin.inquiries.show');
            Route::post('/inquiries/{inquiry}/replies', [InquiryAdminController::class, 'storeReply'])->name('api.admin.inquiries.replies.store');
            Route::put('/inquiries/{inquiry}', [InquiryAdminController::class, 'update'])->name('api.admin.inquiries.update');
            Route::delete('/inquiries/{inquiry}', [InquiryAdminController::class, 'destroy'])->name('api.admin.inquiries.destroy');
            Route::post('/inquiries/{inquiry}/close', [InquiryAdminController::class, 'close'])->name('api.admin.inquiries.close');
            Route::post('/inquiries/{inquiry}/reopen', [InquiryAdminController::class, 'reopen'])->name('api.admin.inquiries.reopen');
            Route::post('/inquiries/{inquiry}/assign', [InquiryAdminController::class, 'assign'])->name('api.admin.inquiries.assign');
            Route::post('/inquiries/bulk-update', [InquiryAdminController::class, 'bulkUpdate'])->name('api.admin.inquiries.bulk-update');
            Route::post('/inquiries/bulk-delete', [InquiryAdminController::class, 'bulkDelete'])->name('api.admin.inquiries.bulk-delete');
            
            // Admin Categories API
            Route::get('/categories', [CategoryController::class, 'index'])->name('api.admin.categories.index');
            Route::get('/categories/{category}', [CategoryController::class, 'show'])->name('api.admin.categories.show');
            Route::post('/categories', [CategoryController::class, 'store'])->name('api.admin.categories.store');
            Route::put('/categories/{category}', [CategoryController::class, 'update'])->name('api.admin.categories.update');
            Route::delete('/categories/{category}', [CategoryController::class, 'destroy'])->name('api.admin.categories.destroy');

            // Admin Suppliers and Purchase Orders
            Route::get('/suppliers', [SupplierController::class, 'index'])->name('api.admin.suppliers.index');
            Route::post('/suppliers', [SupplierController::class, 'store'])->name('api.admin.suppliers.store');
            Route::get('/suppliers/{supplier}', [SupplierController::class, 'show'])->name('api.admin.suppliers.show');
            Route::put('/suppliers/{supplier}', [SupplierController::class, 'update'])->name('api.admin.suppliers.update');
            Route::delete('/suppliers/{supplier}', [SupplierController::class, 'destroy'])->name('api.admin.suppliers.destroy');

            // Admin Employees API
            Route::get('/employees', [EmployeeController::class, 'index'])->name('api.admin.employees.index');
            Route::post('/employees', [EmployeeController::class, 'store'])->name('api.admin.employees.store');
            Route::get('/employees/{employee}', [EmployeeController::class, 'show'])->name('api.admin.employees.show');
            Route::put('/employees/{employee}', [EmployeeController::class, 'update'])->name('api.admin.employees.update');
            Route::delete('/employees/{employee}', [EmployeeController::class, 'destroy'])->name('api.admin.employees.destroy');

            // Admin Attendance API
            Route::get('/attendance', [AttendanceController::class, 'index'])->name('api.admin.attendance.index');
            Route::post('/attendance', [AttendanceController::class, 'store'])->name('api.admin.attendance.store');
            Route::get('/attendance/{attendance}', [AttendanceController::class, 'show'])->name('api.admin.attendance.show');
            Route::put('/attendance/{attendance}', [AttendanceController::class, 'update'])->name('api.admin.attendance.update');
            Route::delete('/attendance/{attendance}', [AttendanceController::class, 'destroy'])->name('api.admin.attendance.destroy');

            // Admin Leave Requests API
            Route::get('/leave-requests', [LeaveRequestController::class, 'index'])->name('api.admin.leave-requests.index');
            Route::post('/leave-requests', [LeaveRequestController::class, 'store'])->name('api.admin.leave-requests.store');
            Route::get('/leave-requests/{leaveRequest}', [LeaveRequestController::class, 'show'])->name('api.admin.leave-requests.show');
            Route::put('/leave-requests/{leaveRequest}', [LeaveRequestController::class, 'update'])->name('api.admin.leave-requests.update');
            Route::delete('/leave-requests/{leaveRequest}', [LeaveRequestController::class, 'destroy'])->name('api.admin.leave-requests.destroy');

            Route::get('/purchase-orders', [PurchaseOrderController::class, 'index'])->name('api.admin.purchase-orders.index');
            Route::post('/purchase-orders', [PurchaseOrderController::class, 'store'])->name('api.admin.purchase-orders.store');
            Route::get('/purchase-orders/{order}', [PurchaseOrderController::class, 'show'])->name('api.admin.purchase-orders.show');
            Route::put('/purchase-orders/{order}', [PurchaseOrderController::class, 'update'])->name('api.admin.purchase-orders.update');
            Route::delete('/purchase-orders/{order}', [PurchaseOrderController::class, 'destroy'])->name('api.admin.purchase-orders.destroy');

            // Admin Inventory Movements
            Route::get('/inventory/movements', [StockMovementController::class, 'index'])->name('api.admin.inventory.movements.index');
            Route::post('/inventory/movements', [StockMovementController::class, 'store'])->name('api.admin.inventory.movements.store');

            // Admin Accounting
            Route::get('/accounting/ledger-accounts', [LedgerAccountController::class, 'index'])->name('api.admin.accounting.ledger-accounts.index');
            Route::post('/accounting/ledger-accounts', [LedgerAccountController::class, 'store'])->name('api.admin.accounting.ledger-accounts.store');
            Route::get('/accounting/ledger-accounts/{ledgerAccount}', [LedgerAccountController::class, 'show'])->name('api.admin.accounting.ledger-accounts.show');
            Route::put('/accounting/ledger-accounts/{ledgerAccount}', [LedgerAccountController::class, 'update'])->name('api.admin.accounting.ledger-accounts.update');
            Route::delete('/accounting/ledger-accounts/{ledgerAccount}', [LedgerAccountController::class, 'destroy'])->name('api.admin.accounting.ledger-accounts.destroy');

            Route::get('/accounting/journal-entries', [JournalEntryController::class, 'index'])->name('api.admin.accounting.journal-entries.index');
            Route::post('/accounting/journal-entries', [JournalEntryController::class, 'store'])->name('api.admin.accounting.journal-entries.store');
            Route::get('/accounting/trial-balance', [JournalEntryController::class, 'trialBalance'])->name('api.admin.accounting.trial-balance');
        });

        // Settings
        Route::post('/settings', [SettingsController::class, 'update'])->name('api.settings.update');
        
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
        Route::put('/invoices/{invoice}', [InvoiceController::class, 'update'])->name('api.invoices.update');
        Route::put('/invoices/{invoice}/status', [InvoiceController::class, 'updateStatus'])->name('api.invoices.update-status');
        Route::delete('/invoices/{invoice}', [InvoiceController::class, 'destroy'])->name('api.invoices.destroy');
        Route::get('/invoices/summary/stats', [InvoiceController::class, 'summary'])->name('api.invoices.summary');
        Route::get('/dashboard/stats', [DashboardController::class, 'stats'])->name('api.dashboard.stats');

        // Admin Purchase Requests Management
        Route::get('/purchase-requests', [PurchaseRequestController::class, 'adminIndex'])->name('api.purchase-requests.admin.index');
        Route::get('/purchase-requests/{salesOrder}', [PurchaseRequestController::class, 'adminShow'])->name('api.purchase-requests.admin.show');
        Route::put('/purchase-requests/{salesOrder}/status', [PurchaseRequestController::class, 'adminUpdateStatus'])->name('api.purchase-requests.admin.update-status');

        // POS / Flutter POS System
        Route::get('/pos/options', [PosController::class, 'options'])->name('api.pos.options');
        Route::get('/pos/products/lookup', [PosController::class, 'productLookup'])->name('api.pos.products.lookup');
        Route::get('/pos/customers', [PosController::class, 'customers'])->name('api.pos.customers.index');
        Route::post('/pos/customers', [PosController::class, 'customerStore'])->name('api.pos.customers.store');
        Route::put('/pos/customers/{customer}', [PosController::class, 'customerUpdate'])->name('api.pos.customers.update');
        Route::delete('/pos/customers/{customer}', [PosController::class, 'customerDestroy'])->name('api.pos.customers.destroy');
        Route::get('/pos/customers/{customer}', [PosController::class, 'customerShow'])->name('api.pos.customers.show');

        // CRM Tickets
        Route::get('/tickets', [TicketController::class, 'index'])->name('api.tickets.index');
        Route::post('/tickets', [TicketController::class, 'store'])->name('api.tickets.store');
        Route::get('/tickets/{ticket}', [TicketController::class, 'show'])->name('api.tickets.show');
        Route::put('/tickets/{ticket}', [TicketController::class, 'update'])->name('api.tickets.update');
        Route::delete('/tickets/{ticket}', [TicketController::class, 'destroy'])->name('api.tickets.destroy');

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

        // Expenses (مصاريف)
        Route::get('/expenses', [ExpenseController::class, 'index'])->name('api.expenses.index');
        Route::post('/expenses', [ExpenseController::class, 'store'])->name('api.expenses.store');
        Route::get('/expenses/{expense}', [ExpenseController::class, 'show'])->name('api.expenses.show');
        Route::put('/expenses/{expense}', [ExpenseController::class, 'update'])->name('api.expenses.update');
        Route::delete('/expenses/{expense}', [ExpenseController::class, 'destroy'])->name('api.expenses.destroy');

        // Purchase Receipts (إيصالات استلام)
        Route::get('/purchase-receipts', [PurchaseReceiptController::class, 'index'])->name('api.purchase-receipts.index');
        Route::post('/purchase-receipts', [PurchaseReceiptController::class, 'store'])->name('api.purchase-receipts.store');
        Route::get('/purchase-receipts/{receipt}', [PurchaseReceiptController::class, 'show'])->name('api.purchase-receipts.show');
        Route::put('/purchase-receipts/{receipt}', [PurchaseReceiptController::class, 'update'])->name('api.purchase-receipts.update');
        Route::delete('/purchase-receipts/{receipt}', [PurchaseReceiptController::class, 'destroy'])->name('api.purchase-receipts.destroy');

        // ERP Retail & WMS Upgrades
        Route::post('/purchase-receipts/{id}/landed-costs', [ErpUpgradeController::class, 'allocateLandedCost'])->name('api.purchase-receipts.landed-costs');
        Route::post('/inventory/reserve', [ErpUpgradeController::class, 'reserveInventory'])->name('api.inventory.reserve');
        Route::post('/inventory/release', [ErpUpgradeController::class, 'releaseInventory'])->name('api.inventory.release');
        Route::get('/rmas', [ErpUpgradeController::class, 'rmaIndex'])->name('api.rmas.index');
        Route::post('/rmas', [ErpUpgradeController::class, 'rmaStore'])->name('api.rmas.store');
        Route::put('/rmas/{id}/status', [ErpUpgradeController::class, 'rmaUpdateStatus'])->name('api.rmas.update-status');
        Route::get('/integrations', [ErpUpgradeController::class, 'integrationIndex'])->name('api.integrations.index');
        Route::post('/integrations', [ErpUpgradeController::class, 'integrationStore'])->name('api.integrations.store');
        Route::get('/warehouse-bins', [ErpUpgradeController::class, 'binIndex'])->name('api.warehouse-bins.index');
        Route::post('/warehouse-bins', [ErpUpgradeController::class, 'binStore'])->name('api.warehouse-bins.store');

        // Enhanced Inventory Management (إدارة المخزون المتقدمة)
        Route::get('/inventory/locations', [EnhancedInventoryController::class, 'getLocations'])->name('api.inventory.locations');
        Route::get('/inventory/locations/{id}/stock', [EnhancedInventoryController::class, 'getLocationStock'])->name('api.inventory.locations.stock');
        Route::get('/inventory/products/{id}/stock', [EnhancedInventoryController::class, 'getProductStock'])->name('api.inventory.products.stock');
        Route::get('/inventory/low-stock-alerts', [EnhancedInventoryController::class, 'getLowStockAlerts'])->name('api.inventory.low-stock-alerts');
        Route::put('/inventory/low-stock-alerts/{id}/resolve', [EnhancedInventoryController::class, 'resolveAlert'])->name('api.inventory.low-stock-alerts.resolve');
        Route::get('/inventory/batches', [EnhancedInventoryController::class, 'getBatches'])->name('api.inventory.batches');
        Route::post('/inventory/batches', [EnhancedInventoryController::class, 'createBatch'])->name('api.inventory.batches.create');
        Route::get('/inventory/serial-numbers', [EnhancedInventoryController::class, 'getSerialNumbers'])->name('api.inventory.serial-numbers');
        Route::post('/inventory/serial-numbers', [EnhancedInventoryController::class, 'createSerialNumbers'])->name('api.inventory.serial-numbers.create');
        Route::post('/inventory/allocate', [EnhancedInventoryController::class, 'allocateInventory'])->name('api.inventory.allocate');
        Route::post('/inventory/check-availability', [EnhancedInventoryController::class, 'checkAvailability'])->name('api.inventory.check-availability');
        Route::post('/inventory/update-reorder-points', [EnhancedInventoryController::class, 'updateReorderPoints'])->name('api.inventory.update-reorder-points');

        // Inventory Transfers (نقل المخزون)
        Route::get('/inventory/transfers', [InventoryTransferController::class, 'index'])->name('api.inventory.transfers.index');
        Route::post('/inventory/transfers', [InventoryTransferController::class, 'store'])->name('api.inventory.transfers.store');
        Route::get('/inventory/transfers/{id}', [InventoryTransferController::class, 'show'])->name('api.inventory.transfers.show');
        Route::post('/inventory/transfers/{id}/ship', [InventoryTransferController::class, 'ship'])->name('api.inventory.transfers.ship');
        Route::post('/inventory/transfers/{id}/receive', [InventoryTransferController::class, 'receive'])->name('api.inventory.transfers.receive');
        Route::post('/inventory/transfers/{id}/cancel', [InventoryTransferController::class, 'cancel'])->name('api.inventory.transfers.cancel');

        // Enhanced Sales Orders & Multi-Channel (طلبات المبيعات المتقدمة)
        Route::get('/sales-orders/enhanced', [EnhancedSalesOrderController::class, 'index'])->name('api.sales-orders.enhanced.index');
        Route::post('/sales-orders/enhanced', [EnhancedSalesOrderController::class, 'store'])->name('api.sales-orders.enhanced.store');
        Route::get('/sales-orders/enhanced/{id}', [EnhancedSalesOrderController::class, 'show'])->name('api.sales-orders.enhanced.show');
        Route::post('/sales-orders/enhanced/{id}/allocate', [EnhancedSalesOrderController::class, 'allocateInventory'])->name('api.sales-orders.enhanced.allocate');
        Route::post('/sales-orders/enhanced/{id}/check-fulfillment', [EnhancedSalesOrderController::class, 'checkFulfillment'])->name('api.sales-orders.enhanced.check-fulfillment');
        Route::post('/sales-orders/enhanced/{id}/tracking', [EnhancedSalesOrderController::class, 'updateTracking'])->name('api.sales-orders.enhanced.tracking');
        Route::post('/sales-orders/enhanced/{id}/deliver', [EnhancedSalesOrderController::class, 'markAsDelivered'])->name('api.sales-orders.enhanced.deliver');
        Route::post('/sales-orders/enhanced/{id}/sync', [EnhancedSalesOrderController::class, 'syncOrder'])->name('api.sales-orders.enhanced.sync');
        Route::get('/sales-orders/channels', [EnhancedSalesOrderController::class, 'getChannels'])->name('api.sales-orders.channels');
        Route::get('/sales-orders/contracts', [EnhancedSalesOrderController::class, 'getContracts'])->name('api.sales-orders.contracts');
        Route::post('/sales-orders/contracts', [EnhancedSalesOrderController::class, 'createContract'])->name('api.sales-orders.contracts.create');
        Route::post('/sales-orders/contracts/{id}/approve', [EnhancedSalesOrderController::class, 'approveContract'])->name('api.sales-orders.contracts.approve');

        // RMA - Return Merchandise Authorization (إرجاع البضائع)
        Route::prefix('admin')->group(function () {
            Route::get('/rma', [RmaController::class, 'index'])->name('api.admin.rma.index');
            Route::post('/rma', [RmaController::class, 'store'])->name('api.admin.rma.store');
            Route::get('/rma/{id}', [RmaController::class, 'show'])->name('api.admin.rma.show');
            Route::put('/rma/{id}', [RmaController::class, 'update'])->name('api.admin.rma.update');
            Route::delete('/rma/{id}', [RmaController::class, 'destroy'])->name('api.admin.rma.destroy');
            Route::post('/rma/{id}/approve', [RmaController::class, 'approve'])->name('api.admin.rma.approve');
            Route::post('/rma/{id}/reject', [RmaController::class, 'reject'])->name('api.admin.rma.reject');
            Route::post('/rma/{id}/receive', [RmaController::class, 'receiveItems'])->name('api.admin.rma.receive');
            Route::post('/rma/{id}/complete', [RmaController::class, 'complete'])->name('api.admin.rma.complete');
            Route::post('/rma/{id}/cancel', [RmaController::class, 'cancel'])->name('api.admin.rma.cancel');
            Route::get('/rma/{id}/items', [RmaController::class, 'getItems'])->name('api.admin.rma.items');
            Route::put('/rma/items/{id}', [RmaController::class, 'updateItem'])->name('api.admin.rma.items.update');
            Route::get('/rma/statistics', [RmaController::class, 'getStatistics'])->name('api.admin.rma.statistics');
        });

        // WMS - Warehouse Management System (نظام إدارة المستودعات)
        Route::prefix('wms')->group(function () {
            // Warehouse Bins (أماكن التخزين)
            Route::get('/bins', [WmsController::class, 'indexBins'])->name('api.wms.bins.index');
            Route::get('/bins/{id}', [WmsController::class, 'showBin'])->name('api.wms.bins.show');
            Route::post('/bins', [WmsController::class, 'storeBin'])->name('api.wms.bins.store');
            Route::put('/bins/{id}', [WmsController::class, 'updateBin'])->name('api.wms.bins.update');
            Route::delete('/bins/{id}', [WmsController::class, 'destroyBin'])->name('api.wms.bins.destroy');

            // Picking Lists (قوائم الاختيار)
            Route::get('/picking-lists', [WmsController::class, 'indexPickingLists'])->name('api.wms.picking.index');
            Route::get('/picking-lists/{id}', [WmsController::class, 'showPickingList'])->name('api.wms.picking.show');
            Route::post('/picking-lists', [WmsController::class, 'createPickingList'])->name('api.wms.picking.create');
            Route::post('/picking-lists/{id}/start', [WmsController::class, 'startPicking'])->name('api.wms.picking.start');
            Route::post('/picking-items/{itemId}', [WmsController::class, 'pickItem'])->name('api.wms.picking.pick');
            Route::post('/picking-lists/{id}/complete', [WmsController::class, 'completePicking'])->name('api.wms.picking.complete');
            Route::post('/picking-lists/{id}/cancel', [WmsController::class, 'cancelPicking'])->name('api.wms.picking.cancel');
            Route::get('/picking/statistics', [WmsController::class, 'getPickingStatistics'])->name('api.wms.picking.statistics');

            // Packing Lists (قوائم التعبئة)
            Route::get('/packing-lists', [WmsController::class, 'indexPackingLists'])->name('api.wms.packing.index');
            Route::get('/packing-lists/{id}', [WmsController::class, 'showPackingList'])->name('api.wms.packing.show');
            Route::post('/packing-lists', [WmsController::class, 'createPackingList'])->name('api.wms.packing.create');
            Route::post('/packing-lists/{id}/start', [WmsController::class, 'startPacking'])->name('api.wms.packing.start');
            Route::put('/packing-items/{itemId}', [WmsController::class, 'updatePackageDetails'])->name('api.wms.packing.update');
            Route::post('/packing-lists/{id}/complete', [WmsController::class, 'completePacking'])->name('api.wms.packing.complete');
            Route::post('/packing-lists/{id}/cancel', [WmsController::class, 'cancelPacking'])->name('api.wms.packing.cancel');
            Route::get('/packing-lists/{id}/labels', [WmsController::class, 'getPackingLabels'])->name('api.wms.packing.labels');
            Route::get('/packing-lists/{id}/validate', [WmsController::class, 'validatePacking'])->name('api.wms.packing.validate');
            Route::get('/packing/statistics', [WmsController::class, 'getPackingStatistics'])->name('api.wms.packing.statistics');

            // Shipping Manifests (بيانات الشحن)
            Route::get('/shipping-manifests', [WmsController::class, 'indexShippingManifests'])->name('api.wms.shipping.index');
            Route::get('/shipping-manifests/{id}', [WmsController::class, 'showShippingManifest'])->name('api.wms.shipping.show');
            Route::post('/shipping-manifests', [WmsController::class, 'createShippingManifest'])->name('api.wms.shipping.create');
            Route::post('/shipping-manifests/{id}/dispatch', [WmsController::class, 'dispatchManifest'])->name('api.wms.shipping.dispatch');
            Route::post('/shipping-items/{itemId}/deliver', [WmsController::class, 'markItemDelivered'])->name('api.wms.shipping.deliver');

            // Cycle Counts (الجرد الدوري)
            Route::get('/cycle-counts', [WmsController::class, 'indexCycleCounts'])->name('api.wms.cycle.index');
            Route::get('/cycle-counts/{id}', [WmsController::class, 'showCycleCount'])->name('api.wms.cycle.show');
            Route::post('/cycle-counts', [WmsController::class, 'storeCycleCount'])->name('api.wms.cycle.store');
            Route::post('/cycle-counts/{id}/start', [WmsController::class, 'startCycleCount'])->name('api.wms.cycle.start');
            Route::post('/cycle-counts/{countId}/items', [WmsController::class, 'addCycleCountItem'])->name('api.wms.cycle.items.add');
            Route::post('/cycle-counts/{id}/complete', [WmsController::class, 'completeCycleCount'])->name('api.wms.cycle.complete');
            Route::post('/cycle-counts/{id}/review', [WmsController::class, 'reviewCycleCount'])->name('api.wms.cycle.review');
            Route::post('/cycle-counts/{id}/adjust', [WmsController::class, 'applyAdjustment'])->name('api.wms.cycle.adjust');
            Route::post('/cycle-counts/{id}/cancel', [WmsController::class, 'cancelCycleCount'])->name('api.wms.cycle.cancel');
        });

        // Analytics & Reporting (التحليلات والتقارير)
        Route::prefix('analytics')->group(function () {
            // Sales Analytics (تحليلات المبيعات)
            Route::get('/sales/summary', [AnalyticsController::class, 'getSalesSummary'])->name('api.analytics.sales.summary');
            Route::get('/sales/trend', [AnalyticsController::class, 'getSalesTrend'])->name('api.analytics.sales.trend');
            Route::get('/sales/by-channel', [AnalyticsController::class, 'getSalesByChannel'])->name('api.analytics.sales.by-channel');
            Route::get('/sales/top-products', [AnalyticsController::class, 'getTopSellingProducts'])->name('api.analytics.sales.top-products');
            Route::get('/sales/customer-analytics', [AnalyticsController::class, 'getCustomerAnalytics'])->name('api.analytics.sales.customer');
            Route::get('/sales/forecast', [AnalyticsController::class, 'forecastSales'])->name('api.analytics.sales.forecast');
            Route::get('/sales/conversion-funnel', [AnalyticsController::class, 'getConversionFunnel'])->name('api.analytics.sales.funnel');

            // Inventory Analytics (تحليلات المخزون)
            Route::get('/inventory/summary', [AnalyticsController::class, 'getInventorySummary'])->name('api.analytics.inventory.summary');
            Route::get('/inventory/turnover', [AnalyticsController::class, 'getInventoryTurnover'])->name('api.analytics.inventory.turnover');
            Route::get('/inventory/slow-moving', [AnalyticsController::class, 'getSlowMovingInventory'])->name('api.analytics.inventory.slow-moving');
            Route::get('/inventory/stockout', [AnalyticsController::class, 'getStockoutAnalysis'])->name('api.analytics.inventory.stockout');
            Route::get('/inventory/valuation', [AnalyticsController::class, 'getInventoryValuation'])->name('api.analytics.inventory.valuation');
            Route::get('/inventory/abc', [AnalyticsController::class, 'getABCAnalysis'])->name('api.analytics.inventory.abc');
            Route::get('/inventory/health-score', [AnalyticsController::class, 'getInventoryHealthScore'])->name('api.analytics.inventory.health');

            // Warehouse Analytics (تحليلات المستودع)
            Route::get('/warehouse/performance', [AnalyticsController::class, 'getWarehousePerformance'])->name('api.analytics.warehouse.performance');
            Route::get('/warehouse/bin-utilization', [AnalyticsController::class, 'getBinUtilization'])->name('api.analytics.warehouse.utilization');
            Route::get('/warehouse/cycle-count-accuracy', [AnalyticsController::class, 'getCycleCountAccuracy'])->name('api.analytics.warehouse.accuracy');
            Route::get('/warehouse/picker-performance', [AnalyticsController::class, 'getPickerPerformance'])->name('api.analytics.warehouse.picker');
            Route::get('/warehouse/capacity-planning', [AnalyticsController::class, 'getCapacityPlanning'])->name('api.analytics.warehouse.capacity');

            // Financial Analytics (التحليلات المالية)
            Route::get('/financial/summary', [AnalyticsController::class, 'getFinancialSummary'])->name('api.analytics.financial.summary');
            Route::get('/financial/revenue-by-category', [AnalyticsController::class, 'getRevenueByCategory'])->name('api.analytics.financial.revenue');
            Route::get('/financial/expenses', [AnalyticsController::class, 'getExpenseBreakdown'])->name('api.analytics.financial.expenses');
            Route::get('/financial/cash-flow', [AnalyticsController::class, 'getCashFlowAnalysis'])->name('api.analytics.financial.cashflow');
            Route::get('/financial/profit-loss', [AnalyticsController::class, 'getProfitAndLoss'])->name('api.analytics.financial.pnl');
            Route::get('/financial/aging', [AnalyticsController::class, 'getAccountsAging'])->name('api.analytics.financial.aging');
            Route::get('/financial/ratios', [AnalyticsController::class, 'getFinancialRatios'])->name('api.analytics.financial.ratios');
            Route::get('/financial/budget-vs-actual', [AnalyticsController::class, 'getBudgetVsActual'])->name('api.analytics.financial.budget');

            // Metrics Management (إدارة المؤشرات)
            Route::get('/metrics', [AnalyticsController::class, 'indexMetrics'])->name('api.analytics.metrics.index');
            Route::get('/metrics/{id}', [AnalyticsController::class, 'showMetric'])->name('api.analytics.metrics.show');
            Route::post('/metrics', [AnalyticsController::class, 'storeMetric'])->name('api.analytics.metrics.store');
            Route::put('/metrics/{id}', [AnalyticsController::class, 'updateMetric'])->name('api.analytics.metrics.update');
            Route::delete('/metrics/{id}', [AnalyticsController::class, 'destroyMetric'])->name('api.analytics.metrics.destroy');
            Route::get('/metrics/{id}/data', [AnalyticsController::class, 'getMetricData'])->name('api.analytics.metrics.data');

            // Reports Management (إدارة التقارير)
            Route::get('/reports', [AnalyticsController::class, 'indexReports'])->name('api.analytics.reports.index');
            Route::get('/reports/{id}', [AnalyticsController::class, 'showReport'])->name('api.analytics.reports.show');
            Route::post('/reports', [AnalyticsController::class, 'storeReport'])->name('api.analytics.reports.store');
            Route::put('/reports/{id}', [AnalyticsController::class, 'updateReport'])->name('api.analytics.reports.update');
            Route::delete('/reports/{id}', [AnalyticsController::class, 'destroyReport'])->name('api.analytics.reports.destroy');
            Route::post('/reports/{id}/run', [AnalyticsController::class, 'runReport'])->name('api.analytics.reports.run');

            // Dashboards Management (إدارة لوحات المعلومات)
            Route::get('/dashboards', [AnalyticsController::class, 'indexDashboards'])->name('api.analytics.dashboards.index');
            Route::get('/dashboards/{id}', [AnalyticsController::class, 'showDashboard'])->name('api.analytics.dashboards.show');
            Route::post('/dashboards', [AnalyticsController::class, 'storeDashboard'])->name('api.analytics.dashboards.store');
            Route::put('/dashboards/{id}', [AnalyticsController::class, 'updateDashboard'])->name('api.analytics.dashboards.update');
            Route::delete('/dashboards/{id}', [AnalyticsController::class, 'destroyDashboard'])->name('api.analytics.dashboards.destroy');
            Route::post('/dashboards/{dashboardId}/widgets', [AnalyticsController::class, 'addWidget'])->name('api.analytics.dashboards.addWidget');
            Route::put('/widgets/{id}', [AnalyticsController::class, 'updateWidget'])->name('api.analytics.widgets.update');
            Route::delete('/widgets/{id}', [AnalyticsController::class, 'destroyWidget'])->name('api.analytics.widgets.destroy');
        });

        // Notifications (الإشعارات)
        Route::prefix('notifications')->group(function () {
            Route::get('/', [NotificationController::class, 'index'])->name('api.notifications.index');
            Route::get('/{id}', [NotificationController::class, 'show'])->name('api.notifications.show');
            Route::post('/{id}/read', [NotificationController::class, 'markAsRead'])->name('api.notifications.read');
            Route::post('/read-all', [NotificationController::class, 'markAllAsRead'])->name('api.notifications.read-all');
            Route::get('/unread-count', [NotificationController::class, 'getUnreadCount'])->name('api.notifications.unread-count');
            Route::delete('/{id}', [NotificationController::class, 'destroy'])->name('api.notifications.destroy');
            Route::get('/preferences', [NotificationController::class, 'getPreferences'])->name('api.notifications.preferences');
            Route::put('/preferences', [NotificationController::class, 'updatePreferences'])->name('api.notifications.update-preferences');
            Route::get('/templates', [NotificationController::class, 'indexTemplates'])->name('api.notifications.templates.index');
            Route::get('/templates/{id}', [NotificationController::class, 'showTemplate'])->name('api.notifications.templates.show');
            Route::post('/templates', [NotificationController::class, 'storeTemplate'])->name('api.notifications.templates.store');
            Route::put('/templates/{id}', [NotificationController::class, 'updateTemplate'])->name('api.notifications.templates.update');
            Route::delete('/templates/{id}', [NotificationController::class, 'destroyTemplate'])->name('api.notifications.templates.destroy');
            Route::post('/send', [NotificationController::class, 'sendNotification'])->name('api.notifications.send');
            Route::post('/send-bulk', [NotificationController::class, 'sendBulkNotification'])->name('api.notifications.send-bulk');
        });

        // Workflows (سير العمل)
        Route::prefix('workflows')->group(function () {
            Route::get('/', [WorkflowController::class, 'index'])->name('api.workflows.index');
            Route::get('/{id}', [WorkflowController::class, 'show'])->name('api.workflows.show');
            Route::post('/', [WorkflowController::class, 'store'])->name('api.workflows.store');
            Route::put('/{id}', [WorkflowController::class, 'update'])->name('api.workflows.update');
            Route::delete('/{id}', [WorkflowController::class, 'destroy'])->name('api.workflows.destroy');
            Route::post('/{id}/execute', [WorkflowController::class, 'execute'])->name('api.workflows.execute');
            Route::get('/{id}/executions', [WorkflowController::class, 'getExecutions'])->name('api.workflows.executions');
            Route::get('/{id}/statistics', [WorkflowController::class, 'getStatistics'])->name('api.workflows.statistics');
            Route::post('/executions/{executionId}/cancel', [WorkflowController::class, 'cancelExecution'])->name('api.workflows.executions.cancel');
            Route::post('/executions/{executionId}/retry', [WorkflowController::class, 'retryExecution'])->name('api.workflows.executions.retry');
            Route::get('/{workflowId}/steps', [WorkflowController::class, 'indexSteps'])->name('api.workflows.steps.index');
            Route::post('/{workflowId}/steps', [WorkflowController::class, 'storeStep'])->name('api.workflows.steps.store');
            Route::put('/steps/{id}', [WorkflowController::class, 'updateStep'])->name('api.workflows.steps.update');
            Route::delete('/steps/{id}', [WorkflowController::class, 'destroyStep'])->name('api.workflows.steps.destroy');
            Route::post('/{workflowId}/steps/reorder', [WorkflowController::class, 'reorderSteps'])->name('api.workflows.steps.reorder');
        });

        // Audit Logs (سجلات التدقيق)
        Route::prefix('audit')->group(function () {
            Route::get('/', [AuditController::class, 'index'])->name('api.audit.index');
            Route::get('/{id}', [AuditController::class, 'show'])->name('api.audit.show');
            Route::get('/entity-logs', [AuditController::class, 'getEntityLogs'])->name('api.audit.entity-logs');
            Route::get('/user-logs/{userId}', [AuditController::class, 'getUserLogs'])->name('api.audit.user-logs');
            Route::get('/module-logs/{module}', [AuditController::class, 'getModuleLogs'])->name('api.audit.module-logs');
            Route::get('/recent', [AuditController::class, 'getRecentLogs'])->name('api.audit.recent');
            Route::get('/today', [AuditController::class, 'getTodayLogs'])->name('api.audit.today');
            Route::get('/statistics', [AuditController::class, 'getStatistics'])->name('api.audit.statistics');
            Route::get('/activity-timeline', [AuditController::class, 'getActivityTimeline'])->name('api.audit.timeline');
            Route::get('/user-summary/{userId}', [AuditController::class, 'getUserActivitySummary'])->name('api.audit.user-summary');
            Route::get('/my-summary', [AuditController::class, 'getMyActivitySummary'])->name('api.audit.my-summary');
            Route::post('/cleanup', [AuditController::class, 'cleanupOldLogs'])->name('api.audit.cleanup');
        });

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
