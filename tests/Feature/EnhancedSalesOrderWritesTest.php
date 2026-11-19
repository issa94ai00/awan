<?php

use App\Models\Customer;
use App\Models\OrderChannel;
use App\Models\Product;
use App\Models\SalesContract;
use App\Models\SalesOrder;
use App\Models\User;
use App\Models\Warehouse;
use App\Models\WarehouseInventory;
use Illuminate\Support\Facades\DB;

function eswOrder(Product $product, int $qty, Warehouse $wh, string $status = 'pending', ?int $channelId = null): int
{
    $id = DB::table('sales_orders')->insertGetId([
        'order_number' => 'SO-E-'.uniqid(),
        'status' => $status,
        'order_date' => now()->toDateString(),
        'subtotal' => $qty * 100, 'total' => $qty * 100,
        'fulfillment_type' => 'ship',
        'fulfillment_warehouse_id' => $wh->id,
        'channel_id' => $channelId,
        'created_at' => now(), 'updated_at' => now(),
    ]);
    DB::table('sales_order_items')->insert([
        'sales_order_id' => $id, 'product_id' => $product->id, 'quantity' => $qty,
        'unit_price' => 100, 'total' => $qty * 100, 'created_at' => now(), 'updated_at' => now(),
    ]);

    return $id;
}

beforeEach(function () {
    $this->admin = User::factory()->admin()->create();
    $this->product = Product::factory()->create(['price' => 100, 'cost_price' => 60]);
    $this->wh = Warehouse::create(['name' => 'WH E', 'code' => 'W-E', 'is_active' => true, 'status' => 'active', 'location_type' => Warehouse::TYPE_WAREHOUSE]);
    WarehouseInventory::create([
        'product_id' => $this->product->id, 'warehouse_id' => $this->wh->id,
        'quantity' => 20, 'available_quantity' => 20, 'reserved_quantity' => 0,
        'damaged_quantity' => 0, 'quarantined_quantity' => 0,
    ]);
    $this->customer = Customer::create(['name' => 'عميل', 'phone' => '0922333444', 'status' => 'active']);
});

it('creates a draft contract, validates dates, then approves it once', function () {
    $this->actingAs($this->admin)->postJson('/api/v1/sales-orders/contracts', [
        'customer_id' => $this->customer->id,
        'start_date' => '2026-02-01', 'end_date' => '2026-01-01',
        'total_value' => 1000, 'discount_percentage' => 5,
    ])->assertStatus(422);

    $this->actingAs($this->admin)->postJson('/api/v1/sales-orders/contracts', [
        'customer_id' => $this->customer->id,
        'start_date' => '2026-01-01', 'end_date' => '2026-12-31',
        'total_value' => 1000, 'discount_percentage' => 5,
    ])->assertCreated();

    $contract = SalesContract::latest('id')->first();
    expect($contract->status)->toBe('draft')->and($contract->created_by)->toBe($this->admin->id);
    $id = $contract->id;

    $this->actingAs($this->admin)->postJson("/api/v1/sales-orders/contracts/$id/approve")->assertOk();
    $contract->refresh();
    expect($contract->status)->toBe('active')->and($contract->approved_by)->toBe($this->admin->id);

    $this->actingAs($this->admin)->postJson("/api/v1/sales-orders/contracts/$id/approve")->assertStatus(422);
});

it('creates an enhanced order attributed to the signed-in employee', function () {
    $res = $this->actingAs($this->admin)->postJson('/api/v1/sales-orders/enhanced', [
        'customer_id' => $this->customer->id, 'fulfillment_type' => 'ship',
        'shipping_cost' => 0, 'tax_amount' => 0, 'discount_amount' => 0,
        'items' => [['product_id' => $this->product->id, 'quantity' => 2, 'unit_price' => 100]],
    ]);

    $res->assertSuccessful();
    expect(SalesOrder::count())->toBe(1)->and(SalesOrder::first()->items()->count())->toBe(1);
});

it('rejects an enhanced order with no items', function () {
    $this->actingAs($this->admin)->postJson('/api/v1/sales-orders/enhanced', [
        'customer_id' => $this->customer->id, 'fulfillment_type' => 'ship',
        'shipping_cost' => 0, 'tax_amount' => 0, 'discount_amount' => 0, 'items' => [],
    ])->assertStatus(422);
    expect(SalesOrder::count())->toBe(0);
});

it('allocates inventory by confirming and moving the order to processing', function () {
    $id = eswOrder($this->product, 4, $this->wh);

    $this->actingAs($this->admin)->postJson("/api/v1/sales-orders/enhanced/$id/allocate")
        ->assertOk()->assertJsonPath('data.status', 'processing');

    expect(SalesOrder::find($id)->status)->toBe('processing');
});

it('refuses to allocate an order that is already past processing', function () {
    $id = eswOrder($this->product, 1, $this->wh, 'delivered');

    $this->actingAs($this->admin)->postJson("/api/v1/sales-orders/enhanced/$id/allocate")
        ->assertStatus(422)->assertJsonPath('success', false);
    expect(SalesOrder::find($id)->status)->toBe('delivered');
});

it('checks fulfillment without changing the order', function () {
    $id = eswOrder($this->product, 4, $this->wh);

    $this->actingAs($this->admin)->postJson("/api/v1/sales-orders/enhanced/$id/check-fulfillment")
        ->assertOk()->assertJsonStructure(['data' => ['can_fulfill', 'summary', 'routing', 'sourcing_plan']]);

    expect(SalesOrder::find($id)->status)->toBe('pending');
});

it('records tracking only for a processing or shipped order', function () {
    $id = eswOrder($this->product, 2, $this->wh);
    $payload = ['tracking_number' => 'TRK-1', 'carrier' => 'DHL'];

    $this->actingAs($this->admin)->postJson("/api/v1/sales-orders/enhanced/$id/tracking", ['carrier' => 'DHL'])->assertStatus(422);
    $this->actingAs($this->admin)->postJson("/api/v1/sales-orders/enhanced/$id/tracking", $payload)->assertStatus(422);
    expect(SalesOrder::find($id)->tracking_number)->toBeNull();

    $this->actingAs($this->admin)->postJson("/api/v1/sales-orders/enhanced/$id/allocate")->assertOk();
    $this->actingAs($this->admin)->postJson("/api/v1/sales-orders/enhanced/$id/tracking", $payload)->assertOk();

    $order = SalesOrder::find($id);
    expect($order->status)->toBe('shipped')
        ->and($order->tracking_number)->toBe('TRK-1')
        ->and($order->carrier)->toBe('DHL');

    // A shipped order keeps accepting corrected tracking details.
    $this->actingAs($this->admin)->postJson("/api/v1/sales-orders/enhanced/$id/tracking", ['tracking_number' => 'TRK-2', 'carrier' => 'UPS'])->assertOk();
    expect(SalesOrder::find($id)->tracking_number)->toBe('TRK-2');
});

it('delivers a shipped order and refuses delivery of a pending one', function () {
    $pending = eswOrder($this->product, 2, $this->wh);
    $this->actingAs($this->admin)->postJson("/api/v1/sales-orders/enhanced/$pending/deliver")->assertStatus(422);
    expect(SalesOrder::find($pending)->status)->toBe('pending');

    $id = eswOrder($this->product, 2, $this->wh);
    $this->actingAs($this->admin)->postJson("/api/v1/sales-orders/enhanced/$id/allocate")->assertOk();
    $this->actingAs($this->admin)->postJson("/api/v1/sales-orders/enhanced/$id/tracking", ['tracking_number' => 'T', 'carrier' => 'C'])->assertOk();
    $this->actingAs($this->admin)->postJson("/api/v1/sales-orders/enhanced/$id/deliver")->assertOk();

    expect(SalesOrder::find($id)->status)->toBe('delivered');
});

it('syncs only multi-channel orders', function () {
    $plain = eswOrder($this->product, 1, $this->wh);
    $this->actingAs($this->admin)->postJson("/api/v1/sales-orders/enhanced/$plain/sync")->assertStatus(422);

    $channel = OrderChannel::create(['name' => 'Shop', 'code' => 'SHOP', 'type' => 'online', 'is_active' => true]);
    $id = eswOrder($this->product, 1, $this->wh, 'pending', $channel->id);

    $this->actingAs($this->admin)->postJson("/api/v1/sales-orders/enhanced/$id/sync")->assertOk();

    $order = SalesOrder::find($id);
    expect($order->sync_status)->toBe('synced')->and($order->synced_at)->not->toBeNull();
});
