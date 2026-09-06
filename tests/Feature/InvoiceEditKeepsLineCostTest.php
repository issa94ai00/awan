<?php

use App\Models\InvoiceItem;
use App\Models\Product;
use App\Models\User;
use App\Models\Warehouse;
use App\Services\Inventory\InventoryService;

/**
 * Editing an invoice must not throw away what its goods cost.
 *
 * The cost of a sale is measured once, by the issue that moves the goods out
 * of the warehouse. Editing an invoice replaces all of its rows, so that
 * figure went with them: an invoice whose price had been corrected came back
 * costed at whatever the catalogue said that day, and the report marked it as
 * an estimate. Nobody editing a price has any reason to expect that.
 *
 * The same rewrite silently dropped each line's warehouse, which the client
 * had always sent and this end had never read — losing the only record of
 * which shelf the goods left.
 */
beforeEach(function () {
    $this->user = User::factory()->admin()->create();

    $this->main = Warehouse::create(['name' => 'الرئيسي', 'code' => 'WH-EDIT-1', 'is_active' => true]);
    $this->branch = Warehouse::create(['name' => 'الفرع', 'code' => 'WH-EDIT-2', 'is_active' => true]);

    // The catalogue says 100; the stock was bought at 40.
    $this->product = Product::factory()->create(['price' => 250, 'cost_price' => 100]);
    $this->other = Product::factory()->create(['price' => 250, 'cost_price' => 100]);

    $this->receive = function (Product $product, int $quantity, float $unitCost, Warehouse $warehouse) {
        app(InventoryService::class)->receive(
            $product->id,
            $quantity,
            $warehouse->id,
            ['key' => uniqid('recv-', true), 'unit_cost' => $unitCost]
        );
    };

    $this->invoice = function (int $quantity = 4) {
        ($this->receive)($this->product, 20, 40, $this->main);

        return $this->actingAs($this->user)->postJson('/api/v1/invoices', [
            'items' => [
                ['product_id' => $this->product->id, 'quantity' => $quantity, 'unit_price' => 250, 'warehouse_id' => $this->main->id],
            ],
        ])->assertCreated()->json('data.id');
    };

    $this->edit = fn (int $id, array $items) => $this->actingAs($this->user)
        ->putJson('/api/v1/invoices/'.$id, ['items' => $items])
        ->assertOk();
});

it('keeps the measured cost when the edit leaves the goods alone', function () {
    $id = ($this->invoice)(4);

    expect((float) InvoiceItem::first()->total_cost)->toBe(160.0);

    // Only the price is corrected. The same four units came off the same
    // shelf, and what they cost has not changed with them.
    ($this->edit)($id, [
        ['product_id' => $this->product->id, 'quantity' => 4, 'unit_price' => 300, 'warehouse_id' => $this->main->id],
    ]);

    $line = InvoiceItem::first();

    expect((float) $line->total_cost)->toBe(160.0)
        ->and((float) $line->unit_cost)->toBe(40.0)
        ->and((float) $line->unit_price)->toBe(300.0);
});

it('keeps the warehouse the edit used to strip off every line', function () {
    $id = ($this->invoice)(4);

    ($this->edit)($id, [
        ['product_id' => $this->product->id, 'quantity' => 4, 'unit_price' => 250, 'warehouse_id' => $this->main->id],
    ]);

    expect((int) InvoiceItem::first()->warehouse_id)->toBe($this->main->id);
});

it('rescales the cost down when fewer units are billed than were issued', function () {
    $id = ($this->invoice)(4);

    // Two of the four are taken off the bill. Those two units did leave the
    // warehouse at 40 apiece, so their cost is still a measurement.
    ($this->edit)($id, [
        ['product_id' => $this->product->id, 'quantity' => 2, 'unit_price' => 250, 'warehouse_id' => $this->main->id],
    ]);

    $line = InvoiceItem::first();

    expect((float) $line->total_cost)->toBe(80.0)
        ->and((float) $line->unit_cost)->toBe(40.0);
});

it('will not claim to have measured units that never moved', function () {
    $id = ($this->invoice)(4);

    // Six billed where four were issued. The extra two have no issue behind
    // them, so the line stops claiming a measured cost altogether rather than
    // extrapolating one from the units that did move.
    ($this->edit)($id, [
        ['product_id' => $this->product->id, 'quantity' => 6, 'unit_price' => 250, 'warehouse_id' => $this->main->id],
    ]);

    expect(InvoiceItem::first()->total_cost)->toBeNull();
});

it('leaves a line uncosted when the edit changes what it is for', function () {
    $id = ($this->invoice)(4);

    // A different product, and the same product off a different shelf: neither
    // describes goods this invoice has ever issued.
    ($this->edit)($id, [
        ['product_id' => $this->other->id, 'quantity' => 4, 'unit_price' => 250, 'warehouse_id' => $this->main->id],
        ['product_id' => $this->product->id, 'quantity' => 1, 'unit_price' => 250, 'warehouse_id' => $this->branch->id],
    ]);

    expect(InvoiceItem::pluck('total_cost')->filter()->all())->toBe([]);
});

it('reports an edited invoice on the cost it kept', function () {
    $id = ($this->invoice)(4);

    ($this->edit)($id, [
        ['product_id' => $this->product->id, 'quantity' => 4, 'unit_price' => 300, 'warehouse_id' => $this->main->id],
    ]);

    $row = $this->actingAs($this->user, 'sanctum')
        ->getJson('/api/v1/admin/reports/invoices')
        ->assertOk()
        ->json('data.invoices.0');

    // 1200 billed against 160 of goods, and nothing about it estimated.
    expect((float) $row['total_cost'])->toBe(160.0)
        ->and((float) $row['gross_profit'])->toBe(1040.0)
        ->and((int) $row['estimated_lines'])->toBe(0);
});
