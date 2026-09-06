<?php

use App\Models\InvoiceItem;
use App\Models\Product;
use App\Models\User;
use App\Models\Warehouse;
use App\Services\Inventory\InventoryService;

/**
 * What a sale cost, recorded on the sale.
 *
 * Profit reporting used to cost an invoice by multiplying its quantities
 * against the product's *current* cost_price. The real figure was already
 * being measured a few lines earlier — the goods issue consumes FIFO layers
 * and knows exactly what the units it took were bought for — but it was
 * written only to the warehouse ledger, where no sales report could reach it.
 *
 * So two things were wrong at once: a sale drawn from a cheap batch and one
 * drawn from an expensive batch reported the same cost, and re-pricing a
 * product silently rewrote the margin of every invoice it had ever appeared
 * in. The cost is now stamped on the line as the goods leave.
 */
beforeEach(function () {
    $this->user = User::factory()->admin()->create();

    $this->warehouse = Warehouse::create([
        'name' => 'مستودع التكلفة',
        'code' => 'WH-COST',
        'is_active' => true,
    ]);

    // The catalogue says 100. What was actually paid, below, says otherwise —
    // which is the whole point of the exercise.
    $this->product = Product::factory()->create(['price' => 250, 'cost_price' => 100]);

    $this->receive = function (int $quantity, float $unitCost) {
        app(InventoryService::class)->receive(
            $this->product->id,
            $quantity,
            $this->warehouse->id,
            ['key' => uniqid('recv-', true), 'unit_cost' => $unitCost]
        );
    };
});

it('records what the issued goods cost on the invoice line', function () {
    // Two batches at two prices: 2 @ 40, then 2 @ 80.
    ($this->receive)(2, 40);
    ($this->receive)(2, 80);

    $this->actingAs($this->user)->postJson('/api/v1/invoices', [
        'items' => [
            ['product_id' => $this->product->id, 'quantity' => 3, 'unit_price' => 250, 'warehouse_id' => $this->warehouse->id],
        ],
    ])->assertCreated();

    $line = InvoiceItem::first();

    // Oldest first: 40 + 40 + 80. Costing off the catalogue would have said
    // 300, and reported 450 of profit where there was 610.
    expect((float) $line->total_cost)->toBe(160.0)
        ->and(round((float) $line->unit_cost, 5))->toBe(53.33333);
});

it('reports that cost rather than re-deriving one from the catalogue', function () {
    ($this->receive)(5, 40);

    $this->actingAs($this->user)->postJson('/api/v1/invoices', [
        'items' => [
            ['product_id' => $this->product->id, 'quantity' => 2, 'unit_price' => 250, 'warehouse_id' => $this->warehouse->id],
        ],
    ])->assertCreated();

    // The supplier's price moves after the sale is closed. The sale does not.
    $this->product->update(['cost_price' => 500]);

    $row = $this->actingAs($this->user, 'sanctum')
        ->getJson('/api/v1/admin/reports/invoices')
        ->assertOk()
        ->json('data.invoices.0');

    expect((float) $row['total_cost'])->toBe(80.0)
        ->and((float) $row['gross_profit'])->toBe(420.0)
        // Nothing here was guessed at.
        ->and((int) $row['estimated_lines'])->toBe(0)
        ->and((int) $row['uncosted_lines'])->toBe(0);
});

it('costs each line against the warehouse it actually left', function () {
    $other = Warehouse::create(['name' => 'مستودع آخر', 'code' => 'WH-COST-2', 'is_active' => true]);

    // The same product held in two places, bought at two prices.
    ($this->receive)(5, 40);
    app(InventoryService::class)->receive(
        $this->product->id,
        5,
        $other->id,
        ['key' => uniqid('recv-', true), 'unit_cost' => 90]
    );

    $this->actingAs($this->user)->postJson('/api/v1/invoices', [
        'items' => [
            ['product_id' => $this->product->id, 'quantity' => 1, 'unit_price' => 250, 'warehouse_id' => $this->warehouse->id],
            ['product_id' => $this->product->id, 'quantity' => 1, 'unit_price' => 250, 'warehouse_id' => $other->id],
        ],
    ])->assertCreated();

    $costs = InvoiceItem::orderBy('id')->pluck('total_cost')->map(fn ($cost) => (float) $cost)->all();

    // One shelf's stock is not the other's, and a single product-level cost
    // could not have told them apart.
    expect($costs)->toBe([40.0, 90.0]);
});
