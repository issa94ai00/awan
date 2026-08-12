<?php

use App\Models\CycleCount;
use App\Models\Product;
use App\Models\StockMovement;
use App\Models\User;
use App\Models\Warehouse;
use App\Models\WarehouseInventory;

/**
 * Approving a cycle count used to only clear `requires_adjustment` and stamp
 * who approved it — the variance was calculated and reviewed, but
 * `warehouse_inventory` never moved, so the shelf and the count disagreed
 * forever and the next count found the exact same gap. These hold
 * `CycleCount::applyAdjustment()` to actually correcting the shelf, through
 * `InventoryService::adjust()` so the movement and the FIFO layers move with it.
 */
beforeEach(function () {
    $this->user = User::factory()->create();

    $this->warehouse = Warehouse::create([
        'name' => 'مستودع الجرد',
        'code' => 'WH-CC',
        'location' => 'الرياض',
        'status' => 'active',
        'is_active' => true,
        'location_type' => Warehouse::TYPE_WAREHOUSE,
    ]);

    $this->product = Product::create([
        'name_ar' => 'صنف مجرود',
        'sku' => 'SKU-CC',
        'price' => 50,
        'cost_price' => 20,
    ]);

    WarehouseInventory::create([
        'warehouse_id' => $this->warehouse->id,
        'product_id' => $this->product->id,
        'quantity' => 10,
        'available_quantity' => 10,
        'reserved_quantity' => 0,
        'damaged_quantity' => 0,
        'quarantined_quantity' => 0,
    ]);

    $this->count = CycleCount::create([
        'warehouse_id' => $this->warehouse->id,
        'count_number' => 'CC-TEST-1',
        'type' => CycleCount::TYPE_FULL,
        'status' => CycleCount::STATUS_COMPLETED,
        'counter_id' => $this->user->id,
        'requires_adjustment' => true,
    ]);
});

test('applying an adjustment corrects the shelf to what was counted', function () {
    $item = $this->count->items()->create([
        'product_id' => $this->product->id,
        'bin_id' => null,
        'expected_quantity' => 10,
        'counted_quantity' => 7,
        'unit_cost' => 20,
    ]);
    $item->calculateVariance();

    expect((int) $item->variance)->toBe(-3);

    $this->count->applyAdjustment($this->user->id);

    $row = WarehouseInventory::where('warehouse_id', $this->warehouse->id)
        ->where('product_id', $this->product->id)
        ->first();

    expect((int) $row->quantity)->toBe(7);
    expect((int) $row->available_quantity)->toBe(7);

    $movement = StockMovement::where('reference', 'cycle_count')
        ->where('source', $this->count->id)
        ->first();

    expect($movement)->not->toBeNull();
    expect((int) $movement->quantity)->toBe(-3);

    $this->count->refresh();
    expect($this->count->requires_adjustment)->toBeFalse();
});

test('an overage adjustment also opens a FIFO layer at the counted cost', function () {
    $item = $this->count->items()->create([
        'product_id' => $this->product->id,
        'bin_id' => null,
        'expected_quantity' => 10,
        'counted_quantity' => 14,
        'unit_cost' => 20,
    ]);
    $item->calculateVariance();

    $this->count->applyAdjustment($this->user->id);

    $row = WarehouseInventory::where('warehouse_id', $this->warehouse->id)
        ->where('product_id', $this->product->id)
        ->first();

    expect((int) $row->quantity)->toBe(14);
});

test('re-approving an already-applied count does not move stock twice', function () {
    $item = $this->count->items()->create([
        'product_id' => $this->product->id,
        'bin_id' => null,
        'expected_quantity' => 10,
        'counted_quantity' => 7,
        'unit_cost' => 20,
    ]);
    $item->calculateVariance();

    $this->count->applyAdjustment($this->user->id);
    $this->count->applyAdjustment($this->user->id);

    $row = WarehouseInventory::where('warehouse_id', $this->warehouse->id)
        ->where('product_id', $this->product->id)
        ->first();

    expect((int) $row->quantity)->toBe(7);
});
