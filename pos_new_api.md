# POS Invoice API Documentation

## Overview

Full API documentation for the Point of Sale (POS) Invoice system. These endpoints allow creating, managing, and retrieving sales invoices.

**Base URL:** `https://your-domain.com/api/v1`

---

## Authentication

All invoice endpoints require a valid Bearer token from Laravel Sanctum.

### Obtaining a Token

```http
POST /api/v1/auth/login
Content-Type: application/json

{
    "email": "user@example.com",
    "password": "password123"
}
```

**Response:**
```json
{
    "success": true,
    "message": "تم تسجيل الدخول بنجاح",
    "data": {
        "token": "1|abcdef123456...",
        "token_type": "Bearer",
        "user": { ... }
    }
}
```

### Using the Token

Include the token in the `Authorization` header for all invoice requests:

```http
Authorization: Bearer 1|abcdef123456...
```

---

## Endpoints

### 1. List All Invoices

Retrieve a paginated list of all invoices with optional filters.

```http
GET /api/v1/invoices
Authorization: Bearer {token}
```

**Query Parameters (optional):**

| Parameter | Type | Description |
|-----------|------|-------------|
| `status` | string | Filter by status: `pending`, `paid`, `cancelled` |
| `payment_method` | string | Filter by method: `cash`, `card`, `transfer` |
| `date_from` | date | Filter from date (YYYY-MM-DD) |
| `date_to` | date | Filter to date (YYYY-MM-DD) |
| `search` | string | Search by customer name, phone, or invoice number |
| `per_page` | integer | Items per page (default: 15) |

**Example:**

```http
GET /api/v1/invoices?status=paid&date_from=2026-05-01&per_page=10
Authorization: Bearer {token}
```

**Response:**

```json
{
    "success": true,
    "message": "تم جلب الفواتير بنجاح",
    "data": [
        {
            "id": 1,
            "invoice_number": "INV-20260508-A3B7",
            "customer_name": "محمد أحمد",
            "customer_email": "mohamed@example.com",
            "customer_phone": "0501234567",
            "subtotal": 500.00,
            "tax": 25.00,
            "discount": 0.00,
            "total": 525.00,
            "payment_method": "cash",
            "payment_method_label": "نقدي",
            "status": "paid",
            "status_label": "مدفوع",
            "notes": null,
            "paid_at": "2026-05-08T14:30:00.000000Z",
            "items": [
                {
                    "id": 1,
                    "product_id": 3,
                    "product_name": "مثقاب كهربائي 500 واط",
                    "quantity": 2,
                    "unit_price": 250.00,
                    "total_price": 500.00,
                    "product": {
                        "id": 3,
                        "name_ar": "مثقاب كهربائي 500 واط",
                        "name_en": "500W Electric Drill",
                        "slug": "500w-electric-drill",
                        "image_main": "https://domain.com/storage/products/drill.jpg"
                    }
                }
            ],
            "created_at": "2026-05-08T14:30:00.000000Z",
            "created_at_formatted": "2026-05-08 14:30:00",
            "created_at_human": "منذ 5 دقائق"
        }
    ],
    "pagination": {
        "current_page": 1,
        "last_page": 1,
        "per_page": 15,
        "total": 1,
        "has_more_pages": false
    }
}
```

---

### 2. Create New Invoice

Create a new sales invoice with items.

```http
POST /api/v1/invoices
Content-Type: application/json
Authorization: Bearer {token}
```

**Request Body:**

```json
{
    "customer_name": "محمد أحمد",
    "customer_email": "mohamed@example.com",
    "customer_phone": "0501234567",
    "items": [
        {
            "product_id": 3,
            "quantity": 2,
            "unit_price": 250.00,
            "notes": "لون أزرق"
        },
        {
            "product_id": 5,
            "quantity": 1,
            "unit_price": 150.00,
            "notes": null
        }
    ],
    "tax": 25.00,
    "discount": 10.00,
    "payment_method": "cash",
    "notes": "فاتورة عميل جديد",
    "status": "paid"
}
```

**Field Descriptions:**

| Field | Type | Required | Description |
|-------|------|----------|-------------|
| `customer_name` | string | No | Customer name |
| `customer_email` | string | No | Customer email |
| `customer_phone` | string | No | Customer phone |
| `items` | array | **Yes** | Array of invoice items |
| `items.*.product_id` | integer | **Yes** | Product ID from database |
| `items.*.quantity` | integer | **Yes** | Quantity (min: 1) |
| `items.*.unit_price` | decimal | **Yes** | Unit price |
| `items.*.notes` | string | No | Item note |
| `tax` | decimal | No | Tax amount |
| `discount` | decimal | No | Discount amount |
| `payment_method` | string | No | `cash`, `card`, `transfer` (default: `cash`) |
| `status` | string | No | `pending`, `paid`, `cancelled` (default: `pending`) |
| `notes` | string | No | General invoice notes |

**Auto-Calculated Fields:**
- `subtotal` = sum of all item `quantity * unit_price`
- `total` = `subtotal + tax - discount`
- `invoice_number` = auto-generated (`INV-YYYYMMDD-RANDOM`)

**Response (Success - 201):**

```json
{
    "success": true,
    "message": "تم إنشاء الفاتورة بنجاح",
    "data": {
        "id": 1,
        "invoice_number": "INV-20260508-A3B7",
        "customer_name": "محمد أحمد",
        "customer_phone": "0501234567",
        "subtotal": 650.00,
        "tax": 25.00,
        "discount": 10.00,
        "total": 665.00,
        "payment_method": "cash",
        "status": "paid",
        "notes": "فاتورة عميل جديد",
        "items": [
            {
                "id": 1,
                "product_id": 3,
                "product_name": "مثقاب كهربائي 500 واط",
                "quantity": 2,
                "unit_price": 250.00,
                "total_price": 500.00
            },
            {
                "id": 2,
                "product_id": 5,
                "product_name": "مفك مجموعة 20 قطعة",
                "quantity": 1,
                "unit_price": 150.00,
                "total_price": 150.00
            }
        ],
        "created_at": "2026-05-08T14:30:00.000000Z"
    }
}
```

**Response (Validation Error - 422):**

```json
{
    "success": false,
    "message": "خطأ في التحقق من البيانات",
    "errors": {
        "items": ["يجب إضافة منتج واحد على الأقل"],
        "items.0.product_id": ["معرف المنتج مطلوب"]
    }
}
```

---

### 3. Show Single Invoice

Retrieve a specific invoice by ID with all items.

```http
GET /api/v1/invoices/{invoice_id}
Authorization: Bearer {token}
```

**Example:**

```http
GET /api/v1/invoices/1
Authorization: Bearer {token}
```

**Response:**

```json
{
    "success": true,
    "message": "تم جلب الفاتورة بنجاح",
    "data": {
        "id": 1,
        "invoice_number": "INV-20260508-A3B7",
        "customer_name": "محمد أحمد",
        "customer_email": "mohamed@example.com",
        "customer_phone": "0501234567",
        "subtotal": 650.00,
        "tax": 25.00,
        "discount": 10.00,
        "total": 665.00,
        "payment_method": "cash",
        "status": "paid",
        "status_label": "مدفوع",
        "items": [
            {
                "id": 1,
                "product_id": 3,
                "product_name": "مثقاب كهربائي 500 واط",
                "quantity": 2,
                "unit_price": 250.00,
                "total_price": 500.00,
                "product": {
                    "id": 3,
                    "name_ar": "مثقاب كهربائي 500 واط",
                    "slug": "500w-electric-drill",
                    "image_main": "https://domain.com/storage/products/drill.jpg"
                }
            }
        ],
        "created_at": "2026-05-08T14:30:00.000000Z",
        "paid_at": "2026-05-08T14:30:00.000000Z"
    }
}
```

---

### 4. Update Invoice Status

Change the status of an invoice (pending → paid → cancelled).

```http
PUT /api/v1/invoices/{invoice_id}/status
Content-Type: application/json
Authorization: Bearer {token}
```

**Request Body:**

```json
{
    "status": "paid"
}
```

**Status Options:**

| Status | Description | Auto Action |
|--------|-------------|-------------|
| `pending` | Pending payment | paid_at = null |
| `paid` | Fully paid | paid_at = now() |
| `cancelled` | Cancelled | -- |

**Response:**

```json
{
    "success": true,
    "message": "تم تحديث حالة الفاتورة بنجاح",
    "data": {
        "id": 1,
        "status": "paid",
        "status_label": "مدفوع",
        "paid_at": "2026-05-08T15:00:00.000000Z",
        ...
    }
}
```

---

### 5. Delete Invoice

Permanently delete an invoice and all its items.

```http
DELETE /api/v1/invoices/{invoice_id}
Authorization: Bearer {token}
```

**Response:**

```json
{
    "success": true,
    "message": "تم حذف الفاتورة بنجاح"
}
```

---

### 6. Invoice Summary Statistics

Get revenue and count statistics for invoices.

```http
GET /api/v1/invoices/summary/stats
Authorization: Bearer {token}
```

**Response:**

```json
{
    "success": true,
    "message": "تم جلب الإحصائيات بنجاح",
    "data": {
        "revenue": {
            "today": 1250.00,
            "week": 8750.00,
            "month": 32500.00,
            "total": 156000.00
        },
        "count": {
            "today": 8,
            "week": 45,
            "month": 180,
            "total": 1200
        },
        "today_breakdown": {
            "paid": 1000.00,
            "pending": 250.00
        }
    }
}
```

---

## Error Responses

### 401 Unauthorized

```json
{
    "message": "Unauthenticated."
}
```

### 404 Not Found

```json
{
    "success": false,
    "message": "API endpoint not found",
    "data": null
}
```

### 422 Validation Error

```json
{
    "success": false,
    "message": "خطأ في التحقق من البيانات",
    "errors": {
        "items": ["يجب إضافة منتج واحد على الأقل"]
    }
}
```

### 500 Server Error

```json
{
    "success": false,
    "message": "خطأ في إنشاء الفاتورة",
    "error": "SQL error details..."
}
```

---

## Invoice Data Model

### Invoice Fields

| Field | Type | Description |
|-------|------|-------------|
| `id` | integer | Auto-increment primary key |
| `invoice_number` | string | Unique invoice number (INV-YYYYMMDD-XXXX) |
| `customer_name` | string | Customer full name |
| `customer_email` | string | Customer email |
| `customer_phone` | string | Customer phone |
| `subtotal` | decimal | Sum of item totals |
| `tax` | decimal | Tax amount |
| `discount` | decimal | Discount amount |
| `total` | decimal | Final total (subtotal + tax - discount) |
| `payment_method` | enum | `cash` \| `card` \| `transfer` |
| `status` | enum | `pending` \| `paid` \| `cancelled` |
| `notes` | text | General notes |
| `created_by` | integer | User ID who created invoice |
| `paid_at` | datetime | When invoice was marked paid |
| `created_at` | datetime | Creation timestamp |
| `updated_at` | datetime | Last update timestamp |

### Invoice Item Fields

| Field | Type | Description |
|-------|------|-------------|
| `id` | integer | Auto-increment primary key |
| `invoice_id` | integer | Parent invoice ID |
| `product_id` | integer | Linked product ID (nullable) |
| `product_name` | string | Product name at time of sale |
| `quantity` | integer | Quantity sold |
| `unit_price` | decimal | Price per unit |
| `total_price` | decimal | Auto-calculated: quantity × unit_price |
| `notes` | text | Item-specific notes |

---

## Database Migrations

### invoices table

```php
Schema::create('invoices', function (Blueprint $table) {
    $table->id();
    $table->string('invoice_number')->unique()->index();
    $table->string('customer_name')->nullable();
    $table->string('customer_email')->nullable();
    $table->string('customer_phone')->nullable();
    $table->decimal('subtotal', 12, 2)->default(0);
    $table->decimal('tax', 12, 2)->default(0);
    $table->decimal('discount', 12, 2)->default(0);
    $table->decimal('total', 12, 2)->default(0);
    $table->string('payment_method')->default('cash');
    $table->string('status')->default('pending');
    $table->text('notes')->nullable();
    $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
    $table->timestamp('paid_at')->nullable();
    $table->timestamps();
});
```

### invoice_items table

```php
Schema::create('invoice_items', function (Blueprint $table) {
    $table->id();
    $table->foreignId('invoice_id')->constrained('invoices')->cascadeOnDelete();
    $table->foreignId('product_id')->nullable()->constrained('products')->nullOnDelete();
    $table->string('product_name');
    $table->integer('quantity')->default(1);
    $table->decimal('unit_price', 12, 2)->default(0);
    $table->decimal('total_price', 12, 2)->default(0);
    $table->text('notes')->nullable();
    $table->timestamps();
});
```

---

## Integration Notes

### POS Workflow

1. **Select Products:** User browses categories and products in the POS UI
2. **Add to Cart:** Products are added to an internal cart with quantities
3. **Review Cart:** Subtotal, tax, and discount are calculated
4. **Create Invoice:** Send POST `/api/v1/invoices` with the cart items
5. **Payment:** If `status: "paid"`, invoice is marked as paid immediately
6. **Print/Receipt:** Use the returned `invoice_number` and `total` for printing

### Invoice Number Format

```
INV-YYYYMMDD-XXXX
```

Example: `INV-20260508-A3B7`

### Auto-Calculations

- `subtotal` = Σ(items.quantity × items.unit_price)
- `total` = subtotal + tax - discount (min: 0)
- `invoice_number` = auto-generated unique string
- `items.*.total_price` = quantity × unit_price (per item)

---

## Migration Commands

Run these on the production server after deployment:

```bash
php artisan migrate --force
php artisan route:clear
php artisan config:clear
```

---

## Full API Route List

| Method | Endpoint | Auth | Description |
|--------|----------|------|-------------|
| POST | `/api/v1/auth/login` | No | Login and get token |
| POST | `/api/v1/auth/register` | No | Register new user |
| POST | `/api/v1/auth/logout` | Yes | Logout and revoke token |
| GET | `/api/v1/auth/user` | Yes | Get authenticated user |
| PUT | `/api/v1/auth/profile` | Yes | Update profile |
| GET | `/api/v1/home` | No | Home page data |
| GET | `/api/v1/featured-products` | No | Featured products |
| GET | `/api/v1/categories` | No | All categories |
| GET | `/api/v1/categories/{slug}` | No | Category details |
| GET | `/api/v1/categories/{slug}/products` | No | Category products |
| GET | `/api/v1/products` | No | All products |
| GET | `/api/v1/products/{slug}` | No | Product details |
| GET | `/api/v1/products/{slug}/related` | No | Related products |
| GET | `/api/v1/search` | No | Search products |
| GET | `/api/v1/search/suggestions` | No | Search suggestions |
| POST | `/api/v1/inquiries` | No | Submit inquiry |
| GET | `/api/v1/user/inquiries` | Yes | User inquiries list |
| GET | `/api/v1/user/inquiries/{id}` | Yes | Single inquiry |
| **GET** | **`/api/v1/invoices`** | **Yes** | **List invoices** |
| **POST** | **`/api/v1/invoices`** | **Yes** | **Create invoice** |
| **GET** | **`/api/v1/invoices/{id}`** | **Yes** | **Show invoice** |
| **PUT** | **`/api/v1/invoices/{id}/status`** | **Yes** | **Update status** |
| **DELETE** | **`/api/v1/invoices/{id}`** | **Yes** | **Delete invoice** |
| **GET** | **`/api/v1/invoices/summary/stats`** | **Yes** | **Stats summary** |
