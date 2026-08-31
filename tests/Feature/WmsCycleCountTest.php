<?php

use App\Models\CycleCount;
use App\Models\Product;
use App\Models\Role;
use App\Models\User;
use App\Models\Warehouse;
use App\Models\WarehouseInventory;
use App\Services\Inventory\InventoryService;

/**
 * Cycle counts had no working UI at all before this — the create dialog sent
 * fields the backend has never accepted (zone/count_date instead of
 * type/bin_id), and there was no way to record a counted item. This locks
 * down the rebuilt controller: real fields, a server-trusted expected
 * quantity (never the client's), and the full pending -> in_progress ->
 * completed -> reviewed -> adjusted workflow actually moving stock.
 */
beforeEach(function () {
    $this->admin = User::factory()->create([
        'is_admin' => true,
        'role_id' => Role::firstOrCreate(['name' => 'admin'], ['display_name' => 'admin'])->id,
    ]);

    $this->warehouse = Warehouse::create([
        'name' => 'مستودع الجرد', 'code' => 'WH-COUNT', 'location' => 'سورية',
        'status' => 'active', 'is_active' => true, 'location_type' => Warehouse::TYPE_WAREHOUSE,
    ]);

    $this->product = Product::create([
        'name_ar' => 'أنبوب', 'sku' => 'SKU-COUNT', 'price' => 20, 'cost_price' => 8,
    ]);

    app(InventoryService::class)->receive(
        $this->product->id, 50, $this->warehouse->id, ['key' => 'open:'.uniqid()]
    );
});

test('creating a cycle count accepts the real fields (type, bin_id) not a fictional zone/count_date', function () {
    $this->actingAs($this->admin)
        ->postJson('/api/v1/admin/wms/cycle-counts', [
            'warehouse_id' => $this->warehouse->id,
            'type' => 'partial',
            'notes' => 'جرد تجريبي',
        ])
        ->assertCreated()
        ->assertJsonPath('data.count.type', 'partial')
        ->assertJsonPath('data.count.status', 'pending')
        ->assertJsonPath('data.count.can_start', true);
});

test('items cannot be added before the count is started', function () {
    $count = CycleCount::create([
        'warehouse_id' => $this->warehouse->id,
        'count_number' => 'CC-000001',
        'type' => 'full',
        'status' => CycleCount::STATUS_PENDING,
    ]);

    $this->actingAs($this->admin)
        ->postJson("/api/v1/admin/wms/cycle-counts/{$count->id}/items", [
            'product_id' => $this->product->id,
            'counted_quantity' => 40,
        ])
        ->assertStatus(422);
});

test('the expected quantity always comes from the system, never the request', function () {
    $count = CycleCount::create([
        'warehouse_id' => $this->warehouse->id,
        'count_number' => 'CC-000002',
        'type' => 'full',
        'status' => CycleCount::STATUS_IN_PROGRESS,
        'counter_id' => $this->admin->id,
        'started_at' => now(),
    ]);

    // Real stock is 50. A dishonest or buggy client claiming the system also
    // expected 40 must not be able to mask the shortage.
    $response = $this->actingAs($this->admin)
        ->postJson("/api/v1/admin/wms/cycle-counts/{$count->id}/items", [
            'product_id' => $this->product->id,
            'expected_quantity' => 40,
            'counted_quantity' => 40,
        ])
        ->assertOk();

    $item = $response->json('data.count.items.0');
    expect($item['expected_quantity'])->toBe(50);
    expect($item['counted_quantity'])->toBe(40);
    expect($item['variance'])->toBe(-10);
    expect($response->json('data.count.requires_adjustment'))->toBeTrue();
});

test('an empty count cannot be completed, and a completed count cannot be cancelled', function () {
    $count = CycleCount::create([
        'warehouse_id' => $this->warehouse->id,
        'count_number' => 'CC-000003',
        'type' => 'full',
        'status' => CycleCount::STATUS_IN_PROGRESS,
        'counter_id' => $this->admin->id,
        'started_at' => now(),
    ]);

    $this->actingAs($this->admin)
        ->postJson("/api/v1/admin/wms/cycle-counts/{$count->id}/complete")
        ->assertStatus(422);

    $count->items()->create([
        'product_id' => $this->product->id,
        'expected_quantity' => 50,
        'counted_quantity' => 50,
    ]);

    $this->actingAs($this->admin)
        ->postJson("/api/v1/admin/wms/cycle-counts/{$count->id}/complete")
        ->assertOk()
        ->assertJsonPath('data.count.status', 'completed');

    $this->actingAs($this->admin)
        ->postJson("/api/v1/admin/wms/cycle-counts/{$count->id}/cancel")
        ->assertStatus(422);
});

test('the full workflow — count, complete, review, apply — actually moves the shelf', function () {
    $count = CycleCount::create([
        'warehouse_id' => $this->warehouse->id,
        'count_number' => 'CC-000004',
        'type' => 'full',
        'status' => CycleCount::STATUS_IN_PROGRESS,
        'counter_id' => $this->admin->id,
        'started_at' => now(),
    ]);

    // Counted 45 against a real system quantity of 50 — a shortage of 5.
    $this->actingAs($this->admin)
        ->postJson("/api/v1/admin/wms/cycle-counts/{$count->id}/items", [
            'product_id' => $this->product->id,
            'counted_quantity' => 45,
            'variance_reason' => 'damage',
        ])
        ->assertOk();

    $this->actingAs($this->admin)
        ->postJson("/api/v1/admin/wms/cycle-counts/{$count->id}/complete")
        ->assertOk()
        ->assertJsonPath('data.count.requires_adjustment', true)
        ->assertJsonPath('data.count.can_review', true);

    $this->actingAs($this->admin)
        ->postJson("/api/v1/admin/wms/cycle-counts/{$count->id}/review")
        ->assertOk()
        ->assertJsonPath('data.count.can_review', false)
        ->assertJsonPath('data.count.can_apply_adjustment', true);

    $this->actingAs($this->admin)
        ->postJson("/api/v1/admin/wms/cycle-counts/{$count->id}/adjustment")
        ->assertOk()
        ->assertJsonPath('data.count.can_apply_adjustment', false);

    $quantity = WarehouseInventory::where('product_id', $this->product->id)
        ->where('warehouse_id', $this->warehouse->id)->value('quantity');

    expect((float) $quantity)->toBe(45.0);

    // Applying it twice must not move the shelf a second time.
    $this->actingAs($this->admin)
        ->postJson("/api/v1/admin/wms/cycle-counts/{$count->id}/adjustment")
        ->assertStatus(422);
});
