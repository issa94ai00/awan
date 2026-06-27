# Awan Attakaddum — System Documentation

> **Version:** 1.0.0  
> **Last Updated:** June 2026  
> **Tech Stack:** Laravel 12 + Vue 3.5 + Vite 7 + MySQL  
> **Auth:** Laravel Sanctum (Token-based dual auth)

---

## Table of Contents

1. [System Overview](#1-system-overview)
2. [Tech Stack](#2-tech-stack)
3. [Project Structure](#3-project-structure)
4. [Architecture](#4-architecture)
5. [Database Schema](#5-database-schema)
6. [API Documentation](#6-api-documentation)
7. [Frontend Architecture](#7-frontend-architecture)
8. [Authentication System](#8-authentication-system)
9. [Modules Overview](#9-modules-overview)
10. [Development Setup](#10-development-setup)
11. [Deployment](#11-deployment)
12. [Environment Variables](#12-environment-variables)
13. [Code Conventions](#13-code-conventions)
14. [Maintenance & Troubleshooting](#14-maintenance--troubleshooting)

---

## 1. System Overview

**Awan Attakaddum** is a bilingual (Arabic/English) ERP + E-commerce system for a building materials company in Syria. It provides:

- **Public Storefront** — Product catalog, categories, special offers, featured products, inquiries, cart
- **Admin Dashboard** — Full ERP with Sales, Purchases, Inventory, HR, Accounting, Production, CRM, WMS, RMA, and BI Analytics
- **Mobile API** — Flutter app backend for customers (orders, wallet, reviews, wishlist)

### Core Business Flow

```
Customer Browses → Submits Inquiry/Purchase Request → Admin Processes → Invoice → Payment → Delivery
```

### Key Stats

| Metric | Value |
|---|---|
| API Endpoints | 358 |
| PHP Controllers | 85 |
| Eloquent Models | 82 |
| Database Tables | 70+ |
| Migration Files | 112 |
| Vue Views | 118 |
| Pinia Stores | 26 |
| PHP Lines | ~27,350 |
| Vue/JS Lines | ~48,600 |
| **Total** | **~76,000 lines** |

---

## 2. Tech Stack

### Backend

| Technology | Version | Purpose |
|---|---|---|
| PHP | ^8.2 | Runtime |
| Laravel | ^12.0 | Framework |
| Laravel Sanctum | latest | Token auth |
| MySQL | 8+ | Database |
| Livewire | ^4.3 | Blade components |
| Spatie Sitemap | latest | SEO sitemaps |
| Pest PHP | ^3.8 | Testing |

### Frontend

| Technology | Version | Purpose |
|---|---|---|
| Vue | ^3.5.35 | SPA Framework |
| Vue Router | ^5.1.0 | SPA Routing |
| Pinia | ^3.0.4 | State Management |
| Vite | ^7.0.7 | Build Tool |
| Element Plus | ^2.14.1 | Admin UI Kit |
| Font Awesome 6 | 6.4.0 (CDN) | Public icons |
| vue-i18n | ^11.4.6 | i18n (ar/en) |
| ECharts | ^6.1.0 | Charts |
| Tailwind CSS | ^3.1.0 | Utility CSS |
| Axios | ^1.11.0 | HTTP client |

### Infrastructure

| Tool | Purpose |
|---|---|
| Nginx/Apache | Web Server |
| MySQL 8+ | Database |
| Redis (optional) | Cache/Queue |
| Git | Version Control |

---

## 3. Project Structure

```
/
├── app/
│   ├── Http/
│   │   ├── Controllers/
│   │   │   ├── Api/          # 51 API Controllers
│   │   │   ├── Admin/        # Blade admin controllers
│   │   │   └── *.php         # Web controllers (Cart, Profile)
│   │   ├── Resources/        # API Resources (ProductResource, etc.)
│   │   └── Middleware/       # Custom middleware
│   ├── Models/               # 82 Eloquent Models
│   ├── helpers.php           # Global helper functions
│   └── Providers/            # Service Providers
│
├── config/                   # Laravel config files
├── database/
│   ├── migrations/           # 112 migration files
│   ├── factories/            # Model factories
│   └── seeders/              # Database seeders
│
├── resources/
│   ├── js/
│   │   ├── app.js            # Vue app entry
│   │   ├── App.vue           # Root component
│   │   ├── router/index.js   # Vue Router (640 lines)
│   │   ├── i18n.js           # Translations (ar/en, 4027 lines)
│   │   ├── bootstrap.js      # Axios + CSRF setup
│   │   ├── stores/           # 26 Pinia stores
│   │   ├── layouts/          # PublicLayout + AdminLayout
│   │   ├── views/            # 118 Vue views
│   │   │   ├── public/       # Storefront pages
│   │   │   ├── admin/        # Dashboard pages
│   │   │   └── *.vue         # Shared pages
│   │   ├── components/       # Shared components
│   │   │   ├── public/       # PublicNavbar, Footer, etc.
│   │   │   └── admin/        # AdminSidebar, etc.
│   │   └── utils/            # Helpers (fadeUp, etc.)
│   ├── views/                # Blade templates
│   │   ├── vue.blade.php     # SPA entry (main)
│   │   ├── layouts/          # Guest/admin layouts
│   │   └── admin/            # Blade admin pages (WMS, Analytics, etc.)
│   └── css/                  # Global CSS
│
├── routes/
│   ├── api.php               # 555 lines, 358 endpoints
│   ├── web.php               # 165 lines, SPA + admin blade routes
│   ├── auth.php              # Breeze auth routes
│   └── channels.php          # Broadcasting channels
│
├── public/
│   ├── build/                # Vite output (chunked JS/CSS)
│   ├── assets/               # Static assets (images, CSS, fonts)
│   └── index.php             # Laravel front controller
│
├── storage/                  # Logs, cache, uploads
├── tests/                    # Pest tests
├── vendor/                   # Composer dependencies
└── node_modules/              # NPM dependencies
```

---

## 4. Architecture

### 4.1 Request Lifecycle

```
Browser URL → /featured-products?new
    │
    ├─→ nginx → public/index.php
    │
    ├─→ Laravel Router
    │   ├── routes/web.php → matches /featured-products
    │   └── return view('vue')  ← SPA entry point
    │
    ├─→ Vue SPA boots
    │   ├── vue-router matches FeaturedProducts.vue
    │   ├── Component reads route.query ({new: ''})
    │   └── Calls GET /api/v1/featured-products?type=new
    │
    ├─→ Laravel Router
    │   └── routes/api.php → HomeController@featuredProducts
    │
    ├─→ Product query with type='new' filter
    │   └── where('created_at', '>=', 30 days ago)
    │
    └─→ JSON response → Vue renders products grid
```

### 4.2 SPA Architecture

```
resources/js/app.js
    │
    ├── createApp(App.vue)
    ├── use(pinia)          // 26 stores
    ├── use(router)         // 80+ routes
    ├── use(i18n)           // ar/en translations
    ├── use(ElementPlus)    // Admin UI
    └── auth.init() → app.mount('#app')

App.vue → <router-view /> renders based on route
    │
    ├── PublicLayout.vue
    │   ├── PublicNavbar
    │   ├── <router-view /> (page content)
    │   ├── FloatButtons (WhatsApp/Facebook)
    │   ├── PublicFooter
    │   └── CartDrawer
    │
    ├── AdminLayout.vue
    │   ├── AdminSidebar
    │   ├── AdminHeader
    │   └── <router-view /> (page content)
    │
    └── Login.vue (standalone, no layout)
```

### 4.3 API Structure

All API responses follow a consistent format:

```json
{
    "success": true|false,
    "message": "Human-readable message",
    "data": { ... },
    "pagination": {  // only for paginated endpoints
        "current_page": 1,
        "last_page": 10,
        "per_page": 12,
        "total": 120,
        "has_more_pages": true
    }
}
```

### 4.4 Admin/Public Separation

```
Frontend:
  / (PublicLayout)  →  No auth required
  /admin (AdminLayout)  →  requiresAuth guard (Sanctum token)

Backend API:
  /api/v1/*  →  Public endpoints (products, categories, search)
  /api/v1/admin/*  →  auth:sanctum middleware (CRUD operations)
  /api/v1/* (inside auth:sanctum group) →  Protected (invoices, POS, etc.)

Backend Web:
  /admin/*  →  Blade routes for WMS, Analytics, etc. (laravel auth)
  /admin/{path?}  →  Catch-all → view('vue') for Vue SPA
  /{path?}  →  Catch-all → view('vue') for Vue SPA
```

---

## 5. Database Schema

### 5.1 Core Tables

#### Products & Categories

- **`products`** (37 columns) — `id, name_ar, name_en, slug, description_ar, description_en, price, sale_price, currency, stock_quantity, image_main, image_gallery, brand, model, is_featured, is_active, in_stock, show_price, category_id, sku, barcode, cost_price, tax_rate, taxable, unit, min_stock, max_stock, reorder_point, weight, length, width, height, color, size, variant_group, short_description_ar, short_description_en, sort_order, seo (JSON), views_count, created_at, updated_at`
- **`categories`** (17 columns) — `id, name_ar, name_en, slug, description_ar, description_en, image, is_active, sort_order, seo (JSON), parent_id, created_at, updated_at`
- **`product_units`** — Multi-unit support (piece, box, kg...)
- **`product_batches`** — Batch/lot tracking with expiry dates
- **`product_serial_numbers`** — Individual serial number tracking

#### Sales & CRM

- **`invoices`** → `invoice_items` — Sales invoices with line items
- **`sales_orders`** → `sales_order_items` — Sales orders (can convert to invoice)
- **`quotes`** → `quote_items` — Customer quotes (can convert to sales order)
- **`payments`** — Payment records linked to invoices
- **`expenses`** — Expense tracking
- **`customers`** — CRM customer database with balance tracking
- **`inquiries`** → `inquiry_replies` — Customer inquiries with admin replies
- **`tickets`** — Support tickets with priority and status

#### Purchases & Inventory

- **`suppliers`** — Vendor management
- **`purchase_orders`** → `purchase_order_items` — Supplier orders
- **`purchase_receipts`** → `purchase_receipt_items` — Goods received
- **`stock_movements`** — Inventory transactions (in/out/adjust)
- **`warehouses`** — Multi-warehouse support
- **`warehouse_inventory`** — Stock per warehouse
- **`warehouse_bins`** — Location-based storage
- **`inventory_transfers`** → `inventory_transfer_items` — Inter-warehouse transfers
- **`reorder_alerts`** — Low stock notifications

#### HR

- **`employees`** — Employee records (name, department, position, salary, avatar)
- **`attendance`** — Daily check-in/out with status (present/late/absent)
- **`leave_requests`** — Leave management with type and approval
- **`payrolls`** — Salary processing

#### Accounting

- **`ledger_accounts`** — Chart of accounts (tree structure)
- **`journal_entries`** — Double-entry journal with debit/credit

#### WMS (Warehouse Management System)

- **`warehouses`**, **`warehouse_bins`** — Locations
- **`picking_lists`** → `picking_list_items` — Order picking
- **`packing_lists`** → `packing_list_items` — Order packing
- **`shipping_manifests`** → `shipping_manifest_items` — Dispatch
- **`cycle_counts`** → `cycle_count_items` — Inventory auditing

#### Advanced Modules

- **`rma_requests`** → `rma_items` — Return Merchandise Authorization
- **`analytics_metrics`** → `analytics_data_points` — KPI tracking
- **`reports`** — Custom report definitions
- **`dashboards`** → `dashboard_widgets` — BI dashboards
- **`workflows`** → `workflow_steps` → `workflow_executions` — Automation
- **`notification_templates`**, **`notification_preferences`** — Alerts
- **`audit_logs`** — Security audit trail
- **`special_offers`** — Promotional offers with date range
- **`settings`** — Key-value site settings

### 5.2 Core Relationships

```
Category 1──N Product
Product  1──N ProductUnit
Product  1──N ProductBatch
Product  1──N ProductSerialNumber

Customer 1──N Invoice
Invoice  1──N InvoiceItem
Invoice  1──N Payment

Supplier 1──N PurchaseOrder
PurchaseOrder 1──N PurchaseOrderItem

SalesOrder 1──N SalesOrderItem
Quote 1──N QuoteItem
Quote 1──1 SalesOrder (convert)
SalesOrder 1──1 Invoice (convert)

Employee 1──N Attendance
Employee 1──N LeaveRequest
Employee 1──N Payroll

User (admin) 1──N Invoice (created_by)
User 1──N AuditLog

RmaRequest 1──N RmaItem
RmaRequest N──1 Invoice
```

---

## 6. API Documentation

### 6.1 Public Endpoints (No Auth)

| Method | Endpoint | Controller | Purpose |
|---|---|---|---|
| GET | `/api/v1/home` | `HomeController@index` | Home page data (categories + featured) |
| GET | `/api/v1/featured-products?type=` | `HomeController@featuredProducts` | Products by type (featured/new/best) |
| GET | `/api/v1/special-offers` | `SpecialOfferController@activeOffers` | Active offers |
| GET | `/api/v1/settings` | `SettingsController@index` | Public settings |
| GET | `/api/v1/products` | `ProductController@index` | Product list (filtered) |
| GET | `/api/v1/products/{slug}` | `ProductController@show` | Product detail |
| GET | `/api/v1/products/{slug}/related` | `ProductController@related` | Related products |
| GET | `/api/v1/categories` | `CategoryController@index` | Category list |
| GET | `/api/v1/categories/{id}` | `CategoryController@show` | Category detail |
| GET | `/api/v1/categories/{id}/products` | `CategoryController@products` | Products by category |
| GET | `/api/v1/search` | `SearchController@search` | Full-text search |
| GET | `/api/v1/search/suggestions` | `SearchController@suggestions` | Autocomplete |
| POST | `/api/v1/inquiries` | `InquiryController@store` | Submit inquiry |
| POST | `/api/v1/purchase-requests` | `PurchaseRequestController@store` | Submit order request |
| GET | `/api/v1/purchase-requests/orders` | `PurchaseRequestController@orders` | View my orders |
| POST | `/api/v1/customer/auth/register` | `CustomerAuthController@register` | Customer register |
| POST | `/api/v1/customer/auth/login` | `CustomerAuthController@login` | Customer login |
| POST | `/api/v1/subscribe` | `SubscribeController@store` | Newsletter signup |
| POST | `/api/v1/auth/login` | `AuthController@login` | Admin login |
| GET | `/api/v1/cart/*` | `CartController` | Cart operations (web middleware) |

### 6.2 Protected Endpoints (auth:sanctum)

**Admin CRUD** (prefix: `/api/v1/admin`):

| Method | Endpoint | Purpose |
|---|---|---|
| GET/POST | `/products` | List / Create |
| GET/PUT/DELETE | `/products/{id}` | Show / Update / Delete |
| GET/POST | `/categories` | List / Create |
| GET/PUT/DELETE | `/categories/{id}` | Show / Update / Delete |
| GET/POST | `/special-offers` | List / Create |
| POST/DELETE | `/special-offers/{id}` | Update / Delete / Toggle |
| GET/POST | `/inquiries` | List inquiries |
| POST/PUT/DELETE | `/inquiries/{id}` | Reply / Update / Close / Reopen |
| GET/POST | `/suppliers` | Supplier CRUD |
| GET/POST | `/employees` | Employee CRUD |
| GET/POST | `/attendance` | Attendance CRUD |
| GET/POST | `/leave-requests` | Leave CRUD |
| GET/POST | `/purchase-orders` | Purchase order CRUD |
| GET/POST | `/inventory/movements` | Stock movements |
| GET/POST | `/accounting/*` | Ledger/Journal/Trial Balance |
| GET/POST | `/rma/*` | RMA full CRUD + approve/reject |
| GET/POST | `/wms/*` | WMS (bins/picking/packing/cycle/shipping) |

**Other Protected** (prefix: `/api/v1`):

| Method | Endpoint | Purpose |
|---|---|---|
| GET/POST | `/invoices` | Invoice CRUD + summary |
| GET/POST/PUT | `/sales-orders` | Sales orders |
| GET/POST/PUT | `/quotes` | Quotes + convert to sales order |
| GET/POST/PUT | `/payments` | Payments |
| GET/POST/PUT | `/expenses` | Expenses |
| GET/POST/PUT | `/purchase-receipts` | Goods received |
| GET/POST/PUT | `/purchase-requests` | Manage customer requests |
| GET/POST/PUT | `/production` | Production orders |
| GET/POST/PUT | `/payrolls` | Payroll + auto-generate |
| GET/POST | `/pos/*` | POS operations |
| GET/POST | `/tickets` | Support tickets |
| POST | `/settings` | Update system settings |
| GET/POST | `/inventory/transfers` | Transfer stock |
| GET/POST | `/analytics/*` | BI analytics |
| GET/POST | `/notifications/*` | Notifications + templates |
| GET/POST | `/workflows/*` | Workflow automation |
| GET/POST | `/audit/*` | Audit logs |
| GET/POST | `/enhanced/*` | Enhanced inventory/SO |

### 6.3 Featured Products API Detail

**Endpoint:** `GET /api/v1/featured-products?type={type}&page={page}`

**Type parameter:**
| Value | Behavior |
|---|---|
| `featured` (default) | `WHERE is_featured = 1` → `ORDER BY created_at DESC` |
| `new` | `WHERE created_at >= NOW() - 30 days` → `ORDER BY created_at DESC` |
| `best` | `ORDER BY views_count DESC` |

**Response:**
```json
{
    "success": true,
    "message": "New arrivals retrieved successfully",
    "data": {
        "type": "new",
        "products": [ /* ProductResource[] */ ],
        "pagination": {
            "current_page": 1,
            "last_page": 5,
            "per_page": 12,
            "total": 56,
            "has_more_pages": true
        }
    }
}
```

---

## 7. Frontend Architecture

### 7.1 Vue Router Structure

```
PublicLayout (/)
├── /                    → Home.vue
├── /about               → About.vue
├── /vision              → Vision.vue
├── /contact             → Contact.vue
├── /inquiry             → Inquiry.vue
├── /purchase-request    → PurchaseRequest.vue
├── /categories          → Categories.vue
├── /products            → Products.vue
├── /category/:slug      → CategoryDetail.vue
├── /product/:slug       → ProductDetail.vue
├── /cart                → Cart.vue
├── /customer-orders     → CustomerOrders.vue
├── /featured-products   → FeaturedProducts.vue (?new, ?best, ?featured)
└── /special-offers      → SpecialOffers.vue

/login                  → Login.vue

AdminLayout (/admin) — requiresAuth
├── /dashboard           → Dashboard.vue
├── /products/*          → 4 views (Index, Form, Show, ProductUnits)
├── /categories/*        → 2 views (Index, Form)
├── /settings            → Settings.vue
├── /special-offers      → SpecialOffers.vue
├── /secondary-navbar    → SecondaryNavbar.vue
├── /accounting/*        → 3 views (Journal, Ledger, TrialBalance)
├── /inventory/*         → 2 views (Index, Movements)
├── /sales/*             → 6 views (Invoices, Customers, Quotes, Orders, Payments)
├── /purchases/*         → 3 views (Suppliers, Orders, Receipts)
├── /hr/*                → 8 views (Employees, Attendance, Leaves, Payrolls)
├── /crm/*               → 4 views (Customers, Tickets)
├── /reports/*           → 4 views (Sales, Inventory, Financial, Payroll)
├── /production          → Production.vue
├── /pos                 → Pos.vue
├── /inquiries           → Inquiries.vue, InquiryShow.vue
├── /purchase-requests   → 2 views (Index, Show)
├── /roles               → Roles.vue
├── /permissions         → Permissions.vue
├── /visitors            → Visitors.vue
├── /wms/*               → 8+ views (Warehouses, Bins, Picking, Packing, Cycle)
├── /analytics/*         → 7 views (Sales, Inventory, Warehouse, Financial, etc.)
├── /notifications/*     → 4 views (Index, Templates, Preferences)
├── /workflows/*         → 5 views (Index, Form, Show, Steps, Executions)
├── /audit/*             → 5 views (Index, EntityLogs, UserActivity, etc.)
└── /rma/*               → 3 views (Index, Form, Show)

/:pathMatch(.*)*        → NotFound.vue (404)
```

### 7.2 Nav Guard (Authentication)

```javascript
router.beforeEach((to, from) => {
    const requiresAuth = to.matched.some(r => r.meta?.requiresAuth);
    if (!requiresAuth) return true;

    const auth = useAuthStore();
    if (!auth.token) return { path: '/login', query: { redirect: to.fullPath } };

    return auth.fetchUser().then(() => {
        if (!auth.isAuthenticated) return { path: '/login' };
        return true;
    }).catch(() => ({ path: '/login' }));
});
```

### 7.3 i18n System

- **Default locale:** `ar` (Arabic)
- **Fallback:** `ar`
- **Storage:** `localStorage.getItem('locale')`
- **Laravel sync:** `window.systemData?.locale`
- **Direction:** Arabic → RTL, English → LTR (dynamic stylesheet swap)
- **Global helper:**
  - `$t('key')` — Standard translation
  - `$p(obj, field)` — Pick localized field (`name_ar` / `name_en`)

### 7.4 Chunked Build (Vite)

```javascript
// vite.config.js — manualChunks:
'vue-vendor': ['vue', 'vue-router', 'pinia'],
'element-plus': ['element-plus', '@element-plus/icons-vue'],
'axios': ['axios']
```

Build output → `public/build/assets/` (separate chunks for lazy-loaded routes).

---

## 8. Authentication System

### 8.1 Dual Auth Model

```
┌─────────────────────────────────────────────────────┐
│  Admin Users (users table)                           │
│  → Sanctum token-based                              │
│  → Full ERP dashboard access                        │
│  → Login: POST /api/v1/auth/login                   │
│  → Token stored in localStorage as 'token'          │
│  → Route guard checks authStore.token               │
└─────────────────────────────────────────────────────┘

┌─────────────────────────────────────────────────────┐
│  Customers (customers table)                         │
│  → Sanctum token-based (separate guard)             │
│  → Order tracking + profile                         │
│  → Login: POST /api/v1/customer/auth/login          │
│  → Managed via customerAuthStore                    │
└─────────────────────────────────────────────────────┘
```

### 8.2 Auth Flow

```
User visits /admin/dashboard
    │
    ├── router.beforeEach → requiresAuth = true
    │
    ├── Check authStore.token
    │   │
    │   ├── No token → redirect /login?redirect=/admin/dashboard
    │   │
    │   └── Has token → call authStore.fetchUser()
    │       │
    │       ├── 200 OK → isAuthenticated = true → proceed
    │       │
    │       └── 401 → clear token → redirect /login
    │
    └── AdminLayout renders AdminSidebar + child route
```

### 8.3 Token Management

```
Login:  axios.post('/api/v1/auth/login', credentials)
        → response.data.token → localStorage.setItem('token', token)
        → axios.defaults.headers.common['Authorization'] = `Bearer ${token}`

Logout: axios.post('/api/v1/auth/logout')
        → localStorage.removeItem('token')
        → router.push('/login')

Axios Interceptor (bootstrap.js):
    response 401 → clear token → redirect /login
```

---

## 9. Modules Overview

### 9.1 Sales Module

| Feature | API | Vue View |
|---|---|---|
| Invoices | `InvoiceController` | `admin/sales/Invoices.vue` |
| Invoice Creation | `store()` | `admin/sales/InvoiceForm.vue` |
| Sales Orders | `SalesOrderController` | `admin/sales/SalesOrders.vue` |
| Quotes | `QuoteController` | `admin/sales/Quotes.vue` |
| Payments | `PaymentController` | `admin/sales/Payments.vue` |
| Customers | `CustomerController` (CRM) | `admin/sales/Customers.vue` |

**Flow:** Quote → Convert → Sales Order → Convert → Invoice → Payment

### 9.2 Purchases Module

| Feature | API | Vue View |
|---|---|---|
| Suppliers | `SupplierController` | `admin/purchases/Suppliers.vue` |
| Purchase Orders | `PurchaseOrderController` | `admin/purchases/Orders.vue` |
| Receipts | `PurchaseReceiptController` | `admin/purchases/Receipts.vue` |

### 9.3 Inventory Module

| Feature | API | Vue View |
|---|---|---|
| Stock Movements | `StockMovementController` | `admin/inventory/Movements.vue` |
| Enhanced Inventory | `EnhancedInventoryController` | — |
| Inventory Transfers | `InventoryTransferController` | — |
| Reorder Alerts | Enhanced Inventory | — |

### 9.4 HR Module

| Feature | API | Vue View |
|---|---|---|
| Employees | `EmployeeController` | `admin/hr/Employees.vue` |
| Attendance | `AttendanceController` | `admin/hr/Attendance.vue` |
| Leave Requests | `LeaveRequestController` | `admin/hr/Leaves.vue` |
| Payrolls | `PayrollController` | `admin/hr/Payrolls.vue` |

### 9.5 Accounting Module

| Feature | API | Vue View |
|---|---|---|
| Ledger Accounts | `LedgerAccountController` | `admin/accounting/Ledger.vue` |
| Journal Entries | `JournalEntryController` | `admin/accounting/Journal.vue` |
| Trial Balance | `JournalEntryController@trialBalance` | `admin/accounting/TrialBalance.vue` |

### 9.6 WMS (Warehouse Management)

| Feature | API | Vue View |
|---|---|---|
| Warehouses | `WmsController` | `admin/wms/Warehouses.vue` |
| Storage Bins | `WmsController` | `admin/wms/Bins.vue` |
| Picking Lists | `WmsController` | `admin/wms/PickingLists.vue` |
| Packing Lists | `WmsController` | `admin/wms/PackingLists.vue` |
| Cycle Counts | `WmsController` | `admin/wms/CycleCounts.vue` |
| Shipping Manifests | `WmsController` | — |
| Performance | `WmsController` | `admin/wms/Performance.vue` |

### 9.7 RMA (Returns)

| Feature | API | Vue View |
|---|---|---|
| RMA Requests | `RmaController` | `admin/rma/Index.vue` |
| Create/Edit | `store/update` | `admin/rma/Form.vue` |
| Detail | `show` | `admin/rma/Show.vue` |

### 9.8 Analytics & BI

| Feature | API | Vue View |
|---|---|---|
| Sales Analytics | `AnalyticsController` | `admin/analytics/Sales.vue` |
| Inventory Analytics | `AnalyticsController` | `admin/analytics/Inventory.vue` |
| Warehouse Analytics | `AnalyticsController` | `admin/analytics/Warehouse.vue` |
| Financial Analytics | `AnalyticsController` | `admin/analytics/Financial.vue` |
| KPIs / Metrics | `AnalyticsController` | `admin/analytics/Metrics.vue` |
| Custom Reports | `AnalyticsController` | `admin/analytics/Reports.vue` |
| Dashboards | `AnalyticsController` | `admin/analytics/Dashboards.vue` |

### 9.9 Automation (Workflows)

- Trigger types: Manual, Event-driven, Scheduled (Cron)
- Action types: Notification, API Call, Email, SMS, Audit Log, Custom Script
- Vue views: Index, Form, Show, Steps, Executions

### 9.10 Notifications

- Templates with placeholder variables (`{order_number}`, `{customer_name}`)
- Channels: In-App, Email, SMS, Push
- Per-user preferences

### 9.11 Public Storefront

| Feature | View | API |
|---|---|---|
| Home Page | `Home.vue` | `GET /api/v1/home` |
| Products | `Products.vue` | `GET /api/v1/products` |
| Product Detail | `ProductDetail.vue` | `GET /api/v1/products/{slug}` |
| Categories | `Categories.vue` | `GET /api/v1/categories` |
| Featured Products | `FeaturedProducts.vue` | `GET /api/v1/featured-products` |
| Special Offers | `SpecialOffers.vue` | `GET /api/v1/special-offers` |
| Cart | `Cart.vue` | `GET /api/v1/cart/*` |
| Inquiry | `Inquiry.vue` | `POST /api/v1/inquiries` |
| Purchase Request | `PurchaseRequest.vue` | `POST /api/v1/purchase-requests` |
| Customer Orders | `CustomerOrders.vue` | `GET /api/v1/purchase-requests/orders` |

---

## 10. Development Setup

### 10.1 Prerequisites

- PHP ^8.2
- Composer
- Node.js (see .nvmrc)
- MySQL 8+
- Git
- Nginx or Apache

### 10.2 Installation

```bash
# 1. Clone repository
git clone <repo-url> awan
cd awan

# 2. Install PHP dependencies
composer install

# 3. Environment setup
cp .env.example .env
php artisan key:generate

# 4. Configure .env (database, app_url)
#    Edit DB_DATABASE, DB_USERNAME, DB_PASSWORD

# 5. Database setup
php artisan migrate

# 6. Install JS dependencies
npm install

# 7. Build assets
npm run build

# 8. Storage link
php artisan storage:link

# 9. Start development
composer dev
#    Runs concurrently: php artisan serve + queue:listen + npm run dev
```

### 10.3 Development Commands

```bash
# Start dev server with hot reload
npm run dev           # Vite dev server
php artisan serve     # Laravel dev server
# Or both:
composer dev

# Build for production
npm run build

# Run tests
composer test
# or
php artisan test

# Clear cache
php artisan optimize:clear

# Run queue worker
php artisan queue:listen --tries=1
```

### 10.4 Default Admin Credentials

Register via `/api/v1/auth/register` or seed the users table.

---

## 11. Deployment

### 11.1 Production Checklist

```bash
# 1. Environment
APP_ENV=production
APP_DEBUG=false

# 2. Optimize Laravel
php artisan optimize
php artisan route:cache
php artisan config:cache
php artisan view:cache
php artisan event:cache

# 3. Build assets
npm run build

# 4. Queue worker (supervisor config)
php artisan queue:work --tries=3

# 5. Scheduler (cron)
* * * * * cd /path-to-project && php artisan schedule:run >> /dev/null 2>&1

# 6. SSL
# Configure HTTPS via Certbot/Let's Encrypt
```

### 11.2 Nginx Config Template

```nginx
server {
    listen 80;
    server_name awan.example.com;
    root /var/www/awan/public;

    index index.php;

    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    location ~ \.php$ {
        fastcgi_pass unix:/var/run/php/php8.2-fpm.sock;
        fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name;
        include fastcgi_params;
    }

    location ~ /\.(?!well-known).* {
        deny all;
    }

    # Cache static assets
    location /build/ {
        expires 1y;
        add_header Cache-Control "public, immutable";
    }
}
```

---

## 12. Environment Variables

| Variable | Default | Description |
|---|---|---|
| `APP_NAME` | `Laravel` | Site name |
| `APP_ENV` | `local` | `local`, `production` |
| `APP_DEBUG` | `true` | Debug mode (false in production) |
| `APP_URL` | `http://localhost` | Base URL |
| `DB_DATABASE` | `attakadom` | MySQL database name |
| `DB_USERNAME` | `root` | MySQL username |
| `DB_PASSWORD` | — | MySQL password |
| `SESSION_DRIVER` | `database` | Session backend |
| `QUEUE_CONNECTION` | `database` | Queue backend |
| `CACHE_STORE` | `database` | Cache backend |
| `REDIS_HOST` | `127.0.0.1` | Redis host (optional) |
| `MAIL_MAILER` | `log` | Mail driver |
| `VITE_APP_NAME` | `"${APP_NAME}"` | Passed to Vite |

---

## 13. Code Conventions

### 13.1 PHP Convention

- **Namespaces:** `App\Http\Controllers\Api\*` for API, `App\Models\*` for models
- **Responses:** Always return `JsonResponse` with `{success, message, data}`
- **Resources:** Use `ProductResource` for data transformation
- **Validation:** FormRequest or inline `$request->validate()`
- **Pagination:** Always return pagination meta with `current_page, last_page, per_page, total, has_more_pages`
- **Filters:** Public queries use `where('is_active', 1)`, admin queries respect `is_active` param

### 13.2 Vue Convention

- **Composition API** with `<script setup>` (no Options API)
- **Stores:** Pinia with `defineStore` using composition syntax
- **i18n:** Use `$t()` for translations, `$p()` for bilingual DB fields
- **API calls:** Direct `axios.get()` in components OR via Pinia store actions
- **Routing:** Lazy-loaded components with `() => import('@/views/...')`
- **Admin UI:** Element Plus components (`ElTable`, `ElDialog`, `ElForm`)
- **Public UI:** Font Awesome icons + custom CSS with Tailwind utility classes
- **Dark mode:** CSS variables + class toggle on `<html>`

### 13.3 CSS Convention

- Scoped `<style scoped>` for component-specific styles
- CSS custom properties (variables) for theming
- `--mobile-primary`, `--mobile-primary-dark`, `--mobile-primary-light` for brand colors
- Gradient gold accent: `#c9a959 → #d4af37`
- RTL-aware: Use `inset-inline-start/end` where possible

### 13.4 Translation Keys

- Flat key structure (no nesting beyond 2 levels)
- Arabic is the default language (key falls back to Arabic)
- Convention: `snake_case` for keys
- Page-specific keys grouped together with comments

---

## 14. Maintenance & Troubleshooting

### 14.1 Common Issues

#### 401 on Public API
- Check `routes/api.php` — ensure the route is outside `auth:sanctum` middleware
- Clear config cache: `php artisan optimize:clear`

#### Vite Build Failures
- Clear node_modules and rebuild: `rm -rf node_modules && npm install && npm run build`
- Check for `PURE_ANNOTATION` warnings (silenced in config)

#### Chunk Load Errors
- Browser has cached old chunks → force reload via router error handler (auto-refresh)
- Clear browser cache or deploy with new chunk hashes

#### Database Migration Issues
- Check migration order (filenames are timestamped)
- Run `php artisan migrate:fresh` only in development

### 14.2 Useful Commands

```bash
# Clear all caches
php artisan optimize:clear

# Show routes list
php artisan route:list

# Show all registered API routes
php artisan route:list --path=api

# Monitor queue
php artisan queue:monitor

# List failed jobs
php artisan queue:failed

# Tinker (interactive shell)
php artisan tinker
```

### 14.3 Backup Strategy

```bash
# Database backup
mysqldump -u root -p attakadom > backup/$(date +%Y%m%d)_attakadom.sql

# Storage backup
tar -czf backup/storage_$(date +%Y%m%d).tar.gz storage/app/

# Full project backup
tar -czf backup/awan_full_$(date +%Y%m%d).tar.gz \
    --exclude=node_modules --exclude=vendor --exclude=.git \
    --exclude=storage/framework/cache .
```

---

## Appendices

### A. Controllers List (API)

```
AnalyticsController      → BI analytics endpoints (30+ methods)
AttendanceController     → HR attendance CRUD
AuditController          → Security audit logs
AuthController           → Admin auth (register/login/logout/profile)
CategoryController       → Categories CRUD
CompanyController        → Company info
CustomerAuthController   → Customer auth (register/login/logout)
DashboardController      → Admin dashboard stats
EmployeeController       → HR employees CRUD
EnhancedInventoryController → Advanced inventory management
EnhancedSalesOrderController → Multi-channel sales orders
ErpUpgradeController     → ERP retail upgrade routes
HomeController           → Public home + featured products
InquiryAdminController   → Admin inquiry management
InquiryController        → Public inquiry submission
InventoryTransferController → Inter-warehouse transfers
InvoiceController        → Invoices CRUD
JournalEntryController   → Accounting journal + trial balance
LeaveRequestController   → HR leave requests CRUD
LedgerAccountController  → Chart of accounts
NotificationController   → Notifications + templates + preferences
PaymentController        → Payments CRUD
PayrollController        → HR payroll CRUD + auto-generate
PosController            → Point of Sale
ProductController        → Products CRUD (admin + public)
ProductionController     → Manufacturing orders
ProductUnitController    → Product multi-unit management
PurchaseOrderController  → Purchase orders CRUD
PurchaseReceiptController → Goods received CRUD
PurchaseRequestController → Customer purchase requests
QuoteController          → Quotes CRUD + convert
RmaController            → Returns management
SalesOrderController     → Sales orders CRUD + convert
SearchController         → Full-text search + suggestions
SettingsController       → System settings (key-value)
SpecialOfferController   → Promotional offers
StockMovementController  → Inventory movements
SubscribeController      → Newsletter subscriptions
SupplierController       → Suppliers CRUD
TicketController         → Support tickets CRUD
UploadController         → File upload/deletion
WmsController            → Warehouse management (bins/picking/packing/cycle)
WorkflowController       → Workflow automation
```

### B. API Endpoint Count by Module (358 total)

| Module | Endpoints |
|---|---|
| Public (Home/Products/Categories) | ~15 |
| Cart (web middleware) | ~6 |
| Customer Auth | ~4 |
| Admin Auth | ~5 |
| Admin Products + Units | ~11 |
| Admin Categories | ~5 |
| Admin Special Offers | ~5 |
| Admin Inquiries | ~12 |
| Admin Suppliers | ~5 |
| Admin Employees | ~5 |
| Admin Attendance | ~5 |
| Admin Leave Requests | ~5 |
| Admin Purchase Orders | ~5 |
| Admin Inventory | ~2 |
| Admin Accounting | ~6 |
| Sales (Invoices/POs/Quotes/Payments) | ~20 |
| CRM (Tickets/Customers) | ~12 |
| POS | ~8 |
| Production | ~7 |
| HR (Payrolls) | ~6 |
| Purchases (Suppliers/Orders/Receipts) | ~20 |
| WMS | ~35 |
| Inventory (Enhanced + Transfers) | ~22 |
| Sales Orders (Enhanced) | ~12 |
| RMA | ~15 |
| Analytics | ~35 |
| Notifications | ~18 |
| Workflows | ~15 |
| Audit | ~12 |
| ERP Retail | ~8 |

### C. Pinia Stores (26)

```
auth.js                → Admin auth state
customerAuth.js        → Customer auth state
products.js            → Products CRUD + filters
categories.js          → Categories CRUD
cart.js                → Shopping cart
settings.js            → Site settings
invoices.js            → Invoices CRUD
salesOrders.js         → Sales orders CRUD
quotes.js              → Quotes CRUD
payments.js            → Payments CRUD
purchaseOrders.js      → Purchase orders
purchaseReceipts.js    → Purchase receipts
purchaseRequests.js    → Customer requests
suppliers.js           → Suppliers
customers.js           → CRM customers
tickets.js             → Support tickets
employees.js           → HR employees
attendance.js          → HR attendance
leaveRequests.js       → HR leaves
payrolls.js            → HR payrolls
journalEntries.js      → Accounting journal
ledgerAccounts.js      → Accounting ledger
stockMovements.js      → Inventory movements
pos.js                 → Point of Sale
inquiries.js           → Admin inquiries
i18n.js                → Locale management
```
