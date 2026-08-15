<?php

use App\Models\Customer;
use App\Models\Employee;
use App\Models\Warehouse;

it('marks one employee as the primary customer representative and keeps legacy mapping in sync', function () {
    $warehouse = Warehouse::create([
        'name' => 'Main Warehouse',
        'code' => 'WH-001',
        'address' => 'Damascus',
        'city' => 'Damascus',
        'country' => 'SY',
        'is_active' => true,
        'location_type' => 'warehouse',
    ]);

    $primary = Employee::create([
        'user_id' => null,
        'warehouse_id' => $warehouse->id,
        'first_name' => 'Ali',
        'last_name' => 'Hassan',
        'email' => 'ali@example.com',
        'phone' => '0999999999',
        'status' => 'active',
    ]);

    $secondary = Employee::create([
        'user_id' => null,
        'warehouse_id' => $warehouse->id,
        'first_name' => 'Samir',
        'last_name' => 'Nasser',
        'email' => 'samir@example.com',
        'phone' => '0888888888',
        'status' => 'active',
    ]);

    $customer = Customer::create([
        'name' => 'Test Customer',
        'email' => 'customer@example.com',
        'phone' => '966500000001',
        'status' => 'active',
    ]);

    $customer->customerEmployees()->sync([
        $primary->id => ['is_primary' => true],
        $secondary->id => ['is_primary' => false],
    ]);

    expect($customer->primaryEmployee()->first()->id)->toBe($primary->id)
        ->and($customer->employee_id)->toBeNull();

    $customer->syncPrimaryEmployee($secondary->id);

    expect($customer->fresh()->primaryEmployee()->first()->id)->toBe($secondary->id)
        ->and($customer->fresh()->employee_id)->toBe($secondary->id);
});

it('stores the sales order warehouse on the invoice so reports can aggregate by warehouse', function () {
    $warehouse = Warehouse::create([
        'name' => 'Warehouse A',
        'code' => 'WH-A',
        'address' => 'Damascus',
        'city' => 'Damascus',
        'country' => 'SY',
        'is_active' => true,
        'location_type' => 'warehouse',
    ]);

    $employee = Employee::create([
        'user_id' => null,
        'warehouse_id' => $warehouse->id,
        'first_name' => 'Nabil',
        'last_name' => 'Rami',
        'email' => 'nabil@example.com',
        'phone' => '0555555555',
        'status' => 'active',
    ]);

    $customer = Customer::create([
        'name' => 'Warehouse Customer',
        'email' => 'warehouse.customer@example.com',
        'phone' => '966500000900',
        'status' => 'active',
    ]);

    $product = \App\Models\Product::create([
        'name_ar' => 'منتج مخزني',
        'name_en' => 'Warehouse Product',
        'sku' => 'SKU-WH-1',
        'price' => 100,
        'is_active' => true,
    ]);

    $order = \App\Models\SalesOrder::create([
        'order_number' => 'SO-TEST-001',
        'customer_id' => $customer->id,
        'assigned_employee_id' => $employee->id,
        'fulfillment_warehouse_id' => $warehouse->id,
        'status' => \App\Models\SalesOrder::STATUS_PENDING,
        'subtotal' => 100,
        'tax' => 0,
        'discount' => 0,
        'total' => 100,
        'currency' => 'SYP',
    ]);

    $order->items()->create([
        'product_id' => $product->id,
        'quantity' => 1,
        'unit_price' => 100,
        'discount' => 0,
        'tax' => 0,
    ]);

    $invoice = app(\App\Services\Sales\SalesOrderWorkflowService::class)->ensureInvoice($order);

    expect((int) $invoice->warehouse_id)->toBe($warehouse->id)
        ->and((int) $invoice->sales_order_id)->toBe($order->id);
});
