<?php

use App\Models\Customer;
use App\Models\Employee;
use App\Models\Invoice;
use App\Models\Product;
use App\Models\SalesOrder;
use App\Models\Warehouse;
use App\Services\Sales\SalesOrderWorkflowService;

it('keeps the warehouse on each invoice item so a single invoice can reflect multi-warehouse fulfillment', function () {
    $warehouseA = Warehouse::create([
        'name' => 'Warehouse A',
        'code' => 'WH-A',
        'address' => 'Damascus',
        'city' => 'Damascus',
        'country' => 'SY',
        'is_active' => true,
        'location_type' => 'warehouse',
    ]);

    $warehouseB = Warehouse::create([
        'name' => 'Warehouse B',
        'code' => 'WH-B',
        'address' => 'Aleppo',
        'city' => 'Aleppo',
        'country' => 'SY',
        'is_active' => true,
        'location_type' => 'warehouse',
    ]);

    $employee = Employee::create([
        'user_id' => null,
        'warehouse_id' => $warehouseA->id,
        'first_name' => 'Ahmad',
        'last_name' => 'Saleh',
        'email' => 'ahmad@example.com',
        'phone' => '0555555555',
        'status' => 'active',
    ]);

    $customer = Customer::create([
        'name' => 'Split Customer',
        'email' => 'split.customer@example.com',
        'phone' => '966500000111',
        'status' => 'active',
    ]);

    $product = Product::create([
        'name_ar' => 'منتج متعدد المستودعات',
        'name_en' => 'Multi Warehouse Product',
        'sku' => 'SKU-MW-1',
        'price' => 100,
        'is_active' => true,
    ]);

    $order = SalesOrder::create([
        'order_number' => 'SO-TEST-002',
        'customer_id' => $customer->id,
        'assigned_employee_id' => $employee->id,
        'fulfillment_warehouse_id' => $warehouseA->id,
        'status' => SalesOrder::STATUS_PENDING,
        'subtotal' => 200,
        'tax' => 0,
        'discount' => 0,
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

    $item = $order->items()->first();
    $item->allocations()->createMany([
        ['warehouse_id' => $warehouseA->id, 'quantity' => 1, 'status' => 'pending'],
        ['warehouse_id' => $warehouseB->id, 'quantity' => 1, 'status' => 'pending'],
    ]);

    $invoice = app(SalesOrderWorkflowService::class)->ensureInvoice($order);

    expect($invoice->items()->count())->toBe(1)
        ->and((int) $invoice->items()->first()->warehouse_id)->toBe($warehouseA->id);

    $invoice->items()->update(['warehouse_id' => $warehouseB->id]);

    expect((int) $invoice->fresh()->items()->first()->warehouse_id)->toBe($warehouseB->id);
});
