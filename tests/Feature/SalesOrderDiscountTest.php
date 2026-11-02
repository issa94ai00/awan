<?php

use App\Models\Customer;
use App\Models\Employee;
use App\Models\Invoice;
use App\Models\Product;
use App\Models\SalesOrder;
use App\Models\User;
use App\Models\Warehouse;

beforeEach(function () {
    $this->user = User::factory()->operator()->create();

    $this->warehouse = Warehouse::create([
        'name' => 'المستودع الرئيسي',
        'location' => 'دمشق',
        'code' => 'WH-MAIN',
        'address' => 'دمشق',
        'phone' => '+963110000000',
        'email' => 'warehouse@example.com',
        'status' => 'active',
    ]);

    $this->customer = Customer::create([
        'name' => 'عميل اختبار الخصم',
        'email' => 'discount-client@example.com',
        'phone' => '+963110000001',
        'status' => 'نشط',
    ]);

    $this->product = Product::create([
        'name_ar' => 'محبس مياه نصف إنش',
        'price' => 100,
        'stock_quantity' => 100,
    ]);
});

test('it creates a sales order with percentage discount', function () {
    $payload = [
        'customer_id' => $this->customer->id,
        'order_date' => now()->toDateString(),
        'discount_percent' => 10,
        'items' => [
            [
                'product_id' => $this->product->id,
                'quantity' => 2,
                'unit_price' => 100,
                'discount' => 0,
                'tax' => 0,
            ],
        ],
    ];

    $response = $this->actingAs($this->user, 'sanctum')
        ->postJson('/api/v1/sales-orders', $payload);

    $response->assertStatus(201);

    $order = SalesOrder::latest('id')->first();
    expect((float) $order->subtotal)->toBe(200.0)
        ->and((float) $order->discount_percent)->toBe(10.0)
        ->and((float) $order->discount)->toBe(20.0)
        ->and((float) $order->total)->toBe(180.0);
});

test('it creates a sales order with fixed amount discount', function () {
    $payload = [
        'customer_id' => $this->customer->id,
        'order_date' => now()->toDateString(),
        'discount' => 25,
        'discount_percent' => null,
        'items' => [
            [
                'product_id' => $this->product->id,
                'quantity' => 2,
                'unit_price' => 100,
                'discount' => 0,
                'tax' => 0,
            ],
        ],
    ];

    $response = $this->actingAs($this->user, 'sanctum')
        ->postJson('/api/v1/sales-orders', $payload);

    $response->assertStatus(201);

    $order = SalesOrder::latest('id')->first();
    expect((float) $order->subtotal)->toBe(200.0)
        ->and($order->discount_percent)->toBeNull()
        ->and((float) $order->discount)->toBe(25.0)
        ->and((float) $order->total)->toBe(175.0);
});

test('it rejects a fixed discount that exceeds the subtotal', function () {
    $payload = [
        'customer_id' => $this->customer->id,
        'order_date' => now()->toDateString(),
        'discount' => 250,
        'discount_percent' => null,
        'items' => [
            [
                'product_id' => $this->product->id,
                'quantity' => 2,
                'unit_price' => 100,
                'discount' => 0,
                'tax' => 0,
            ],
        ],
    ];

    $response = $this->actingAs($this->user, 'sanctum')
        ->postJson('/api/v1/sales-orders', $payload);

    $response->assertStatus(422)
        ->assertJsonValidationErrors(['discount']);
});

test('it updates a sales order switching from percentage to fixed discount', function () {
    $payload = [
        'customer_id' => $this->customer->id,
        'order_date' => now()->toDateString(),
        'discount_percent' => 10,
        'items' => [
            [
                'product_id' => $this->product->id,
                'quantity' => 2,
                'unit_price' => 100,
                'discount' => 0,
                'tax' => 0,
            ],
        ],
    ];

    $created = $this->actingAs($this->user, 'sanctum')
        ->postJson('/api/v1/sales-orders', $payload)
        ->assertCreated()
        ->json('data');

    $updatePayload = [
        'items' => [
            [
                'product_id' => $this->product->id,
                'quantity' => 2,
                'unit_price' => 100,
                'discount' => 0,
                'tax' => 0,
            ],
        ],
        'discount' => 30,
        'discount_percent' => null,
    ];

    $this->actingAs($this->user, 'sanctum')
        ->putJson('/api/v1/sales-orders/'.$created['id'], $updatePayload)
        ->assertOk();

    $order = SalesOrder::find($created['id']);
    expect($order->discount_percent)->toBeNull()
        ->and((float) $order->discount)->toBe(30.0)
        ->and((float) $order->total)->toBe(170.0);
});

test('it retains percentage discount on invoice when sales order is converted to invoice', function () {
    $order = SalesOrder::create([
        'order_number' => 'SO-DISC01',
        'customer_id' => $this->customer->id,
        'status' => SalesOrder::STATUS_CONFIRMED,
        'order_date' => now()->toDateString(),
        'subtotal' => 200,
        'discount_percent' => 15,
        'discount' => 30,
        'total' => 170,
        'created_by' => $this->user->id,
    ]);

    $order->items()->create([
        'product_id' => $this->product->id,
        'quantity' => 2,
        'unit_price' => 100,
        'discount' => 0,
        'tax' => 0,
        'total' => 200,
    ]);

    $this->actingAs($this->user, 'sanctum')
        ->postJson("/api/v1/sales-orders/{$order->id}/convert-to-invoice")
        ->assertCreated();

    $invoice = Invoice::where('sales_order_id', $order->id)->first();
    expect($invoice)->not->toBeNull()
        ->and((float) $invoice->discount_percent)->toBe(15.0)
        ->and((float) $invoice->discount)->toBe(30.0)
        ->and((float) $invoice->total)->toBe(170.0);
});
