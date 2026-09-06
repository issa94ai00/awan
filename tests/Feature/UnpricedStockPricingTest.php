<?php

use App\Models\Product;
use App\Models\User;
use App\Models\Warehouse;
use App\Models\WarehouseInventory;

/**
 * Clearing the "stocked items with no cost price" finding from the accounting
 * screen.
 *
 * The health check counted these items and told the reader to go and price
 * them on another screen, without naming one of them. This endpoint names them
 * and takes the answer — and because it writes, it is deliberately not part of
 * the read-only health report.
 *
 * The population is "on a shelf, and cost unknown": stock is what makes an
 * unpriced item matter, since one nobody holds costs the books nothing.
 */
function stockedProduct(float $cost = 0, int $quantity = 5): Product
{
    $product = Product::factory()->create(['price' => 10, 'cost_price' => $cost]);

    WarehouseInventory::create([
        'product_id' => $product->id,
        'warehouse_id' => Warehouse::create([
            'name' => 'مستودع '.uniqid(),
            'code' => strtoupper(substr(uniqid(), -6)),
            'is_active' => true,
        ])->id,
        'quantity' => $quantity,
        'available_quantity' => $quantity,
        'reserved_quantity' => 0,
    ]);

    return $product;
}

beforeEach(function () {
    $this->actor = User::factory()->admin()->create();
});

it('lists only stocked products whose cost is unknown', function () {
    $unpriced = stockedProduct(cost: 0);
    $priced = stockedProduct(cost: 4.5);
    $unpricedButNotStocked = Product::factory()->create(['cost_price' => 0]);

    $ids = collect(
        $this->actingAs($this->actor)
            ->getJson('/api/v1/admin/accounting/unpriced-stock')
            ->assertOk()
            ->json('data.products')
    )->pluck('id');

    expect($ids)->toContain($unpriced->id)
        ->and($ids)->not->toContain($priced->id)
        // Nothing on the shelf, nothing mis-costed.
        ->and($ids)->not->toContain($unpricedButNotStocked->id);
});

it('sets the cost price it is given', function () {
    $product = stockedProduct(cost: 0);

    $this->actingAs($this->actor)
        ->putJson('/api/v1/admin/accounting/unpriced-stock', [
            'items' => [['id' => $product->id, 'cost_price' => 3.25]],
        ])
        ->assertOk()
        ->assertJsonPath('data.updated', 1)
        ->assertJsonPath('data.remaining', 0);

    expect((float) $product->fresh()->cost_price)->toBe(3.25);
});

it('refuses a zero cost, which would leave the item exactly as it was', function () {
    $product = stockedProduct(cost: 0);

    $this->actingAs($this->actor)
        ->putJson('/api/v1/admin/accounting/unpriced-stock', [
            'items' => [['id' => $product->id, 'cost_price' => 0]],
        ])
        ->assertStatus(422);

    expect((float) $product->fresh()->cost_price)->toBe(0.0);
});

it('will not rewrite the cost of a product that already has one', function () {
    // The screen exists to clear one finding. It is not a way in to arbitrary
    // product data, so a product outside the finding is reported, not written.
    $alreadyPriced = stockedProduct(cost: 7.0);

    $this->actingAs($this->actor)
        ->putJson('/api/v1/admin/accounting/unpriced-stock', [
            'items' => [['id' => $alreadyPriced->id, 'cost_price' => 0.01]],
        ])
        ->assertOk()
        ->assertJsonPath('data.updated', 0)
        ->assertJsonPath('data.skipped', [$alreadyPriced->id]);

    expect((float) $alreadyPriced->fresh()->cost_price)->toBe(7.0);
});

it('prices several items at once and reports what is left', function () {
    $first = stockedProduct(cost: 0);
    $second = stockedProduct(cost: 0);
    $untouched = stockedProduct(cost: 0);

    $this->actingAs($this->actor)
        ->putJson('/api/v1/admin/accounting/unpriced-stock', [
            'items' => [
                ['id' => $first->id, 'cost_price' => 1.5],
                ['id' => $second->id, 'cost_price' => 2.5],
            ],
        ])
        ->assertOk()
        ->assertJsonPath('data.updated', 2)
        // The finding shrinks rather than clears: one item was left blank.
        ->assertJsonPath('data.remaining', 1);

    expect((float) $untouched->fresh()->cost_price)->toBe(0.0);
});

it('is closed to users who have no business in the books', function () {
    $product = stockedProduct(cost: 0);
    // No admin flag and no role: `role` is a relation here, not a column, so a
    // plain user carries neither.
    $outsider = User::factory()->create();

    $this->actingAs($outsider)
        ->putJson('/api/v1/admin/accounting/unpriced-stock', [
            'items' => [['id' => $product->id, 'cost_price' => 3]],
        ])
        ->assertForbidden();

    expect((float) $product->fresh()->cost_price)->toBe(0.0);
});
