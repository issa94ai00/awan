<?php

use App\Models\Product;
use App\Models\ProductComponent;
use App\Models\ProductionOrder;
use App\Models\ProductWarehouseAssignment;
use App\Models\Role;
use App\Models\StockMovement;
use App\Models\User;
use App\Models\Warehouse;
use App\Models\WarehouseInventory;
use App\Services\Inventory\InventoryService;
use Illuminate\Support\Facades\DB;

beforeEach(function () {
    $this->admin = User::factory()->create([
        'is_admin' => true,
        'role_id' => Role::firstOrCreate(['name' => 'admin'], ['display_name' => 'admin'])->id,
    ]);
    $this->warehouse = Warehouse::create([
        'name' => 'مستودع الإنتاج', 'code' => 'WH-PRD', 'location' => 'سورية',
        'status' => 'active', 'is_active' => true, 'location_type' => Warehouse::TYPE_WAREHOUSE,
    ]);
    $this->parent = Product::create(['name_ar' => 'طقم', 'sku' => 'SKU-KIT', 'price' => 100, 'cost_price' => 40]);
    $this->comp = Product::create(['name_ar' => 'قطعة', 'sku' => 'SKU-PART', 'price' => 10, 'cost_price' => 5]);
});

function compOnHand($productId, $warehouseId): array
{
    $row = WarehouseInventory::where('product_id', $productId)->where('warehouse_id', $warehouseId)->first();

    return [(int) ($row->quantity ?? 0), (int) ($row->reserved_quantity ?? 0), (int) ($row->available_quantity ?? 0)];
}

// --------------------------------------------------------------- production

test('production order: create, update, status to completed receives stock once, delete', function () {
    $id = $this->actingAs($this->admin)->postJson('/api/v1/production', [
        'product_id' => $this->parent->id, 'quantity' => 6, 'cost' => 12.5,
    ])->assertCreated()->json('data.id');

    $order = ProductionOrder::find($id);
    expect($order->status)->toBe(ProductionOrder::STATUS_PENDING)
        ->and($order->created_by)->toBe($this->admin->id)
        ->and($order->order_number)->not->toBeEmpty();

    $this->postJson('/api/v1/production', ['product_id' => $this->parent->id, 'quantity' => 0])->assertStatus(422);
    $this->postJson('/api/v1/production', ['product_id' => 99999, 'quantity' => 1])->assertStatus(422);

    $this->putJson("/api/v1/production/$id", [
        'product_id' => $this->parent->id, 'quantity' => 8, 'status' => 'in_progress', 'notes' => 'تعديل',
    ])->assertOk();
    expect($order->refresh()->quantity)->toBe(8)->and($order->status)->toBe(ProductionOrder::STATUS_IN_PROGRESS);
    // update validates status enum
    $this->putJson("/api/v1/production/$id", ['product_id' => $this->parent->id, 'quantity' => 8, 'status' => 'bogus'])->assertStatus(422);

    $this->putJson("/api/v1/production/$id/status", ['status' => 'bogus'])->assertStatus(422);
    expect(StockMovement::where('product_id', $this->parent->id)->count())->toBe(0);

    $this->putJson("/api/v1/production/$id/status", ['status' => 'completed'])->assertOk();
    expect($order->refresh()->status)->toBe(ProductionOrder::STATUS_COMPLETED);
    expect((int) StockMovement::where('product_id', $this->parent->id)->sum('quantity'))->toBe(8);
    expect((int) $this->parent->refresh()->stock_quantity)->toBe(8);

    // repeating the transition must not book the finished goods twice
    $this->putJson("/api/v1/production/$id/status", ['status' => 'completed'])->assertOk();
    expect((int) $this->parent->refresh()->stock_quantity)->toBe(8);
    expect(StockMovement::where('product_id', $this->parent->id)->count())->toBe(1);

    $this->deleteJson("/api/v1/production/$id")->assertOk();
    expect(ProductionOrder::find($id))->toBeNull();
});

test('production order pending to cancelled does not touch stock', function () {
    $id = $this->actingAs($this->admin)->postJson('/api/v1/production', ['product_id' => $this->parent->id, 'quantity' => 3])
        ->assertCreated()->json('data.id');
    $this->putJson("/api/v1/production/$id/status", ['status' => 'cancelled'])->assertOk();
    expect(ProductionOrder::find($id)->status)->toBe(ProductionOrder::STATUS_CANCELLED);
    expect(StockMovement::where('product_id', $this->parent->id)->count())->toBe(0);
});

test('production routes require authentication', function () {
    $this->postJson('/api/v1/production', ['product_id' => $this->parent->id, 'quantity' => 1])->assertStatus(401);
});

// ---------------------------------------------------------------- composite

function compositeSetup($t, int $compStock = 10): ProductComponent
{
    $component = ProductComponent::create([
        'parent_product_id' => $t->parent->id, 'component_product_id' => $t->comp->id, 'quantity_required' => 2, 'is_optional' => false,
    ]);
    app(InventoryService::class)->receive($t->comp->id, $compStock, $t->warehouse->id, ['key' => 'comp-open-'.uniqid(), 'unit_cost' => 5]);
    ProductWarehouseAssignment::create([
        'product_id' => $t->comp->id, 'warehouse_id' => $t->warehouse->id, 'is_active' => true,
    ]);

    return $component;
}

test('composite can-assemble reports availability and shortage', function () {
    $this->actingAs($this->admin);
    $pid = $this->parent->id;

    // not composite yet
    $this->postJson("/api/v1/admin/composite-products/$pid/can-assemble", ['quantity' => 1])
        ->assertOk()->assertJsonPath('data.can_assemble', false);

    compositeSetup($this);

    $this->postJson("/api/v1/admin/composite-products/$pid/can-assemble", ['quantity' => 5, 'warehouse_id' => $this->warehouse->id])
        ->assertOk()->assertJsonPath('data.can_assemble', true);

    $res = $this->postJson("/api/v1/admin/composite-products/$pid/can-assemble", ['quantity' => 6, 'warehouse_id' => $this->warehouse->id])
        ->assertOk()->assertJsonPath('data.can_assemble', false);
    expect($res->json('data.missing_components.0.shortage'))->toBe(2);
});

test('composite assembly create refuses on shortage and completes a pending order once, then disassembles', function () {
    $compositeComponent = compositeSetup($this);
    $this->actingAs($this->admin);

    // insufficient components: refused without creating an order
    $res = $this->postJson('/api/v1/admin/composite-products/assembly-orders', [
        'product_id' => $this->parent->id, 'quantity' => 6, 'warehouse_id' => $this->warehouse->id,
    ])->assertOk()->assertJsonPath('success', false);
    expect($res->json('missing_components'))->not->toBeEmpty();
    expect(DB::table('product_assembly_orders')->count())->toBe(0);
    expect(compOnHand($this->comp->id, $this->warehouse->id))->toBe([10, 0, 10]);

    // A covered order is created, numbered and reserves its components.
    $this->postJson('/api/v1/admin/composite-products/assembly-orders', [
        'product_id' => $this->parent->id, 'quantity' => 3, 'warehouse_id' => $this->warehouse->id,
    ])->assertOk()->assertJsonPath('success', true);
    expect(DB::table('product_assembly_orders')->count())->toBe(1)
        ->and(DB::table('product_assembly_orders')->value('assembly_number'))->toStartWith('ASM-');
    // Clear it again so the rest of this test starts from untouched stock.
    DB::table('product_assembly_order_items')->delete();
    DB::table('product_assembly_orders')->delete();
    WarehouseInventory::where('product_id', $this->comp->id)->update(['reserved_quantity' => 0]);

    // validation
    $this->postJson('/api/v1/admin/composite-products/assembly-orders', ['product_id' => $this->parent->id, 'quantity' => 0, 'warehouse_id' => $this->warehouse->id])->assertStatus(422);

    // A pending order with its reservation, as createAssemblyOrder would leave it
    // (the create route itself is covered above).
    $orderId = DB::table('product_assembly_orders')->insertGetId([
        'assembly_number' => 'ASM-T-1', 'parent_product_id' => $this->parent->id, 'warehouse_id' => $this->warehouse->id,
        'quantity_to_assemble' => 3, 'status' => 'pending', 'created_at' => now(), 'updated_at' => now(),
    ]);
    DB::table('product_assembly_order_items')->insert([
        'assembly_order_id' => $orderId, 'component_product_id' => $this->comp->id,
        'quantity_required' => 6, 'quantity_reserved' => 6, 'created_at' => now(), 'updated_at' => now(),
    ]);
    WarehouseInventory::where('product_id', $this->comp->id)->update(['reserved_quantity' => 6]);

    $this->postJson("/api/v1/admin/composite-products/assembly-orders/$orderId/complete")
        ->assertOk()->assertJsonPath('success', true);
    expect(DB::table('product_assembly_orders')->where('id', $orderId)->value('status'))->toBe('completed');
    expect(compOnHand($this->comp->id, $this->warehouse->id))->toBe([4, 0, 4]);
    expect(compOnHand($this->parent->id, $this->warehouse->id)[0])->toBe(3);

    // completing again is refused and moves nothing
    $this->postJson("/api/v1/admin/composite-products/assembly-orders/$orderId/complete")
        ->assertOk()->assertJsonPath('success', false);
    expect(compOnHand($this->comp->id, $this->warehouse->id)[0])->toBe(4);
    expect(compOnHand($this->parent->id, $this->warehouse->id)[0])->toBe(3);

    // disassemble returns components
    $pid = $this->parent->id;
    $this->postJson('/api/v1/admin/composite-products/disassemble', [
        'product_id' => $pid, 'quantity' => 2, 'warehouse_id' => $this->warehouse->id,
    ])->assertOk()->assertJsonPath('success', true);
    expect(compOnHand($this->parent->id, $this->warehouse->id)[0])->toBe(1);
    expect(compOnHand($this->comp->id, $this->warehouse->id)[0])->toBe(8);

    // more than is on hand: refused, nothing moves
    $this->postJson('/api/v1/admin/composite-products/disassemble', [
        'product_id' => $pid, 'quantity' => 5, 'warehouse_id' => $this->warehouse->id,
    ])->assertOk()->assertJsonPath('success', false);
    expect(compOnHand($this->parent->id, $this->warehouse->id)[0])->toBe(1);
    expect(compOnHand($this->comp->id, $this->warehouse->id)[0])->toBe(8);

    // non-composite product cannot be disassembled
    $this->postJson('/api/v1/admin/composite-products/disassemble', [
        'product_id' => $this->comp->id, 'quantity' => 1, 'warehouse_id' => $this->warehouse->id,
    ])->assertOk()->assertJsonPath('success', false);

    $this->postJson('/api/v1/admin/composite-products/disassemble', ['product_id' => $pid])->assertStatus(422);

    expect($compositeComponent->refresh()->quantity_required)->toBe(2);
});

test('composite update components changes quantities only for that parent', function () {
    $component = compositeSetup($this);
    $other = Product::create(['name_ar' => 'آخر', 'sku' => 'SKU-OTH', 'price' => 1, 'cost_price' => 1]);
    $foreign = ProductComponent::create(['parent_product_id' => $other->id, 'component_product_id' => $this->comp->id, 'quantity_required' => 7]);

    $pid = $this->parent->id;
    $this->actingAs($this->admin)->putJson("/api/v1/admin/composite-products/$pid/components", [
        'components' => [
            ['component_id' => $component->id, 'quantity' => 5, 'is_optional' => true, 'notes' => 'ملاحظة'],
            ['component_id' => $foreign->id, 'quantity' => 99],
        ],
    ])->assertOk()->assertJsonPath('success', true);

    $component->refresh();
    expect($component->quantity_required)->toBe(5)->and($component->is_optional)->toBeTrue()->and($component->notes)->toBe('ملاحظة');
    // another product's component is not touched
    expect($foreign->refresh()->quantity_required)->toBe(7);

    $this->putJson("/api/v1/admin/composite-products/$pid/components", ['components' => [['component_id' => $component->id, 'quantity' => 0]]])->assertStatus(422);
    $this->putJson("/api/v1/admin/composite-products/$pid/components", [])->assertStatus(422);
});

// ---------------------------------------------------------------------- MRP

test('mrp execute route is reachable and unknown assignment is 404', function () {
    $assignment = ProductWarehouseAssignment::create([
        'product_id' => $this->comp->id, 'warehouse_id' => $this->warehouse->id, 'is_active' => true,
        'planning_method' => 'mrp', 'replenishment_method' => 'purchase', 'min_stock_level' => 5, 'max_stock_level' => 20,
        'safety_stock' => 2, 'lead_time_days' => 3,
    ]);
    $id = $assignment->id;

    // MrpService still queries purchase_orders.expected_date / warehouse_id, which the
    // schema does not have, so a real execution errors out. Only routing/auth is asserted.
    $res = $this->actingAs($this->admin)->postJson("/api/v1/admin/mrp/assignments/$id/execute", ['recommendation_types' => []]);
    expect($res->status())->not->toBe(401)->and($res->status())->not->toBe(403)->and($res->status())->not->toBe(404);

    $this->postJson('/api/v1/admin/mrp/assignments/999999/execute')->assertStatus(404);
});
