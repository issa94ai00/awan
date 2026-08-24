<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\CategoryController;
use App\Http\Controllers\Api\CompanyController;
use App\Http\Controllers\Api\CurrencyController;
use App\Http\Controllers\Api\ProductController;
use App\Http\Controllers\Api\ProductUnitController;
use App\Http\Controllers\Api\ProductWarehouseAssignmentController;
use App\Http\Controllers\Api\MrpController;
use App\Http\Controllers\Api\CompositeProductController;
use App\Http\Controllers\Api\ErpUpgradeController;
use App\Http\Controllers\Api\SearchController;
use App\Http\Controllers\Api\AdminSearchController;
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
use App\Http\Controllers\Api\OrderAllocationController;
use App\Http\Controllers\Api\QuoteController;
use App\Http\Controllers\Api\SalesOrderController;
use App\Http\Controllers\Api\SalesReportController;
use App\Http\Controllers\Api\Field\FieldInventoryController;
use App\Http\Controllers\Api\Field\FieldOrderController;
use App\Http\Controllers\Api\Field\FieldReplenishmentController;
use App\Http\Controllers\Api\Field\FieldSessionController;
use App\Http\Controllers\Api\PaymentController;
use App\Http\Controllers\Api\PurchaseReceiptController;
use App\Http\Controllers\Api\PayrollController;
use App\Http\Controllers\Api\EmployeeController;
use App\Http\Controllers\Api\AttendanceController;
use App\Http\Controllers\Api\LeaveRequestController;use App\Http\Controllers\Api\TicketController;use App\Http\Controllers\Api\UploadController;
use App\Http\Controllers\Api\SupplierController;
use App\Http\Controllers\Api\PurchaseReturnController;
use App\Http\Controllers\Api\SupplierPaymentController;
use App\Http\Controllers\Api\PurchaseOrderController;
use App\Http\Controllers\Api\PurchaseReportController;
use App\Http\Controllers\Api\StockMovementController;
use App\Http\Controllers\Api\InventoryController;
use App\Http\Controllers\Api\LedgerAccountController;
use App\Http\Controllers\Api\JournalEntryController;
use App\Http\Controllers\Api\AccountingReportController;
use App\Http\Controllers\Api\AccountingPeriodController;
use App\Http\Controllers\Api\BankReconciliationController;
use App\Http\Controllers\Api\BudgetController;
use App\Http\Controllers\Api\CostCenterController;
use App\Http\Controllers\Api\FixedAssetController;
use App\Http\Controllers\Api\SettingsController;
use App\Http\Controllers\Api\SpecialOfferController;
use App\Http\Controllers\Api\InventoryTransferController;
use App\Http\Controllers\Api\EnhancedInventoryController;
use App\Http\Controllers\Api\EnhancedSalesOrderController;
use App\Http\Controllers\Api\CreditNoteController;
use App\Http\Controllers\Api\CustomerOverviewController;
use App\Http\Controllers\Api\RmaController;
use App\Http\Controllers\Api\WmsController;
use App\Http\Controllers\Api\AnalyticsController;
use App\Http\Controllers\Api\NotificationController;
use App\Http\Controllers\Api\WorkflowController;
use App\Http\Controllers\Api\AuditController;
use App\Http\Controllers\ExpenseController;
use App\Http\Controllers\Api\FlutterCartController;
use App\Http\Controllers\Api\StaffCustomerController;
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
Route::prefix('v1')->middleware('web')->group(function () {
    
    // Home & Featured Content
    Route::get('/home', [HomeController::class, 'index'])->name('api.home');
    Route::get('/featured-products', [HomeController::class, 'featuredProducts'])->name('api.featured-products');
    Route::get('/special-offers', [SpecialOfferController::class, 'activeOffers'])->name('api.special-offers.active');
    Route::get('/settings', [SettingsController::class, 'index'])->name('api.settings.public');
    // The currencies a shopper may view prices in, with their current rates.
    // Public: the storefront and the mobile app both read it before login.
    Route::get('/currencies', [CurrencyController::class, 'index'])->name('api.currencies.public');

    // Cart API (with Session / Web Middleware)
    Route::middleware('web')->group(function () {
        Route::get('/cart/data', [\App\Http\Controllers\CartController::class, 'getCartData'])->name('api.cart.data');
        Route::post('/cart/add', [\App\Http\Controllers\CartController::class, 'add'])->name('api.cart.add');
        Route::post('/cart/update/{id}', [\App\Http\Controllers\CartController::class, 'update'])->name('api.cart.update');
        Route::post('/cart/remove/{id}', [\App\Http\Controllers\CartController::class, 'remove'])->name('api.cart.remove');
        Route::post('/cart/clear', [\App\Http\Controllers\CartController::class, 'clear'])->name('api.cart.clear');
        Route::get('/cart/count', [\App\Http\Controllers\CartController::class, 'getCartCount'])->name('api.cart.count');
    });

    // Sales, inventory and invoice reporting lives under the authenticated
    // admin prefix only (see `api.admin.reports.*` below): the payloads carry
    // customer names, margins and full CSV exports, so there is no public alias.

    // Flutter Cart API (Token-based for mobile app)
    Route::middleware('auth:sanctum')->group(function () {
        Route::get('/flutter/cart', [FlutterCartController::class, 'index'])->name('api.flutter.cart.index');
        Route::post('/flutter/cart/add', [FlutterCartController::class, 'add'])->name('api.flutter.cart.add');
        Route::post('/flutter/cart/update', [FlutterCartController::class, 'update'])->name('api.flutter.cart.update');
        Route::post('/flutter/cart/remove', [FlutterCartController::class, 'remove'])->name('api.flutter.cart.remove');
        Route::post('/flutter/cart/clear', [FlutterCartController::class, 'clear'])->name('api.flutter.cart.clear');
    });

    // Staff Customer API (Token-based for staff to manage their customers)
    Route::middleware('auth:sanctum')->prefix('staff/customers')->group(function () {
        Route::get('/', [StaffCustomerController::class, 'index'])->name('api.staff.customers.index');
        Route::post('/', [StaffCustomerController::class, 'store'])->name('api.staff.customers.store');
        Route::get('/{id}', [StaffCustomerController::class, 'show'])->name('api.staff.customers.show');
        Route::put('/{id}', [StaffCustomerController::class, 'update'])->name('api.staff.customers.update');
    });
    
    // Admin WMS API (using web middleware for session auth)
    // The admin SPA authenticates with a Sanctum bearer token, so this group has
    // to use the sanctum guard. Plain `auth` resolves to the default `web`
    // (session) guard, which no token request can satisfy — every WMS call came
    // back 401, and the client's 401 handler reacted by clearing the token and
    // bouncing the user to the login page the moment they opened the module.
    Route::prefix('wms')->middleware('auth:sanctum')->group(function () {
        // Dashboard (لوحة التحكم)
        Route::get('/dashboard', [WmsController::class, 'dashboard'])->name('api.admin.wms.dashboard');

        // Products (المنتجات)
        Route::get('/products', [WmsController::class, 'indexProducts'])->name('api.admin.wms.products.index');

        // Assignments (الربط)
        Route::get('/assignments', [WmsController::class, 'indexAssignments'])->name('api.admin.wms.assignments.index');
        Route::post('/assignments', [WmsController::class, 'storeAssignment'])->name('api.admin.wms.assignments.store');
        Route::get('/assignments/{id}', [WmsController::class, 'showAssignment'])->name('api.admin.wms.assignments.show');
        Route::put('/assignments/{id}', [WmsController::class, 'updateAssignment'])->name('api.admin.wms.assignments.update');
        Route::delete('/assignments/{id}', [WmsController::class, 'destroyAssignment'])->name('api.admin.wms.assignments.destroy');
        Route::get('/suggest-stock-levels', [WmsController::class, 'suggestStockLevels'])->name('api.admin.wms.suggest-stock-levels');

        // Stock (المخزون)
        Route::get('/stock/balance', [WmsController::class, 'getStockBalance'])->name('api.admin.wms.stock.balance');
        Route::get('/stock/transactions', [WmsController::class, 'getStockTransactions'])->name('api.admin.wms.stock.transactions');
        Route::post('/stock/movements', [WmsController::class, 'createStockMovement'])->name('api.admin.wms.stock.movements.create');

        // Stats (إحصائيات)
        Route::get('/stats', [WmsController::class, 'getWmsStats'])->name('api.admin.wms.stats');

        // Warehouses (المستودعات)
        Route::get('/warehouses', [WmsController::class, 'indexWarehouses'])->name('api.admin.wms.warehouses.index');
        Route::get('/warehouses/{id}', [WmsController::class, 'showWarehouse'])->name('api.admin.wms.warehouses.show');
        Route::post('/warehouses', [WmsController::class, 'storeWarehouse'])->name('api.admin.wms.warehouses.store');
        Route::put('/warehouses/{id}', [WmsController::class, 'updateWarehouse'])->name('api.admin.wms.warehouses.update');
        Route::delete('/warehouses/{id}', [WmsController::class, 'destroyWarehouse'])->name('api.admin.wms.warehouses.destroy');

        // Warehouse Bins (أماكن التخزين)
        Route::get('/bins', [WmsController::class, 'indexBins'])->name('api.admin.wms.bins.index');
        Route::get('/bins/{id}', [WmsController::class, 'showBin'])->name('api.admin.wms.bins.show');
        Route::post('/bins', [WmsController::class, 'storeBin'])->name('api.admin.wms.bins.store');
        Route::put('/bins/{id}', [WmsController::class, 'updateBin'])->name('api.admin.wms.bins.update');
        Route::delete('/bins/{id}', [WmsController::class, 'destroyBin'])->name('api.admin.wms.bins.destroy');

        // Picking Lists (قوائم الاختيار)
        Route::get('/picking-lists', [WmsController::class, 'indexPickingLists'])->name('api.admin.wms.picking.index');
        Route::get('/picking-lists/{id}', [WmsController::class, 'showPickingList'])->name('api.admin.wms.picking.show');
        Route::post('/picking-lists', [WmsController::class, 'createPickingList'])->name('api.admin.wms.picking.create');
        Route::post('/picking-lists/{id}/start', [WmsController::class, 'startPicking'])->name('api.admin.wms.picking.start');
        Route::post('/picking-items/{itemId}', [WmsController::class, 'pickItem'])->name('api.admin.wms.picking.pick');
        Route::post('/picking-lists/{id}/complete', [WmsController::class, 'completePicking'])->name('api.admin.wms.picking.complete');
        Route::post('/picking-lists/{id}/cancel', [WmsController::class, 'cancelPicking'])->name('api.admin.wms.picking.cancel');
        Route::get('/picking/statistics', [WmsController::class, 'getPickingStatistics'])->name('api.admin.wms.picking.statistics');
    });
    
    // Admin Products API. Same as the WMS group above: the SPA sends a Sanctum
    // bearer token, so a bare `auth` (session guard) rejects every request and
    // the client's 401 handler logs the user out.
    Route::prefix('admin')->middleware('auth:sanctum')->group(function () {
        Route::get('/products', [ProductController::class, 'index'])->name('api.admin.products.index');
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

    // Sales Employees (public - for registration forms)
    Route::get('/sales-employees', [EmployeeController::class, 'salesEmployees'])->name('api.sales-employees');

    // Warehouse split suggestions. Public so the storefront cart can plan its
    // order against live per-warehouse availability without a staff session.
    Route::post('/order-allocations/suggest', [OrderAllocationController::class, 'suggest'])->name('api.order-allocations.suggest');
    
    // Authentication
    Route::post('/auth/register', [AuthController::class, 'register'])->name('api.auth.register');
    Route::post('/auth/login', [AuthController::class, 'login'])->name('api.auth.login');
    Route::post('/auth/logout', [AuthController::class, 'logout'])->middleware('auth:sanctum')->name('api.auth.logout');
    Route::get('/auth/user', [AuthController::class, 'user'])->middleware('auth:sanctum')->name('api.auth.user');
    Route::put('/auth/profile', [AuthController::class, 'updateProfile'])->middleware('auth:sanctum')->name('api.auth.profile');
    Route::post('/auth/change-password', [AuthController::class, 'changePassword'])->middleware('auth:sanctum')->name('api.auth.change-password');
    
    // Protected Routes (require authentication)
    Route::middleware('auth:sanctum')->group(function () {

        // Role-scoped admin areas
        Route::middleware('role:sells')->prefix('sales')->group(function () {
            Route::get('/dashboard', fn () => response()->json(['ok' => true, 'module' => 'sales']));
            Route::get('/orders', fn () => response()->json(['ok' => true, 'module' => 'sales-orders']));
        });

        Route::middleware('role:accountant')->prefix('accounting')->group(function () {
            Route::get('/dashboard', fn () => response()->json(['ok' => true, 'module' => 'accounting']));
            Route::get('/reports', fn () => response()->json(['ok' => true, 'module' => 'financial-reports']));
        });

        Route::middleware('role:marketer')->prefix('marketing')->group(function () {
            Route::get('/dashboard', fn () => response()->json(['ok' => true, 'module' => 'marketing']));
            Route::get('/campaigns', fn () => response()->json(['ok' => true, 'module' => 'campaigns']));
        });

        // File Upload
        Route::post('/upload', [UploadController::class, 'upload'])->name('api.upload');
        Route::delete('/upload', [UploadController::class, 'delete'])->name('api.upload.delete');

        /*
         * Field app (Flutter).
         *
         * Every endpoint is scoped to the warehouses the signed-in employee may
         * act on — see App\Services\Field\FieldScope. Orders raised here are
         * ordinary sales orders and run through SalesOrderWorkflowService, so a
         * confirmation from a phone reserves stock, raises the invoice and posts
         * the ledger entries exactly as one from the back office does.
         */
        Route::prefix('field')->group(function () {
            Route::get('/me', [FieldSessionController::class, 'me'])->name('api.field.me');

            Route::get('/inventory', [FieldInventoryController::class, 'index'])->name('api.field.inventory');
            Route::get('/inventory/movements', [FieldInventoryController::class, 'movements'])->name('api.field.inventory.movements');
            Route::get('/inventory/products/{product}', [FieldInventoryController::class, 'product'])->whereNumber('product')->name('api.field.inventory.product');

            // Stock the branch asks the main warehouse for. Not a sale — goods
            // move between the company's own locations — so it runs on
            // inventory_transfers, the same queue the warehouse ships against.
            Route::get('/replenishment', [FieldReplenishmentController::class, 'index'])->name('api.field.replenishment.index');
            // What the branch is running out of, with what the main warehouse
            // could send. Declared before `{id}`, which is numeric-only anyway.
            Route::get('/replenishment/suggestions', [FieldReplenishmentController::class, 'suggestions'])->name('api.field.replenishment.suggestions');
            Route::post('/replenishment', [FieldReplenishmentController::class, 'store'])->name('api.field.replenishment.store');
            Route::get('/replenishment/{id}', [FieldReplenishmentController::class, 'show'])->whereNumber('id')->name('api.field.replenishment.show');
            // Approval belongs to the warehouse being asked; receipt to the one
            // that asked. Cancelling is open to either while the goods have not
            // moved. FieldReplenishmentController enforces which is which.
            Route::post('/replenishment/{id}/approve', [FieldReplenishmentController::class, 'approve'])->whereNumber('id')->name('api.field.replenishment.approve');
            Route::post('/replenishment/{id}/receive', [FieldReplenishmentController::class, 'receive'])->whereNumber('id')->name('api.field.replenishment.receive');
            Route::post('/replenishment/{id}/cancel', [FieldReplenishmentController::class, 'cancel'])->whereNumber('id')->name('api.field.replenishment.cancel');

            Route::get('/orders', [FieldOrderController::class, 'index'])->name('api.field.orders.index');
            // Where the goods would come from, before anything is created. Read
            // only — it holds no stock, so an abandoned draft costs nothing.
            // Declared before the `{salesOrder}` routes cannot shadow it, since
            // those are constrained to numbers.
            Route::post('/orders/preview', [FieldOrderController::class, 'preview'])->name('api.field.orders.preview');
            Route::post('/orders', [FieldOrderController::class, 'store'])->name('api.field.orders.store');
            Route::get('/orders/{salesOrder}', [FieldOrderController::class, 'show'])->whereNumber('salesOrder')->name('api.field.orders.show');
            Route::post('/orders/{salesOrder}/transition', [FieldOrderController::class, 'transition'])->whereNumber('salesOrder')->name('api.field.orders.transition');
        });

        // Admin Products API (using Sanctum for API clients)
        Route::prefix('admin')->group(function () {
            Route::get('/products', [ProductController::class, 'index'])->name('api.admin.products.index');
            Route::get('/products/export', [ProductController::class, 'export'])->name('api.admin.products.export');
            Route::post('/products/import', [ProductController::class, 'import'])->name('api.admin.products.import');
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

            // Product-Warehouse Assignment API
            Route::get('/product-warehouse-assignments', [ProductWarehouseAssignmentController::class, 'index'])->name('api.admin.product-warehouse-assignments.index');
            Route::post('/product-warehouse-assignments', [ProductWarehouseAssignmentController::class, 'store'])->name('api.admin.product-warehouse-assignments.store');
            Route::get('/product-warehouse-assignments/{id}', [ProductWarehouseAssignmentController::class, 'show'])->name('api.admin.product-warehouse-assignments.show');
            Route::put('/product-warehouse-assignments/{id}', [ProductWarehouseAssignmentController::class, 'update'])->name('api.admin.product-warehouse-assignments.update');
            Route::delete('/product-warehouse-assignments/{id}', [ProductWarehouseAssignmentController::class, 'destroy'])->name('api.admin.product-warehouse-assignments.destroy');
            Route::get('/product-warehouse-assignments/products/{productId}/stock-balance', [ProductWarehouseAssignmentController::class, 'getRealTimeStockBalance'])->name('api.admin.product-warehouse-assignments.stock-balance');
            Route::post('/product-warehouse-assignments/update-stock-balance', [ProductWarehouseAssignmentController::class, 'updateStockBalance'])->name('api.admin.product-warehouse-assignments.update-stock');
            Route::get('/product-warehouse-assignments/reorder-alerts', [ProductWarehouseAssignmentController::class, 'getReorderAlerts'])->name('api.admin.product-warehouse-assignments.reorder-alerts');
            Route::get('/product-warehouse-assignments/recommended-warehouse', [ProductWarehouseAssignmentController::class, 'getRecommendedWarehouse'])->name('api.admin.product-warehouse-assignments.recommended-warehouse');

            // MRP (Material Requirements Planning) API
            Route::get('/mrp/products/{productId}', [MrpController::class, 'runForProduct'])->name('api.admin.mrp.product');
            Route::get('/mrp/warehouses/{warehouseId}', [MrpController::class, 'runForWarehouse'])->name('api.admin.mrp.warehouse');
            Route::get('/mrp/run-all', [MrpController::class, 'runForAll'])->name('api.admin.mrp.run-all');
            Route::post('/mrp/assignments/{assignmentId}/execute', [MrpController::class, 'executeRecommendations'])->name('api.admin.mrp.execute');
            Route::get('/mrp/summary', [MrpController::class, 'getSummary'])->name('api.admin.mrp.summary');

            // Picking lives entirely under /picking-lists (WmsController, backed by
            // App\Services\PickingService). The old /picking/* routes here called
            // App\Services\Inventory\PickingService, whose confirmPicking() wrote
            // straight to warehouse_inventory — bypassing InventoryService, FIFO
            // costing and the ledger entirely, and consuming the same reservation
            // applyShipment() consumes. Nothing in the frontend called them; the
            // controller and service behind them have been removed rather than
            // left as a live route two systems could disagree over.

            // Composite Product API
            Route::get('/composite-products/{productId}/is-composite', [CompositeProductController::class, 'isComposite'])->name('api.admin.composite.is-composite');
            Route::get('/composite-products/{productId}/components', [CompositeProductController::class, 'getComponents'])->name('api.admin.composite.components');
            Route::post('/composite-products/{productId}/can-assemble', [CompositeProductController::class, 'canAssemble'])->name('api.admin.composite.can-assemble');
            Route::get('/composite-products/{productId}/best-warehouse', [CompositeProductController::class, 'getBestWarehouseForAssembly'])->name('api.admin.composite.best-warehouse');
            Route::post('/composite-products/assembly-orders', [CompositeProductController::class, 'createAssemblyOrder'])->name('api.admin.composite.create-assembly');
            Route::post('/composite-products/assembly-orders/{assemblyOrderId}/complete', [CompositeProductController::class, 'completeAssemblyOrder'])->name('api.admin.composite.complete-assembly');
            Route::post('/composite-products/disassemble', [CompositeProductController::class, 'disassemble'])->name('api.admin.composite.disassemble');
            Route::get('/composite-products/{productId}/bom', [CompositeProductController::class, 'getBillOfMaterials'])->name('api.admin.composite.bom');
            Route::get('/composite-products/{productId}/cost', [CompositeProductController::class, 'calculateCost'])->name('api.admin.composite.cost');
            Route::put('/composite-products/{productId}/components', [CompositeProductController::class, 'updateComponents'])->name('api.admin.composite.update-components');
            Route::get('/composite-products/assembly-orders', [CompositeProductController::class, 'getAssemblyOrders'])->name('api.admin.composite.assembly-orders');
            Route::get('/composite-products/assembly-orders/{id}', [CompositeProductController::class, 'getAssemblyOrder'])->name('api.admin.composite.assembly-order');

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
            Route::get('/categories/{category:id}', [CategoryController::class, 'show'])->name('api.admin.categories.show');
            Route::post('/categories', [CategoryController::class, 'store'])->name('api.admin.categories.store');
            Route::put('/categories/{category:id}', [CategoryController::class, 'update'])->name('api.admin.categories.update');
            Route::delete('/categories/{category:id}', [CategoryController::class, 'destroy'])->name('api.admin.categories.destroy');

            // Admin Suppliers — purchasing is an admin group in the sidebar,
            // and supplier records carry the prices the business buys at.
            Route::middleware('role:admin')->group(function () {
                Route::get('/suppliers', [SupplierController::class, 'index'])->name('api.admin.suppliers.index');
                Route::post('/suppliers', [SupplierController::class, 'store'])->name('api.admin.suppliers.store');
                Route::get('/suppliers/{supplier}', [SupplierController::class, 'show'])->name('api.admin.suppliers.show');
                Route::put('/suppliers/{supplier}', [SupplierController::class, 'update'])->name('api.admin.suppliers.update');
                Route::delete('/suppliers/{supplier}', [SupplierController::class, 'destroy'])->name('api.admin.suppliers.destroy');

                // Paying suppliers. Same audience as the rest of purchasing:
                // this moves money out and settles the payables account, so it
                // sits behind the admin role rather than with the staff-wide
                // receipt endpoints below.
                // Returning goods to a supplier: stock leaves, the payable
                // drops, and the tax on the returned portion is given back.
                Route::get('/purchase-returns', [PurchaseReturnController::class, 'index'])->name('api.admin.purchase-returns.index');
                Route::post('/purchase-returns', [PurchaseReturnController::class, 'store'])->name('api.admin.purchase-returns.store');
                Route::get('/purchase-returns/{purchaseReturn}', [PurchaseReturnController::class, 'show'])->name('api.admin.purchase-returns.show');
                Route::delete('/purchase-returns/{purchaseReturn}', [PurchaseReturnController::class, 'destroy'])->name('api.admin.purchase-returns.destroy');

                Route::get('/supplier-payments', [SupplierPaymentController::class, 'index'])->name('api.admin.supplier-payments.index');
                Route::post('/supplier-payments', [SupplierPaymentController::class, 'store'])->name('api.admin.supplier-payments.store');
                Route::get('/supplier-payments/outstanding', [SupplierPaymentController::class, 'outstanding'])->name('api.admin.supplier-payments.outstanding');
                Route::get('/supplier-payments/{supplierPayment}', [SupplierPaymentController::class, 'show'])->name('api.admin.supplier-payments.show');
                Route::delete('/supplier-payments/{supplierPayment}', [SupplierPaymentController::class, 'destroy'])->name('api.admin.supplier-payments.destroy');
            });

            // Admin Employees API
            // Staff records — admin only. They carry salary and contact details
            // and decide which warehouse a login is scoped to, so editing one is
            // a way to widen your own access.
            Route::middleware('role:admin')->group(function () {
                Route::get('/employees', [EmployeeController::class, 'index'])->name('api.admin.employees.index');
                Route::post('/employees', [EmployeeController::class, 'store'])->name('api.admin.employees.store');
                Route::get('/employees/{employee}', [EmployeeController::class, 'show'])->name('api.admin.employees.show');
                Route::put('/employees/{employee}', [EmployeeController::class, 'update'])->name('api.admin.employees.update');
                Route::delete('/employees/{employee}', [EmployeeController::class, 'destroy'])->name('api.admin.employees.destroy');
            });

            // Employee Customers API
            Route::get('/employees/{employee}/customers', [EmployeeController::class, 'customers'])->name('api.admin.employees.customers');
            Route::post('/employees/{employee}/customers/attach', [EmployeeController::class, 'attachCustomers'])->name('api.admin.employees.customers.attach');
            Route::post('/employees/{employee}/customers/detach', [EmployeeController::class, 'detachCustomers'])->name('api.admin.employees.customers.detach');

            // Sales Reports API
            Route::get('/reports/sales', [SalesReportController::class, 'salesReport'])->name('api.admin.reports.sales');
            Route::get('/reports/sales/summary', [SalesReportController::class, 'salesSummary'])->name('api.admin.reports.sales.summary');
            Route::get('/reports/sales/dimensions', [SalesReportController::class, 'salesDimensions'])->name('api.admin.reports.sales.dimensions');
            Route::get('/reports/sales/performance', [SalesReportController::class, 'salesPerformance'])->name('api.admin.reports.sales.performance');
            Route::get('/reports/sales/product-profitability', [SalesReportController::class, 'productProfitability'])->name('api.admin.reports.sales.product-profitability');
            Route::get('/reports/sales/top-performers', [SalesReportController::class, 'topPerformers'])->name('api.admin.reports.sales.top-performers');
            Route::get('/reports/sales/export', [SalesReportController::class, 'export'])->name('api.admin.reports.sales.export');
            Route::get('/reports/inventory/dimensions', [SalesReportController::class, 'inventoryDimensions'])->name('api.admin.reports.inventory.dimensions');
            Route::get('/reports/inventory/export', [SalesReportController::class, 'inventoryExport'])->name('api.admin.reports.inventory.export');
            Route::get('/reports/invoices', [SalesReportController::class, 'invoiceReport'])->name('api.admin.reports.invoices.index');
            Route::get('/reports/invoices/dimensions', [SalesReportController::class, 'invoiceDimensions'])->name('api.admin.reports.invoices.dimensions');
            Route::get('/reports/invoices/performance', [SalesReportController::class, 'invoicePerformance'])->name('api.admin.reports.invoices.performance');
            Route::get('/reports/invoices/product-profitability', [SalesReportController::class, 'invoiceProductProfitability'])->name('api.admin.reports.invoices.product-profitability');
            Route::get('/reports/invoices/top-performers', [SalesReportController::class, 'invoiceTopPerformers'])->name('api.admin.reports.invoices.top-performers');
            Route::get('/reports/invoices/export', [SalesReportController::class, 'invoiceExport'])->name('api.admin.reports.invoices.export');

            // Purchase Reports API
            Route::get('/reports/purchases', [PurchaseReportController::class, 'purchaseReport'])->name('api.admin.reports.purchases');
            Route::get('/reports/purchases/summary', [PurchaseReportController::class, 'purchaseSummary'])->name('api.admin.reports.purchases.summary');
            Route::get('/reports/purchases/dimensions', [PurchaseReportController::class, 'purchaseDimensions'])->name('api.admin.reports.purchases.dimensions');
            Route::get('/reports/purchases/performance', [PurchaseReportController::class, 'purchasePerformance'])->name('api.admin.reports.purchases.performance');
            Route::get('/reports/purchases/product-spend', [PurchaseReportController::class, 'productSpend'])->name('api.admin.reports.purchases.product-spend');
            Route::get('/reports/purchases/top-suppliers', [PurchaseReportController::class, 'topSuppliers'])->name('api.admin.reports.purchases.top-suppliers');
            Route::get('/reports/purchases/export', [PurchaseReportController::class, 'export'])->name('api.admin.reports.purchases.export');

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

            Route::middleware('role:admin')->group(function () {
                Route::get('/purchase-orders', [PurchaseOrderController::class, 'index'])->name('api.admin.purchase-orders.index');
                Route::post('/purchase-orders', [PurchaseOrderController::class, 'store'])->name('api.admin.purchase-orders.store');
                Route::get('/purchase-orders/{order}', [PurchaseOrderController::class, 'show'])->name('api.admin.purchase-orders.show');
                Route::put('/purchase-orders/{order}', [PurchaseOrderController::class, 'update'])->name('api.admin.purchase-orders.update');
                Route::delete('/purchase-orders/{order}', [PurchaseOrderController::class, 'destroy'])->name('api.admin.purchase-orders.destroy');
            });

            // Admin Inventory Movements
            Route::get('/inventory/movements', [StockMovementController::class, 'index'])->name('api.admin.inventory.movements.index');
            Route::post('/inventory/movements', [StockMovementController::class, 'store'])->name('api.admin.inventory.movements.store');
            Route::post('/inventory/transfer', [StockMovementController::class, 'transfer'])->name('api.admin.inventory.transfer');

            // Admin Inventory Overview (single source of truth for the screens)
            Route::get('/inventory/summary', [InventoryController::class, 'summary'])->name('api.admin.inventory.summary');
            Route::get('/inventory/stock', [InventoryController::class, 'stock'])->name('api.admin.inventory.stock');
            Route::get('/inventory/export', [InventoryController::class, 'export'])->name('api.admin.inventory.export');
            Route::post('/inventory/import', [InventoryController::class, 'import'])->name('api.admin.inventory.import');

            // Admin Accounting
            //
            // Behind the accountant role. Being signed in used to be the whole
            // check, so any staff account could read the trial balance, the
            // ledger and every journal entry — and delete them. `RequireRole`
            // lets an admin through regardless, so this narrows the audience
            // without locking the owner out of their own books.
            Route::middleware('role:accountant')->group(function () {
                Route::get('/accounting/ledger-accounts', [LedgerAccountController::class, 'index'])->name('api.admin.accounting.ledger-accounts.index');
                Route::post('/accounting/ledger-accounts', [LedgerAccountController::class, 'store'])->name('api.admin.accounting.ledger-accounts.store');
                Route::get('/accounting/ledger-accounts/{ledgerAccount}', [LedgerAccountController::class, 'show'])->name('api.admin.accounting.ledger-accounts.show');
                Route::put('/accounting/ledger-accounts/{ledgerAccount}', [LedgerAccountController::class, 'update'])->name('api.admin.accounting.ledger-accounts.update');
                Route::delete('/accounting/ledger-accounts/{ledgerAccount}', [LedgerAccountController::class, 'destroy'])->name('api.admin.accounting.ledger-accounts.destroy');

                Route::get('/accounting/journal-entries', [JournalEntryController::class, 'index'])->name('api.admin.accounting.journal-entries.index');
                Route::post('/accounting/journal-entries', [JournalEntryController::class, 'store'])->name('api.admin.accounting.journal-entries.store');
                Route::get('/accounting/journal-entries/{journalEntry}', [JournalEntryController::class, 'show'])->name('api.admin.accounting.journal-entries.show');
                // Both refuse: a posted entry is corrected by reversing it, not
                // by rewriting or removing it. Kept registered so the refusal
                // is an explanation rather than a 404.
                Route::put('/accounting/journal-entries/{journalEntry}', [JournalEntryController::class, 'update'])->name('api.admin.accounting.journal-entries.update');
                Route::delete('/accounting/journal-entries/{journalEntry}', [JournalEntryController::class, 'destroy'])->name('api.admin.accounting.journal-entries.destroy');
                Route::post('/accounting/journal-entries/{journalEntry}/reverse', [JournalEntryController::class, 'reverse'])->name('api.admin.accounting.journal-entries.reverse');

                // The register of things bought to keep. Their cost belongs to
                // the periods that use them, not to the month they were paid.
                Route::get('/accounting/fixed-assets', [FixedAssetController::class, 'index'])->name('api.admin.accounting.fixed-assets.index');
                Route::post('/accounting/fixed-assets', [FixedAssetController::class, 'store'])->name('api.admin.accounting.fixed-assets.store');
                Route::get('/accounting/fixed-assets/{fixedAsset}', [FixedAssetController::class, 'show'])->name('api.admin.accounting.fixed-assets.show');
                Route::post('/accounting/fixed-assets/{fixedAsset}/dispose', [FixedAssetController::class, 'dispose'])->name('api.admin.accounting.fixed-assets.dispose');
                Route::delete('/accounting/fixed-assets/{fixedAsset}', [FixedAssetController::class, 'destroy'])->name('api.admin.accounting.fixed-assets.destroy');

                // The parts of the business a figure can belong to.
                Route::get('/accounting/cost-centers', [CostCenterController::class, 'index'])->name('api.admin.accounting.cost-centers.index');
                Route::post('/accounting/cost-centers', [CostCenterController::class, 'store'])->name('api.admin.accounting.cost-centers.store');
                Route::put('/accounting/cost-centers/{costCenter}', [CostCenterController::class, 'update'])->name('api.admin.accounting.cost-centers.update');
                Route::delete('/accounting/cost-centers/{costCenter}', [CostCenterController::class, 'destroy'])->name('api.admin.accounting.cost-centers.destroy');

                // What the year was meant to earn and spend — the only thing
                // that turns a figure into a verdict.
                Route::get('/accounting/budgets', [BudgetController::class, 'index'])->name('api.admin.accounting.budgets.index');
                Route::post('/accounting/budgets', [BudgetController::class, 'store'])->name('api.admin.accounting.budgets.store');
                Route::get('/accounting/budgets/{budget}', [BudgetController::class, 'show'])->name('api.admin.accounting.budgets.show');
                Route::post('/accounting/budgets/{budget}/lines', [BudgetController::class, 'setLines'])->name('api.admin.accounting.budgets.lines');
                Route::get('/accounting/budgets/{budget}/variance', [BudgetController::class, 'variance'])->name('api.admin.accounting.budgets.variance');
                Route::post('/accounting/budgets/{budget}/approve', [BudgetController::class, 'approve'])->name('api.admin.accounting.budgets.approve');
                Route::delete('/accounting/budgets/{budget}', [BudgetController::class, 'destroy'])->name('api.admin.accounting.budgets.destroy');

                // Holding the bank account against the bank's own statement —
                // the only balance in the books with an outside witness.
                Route::get('/accounting/bank-reconciliations', [BankReconciliationController::class, 'index'])->name('api.admin.accounting.bank-reconciliations.index');
                Route::post('/accounting/bank-reconciliations', [BankReconciliationController::class, 'store'])->name('api.admin.accounting.bank-reconciliations.store');
                Route::get('/accounting/bank-reconciliations/{bankReconciliation}', [BankReconciliationController::class, 'show'])->name('api.admin.accounting.bank-reconciliations.show');
                Route::post('/accounting/bank-reconciliations/{bankReconciliation}/toggle-line', [BankReconciliationController::class, 'toggleLine'])->name('api.admin.accounting.bank-reconciliations.toggle');
                Route::post('/accounting/bank-reconciliations/{bankReconciliation}/complete', [BankReconciliationController::class, 'complete'])->name('api.admin.accounting.bank-reconciliations.complete');
                Route::post('/accounting/bank-reconciliations/{bankReconciliation}/reopen', [BankReconciliationController::class, 'reopen'])->name('api.admin.accounting.bank-reconciliations.reopen');
                Route::delete('/accounting/bank-reconciliations/{bankReconciliation}', [BankReconciliationController::class, 'destroy'])->name('api.admin.accounting.bank-reconciliations.destroy');

                // Accounting periods: closing one is what makes a reported
                // month final, so it sits with the rest of the books.
                Route::get('/accounting/periods', [AccountingPeriodController::class, 'index'])->name('api.admin.accounting.periods.index');
                Route::post('/accounting/periods', [AccountingPeriodController::class, 'store'])->name('api.admin.accounting.periods.store');
                Route::post('/accounting/periods/{accountingPeriod}/close', [AccountingPeriodController::class, 'close'])->name('api.admin.accounting.periods.close');
                Route::post('/accounting/periods/{accountingPeriod}/reopen', [AccountingPeriodController::class, 'reopen'])->name('api.admin.accounting.periods.reopen');
                Route::delete('/accounting/periods/{accountingPeriod}', [AccountingPeriodController::class, 'destroy'])->name('api.admin.accounting.periods.destroy');

                Route::get('/accounting/trial-balance', [AccountingReportController::class, 'trialBalance'])->name('api.admin.accounting.trial-balance');
                Route::get('/accounting/income-statement', [AccountingReportController::class, 'incomeStatement'])->name('api.admin.accounting.income-statement');
                // Cross-module consistency: whether the books still agree with the
                // operational records. Read-only — repairs stay deliberate.
                Route::get('/accounting/system-health', [AccountingReportController::class, 'systemHealth'])->name('api.admin.accounting.system-health');
                Route::get('/accounting/balance-sheet', [AccountingReportController::class, 'balanceSheet'])->name('api.admin.accounting.balance-sheet');
                // One account's movements with an opening balance, and who owes
                // what for how long — both read straight from the ledger.
                Route::get('/accounting/account-statement', [AccountingReportController::class, 'accountStatement'])->name('api.admin.accounting.account-statement');
                Route::get('/accounting/aging', [AccountingReportController::class, 'aging'])->name('api.admin.accounting.aging');
                Route::get('/accounting/vat-return', [AccountingReportController::class, 'vatReturn'])->name('api.admin.accounting.vat-return');
                Route::get('/accounting/cash-flow', [AccountingReportController::class, 'cashFlow'])->name('api.admin.accounting.cash-flow');
                // The documents behind one party's balance — what the aging
                // report cannot answer, and what a dispute actually needs.
                Route::get('/accounting/party-statement', [AccountingReportController::class, 'partyStatement'])->name('api.admin.accounting.party-statement');
                // Which branch made the money — the question a combined income
                // statement cannot answer.
                Route::get('/accounting/cost-center-statement', [AccountingReportController::class, 'costCenterStatement'])->name('api.admin.accounting.cost-center-statement');
            });
        });

        // Settings
        //
        // Admin only. These decide the base currency the whole ledger is kept
        // in, among other things — a sales account was able to change them.
        Route::post('/settings', [SettingsController::class, 'update'])->middleware('role:admin')->name('api.settings.update');

        // Currencies: the managed list, their rates, and which one is base.
        //
        // Under /admin so the management endpoint does not sit on the same URI
        // as the public list — Laravel keeps only the last route registered for
        // a given method and path, and the public one was being swallowed.
        //
        // Admin or accountant: moving the base currency is an accounting event,
        // and a recorded rate decides what every converted price shows.
        Route::prefix('admin')->middleware('role:accountant')->group(function () {
            Route::get('/currencies', [CurrencyController::class, 'adminIndex'])->name('api.admin.currencies.index');
            Route::post('/currencies', [CurrencyController::class, 'store'])->name('api.admin.currencies.store');
            Route::put('/currencies/{currency}', [CurrencyController::class, 'update'])->name('api.admin.currencies.update');
            Route::post('/currencies/{currency}/rates', [CurrencyController::class, 'storeRate'])->name('api.admin.currencies.rates.store');
            Route::post('/currencies/{currency}/base', [CurrencyController::class, 'setBase'])->name('api.admin.currencies.base');
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
        Route::put('/invoices/{invoice}', [InvoiceController::class, 'update'])->name('api.invoices.update');
        Route::put('/invoices/{invoice}/status', [InvoiceController::class, 'updateStatus'])->name('api.invoices.update-status');
        Route::delete('/invoices/{invoice}', [InvoiceController::class, 'destroy'])->name('api.invoices.destroy');
        Route::get('/invoices/summary/stats', [InvoiceController::class, 'summary'])->name('api.invoices.summary');
        Route::get('/dashboard/stats', [DashboardController::class, 'stats'])->name('api.dashboard.stats');
        Route::get('/admin/search', [AdminSearchController::class, 'search'])->name('api.admin.search');

        // Admin Purchase Requests Management (employee/manager/admin roles only)
        Route::middleware('manage_orders')->group(function () {
            Route::post('/admin/purchase-requests', [PurchaseRequestController::class, 'adminStore'])->name('api.purchase-requests.admin.store');
            Route::get('/purchase-requests', [PurchaseRequestController::class, 'adminIndex'])->name('api.purchase-requests.admin.index');
            Route::get('/purchase-requests/{salesOrder}', [PurchaseRequestController::class, 'adminShow'])->name('api.purchase-requests.admin.show');
            Route::put('/purchase-requests/{salesOrder}/status', [PurchaseRequestController::class, 'adminUpdateStatus'])->name('api.purchase-requests.admin.update-status');
            Route::put('/purchase-requests/{salesOrder}/items', [PurchaseRequestController::class, 'adminUpdateItems'])->name('api.purchase-requests.admin.update-items');
            Route::put('/purchase-requests/{salesOrder}/assign', [PurchaseRequestController::class, 'adminAssignEmployee'])->name('api.purchase-requests.admin.assign');
        });

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
        // /production/stats must not be swallowed by the {productionOrder} wildcard.
        Route::get('/production/stats', [ProductionController::class, 'stats'])->name('api.production.stats');
        Route::get('/production/{productionOrder}', [ProductionController::class, 'show'])->whereNumber('productionOrder')->name('api.production.show');
        Route::put('/production/{productionOrder}', [ProductionController::class, 'update'])->whereNumber('productionOrder')->name('api.production.update');
        Route::delete('/production/{productionOrder}', [ProductionController::class, 'destroy'])->whereNumber('productionOrder')->name('api.production.destroy');
        Route::put('/production/{productionOrder}/status', [ProductionController::class, 'updateStatus'])->whereNumber('productionOrder')->name('api.production.update-status');

        // Quotes (عروض أسعار)
        Route::get('/quotes', [QuoteController::class, 'index'])->name('api.quotes.index');
        Route::post('/quotes', [QuoteController::class, 'store'])->name('api.quotes.store');
        Route::get('/quotes/{quote}', [QuoteController::class, 'show'])->name('api.quotes.show');
        Route::put('/quotes/{quote}', [QuoteController::class, 'update'])->name('api.quotes.update');
        Route::put('/quotes/{quote}/status', [QuoteController::class, 'updateStatus'])->name('api.quotes.update-status');
        Route::delete('/quotes/{quote}', [QuoteController::class, 'destroy'])->name('api.quotes.destroy');
        Route::post('/quotes/{quote}/convert-to-sales-order', [QuoteController::class, 'convertToSalesOrder'])->name('api.quotes.convert-to-sales-order');

        // Sales Orders (طلبات بيع)
        // The {salesOrder} wildcard is constrained to numeric ids: without it, it
        // matches the literal segments registered further down for the enhanced
        // sales-order endpoints (/sales-orders/enhanced, /channels, /contracts),
        // which are declared later and would never be reached.
        Route::get('/sales-orders', [SalesOrderController::class, 'index'])->name('api.sales-orders.index');
        Route::post('/sales-orders', [SalesOrderController::class, 'store'])->name('api.sales-orders.store');
        Route::get('/sales-orders/{salesOrder}', [SalesOrderController::class, 'show'])->whereNumber('salesOrder')->name('api.sales-orders.show');
        Route::put('/sales-orders/{salesOrder}', [SalesOrderController::class, 'update'])->whereNumber('salesOrder')->name('api.sales-orders.update');
        Route::delete('/sales-orders/{salesOrder}', [SalesOrderController::class, 'destroy'])->whereNumber('salesOrder')->name('api.sales-orders.destroy');
        Route::post('/sales-orders/{salesOrder}/convert-to-invoice', [SalesOrderController::class, 'convertToInvoice'])->whereNumber('salesOrder')->name('api.sales-orders.convert-to-invoice');
        Route::post('/sales-orders/{salesOrder}/confirm', [SalesOrderController::class, 'confirmOrder'])->whereNumber('salesOrder')->name('api.sales-orders.confirm');
        // Order routing and execution stages. Each of these moves stock, the
        // invoice and the ledger together — see SalesOrderWorkflowService.
        Route::get('/sales-orders/{salesOrder}/detail', [SalesOrderController::class, 'detail'])->whereNumber('salesOrder')->name('api.sales-orders.detail');
        Route::get('/sales-orders/{salesOrder}/routing', [SalesOrderController::class, 'routingOptions'])->whereNumber('salesOrder')->name('api.sales-orders.routing');
        // What stock cannot cover, for prefilling a purchase order.
        Route::get('/sales-orders/{salesOrder}/shortages', [SalesOrderController::class, 'stockShortages'])->whereNumber('salesOrder')->name('api.sales-orders.shortages');
        // Where each line's goods come from. A line may be split across
        // warehouses; saving the plan moves the stock hold with it.
        Route::get('/sales-orders/{salesOrder}/sourcing', [SalesOrderController::class, 'sourcing'])->whereNumber('salesOrder')->name('api.sales-orders.sourcing');
        Route::put('/sales-orders/{salesOrder}/sourcing', [SalesOrderController::class, 'saveSourcing'])->whereNumber('salesOrder')->name('api.sales-orders.sourcing.save');
        // The set of warehouses the order may draw on. Sourcing above then
        // distributes each line across exactly these.
        Route::put('/sales-orders/{salesOrder}/routings', [SalesOrderController::class, 'saveRoutings'])->whereNumber('salesOrder')->name('api.sales-orders.routings.save');
        Route::post('/sales-orders/{salesOrder}/transition', [SalesOrderController::class, 'transition'])->whereNumber('salesOrder')->name('api.sales-orders.transition');
        Route::post('/sales-orders/{salesOrder}/fulfillment-type', [SalesOrderController::class, 'changeFulfillmentType'])->whereNumber('salesOrder')->name('api.sales-orders.fulfillment-type');

        // Payments (مدفوعات)
        Route::get('/payments', [PaymentController::class, 'index'])->name('api.payments.index');
        Route::post('/payments', [PaymentController::class, 'store'])->name('api.payments.store');
        // Ahead of the {payment} wildcard below, which would otherwise try to
        // resolve "currency-summary" as a payment id.
        Route::get('/payments/currency-summary', [PaymentController::class, 'currencySummary'])->name('api.payments.currency-summary');
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
        Route::get('/purchase-receipts/purchase-order/{purchaseOrderId}', [PurchaseReceiptController::class, 'getPurchaseOrderDetails'])->name('api.purchase-receipts.purchase-order-details');

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
            // Literal segments must precede /rma/{id} or the wildcard swallows them.
            Route::get('/rma/statistics', [RmaController::class, 'getStatistics'])->name('api.admin.rma.statistics');
            Route::get('/rma/export', [RmaController::class, 'export'])->name('api.admin.rma.export');
            Route::get('/rma/customers-with-orders', [RmaController::class, 'getCustomersWithOrders'])->name('api.admin.rma.customers-with-orders');
            Route::post('/rma', [RmaController::class, 'store'])->name('api.admin.rma.store');
            Route::get('/rma/{id}', [RmaController::class, 'show'])->whereNumber('id')->name('api.admin.rma.show');
            Route::put('/rma/{id}', [RmaController::class, 'update'])->whereNumber('id')->name('api.admin.rma.update');
            Route::delete('/rma/{id}', [RmaController::class, 'destroy'])->whereNumber('id')->name('api.admin.rma.destroy');
            Route::post('/rma/{id}/approve', [RmaController::class, 'approve'])->name('api.admin.rma.approve');
            Route::post('/rma/{id}/reject', [RmaController::class, 'reject'])->name('api.admin.rma.reject');
            Route::post('/rma/{id}/receive', [RmaController::class, 'receiveItems'])->name('api.admin.rma.receive');
            Route::post('/rma/{id}/complete', [RmaController::class, 'complete'])->name('api.admin.rma.complete');
            Route::post('/rma/{id}/cancel', [RmaController::class, 'cancel'])->name('api.admin.rma.cancel');
            Route::get('/rma/{id}/items', [RmaController::class, 'getItems'])->name('api.admin.rma.items');
            Route::put('/rma/items/{id}', [RmaController::class, 'updateItem'])->name('api.admin.rma.items.update');
            Route::get('/rma/{id}/activity', [RmaController::class, 'getActivity'])->name('api.admin.rma.activity');

            // Credit notes (إشعارات دائنة) — raised by the returns settlement.
            // Literal segments before the {id} wildcard.
            Route::get('/credit-notes', [CreditNoteController::class, 'index'])->name('api.admin.credit-notes.index');
            Route::get('/credit-notes/summary', [CreditNoteController::class, 'summary'])->name('api.admin.credit-notes.summary');
            Route::get('/credit-notes/{id}', [CreditNoteController::class, 'show'])->whereNumber('id')->name('api.admin.credit-notes.show');
            Route::post('/credit-notes/{id}/cancel', [CreditNoteController::class, 'cancel'])->whereNumber('id')->name('api.admin.credit-notes.cancel');

            // Aggregated customer profile: metrics plus each activity list in
            // one response, so the profile screen makes a single request.
            Route::get('/customers/{id}/overview', [CustomerOverviewController::class, 'show'])
                ->whereNumber('id')->name('api.admin.customers.overview');

            // WMS - Warehouse Management System (نظام إدارة المستودعات)
            Route::prefix('wms')->middleware('web')->group(function () {
                /*
                 * The warehouse list is a shared lookup, not a WMS screen.
                 *
                 * Six screens outside this module — the sales, inventory,
                 * financial and product reports, and the warehouse analytics —
                 * read it to populate a filter, and those screens are open to
                 * roles that have no business managing warehouses. Gating the
                 * read along with the rest of WMS would empty those filters and
                 * make the reports look broken to the people who use them.
                 *
                 * Reading which warehouses exist is not sensitive; creating,
                 * editing and deleting them is, and stays behind the gate below.
                 */
                Route::get('/warehouses', [WmsController::class, 'indexWarehouses'])->name('api.admin.wms.warehouses.index');
                Route::get('/warehouses/{id}', [WmsController::class, 'showWarehouse'])->name('api.admin.wms.warehouses.show');

                // Everything else here is warehouse administration. The sidebar
                // already shows the WMS group to admins only; this makes the
                // API agree instead of relying on the menu to hide it.
                Route::middleware('role:admin')->group(function () {

                // Dashboard (لوحة التحكم)
                Route::get('/dashboard', [WmsController::class, 'dashboard'])->name('api.admin.wms.dashboard');

                // Products (المنتجات)
                Route::get('/products', [WmsController::class, 'indexProducts'])->name('api.admin.wms.products.index');

                // Assignments (الربط)
                Route::get('/assignments', [WmsController::class, 'indexAssignments'])->name('api.admin.wms.assignments.index');
                Route::post('/assignments', [WmsController::class, 'storeAssignment'])->name('api.admin.wms.assignments.store');
                Route::get('/assignments/{id}', [WmsController::class, 'showAssignment'])->name('api.admin.wms.assignments.show');
                Route::put('/assignments/{id}', [WmsController::class, 'updateAssignment'])->name('api.admin.wms.assignments.update');
                Route::delete('/assignments/{id}', [WmsController::class, 'destroyAssignment'])->name('api.admin.wms.assignments.destroy');
                Route::get('/suggest-stock-levels', [WmsController::class, 'suggestStockLevels'])->name('api.admin.wms.suggest-stock-levels');

                // Stock (المخزون)
                Route::get('/stock/balance', [WmsController::class, 'getStockBalance'])->name('api.admin.wms.stock.balance');
                Route::get('/stock/transactions', [WmsController::class, 'getStockTransactions'])->name('api.admin.wms.stock.transactions');
                Route::post('/stock/movements', [WmsController::class, 'createStockMovement'])->name('api.admin.wms.stock.movements.create');

                // Stats (إحصائيات)
                Route::get('/stats', [WmsController::class, 'getWmsStats'])->name('api.admin.wms.stats');

                // Warehouses (المستودعات) — reads are declared above, outside
                // this gate, because other modules use them as a lookup.
                Route::post('/warehouses', [WmsController::class, 'storeWarehouse'])->name('api.admin.wms.warehouses.store');
                Route::put('/warehouses/{id}', [WmsController::class, 'updateWarehouse'])->name('api.admin.wms.warehouses.update');
                Route::delete('/warehouses/{id}', [WmsController::class, 'destroyWarehouse'])->name('api.admin.wms.warehouses.destroy');

                // Warehouse Bins (أماكن التخزين)
                Route::get('/bins', [WmsController::class, 'indexBins'])->name('api.admin.wms.bins.index');
                Route::get('/bins/{id}', [WmsController::class, 'showBin'])->name('api.admin.wms.bins.show');
                Route::post('/bins', [WmsController::class, 'storeBin'])->name('api.admin.wms.bins.store');
                Route::put('/bins/{id}', [WmsController::class, 'updateBin'])->name('api.admin.wms.bins.update');
                Route::delete('/bins/{id}', [WmsController::class, 'destroyBin'])->name('api.admin.wms.bins.destroy');

                // Picking Lists (قوائم الاختيار)
                Route::get('/picking-lists', [WmsController::class, 'indexPickingLists'])->name('api.admin.wms.picking.index');
                Route::get('/picking-lists/{id}', [WmsController::class, 'showPickingList'])->name('api.admin.wms.picking.show');
                Route::post('/picking-lists', [WmsController::class, 'createPickingList'])->name('api.admin.wms.picking.create');
                Route::post('/picking-lists/{id}/start', [WmsController::class, 'startPicking'])->name('api.admin.wms.picking.start');
                Route::post('/picking-items/{itemId}', [WmsController::class, 'pickItem'])->name('api.admin.wms.picking.pick');
                Route::post('/picking-lists/{id}/complete', [WmsController::class, 'completePicking'])->name('api.admin.wms.picking.complete');
                Route::post('/picking-lists/{id}/cancel', [WmsController::class, 'cancelPicking'])->name('api.admin.wms.picking.cancel');
                Route::get('/picking/statistics', [WmsController::class, 'getPickingStatistics'])->name('api.admin.wms.picking.statistics');
                });
            });
        });

        // WMS - Warehouse Management System (نظام إدارة المستودعات) - Public Routes
        /*
         * The rest of WMS: packing, shipping and cycle counts.
         *
         * This block used to re-register the warehouse, bin and picking routes
         * that the guarded group above already declares. Laravel keeps only the
         * last route for a given method and URI, so those copies silently
         * replaced the guarded ones — putting `role:admin` on the first block
         * had no effect on any URI this one repeated. Verified: with the gate in
         * place, `/wms/dashboard` (declared once) answered 403 to a sales
         * account while `/wms/bins` (declared twice) still answered 200.
         *
         * The duplicates are gone, so each URI is registered once and carries
         * the gate it is supposed to.
         */
        Route::prefix('admin/wms')->middleware('role:admin')->group(function () {
            // Stats (إحصائيات)
            Route::get('/stats', [WmsController::class, 'getWmsStats'])->name('api.admin.wms.stats');

            // Packing Lists (قوائم التعبئة)
            Route::get('/packing-lists', [WmsController::class, 'indexPackingLists'])->name('api.admin.wms.packing.index');
            Route::get('/packing-lists/{id}', [WmsController::class, 'showPackingList'])->name('api.admin.wms.packing.show');
            Route::post('/packing-lists', [WmsController::class, 'createPackingList'])->name('api.admin.wms.packing.create');
            Route::post('/packing-lists/{id}/start', [WmsController::class, 'startPacking'])->name('api.admin.wms.packing.start');
            Route::put('/packing-items/{itemId}', [WmsController::class, 'updatePackageDetails'])->name('api.admin.wms.packing.update');
            Route::post('/packing-lists/{id}/complete', [WmsController::class, 'completePacking'])->name('api.admin.wms.packing.complete');
            Route::post('/packing-lists/{id}/cancel', [WmsController::class, 'cancelPacking'])->name('api.admin.wms.packing.cancel');
            Route::get('/packing-lists/{id}/labels', [WmsController::class, 'getPackingLabels'])->name('api.admin.wms.packing.labels');
            Route::get('/packing-lists/{id}/validate', [WmsController::class, 'validatePacking'])->name('api.admin.wms.packing.validate');
            Route::get('/packing/statistics', [WmsController::class, 'getPackingStatistics'])->name('api.admin.wms.packing.statistics');

            // Shipping Manifests (بيانات الشحن)
            Route::get('/shipping-manifests', [WmsController::class, 'indexShippingManifests'])->name('api.admin.wms.shipping.index');
            Route::get('/shipping-manifests/{id}', [WmsController::class, 'showShippingManifest'])->name('api.admin.wms.shipping.show');
            Route::post('/shipping-manifests', [WmsController::class, 'createShippingManifest'])->name('api.admin.wms.shipping.create');
            Route::post('/shipping-manifests/{id}/dispatch', [WmsController::class, 'dispatchManifest'])->name('api.admin.wms.shipping.dispatch');
            Route::post('/shipping-manifests/{id}/complete', [WmsController::class, 'completeManifest'])->name('api.admin.wms.shipping.complete');
            Route::get('/shipping-manifests/{id}/items/{itemId}/deliver', [WmsController::class, 'markItemDelivered'])->name('api.admin.wms.shipping.deliver');
            Route::get('/shipping/statistics', [WmsController::class, 'getShippingStatistics'])->name('api.admin.wms.shipping.statistics');

            // Cycle Counts (جرد الدوري)
            Route::get('/cycle-counts', [WmsController::class, 'indexCycleCounts'])->name('api.admin.wms.cycle-counts.index');
            Route::get('/cycle-counts/{id}', [WmsController::class, 'showCycleCount'])->name('api.admin.wms.cycle-counts.show');
            Route::post('/cycle-counts', [WmsController::class, 'storeCycleCount'])->name('api.admin.wms.cycle-counts.store');
            Route::post('/cycle-counts/{id}/start', [WmsController::class, 'startCycleCount'])->name('api.admin.wms.cycle-counts.start');
            Route::post('/cycle-counts/{countId}/items', [WmsController::class, 'addCycleCountItem'])->name('api.admin.wms.cycle-counts.items.store');
            Route::post('/cycle-counts/{id}/complete', [WmsController::class, 'completeCycleCount'])->name('api.admin.wms.cycle-counts.complete');
            Route::post('/cycle-counts/{id}/review', [WmsController::class, 'reviewCycleCount'])->name('api.admin.wms.cycle-counts.review');
            Route::post('/cycle-counts/{id}/adjustment', [WmsController::class, 'applyAdjustment'])->name('api.admin.wms.cycle-counts.adjustment');
            Route::post('/cycle-counts/{id}/cancel', [WmsController::class, 'cancelCycleCount'])->name('api.admin.wms.cycle-counts.cancel');
        });

        // Analytics & Reporting (التحليلات والتقارير)
        Route::prefix('analytics')->group(function () {
            // Overview (نظرة عامة) — the BI landing screen's whole card row.
            Route::get('/overview', [AnalyticsController::class, 'getOverview'])->name('api.analytics.overview');

            // CSV export per domain (تصدير). Declared before the domain routes
            // so `export` is never swallowed as a path segment.
            Route::get('/export/{domain}', [AnalyticsController::class, 'export'])
                ->whereIn('domain', ['sales', 'inventory', 'warehouse', 'financial'])
                ->name('api.analytics.export');

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
            // Profit, margins, cash flow and receivables ageing: the same
            // picture the books give, so the same audience. Only this subgroup
            // is gated — the sales, inventory and warehouse analytics stay open
            // because the main dashboard reads `/analytics/sales/trend` for
            // every role, and closing that would empty the landing screen for
            // the people who use it most.
            Route::middleware('role:accountant')->group(function () {
                Route::get('/financial/summary', [AnalyticsController::class, 'getFinancialSummary'])->name('api.analytics.financial.summary');
                Route::get('/financial/revenue-by-category', [AnalyticsController::class, 'getRevenueByCategory'])->name('api.analytics.financial.revenue');
                Route::get('/financial/expenses', [AnalyticsController::class, 'getExpenseBreakdown'])->name('api.analytics.financial.expenses');
                Route::get('/financial/cash-flow', [AnalyticsController::class, 'getCashFlowAnalysis'])->name('api.analytics.financial.cashflow');
                Route::get('/financial/profit-loss', [AnalyticsController::class, 'getProfitAndLoss'])->name('api.analytics.financial.pnl');
                Route::get('/financial/aging', [AnalyticsController::class, 'getAccountsAging'])->name('api.analytics.financial.aging');
                Route::get('/financial/ratios', [AnalyticsController::class, 'getFinancialRatios'])->name('api.analytics.financial.ratios');
                Route::get('/financial/budget-vs-actual', [AnalyticsController::class, 'getBudgetVsActual'])->name('api.analytics.financial.budget');
            });

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

            // Literal segments first: /{id} was declared above these and matched
            // them as ids, so /unread-count (polled by the admin header on every
            // page), /preferences and /templates all answered 404.
            Route::get('/unread-count', [NotificationController::class, 'getUnreadCount'])->name('api.notifications.unread-count');
            Route::post('/read-all', [NotificationController::class, 'markAllAsRead'])->name('api.notifications.read-all');
            Route::get('/preferences', [NotificationController::class, 'getPreferences'])->name('api.notifications.preferences');
            Route::put('/preferences', [NotificationController::class, 'updatePreferences'])->name('api.notifications.update-preferences');
            /*
             * Template management and broadcasting — admin only, matching the
             * sidebar's "notifications management" group.
             *
             * Only this part. Reading your own notifications, the unread count
             * the header polls on every page, and your own preferences all stay
             * open: they are personal, and gating them would break the header
             * for everyone.
             */
            Route::middleware('role:admin')->group(function () {
                Route::get('/templates', [NotificationController::class, 'indexTemplates'])->name('api.notifications.templates.index');
                Route::post('/templates', [NotificationController::class, 'storeTemplate'])->name('api.notifications.templates.store');
                Route::get('/templates/{id}', [NotificationController::class, 'showTemplate'])->whereNumber('id')->name('api.notifications.templates.show');
                Route::put('/templates/{id}', [NotificationController::class, 'updateTemplate'])->whereNumber('id')->name('api.notifications.templates.update');
                Route::delete('/templates/{id}', [NotificationController::class, 'destroyTemplate'])->whereNumber('id')->name('api.notifications.templates.destroy');
                Route::post('/send', [NotificationController::class, 'sendNotification'])->name('api.notifications.send');
                Route::post('/send-bulk', [NotificationController::class, 'sendBulkNotification'])->name('api.notifications.send-bulk');
            });

            Route::get('/{id}', [NotificationController::class, 'show'])->whereNumber('id')->name('api.notifications.show');
            Route::post('/{id}/read', [NotificationController::class, 'markAsRead'])->whereNumber('id')->name('api.notifications.read');
            Route::delete('/{id}', [NotificationController::class, 'destroy'])->whereNumber('id')->name('api.notifications.destroy');
        });

        // Workflows (سير العمل) — automation that acts on other people's data,
        // so admin only, matching the sidebar.
        Route::prefix('workflows')->middleware('role:admin')->group(function () {
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

        // Audit Logs (سجلات التدقيق) — who did what across every module,
        // including the risk scan. Admin only, matching the sidebar.
        Route::prefix('audit')->middleware('role:admin')->group(function () {
            Route::get('/risk-scan', [AuditController::class, 'riskScan'])->name('api.audit.risk-scan');
            Route::get('/risk-scan/export', [AuditController::class, 'exportRiskScan'])->name('api.audit.risk-scan.export');
            Route::get('/reconciliation', [AuditController::class, 'reconciliationSummary'])->name('api.audit.reconciliation');
            Route::get('/statistics', [AuditController::class, 'getStatistics'])->name('api.audit.statistics');
            Route::get('/recent', [AuditController::class, 'getRecentLogs'])->name('api.audit.recent');
            Route::get('/today', [AuditController::class, 'getTodayLogs'])->name('api.audit.today');
            Route::get('/entity-logs', [AuditController::class, 'getEntityLogs'])->name('api.audit.entity-logs');
            Route::get('/user-logs/{userId}', [AuditController::class, 'getUserLogs'])->name('api.audit.user-logs');
            Route::get('/module-logs/{module}', [AuditController::class, 'getModuleLogs'])->name('api.audit.module-logs');
            Route::get('/activity-timeline', [AuditController::class, 'getActivityTimeline'])->name('api.audit.timeline');
            Route::get('/user-summary/{userId}', [AuditController::class, 'getUserActivitySummary'])->name('api.audit.user-summary');
            Route::get('/my-summary', [AuditController::class, 'getMyActivitySummary'])->name('api.audit.my-summary');
            Route::get('/', [AuditController::class, 'index'])->name('api.audit.index');
            Route::get('/{id}', [AuditController::class, 'show'])->whereNumber('id')->name('api.audit.show');
            Route::post('/cleanup', [AuditController::class, 'cleanupOldLogs'])->name('api.audit.cleanup');
        });

        // Payrolls (رواتب) — salary figures, so admin or accountant only.
        Route::middleware('role:accountant')->group(function () {
            Route::get('/payrolls', [PayrollController::class, 'index'])->name('api.payrolls.index');
            Route::post('/payrolls', [PayrollController::class, 'store'])->name('api.payrolls.store');
            Route::get('/payrolls/{payroll}', [PayrollController::class, 'show'])->name('api.payrolls.show');
            Route::put('/payrolls/{payroll}', [PayrollController::class, 'update'])->name('api.payrolls.update');
            Route::delete('/payrolls/{payroll}', [PayrollController::class, 'destroy'])->name('api.payrolls.destroy');
            Route::post('/payrolls/auto-generate', [PayrollController::class, 'autoGenerate'])->name('api.payrolls.auto-generate');
            // Paying out what the monthly accruals built up, when somebody leaves.
            Route::post('/employees/{employee}/end-of-service', [PayrollController::class, 'settleEndOfService'])->name('api.payrolls.end-of-service');
        });
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
