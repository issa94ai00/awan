<?php

use App\Models\Product;
use App\Models\PurchaseOrder;
use App\Models\Role;
use App\Models\Supplier;
use App\Models\User;
use App\Models\Warehouse;

/**
 * Approving a purchase order, and receiving its goods afterwards.
 *
 * There was no endpoint for the first of those. The only way to move an order
 * out of 'pending' was to PUT the whole thing back — which deletes every line
 * and writes it again — and the form that would have done it offered a status
 * list that never rendered, so in practice orders could not be approved from
 * the admin at all.
 */
beforeEach(function () {
    $this->admin = User::factory()->create([
        'is_admin' => true,
        'role_id' => Role::firstOrCreate(['name' => 'admin'], ['display_name' => 'admin'])->id,
    ]);

    Warehouse::create([
        'name' => 'المستودع الرئيسي',
        'code' => 'WH-APPROVAL',
        'status' => 'active',
        'is_active' => true,
        'is_primary' => true,
        'location_type' => Warehouse::TYPE_WAREHOUSE,
    ]);

    $this->supplier = Supplier::create(['name' => 'مورّد الأنابيب', 'status' => 'active', 'balance' => 0]);

    $this->product = Product::create([
        'name_ar' => 'أنبوب نحاسي',
        'sku' => 'SKU-PIPE',
        'price' => 90,
        'cost_price' => 50,
    ]);

    $this->makeOrder = fn (string $status = 'pending') => tap(
        PurchaseOrder::create([
            'supplier_id' => $this->supplier->id,
            'order_number' => 'PO-' . fake()->unique()->numerify('######'),
            'status' => $status,
            'subtotal' => 500,
            'total' => 500,
        ]),
        fn (PurchaseOrder $order) => $order->items()->create([
            'product_id' => $this->product->id,
            'product_name' => 'أنبوب نحاسي',
            'quantity' => 10,
            'unit_price' => 50,
            'total_price' => 500,
        ])
    );
});

it('approves a pending order without touching its lines', function () {
    $order = ($this->makeOrder)();

    $this->actingAs($this->admin)
        ->putJson("/api/v1/admin/purchase-orders/{$order->id}/status", ['status' => 'confirmed'])
        ->assertOk()
        ->assertJsonPath('data.status', 'confirmed');

    // The whole point of the dedicated endpoint: update() would have deleted
    // and recreated this line, giving it a new id.
    expect($order->fresh()->status)->toBe('confirmed')
        ->and($order->items()->count())->toBe(1)
        ->and((int) $order->items()->first()->quantity)->toBe(10);
});

it('refuses to mark an order completed by hand', function () {
    $order = ($this->makeOrder)('confirmed');

    // Completion means goods arrived, which only a receipt can say — it is what
    // moves the stock and posts the journal entry.
    $this->actingAs($this->admin)
        ->putJson("/api/v1/admin/purchase-orders/{$order->id}/status", ['status' => 'completed'])
        ->assertStatus(422);

    expect($order->fresh()->status)->toBe('confirmed');
});

it('refuses to reopen an order whose goods were already received', function () {
    $order = ($this->makeOrder)('confirmed');

    $this->actingAs($this->admin)->postJson('/api/v1/purchase-receipts', [
        'purchase_order_id' => $order->id,
        'supplier_id' => $this->supplier->id,
        'receipt_date' => now()->toDateString(),
        'items' => [
            ['product_id' => $this->product->id, 'quantity' => 10, 'unit_price' => 50],
        ],
    ])->assertCreated();

    expect($order->fresh()->status)->toBe('completed');

    $this->actingAs($this->admin)
        ->putJson("/api/v1/admin/purchase-orders/{$order->id}/status", ['status' => 'pending'])
        ->assertStatus(422);

    expect($order->fresh()->status)->toBe('completed');
});

it('will not resurrect a cancelled order', function () {
    $order = ($this->makeOrder)('cancelled');

    $this->actingAs($this->admin)
        ->putJson("/api/v1/admin/purchase-orders/{$order->id}/status", ['status' => 'confirmed'])
        ->assertStatus(422);
});

it('counts every stage across the table, not just the loaded page', function () {
    ($this->makeOrder)('pending');
    ($this->makeOrder)('pending');
    ($this->makeOrder)('confirmed');
    // Written by an older version of this screen; it still means 'confirmed'.
    ($this->makeOrder)('ordered');
    ($this->makeOrder)('cancelled');

    $response = $this->actingAs($this->admin)
        ->getJson('/api/v1/admin/purchase-orders?per_page=1')
        ->assertOk();

    // One row on the page, but the badges describe all five orders.
    expect($response->json('data.orders'))->toHaveCount(1);
    expect($response->json('data.status_counts'))
        ->toMatchArray([
            'all' => 5,
            'pending' => 2,
            'confirmed' => 2,
            'completed' => 0,
            'cancelled' => 1,
        ]);
});

it('finds an order by number or supplier from any page', function () {
    $order = ($this->makeOrder)();
    $order->update(['order_number' => 'PO-FINDME']);
    ($this->makeOrder)();

    $byNumber = $this->actingAs($this->admin)
        ->getJson('/api/v1/admin/purchase-orders?search=FINDME')
        ->assertOk();

    expect($byNumber->json('data.orders'))->toHaveCount(1)
        ->and($byNumber->json('data.orders.0.order_number'))->toBe('PO-FINDME');

    $bySupplier = $this->actingAs($this->admin)
        ->getJson('/api/v1/admin/purchase-orders?search=الأنابيب')
        ->assertOk();

    expect($bySupplier->json('data.orders'))->toHaveCount(2);
});

it('filters a stage by every spelling it was ever stored under', function () {
    ($this->makeOrder)('confirmed');
    ($this->makeOrder)('ordered');
    ($this->makeOrder)('pending');

    $response = $this->actingAs($this->admin)
        ->getJson('/api/v1/admin/purchase-orders?status=confirmed')
        ->assertOk();

    expect($response->json('data.orders'))->toHaveCount(2);
});

it('lets a received order still be saved from the edit form', function () {
    $order = ($this->makeOrder)('completed');

    // 'completed' was not in the list of statuses update() accepted, so an order
    // a receipt had completed could not be re-saved — not even to fix a typo in
    // its notes — because its own current status failed validation.
    $this->actingAs($this->admin)
        ->putJson("/api/v1/admin/purchase-orders/{$order->id}", [
            'supplier_id' => $this->supplier->id,
            'status' => 'completed',
            'notes' => 'وصلت ناقصة قطعتين',
            'items' => [
                ['product_id' => $this->product->id, 'quantity' => 10, 'unit_price' => 50],
            ],
        ])
        ->assertOk();

    expect($order->fresh()->notes)->toBe('وصلت ناقصة قطعتين');
});
