<?php

use App\Models\Customer;
use App\Models\JournalEntryHeader;
use App\Models\PickingList;
use App\Models\Product;
use App\Models\SalesOrder;
use App\Models\User;
use App\Models\Warehouse;
use App\Models\WarehouseInventory;
use App\Services\Sales\SalesOrderWorkflowService;

/**
 * An order routed across more than one warehouse, followed all the way.
 *
 * A single line can be sourced from several warehouses at once. Every stage
 * after that has to respect the split: the hold, the picking instruction, the
 * goods leaving, the cost credited to each holding, and — if the sale falls
 * through — the goods going back where they actually came from.
 */
beforeEach(function () {
    $this->branch = Warehouse::create([
        'name' => 'فرع جدة',
        'code' => 'WH-JED',
        'location' => 'جدة',
        'status' => 'active',
        'is_active' => true,
        'location_type' => Warehouse::TYPE_BRANCH,
    ]);

    $this->main = Warehouse::create([
        'name' => 'المستودع الرئيسي',
        'code' => 'WH-MAIN',
        'location' => 'الرياض',
        'status' => 'active',
        'is_active' => true,
        'is_primary' => true,
        'location_type' => Warehouse::TYPE_WAREHOUSE,
    ]);

    $this->user = User::factory()->create();
    $this->workflow = app(SalesOrderWorkflowService::class);

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

    // One line of 8, routed 3 from the branch and 5 from the main warehouse.
    $this->splitOrder = function () {
        $customer = Customer::create([
            'name' => 'عميل',
            'email' => 'c@example.com',
            'phone' => '+966500000001',
            'status' => 'نشط',
        ]);

        $order = SalesOrder::create([
            'order_number' => 'SO-SPLIT-1',
            'customer_id' => $customer->id,
            'status' => SalesOrder::STATUS_PENDING,
            'order_date' => now()->toDateString(),
            'subtotal' => 800,
            'total' => 800,
            'currency' => 'SAR',
            'fulfillment_warehouse_id' => $this->branch->id,
            'created_by' => $this->user->id,
        ]);

        $item = $order->items()->create([
            'product_id' => $this->product->id,
            'quantity' => 8,
            'unit_price' => 100,
        ]);

        $item->allocations()->create(['warehouse_id' => $this->branch->id, 'quantity' => 3]);
        $item->allocations()->create(['warehouse_id' => $this->main->id, 'quantity' => 5]);

        return $order->refresh()->load('items.allocations');
    };
});

test('confirmation holds each share where it will actually be picked', function () {
    ($this->stock)($this->branch, 10);
    ($this->stock)($this->main, 10);

    $this->actingAs($this->user);
    $this->workflow->transitionTo(($this->splitOrder)(), SalesOrder::STATUS_CONFIRMED);

    expect((int) ($this->at)($this->branch)->reserved_quantity)->toBe(3);
    expect((int) ($this->at)($this->main)->reserved_quantity)->toBe(5);
});

test('every warehouse in the routing is told to fetch its share', function () {
    ($this->stock)($this->branch, 10);
    ($this->stock)($this->main, 10);

    $this->actingAs($this->user);
    $order = ($this->splitOrder)();
    $this->workflow->transitionTo($order, SalesOrder::STATUS_CONFIRMED);

    $lists = PickingList::where('sales_order_id', $order->id)->get();

    // A list only at the fulfilment warehouse leaves the other half of the
    // order with nobody instructed to pick it.
    expect($lists->pluck('warehouse_id')->sort()->values()->all())
        ->toBe([$this->branch->id, $this->main->id]);

    expect((int) $lists->firstWhere('warehouse_id', $this->branch->id)->items->sum('quantity_to_pick'))->toBe(3);
    expect((int) $lists->firstWhere('warehouse_id', $this->main->id)->items->sum('quantity_to_pick'))->toBe(5);
});

test('shipment takes each share out of its own warehouse and costs it there', function () {
    ($this->stock)($this->branch, 10);
    ($this->stock)($this->main, 10);

    $this->actingAs($this->user);
    $order = ($this->splitOrder)();
    $this->workflow->transitionTo($order, SalesOrder::STATUS_CONFIRMED);
    $this->workflow->transitionTo($order->refresh(), SalesOrder::STATUS_PROCESSING);
    $this->workflow->transitionTo($order->refresh(), SalesOrder::STATUS_SHIPPED);

    expect((int) ($this->at)($this->branch)->quantity)->toBe(7);
    expect((int) ($this->at)($this->main)->quantity)->toBe(5);

    // Cost of 8 units at 60 = 480, credited to each warehouse's own inventory
    // account in proportion to what left it.
    $entry = JournalEntryHeader::where('posting_key', 'so_cogs:' . $order->id)->first();
    expect($entry)->not->toBeNull();
    expect(round((float) $entry->lines->sum('debit'), 2))->toBe(480.0);
    expect(round((float) $entry->lines->sum('credit'), 2))->toBe(480.0);
    // Two credits — one per source warehouse — not one lump.
    expect($entry->lines->where('credit', '>', 0)->count())->toBe(2);
});

test('cancelling a shipped order returns each share to the warehouse it left', function () {
    ($this->stock)($this->branch, 10);
    ($this->stock)($this->main, 10);

    $this->actingAs($this->user);
    $order = ($this->splitOrder)();
    $this->workflow->transitionTo($order, SalesOrder::STATUS_CONFIRMED);
    $this->workflow->transitionTo($order->refresh(), SalesOrder::STATUS_PROCESSING);
    $this->workflow->transitionTo($order->refresh(), SalesOrder::STATUS_SHIPPED);

    $this->workflow->transitionTo($order->refresh(), SalesOrder::STATUS_CANCELLED, [
        'note' => 'العميل ألغى الطلب',
    ]);

    // Returning everything to the order's own warehouse would put five units
    // the main warehouse shipped onto the branch's shelf — inventing stock in
    // one building and losing it in another, while the ledger reversal credits
    // both correctly and the two records stop agreeing.
    expect((int) ($this->at)($this->branch)->quantity)->toBe(10);
    expect((int) ($this->at)($this->main)->quantity)->toBe(10);
});

test('cancelling before shipment releases each hold where it was taken', function () {
    ($this->stock)($this->branch, 10);
    ($this->stock)($this->main, 10);

    $this->actingAs($this->user);
    $order = ($this->splitOrder)();
    $this->workflow->transitionTo($order, SalesOrder::STATUS_CONFIRMED);

    $this->workflow->transitionTo($order->refresh(), SalesOrder::STATUS_CANCELLED, [
        'note' => 'العميل عدل عن الشراء',
    ]);

    expect((int) ($this->at)($this->branch)->reserved_quantity)->toBe(0);
    expect((int) ($this->at)($this->main)->reserved_quantity)->toBe(0);
    expect((int) ($this->at)($this->branch)->quantity)->toBe(10);
    expect((int) ($this->at)($this->main)->quantity)->toBe(10);
});

test('a routed warehouse left without a picking list is reported by name', function () {
    ($this->stock)($this->branch, 10);
    ($this->stock)($this->main, 10);

    $this->actingAs($this->user);
    $order = ($this->splitOrder)();
    $this->workflow->transitionTo($order, SalesOrder::STATUS_CONFIRMED);

    // The main warehouse's instruction goes missing — a partially repaired
    // order, or one confirmed before the split was understood.
    PickingList::where('sales_order_id', $order->id)
        ->where('warehouse_id', $this->main->id)
        ->update(['status' => PickingList::STATUS_CANCELLED]);

    $issue = collect($this->workflow->diagnose($order->refresh()))
        ->firstWhere('code', 'picking_list_missing');

    // Checking once for the order would have found the branch's list and
    // called the whole thing served.
    expect($issue)->not->toBeNull();
    expect($issue['detail'])->toContain('المستودع الرئيسي');
    expect($issue['detail'])->not->toContain('فرع جدة');
});

test('a routing the source cannot cover is refused before anything moves', function () {
    ($this->stock)($this->branch, 10);
    ($this->stock)($this->main, 2); // routed for 5

    $this->actingAs($this->user);

    expect(fn () => $this->workflow->transitionTo(($this->splitOrder)(), SalesOrder::STATUS_CONFIRMED))
        ->toThrow(RuntimeException::class);

    // Nothing half-applied: no hold anywhere, order untouched.
    expect((int) ($this->at)($this->branch)->reserved_quantity)->toBe(0);
    expect((int) ($this->at)($this->main)->reserved_quantity)->toBe(0);
    expect(SalesOrder::first()->status)->toBe(SalesOrder::STATUS_PENDING);
});
