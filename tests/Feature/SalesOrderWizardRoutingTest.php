<?php

use App\Models\Customer;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\SalesOrder;
use App\Models\User;
use App\Models\Warehouse;
use App\Services\Inventory\InventoryService;

/**
 * The new-order wizard plans where each line comes from before saving, and
 * can save and confirm in one step.
 */
beforeEach(function () {
    $this->user = User::factory()->create();

    $this->main = Warehouse::create(['name' => 'الرئيسي', 'code' => 'WH-WZ-1', 'is_active' => true, 'is_primary' => true]);
    $this->branch = Warehouse::create(['name' => 'الفرع', 'code' => 'WH-WZ-2', 'is_active' => true]);

    $this->drain = Product::create(['name_ar' => 'جريدة تصريف', 'sku' => 'FD', 'price' => 5, 'cost_price' => 1]);
    $this->four = ProductVariant::create(['product_id' => $this->drain->id, 'sku' => 'FD-4', 'size' => '4"', 'price' => 1, 'stock_quantity' => 50]);
    $this->five = ProductVariant::create(['product_id' => $this->drain->id, 'sku' => 'FD-5', 'size' => '5"', 'price' => 2, 'stock_quantity' => 50]);
    $this->tap = Product::create(['name_ar' => 'حنفية', 'sku' => 'TAP', 'price' => 20, 'cost_price' => 8]);

    $inventory = app(InventoryService::class);
    $inventory->receive($this->drain->id, 6, $this->main->id, ['key' => 'wz-1', 'unit_cost' => 1]);
    $inventory->receive($this->drain->id, 10, $this->branch->id, ['key' => 'wz-2', 'unit_cost' => 1]);
    $inventory->receive($this->tap->id, 5, $this->main->id, ['key' => 'wz-3', 'unit_cost' => 8]);

    $this->customer = Customer::create(['name' => 'زبون', 'status' => 'active']);

    $this->line = fn ($product, int $quantity, $variant = null, array $allocations = []) => array_filter([
        'product_id' => $product->id,
        'product_variant_id' => $variant?->id,
        'quantity' => $quantity,
        'unit_price' => 1,
        'allocations' => $allocations ?: null,
    ], fn ($v) => $v !== null);
});

test('the suggestion shares one product\'s stock between its sizes and prefers one warehouse for all', function () {
    // 4 + 4 of the drain: the main warehouse has 6, the branch 10 — only the
    // branch can fill both sizes, so the whole order goes there.
    $plan = $this->actingAs($this->user, 'sanctum')->postJson('/api/v1/sales-orders/suggest-routing', [
        'items' => [
            ['product_id' => $this->drain->id, 'quantity' => 4],
            ['product_id' => $this->drain->id, 'quantity' => 4],
        ],
    ])->assertOk()->json('data');

    expect($plan['single_source'])->toBeTrue()
        ->and($plan['preferred_warehouse_id'])->toBe($this->branch->id)
        ->and($plan['lines'][0]['allocations'])->toBe([['warehouse_id' => $this->branch->id, 'quantity' => 4]])
        ->and($plan['lines'][1]['allocations'])->toBe([['warehouse_id' => $this->branch->id, 'quantity' => 4]]);
});

test('with no single warehouse it splits without promising the same units twice', function () {
    // 8 + 8 of the drain against 6 + 10: 16 exactly, never 16 from one place.
    $plan = $this->actingAs($this->user, 'sanctum')->postJson('/api/v1/sales-orders/suggest-routing', [
        'items' => [
            ['product_id' => $this->drain->id, 'quantity' => 8],
            ['product_id' => $this->drain->id, 'quantity' => 8],
            ['product_id' => $this->tap->id, 'quantity' => 7],
        ],
    ])->assertOk()->json('data');

    expect($plan['single_source'])->toBeFalse();
    $drainTotal = collect([$plan['lines'][0], $plan['lines'][1]])->flatMap(fn ($l) => $l['allocations'])->sum('quantity');
    expect($drainTotal)->toBe(16)
        ->and($plan['lines'][0]['shortfall'] + $plan['lines'][1]['shortfall'])->toBe(0)
        // Only 5 taps anywhere: the line says what is missing.
        ->and($plan['lines'][2]['shortfall'])->toBe(2);
});

test('an order saved with a plan keeps its routing and each line\'s split', function () {
    $id = $this->actingAs($this->user, 'sanctum')->postJson('/api/v1/sales-orders', [
        'customer_id' => $this->customer->id,
        'items' => [
            ($this->line)($this->drain, 8, $this->four, [
                ['warehouse_id' => $this->main->id, 'quantity' => 3],
                ['warehouse_id' => $this->branch->id, 'quantity' => 5],
            ]),
            ($this->line)($this->tap, 2, null, [['warehouse_id' => $this->main->id, 'quantity' => 2]]),
        ],
    ])->assertCreated()->json('data.id');

    $order = SalesOrder::with('items.allocations', 'routings')->find($id);

    expect($order->status)->toBe(SalesOrder::STATUS_PENDING)
        // Most of the order (5 from main vs 5 from branch — main is first) is
        // owned by a warehouse it draws on.
        ->and(in_array($order->fulfillment_warehouse_id, [$this->main->id, $this->branch->id], true))->toBeTrue()
        ->and($order->routings->pluck('id')->sort()->values()->all())->toBe([$this->main->id, $this->branch->id]);

    $drainLine = $order->items->firstWhere('product_variant_id', $this->four->id);
    expect($drainLine->allocations->pluck('quantity', 'warehouse_id')->all())
        ->toBe([$this->main->id => 3, $this->branch->id => 5]);
});

test('a plan that does not add up to the line is refused and nothing is saved', function () {
    $this->actingAs($this->user, 'sanctum')->postJson('/api/v1/sales-orders', [
        'customer_id' => $this->customer->id,
        'items' => [
            ($this->line)($this->drain, 8, null, [['warehouse_id' => $this->main->id, 'quantity' => 3]]),
        ],
    ])->assertStatus(422);

    expect(SalesOrder::count())->toBe(0);
});

test('save and confirm reserves the stock in one step', function () {
    $response = $this->actingAs($this->user, 'sanctum')->postJson('/api/v1/sales-orders', [
        'customer_id' => $this->customer->id,
        'execute' => 'confirm',
        'items' => [
            ($this->line)($this->drain, 4, $this->five, [['warehouse_id' => $this->branch->id, 'quantity' => 4]]),
        ],
    ])->assertCreated();

    expect($response->json('execution.confirmed'))->toBeTrue();
    $order = SalesOrder::find($response->json('data.id'));
    expect($order->status)->toBe(SalesOrder::STATUS_CONFIRMED);

    // Held where the plan said.
    expect(app(InventoryService::class)->sellableQuantity($this->drain->id, $this->branch->id))->toBe(6);
});

test('save and confirm with short stock keeps the draft and says what is missing', function () {
    $response = $this->actingAs($this->user, 'sanctum')->postJson('/api/v1/sales-orders', [
        'customer_id' => $this->customer->id,
        'execute' => 'confirm',
        'items' => [($this->line)($this->tap, 9)],
    ])->assertCreated();

    expect($response->json('execution.confirmed'))->toBeFalse()
        ->and($response->json('execution.message'))->not->toBeEmpty()
        ->and(SalesOrder::find($response->json('data.id'))->status)->toBe(SalesOrder::STATUS_PENDING);
});

test('editing an order with a new plan replaces the old one', function () {
    $id = $this->actingAs($this->user, 'sanctum')->postJson('/api/v1/sales-orders', [
        'customer_id' => $this->customer->id,
        'items' => [($this->line)($this->drain, 4, null, [['warehouse_id' => $this->main->id, 'quantity' => 4]])],
    ])->assertCreated()->json('data.id');

    $this->putJson('/api/v1/sales-orders/'.$id, [
        'customer_id' => $this->customer->id,
        'items' => [($this->line)($this->drain, 5, null, [['warehouse_id' => $this->branch->id, 'quantity' => 5]])],
    ])->assertOk();

    $order = SalesOrder::with('items.allocations', 'routings')->find($id);
    expect($order->routings->pluck('id')->all())->toBe([$this->branch->id])
        ->and($order->fulfillment_warehouse_id)->toBe($this->branch->id)
        ->and($order->items->first()->allocations->pluck('quantity', 'warehouse_id')->all())->toBe([$this->branch->id => 5]);
});
