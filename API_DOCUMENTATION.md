# Awan ERP API Documentation

## Overview
This document describes all the API endpoints for the Awan ERP system. All endpoints require authentication via Sanctum unless otherwise noted.

**Base URL:** `http://your-domain.com/api/v1`
**Authentication:** Bearer Token (Sanctum)
**Total Endpoints:** 150+

---

## Table of Contents
1. [Core ERP](#core-erp)
2. [Order Management](#order-management)
3. [RMA (Returns Management)](#rma-returns-management)
4. [Warehouse Management System (WMS)](#warehouse-management-system-wms)
5. [Advanced Analytics](#advanced-analytics)
6. [Notifications](#notifications)
7. [Workflows](#workflows)
8. [Audit Logs](#audit-logs)
9. [Error Responses](#error-responses)
10. [Status Enums](#status-enums)

---

## Core ERP

### Products (المنتجات)

#### Get Products
```
GET /api/v1/products
```

**Query Parameters:**
- `category_id` (optional): Filter by category ID
- `search` (optional): Search in name
- `is_active` (optional): Filter by active status

#### Create Product
```
POST /api/v1/products
```

#### Get Product by ID
```
GET /api/v1/products/{product}
```

#### Update Product
```
PUT /api/v1/products/{product}
```

#### Delete Product
```
DELETE /api/v1/products/{product}
```

### Categories (الفئات)

#### Get Categories
```
GET /api/v1/categories
```

#### Create Category
```
POST /api/v1/categories
```

#### Get Category by ID
```
GET /api/v1/categories/{category}
```

#### Update Category
```
PUT /api/v1/categories/{category}
```

#### Delete Category
```
DELETE /api/v1/categories/{category}
```

### Customers (العملاء)

#### Get Customers
```
GET /api/v1/customers
```

**Query Parameters:**
- `status` (optional): Filter by status
- `search` (optional): Search in name or email

#### Create Customer
```
POST /api/v1/customers
```

#### Get Customer by ID
```
GET /api/v1/customers/{customer}
```

#### Update Customer
```
PUT /api/v1/customers/{customer}
```

#### Delete Customer
```
DELETE /api/v1/customers/{customer}
```

### Suppliers (الموردين)

#### Get Suppliers
```
GET /api/v1/suppliers
```

#### Create Supplier
```
POST /api/v1/suppliers
```

#### Get Supplier by ID
```
GET /api/v1/suppliers/{supplier}
```

#### Update Supplier
```
PUT /api/v1/suppliers/{supplier}
```

#### Delete Supplier
```
DELETE /api/v1/suppliers/{supplier}
```

---

## Order Management

### Sales Orders (طلبات بيع)

#### Get Sales Orders
```
GET /api/v1/sales-orders
```

**Query Parameters:**
- `status` (optional): Filter by status (pending, confirmed, processing, shipped, delivered, cancelled)
- `customer_id` (optional): Filter by customer ID
- `channel_id` (optional): Filter by sales channel

#### Create Sales Order
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

#### Get Sales Order by ID
```
GET /api/v1/sales-orders/{salesOrder}
```

#### Update Sales Order
```
PUT /api/v1/sales-orders/{salesOrder}
```

#### Delete Sales Order
```
DELETE /api/v1/sales-orders/{salesOrder}
```

#### Update Order Status
```
PUT /api/v1/sales-orders/{salesOrder}/status
```

**Request Body:**
```json
{
  "status": "shipped",
  "notes": "Optional notes"
}
```

---

## RMA (Returns Management)

### Return Requests (طلبات الإرجاع)

#### Get RMA Requests
```
GET /api/v1/rma
```

**Query Parameters:**
- `status` (optional): Filter by status (pending, approved, rejected, completed, cancelled)
- `customer_id` (optional): Filter by customer ID

#### Create RMA Request
```
POST /api/v1/rma
```

**Request Body:**
```json
{
  "customer_id": 1,
  "order_id": 1,
  "reason": "Product defective",
  "return_type": "refund",
  "items": [
    {
      "product_id": 1,
      "quantity": 1,
      "reason": "Damaged packaging"
    }
  ]
}
```

#### Get RMA Request by ID
```
GET /api/v1/rma/{rma}
```

#### Update RMA Request
```
PUT /api/v1/rma/{rma}
```

#### Delete RMA Request
```
DELETE /api/v1/rma/{rma}
```

#### Approve RMA Request
```
POST /api/v1/rma/{rma}/approve
```

#### Reject RMA Request
```
POST /api/v1/rma/{rma}/reject
```

**Request Body:**
```json
{
  "reason": "Return period expired"
}
```

#### Process RMA Refund
```
POST /api/v1/rma/{rma}/process-refund
```

---

## Warehouse Management System (WMS)

### Warehouses (المستودعات)

#### Get Warehouses
```
GET /api/v1/wms/warehouses
```

#### Create Warehouse
```
POST /api/v1/wms/warehouses
```

#### Get Warehouse by ID
```
GET /api/v1/wms/warehouses/{warehouse}
```

#### Update Warehouse
```
PUT /api/v1/wms/warehouses/{warehouse}
```

#### Delete Warehouse
```
DELETE /api/v1/wms/warehouses/{warehouse}
```

### Warehouse Bins (أماكن التخزين)

#### Get Warehouse Bins
```
GET /api/v1/wms/bins
```

**Query Parameters:**
- `warehouse_id` (optional): Filter by warehouse
- `zone` (optional): Filter by zone

#### Create Warehouse Bin
```
POST /api/v1/wms/bins
```

#### Get Bin by ID
```
GET /api/v1/wms/bins/{bin}
```

#### Update Bin
```
PUT /api/v1/wms/bins/{bin}
```

#### Delete Bin
```
DELETE /api/v1/wms/bins/{bin}
```

### Picking Lists (قوائم الانتقاء)

#### Get Picking Lists
```
GET /api/v1/wms/picking-lists
```

**Query Parameters:**
- `status` (optional): Filter by status (pending, in_progress, completed, cancelled)
- `warehouse_id` (optional): Filter by warehouse

#### Create Picking List
```
POST /api/v1/wms/picking-lists
```

#### Get Picking List by ID
```
GET /api/v1/wms/picking-lists/{pickingList}
```

#### Update Picking List
```
PUT /api/v1/wms/picking-lists/{pickingList}
```

#### Start Picking
```
POST /api/v1/wms/picking-lists/{pickingList}/start
```

#### Complete Picking
```
POST /api/v1/wms/picking-lists/{pickingList}/complete
```

#### Cancel Picking List
```
POST /api/v1/wms/picking-lists/{pickingList}/cancel
```

### Packing Lists (قوائم التعبئة)

#### Get Packing Lists
```
GET /api/v1/wms/packing-lists
```

**Query Parameters:**
- `status` (optional): Filter by status (pending, in_progress, completed, cancelled)
- `warehouse_id` (optional): Filter by warehouse

#### Create Packing List
```
POST /api/v1/wms/packing-lists
```

#### Get Packing List by ID
```
GET /api/v1/wms/packing-lists/{packingList}
```

#### Update Packing List
```
PUT /api/v1/wms/packing-lists/{packingList}
```

#### Start Packing
```
POST /api/v1/wms/packing-lists/{packingList}/start
```

#### Complete Packing
```
POST /api/v1/wms/packing-lists/{packingList}/complete
```

#### Cancel Packing List
```
POST /api/v1/wms/packing-lists/{packingList}/cancel
```

### Cycle Counts (الجرد الدوري)

#### Get Cycle Counts
```
GET /api/v1/wms/cycle-counts
```

**Query Parameters:**
- `status` (optional): Filter by status (pending, in_progress, completed, cancelled)
- `warehouse_id` (optional): Filter by warehouse

#### Create Cycle Count
```
POST /api/v1/wms/cycle-counts
```

#### Get Cycle Count by ID
```
GET /api/v1/wms/cycle-counts/{cycleCount}
```

#### Update Cycle Count
```
PUT /api/v1/wms/cycle-counts/{cycleCount}
```

#### Start Cycle Count
```
POST /api/v1/wms/cycle-counts/{cycleCount}/start
```

#### Complete Cycle Count
```
POST /api/v1/wms/cycle-counts/{cycleCount}/complete
```

#### Cancel Cycle Count
```
POST /api/v1/wms/cycle-counts/{cycleCount}/cancel
```

### Warehouse Performance Metrics

#### Get Warehouse Performance
```
GET /api/v1/wms/performance
```

**Query Parameters:**
- `warehouse_id` (optional): Filter by warehouse
- `start_date` (optional): Start date for metrics
- `end_date` (optional): End date for metrics

**Response:**
```json
{
  "picking_accuracy": 98.5,
  "packing_accuracy": 99.2,
  "cycle_count_accuracy": 97.8,
  "average_picking_time": 15.5,
  "average_packing_time": 12.3,
  "throughput": 450
}
```

---

## Advanced Analytics

### Sales Analytics (تحليلات المبيعات)

#### Get Sales Overview
```
GET /api/v1/analytics/sales/overview
```

**Query Parameters:**
- `start_date` (optional): Start date
- `end_date` (optional): End date

**Response:**
```json
{
  "total_revenue": 150000.00,
  "total_orders": 250,
  "average_order_value": 600.00,
  "growth_rate": 12.5
}
```

#### Get Sales Trends
```
GET /api/v1/analytics/sales/trends
```

#### Get Top Products
```
GET /api/v1/analytics/sales/top-products
```

#### Get Sales Forecast
```
GET /api/v1/analytics/sales/forecast
```

**Query Parameters:**
- `period` (optional): Forecast period (7, 30, 90 days)

### Inventory Analytics (تحليلات المخزون)

#### Get Inventory Overview
```
GET /api/v1/analytics/inventory/overview
```

**Response:**
```json
{
  "total_products": 500,
  "total_stock": 15000,
  "low_stock_count": 25,
  "out_of_stock_count": 5,
  "inventory_value": 750000.00
}
```

#### Get Inventory Turnover
```
GET /api/v1/analytics/inventory/turnover
```

#### Get ABC Analysis
```
GET /api/v1/analytics/inventory/abc-analysis
```

#### Get Low Stock Alerts
```
GET /api/v1/analytics/inventory/low-stock
```

### Warehouse Analytics (تحليلات المستودع)

#### Get Warehouse Utilization
```
GET /api/v1/analytics/warehouse/utilization
```

#### Get Warehouse Performance
```
GET /api/v1/analytics/warehouse/performance
```

#### Get Picking Efficiency
```
GET /api/v1/analytics/warehouse/picking-efficiency
```

### Financial Analytics (التحليلات المالية)

#### Get Profit & Loss
```
GET /api/v1/analytics/financial/pnl
```

**Query Parameters:**
- `start_date` (optional): Start date
- `end_date` (optional): End date

**Response:**
```json
{
  "revenue": 250000.00,
  "cost_of_goods_sold": 150000.00,
  "gross_profit": 100000.00,
  "operating_expenses": 45000.00,
  "net_profit": 55000.00,
  "profit_margin": 22.0
}
```

#### Get Cash Flow
```
GET /api/v1/analytics/financial/cash-flow
```

#### Get Financial Ratios
```
GET /api/v1/analytics/financial/ratios
```

**Response:**
```json
{
  "current_ratio": 2.5,
  "quick_ratio": 1.8,
  "debt_to_equity": 0.4,
  "inventory_turnover": 8.5,
  "profit_margin": 22.0
}
```

### Metrics (مؤشرات الأداء)

#### Get All Metrics
```
GET /api/v1/analytics/metrics
```

#### Get Metric by ID
```
GET /api/v1/analytics/metrics/{metric}
```

#### Create Metric
```
POST /api/v1/analytics/metrics
```

#### Update Metric
```
PUT /api/v1/analytics/metrics/{metric}
```

#### Delete Metric
```
DELETE /api/v1/analytics/metrics/{metric}
```

### Reports (التقارير)

#### Get All Reports
```
GET /api/v1/analytics/reports
```

#### Get Report by ID
```
GET /api/v1/analytics/reports/{report}
```

#### Create Report
```
POST /api/v1/analytics/reports
```

**Request Body:**
```json
{
  "name": "Monthly Sales Report",
  "type": "sales",
  "query_config": {
    "period": "monthly",
    "group_by": "category"
  }
}
```

#### Execute Report
```
POST /api/v1/analytics/reports/{report}/execute
```

#### Export Report
```
GET /api/v1/analytics/reports/{report}/export
```

**Query Parameters:**
- `format` (optional): Export format (csv, json, pdf)

### Dashboards (لوحات المعلومات)

#### Get All Dashboards
```
GET /api/v1/analytics/dashboards
```

#### Get Dashboard by ID
```
GET /api/v1/analytics/dashboards/{dashboard}
```

#### Create Dashboard
```
POST /api/v1/analytics/dashboards
```

**Request Body:**
```json
{
  "name": "Executive Dashboard",
  "type": "executive",
  "layout": "grid"
}
```

#### Update Dashboard
```
PUT /api/v1/analytics/dashboards/{dashboard}
```

#### Delete Dashboard
```
DELETE /api/v1/analytics/dashboards/{dashboard}
```

#### Add Widget to Dashboard
```
POST /api/v1/analytics/dashboards/{dashboard}/widgets
```

#### Update Widget
```
PUT /api/v1/analytics/widgets/{widget}
```

#### Delete Widget
```
DELETE /api/v1/analytics/widgets/{widget}
```

---

## Notifications (الإشعارات)

### User Notifications

#### Get User Notifications
```
GET /api/v1/notifications
```

**Query Parameters:**
- `unread_only` (optional): Filter unread only
- `type` (optional): Filter by type

#### Get Notification by ID
```
GET /api/v1/notifications/{id}
```

#### Mark as Read
```
POST /api/v1/notifications/{id}/read
```

#### Mark All as Read
```
POST /api/v1/notifications/read-all
```

#### Get Unread Count
```
GET /api/v1/notifications/unread-count
```

#### Delete Notification
```
DELETE /api/v1/notifications/{id}
```

### Notification Preferences

#### Get User Preferences
```
GET /api/v1/notifications/preferences
```

**Response:**
```json
{
  "notification_type": "all",
  "email_enabled": true,
  "sms_enabled": false,
  "push_enabled": true,
  "in_app_enabled": true
}
```

#### Update Preferences
```
PUT /api/v1/notifications/preferences
```

**Request Body:**
```json
{
  "notification_type": "order",
  "email_enabled": true,
  "sms_enabled": false,
  "push_enabled": true,
  "in_app_enabled": true
}
```

### Notification Templates

#### Get Templates
```
GET /api/v1/notifications/templates
```

**Query Parameters:**
- `type` (optional): Filter by type (email, sms, push, in_app)
- `active_only` (optional): Filter active only

#### Get Template by ID
```
GET /api/v1/notifications/templates/{id}
```

#### Create Template
```
POST /api/v1/notifications/templates
```

**Request Body:**
```json
{
  "template_key": "order_confirmed",
  "name": "Order Confirmed",
  "name_ar": "تأكيد الطلب",
  "subject": "Your order #{order_number} has been confirmed",
  "subject_ar": "تم تأكيد طلبك #{order_number}",
  "body": "Dear customer, your order has been confirmed.",
  "body_ar": "عزيزي العميل، تم تأكيد طلبك.",
  "type": "email",
  "variables": ["order_number", "customer_name"],
  "is_active": true
}
```

#### Update Template
```
PUT /api/v1/notifications/templates/{id}
```

#### Delete Template
```
DELETE /api/v1/notifications/templates/{id}
```

### Send Notifications

#### Send Notification
```
POST /api/v1/notifications/send
```

**Request Body:**
```json
{
  "user_id": 1,
  "title": "New Order",
  "message": "You have received a new order",
  "type": "order",
  "data": {
    "order_id": 123
  }
}
```

#### Send Bulk Notification
```
POST /api/v1/notifications/send-bulk
```

**Request Body:**
```json
{
  "user_ids": [1, 2, 3],
  "title": "System Update",
  "message": "System maintenance scheduled",
  "type": "system"
}
```

---

## Workflows (سير العمل)

### Workflows

#### Get Workflows
```
GET /api/v1/workflows
```

**Query Parameters:**
- `status` (optional): Filter by status (active, inactive, draft)
- `trigger_type` (optional): Filter by trigger type

#### Get Workflow by ID
```
GET /api/v1/workflows/{id}
```

#### Create Workflow
```
POST /api/v1/workflows
```

**Request Body:**
```json
{
  "name": "Order Confirmation Process",
  "name_ar": "عملية تأكيد الطلب",
  "description": "Automatically sends confirmation and processes new orders",
  "trigger_type": "order_created",
  "trigger_config": {
    "auto_execute": true
  },
  "status": "active"
}
```

**Trigger Types:**
- `manual` - Manual execution
- `order_created` - When order is created
- `order_status_changed` - When order status changes
- `inventory_low` - When inventory falls below minimum
- `scheduled` - Scheduled execution
- `webhook` - Webhook trigger

#### Update Workflow
```
PUT /api/v1/workflows/{id}
```

#### Delete Workflow
```
DELETE /api/v1/workflows/{id}
```

### Workflow Execution

#### Execute Workflow
```
POST /api/v1/workflows/{id}/execute
```

**Request Body:**
```json
{
  "input_data": {
    "order_id": 123,
    "customer_id": 1
  }
}
```

#### Get Execution History
```
GET /api/v1/workflows/{id}/executions
```

#### Get Workflow Statistics
```
GET /api/v1/workflows/{id}/statistics
```

**Response:**
```json
{
  "total_executions": 150,
  "completed": 140,
  "failed": 8,
  "running": 2,
  "cancelled": 0,
  "success_rate": 93.33
}
```

#### Cancel Execution
```
POST /api/v1/workflows/executions/{executionId}/cancel
```

#### Retry Execution
```
POST /api/v1/workflows/executions/{executionId}/retry
```

### Workflow Steps

#### Get Workflow Steps
```
GET /api/v1/workflows/{workflowId}/steps
```

#### Create Step
```
POST /api/v1/workflows/{workflowId}/steps
```

**Request Body:**
```json
{
  "name": "Send Confirmation Email",
  "name_ar": "إرسال بريد التأكيد",
  "action_type": "notification",
  "action_config": {
    "template_key": "order_confirmed",
    "channel": "email"
  },
  "order": 1,
  "is_required": true
}
```

**Action Types:**
- `task` - Create task
- `notification` - Send notification
- `email` - Send email
- `api_call` - Make API call
- `update_field` - Update field
- `approval` - Request approval
- `condition` - Conditional check

#### Update Step
```
PUT /api/v1/workflows/steps/{id}
```

#### Delete Step
```
DELETE /api/v1/workflows/steps/{id}
```

#### Reorder Steps
```
POST /api/v1/workflows/{workflowId}/steps/reorder
```

**Request Body:**
```json
{
  "steps": [
    {"id": 1, "order": 1},
    {"id": 2, "order": 2}
  ]
}
```

---

## Audit Logs (سجلات التدقيق)

### Audit Logs

#### Get Audit Logs
```
GET /api/v1/audit
```

**Query Parameters:**
- `action` (optional): Filter by action (create, update, delete, view, login, logout, etc.)
- `module` (optional): Filter by module (products, inventory, orders, etc.)
- `user_id` (optional): Filter by user ID
- `entity_type` (optional): Filter by entity type
- `entity_id` (optional): Filter by entity ID
- `days` (optional): Filter by recent days

#### Get Audit Log by ID
```
GET /api/v1/audit/{id}
```

### Entity Logs

#### Get Entity Logs
```
GET /api/v1/audit/entity-logs
```

**Query Parameters:**
- `entity_type` (required): Entity type (e.g., App\Models\Product)
- `entity_id` (required): Entity ID

**Response:**
```json
[
  {
    "id": 1,
    "action": "update",
    "action_text": "تحديث",
    "description": "Updated product",
    "user": "Admin",
    "changes": {
      "price": {
        "old": 100.00,
        "new": 120.00
      }
    },
    "created_at": "2026-06-23 12:00:00"
  }
]
```

### User Activity

#### Get User Logs
```
GET /api/v1/audit/user-logs/{userId}
```

#### Get User Activity Summary
```
GET /api/v1/audit/user-summary/{userId}
```

**Query Parameters:**
- `days` (optional): Period in days (default: 30)

**Response:**
```json
{
  "total_actions": 150,
  "by_action": {
    "create": 45,
    "update": 60,
    "delete": 15,
    "view": 30
  },
  "by_module": {
    "products": 50,
    "orders": 40,
    "inventory": 30,
    "customers": 30
  },
  "last_activity": "2026-06-23 18:00:00",
  "most_active_module": "products"
}
```

#### Get My Activity Summary
```
GET /api/v1/audit/my-summary
```

### Module Logs

#### Get Module Logs
```
GET /api/v1/audit/module-logs/{module}
```

**Modules:** products, inventory, orders, customers, suppliers, warehouse, finance, users, settings, workflows, reports

### Recent Activity

#### Get Recent Logs
```
GET /api/v1/audit/recent
```

**Query Parameters:**
- `days` (optional): Period in days (default: 7)

#### Get Today's Logs
```
GET /api/v1/audit/today
```

### Activity Timeline

#### Get Activity Timeline
```
GET /api/v1/audit/activity-timeline
```

**Query Parameters:**
- `entity_type` (required): Entity type
- `entity_id` (required): Entity ID

### Statistics

#### Get Audit Statistics
```
GET /api/v1/audit/statistics
```

**Query Parameters:**
- `days` (optional): Period in days (default: 30)

**Response:**
```json
{
  "total_logs": 5000,
  "by_action": {
    "create": 1500,
    "update": 2000,
    "delete": 500,
    "view": 1000
  },
  "by_module": {
    "products": 1200,
    "orders": 800,
    "inventory": 600,
    "customers": 400
  },
  "by_user": {
    "1": 2000,
    "2": 1500,
    "3": 1500
  },
  "today_logs": 150
}
```

### Maintenance

#### Cleanup Old Logs
```
POST /api/v1/audit/cleanup
```

**Request Body:**
```json
{
  "days": 90
}
```

**Response:**
```json
{
  "deleted": 2500
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
- `403` - Forbidden
- `404` - Not Found
- `422` - Validation Error
- `500` - Server Error

---

## Status Enums

### Order Status
- `pending` - معلق
- `confirmed` - مؤكد
- `processing` - قيد المعالجة
- `shipped` - مشحن
- `delivered` - تم التسليم
- `cancelled` - ملغي

### RMA Status
- `pending` - معلق
- `approved` - موافق عليه
- `rejected` - مرفوض
- `completed` - مكتمل
- `cancelled` - ملغي

### Workflow Status
- `active` - نشط
- `inactive` - غير نشط
- `draft` - مسودة

### Workflow Execution Status
- `pending` - في الانتظار
- `running` - قيد التشغيل
- `completed` - مكتمل
- `failed` - فشل
- `cancelled` - ملغي

### Picking/Packing Status
- `pending` - معلق
- `in_progress` - قيد التنفيذ
- `completed` - مكتمل
- `cancelled` - ملغي

### Cycle Count Status
- `pending` - معلق
- `in_progress` - قيد التنفيذ
- `completed` - مكتمل
- `cancelled` - ملغي

### Audit Actions
- `create` - إنشاء
- `update` - تحديث
- `delete` - حذف
- `view` - عرض
- `login` - تسجيل دخول
- `logout` - تسجيل خروج
- `export` - تصدير
- `import` - استيراد
- `approve` - موافقة
- `reject` - رفض
- `cancel` - إلغاء

---

## Authentication

### Login
```
POST /api/v1/login
```

**Request Body:**
```json
{
  "email": "admin@admin.com",
  "password": "admin123"
}
```

**Response:**
```json
{
  "success": true,
  "message": "Login successful",
  "data": {
    "token": "1|abcdef123456...",
    "user": {
      "id": 1,
      "name": "Admin",
      "email": "admin@admin.com"
    }
  }
}
```

### Logout
```
POST /api/v1/logout
```

**Headers:**
```
Authorization: Bearer {token}
```

---

## Notes

- All monetary values are in decimal format (e.g., 100.00)
- All dates should be in YYYY-MM-DD format
- All endpoints require authentication via Bearer token
- Pagination is available on most list endpoints (default: 20 per page)
- Multi-language support: All responses include Arabic translations where applicable
- Audit logging is automatic for all CRUD operations
- Workflow execution is asynchronous for long-running processes
