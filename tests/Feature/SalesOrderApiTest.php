<?php

use App\Models\Customer;
use App\Models\Employee;
use App\Models\Invoice;
use App\Models\JournalEntryHeader;
use App\Models\Product;
use App\Models\ProductUnit;
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
        ->assertJson(['success' => true])
        ->assertJsonPath('data.fulfillment_warehouse_id', $warehouse->id)
        ->assertJsonPath('data.assigned_employee_id', $employee->id);

    $this->assertDatabaseHas('sales_orders', [
        'assigned_employee_id' => $employee->id,
        'fulfillment_warehouse_id' => $warehouse->id,
        'customer_id' => $customer->id,
    ]);
});

test('pending sales order items can be updated without restating header fields', function () {
    $customer = Customer::create([
        'name' => 'عميل التعديل',
        'email' => 'edit-customer@example.com',
        'phone' => '+966500000010',
        'status' => 'نشط',
    ]);

    $productA = Product::create(['name_ar' => 'منتج أ', 'price' => 100]);
    $productB = Product::create(['name_ar' => 'منتج ب', 'price' => 50]);

    $unit = ProductUnit::create([
        'product_id' => $productA->id,
        'name' => 'Box',
        'name_ar' => 'كرتون',
        'base_unit_multiplier' => 12,
        'price_multiplier' => 12,
        'is_default' => false,
    ]);

    $order = SalesOrder::create([
        'order_number' => 'SO-EDIT01',
        'customer_id' => $customer->id,
        'status' => SalesOrder::STATUS_PENDING,
        'order_date' => now()->toDateString(),
        'discount' => 10,
        'tax' => 5,
        'shipping_cost' => 20,
        'subtotal' => 100,
        'total' => 115,
        'notes' => 'ملاحظة أصلية',
        'created_by' => $this->user->id,
    ]);

    $order->items()->create([
        'product_id' => $productA->id,
        'quantity' => 1,
        'unit_price' => 100,
        'discount' => 0,
        'tax' => 0,
    ]);

    $response = $this->actingAs($this->user, 'sanctum')
        ->putJson("/api/v1/sales-orders/{$order->id}", [
            'items' => [
                [
                    'product_id' => $productA->id,
                    'quantity' => 3,
                    'unit_price' => 90,
                    'product_unit_id' => $unit->id,
                ],
                [
                    'product_id' => $productB->id,
                    'quantity' => 2,
                    'unit_price' => 40,
                ],
            ],
        ]);

    $response->assertSuccessful()
        ->assertJsonPath('data.customer_id', $customer->id)
        ->assertJsonPath('data.notes', 'ملاحظة أصلية');

    $order->refresh()->load('items');

    expect($order->customer_id)->toBe($customer->id);
    expect($order->notes)->toBe('ملاحظة أصلية');
    expect((float) $order->discount)->toBe(10.0);
    expect((float) $order->shipping_cost)->toBe(20.0);
    // 3*90 + 2*40 = 350; total = 350 - 10 + 5 + 20 = 365
    expect((float) $order->subtotal)->toBe(350.0);
    expect((float) $order->total)->toBe(365.0);
    expect($order->items)->toHaveCount(2);

    $lineA = $order->items->firstWhere('product_id', $productA->id);
    expect($lineA->quantity)->toBe(3);
    expect((float) $lineA->unit_price)->toBe(90.0);
    expect($lineA->product_unit_id)->toBe($unit->id);
    expect($lineA->unit_name)->toBe('كرتون');
    expect((float) $lineA->unit_multiplier)->toBe(12.0);

    $lineB = $order->items->firstWhere('product_id', $productB->id);
    expect($lineB->quantity)->toBe(2);
    expect((float) $lineB->unit_price)->toBe(40.0);
});

test('confirmed sales order items cannot be rewritten', function () {
    $customer = Customer::create([
        'name' => 'عميل مؤكد',
        'email' => 'confirmed@example.com',
        'phone' => '+966500000011',
        'status' => 'نشط',
    ]);

    $product = Product::create(['name_ar' => 'منتج', 'price' => 25]);

    $order = SalesOrder::create([
        'order_number' => 'SO-CONF01',
        'customer_id' => $customer->id,
        'status' => SalesOrder::STATUS_CONFIRMED,
        'order_date' => now()->toDateString(),
        'subtotal' => 25,
        'total' => 25,
        'created_by' => $this->user->id,
    ]);

    $order->items()->create([
        'product_id' => $product->id,
        'quantity' => 1,
        'unit_price' => 25,
    ]);

    $this->actingAs($this->user, 'sanctum')
        ->putJson("/api/v1/sales-orders/{$order->id}", [
            'items' => [
                [
                    'product_id' => $product->id,
                    'quantity' => 9,
                    'unit_price' => 25,
                ],
            ],
        ])
        ->assertStatus(422);

    expect($order->fresh()->items()->first()->quantity)->toBe(1);
});

test('creating a sales order persists the chosen product unit', function () {
    $customer = Customer::create([
        'name' => 'عميل وحدة',
        'email' => 'unit-customer@example.com',
        'phone' => '+966500000012',
        'status' => 'نشط',
    ]);

    $product = Product::create(['name_ar' => 'منتج بوحدة', 'price' => 10]);
    $unit = ProductUnit::create([
        'product_id' => $product->id,
        'name' => 'Dozen',
        'name_ar' => 'دستة',
        'base_unit_multiplier' => 12,
        'price_multiplier' => 12,
        'is_default' => true,
    ]);

    $response = $this->actingAs($this->user, 'sanctum')
        ->postJson('/api/v1/sales-orders', [
            'customer_id' => $customer->id,
            'order_date' => now()->toDateString(),
            'items' => [
                [
                    'product_id' => $product->id,
                    'quantity' => 1,
                    'unit_price' => 120,
                    'product_unit_id' => $unit->id,
                ],
            ],
        ]);

    $response->assertCreated();

    $item = SalesOrder::findOrFail($response->json('data.id'))->items()->first();
    expect($item->product_unit_id)->toBe($unit->id);
    expect($item->unit_name)->toBe('دستة');
    expect((float) $item->unit_multiplier)->toBe(12.0);
});

test('creating a sales order does not raise an invoice or post to the ledger', function () {
    $customer = Customer::create([
        'name' => 'عميل بلا قيد',
        'email' => 'no-ledger@example.com',
        'phone' => '+966500000013',
        'status' => 'نشط',
    ]);
    $product = Product::create(['name_ar' => 'منتج', 'price' => 40]);

    $response = $this->actingAs($this->user, 'sanctum')
        ->postJson('/api/v1/sales-orders', [
            'customer_id' => $customer->id,
            'order_date' => now()->toDateString(),
            'items' => [
                [
                    'product_id' => $product->id,
                    'quantity' => 2,
                    'unit_price' => 40,
                ],
            ],
        ])
        ->assertCreated();

    $orderId = $response->json('data.id');

    expect($response->json('data.status'))->toBe(SalesOrder::STATUS_PENDING);
    expect(Invoice::where('sales_order_id', $orderId)->exists())->toBeFalse();
    expect(JournalEntryHeader::query()
        ->where('posting_key', 'like', '%'.$orderId.'%')
        ->exists())->toBeFalse();
});
