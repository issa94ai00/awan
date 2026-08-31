<?php

use App\Models\CycleCount;
use App\Models\PickingList;
use App\Models\PickingListItem;
use App\Models\Product;
use App\Models\Role;
use App\Models\User;
use App\Models\Warehouse;

/**
 * Performance.vue used to be entirely fabricated — every KPI a hardcoded
 * literal (picking_accuracy: 98.5, a made-up 6-month trend). This pins the
 * real replacement down: accuracy computed from actual short-picked items
 * and cycle-count variance, an honest null (not a fake 0 or 100) when a
 * period has no completed activity, and the pre-existing bug where omitting
 * warehouse_id (an "all warehouses" view) silently returned zero data
 * instead of the real aggregate.
 */
beforeEach(function () {
    $this->admin = User::factory()->create([
        'is_admin' => true,
        'role_id' => Role::firstOrCreate(['name' => 'admin'], ['display_name' => 'admin'])->id,
    ]);

    $this->warehouseA = Warehouse::create([
        'name' => 'مستودع الأداء أ', 'code' => 'WH-PERF-A', 'location' => 'سورية',
        'status' => 'active', 'is_active' => true, 'location_type' => Warehouse::TYPE_WAREHOUSE,
    ]);

    $this->warehouseB = Warehouse::create([
        'name' => 'مستودع الأداء ب', 'code' => 'WH-PERF-B', 'location' => 'سورية',
        'status' => 'active', 'is_active' => true, 'location_type' => Warehouse::TYPE_WAREHOUSE,
    ]);

    $this->product = Product::create(['name_ar' => 'صنف أداء', 'sku' => 'SKU-PERF', 'price' => 15, 'cost_price' => 6]);
});

function makeCompletedPickingList(Warehouse $warehouse, Product $product, int $toPick, int $picked): PickingList
{
    $list = PickingList::create([
        'warehouse_id' => $warehouse->id,
        'list_number' => 'PL-PERF-'.uniqid(),
        'priority' => PickingList::PRIORITY_NORMAL,
        'status' => PickingList::STATUS_COMPLETED,
        'total_items' => 1,
        'picked_items' => 1,
        'started_at' => now(),
        'completed_at' => now(),
    ]);

    $list->items()->create([
        'product_id' => $product->id,
        'quantity_to_pick' => $toPick,
        'quantity_picked' => $picked,
        'status' => $picked >= $toPick ? PickingListItem::STATUS_PICKED : PickingListItem::STATUS_SHORT,
        'sort_order' => 1,
    ]);

    return $list;
}

test('picking accuracy reflects real short-picked items, not a fixed constant', function () {
    // 3 lines fully picked, 1 short by half — 75% of lines correct.
    makeCompletedPickingList($this->warehouseA, $this->product, 10, 10);
    makeCompletedPickingList($this->warehouseA, $this->product, 10, 10);
    makeCompletedPickingList($this->warehouseA, $this->product, 10, 10);
    makeCompletedPickingList($this->warehouseA, $this->product, 10, 5);

    $response = $this->actingAs($this->admin)
        ->getJson('/api/v1/admin/wms/performance?'.http_build_query(['warehouse_id' => $this->warehouseA->id]))
        ->assertOk();

    expect((float) $response->json('picking_accuracy'))->toBe(75.0);
});

test('a period with no completed activity reports null, not a fabricated 0 or 100', function () {
    $response = $this->actingAs($this->admin)
        ->getJson('/api/v1/admin/wms/performance?'.http_build_query(['warehouse_id' => $this->warehouseA->id]))
        ->assertOk();

    expect($response->json('picking_accuracy'))->toBeNull();
    expect($response->json('cycle_count_accuracy'))->toBeNull();
});

test('omitting warehouse_id aggregates every warehouse instead of silently returning nothing', function () {
    makeCompletedPickingList($this->warehouseA, $this->product, 10, 10);
    makeCompletedPickingList($this->warehouseB, $this->product, 10, 10);

    $response = $this->actingAs($this->admin)
        ->getJson('/api/v1/admin/wms/performance')
        ->assertOk();

    expect($response->json('completed_picking_lists'))->toBe(2);
    expect((float) $response->json('picking_accuracy'))->toBe(100.0);
});

test('cycle count accuracy comes from real recorded variance', function () {
    $count = CycleCount::create([
        'warehouse_id' => $this->warehouseA->id,
        'count_number' => 'CC-PERF-1',
        'type' => 'full',
        'status' => CycleCount::STATUS_COMPLETED,
        'total_items' => 4,
        'variance_items' => 1,
    ]);

    $response = $this->actingAs($this->admin)
        ->getJson('/api/v1/admin/wms/performance?'.http_build_query(['warehouse_id' => $this->warehouseA->id]))
        ->assertOk();

    expect((float) $response->json('cycle_count_accuracy'))->toBe(75.0);
    expect($response->json('completed_cycle_counts'))->toBe(1);
});

test('the trend endpoint buckets real monthly history, not invented figures', function () {
    makeCompletedPickingList($this->warehouseA, $this->product, 10, 10);

    $lastMonthList = makeCompletedPickingList($this->warehouseA, $this->product, 10, 5);
    $lastMonthList->forceFill(['created_at' => now()->subMonthNoOverflow()])->save();

    $response = $this->actingAs($this->admin)
        ->getJson('/api/v1/admin/wms/performance/trends?'.http_build_query([
            'warehouse_id' => $this->warehouseA->id,
            'months' => 2,
        ]))
        ->assertOk();

    $labels = $response->json('labels');
    $accuracy = $response->json('picking_accuracy');

    expect($labels)->toHaveCount(2);
    expect($labels[1])->toBe(now()->format('Y-m'));
    expect((float) $accuracy[1])->toBe(100.0);
    // Its one line was short — a binary per-item metric, so that whole
    // bucket reads 0%, not a quantity-weighted partial credit.
    expect((float) $accuracy[0])->toBe(0.0);
});
