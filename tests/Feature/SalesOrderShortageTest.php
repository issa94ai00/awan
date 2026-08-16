<?php

use App\Models\Product;
use App\Models\SalesOrder;
use App\Models\User;
use App\Models\Warehouse;
use App\Models\WarehouseInventory;
use Illuminate\Support\Facades\DB;

/**
 * Confirming an order the network cannot cover.
 *
 * The refusal used to be a sentence and nothing else — "الرصيد غير كافٍ في أي
 * مستودع: خلاط (ناقص 7 من أصل 10)" — so an operator wanting to buy the shortfall
 * had to read the product names out of the error text and retype them into a
 * purchase order. The same numbers now come back as data.
 */

function shortageWarehouse(string $code, array $attributes = []): Warehouse
{
    return Warehouse::create(array_merge([
        'name' => 'Warehouse '.$code,
        'code' => $code,
        'is_active' => true,
    ], $attributes));
}

function shortageStock(Product $product, Warehouse $warehouse, int $available, int $reserved = 0): void
{
    WarehouseInventory::create([
        'product_id' => $product->id,
        'warehouse_id' => $warehouse->id,
        'quantity' => $available,
        'available_quantity' => $available,
        'reserved_quantity' => $reserved,
    ]);
}

function shortageOrder(array $lines): SalesOrder
{
    $orderId = DB::table('sales_orders')->insertGetId([
        'order_number' => 'SO-'.uniqid(),
        'status' => SalesOrder::STATUS_PENDING,
        'order_date' => now()->toDateString(),
        'subtotal' => 0,
        'total' => 0,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    foreach ($lines as [$product, $quantity, $price]) {
        DB::table('sales_order_items')->insert([
            'sales_order_id' => $orderId,
            'product_id' => $product->id,
            'quantity' => $quantity,
            'unit_price' => $price,
            'total' => $quantity * $price,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    return SalesOrder::find($orderId);
}

it('returns the shortfall as data when confirmation is refused', function () {
    $user = User::factory()->create();
    $warehouse = shortageWarehouse('W1');

    $product = Product::factory()->create(['name_ar' => 'خلاط مغسلة', 'sku' => 'MIX-1', 'cost_price' => 90]);
    shortageStock($product, $warehouse, 3);

    $order = shortageOrder([[$product, 10, 150]]);

    $response = $this->actingAs($user)
        ->postJson("/api/v1/sales-orders/{$order->id}/confirm")
        ->assertStatus(422);

    $response->assertJsonPath('data.shortages.0.product_id', $product->id)
        ->assertJsonPath('data.shortages.0.required', 10)
        ->assertJsonPath('data.shortages.0.available', 3)
        ->assertJsonPath('data.shortages.0.shortfall', 7)
        ->assertJsonPath('data.shortages.0.suggested_quantity', 7)
        ->assertJsonPath('data.shortages.0.sku', 'MIX-1');
});

it('reads the shortfall on its own so the purchase screen can prefill', function () {
    $user = User::factory()->create();
    $warehouse = shortageWarehouse('W2');

    $product = Product::factory()->create(['name_ar' => 'حنفية', 'cost_price' => 40]);
    shortageStock($product, $warehouse, 0);

    $order = shortageOrder([[$product, 5, 80]]);

    $this->actingAs($user)
        ->getJson("/api/v1/sales-orders/{$order->id}/shortages")
        ->assertOk()
        ->assertJsonPath('data.sales_order.id', $order->id)
        ->assertJsonPath('data.shortages.0.shortfall', 5)
        // Never bought before, so the catalogue cost stands in. Compared loosely
        // because JSON returns a whole number as an int.
        ->assertJsonPath('data.shortages.0.unit_price', fn ($value) => (float) $value === 40.0);
});

it('sums availability across every warehouse before calling anything short', function () {
    $user = User::factory()->create();
    $a = shortageWarehouse('A');
    $b = shortageWarehouse('B');

    $product = Product::factory()->create(['cost_price' => 10]);
    shortageStock($product, $a, 4);
    shortageStock($product, $b, 6);

    // 4 + 6 covers 10 exactly: the network can fill it, so nothing is short.
    $order = shortageOrder([[$product, 10, 20]]);

    $this->actingAs($user)
        ->getJson("/api/v1/sales-orders/{$order->id}/shortages")
        ->assertOk()
        ->assertJsonPath('data.shortages', []);
});

it('does not promise the same units to two lines of the same product', function () {
    $user = User::factory()->create();
    $warehouse = shortageWarehouse('W3');

    $product = Product::factory()->create(['cost_price' => 25]);
    shortageStock($product, $warehouse, 6);

    // Two lines of 5 against 6 in stock: the first takes 5, the second finds 1.
    $order = shortageOrder([[$product, 5, 30], [$product, 5, 30]]);

    $response = $this->actingAs($user)
        ->getJson("/api/v1/sales-orders/{$order->id}/shortages")
        ->assertOk();

    // Only the second line is short, by 4 — not both lines reporting plenty.
    expect($response->json('data.shortages'))->toHaveCount(1);
    $response->assertJsonPath('data.shortages.0.available', 1)
        ->assertJsonPath('data.shortages.0.shortfall', 4);
});

it('suggests the price last actually paid to a supplier over the catalogue cost', function () {
    $user = User::factory()->create();
    $warehouse = shortageWarehouse('W4');

    $product = Product::factory()->create(['cost_price' => 100]);
    shortageStock($product, $warehouse, 0);

    $supplierId = DB::table('suppliers')->insertGetId([
        'name' => 'Supplier', 'created_at' => now(), 'updated_at' => now(),
    ]);
    $poId = DB::table('purchase_orders')->insertGetId([
        'order_number' => 'PO-000001', 'supplier_id' => $supplierId, 'status' => 'received',
        'subtotal' => 0, 'total' => 0, 'created_at' => now(), 'updated_at' => now(),
    ]);
    DB::table('purchase_order_items')->insert([
        'purchase_order_id' => $poId, 'product_id' => $product->id, 'product_name' => 'x',
        'quantity' => 1, 'unit_price' => 72.5, 'total_price' => 72.5,
        'created_at' => now(), 'updated_at' => now(),
    ]);

    $order = shortageOrder([[$product, 2, 150]]);

    $this->actingAs($user)
        ->getJson("/api/v1/sales-orders/{$order->id}/shortages")
        ->assertOk()
        ->assertJsonPath('data.shortages.0.unit_price', 72.5);
});

it('reports nothing short when stock covers the order', function () {
    $user = User::factory()->create();
    $warehouse = shortageWarehouse('W5');

    $product = Product::factory()->create(['cost_price' => 10]);
    shortageStock($product, $warehouse, 50);

    $order = shortageOrder([[$product, 5, 20]]);

    $this->actingAs($user)
        ->getJson("/api/v1/sales-orders/{$order->id}/shortages")
        ->assertOk()
        ->assertJsonPath('data.shortages', []);
});
