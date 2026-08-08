<?php

use App\Models\Customer;
use App\Models\Employee;
use App\Models\Product;
use App\Models\SalesOrder;
use App\Models\User;
use App\Models\Warehouse;

beforeEach(function () {
    $this->user = User::factory()->create();
});

test('sales order creation prefers assigned employee warehouse when fulfillment warehouse is omitted', function () {
    $warehouse = Warehouse::create([
        'name' => 'مستودع الرياض',
        'location' => 'الرياض',
        'code' => 'WH-RYD',
        'address' => 'الرياض، المملكة العربية السعودية',
        'phone' => '+966500000000',
        'email' => 'riyadh-warehouse@example.com',
        'status' => 'active',
    ]);

    $employee = Employee::create([
        'name' => 'محمد السالم',
        'email' => 'mohamed.salem@example.com',
        'phone' => '+966500000001',
        'position' => 'مندوب مبيعات',
        'department' => 'المبيعات',
        'hire_date' => now()->subYear()->toDateString(),
        'salary' => 4500,
        'status' => 'نشط',
        'warehouse_id' => $warehouse->id,
    ]);

    $customer = Customer::create([
        'name' => 'عميل تجريبي',
        'email' => 'customer@example.com',
        'phone' => '+966500000002',
        'status' => 'نشط',
    ]);

    $product = Product::create([]);

    $payload = [
        'customer_id' => $customer->id,
        'assigned_employee_id' => $employee->id,
        'order_date' => now()->toDateString(),
        'items' => [
            [
                'product_id' => $product->id,
                'quantity' => 2,
                'unit_price' => 150,
                'discount' => 0,
                'tax' => 0,
            ],
        ],
    ];

    $response = $this->actingAs($this->user, 'sanctum')
        ->postJson('/api/v1/sales-orders', $payload);

    $response->assertStatus(201)
        ->assertJson([ 'success' => true ])
        ->assertJsonPath('data.fulfillment_warehouse_id', $warehouse->id)
        ->assertJsonPath('data.assigned_employee_id', $employee->id);

    $this->assertDatabaseHas('sales_orders', [
        'assigned_employee_id' => $employee->id,
        'fulfillment_warehouse_id' => $warehouse->id,
        'customer_id' => $customer->id,
    ]);
});
