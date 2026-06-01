# ERP API Documentation

## Overview
This document describes all the ERP-related API endpoints for the Awan system. All endpoints require authentication via Sanctum unless otherwise noted.

**Base URL:** `http://your-domain.com/api/v1`
**Authentication:** Bearer Token (Sanctum)

---

## Quotes (عروض الأسعار)

### Get Quotes
```
GET /api/v1/quotes
```

**Query Parameters:**
- `status` (optional): Filter by status (draft, sent, accepted, rejected, expired)
- `customer_id` (optional): Filter by customer ID

**Response:**
```json
{
  "success": true,
  "message": "Quotes retrieved successfully",
  "data": {
    "quotes": [...],
    "pagination": {
      "current_page": 1,
      "last_page": 10,
      "per_page": 20,
      "total": 200,
      "has_more_pages": true
    }
  }
}
```

### Create Quote
```
POST /api/v1/quotes
```

**Request Body:**
```json
{
  "customer_id": 1,
  "valid_until": "2026-12-31",
  "discount": 0,
  "tax": 0,
  "notes": "Optional notes",
  "terms": "Optional terms",
  "items": [
    {
      "product_id": 1,
      "quantity": 5,
      "unit_price": 100.00,
      "discount": 0,
      "tax": 0
    }
  ]
}
```

**Response:**
```json
{
  "success": true,
  "message": "تم إنشاء عرض السعر بنجاح",
  "data": {
    "id": 1,
    "quote_number": "QT-000001",
    "customer_id": 1,
    "status": "draft",
    "subtotal": 500.00,
    "total": 500.00,
    ...
  }
}
```

### Get Quote by ID
```
GET /api/v1/quotes/{quote}
```

### Update Quote
```
PUT /api/v1/quotes/{quote}
```

**Request Body:** Same as Create Quote

### Delete Quote
```
DELETE /api/v1/quotes/{quote}
```

### Convert Quote to Sales Order
```
POST /api/v1/quotes/{quote}/convert-to-sales-order
```

**Conditions:** Quote must have status `accepted`

**Response:**
```json
{
  "success": true,
  "message": "تم تحويل عرض السعر إلى طلب بيع بنجاح",
  "data": {
    "id": 1,
    "order_number": "SO-000001",
    "quote_id": 1,
    ...
  }
}
```

---

## Sales Orders (طلبات بيع)

### Get Sales Orders
```
GET /api/v1/sales-orders
```

**Query Parameters:**
- `status` (optional): Filter by status (pending, confirmed, processing, shipped, delivered, cancelled)
- `customer_id` (optional): Filter by customer ID

### Create Sales Order
```
POST /api/v1/sales-orders
```

**Request Body:**
```json
{
  "customer_id": 1,
  "order_date": "2026-06-01",
  "expected_delivery": "2026-06-15",
  "discount": 0,
  "tax": 0,
  "shipping_address": "Shipping address",
  "notes": "Optional notes",
  "items": [
    {
      "product_id": 1,
      "quantity": 5,
      "unit_price": 100.00,
      "discount": 0,
      "tax": 0
    }
  ]
}
```

### Get Sales Order by ID
```
GET /api/v1/sales-orders/{salesOrder}
```

### Update Sales Order
```
PUT /api/v1/sales-orders/{salesOrder}
```

### Delete Sales Order
```
DELETE /api/v1/sales-orders/{salesOrder}
```

### Convert Sales Order to Invoice
```
POST /api/v1/sales-orders/{salesOrder}/convert-to-invoice
```

**Conditions:** Sales order must have status `confirmed`

**Response:**
```json
{
  "success": true,
  "message": "تم تحويل طلب البيع إلى فاتورة بنجاح",
  "data": {
    "id": 1,
    "invoice_number": "INV-20260601-0001",
    "sales_order_id": 1,
    ...
  }
}
```

---

## Payments (مدفوعات)

### Get Payments
```
GET /api/v1/payments
```

**Query Parameters:**
- `status` (optional): Filter by status (pending, completed, failed, refunded)
- `customer_id` (optional): Filter by customer ID

### Create Payment
```
POST /api/v1/payments
```

**Request Body:**
```json
{
  "invoice_id": 1,
  "customer_id": 1,
  "payment_method": "cash",
  "amount": 500.00,
  "payment_date": "2026-06-01",
  "reference": "REF-001",
  "notes": "Optional notes"
}
```

**Payment Methods:** cash, card, bank_transfer, check

**Side Effects:**
- Updates invoice paid_amount and due_amount
- Marks invoice as paid if due_amount reaches 0
- Updates customer balance

### Get Payment by ID
```
GET /api/v1/payments/{payment}
```

### Update Payment
```
PUT /api/v1/payments/{payment}
```

### Delete Payment
```
DELETE /api/v1/payments/{payment}
```

**Side Effects:**
- Reverses invoice paid_amount and due_amount
- Restores customer balance

---

## Purchase Receipts (إيصالات استلام)

### Get Purchase Receipts
```
GET /api/v1/purchase-receipts
```

**Query Parameters:**
- `supplier_id` (optional): Filter by supplier ID

### Create Purchase Receipt
```
POST /api/v1/purchase-receipts
```

**Request Body:**
```json
{
  "purchase_order_id": 1,
  "supplier_id": 1,
  "receipt_date": "2026-06-01",
  "notes": "Optional notes",
  "items": [
    {
      "product_id": 1,
      "quantity": 10,
      "unit_price": 50.00
    }
  ]
}
```

**Side Effects:**
- Automatically creates stock movements for each item
- Updates product stock quantities

### Get Purchase Receipt by ID
```
GET /api/v1/purchase-receipts/{receipt}
```

### Update Purchase Receipt
```
PUT /api/v1/purchase-receipts/{receipt}
```

### Delete Purchase Receipt
```
DELETE /api/v1/purchase-receipts/{receipt}
```

---

## Payrolls (رواتب)

### Get Payrolls
```
GET /api/v1/payrolls
```

**Query Parameters:**
- `status` (optional): Filter by status (pending, processed, paid)
- `employee_id` (optional): Filter by employee ID

### Create Payroll
```
POST /api/v1/payrolls
```

**Request Body:**
```json
{
  "employee_id": 1,
  "pay_period_start": "2026-06-01",
  "pay_period_end": "2026-06-30",
  "payment_date": "2026-06-30",
  "basic_salary": 5000.00,
  "overtime_hours": 10,
  "overtime_rate": 25.00,
  "bonuses": 500.00,
  "deductions": 200.00,
  "notes": "Optional notes"
}
```

**Calculated Fields:**
- `net_salary` = basic_salary + (overtime_hours × overtime_rate) + bonuses - deductions

### Get Payroll by ID
```
GET /api/v1/payrolls/{payroll}
```

### Update Payroll
```
PUT /api/v1/payrolls/{payroll}
```

### Delete Payroll
```
DELETE /api/v1/payrolls/{payroll}
```

### Auto-Generate Payrolls
```
POST /api/v1/payrolls/auto-generate
```

**Request Body:**
```json
{
  "pay_period_start": "2026-06-01",
  "pay_period_end": "2026-06-30",
  "payment_date": "2026-06-30"
}
```

**Description:** Automatically generates payroll records for all employees based on attendance records and calculates overtime hours.

**Response:**
```json
{
  "success": true,
  "message": "تم إنشاء 10 مسيرة رواتب بنجاح",
  "data": {
    "created_count": 10
  }
}
```

---

## Error Responses

All endpoints return consistent error responses:

```json
{
  "success": false,
  "message": "Error message description",
  "data": null
}
```

**Common HTTP Status Codes:**
- `200` - Success
- `201` - Created
- `400` - Bad Request (validation error)
- `401` - Unauthorized
- `404` - Not Found
- `422` - Validation Error
- `500` - Server Error

---

## Status Enums

### Quote Status
- `draft` - مسودة
- `sent` - مرسل
- `accepted` - مقبول
- `rejected` - مرفوض
- `expired` - منتهي الصلاحية

### Sales Order Status
- `pending` - معلق
- `confirmed` - مؤكد
- `processing` - قيد المعالجة
- `shipped` - مشحن
- `delivered` - تم التسليم
- `cancelled` - ملغي

### Payment Status
- `pending` - معلق
- `completed` - مكتمل
- `failed` - فشل
- `refunded` - مسترد

### Payroll Status
- `pending` - معلق
- `processed` - معالج
- `paid` - مدفوع

---

## Numbering Schemes

- **Quotes:** QT-000001, QT-000002, ...
- **Sales Orders:** SO-000001, SO-000002, ...
- **Payments:** PAY-000001, PAY-000002, ...
- **Purchase Receipts:** PR-000001, PR-000002, ...
- **Payrolls:** PAY-000001, PAY-000002, ...
- **Invoices:** INV-YYYYMMDD-0001, INV-YYYYMMDD-0002, ...

---

## Notes

- All monetary values are in decimal format (e.g., 100.00)
- All dates should be in YYYY-MM-DD format
- All endpoints require authentication via Bearer token
- Stock updates happen automatically when creating/updating purchase receipts
- Customer and supplier balances update automatically on payments and invoices
- Conversion operations (quote → sales order → invoice) preserve all data integrity
