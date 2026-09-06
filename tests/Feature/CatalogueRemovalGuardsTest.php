<?php

use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\StockMovement;
use App\Models\User;
use App\Models\Warehouse;
use App\Models\WarehouseInventory;
use Illuminate\Support\Facades\DB;

/**
 * What the catalogue refuses to delete, and why.
 *
 * Neither products nor variants are soft-deleted here, so removing one is
 * permanent. Two foreign keys make that worse than it looks, both ON DELETE
 * CASCADE: `warehouse_inventory` follows the record off the edge, and — for a
 * product — so does every row in `stock_movements`. That second one is the
 * audit trail behind inventory valuation and the accounting health checks, and
 * nothing warned that deleting a catalogue row erased it.
 *
 * So the rule is: goods you are still holding, and history you have already
 * written, are not collateral. A product with either is retired by
 * deactivating it, which takes it out of the catalogue and leaves the record
 * standing.
 */
function removalWarehouse(): Warehouse
{
    return Warehouse::create([
        'name' => 'مستودع '.uniqid(),
        'code' => strtoupper(substr(uniqid(), -6)),
        'is_active' => true,
    ]);
}

beforeEach(function () {
    $this->actor = User::factory()->admin()->create();
});

it('deletes a product that has neither stock nor history', function () {
    $product = Product::factory()->create();

    $this->actingAs($this->actor)
        ->deleteJson('/api/v1/admin/products/'.$product->id)
        ->assertOk();

    expect(Product::find($product->id))->toBeNull();
});

it('refuses a product still holding stock', function () {
    $product = Product::factory()->create();

    WarehouseInventory::create([
        'product_id' => $product->id,
        'warehouse_id' => removalWarehouse()->id,
        'quantity' => 4,
        'available_quantity' => 4,
        'reserved_quantity' => 0,
    ]);

    $this->actingAs($this->actor)
        ->deleteJson('/api/v1/admin/products/'.$product->id)
        ->assertStatus(422)
        ->assertJsonPath('data.reason', 'has_stock');

    expect(Product::find($product->id))->not->toBeNull();
});

it('refuses a product whose movement history would cascade away with it', function () {
    $product = Product::factory()->create();

    StockMovement::create([
        'product_id' => $product->id,
        'warehouse_id' => removalWarehouse()->id,
        'movement_type' => 'in',
        'quantity' => 3,
    ]);

    $movementsBefore = DB::table('stock_movements')->where('product_id', $product->id)->count();

    $this->actingAs($this->actor)
        ->deleteJson('/api/v1/admin/products/'.$product->id)
        ->assertStatus(422)
        ->assertJsonPath('data.reason', 'has_history');

    // The point of the refusal: the trail is still there afterwards.
    expect(DB::table('stock_movements')->where('product_id', $product->id)->count())
        ->toBe($movementsBefore)
        ->and(Product::find($product->id))->not->toBeNull();
});

it('deletes a variant that is not holding anything', function () {
    $product = Product::factory()->create();
    $variant = ProductVariant::create([
        'product_id' => $product->id,
        'sku' => 'VAR-'.uniqid(),
        'price' => 10,
        'stock_quantity' => 0,
    ]);

    $this->actingAs($this->actor)
        ->deleteJson('/api/v1/admin/product-variants/'.$variant->id)
        ->assertOk();

    expect(ProductVariant::find($variant->id))->toBeNull();
});

it('refuses a variant still on a shelf, rather than dropping the stock with it', function () {
    $product = Product::factory()->create();
    $variant = ProductVariant::create([
        'product_id' => $product->id,
        'sku' => 'VAR-'.uniqid(),
        'price' => 10,
        'stock_quantity' => 6,
    ]);

    WarehouseInventory::create([
        'product_id' => $product->id,
        'product_variant_id' => $variant->id,
        'warehouse_id' => removalWarehouse()->id,
        'quantity' => 6,
        'available_quantity' => 6,
        'reserved_quantity' => 0,
    ]);

    $this->actingAs($this->actor)
        ->deleteJson('/api/v1/admin/product-variants/'.$variant->id)
        ->assertStatus(422);

    expect(ProductVariant::find($variant->id))->not->toBeNull()
        // The count on the floor still matches the count in the system.
        ->and(DB::table('warehouse_inventory')->where('product_variant_id', $variant->id)->count())->toBe(1);
});
