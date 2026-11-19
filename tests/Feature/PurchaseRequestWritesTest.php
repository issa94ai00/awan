<?php

use App\Models\Employee;
use App\Models\Product;
use App\Models\SalesOrder;
use App\Models\User;
use App\Models\Warehouse;
use App\Models\WarehouseInventory;
use Illuminate\Support\Facades\DB;

function prwOrder(Product $product, Warehouse $wh, int $qty = 2, string $status = 'pending'): SalesOrder
{
    $id = DB::table('sales_orders')->insertGetId([
        'order_number' => 'SO-PR-'.uniqid(),
        'status' => $status,
        'order_date' => now()->toDateString(),
        'subtotal' => $qty * 100, 'total' => $qty * 100,
        'fulfillment_type' => 'ship',
        'fulfillment_warehouse_id' => $wh->id,
        'created_at' => now(), 'updated_at' => now(),
    ]);
    DB::table('sales_order_items')->insert([
        'sales_order_id' => $id, 'product_id' => $product->id, 'quantity' => $qty,
        'unit_price' => 100, 'total' => $qty * 100, 'created_at' => now(), 'updated_at' => now(),
    ]);

    return SalesOrder::find($id);
}

beforeEach(function () {
    $this->admin = User::factory()->admin()->create();
    $this->wh = Warehouse::create(['name' => 'WH PR', 'code' => 'W-PR', 'is_active' => true, 'status' => 'active', 'location_type' => Warehouse::TYPE_WAREHOUSE]);
    $this->product = Product::factory()->create(['price' => 100, 'cost_price' => 60]);
    WarehouseInventory::create([
        'product_id' => $this->product->id, 'warehouse_id' => $this->wh->id,
        'quantity' => 50, 'available_quantity' => 50, 'reserved_quantity' => 0,
        'damaged_quantity' => 0, 'quarantined_quantity' => 0,
    ]);
});

it('assigns and unassigns the responsible employee', function () {
    $order = prwOrder($this->product, $this->wh);
    $id = $order->id;
    $employee = Employee::create([
        'warehouse_id' => $this->wh->id, 'first_name' => 'Emp', 'last_name' => 'One',
        'email' => 'emp.pr@example.com', 'phone' => '0933000111', 'status' => 'active',
    ]);

    $this->actingAs($this->admin)->putJson("/api/v1/purchase-requests/$id/assign", ['employee_id' => $employee->id])
        ->assertOk()->assertJsonPath('data.assigned_employee.id', $employee->id);
    expect($order->fresh()->assigned_employee_id)->toBe($employee->id);

    $this->actingAs($this->admin)->putJson("/api/v1/purchase-requests/$id/assign", ['employee_id' => null])->assertOk();
    expect($order->fresh()->assigned_employee_id)->toBeNull();

    $this->actingAs($this->admin)->putJson("/api/v1/purchase-requests/$id/assign", ['employee_id' => 999999])
        ->assertStatus(422)->assertJsonValidationErrors('employee_id');
});

it('replaces the lines of a pending order and recomputes totals', function () {
    $order = prwOrder($this->product, $this->wh);
    $id = $order->id;
    $other = Product::factory()->create(['price' => 250, 'cost_price' => 100]);
    WarehouseInventory::create([
        'product_id' => $other->id, 'warehouse_id' => $this->wh->id,
        'quantity' => 50, 'available_quantity' => 50, 'reserved_quantity' => 0,
        'damaged_quantity' => 0, 'quarantined_quantity' => 0,
    ]);

    $this->actingAs($this->admin)->putJson("/api/v1/purchase-requests/$id/items", [
        'items' => [[
            'product_id' => $other->id, 'quantity' => 3,
            'allocations' => [['warehouse_id' => $this->wh->id, 'quantity' => 3]],
        ]],
    ])->assertOk();

    $order->refresh();
    expect($order->items()->count())->toBe(1)
        ->and($order->items()->first()->product_id)->toBe($other->id)
        ->and((float) $order->total)->toBe(750.0)
        ->and(DB::table('sales_order_item_allocations')->sum('quantity'))->toBe(3);
});

it('refuses mismatched allocations, empty items and edits after confirmation', function () {
    $order = prwOrder($this->product, $this->wh);
    $id = $order->id;

    $this->actingAs($this->admin)->putJson("/api/v1/purchase-requests/$id/items", ['items' => []])->assertStatus(422);

    $this->actingAs($this->admin)->putJson("/api/v1/purchase-requests/$id/items", [
        'items' => [[
            'product_id' => $this->product->id, 'quantity' => 3,
            'allocations' => [['warehouse_id' => $this->wh->id, 'quantity' => 1]],
        ]],
    ])->assertStatus(422);
    expect((int) $order->items()->first()->quantity)->toBe(2);

    $confirmed = prwOrder($this->product, $this->wh, 2, 'confirmed');
    $cid = $confirmed->id;
    $this->actingAs($this->admin)->putJson("/api/v1/purchase-requests/$cid/items", [
        'items' => [['product_id' => $this->product->id, 'quantity' => 1]],
    ])->assertStatus(422)->assertJsonPath('success', false);
});

it('moves status through the workflow and refuses a return to pending', function () {
    $order = prwOrder($this->product, $this->wh);
    $id = $order->id;

    $this->actingAs($this->admin)->putJson("/api/v1/purchase-requests/$id/status", ['status' => 'pending'])->assertStatus(422);
    $this->actingAs($this->admin)->putJson("/api/v1/purchase-requests/$id/status", ['status' => 'nonsense'])->assertStatus(422);
    $this->actingAs($this->admin)->putJson("/api/v1/purchase-requests/$id/status", ['status' => 'shipped'])->assertStatus(422);
    expect($order->fresh()->status)->toBe('pending');

    $this->actingAs($this->admin)->putJson("/api/v1/purchase-requests/$id/status", ['status' => 'confirmed'])
        ->assertOk()->assertJsonPath('data.status', 'confirmed');
    expect($order->fresh()->status)->toBe('confirmed');
});

it('only lets an admin confirm a purchase request', function () {
    $order = prwOrder($this->product, $this->wh);
    $id = $order->id;
    $staff = User::factory()->create();

    $this->actingAs($staff)->putJson("/api/v1/purchase-requests/$id/status", ['status' => 'confirmed'])->assertStatus(403);
    expect($order->fresh()->status)->toBe('pending');
});
