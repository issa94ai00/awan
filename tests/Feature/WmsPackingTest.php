<?php

use App\Models\Customer;
use App\Models\PackingList;
use App\Models\PickingList;
use App\Models\PickingListItem;
use App\Models\Product;
use App\Models\Role;
use App\Models\SalesOrder;
use App\Models\User;
use App\Models\Warehouse;

/**
 * Packing had two separate bugs before this: the frontend hit routes that
 * were never registered (404 on every call — fixed in routes/services), and
 * the list/detail responses returned raw Eloquent JSON with no can_start /
 * can_complete flags, the pattern every other WMS screen relies on. This
 * pins the controller side of the rebuild down.
 */
beforeEach(function () {
    $this->admin = User::factory()->create([
        'is_admin' => true,
        'role_id' => Role::firstOrCreate(['name' => 'admin'], ['display_name' => 'admin'])->id,
    ]);

    $this->warehouse = Warehouse::create([
        'name' => 'مستودع التعبئة', 'code' => 'WH-PACK', 'location' => 'سورية',
        'status' => 'active', 'is_active' => true, 'location_type' => Warehouse::TYPE_WAREHOUSE,
    ]);

    $this->product = Product::create([
        'name_ar' => 'خلاط مياه', 'sku' => 'SKU-PACK', 'price' => 80, 'cost_price' => 30,
    ]);

    $customer = Customer::create(['name' => 'عميل التعبئة', 'email' => 'pack@example.test', 'status' => 'active']);

    $order = SalesOrder::create([
        'order_number' => 'SO-PACK-001',
        'customer_id' => $customer->id,
        'status' => SalesOrder::STATUS_SHIPPED,
        'order_date' => now()->toDateString(),
        'subtotal' => 400,
        'discount' => 0,
        'tax' => 0,
        'total' => 400,
        'currency' => 'SYP',
        'fulfillment_warehouse_id' => $this->warehouse->id,
    ]);

    $this->pickingList = PickingList::create([
        'warehouse_id' => $this->warehouse->id,
        'sales_order_id' => $order->id,
        'list_number' => 'PL-TEST-1',
        'priority' => PickingList::PRIORITY_NORMAL,
        'status' => PickingList::STATUS_COMPLETED,
        'total_items' => 1,
        'picked_items' => 1,
    ]);

    $this->pickingList->items()->create([
        'product_id' => $this->product->id,
        'quantity_to_pick' => 5,
        'quantity_picked' => 5,
        'status' => PickingListItem::STATUS_PICKED,
        'sort_order' => 1,
    ]);
});

test('a packing list is created from a completed picking list with server-computed flags', function () {
    $response = $this->actingAs($this->admin)
        ->postJson('/api/v1/admin/wms/packing-lists', ['picking_list_id' => $this->pickingList->id])
        ->assertCreated()
        ->assertJsonPath('data.list.status', 'pending')
        ->assertJsonPath('data.list.can_start', true)
        ->assertJsonPath('data.list.can_complete', false)
        ->assertJsonPath('data.list.total_packages', 1)
        ->assertJsonPath('data.list.items.0.product_id', $this->product->id);

    expect(PackingList::count())->toBe(1);
});

test('the packing list index returns flattened rows behind pagination', function () {
    app(\App\Services\PackingService::class)->createPackingList($this->pickingList->fresh());

    $this->actingAs($this->admin)
        ->getJson('/api/v1/admin/wms/packing-lists')
        ->assertOk()
        ->assertJsonPath('data.lists.0.warehouse_name', 'مستودع التعبئة')
        ->assertJsonPath('data.pagination.total', 1);
});

test('a packing list walks pending -> in_progress -> completed with matching flags', function () {
    $packing = app(\App\Services\PackingService::class)->createPackingList($this->pickingList->fresh());

    $this->actingAs($this->admin)
        ->postJson("/api/v1/admin/wms/packing-lists/{$packing->id}/start")
        ->assertOk()
        ->assertJsonPath('data.list.status', 'in_progress')
        ->assertJsonPath('data.list.can_start', false)
        ->assertJsonPath('data.list.can_complete', true);

    $this->actingAs($this->admin)
        ->postJson("/api/v1/admin/wms/packing-lists/{$packing->id}/complete")
        ->assertOk()
        ->assertJsonPath('data.list.status', 'completed')
        ->assertJsonPath('data.list.can_complete', false)
        ->assertJsonPath('data.list.can_cancel', false);
});

test('package details update returns the refreshed list, not just the one item', function () {
    $packing = app(\App\Services\PackingService::class)->createPackingList($this->pickingList->fresh());
    $item = $packing->items->first();

    $this->actingAs($this->admin)
        ->putJson("/api/v1/admin/wms/packing-items/{$item->id}", [
            'weight' => 4.5,
            'fragile' => true,
        ])
        ->assertOk()
        ->assertJsonPath('data.list.items.0.weight', 4.5)
        ->assertJsonPath('data.list.items.0.fragile', true);
});

test('a completed packing list cannot be cancelled', function () {
    $packing = app(\App\Services\PackingService::class)->createPackingList($this->pickingList->fresh());
    $packing->start($this->admin->id);
    $packing->complete();

    $this->actingAs($this->admin)
        ->postJson("/api/v1/admin/wms/packing-lists/{$packing->id}/cancel")
        ->assertStatus(422);
});
