<?php

use App\Models\Customer;
use App\Models\Employee;
use App\Models\Invoice;
use App\Models\Product;
use App\Models\SalesOrder;
use App\Models\User;
use App\Models\Warehouse;
use Illuminate\Support\Facades\DB;

it('groups sales totals by employee, customer and warehouse for ERP reporting', function () {
    $this->actingAs(User::factory()->create(), 'sanctum');

    $warehouseA = Warehouse::create([
        'name' => 'Warehouse A',
        'code' => 'WH-A',
        'city' => 'Damascus',
        'country' => 'SY',
        'is_active' => true,
        'location_type' => 'warehouse',
    ]);

    $warehouseB = Warehouse::create([
        'name' => 'Warehouse B',
        'code' => 'WH-B',
        'city' => 'Aleppo',
        'country' => 'SY',
        'is_active' => true,
        'location_type' => 'warehouse',
    ]);

    $employee = Employee::create([
        'user_id' => null,
        'warehouse_id' => $warehouseA->id,
        'first_name' => 'Mazen',
        'last_name' => 'Khaled',
        'email' => 'mazen@example.com',
        'phone' => '0999999999',
        'status' => 'active',
    ]);

    $customer = Customer::create([
        'name' => 'ERP Customer',
        'email' => 'erp.customer@example.com',
        'phone' => '966500000555',
        'status' => 'active',
    ]);

    $product = Product::create([
        'name_ar' => 'منتج تقارير',
        'name_en' => 'Report Product',
        'sku' => 'SKU-REPORT-1',
        'price' => 100,
        'is_active' => true,
    ]);

    $order = SalesOrder::create([
        'order_number' => 'SO-REPORT-001',
        'customer_id' => $customer->id,
        'assigned_employee_id' => $employee->id,
        'fulfillment_warehouse_id' => $warehouseA->id,
        'status' => SalesOrder::STATUS_CONFIRMED,
        'order_date' => now()->toDateString(),
        'subtotal' => 200,
        'discount' => 0,
        'tax' => 0,
        'total' => 200,
        'currency' => 'SYP',
    ]);

    $order->items()->create([
        'product_id' => $product->id,
        'quantity' => 2,
        'unit_price' => 100,
        'discount' => 0,
        'tax' => 0,
    ]);

    $response = $this->getJson('/api/v1/admin/reports/sales/summary?group_by=employee&date_filter_type=today');
    $response->assertStatus(200)
        ->assertJsonPath('data.summary.0.employee_id', $employee->id)
        ->assertJsonPath('data.summary.0.total_sales', 200);

    $customerResponse = $this->getJson('/api/v1/admin/reports/sales/summary?group_by=customer&date_filter_type=today');
    $customerResponse->assertStatus(200)
        ->assertJsonPath('data.summary.0.customer_id', $customer->id)
        ->assertJsonPath('data.summary.0.total_sales', 200);

    $warehouseResponse = $this->getJson('/api/v1/admin/reports/sales/summary?group_by=warehouse&date_filter_type=today');
    $warehouseResponse->assertStatus(200)
        ->assertJsonPath('data.summary.0.warehouse_id', $warehouseA->id)
        ->assertJsonPath('data.summary.0.total_sales', 200);

    $filteredByCustomer = $this->getJson('/api/v1/admin/reports/sales/summary?group_by=employee&date_filter_type=today&customer_id='.$customer->id);
    $filteredByCustomer->assertStatus(200)
        ->assertJsonPath('data.summary.0.employee_id', $employee->id)
        ->assertJsonPath('data.summary.0.total_sales', 200);

    $filteredByWarehouse = $this->getJson('/api/v1/admin/reports/sales/summary?group_by=customer&date_filter_type=today&warehouse_id='.$warehouseA->id);
    $filteredByWarehouse->assertStatus(200)
        ->assertJsonPath('data.summary.0.customer_id', $customer->id)
        ->assertJsonPath('data.summary.0.total_sales', 200);

    $dimensionResponse = $this->getJson('/api/v1/admin/reports/sales/dimensions?date_filter_type=today');
    $dimensionResponse->assertStatus(200)
        ->assertJsonPath('data.employee_summary.0.employee_id', $employee->id)
        ->assertJsonPath('data.customer_summary.0.customer_id', $customer->id)
        ->assertJsonPath('data.warehouse_summary.0.warehouse_id', $warehouseA->id);

    DB::table('warehouse_inventory')->insert([
        'warehouse_id' => $warehouseA->id,
        'product_id' => $product->id,
        'quantity' => 10,
        'reserved_quantity' => 0,
        'available_quantity' => 10,
        'reorder_point' => 2,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $user = \App\Models\User::factory()->create();

    Invoice::create([
        'invoice_number' => 'INV-REPORT-001',
        'customer_id' => $customer->id,
        'sales_order_id' => $order->id,
        'warehouse_id' => $warehouseA->id,
        'subtotal' => 200,
        'tax' => 0,
        'discount' => 0,
        'total' => 200,
        'paid_amount' => 200,
        'due_amount' => 0,
        'status' => Invoice::STATUS_DELIVERED,
        'currency' => 'SYP',
        'created_by' => $user->id,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $inventoryResponse = $this->getJson('/api/v1/admin/reports/inventory/dimensions?warehouse_id='.$warehouseA->id);
    $inventoryResponse->assertStatus(200)
        ->assertJsonPath('data.warehouse_summary.0.warehouse_id', $warehouseA->id)
        ->assertJsonPath('data.overall.total_quantity', 10);

    $invoiceResponse = $this->getJson('/api/v1/admin/reports/invoices/dimensions?date_filter_type=today');
    $invoiceResponse->assertStatus(200)
        ->assertJsonPath('data.customer_summary.0.customer_id', $customer->id)
        ->assertJsonPath('data.warehouse_summary.0.warehouse_id', $warehouseA->id)
        ->assertJsonPath('data.overall.total_invoiced', 200);

    $inventoryExport = $this->get('/api/v1/admin/reports/inventory/export?warehouse_id='.$warehouseA->id);
    $inventoryExport->assertStatus(200)
        ->assertHeader('Content-Type', 'text/csv; charset=UTF-8');
    $this->assertStringContainsString('warehouse_name', $inventoryExport->baseResponse->getContent());

    $invoiceExport = $this->get('/api/v1/admin/reports/invoices/export?customer_id='.$customer->id);
    $invoiceExport->assertStatus(200)
        ->assertHeader('Content-Type', 'text/csv; charset=UTF-8');
    $this->assertStringContainsString('customer_name', $invoiceExport->baseResponse->getContent());
});

it('keeps the core ERP entity relationships connected and queryable', function () {
    $warehouse = Warehouse::create([
        'name' => 'Operations Hub',
        'code' => 'OPS-01',
        'city' => 'Damascus',
        'country' => 'SY',
        'is_active' => true,
        'location_type' => 'warehouse',
    ]);

    $employee = Employee::create([
        'user_id' => null,
        'warehouse_id' => $warehouse->id,
        'first_name' => 'Ali',
        'last_name' => 'Nassar',
        'email' => 'ali.nassar@example.com',
        'phone' => '0991000000',
        'status' => 'active',
    ]);

    $customer = Customer::create([
        'name' => 'Relation Customer',
        'email' => 'relation.customer@example.com',
        'phone' => '966500000666',
        'status' => 'active',
    ]);

    $customer->customerEmployees()->sync([
        $employee->id => ['is_primary' => true],
    ]);

    $order = SalesOrder::create([
        'order_number' => 'SO-REL-001',
        'customer_id' => $customer->id,
        'assigned_employee_id' => $employee->id,
        'fulfillment_warehouse_id' => $warehouse->id,
        'status' => SalesOrder::STATUS_CONFIRMED,
        'order_date' => now()->toDateString(),
        'subtotal' => 500,
        'discount' => 0,
        'tax' => 0,
        'total' => 500,
        'currency' => 'SYP',
    ]);

    $order->routings()->sync([$warehouse->id]);

    expect($customer->primaryEmployee()->first()->id)->toBe($employee->id)
        ->and($employee->customers()->first()->id)->toBe($customer->id)
        ->and($warehouse->employees()->first()->id)->toBe($employee->id)
        ->and($order->routings()->first()->id)->toBe($warehouse->id);
});

it('calculates sales performance and gross profit by entity', function () {
    $this->actingAs(User::factory()->create(), 'sanctum');

    $warehouse = Warehouse::create([
        'name' => 'Profit Hub',
        'code' => 'PROFIT-01',
        'city' => 'Homs',
        'country' => 'SY',
        'is_active' => true,
        'location_type' => 'warehouse',
    ]);

    $employee = Employee::create([
        'user_id' => null,
        'warehouse_id' => $warehouse->id,
        'first_name' => 'Sara',
        'last_name' => 'Hamed',
        'email' => 'sara@example.com',
        'phone' => '0992000000',
        'status' => 'active',
    ]);

    $customer = Customer::create([
        'name' => 'Profit Customer',
        'email' => 'profit.customer@example.com',
        'phone' => '966500000777',
        'status' => 'active',
    ]);

    $product = Product::create([
        'name_ar' => 'منتج ربح',
        'name_en' => 'Profit Product',
        'sku' => 'SKU-PROFIT-1',
        'price' => 200,
        'cost_price' => 120,
        'is_active' => true,
    ]);

    $order = SalesOrder::create([
        'order_number' => 'SO-PROFIT-001',
        'customer_id' => $customer->id,
        'assigned_employee_id' => $employee->id,
        'fulfillment_warehouse_id' => $warehouse->id,
        'status' => SalesOrder::STATUS_CONFIRMED,
        'order_date' => now()->toDateString(),
        'subtotal' => 200,
        'discount' => 0,
        'tax' => 0,
        'total' => 200,
        'currency' => 'SYP',
    ]);

    $order->items()->create([
        'product_id' => $product->id,
        'quantity' => 1,
        'unit_price' => 200,
        'discount' => 0,
        'tax' => 0,
    ]);

    $response = $this->getJson('/api/v1/admin/reports/sales/summary?group_by=employee&date_filter_type=today');
    $response->assertStatus(200)
        ->assertJsonPath('data.summary.0.employee_id', $employee->id)
        ->assertJsonPath('data.summary.0.total_sales', 200);

    $performanceResponse = $this->getJson('/api/v1/admin/reports/sales/performance?date_filter_type=today');
    $performanceResponse->assertStatus(200)
        ->assertJsonPath('data.summary.total_revenue', 200)
        ->assertJsonPath('data.summary.total_cost', 120)
        ->assertJsonPath('data.summary.gross_profit', 80)
        ->assertJsonPath('data.summary.gross_margin', 40)
        ->assertJsonPath('data.employee_summary.0.employee_id', $employee->id)
        ->assertJsonPath('data.customer_summary.0.customer_id', $customer->id)
        ->assertJsonPath('data.warehouse_summary.0.warehouse_id', $warehouse->id);
});

it('calculates product profitability by warehouse and product', function () {
    $this->actingAs(User::factory()->create(), 'sanctum');

    $warehouse = Warehouse::create([
        'name' => 'Product Profit Hub',
        'code' => 'PPH-01',
        'city' => 'Damascus',
        'country' => 'SY',
        'is_active' => true,
        'location_type' => 'warehouse',
    ]);

    $employee = Employee::create([
        'user_id' => null,
        'warehouse_id' => $warehouse->id,
        'first_name' => 'Lina',
        'last_name' => 'Kassem',
        'email' => 'lina@example.com',
        'phone' => '0993000000',
        'status' => 'active',
    ]);

    $customer = Customer::create([
        'name' => 'Product Profit Customer',
        'email' => 'product.profit@example.com',
        'phone' => '966500000888',
        'status' => 'active',
    ]);

    $product = Product::create([
        'name_ar' => 'منتج ربح حسب المنتج',
        'name_en' => 'Product Profit Item',
        'sku' => 'SKU-PROFIT-ITEM-1',
        'price' => 250,
        'cost_price' => 150,
        'is_active' => true,
    ]);

    $order = SalesOrder::create([
        'order_number' => 'SO-PRODUCT-PROFIT-001',
        'customer_id' => $customer->id,
        'assigned_employee_id' => $employee->id,
        'fulfillment_warehouse_id' => $warehouse->id,
        'status' => SalesOrder::STATUS_CONFIRMED,
        'order_date' => now()->toDateString(),
        'subtotal' => 250,
        'discount' => 0,
        'tax' => 0,
        'total' => 250,
        'currency' => 'SYP',
    ]);

    $order->items()->create([
        'product_id' => $product->id,
        'quantity' => 1,
        'unit_price' => 250,
        'discount' => 0,
        'tax' => 0,
    ]);

    $response = $this->getJson('/api/v1/admin/reports/sales/product-profitability?date_filter_type=today');
    $response->assertStatus(200)
        ->assertJsonPath('data.summary.total_revenue', 250)
        ->assertJsonPath('data.summary.total_cost', 150)
        ->assertJsonPath('data.summary.gross_profit', 100)
        ->assertJsonPath('data.product_summary.0.product_id', $product->id)
        ->assertJsonPath('data.product_summary.0.warehouse_id', $warehouse->id);
});

/**
 * A line split across warehouses (SalesOrderItem::allocations) used to be
 * credited entirely to the order's own fulfillment_warehouse_id — wrong
 * whenever the order itself had none set, which is the normal state before
 * an order is routed, and the reason the "product profit by warehouse"
 * table read "Unknown" even for lines with a recorded split.
 */
it('splits a sales-order line\'s profit across each warehouse its fulfilment plan actually used', function () {
    $this->actingAs(User::factory()->create(), 'sanctum');

    $warehouseA = Warehouse::create([
        'name' => 'Split Warehouse A', 'code' => 'SPLIT-A', 'is_active' => true,
    ]);
    $warehouseB = Warehouse::create([
        'name' => 'Split Warehouse B', 'code' => 'SPLIT-B', 'is_active' => true,
    ]);

    $customer = Customer::create([
        'name' => 'Split Customer', 'email' => 'split@example.com', 'phone' => '966500000899', 'status' => 'active',
    ]);

    $product = Product::create([
        'name_ar' => 'منتج مقسوم', 'name_en' => 'Split Product', 'sku' => 'SKU-SPLIT-1',
        'price' => 100, 'cost_price' => 60, 'is_active' => true,
    ]);

    // No fulfillment_warehouse_id on the order itself — the split lives on
    // the item's allocations, not the header.
    $order = SalesOrder::create([
        'order_number' => 'SO-SPLIT-001',
        'customer_id' => $customer->id,
        'status' => SalesOrder::STATUS_CONFIRMED,
        'order_date' => now()->toDateString(),
        'subtotal' => 1000, 'discount' => 0, 'tax' => 0, 'total' => 1000,
        'currency' => 'USD',
    ]);

    $item = $order->items()->create([
        'product_id' => $product->id, 'quantity' => 10, 'unit_price' => 100, 'discount' => 0, 'tax' => 0,
    ]);

    $item->allocations()->create(['warehouse_id' => $warehouseA->id, 'quantity' => 4, 'status' => 'allocated']);
    $item->allocations()->create(['warehouse_id' => $warehouseB->id, 'quantity' => 6, 'status' => 'allocated']);

    $response = $this->getJson('/api/v1/admin/reports/sales/product-profitability?date_filter_type=today');
    $response->assertStatus(200);

    $rows = collect($response->json('data.product_summary'))->keyBy('warehouse_id');

    expect($rows[$warehouseA->id]['quantity'])->toBe(4)
        ->and($rows[$warehouseA->id]['total_revenue'])->toBe(400)
        ->and($rows[$warehouseA->id]['total_cost'])->toBe(240)
        ->and($rows[$warehouseB->id]['quantity'])->toBe(6)
        ->and($rows[$warehouseB->id]['total_revenue'])->toBe(600)
        ->and($rows[$warehouseB->id]['total_cost'])->toBe(360);
});

/**
 * The invoice-level warehouse_id is often never set — every InvoiceItem
 * carries its own warehouse_id instead, populated whenever a line is
 * actually picked from stock. Reading the header field left this table
 * showing "Unknown" for every invoice created that way, even though the
 * real warehouse was one relation away.
 */
it('reads a line\'s own warehouse for invoice product profitability, not just the header\'s', function () {
    $this->actingAs(User::factory()->create(), 'sanctum');

    $warehouse = Warehouse::create([
        'name' => 'Invoice Line Warehouse', 'code' => 'INV-LINE-WH', 'is_active' => true,
    ]);

    $customer = Customer::create([
        'name' => 'Invoice Line Customer', 'email' => 'invoice.line@example.com', 'phone' => '966500000877', 'status' => 'active',
    ]);

    $product = Product::create([
        'name_ar' => 'منتج فاتورة', 'name_en' => 'Invoice Line Product', 'sku' => 'SKU-INV-LINE-1',
        'price' => 80, 'cost_price' => 50, 'is_active' => true,
    ]);

    // Deliberately no warehouse_id on the invoice header — only the line
    // carries one, the way real invoices in this system are populated.
    $invoice = Invoice::create([
        'invoice_number' => 'INV-LINE-WH-001',
        'customer_id' => $customer->id,
        'status' => 'confirmed',
        'subtotal' => 80, 'tax' => 0, 'discount' => 0, 'total' => 80,
        'paid_amount' => 0, 'due_amount' => 80,
        'currency' => 'USD',
    ]);

    $invoice->items()->create([
        'warehouse_id' => $warehouse->id,
        'product_id' => $product->id,
        'product_name' => $product->name_en,
        'quantity' => 1,
        'unit_price' => 80,
    ]);

    $response = $this->getJson('/api/v1/admin/reports/invoices/product-profitability?date_filter_type=today');
    $response->assertStatus(200)
        ->assertJsonPath('data.product_summary.0.warehouse_id', $warehouse->id)
        ->assertJsonPath('data.product_summary.0.warehouse_name', $warehouse->name);
});

it('keeps every reporting endpoint closed to guests', function (string $uri) {
    $this->getJson($uri)->assertStatus(401);
})->with([
    '/api/v1/admin/reports/sales',
    '/api/v1/admin/reports/sales/summary',
    '/api/v1/admin/reports/sales/dimensions',
    '/api/v1/admin/reports/sales/performance',
    '/api/v1/admin/reports/sales/product-profitability',
    '/api/v1/admin/reports/sales/top-performers',
    '/api/v1/admin/reports/sales/export',
    '/api/v1/admin/reports/inventory/dimensions',
    '/api/v1/admin/reports/inventory/export',
    '/api/v1/admin/reports/invoices/dimensions',
    '/api/v1/admin/reports/invoices/export',
]);

it('no longer exposes the public reporting aliases', function (string $uri) {
    $this->getJson($uri)->assertStatus(404);
})->with([
    '/api/v1/reports/sales',
    '/api/v1/reports/sales/summary',
    '/api/v1/reports/sales/dimensions',
    '/api/v1/reports/sales/performance',
    '/api/v1/reports/sales/product-profitability',
    '/api/v1/reports/sales/top-performers',
    '/api/v1/reports/sales/export',
    '/api/v1/reports/inventory/dimensions',
    '/api/v1/reports/inventory/export',
    '/api/v1/reports/invoices/dimensions',
    '/api/v1/reports/invoices/export',
]);
