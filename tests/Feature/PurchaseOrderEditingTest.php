<?php

use App\Models\Product;
use App\Models\PurchaseOrder;
use App\Models\Role;
use App\Models\Supplier;
use App\Models\User;

/**
 * Saving a purchase order from the form: what it keeps, and what it refuses.
 */
beforeEach(function () {
    $this->admin = User::factory()->create([
        'is_admin' => true,
        'role_id' => Role::firstOrCreate(['name' => 'admin'], ['display_name' => 'admin'])->id,
    ]);

    $this->supplier = Supplier::create(['name' => 'مورّد الخلاطات', 'status' => 'active', 'balance' => 0]);

    $this->product = Product::create([
        'name_ar' => 'خلاط مغسلة',
        'sku' => 'SKU-MIX',
        'price' => 40,
        'cost_price' => 25,
    ]);

    $this->payload = fn (array $overrides = []) => array_merge([
        'supplier_id' => $this->supplier->id,
        'order_date' => '2026-09-01',
        'due_date' => '2026-09-15',
        'items' => [
            ['product_id' => $this->product->id, 'quantity' => 4, 'unit_price' => 25],
        ],
    ], $overrides);
});

it('keeps the order date the form sends', function () {
    // order_date was never validated, so validated() dropped it and every
    // order was saved without one.
    $id = $this->actingAs($this->admin)
        ->postJson('/api/v1/admin/purchase-orders', ($this->payload)())
        ->assertCreated()
        ->json('data.id');

    expect(PurchaseOrder::find($id)->order_date->toDateString())->toBe('2026-09-01');
});

it('does not reuse an order number after an order is deleted', function () {
    $post = fn () => $this->actingAs($this->admin)
        ->postJson('/api/v1/admin/purchase-orders', ($this->payload)())
        ->assertCreated()
        ->json('data');

    $first = $post();
    $second = $post();

    $this->actingAs($this->admin)
        ->deleteJson("/api/v1/admin/purchase-orders/{$first['id']}")
        ->assertOk();

    // Counting rows handed the next order the second one's number, and the
    // unique index turned the save into a 500.
    $third = $post();

    expect($third['order_number'])->not->toBe($second['order_number']);
});

it('refuses a discount larger than the order', function () {
    $this->actingAs($this->admin)
        ->postJson('/api/v1/admin/purchase-orders', ($this->payload)(['discount' => 500]))
        ->assertStatus(422);

    expect(PurchaseOrder::count())->toBe(0);
});

it('refuses a due date before the order date', function () {
    $this->actingAs($this->admin)
        ->postJson('/api/v1/admin/purchase-orders', ($this->payload)(['due_date' => '2026-08-01']))
        ->assertStatus(422);
});

it('can create an order already approved, but not already received', function () {
    $this->actingAs($this->admin)
        ->postJson('/api/v1/admin/purchase-orders', ($this->payload)(['status' => 'confirmed']))
        ->assertCreated()
        ->assertJsonPath('data.status', 'confirmed');

    $this->actingAs($this->admin)
        ->postJson('/api/v1/admin/purchase-orders', ($this->payload)(['status' => 'completed']))
        ->assertStatus(422);
});

it('will not complete or revive an order through a full save', function () {
    $pending = PurchaseOrder::create([
        'supplier_id' => $this->supplier->id,
        'order_number' => 'PO-EDIT-1',
        'status' => 'pending',
    ]);

    // The status endpoint refuses this; the form save used to accept it.
    $this->actingAs($this->admin)
        ->putJson("/api/v1/admin/purchase-orders/{$pending->id}", ($this->payload)(['status' => 'completed']))
        ->assertStatus(422);

    expect($pending->fresh()->status)->toBe('pending');
});

it('saves an order without a status and leaves its status alone', function () {
    $order = PurchaseOrder::create([
        'supplier_id' => $this->supplier->id,
        'order_number' => 'PO-EDIT-2',
        'status' => 'confirmed',
    ]);

    $this->actingAs($this->admin)
        ->putJson("/api/v1/admin/purchase-orders/{$order->id}", ($this->payload)(['notes' => 'تعديل']))
        ->assertOk();

    expect($order->fresh()->status)->toBe('confirmed')
        ->and((float) $order->fresh()->total)->toBe(100.0);
});

it('leaves a received order\'s lines and landed cost untouched when it is saved', function () {
    $order = PurchaseOrder::create([
        'supplier_id' => $this->supplier->id,
        'order_number' => 'PO-EDIT-3',
        'status' => 'completed',
        'subtotal' => 100,
        'total' => 100,
    ]);
    $line = $order->items()->create([
        'product_id' => $this->product->id,
        'product_name' => 'خلاط مغسلة',
        'quantity' => 4,
        'unit_price' => 25,
        'total_price' => 100,
    ]);
    // Written by the receipt's cost sync, never by a request.
    $line->forceFill(['received_quantity' => 4, 'received_cost' => 104, 'received_unit_cost' => 26])->save();

    $this->actingAs($this->admin)
        ->putJson("/api/v1/admin/purchase-orders/{$order->id}", ($this->payload)([
            'notes' => 'وصلت كاملة',
            'items' => [['product_id' => $this->product->id, 'quantity' => 9, 'unit_price' => 1]],
        ]))
        ->assertOk();

    $fresh = $order->fresh();
    $kept = $fresh->items()->sole();

    expect($fresh->notes)->toBe('وصلت كاملة')
        ->and((float) $fresh->total)->toBe(100.0)
        ->and($kept->id)->toBe($line->id)
        ->and($kept->quantity)->toBe(4)
        ->and((float) $kept->received_cost)->toBe(104.0);
});

it('refuses to delete an order whose goods were received', function () {
    $order = PurchaseOrder::create([
        'supplier_id' => $this->supplier->id,
        'order_number' => 'PO-EDIT-4',
        'status' => 'completed',
    ]);

    $this->actingAs($this->admin)
        ->deleteJson("/api/v1/admin/purchase-orders/{$order->id}")
        ->assertStatus(422);

    expect(PurchaseOrder::find($order->id))->not->toBeNull();
});
