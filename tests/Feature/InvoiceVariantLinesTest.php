<?php

use App\Models\Customer;
use App\Models\Invoice;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\User;
use App\Models\Warehouse;
use App\Services\Inventory\InventoryService;

/**
 * A direct-sale invoice can sell one size of a product.
 *
 * Warehouse stock is per product and moves through the issue as before. The
 * line keeps the variant, is named for it, and the variant's own count
 * follows the goods out, back on an edit, and back again on a cancel.
 */
beforeEach(function () {
    $this->user = User::factory()->admin()->create();
    $this->warehouse = Warehouse::create(['name' => 'الرئيسي', 'code' => 'WH-INV-VAR', 'is_active' => true]);
    $this->customer = Customer::create(['name' => 'زبون', 'status' => 'active']);

    $this->drain = Product::create(['name_ar' => 'جريدة تصريف', 'sku' => 'FD', 'price' => 5, 'cost_price' => 1]);
    $this->four = ProductVariant::create(['product_id' => $this->drain->id, 'sku' => 'FD-4', 'size' => '4"', 'price' => 0.95, 'stock_quantity' => 20]);
    $this->five = ProductVariant::create(['product_id' => $this->drain->id, 'sku' => 'FD-5', 'size' => '5"', 'price' => 1.7, 'stock_quantity' => 20]);

    app(InventoryService::class)->receive($this->drain->id, 40, $this->warehouse->id, ['key' => 'seed-inv-var', 'unit_cost' => 1]);

    $this->line = fn (ProductVariant $variant, int $quantity) => [
        'product_id' => $this->drain->id, 'product_variant_id' => $variant->id,
        'quantity' => $quantity, 'unit_price' => (float) $variant->price, 'warehouse_id' => $this->warehouse->id,
    ];
});

test('an invoice sells two sizes of one product as two lines', function () {
    $id = $this->actingAs($this->user)->postJson('/api/v1/invoices', [
        'customer_id' => $this->customer->id,
        'items' => [($this->line)($this->four, 3), ($this->line)($this->five, 2)],
    ])->assertCreated()->json('data.id');

    $items = Invoice::find($id)->items->keyBy('product_variant_id');
    expect($items[$this->four->id]->product_name)->toBe('جريدة تصريف - 4"')
        ->and($items[$this->five->id]->product_name)->toBe('جريدة تصريف - 5"');

    // Both lines left the shelf, and each size's count followed its own.
    expect((int) $this->drain->refresh()->stock_quantity)->toBe(35)
        ->and($this->four->refresh()->stock_quantity)->toBe(17)
        ->and($this->five->refresh()->stock_quantity)->toBe(18);

    // Reopening gives the form what it restores: warehouse, unit and variant.
    $shown = collect($this->getJson('/api/v1/invoices/'.$id)->assertOk()->json('data.items'))->keyBy('product_variant_id');
    expect($shown[$this->four->id]['warehouse_id'])->toBe($this->warehouse->id)
        ->and($shown[$this->four->id]['variant']['label'])->toBe('4"')
        ->and($shown[$this->four->id]['variant']['sku'])->toBe('FD-4');
});

test('editing a size\'s quantity moves its count by the difference, and cancelling puts it back', function () {
    $id = $this->actingAs($this->user)->postJson('/api/v1/invoices', [
        'customer_id' => $this->customer->id,
        'items' => [($this->line)($this->four, 3)],
    ])->assertCreated()->json('data.id');

    $this->putJson('/api/v1/invoices/'.$id, [
        'items' => [($this->line)($this->four, 5), ($this->line)($this->five, 1)],
    ])->assertOk();

    expect($this->four->refresh()->stock_quantity)->toBe(15)
        ->and($this->five->refresh()->stock_quantity)->toBe(19)
        ->and((int) $this->drain->refresh()->stock_quantity)->toBe(34);

    $this->putJson('/api/v1/invoices/'.$id.'/status', ['status' => 'cancelled'])->assertOk();

    expect($this->four->refresh()->stock_quantity)->toBe(20)
        ->and($this->five->refresh()->stock_quantity)->toBe(20)
        ->and((int) $this->drain->refresh()->stock_quantity)->toBe(40);
});

test('an invoice refuses a variant of another product', function () {
    $tap = Product::create(['name_ar' => 'حنفية', 'price' => 20]);

    $this->actingAs($this->user)->postJson('/api/v1/invoices', [
        'customer_id' => $this->customer->id,
        'items' => [[
            'product_id' => $tap->id, 'product_variant_id' => $this->four->id,
            'quantity' => 1, 'unit_price' => 1, 'warehouse_id' => $this->warehouse->id,
        ]],
    ])->assertStatus(422)->assertJsonValidationErrors('items.0.product_variant_id');
});
