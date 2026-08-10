<?php

use App\Models\Employee;
use App\Models\Product;
use App\Models\SalesOrder;
use App\Models\User;
use App\Models\Warehouse;
use App\Models\WarehouseInventory;
use App\Services\Sales\SalesOrderWorkflowService;
use Illuminate\Support\Facades\DB;

/**
 * What the order diagnostics panel says, and whether it can be believed.
 *
 * The panel's value rests entirely on not crying wolf: an operator who is sent
 * to re-route an order twice for nothing stops reading it. These cover the two
 * ways it was wrong — one alarm that fired for the wrong reason, and one that
 * was about to start firing when nothing was actually amiss.
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

    $this->seller = User::factory()->create();

    Employee::create([
        'name' => 'بائع الفرع',
        'email' => 'branch.seller@example.com',
        'phone' => '+966500000001',
        'position' => 'مندوب مبيعات',
        'department' => 'المبيعات',
        'hire_date' => now()->subYear()->toDateString(),
        'status' => 'نشط',
        'user_id' => $this->seller->id,
        'warehouse_id' => $this->branch->id,
    ]);

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

    $this->codes = fn (SalesOrder $order) => collect(
        app(SalesOrderWorkflowService::class)->diagnose($order)
    )->pluck('code');
});

test('a line held entirely at the main warehouse is not reported as unreserved', function () {
    // Nothing on the branch shelf, so the whole line is topped up from the main
    // warehouse and held there.
    ($this->stock)($this->branch, 0);
    ($this->stock)($this->main, 50);

    $this->actingAs($this->seller, 'sanctum')
        ->postJson('/api/v1/field/orders', [
            'customer_name' => 'عميل',
            'customer_phone' => '+966500000009',
            'items' => [['product_id' => $this->product->id, 'quantity' => 5]],
            'supply_from_main' => true,
            'confirm' => true,
        ])
        ->assertStatus(201);

    $order = SalesOrder::latest('id')->first();

    // The order is served from the branch but the stock is held at the main
    // warehouse — which is exactly where it will be picked from.
    expect((int) $order->fulfillment_warehouse_id)->toBe($this->branch->id);
    expect((int) WarehouseInventory::where('warehouse_id', $this->main->id)
        ->where('product_id', $this->product->id)->value('reserved_quantity'))->toBe(5);

    // Looking only at the fulfilment warehouse would find nothing and raise an
    // alarm about a reservation sitting safely one warehouse over.
    expect(($this->codes)($order))->not->toContain('reservation_missing');
});

test('a genuinely unreserved line is still reported', function () {
    ($this->stock)($this->branch, 10);

    $order = SalesOrder::create([
        'order_number' => 'SO-TEST-1',
        'status' => SalesOrder::STATUS_CONFIRMED,
        'order_date' => now()->toDateString(),
        'subtotal' => 100,
        'total' => 100,
        'fulfillment_warehouse_id' => $this->branch->id,
    ]);

    $order->items()->create([
        'product_id' => $this->product->id,
        'quantity' => 2,
        'unit_price' => 100,
    ]);

    // Confirmed, but nothing was ever held for it.
    expect(($this->codes)($order->refresh()))->toContain('reservation_missing');
});

test('a line whose product was deleted names the real cause', function () {
    ($this->stock)($this->branch, 10);

    $order = SalesOrder::create([
        'order_number' => 'SO-TEST-2',
        'status' => SalesOrder::STATUS_CONFIRMED,
        'order_date' => now()->toDateString(),
        'subtotal' => 100,
        'total' => 100,
        'fulfillment_warehouse_id' => $this->branch->id,
    ]);

    $order->items()->create([
        'product_id' => $this->product->id,
        'description' => 'خلاط مغسلة',
        'quantity' => 2,
        'unit_price' => 100,
    ]);

    // The catalogue is deleted and re-imported with new ids — the line is left
    // pointing at nothing. Written straight to the table so the foreign key
    // does not simply null the column, which is what a real re-import leaves.
    DB::table('products')->where('id', $this->product->id)->delete();

    $codes = ($this->codes)($order->refresh()->load('items.product'));

    expect($codes)->toContain('product_missing');

    // And it must not also be reported as a plain missing reservation: that
    // advice sends the operator to re-route an order no warehouse can cover.
    expect($codes)->not->toContain('reservation_missing');
    expect($codes)->not->toContain('cost_price_missing');
});

test('the missing-product notice explains what to do instead', function () {
    $order = SalesOrder::create([
        'order_number' => 'SO-TEST-3',
        'status' => SalesOrder::STATUS_CONFIRMED,
        'order_date' => now()->toDateString(),
        'subtotal' => 100,
        'total' => 100,
        'fulfillment_warehouse_id' => $this->branch->id,
    ]);

    $order->items()->create([
        'product_id' => $this->product->id,
        'description' => 'خلاط مغسلة',
        'quantity' => 2,
        'unit_price' => 100,
    ]);

    DB::table('products')->where('id', $this->product->id)->delete();

    $issue = collect(app(SalesOrderWorkflowService::class)->diagnose($order->refresh()->load('items.product')))
        ->firstWhere('code', 'product_missing');

    expect($issue['level'])->toBe('error');
    // The line still names itself, from its own description — the one label
    // that survives the product being deleted.
    expect($issue['detail'])->toContain('خلاط مغسلة');
    expect($issue['action'])->toContain('لن تُجديا');
});
