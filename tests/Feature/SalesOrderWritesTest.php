<?php

use App\Models\Employee;
use App\Models\Product;
use App\Models\SalesOrder;
use App\Models\User;
use App\Models\Warehouse;
use App\Models\WarehouseInventory;
use Illuminate\Support\Facades\DB;

function sowWarehouse(string $code, string $type = Warehouse::TYPE_WAREHOUSE): Warehouse
{
    return Warehouse::create([
        'name' => 'WH '.$code, 'code' => $code, 'is_active' => true, 'status' => 'active', 'location_type' => $type,
    ]);
}

function sowStock(Product $product, Warehouse $warehouse, int $qty): void
{
    WarehouseInventory::create([
        'product_id' => $product->id, 'warehouse_id' => $warehouse->id,
        'quantity' => $qty, 'available_quantity' => $qty, 'reserved_quantity' => 0,
        'damaged_quantity' => 0, 'quarantined_quantity' => 0,
    ]);
}

/** @return array{0:SalesOrder,1:int} order and its first line id */
function sowOrder(Product $product, int $qty, ?Warehouse $warehouse = null, string $status = 'pending'): array
{
    $id = DB::table('sales_orders')->insertGetId([
        'order_number' => 'SO-W-'.uniqid(),
        'status' => $status,
        'order_date' => now()->toDateString(),
        'subtotal' => $qty * 100, 'total' => $qty * 100,
        'fulfillment_type' => 'ship',
        'fulfillment_warehouse_id' => $warehouse?->id,
        'created_at' => now(), 'updated_at' => now(),
    ]);
    $itemId = DB::table('sales_order_items')->insertGetId([
        'sales_order_id' => $id, 'product_id' => $product->id, 'quantity' => $qty,
        'unit_price' => 100, 'total' => $qty * 100, 'created_at' => now(), 'updated_at' => now(),
    ]);

    return [SalesOrder::find($id), $itemId];
}

beforeEach(function () {
    $this->admin = User::factory()->admin()->create();
    $this->product = Product::factory()->create(['price' => 100, 'cost_price' => 60]);
});

it('changes a pending order to pickup, zeroing shipping and retargeting the warehouse', function () {
    $main = sowWarehouse('W-MAIN');
    $branch = sowWarehouse('W-BR', Warehouse::TYPE_BRANCH);
    sowStock($this->product, $main, 20);
    sowStock($this->product, $branch, 20);
    [$order] = sowOrder($this->product, 2, $main);
    $order->forceFill(['shipping_cost' => 15])->save();
    $id = $order->id;

    $this->actingAs($this->admin)->postJson("/api/v1/sales-orders/$id/fulfillment-type", [
        'fulfillment_type' => 'pickup',
        'fulfillment_warehouse_id' => $branch->id,
    ])->assertOk()->assertJsonPath('success', true);

    $order->refresh();
    expect($order->fulfillment_type)->toBe('pickup')
        ->and((int) $order->fulfillment_warehouse_id)->toBe($branch->id)
        ->and((float) $order->shipping_cost)->toBe(0.0);
});

it('validates the fulfillment type and refuses a shipped order', function () {
    $main = sowWarehouse('W-MAIN2');
    [$order] = sowOrder($this->product, 1, $main, 'shipped');
    $id = $order->id;

    $this->actingAs($this->admin)->postJson("/api/v1/sales-orders/$id/fulfillment-type", ['fulfillment_type' => 'teleport'])
        ->assertStatus(422);

    $this->actingAs($this->admin)->postJson("/api/v1/sales-orders/$id/fulfillment-type", ['fulfillment_type' => 'pickup'])
        ->assertStatus(422)->assertJsonPath('success', false);

    expect($order->fresh()->fulfillment_type)->toBe('ship');
});

it('saves a sourcing plan that splits a line across warehouses', function () {
    $a = sowWarehouse('W-A');
    $b = sowWarehouse('W-B');
    sowStock($this->product, $a, 10);
    sowStock($this->product, $b, 10);
    [$order, $itemId] = sowOrder($this->product, 6, $a);
    $id = $order->id;

    $this->actingAs($this->admin)->putJson("/api/v1/sales-orders/$id/sourcing", [
        'lines' => [[
            'item_id' => $itemId,
            'sources' => [
                ['warehouse_id' => $a->id, 'quantity' => 4],
                ['warehouse_id' => $b->id, 'quantity' => 2],
            ],
        ]],
    ])->assertOk()->assertJsonPath('success', true);

    $alloc = DB::table('sales_order_item_allocations')->where('sales_order_item_id', $itemId)->pluck('quantity', 'warehouse_id');
    expect((int) $alloc[$a->id])->toBe(4)->and((int) $alloc[$b->id])->toBe(2);
});

it('refuses a sourcing plan whose quantities do not add up to the line', function () {
    $a = sowWarehouse('W-A3');
    sowStock($this->product, $a, 10);
    [$order, $itemId] = sowOrder($this->product, 6, $a);
    $id = $order->id;

    $this->actingAs($this->admin)->putJson("/api/v1/sales-orders/$id/sourcing", [
        'lines' => [['item_id' => $itemId, 'sources' => [['warehouse_id' => $a->id, 'quantity' => 3]]]],
    ])->assertStatus(422)->assertJsonPath('success', false);

    $this->actingAs($this->admin)->putJson("/api/v1/sales-orders/$id/sourcing", [])->assertStatus(422);
    expect(DB::table('sales_order_item_allocations')->where('sales_order_item_id', $itemId)->count())->toBe(0);
});

it('refuses sourcing edits once the order has shipped', function () {
    $a = sowWarehouse('W-A4');
    [$order, $itemId] = sowOrder($this->product, 2, $a, 'shipped');
    $id = $order->id;

    $this->actingAs($this->admin)->putJson("/api/v1/sales-orders/$id/sourcing", [
        'lines' => [['item_id' => $itemId, 'sources' => [['warehouse_id' => $a->id, 'quantity' => 2]]]],
    ])->assertStatus(422);
});

it('field transition: an unconfined user moves an order, illegal jumps are refused', function () {
    $a = sowWarehouse('W-F1');
    sowStock($this->product, $a, 10);
    [$order] = sowOrder($this->product, 2, $a);
    $id = $order->id;

    // pending -> shipped is not a legal step.
    $this->actingAs($this->admin, 'sanctum')->postJson("/api/v1/field/orders/$id/transition", ['status' => 'shipped'])
        ->assertStatus(422)->assertJsonPath('success', false);
    expect($order->fresh()->status)->toBe('pending');

    $this->actingAs($this->admin, 'sanctum')->postJson("/api/v1/field/orders/$id/transition", ['status' => 'bogus'])
        ->assertStatus(422);

    $res = $this->actingAs($this->admin, 'sanctum')->postJson("/api/v1/field/orders/$id/transition", ['status' => 'confirmed']);
    $res->assertOk()->assertJsonPath('success', true);
    expect($order->fresh()->status)->toBe('confirmed');
});

it('field transition: a rep confined to another warehouse gets 403', function () {
    $mine = sowWarehouse('W-MINE', Warehouse::TYPE_BRANCH);
    $other = sowWarehouse('W-OTHER');
    sowStock($this->product, $other, 10);
    [$order] = sowOrder($this->product, 2, $other);
    $id = $order->id;

    $rep = User::factory()->create();
    Employee::create([
        'user_id' => $rep->id, 'warehouse_id' => $mine->id, 'first_name' => 'Rep', 'last_name' => 'One',
        'email' => 'rep.w@example.com', 'phone' => '0911000111', 'status' => 'active',
    ]);

    $this->actingAs($rep, 'sanctum')->postJson("/api/v1/field/orders/$id/transition", ['status' => 'confirmed'])
        ->assertStatus(403);
    expect($order->fresh()->status)->toBe('pending');
});
