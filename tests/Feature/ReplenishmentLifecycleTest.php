<?php

use App\Models\Employee;
use App\Models\InventoryTransfer;
use App\Models\Product;
use App\Models\User;
use App\Models\Warehouse;
use App\Models\WarehouseInventory;

/**
 * A replenishment request, end to end.
 *
 * The point of the whole document is that stock ends up in the branch that
 * asked for it. These tests follow the units: what leaves the main warehouse,
 * when, and what the branch is holding at the end.
 */
beforeEach(function () {
    $this->main = Warehouse::create([
        'name' => 'المستودع الرئيسي',
        'code' => 'WH-MAIN',
        'location' => 'الرياض',
        'status' => 'active',
        'is_active' => true,
        'is_primary' => true,
        'location_type' => Warehouse::TYPE_WAREHOUSE,
    ]);

    $this->branch = Warehouse::create([
        'name' => 'فرع جدة',
        'code' => 'WH-JED',
        'location' => 'جدة',
        'status' => 'active',
        'is_active' => true,
        'location_type' => Warehouse::TYPE_BRANCH,
    ]);

    $staff = function (string $email, Warehouse $warehouse) {
        $user = User::factory()->create();
        Employee::create([
            'name' => 'موظف',
            'email' => $email,
            'phone' => '+96650' . random_int(1000000, 9999999),
            'position' => 'أمين مستودع',
            'department' => 'المستودعات',
            'hire_date' => now()->subYear()->toDateString(),
            'status' => 'نشط',
            'user_id' => $user->id,
            'warehouse_id' => $warehouse->id,
        ]);
        return $user;
    };

    // Two people on opposite ends of the same request.
    $this->seller = $staff('seller@example.com', $this->branch);
    $this->keeper = $staff('keeper@example.com', $this->main);

    $this->product = Product::create([
        'name_ar' => 'خلاط مغسلة',
        'sku' => 'SKU-MIXER',
        'price' => 100,
        'cost_price' => 60,
    ]);

    $this->stock = function (Warehouse $warehouse, int $quantity) {
        WarehouseInventory::create([
            'warehouse_id' => $warehouse->id,
            'product_id' => $this->product->id,
            'quantity' => $quantity,
            'available_quantity' => $quantity,
            'reserved_quantity' => 0,
            'damaged_quantity' => 0,
            'quarantined_quantity' => 0,
        ]);
    };

    $this->at = fn (Warehouse $w) => WarehouseInventory::where('warehouse_id', $w->id)
        ->where('product_id', $this->product->id)->first();

    $this->raise = function (array $payload = []) {
        return $this->actingAs($this->seller, 'sanctum')
            ->postJson('/api/v1/field/replenishment', array_merge([
                'items' => [['product_id' => $this->product->id, 'quantity' => 6]],
            ], $payload));
    };
});

test('a raised request holds the stock at the source without moving it', function () {
    ($this->stock)($this->main, 20);

    ($this->raise)()->assertStatus(201)
        ->assertJsonPath('data.request.status', 'pending')
        ->assertJsonPath('data.request.can_approve', false); // the seller is not the source

    $main = ($this->at)($this->main);

    // Nothing has moved; the units are simply spoken for.
    expect((int) $main->quantity)->toBe(20);
    expect((int) $main->reserved_quantity)->toBe(6);
});

test('delivery: approval ships the goods and receipt lands them in the branch', function () {
    ($this->stock)($this->main, 20);
    ($this->stock)($this->branch, 1);

    ($this->raise)()->assertStatus(201);
    $transfer = InventoryTransfer::latest('id')->first();

    // The warehouse being asked is the one that approves.
    $this->actingAs($this->keeper, 'sanctum')
        ->postJson("/api/v1/field/replenishment/{$transfer->id}/approve", [
            'fulfillment_method' => 'delivery',
        ])
        ->assertStatus(200)
        ->assertJsonPath('data.request.status', 'in_transit');

    // The goods have left the source and are on a road: gone from the main
    // warehouse, not yet at the branch. The hold went with them.
    expect((int) ($this->at)($this->main)->quantity)->toBe(14);
    expect((int) ($this->at)($this->main)->reserved_quantity)->toBe(0);
    expect((int) ($this->at)($this->branch)->quantity)->toBe(1);

    $this->actingAs($this->seller, 'sanctum')
        ->postJson("/api/v1/field/replenishment/{$transfer->id}/receive")
        ->assertStatus(200)
        ->assertJsonPath('data.request.status', 'completed');

    // And now they are the branch's.
    expect((int) ($this->at)($this->main)->quantity)->toBe(14);
    expect((int) ($this->at)($this->branch)->quantity)->toBe(7);
});

test('pickup: approval moves nothing and collection moves both legs at once', function () {
    ($this->stock)($this->main, 20);
    ($this->stock)($this->branch, 1);

    ($this->raise)()->assertStatus(201);
    $transfer = InventoryTransfer::latest('id')->first();

    $this->actingAs($this->keeper, 'sanctum')
        ->postJson("/api/v1/field/replenishment/{$transfer->id}/approve", [
            'fulfillment_method' => 'pickup',
        ])
        ->assertStatus(200)
        ->assertJsonPath('data.request.status', 'ready_for_pickup')
        ->assertJsonPath('data.request.is_awaiting_pickup', true);

    // Set aside, not shipped: still on the main warehouse's shelf, still held.
    // Booking them out here would understate what the warehouse can sell today
    // for as long as the rep takes to arrive.
    expect((int) ($this->at)($this->main)->quantity)->toBe(20);
    expect((int) ($this->at)($this->main)->reserved_quantity)->toBe(6);

    $this->actingAs($this->seller, 'sanctum')
        ->postJson("/api/v1/field/replenishment/{$transfer->id}/receive")
        ->assertStatus(200)
        ->assertJsonPath('data.request.status', 'completed');

    // Collected: out of the main warehouse and into the branch in one moment.
    expect((int) ($this->at)($this->main)->quantity)->toBe(14);
    expect((int) ($this->at)($this->main)->reserved_quantity)->toBe(0);
    expect((int) ($this->at)($this->branch)->quantity)->toBe(7);
});

test('a rep who collects less than requested frees the rest for sale', function () {
    ($this->stock)($this->main, 20);
    ($this->stock)($this->branch, 0);

    ($this->raise)()->assertStatus(201);
    $transfer = InventoryTransfer::latest('id')->first();

    $this->actingAs($this->keeper, 'sanctum')
        ->postJson("/api/v1/field/replenishment/{$transfer->id}/approve", ['fulfillment_method' => 'pickup'])
        ->assertStatus(200);

    // Takes 4 of the 6 set aside.
    $this->actingAs($this->seller, 'sanctum')
        ->postJson("/api/v1/field/replenishment/{$transfer->id}/receive", [
            'items' => [['product_id' => $this->product->id, 'quantity_received' => 4]],
        ])
        ->assertStatus(200);

    $main = ($this->at)($this->main);

    expect((int) $main->quantity)->toBe(16);
    expect((int) ($this->at)($this->branch)->quantity)->toBe(4);
    // The two left behind must go back on sale. Left reserved they would be
    // held for a request that is now closed — invisible and unsellable.
    expect((int) $main->reserved_quantity)->toBe(0);
});

test('a short delivery is received and the missing units reported', function () {
    ($this->stock)($this->main, 20);
    ($this->stock)($this->branch, 0);

    ($this->raise)()->assertStatus(201);
    $transfer = InventoryTransfer::latest('id')->first();

    $this->actingAs($this->keeper, 'sanctum')
        ->postJson("/api/v1/field/replenishment/{$transfer->id}/approve", ['fulfillment_method' => 'delivery'])
        ->assertStatus(200);

    $response = $this->actingAs($this->seller, 'sanctum')
        ->postJson("/api/v1/field/replenishment/{$transfer->id}/receive", [
            'items' => [['product_id' => $this->product->id, 'quantity_received' => 5]],
        ]);

    $response->assertStatus(200)
        ->assertJsonPath('data.effects.discrepancies.0.shipped', 6)
        ->assertJsonPath('data.effects.discrepancies.0.received', 5)
        ->assertJsonPath('data.effects.discrepancies.0.missing', 1);

    // Only what arrived is booked in. The missing unit left the source and is
    // not at the branch — a loss somebody has to account for, not stock.
    expect((int) ($this->at)($this->branch)->quantity)->toBe(5);
    expect((int) ($this->at)($this->main)->quantity)->toBe(14);
});

test('receiving more than was sent is refused', function () {
    ($this->stock)($this->main, 20);
    ($this->stock)($this->branch, 0);

    ($this->raise)()->assertStatus(201);
    $transfer = InventoryTransfer::latest('id')->first();

    $this->actingAs($this->keeper, 'sanctum')
        ->postJson("/api/v1/field/replenishment/{$transfer->id}/approve", ['fulfillment_method' => 'delivery'])
        ->assertStatus(200);

    $this->actingAs($this->seller, 'sanctum')
        ->postJson("/api/v1/field/replenishment/{$transfer->id}/receive", [
            'items' => [['product_id' => $this->product->id, 'quantity_received' => 99]],
        ])
        ->assertStatus(422);

    expect((int) ($this->at)($this->branch)->quantity)->toBe(0);
});

test('cancelling before the goods move puts them back on sale', function () {
    ($this->stock)($this->main, 20);

    ($this->raise)()->assertStatus(201);
    $transfer = InventoryTransfer::latest('id')->first();

    expect((int) ($this->at)($this->main)->reserved_quantity)->toBe(6);

    $this->actingAs($this->seller, 'sanctum')
        ->postJson("/api/v1/field/replenishment/{$transfer->id}/cancel", ['reason' => 'لم نعد بحاجة'])
        ->assertStatus(200)
        ->assertJsonPath('data.request.status', 'cancelled');

    expect((int) ($this->at)($this->main)->reserved_quantity)->toBe(0);
    expect((int) ($this->at)($this->main)->quantity)->toBe(20);
});

test('a transfer already on the road cannot be cancelled', function () {
    ($this->stock)($this->main, 20);

    ($this->raise)()->assertStatus(201);
    $transfer = InventoryTransfer::latest('id')->first();

    $this->actingAs($this->keeper, 'sanctum')
        ->postJson("/api/v1/field/replenishment/{$transfer->id}/approve", ['fulfillment_method' => 'delivery'])
        ->assertStatus(200);

    // The goods are somewhere between two buildings; calling that off is a
    // return with its own movements, not the absence of a transfer.
    $this->actingAs($this->seller, 'sanctum')
        ->postJson("/api/v1/field/replenishment/{$transfer->id}/cancel")
        ->assertStatus(422);
});

test('only the source may approve and only the destination may receive', function () {
    ($this->stock)($this->main, 20);

    ($this->raise)()->assertStatus(201);
    $transfer = InventoryTransfer::latest('id')->first();

    // The branch cannot approve its own request.
    $this->actingAs($this->seller, 'sanctum')
        ->postJson("/api/v1/field/replenishment/{$transfer->id}/approve", ['fulfillment_method' => 'delivery'])
        ->assertStatus(403);

    $this->actingAs($this->keeper, 'sanctum')
        ->postJson("/api/v1/field/replenishment/{$transfer->id}/approve", ['fulfillment_method' => 'pickup'])
        ->assertStatus(200);

    // And the main warehouse cannot sign for goods on the branch's behalf.
    $this->actingAs($this->keeper, 'sanctum')
        ->postJson("/api/v1/field/replenishment/{$transfer->id}/receive")
        ->assertStatus(403);
});

test('the warehouse being asked can see the request and what to do about it', function () {
    ($this->stock)($this->main, 20);

    ($this->raise)()->assertStatus(201);

    // Outgoing was invisible before: nobody in the main warehouse could see a
    // single request made of them, so nothing could ever be approved.
    $this->actingAs($this->keeper, 'sanctum')
        ->getJson('/api/v1/field/replenishment?direction=outgoing')
        ->assertStatus(200)
        ->assertJsonCount(1, 'data.requests')
        ->assertJsonPath('data.requests.0.direction', 'outgoing')
        ->assertJsonPath('data.requests.0.can_approve', true)
        ->assertJsonPath('data.requests.0.can_receive', false);

    $this->actingAs($this->keeper, 'sanctum')
        ->getJson('/api/v1/field/replenishment?awaiting_my_action=1')
        ->assertStatus(200)
        ->assertJsonCount(1, 'data.requests');

    // The branch sees the same request from the other side.
    $this->actingAs($this->seller, 'sanctum')
        ->getJson('/api/v1/field/replenishment?direction=incoming')
        ->assertStatus(200)
        ->assertJsonPath('data.requests.0.direction', 'incoming')
        ->assertJsonPath('data.requests.0.can_approve', false);
});

test('a preferred method is recorded but the source decides', function () {
    ($this->stock)($this->main, 20);

    ($this->raise)(['preferred_fulfillment_method' => 'pickup'])
        ->assertStatus(201)
        ->assertJsonPath('data.request.fulfillment_method', 'pickup');

    $transfer = InventoryTransfer::latest('id')->first();

    // The warehouse holding the goods knows whether it can spare a driver.
    $this->actingAs($this->keeper, 'sanctum')
        ->postJson("/api/v1/field/replenishment/{$transfer->id}/approve", ['fulfillment_method' => 'delivery'])
        ->assertStatus(200)
        ->assertJsonPath('data.request.fulfillment_method', 'delivery')
        ->assertJsonPath('data.request.status', 'in_transit');
});

test('approving twice does not ship the goods twice', function () {
    ($this->stock)($this->main, 20);

    ($this->raise)()->assertStatus(201);
    $transfer = InventoryTransfer::latest('id')->first();

    $approve = fn () => $this->actingAs($this->keeper, 'sanctum')
        ->postJson("/api/v1/field/replenishment/{$transfer->id}/approve", ['fulfillment_method' => 'delivery']);

    $approve()->assertStatus(200);
    $approve()->assertStatus(422);

    expect((int) ($this->at)($this->main)->quantity)->toBe(14);
});
