<?php

use App\Models\Product;
use App\Models\ProductBatch;
use App\Models\ProductSerialNumber;
use App\Models\ReorderAlert;
use App\Models\Role;
use App\Models\StockMovement;
use App\Models\User;
use App\Models\Warehouse;
use App\Models\WarehouseInventory;
use App\Services\Inventory\InventoryService;

beforeEach(function () {
    $this->admin = User::factory()->create([
        'is_admin' => true,
        'role_id' => Role::firstOrCreate(['name' => 'admin'], ['display_name' => 'admin'])->id,
    ]);
    $this->wh = Warehouse::create([
        'name' => 'مستودع المخزون', 'code' => 'WH-INV', 'location' => 'سورية',
        'status' => 'active', 'is_active' => true, 'location_type' => Warehouse::TYPE_WAREHOUSE,
    ]);
    $this->product = Product::create(['name_ar' => 'منتج مخزون', 'sku' => 'SKU-INV', 'price' => 30, 'cost_price' => 10]);
});

function invRow($productId, $warehouseId): ?WarehouseInventory
{
    return WarehouseInventory::where('product_id', $productId)->where('warehouse_id', $warehouseId)->first();
}

function invStock($t, int $qty = 10): void
{
    app(InventoryService::class)->receive($t->product->id, $qty, $t->wh->id, ['key' => 'inv-open-'.uniqid(), 'unit_cost' => 10]);
}

test('batch create books stock in, rejects duplicates and past expiry', function () {
    $payload = [
        'product_id' => $this->product->id, 'warehouse_id' => $this->wh->id, 'batch_number' => 'B-100',
        'quantity' => 12, 'expiry_date' => now()->addYear()->toDateString(), 'unit_cost' => 9.5,
    ];

    $id = $this->actingAs($this->admin)->postJson('/api/v1/inventory/batches', $payload)
        ->assertCreated()->json('data.id');
    expect(ProductBatch::find($id)->quantity)->toBe(12);
    expect((int) invRow($this->product->id, $this->wh->id)->quantity)->toBe(12);
    expect(StockMovement::where('product_id', $this->product->id)->where('warehouse_id', $this->wh->id)->count())->toBe(1);

    $this->postJson('/api/v1/inventory/batches', $payload)->assertStatus(422);
    $this->postJson('/api/v1/inventory/batches', array_merge($payload, ['batch_number' => 'B-101', 'expiry_date' => now()->subDay()->toDateString()]))->assertStatus(422);
    expect((int) invRow($this->product->id, $this->wh->id)->quantity)->toBe(12);
});

test('serial numbers create books one unit each and rejects duplicates', function () {
    $payload = [
        'product_id' => $this->product->id, 'warehouse_id' => $this->wh->id,
        'serial_numbers' => ['SN-1', 'SN-2', 'SN-3'],
    ];

    $this->actingAs($this->admin)->postJson('/api/v1/inventory/serial-numbers', $payload)->assertCreated();
    expect(ProductSerialNumber::whereIn('serial_number', ['SN-1', 'SN-2', 'SN-3'])->count())->toBe(3);
    expect((int) invRow($this->product->id, $this->wh->id)->quantity)->toBe(3);

    // duplicate serial refused, nothing booked
    $this->postJson('/api/v1/inventory/serial-numbers', array_merge($payload, ['serial_numbers' => ['SN-3', 'SN-4']]))->assertStatus(422);
    expect(ProductSerialNumber::where('serial_number', 'SN-4')->exists())->toBeFalse();
    expect((int) invRow($this->product->id, $this->wh->id)->quantity)->toBe(3);

    $this->postJson('/api/v1/inventory/serial-numbers', array_merge($payload, ['serial_numbers' => []]))->assertStatus(422);
});

test('check-availability reports availability without changing stock', function () {
    invStock($this);

    $this->actingAs($this->admin)->postJson('/api/v1/inventory/check-availability', [
        'product_id' => $this->product->id, 'warehouse_id' => $this->wh->id, 'quantity' => 10,
    ])->assertOk()->assertJsonPath('data.is_available', true)->assertJsonPath('data.total_available', 10);

    $this->postJson('/api/v1/inventory/check-availability', [
        'product_id' => $this->product->id, 'warehouse_id' => $this->wh->id, 'quantity' => 11,
    ])->assertOk()->assertJsonPath('data.is_available', false)->assertJsonPath('data.requested_quantity', 11);

    $this->postJson('/api/v1/inventory/check-availability', ['product_id' => $this->product->id, 'warehouse_id' => $this->wh->id, 'quantity' => 0])->assertStatus(422);
    expect((int) invRow($this->product->id, $this->wh->id)->quantity)->toBe(10);
});

test('allocate FIFO takes oldest batch first and refuses when stock is short', function () {
    $this->actingAs($this->admin);
    foreach ([['B-OLD', 5], ['B-NEW', 5]] as [$num, $qty]) {
        $this->postJson('/api/v1/inventory/batches', [
            'product_id' => $this->product->id, 'warehouse_id' => $this->wh->id, 'batch_number' => $num,
            'quantity' => $qty, 'expiry_date' => now()->addYear()->toDateString(), 'unit_cost' => 10,
        ])->assertCreated();
    }
    ProductBatch::where('batch_number', 'B-OLD')->update(['created_at' => now()->subDays(5)]);

    $res = $this->postJson('/api/v1/inventory/allocate', [
        'product_id' => $this->product->id, 'warehouse_id' => $this->wh->id, 'quantity' => 7,
    ])->assertOk()->assertJsonPath('success', true);

    $data = collect($res->json('data'))->values();
    expect($data->sum('quantity'))->toBe(7);
    expect($data->first()['batch_number'])->toBe('B-OLD')->and($data->first()['quantity'])->toBe(5);
    expect(ProductBatch::where('batch_number', 'B-OLD')->value('quantity_reserved'))->toBe(5);
    expect(ProductBatch::where('batch_number', 'B-NEW')->value('quantity_reserved'))->toBe(2);

    // more than is on hand: 422, no further reservation
    $this->postJson('/api/v1/inventory/allocate', [
        'product_id' => $this->product->id, 'warehouse_id' => $this->wh->id, 'quantity' => 500,
    ])->assertStatus(422)->assertJsonPath('success', false);
    expect(ProductBatch::where('batch_number', 'B-NEW')->value('quantity_reserved'))->toBe(2);
});

test('reserve and release move reserved_quantity and refuse the impossible', function () {
    invStock($this);
    // product_variant_id must be sent explicitly (null): omitting it 500s, see report.
    $base = ['product_id' => $this->product->id, 'warehouse_id' => $this->wh->id, 'product_variant_id' => null];
    $this->actingAs($this->admin);

    $this->postJson('/api/v1/inventory/reserve', $base + ['quantity' => 4])->assertOk()->assertJsonPath('success', true);
    expect((int) invRow($this->product->id, $this->wh->id)->reserved_quantity)->toBe(4);

    // cannot reserve more than the free remainder
    $this->postJson('/api/v1/inventory/reserve', $base + ['quantity' => 7])->assertStatus(400)->assertJsonPath('success', false);
    expect((int) invRow($this->product->id, $this->wh->id)->reserved_quantity)->toBe(4);

    // cannot ship-release more than is reserved
    $this->postJson('/api/v1/inventory/release', $base + ['quantity' => 5, 'is_shipped' => true])->assertStatus(400);
    expect((int) invRow($this->product->id, $this->wh->id)->quantity)->toBe(10);

    // plain release hands units back
    $this->postJson('/api/v1/inventory/release', $base + ['quantity' => 3])->assertOk();
    expect((int) invRow($this->product->id, $this->wh->id)->reserved_quantity)->toBe(1);

    // shipped release consumes the reservation as an issue
    $this->postJson('/api/v1/inventory/release', $base + ['quantity' => 1, 'is_shipped' => true])->assertOk();
    $row = invRow($this->product->id, $this->wh->id);
    expect((int) $row->reserved_quantity)->toBe(0)->and((int) $row->quantity)->toBe(9);
    expect((int) $this->product->refresh()->stock_quantity)->toBe(9);

    $this->postJson('/api/v1/inventory/reserve', $base + ['quantity' => 0])->assertStatus(422);
});

test('update-reorder-points recalculates reorder point from safety stock and daily sales', function () {
    invStock($this, 20);
    $row = invRow($this->product->id, $this->wh->id);
    $row->update(['reorder_point' => 50, 'safety_stock' => 5]);
    // 10 units sold in the last 30 days -> 1/3 per day, 7 day default lead time
    app(InventoryService::class)->issue($this->product->id, 10, $this->wh->id, ['key' => 'rp-out']);
    invRow($this->product->id, $this->wh->id)->update(['lead_time_days' => 3]);

    // Rows below their reorder point would raise a ReorderAlert; that path is
    // currently broken (see report), so the stock here stays above it.
    $this->actingAs($this->admin)->postJson('/api/v1/inventory/update-reorder-points', ['warehouse_id' => $this->wh->id])
        ->assertOk()->assertJsonPath('success', true)->assertJsonPath('data.updated_count', 1);

    $row = invRow($this->product->id, $this->wh->id);
    expect((int) $row->reorder_point)->toBe((int) ceil(((float) $row->average_daily_sales * 3) + 5));
    expect((int) $row->reorder_point)->not->toBe(50);
    expect(ReorderAlert::count())->toBe(0);

    $this->postJson('/api/v1/inventory/update-reorder-points', ['warehouse_id' => 99999])->assertStatus(422);
});

test('low-stock alert resolve applies the action and validates it', function () {
    $alert = ReorderAlert::create([
        'product_id' => $this->product->id, 'warehouse_id' => $this->wh->id, 'current_quantity' => 1,
        'reorder_point' => 10, 'safety_stock' => 2, 'suggested_order_quantity' => 5, 'status' => ReorderAlert::STATUS_PENDING, 'alerted_at' => now(),
    ]);
    $id = $alert->id;

    $this->actingAs($this->admin)->putJson("/api/v1/inventory/low-stock-alerts/$id/resolve", ['action' => 'nonsense'])->assertStatus(422);
    expect($alert->refresh()->status)->toBe(ReorderAlert::STATUS_PENDING);

    $this->putJson("/api/v1/inventory/low-stock-alerts/$id/resolve", ['action' => 'ordered'])->assertOk();
    expect($alert->refresh()->status)->toBe(ReorderAlert::STATUS_ORDERED);
    expect($alert->resolved_by)->toBe($this->admin->id);

    $this->putJson("/api/v1/inventory/low-stock-alerts/$id/resolve", ['action' => 'resolved'])->assertOk();
    expect($alert->refresh()->status)->toBe(ReorderAlert::STATUS_RESOLVED);
    expect($alert->resolved_at)->not->toBeNull();

    $this->putJson('/api/v1/inventory/low-stock-alerts/999999/resolve', ['action' => 'dismissed'])->assertStatus(404);
});

test('order-allocations suggest splits a line across warehouses (public route)', function () {
    $second = Warehouse::create([
        'name' => 'الثاني', 'code' => 'WH-INV2', 'location' => 'سورية',
        'status' => 'active', 'is_active' => true, 'location_type' => Warehouse::TYPE_WAREHOUSE,
    ]);
    app(InventoryService::class)->receive($this->product->id, 3, $this->wh->id, ['key' => 'sug-1']);
    app(InventoryService::class)->receive($this->product->id, 4, $second->id, ['key' => 'sug-2']);

    $res = $this->postJson('/api/v1/order-allocations/suggest', ['items' => [['product_id' => $this->product->id, 'quantity' => 6]]])
        ->assertOk()->assertJsonPath('success', true);
    $s = $res->json('data.suggestions.0');
    expect($s['fulfilled'])->toBeTrue()->and($s['shortage'])->toBe(0);
    expect(collect($s['allocations'])->sum('quantity'))->toBe(6);
    expect(count($s['allocations']))->toBe(2);

    $short = $this->postJson('/api/v1/order-allocations/suggest', ['items' => [['product_id' => $this->product->id, 'quantity' => 10]]])->assertOk();
    expect($short->json('data.suggestions.0.fulfilled'))->toBeFalse();
    expect($short->json('data.suggestions.0.shortage'))->toBe(3);

    $this->postJson('/api/v1/order-allocations/suggest', ['items' => []])->assertStatus(422);
    // suggesting never moves stock
    expect((int) invRow($this->product->id, $this->wh->id)->quantity)->toBe(3);
});
