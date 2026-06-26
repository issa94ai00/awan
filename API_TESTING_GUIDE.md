# API Testing Guide - Awan ERP

## Overview
This guide provides comprehensive instructions for testing all API endpoints of the Awan ERP system.

## Prerequisites

### 1. Authentication
Most endpoints require authentication. First, obtain a Bearer token:

```bash
# Login to get token
curl -X POST http://localhost:8000/api/v1/login \
  -H "Content-Type: application/json" \
  -d '{
    "email": "admin@admin.com",
    "password": "admin123"
  }'
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

**Use the token in subsequent requests:**
```bash
curl -X GET http://localhost:8000/api/v1/products \
  -H "Authorization: Bearer YOUR_TOKEN_HERE"
```

### 2. Testing Tools
- **Postman**: Recommended for manual testing
- **Insomnia**: Alternative REST client
- **cURL**: Command-line testing
- **PHPUnit**: Automated testing
- **Laravel Dusk**: Browser testing

## Core ERP API Tests

### Products

#### Test 1: Get All Products
```bash
curl -X GET http://localhost:8000/api/v1/products \
  -H "Authorization: Bearer YOUR_TOKEN"
```

#### Test 2: Get Product by ID
```bash
curl -X GET http://localhost:8000/api/v1/products/1 \
  -H "Authorization: Bearer YOUR_TOKEN"
```

#### Test 3: Create Product
```bash
curl -X POST http://localhost:8000/api/v1/products \
  -H "Authorization: Bearer YOUR_TOKEN" \
  -H "Content-Type: application/json" \
  -d '{
    "category_id": 1,
    "name_en": "Test Product",
    "name_ar": "منتج تجريبي",
    "description_en": "Test description",
    "description_ar": "وصف تجريبي",
    "price": 100.00,
    "is_active": true
  }'
```

#### Test 4: Update Product
```bash
curl -X PUT http://localhost:8000/api/v1/products/1 \
  -H "Authorization: Bearer YOUR_TOKEN" \
  -H "Content-Type: application/json" \
  -d '{
    "price": 150.00
  }'
```

#### Test 5: Delete Product
```bash
curl -X DELETE http://localhost:8000/api/v1/products/1 \
  -H "Authorization: Bearer YOUR_TOKEN"
```

### Categories

#### Test 1: Get All Categories
```bash
curl -X GET http://localhost:8000/api/v1/categories \
  -H "Authorization: Bearer YOUR_TOKEN"
```

#### Test 2: Create Category
```bash
curl -X POST http://localhost:8000/api/v1/categories \
  -H "Authorization: Bearer YOUR_TOKEN" \
  -H "Content-Type: application/json" \
  -d '{
    "name_en": "Test Category",
    "name_ar": "فئة تجريبية",
    "description": "Test category description"
  }'
```

### Customers

#### Test 1: Get All Customers
```bash
curl -X GET http://localhost:8000/api/v1/customers \
  -H "Authorization: Bearer YOUR_TOKEN"
```

#### Test 2: Create Customer
```bash
curl -X POST http://localhost:8000/api/v1/customers \
  -H "Authorization: Bearer YOUR_TOKEN" \
  -H "Content-Type: application/json" \
  -d '{
    "name": "Test Customer",
    "email": "test@example.com",
    "phone": "+966500000000",
    "address": "Test Address",
    "city": "Riyadh",
    "country": "Saudi Arabia",
    "status": "active"
  }'
```

## Order Management API Tests

### Sales Orders

#### Test 1: Get All Sales Orders
```bash
curl -X GET http://localhost:8000/api/v1/sales-orders \
  -H "Authorization: Bearer YOUR_TOKEN"
```

#### Test 2: Create Sales Order
```bash
curl -X POST http://localhost:8000/api/v1/sales-orders \
  -H "Authorization: Bearer YOUR_TOKEN" \
  -H "Content-Type: application/json" \
  -d '{
    "customer_id": 1,
    "order_date": "2026-06-23",
    "expected_delivery": "2026-06-30",
    "items": [
      {
        "product_id": 1,
        "quantity": 5,
        "unit_price": 100.00
      }
    ]
  }'
```

#### Test 3: Update Order Status
```bash
curl -X PUT http://localhost:8000/api/v1/sales-orders/1/status \
  -H "Authorization: Bearer YOUR_TOKEN" \
  -H "Content-Type: application/json" \
  -d '{
    "status": "confirmed",
    "notes": "Order confirmed"
  }'
```

## RMA API Tests

### Return Requests

#### Test 1: Get All RMA Requests
```bash
curl -X GET http://localhost:8000/api/v1/rma \
  -H "Authorization: Bearer YOUR_TOKEN"
```

#### Test 2: Create RMA Request
```bash
curl -X POST http://localhost:8000/api/v1/rma \
  -H "Authorization: Bearer YOUR_TOKEN" \
  -H "Content-Type: application/json" \
  -d '{
    "customer_id": 1,
    "order_id": 1,
    "reason": "Product defective",
    "return_type": "refund",
    "items": [
      {
        "product_id": 1,
        "quantity": 1,
        "reason": "Damaged"
      }
    ]
  }'
```

#### Test 3: Approve RMA Request
```bash
curl -X POST http://localhost:8000/api/v1/rma/1/approve \
  -H "Authorization: Bearer YOUR_TOKEN"
```

## WMS API Tests

### Warehouses

#### Test 1: Get All Warehouses
```bash
curl -X GET http://localhost:8000/api/v1/wms/warehouses \
  -H "Authorization: Bearer YOUR_TOKEN"
```

#### Test 2: Create Warehouse
```bash
curl -X POST http://localhost:8000/api/v1/wms/warehouses \
  -H "Authorization: Bearer YOUR_TOKEN" \
  -H "Content-Type: application/json" \
  -d '{
    "name": "Test Warehouse",
    "code": "WH-TEST",
    "address": "Test Address",
    "city": "Riyadh",
    "country": "Saudi Arabia",
    "location_type": "warehouse",
    "capacity": 5000
  }'
```

### Warehouse Bins

#### Test 1: Get All Bins
```bash
curl -X GET http://localhost:8000/api/v1/wms/bins \
  -H "Authorization: Bearer YOUR_TOKEN"
```

#### Test 2: Create Bin
```bash
curl -X POST http://localhost:8000/api/v1/wms/bins \
  -H "Authorization: Bearer YOUR_TOKEN" \
  -H "Content-Type: application/json" \
  -d '{
    "warehouse_id": 1,
    "bin_code": "TEST-001",
    "zone": "A",
    "rack": "R1",
    "shelf": "S1",
    "max_weight": 500
  }'
```

### Picking Lists

#### Test 1: Get All Picking Lists
```bash
curl -X GET http://localhost:8000/api/v1/wms/picking-lists \
  -H "Authorization: Bearer YOUR_TOKEN"
```

#### Test 2: Create Picking List
```bash
curl -X POST http://localhost:8000/api/v1/wms/picking-lists \
  -H "Authorization: Bearer YOUR_TOKEN" \
  -H "Content-Type: application/json" \
  -d '{
    "warehouse_id": 1,
    "order_id": 1,
    "priority": "normal"
  }'
```

#### Test 3: Start Picking
```bash
curl -X POST http://localhost:8000/api/v1/wms/picking-lists/1/start \
  -H "Authorization: Bearer YOUR_TOKEN"
```

#### Test 4: Complete Picking
```bash
curl -X POST http://localhost:8000/api/v1/wms/picking-lists/1/complete \
  -H "Authorization: Bearer YOUR_TOKEN"
```

### Cycle Counts

#### Test 1: Get All Cycle Counts
```bash
curl -X GET http://localhost:8000/api/v1/wms/cycle-counts \
  -H "Authorization: Bearer YOUR_TOKEN"
```

#### Test 2: Create Cycle Count
```bash
curl -X POST http://localhost:8000/api/v1/wms/cycle-counts \
  -H "Authorization: Bearer YOUR_TOKEN" \
  -H "Content-Type: application/json" \
  -d '{
    "warehouse_id": 1,
    "zone": "A",
    "count_date": "2026-06-23"
  }'
```

## Analytics API Tests

### Sales Analytics

#### Test 1: Get Sales Overview
```bash
curl -X GET "http://localhost:8000/api/v1/analytics/sales/overview?start_date=2026-06-01&end_date=2026-06-30" \
  -H "Authorization: Bearer YOUR_TOKEN"
```

#### Test 2: Get Sales Trends
```bash
curl -X GET "http://localhost:8000/api/v1/analytics/sales/trends?period=30" \
  -H "Authorization: Bearer YOUR_TOKEN"
```

### Inventory Analytics

#### Test 1: Get Inventory Overview
```bash
curl -X GET http://localhost:8000/api/v1/analytics/inventory/overview \
  -H "Authorization: Bearer YOUR_TOKEN"
```

#### Test 2: Get Low Stock Alerts
```bash
curl -X GET http://localhost:8000/api/v1/analytics/inventory/low-stock \
  -H "Authorization: Bearer YOUR_TOKEN"
```

### Financial Analytics

#### Test 1: Get Profit & Loss
```bash
curl -X GET "http://localhost:8000/api/v1/analytics/financial/pnl?start_date=2026-06-01&end_date=2026-06-30" \
  -H "Authorization: Bearer YOUR_TOKEN"
```

#### Test 2: Get Financial Ratios
```bash
curl -X GET http://localhost:8000/api/v1/analytics/financial/ratios \
  -H "Authorization: Bearer YOUR_TOKEN"
```

## Notifications API Tests

### User Notifications

#### Test 1: Get User Notifications
```bash
curl -X GET http://localhost:8000/api/v1/notifications \
  -H "Authorization: Bearer YOUR_TOKEN"
```

#### Test 2: Mark as Read
```bash
curl -X POST http://localhost:8000/api/v1/notifications/1/read \
  -H "Authorization: Bearer YOUR_TOKEN"
```

#### Test 3: Get Unread Count
```bash
curl -X GET http://localhost:8000/api/v1/notifications/unread-count \
  -H "Authorization: Bearer YOUR_TOKEN"
```

### Notification Templates

#### Test 1: Get All Templates
```bash
curl -X GET http://localhost:8000/api/v1/notifications/templates \
  -H "Authorization: Bearer YOUR_TOKEN"
```

#### Test 2: Create Template
```bash
curl -X POST http://localhost:8000/api/v1/notifications/templates \
  -H "Authorization: Bearer YOUR_TOKEN" \
  -H "Content-Type: application/json" \
  -d '{
    "template_key": "test_template",
    "name": "Test Template",
    "name_ar": "قالب تجريبي",
    "subject": "Test Subject",
    "subject_ar": "موضوع تجريبي",
    "body": "Test body with {variable}",
    "body_ar": "نص تجريبي مع {variable}",
    "type": "email",
    "variables": ["variable"],
    "is_active": true
  }'
```

### Send Notifications

#### Test 1: Send Notification
```bash
curl -X POST http://localhost:8000/api/v1/notifications/send \
  -H "Authorization: Bearer YOUR_TOKEN" \
  -H "Content-Type: application/json" \
  -d '{
    "user_id": 1,
    "title": "Test Notification",
    "message": "This is a test notification",
    "type": "info",
    "data": {
      "test": "data"
    }
  }'
```

## Workflows API Tests

### Workflows

#### Test 1: Get All Workflows
```bash
curl -X GET http://localhost:8000/api/v1/workflows \
  -H "Authorization: Bearer YOUR_TOKEN"
```

#### Test 2: Create Workflow
```bash
curl -X POST http://localhost:8000/api/v1/workflows \
  -H "Authorization: Bearer YOUR_TOKEN" \
  -H "Content-Type: application/json" \
  -d '{
    "name": "Test Workflow",
    "name_ar": "سير عمل تجريبي",
    "description": "Test workflow description",
    "trigger_type": "manual",
    "trigger_config": {},
    "status": "active"
  }'
```

#### Test 3: Execute Workflow
```bash
curl -X POST http://localhost:8000/api/v1/workflows/1/execute \
  -H "Authorization: Bearer YOUR_TOKEN" \
  -H "Content-Type: application/json" \
  -d '{
    "input_data": {
      "test": "data"
    }
  }'
```

#### Test 4: Get Workflow Statistics
```bash
curl -X GET http://localhost:8000/api/v1/workflows/1/statistics \
  -H "Authorization: Bearer YOUR_TOKEN"
```

### Workflow Steps

#### Test 1: Get Workflow Steps
```bash
curl -X GET http://localhost:8000/api/v1/workflows/1/steps \
  -H "Authorization: Bearer YOUR_TOKEN"
```

#### Test 2: Create Step
```bash
curl -X POST http://localhost:8000/api/v1/workflows/1/steps \
  -H "Authorization: Bearer YOUR_TOKEN" \
  -H "Content-Type: application/json" \
  -d '{
    "name": "Test Step",
    "name_ar": "خطوة تجريبية",
    "action_type": "notification",
    "action_config": {
      "template_key": "test_template",
      "channel": "in_app"
    },
    "order": 1,
    "is_required": true
  }'
```

## Audit Logs API Tests

### Audit Logs

#### Test 1: Get All Audit Logs
```bash
curl -X GET http://localhost:8000/api/v1/audit \
  -H "Authorization: Bearer YOUR_TOKEN"
```

#### Test 2: Get Entity Logs
```bash
curl -X GET "http://localhost:8000/api/v1/audit/entity-logs?entity_type=App%5CModels%5CProduct&entity_id=1" \
  -H "Authorization: Bearer YOUR_TOKEN"
```

#### Test 3: Get User Activity Summary
```bash
curl -X GET http://localhost:8000/api/v1/audit/user-summary/1 \
  -H "Authorization: Bearer YOUR_TOKEN"
```

#### Test 4: Get Audit Statistics
```bash
curl -X GET "http://localhost:8000/api/v1/audit/statistics?days=30" \
  -H "Authorization: Bearer YOUR_TOKEN"
```

#### Test 5: Get Recent Logs
```bash
curl -X GET "http://localhost:8000/api/v1/audit/recent?days=7" \
  -H "Authorization: Bearer YOUR_TOKEN"
```

## Automated Testing with PHPUnit

### Create Test File

```bash
php artisan make:test Api/ProductApiTest
```

### Example Test

```php
<?php

namespace Tests\Feature\Api;

use Tests\TestCase;
use App\Models\User;
use App\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;

class ProductApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $user = User::factory()->create();
        $this->actingAs($user, 'sanctum');
    }

    public function test_can_get_all_products()
    {
        $response = $this->getJson('/api/v1/products');
        
        $response->assertStatus(200)
                 ->assertJsonStructure([
                     'success',
                     'message',
                     'data'
                 ]);
    }

    public function test_can_create_product()
    {
        $data = [
            'category_id' => 1,
            'name_en' => 'Test Product',
            'name_ar' => 'منتج تجريبي',
            'price' => 100.00,
        ];

        $response = $this->postJson('/api/v1/products', $data);
        
        $response->assertStatus(201)
                 ->assertJson([
                     'success' => true
                 ]);
    }

    public function test_can_update_product()
    {
        $product = Product::factory()->create();
        
        $response = $this->putJson("/api/v1/products/{$product->id}", [
            'price' => 150.00
        ]);
        
        $response->assertStatus(200);
    }

    public function test_can_delete_product()
    {
        $product = Product::factory()->create();
        
        $response = $this->deleteJson("/api/v1/products/{$product->id}");
        
        $response->assertStatus(200);
    }
}
```

### Run Tests

```bash
# Run all tests
php artisan test

# Run specific test file
php artisan test tests/Feature/Api/ProductApiTest.php

# Run with coverage
php artisan test --coverage
```

## Postman Collection

### Import Collection
1. Download the Postman collection JSON
2. Import into Postman
3. Set environment variables:
   - `base_url`: http://localhost:8000
   - `token`: Your authentication token

### Collection Structure
- **Authentication**: Login endpoint
- **Core ERP**: Products, Categories, Customers, Suppliers
- **Orders**: Sales orders, status updates
- **RMA**: Return requests, approvals
- **WMS**: Warehouses, bins, picking, packing, cycle counts
- **Analytics**: Sales, inventory, warehouse, financial analytics
- **Notifications**: User notifications, templates, sending
- **Workflows**: Workflows, steps, execution
- **Audit**: Audit logs, statistics, activity

## Common Issues & Solutions

### 401 Unauthorized
- Ensure you have a valid token
- Check token hasn't expired
- Verify Authorization header format

### 422 Validation Error
- Check request body matches expected format
- Verify all required fields are included
- Check data types (numbers vs strings)

### 404 Not Found
- Verify endpoint URL is correct
- Check resource ID exists
- Ensure resource hasn't been deleted

### 500 Server Error
- Check Laravel logs: `storage/logs/laravel.log`
- Verify database connection
- Check queue worker is running

## Testing Checklist

### Core ERP
- [ ] Products CRUD operations
- [ ] Categories CRUD operations
- [ ] Customers CRUD operations
- [ ] Suppliers CRUD operations

### Order Management
- [ ] Create sales order
- [ ] Update order status
- [ ] Get order history
- [ ] Order filtering

### RMA
- [ ] Create return request
- [ ] Approve/reject returns
- [ ] Process refunds
- [ ] Return status tracking

### WMS
- [ ] Warehouse management
- [ ] Bin management
- [ ] Picking list lifecycle
- [ ] Packing list lifecycle
- [ ] Cycle count operations
- [ ] Performance metrics

### Analytics
- [ ] Sales analytics endpoints
- [ ] Inventory analytics endpoints
- [ ] Warehouse analytics endpoints
- [ ] Financial analytics endpoints
- [ ] Report generation
- [ ] Dashboard widgets

### Notifications
- [ ] User notifications
- [ ] Notification preferences
- [ ] Template management
- [ ] Send notifications
- [ ] Bulk sending

### Workflows
- [ ] Workflow CRUD
- [ ] Workflow execution
- [ ] Step management
- [ ] Execution history
- [ ] Statistics

### Audit Logs
- [ ] Get audit logs
- [ ] Entity tracking
- [ ] User activity
- [ ] Statistics
- [ ] Activity timeline

## Performance Testing

### Load Testing with Apache Bench
```bash
# Test products endpoint
ab -n 1000 -c 10 -H "Authorization: Bearer YOUR_TOKEN" \
   http://localhost:8000/api/v1/products
```

### Response Time Benchmarks
- **Simple GET**: < 100ms
- **Complex GET with joins**: < 200ms
- **POST/PUT**: < 300ms
- **DELETE**: < 100ms

## Security Testing

### SQL Injection
- Test with malicious input in search fields
- Verify parameterized queries are used

### XSS
- Test with script tags in text fields
- Verify output is escaped

### CSRF
- Ensure CSRF protection is enabled
- Test with invalid tokens

### Rate Limiting
- Test endpoint rate limits
- Verify throttling works correctly

## Continuous Integration

### GitHub Actions Example
```yaml
name: API Tests

on: [push, pull_request]

jobs:
  test:
    runs-on: ubuntu-latest
    
    steps:
    - uses: actions/checkout@v2
    - name: Setup PHP
      uses: shivammathur/setup-php@v2
      with:
        php-version: '8.2'
    - name: Install Dependencies
      run: composer install
    - name: Run Tests
      run: php artisan test
```

## Summary

This testing guide covers:
- Authentication setup
- Core ERP endpoint testing
- Order management testing
- RMA testing
- WMS testing
- Analytics testing
- Notifications testing
- Workflows testing
- Audit logs testing
- Automated testing with PHPUnit
- Postman collection setup
- Common issues and solutions
- Performance testing
- Security testing
- CI/CD integration
