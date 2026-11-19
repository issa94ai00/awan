<?php

use App\Models\CycleCount;
use App\Models\Customer;
use App\Models\PickingList;
use App\Models\PickingListItem;
use App\Models\Product;
use App\Models\ProductWarehouseAssignment;
use App\Models\Role;
use App\Models\SalesOrder;
use App\Models\ShippingManifest;
use App\Models\StockMovement;
use App\Models\User;
use App\Models\Warehouse;
use App\Models\WarehouseBin;
use App\Models\WarehouseInventory;
use App\Services\Inventory\InventoryService;

beforeEach(function () {
    $this->admin = User::factory()->create([
        'is_admin' => true,
        'role_id' => Role::firstOrCreate(['name' => 'admin'], ['display_name' => 'admin'])->id,
    ]);

    $this->warehouse = Warehouse::create([
        'name' => 'مستودع الكتابة', 'code' => 'WH-WR', 'location' => 'سورية',
        'status' => 'active', 'is_active' => true, 'location_type' => Warehouse::TYPE_WAREHOUSE,
    ]);
    $this->product = Product::create([
        'name_ar' => 'منتج كتابة', 'sku' => 'SKU-WR', 'price' => 50, 'cost_price' => 20,
    ]);
});

function wmsOnHand($productId, $warehouseId): int
{
    return (int) WarehouseInventory::where('product_id', $productId)->where('warehouse_id', $warehouseId)->value('quantity');
}

function wmsOrder($warehouse, $product, int $qty = 4): SalesOrder
{
    $customer = Customer::create(['name' => 'عميل '.uniqid(), 'email' => uniqid().'@example.test', 'status' => 'active']);
    $order = SalesOrder::create([
        'order_number' => 'SO-WR-'.uniqid(), 'customer_id' => $customer->id,
        'status' => SalesOrder::STATUS_CONFIRMED, 'order_date' => now()->toDateString(),
        'subtotal' => 50 * $qty, 'discount' => 0, 'tax' => 0, 'total' => 50 * $qty, 'currency' => 'SYP',
        'fulfillment_warehouse_id' => $warehouse->id,
    ]);
    $order->items()->create([
        'product_id' => $product->id, 'quantity' => $qty, 'unit_price' => 50, 'discount' => 0, 'tax' => 0, 'total' => 50 * $qty,
    ]);

    return $order;
}

// ---------------------------------------------------------------- warehouses

test('wms warehouses: create, update (primary swap) and delete guard', function () {
    $payload = ['name' => 'جديد', 'code' => 'WH-NEW', 'location_type' => 'branch', 'is_primary' => true];

    $id = $this->actingAs($this->admin)->postJson('/api/v1/wms/warehouses', $payload)
        ->assertCreated()->json('id');
    expect(Warehouse::find($id)->is_primary)->toBeTruthy();

    // duplicate code refused
    $this->postJson('/api/v1/wms/warehouses', $payload)->assertStatus(422);

    $other = $this->postJson('/api/v1/wms/warehouses', ['name' => 'ثاني', 'code' => 'WH-NEW2', 'location_type' => 'warehouse', 'is_primary' => true])
        ->assertCreated()->json('id');
    expect(Warehouse::find($id)->is_primary)->toBeFalsy();

    $this->putJson("/api/v1/wms/warehouses/$id", ['name' => 'مُعدّل', 'is_primary' => true])->assertOk();
    expect(Warehouse::find($id)->name)->toBe('مُعدّل');
    expect(Warehouse::find($id)->is_primary)->toBeTruthy();
    expect(Warehouse::find($other)->is_primary)->toBeFalsy();

    // a warehouse holding stock cannot be deleted
    app(InventoryService::class)->receive($this->product->id, 5, $id, ['key' => 'wh-del-'.uniqid()]);
    $this->deleteJson("/api/v1/wms/warehouses/$id")->assertStatus(422);
    expect(Warehouse::find($id))->not->toBeNull();

    $this->deleteJson("/api/v1/wms/warehouses/$other")->assertOk();
    expect(Warehouse::find($other))->toBeNull();
});

test('wms warehouses require admin', function () {
    $user = User::factory()->create(['is_admin' => false]);
    $this->actingAs($user)->postJson('/api/v1/wms/warehouses', ['name' => 'x', 'code' => 'X1', 'location_type' => 'branch'])
        ->assertStatus(403);
    expect(Warehouse::where('code', 'X1')->exists())->toBeFalse();
});

test('admin wms warehouses endpoints work the same', function () {
    $id = $this->actingAs($this->admin)->postJson('/api/v1/admin/wms/warehouses', ['name' => 'أ', 'code' => 'WH-ADM', 'location_type' => 'branch'])
        ->assertCreated()->json('id');
    $this->putJson("/api/v1/admin/wms/warehouses/$id", ['city' => 'حلب'])->assertOk();
    expect(Warehouse::find($id)->city)->toBe('حلب');
    $this->deleteJson("/api/v1/admin/wms/warehouses/$id")->assertOk();
    expect(Warehouse::find($id))->toBeNull();
});

// -------------------------------------------------------------- assignments

test('wms assignments: create, duplicate refused, update recount, delete guard', function () {
    $payload = [
        'product_id' => $this->product->id, 'warehouse_id' => $this->warehouse->id,
        'replenishment_method' => 'purchase', 'planning_method' => 'rop',
        'min_stock_level' => 2, 'max_stock_level' => 20, 'safety_stock' => 1, 'lead_time_days' => 3,
    ];

    $id = $this->actingAs($this->admin)->postJson('/api/v1/wms/assignments', $payload)
        ->assertOk()->json('data.id');
    expect(ProductWarehouseAssignment::find($id))->not->toBeNull();
    expect(WarehouseInventory::where('product_id', $this->product->id)->where('warehouse_id', $this->warehouse->id)->exists())->toBeTrue();

    $this->postJson('/api/v1/wms/assignments', $payload)->assertStatus(400);
    $this->postJson('/api/v1/wms/assignments', array_merge($payload, ['max_stock_level' => 1]))->assertStatus(422);

    // recount books a stock movement via the inventory service
    $this->putJson("/api/v1/wms/assignments/$id", ['quantity' => 9, 'notes' => 'جرد'])->assertOk();
    expect(wmsOnHand($this->product->id, $this->warehouse->id))->toBe(9);
    expect(StockMovement::where('product_id', $this->product->id)->where('warehouse_id', $this->warehouse->id)->exists())->toBeTrue();

    // cannot delete while stock is present
    $this->deleteJson("/api/v1/wms/assignments/$id")->assertStatus(400);
    expect(ProductWarehouseAssignment::find($id))->not->toBeNull();

    $this->putJson("/api/v1/wms/assignments/$id", ['quantity' => 0])->assertOk();
    $this->deleteJson("/api/v1/wms/assignments/$id")->assertOk();
    expect(ProductWarehouseAssignment::find($id))->toBeNull();
});

// ----------------------------------------------------------- stock movements

test('wms stock movements: in, out, insufficient stock 422, idempotent key', function () {
    $base = ['product_id' => $this->product->id, 'warehouse_id' => $this->warehouse->id];

    $this->actingAs($this->admin)->postJson('/api/v1/wms/stock/movements', $base + ['movement_type' => 'in', 'quantity' => 10, 'movement_key' => 'k-in-1'])
        ->assertOk();
    expect(wmsOnHand($this->product->id, $this->warehouse->id))->toBe(10);

    // same key replays as a no-op
    $this->postJson('/api/v1/wms/stock/movements', $base + ['movement_type' => 'in', 'quantity' => 10, 'movement_key' => 'k-in-1'])->assertOk();
    expect(wmsOnHand($this->product->id, $this->warehouse->id))->toBe(10);

    $this->postJson('/api/v1/wms/stock/movements', $base + ['movement_type' => 'out', 'quantity' => 4])->assertOk();
    expect(wmsOnHand($this->product->id, $this->warehouse->id))->toBe(6);

    $this->postJson('/api/v1/wms/stock/movements', $base + ['movement_type' => 'out', 'quantity' => 100])->assertStatus(422);
    expect(wmsOnHand($this->product->id, $this->warehouse->id))->toBe(6);

    // transfer needs a destination
    $this->postJson('/api/v1/wms/stock/movements', $base + ['movement_type' => 'transfer', 'quantity' => 1])->assertStatus(422);
});

test('non-admin cannot post wms stock movements', function () {
    $user = User::factory()->create(['is_admin' => false]);
    $this->actingAs($user)->postJson('/api/v1/wms/stock/movements', [
        'product_id' => $this->product->id, 'warehouse_id' => $this->warehouse->id, 'movement_type' => 'in', 'quantity' => 5,
    ])->assertStatus(403);
    expect(wmsOnHand($this->product->id, $this->warehouse->id))->toBe(0);
});

// --------------------------------------------------------------------- bins

test('admin wms bins: update and delete guard', function () {
    $bin = WarehouseBin::create([
        'warehouse_id' => $this->warehouse->id, 'code' => 'B-1', 'bin_code' => 'B-1', 'name' => 'رف', 'type' => 'storage',
        'capacity_type' => 'count', 'capacity_value' => 10, 'is_active' => true,
    ]);
    $id = $bin->id;

    $this->actingAs($this->admin)->putJson("/api/v1/admin/wms/bins/$id", ['code' => 'B-1X', 'name' => 'رف معدل'])->assertOk();
    $bin->refresh();
    expect($bin->code)->toBe('B-1X')->and($bin->bin_code)->toBe('B-1X')->and($bin->name)->toBe('رف معدل');

    // bin holding inventory is protected
    $wi = WarehouseInventory::create([
        'product_id' => $this->product->id, 'warehouse_id' => $this->warehouse->id, 'quantity' => 3, 'reserved_quantity' => 0, 'bin_id' => $id,
    ]);
    $this->deleteJson("/api/v1/admin/wms/bins/$id")->assertStatus(400);
    expect(WarehouseBin::find($id))->not->toBeNull();

    $wi->delete();
    $this->deleteJson("/api/v1/admin/wms/bins/$id")->assertOk();
    expect(WarehouseBin::find($id))->toBeNull();
});

// ------------------------------------------------------------ picking lists

test('wms picking lifecycle: create, start, pick, complete', function () {
    $order = wmsOrder($this->warehouse, $this->product, 4);

    $listId = $this->actingAs($this->admin)->postJson('/api/v1/wms/picking-lists', [
        'sales_order_id' => $order->id, 'warehouse_id' => $this->warehouse->id,
    ])->assertCreated()->json('id');
    $list = PickingList::find($listId);
    expect($list->status)->toBe(PickingList::STATUS_PENDING);
    $item = $list->items()->first();
    expect($item->quantity_to_pick)->toBe(4);
    $itemId = $item->id;

    // picking before start refused
    $this->postJson("/api/v1/wms/picking-items/$itemId", ['quantity' => 4])->assertStatus(422);
    // completing before start refused
    $this->postJson("/api/v1/wms/picking-lists/$listId/complete")->assertStatus(422);

    $this->postJson("/api/v1/wms/picking-lists/$listId/start")->assertOk();
    expect($list->refresh()->status)->toBe(PickingList::STATUS_IN_PROGRESS);
    expect($list->picker_id)->toBe($this->admin->id);
    // cannot start twice
    $this->postJson("/api/v1/wms/picking-lists/$listId/start")->assertStatus(422);

    // completing with unpicked items refused
    $this->postJson("/api/v1/wms/picking-lists/$listId/complete")->assertStatus(422);
    // over-pick refused
    $this->postJson("/api/v1/wms/picking-items/$itemId", ['quantity' => 5])->assertStatus(422);

    $this->postJson("/api/v1/wms/picking-items/$itemId", ['quantity' => 4])->assertOk();
    expect($item->refresh()->quantity_picked)->toBe(4);
    // picked twice refused
    $this->postJson("/api/v1/wms/picking-items/$itemId", ['quantity' => 1])->assertStatus(422);

    $this->postJson("/api/v1/wms/picking-lists/$listId/complete")->assertOk();
    expect($list->refresh()->status)->toBe(PickingList::STATUS_COMPLETED);

    // a completed list cannot be cancelled
    $this->postJson("/api/v1/wms/picking-lists/$listId/cancel")->assertStatus(422);
    expect($list->refresh()->status)->toBe(PickingList::STATUS_COMPLETED);
});

test('wms picking: cancel and create without a warehouse refused', function () {
    $order = wmsOrder($this->warehouse, $this->product, 2);

    $listId = $this->actingAs($this->admin)->postJson('/api/v1/wms/picking-lists', ['sales_order_id' => $order->id])
        ->assertCreated()->json('id'); // falls back to the order's fulfilment warehouse
    $this->postJson("/api/v1/wms/picking-lists/$listId/cancel")->assertOk();
    expect(PickingList::find($listId)->status)->toBe(PickingList::STATUS_CANCELLED);

    $noWh = wmsOrder($this->warehouse, $this->product, 2);
    $noWh->update(['fulfillment_warehouse_id' => null]);
    $this->postJson('/api/v1/wms/picking-lists', ['sales_order_id' => $noWh->id])->assertStatus(400);
    $this->postJson('/api/v1/wms/picking-lists', ['sales_order_id' => 999999])->assertStatus(422);
});

test('admin wms picking lifecycle works under the admin prefix', function () {
    $order = wmsOrder($this->warehouse, $this->product, 3);

    $listId = $this->actingAs($this->admin)->postJson('/api/v1/admin/wms/picking-lists', [
        'sales_order_id' => $order->id, 'warehouse_id' => $this->warehouse->id,
    ])->assertCreated()->json('id');
    $itemId = PickingList::find($listId)->items()->value('id');

    $this->postJson("/api/v1/admin/wms/picking-lists/$listId/start")->assertOk();
    $this->postJson("/api/v1/admin/wms/picking-items/$itemId", ['quantity' => 3, 'verified' => true])->assertOk();
    expect(PickingListItem::find($itemId)->quantity_picked)->toBe(3);
    $this->postJson("/api/v1/admin/wms/picking-lists/$listId/complete")->assertOk();
    expect(PickingList::find($listId)->status)->toBe(PickingList::STATUS_COMPLETED);

    $order2 = wmsOrder($this->warehouse, $this->product, 1);
    $list2 = $this->postJson('/api/v1/admin/wms/picking-lists', ['sales_order_id' => $order2->id])->assertCreated()->json('id');
    $this->postJson("/api/v1/admin/wms/picking-lists/$list2/cancel")->assertOk();
    expect(PickingList::find($list2)->status)->toBe(PickingList::STATUS_CANCELLED);
    // start on cancelled refused
    $this->postJson("/api/v1/admin/wms/picking-lists/$list2/start")->assertStatus(422);
});

// ------------------------------------------------------- cycle counts / ship

test('admin wms cycle count start only from pending', function () {
    $this->actingAs($this->admin)->postJson('/api/v1/admin/wms/cycle-counts', [
        'warehouse_id' => $this->warehouse->id, 'type' => 'full',
    ])->assertCreated();
    $countId = CycleCount::latest('id')->value('id');
    expect(CycleCount::find($countId)->status)->toBe(CycleCount::STATUS_PENDING);

    $this->postJson("/api/v1/admin/wms/cycle-counts/$countId/start")->assertOk();
    $count = CycleCount::find($countId);
    expect($count->status)->toBe(CycleCount::STATUS_IN_PROGRESS)->and($count->counter_id)->toBe($this->admin->id);

    $this->postJson("/api/v1/admin/wms/cycle-counts/$countId/start")->assertStatus(422);
});

test('admin wms shipping manifest dispatch only from pending', function () {
    $manifest = ShippingManifest::create([
        'warehouse_id' => $this->warehouse->id, 'manifest_number' => 'SM-WR-1', 'shipping_date' => now()->toDateString(),
        'status' => ShippingManifest::STATUS_PENDING, 'total_packages' => 1,
    ]);
    $id = $manifest->id;

    $this->actingAs($this->admin)->postJson("/api/v1/admin/wms/shipping-manifests/$id/dispatch")->assertOk();
    expect($manifest->refresh()->status)->toBe(ShippingManifest::STATUS_IN_TRANSIT);

    $this->postJson("/api/v1/admin/wms/shipping-manifests/$id/dispatch")->assertStatus(400);
});
