<?php

use App\Models\Product;
use App\Models\PurchaseOrder;
use App\Models\Role;
use App\Models\Supplier;
use App\Models\User;
use App\Models\Warehouse;

/**
 * Recording a goods receipt against its purchase request.
 */
beforeEach(function () {
    $this->admin = User::factory()->create([
        'is_admin' => true,
        'role_id' => Role::firstOrCreate(['name' => 'admin'], ['display_name' => 'admin'])->id,
    ]);

    $this->warehouse = Warehouse::create([
        'name' => 'المستودع الرئيسي',
        'code' => 'WH-RECEIPT',
        'status' => 'active',
        'is_active' => true,
        'is_primary' => true,
        'location_type' => Warehouse::TYPE_WAREHOUSE,
    ]);

    $this->supplier = Supplier::create(['name' => 'مورّد السيراميك', 'status' => 'active', 'balance' => 0]);
    $this->other = Supplier::create(['name' => 'مورّد آخر', 'status' => 'active', 'balance' => 0]);

    $this->product = Product::create(['name_ar' => 'بلاط أرضي', 'sku' => 'SKU-TILE', 'price' => 12, 'cost_price' => 8]);

    $this->order = function (string $status = 'confirmed') {
        $order = PurchaseOrder::create([
            'supplier_id' => $this->supplier->id,
            'order_number' => 'PO-RC-' . uniqid(),
            'status' => $status,
            'subtotal' => 80,
            'tax' => 5,
            'total' => 85,
        ]);
        $order->items()->create([
            'product_id' => $this->product->id,
            'product_name' => 'بلاط أرضي',
            'quantity' => 10,
            'unit_price' => 8,
            'total_price' => 80,
        ]);

        return $order;
    };

    $this->receive = fn (array $overrides = []) => $this->actingAs($this->admin)
        ->postJson('/api/v1/purchase-receipts', array_merge([
            'supplier_id' => $this->supplier->id,
            'warehouse_id' => $this->warehouse->id,
            'receipt_date' => now()->toDateString(),
            'items' => [
                ['product_id' => $this->product->id, 'quantity' => 6, 'unit_price' => 8],
            ],
        ], $overrides));
});

it('refuses the same product on two lines of one receipt', function () {
    // Stock intake is keyed per product per receipt, so the second line was
    // billed to the supplier but never reached the warehouse.
    ($this->receive)([
        'items' => [
            ['product_id' => $this->product->id, 'quantity' => 6, 'unit_price' => 8],
            ['product_id' => $this->product->id, 'quantity' => 4, 'unit_price' => 8],
        ],
    ])->assertStatus(422)->assertJsonValidationErrors('items.1.product_id');
});

it('refuses a request that belongs to another supplier', function () {
    $order = ($this->order)();

    ($this->receive)(['purchase_order_id' => $order->id, 'supplier_id' => $this->other->id])
        ->assertStatus(422);

    expect($order->fresh()->status)->toBe('confirmed');
});

it('refuses to receive against a cancelled request', function () {
    $order = ($this->order)('cancelled');

    ($this->receive)(['purchase_order_id' => $order->id])->assertStatus(422);

    expect($order->receipts()->count())->toBe(0);
});

it('prefills a second delivery with only what is still owed', function () {
    $order = ($this->order)();

    ($this->receive)(['purchase_order_id' => $order->id])->assertCreated();

    $details = $this->actingAs($this->admin)
        ->getJson("/api/v1/purchase-receipts/purchase-order/{$order->id}")
        ->assertOk();

    expect($details->json('data.receivable'))->toBeTrue()
        ->and($details->json('data.purchase_order.receipts_count'))->toBe(1)
        ->and($details->json('data.items.0.quantity'))->toBe(10)
        ->and($details->json('data.items.0.received_quantity'))->toBe(6)
        ->and($details->json('data.items.0.remaining_quantity'))->toBe(4);
});

it('says a cancelled request cannot be received', function () {
    $order = ($this->order)('cancelled');

    $this->actingAs($this->admin)
        ->getJson("/api/v1/purchase-receipts/purchase-order/{$order->id}")
        ->assertOk()
        ->assertJsonPath('data.receivable', false);
});
