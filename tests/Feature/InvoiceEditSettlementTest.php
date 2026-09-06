<?php

use App\Models\Customer;
use App\Models\InvoiceItem;
use App\Models\JournalEntryHeader;
use App\Models\Product;
use App\Models\User;
use App\Models\Warehouse;
use App\Models\WarehouseInventory;
use App\Services\Inventory\InventoryService;

/**
 * Editing an invoice has to settle the goods and the books, not just the rows.
 *
 * It used to rewrite the lines and stop. The goods stayed off the shelves in
 * the quantities first rung up, the ledger went on carrying the original
 * revenue, receivable and cost, and the measured cost of the sale was thrown
 * away with the rows it lived on. Changing a quantity therefore left the
 * invoice, the warehouse and the books each describing a different sale, and
 * nothing anywhere said so.
 *
 * The goods that actually left now come back, the edited lines are issued in
 * their place, and the books are told the difference — a correction against
 * the original entry rather than a rewrite of it, so the invoice as issued
 * stays on the record beside the amendment.
 */
beforeEach(function () {
    $this->user = User::factory()->admin()->create();

    $this->main = Warehouse::create(['name' => 'الرئيسي', 'code' => 'WH-EDIT-1', 'is_active' => true]);
    $this->branch = Warehouse::create(['name' => 'الفرع', 'code' => 'WH-EDIT-2', 'is_active' => true]);

    // The catalogue says 100; the stock was bought at 40.
    $this->product = Product::factory()->create(['price' => 250, 'cost_price' => 100]);
    $this->other = Product::factory()->create(['price' => 250, 'cost_price' => 100]);

    $this->customer = Customer::create(['name' => 'زبون التعديل', 'status' => 'active']);

    $this->receive = function (Product $product, int $quantity, float $unitCost, Warehouse $warehouse) {
        app(InventoryService::class)->receive(
            $product->id,
            $quantity,
            $warehouse->id,
            ['key' => uniqid('recv-', true), 'unit_cost' => $unitCost]
        );
    };

    $this->onHand = fn (Product $product, Warehouse $warehouse) => (int) WarehouseInventory::where('product_id', $product->id)
        ->where('warehouse_id', $warehouse->id)
        ->value('available_quantity');

    $this->invoice = function (int $quantity = 4, int $stock = 20) {
        ($this->receive)($this->product, $stock, 40, $this->main);

        return $this->actingAs($this->user)->postJson('/api/v1/invoices', [
            'customer_id' => $this->customer->id,
            'items' => [
                ['product_id' => $this->product->id, 'quantity' => $quantity, 'unit_price' => 250, 'warehouse_id' => $this->main->id],
            ],
        ])->assertCreated()->json('data.id');
    };

    $this->edit = fn (int $id, array $items, array $extra = []) => $this->actingAs($this->user)
        ->putJson('/api/v1/invoices/'.$id, ['items' => $items] + $extra);
});

it('keeps the measured cost when the edit leaves the goods alone', function () {
    $id = ($this->invoice)(4);

    expect((float) InvoiceItem::first()->total_cost)->toBe(160.0);

    // Only the price is corrected. The same four units go back and come out
    // again — off the same batch, at the same cost, because a returned layer
    // is dated as it originally arrived rather than as of today.
    ($this->edit)($id, [
        ['product_id' => $this->product->id, 'quantity' => 4, 'unit_price' => 300, 'warehouse_id' => $this->main->id],
    ])->assertOk();

    $line = InvoiceItem::first();

    expect((float) $line->total_cost)->toBe(160.0)
        ->and((float) $line->unit_cost)->toBe(40.0)
        ->and((float) $line->unit_price)->toBe(300.0)
        ->and(($this->onHand)($this->product, $this->main))->toBe(16);
});

it('keeps the warehouse the edit used to strip off every line', function () {
    $id = ($this->invoice)(4);

    ($this->edit)($id, [
        ['product_id' => $this->product->id, 'quantity' => 4, 'unit_price' => 250, 'warehouse_id' => $this->main->id],
    ])->assertOk();

    expect((int) InvoiceItem::first()->warehouse_id)->toBe($this->main->id);
});

it('puts the goods back on the shelf when fewer are billed', function () {
    $id = ($this->invoice)(4);

    expect(($this->onHand)($this->product, $this->main))->toBe(16);

    ($this->edit)($id, [
        ['product_id' => $this->product->id, 'quantity' => 2, 'unit_price' => 250, 'warehouse_id' => $this->main->id],
    ])->assertOk();

    // Two units are no longer being sold, so the warehouse has them back.
    expect(($this->onHand)($this->product, $this->main))->toBe(18)
        ->and((float) InvoiceItem::first()->total_cost)->toBe(80.0);
});

it('takes the extra goods when more are billed, and costs them for real', function () {
    $id = ($this->invoice)(4);

    ($this->edit)($id, [
        ['product_id' => $this->product->id, 'quantity' => 6, 'unit_price' => 250, 'warehouse_id' => $this->main->id],
    ])->assertOk();

    // Six units have now left, and all six were costed by an issue rather
    // than valued at the catalogue's 100.
    expect(($this->onHand)($this->product, $this->main))->toBe(14)
        ->and((float) InvoiceItem::first()->total_cost)->toBe(240.0);
});

it('moves the goods between shelves when the edit changes where they come from', function () {
    $id = ($this->invoice)(4);
    ($this->receive)($this->product, 10, 55, $this->branch);

    ($this->edit)($id, [
        ['product_id' => $this->product->id, 'quantity' => 4, 'unit_price' => 250, 'warehouse_id' => $this->branch->id],
    ])->assertOk();

    // The main store is whole again and the branch is four down, costed at
    // what the branch's own stock was bought for.
    expect(($this->onHand)($this->product, $this->main))->toBe(20)
        ->and(($this->onHand)($this->product, $this->branch))->toBe(6)
        ->and((float) InvoiceItem::first()->total_cost)->toBe(220.0);
});

it('refuses an edit the shelves cannot cover, and leaves everything as it was', function () {
    $id = ($this->invoice)(4, stock: 5);

    expect(($this->onHand)($this->product, $this->main))->toBe(1);

    // Ten asked for where five were ever received. The four already issued
    // come back during the attempt, so nine is the most that could be covered.
    ($this->edit)($id, [
        ['product_id' => $this->product->id, 'quantity' => 10, 'unit_price' => 250, 'warehouse_id' => $this->main->id],
    ])->assertStatus(422)->assertJsonPath('data.shortages.0.shortfall', 5);

    // Nothing half-applied: the line, the shelf and the cost are as they were.
    expect(($this->onHand)($this->product, $this->main))->toBe(1)
        ->and((int) InvoiceItem::first()->quantity)->toBe(4)
        ->and((float) InvoiceItem::first()->total_cost)->toBe(160.0);
});

it('tells the books the difference instead of restating the sale', function () {
    $id = ($this->invoice)(4);

    // 4 × 250 = 1000 receivable, 160 of cost.
    $original = JournalEntryHeader::where('posting_key', 'invoice:'.$id)->firstOrFail();

    ($this->edit)($id, [
        ['product_id' => $this->product->id, 'quantity' => 6, 'unit_price' => 250, 'warehouse_id' => $this->main->id],
    ])->assertOk();

    // The entry as issued is untouched and unreversed — the amendment sits
    // beside it rather than voiding it.
    expect($original->fresh()->status)->not->toBe('reversed');

    $correction = JournalEntryHeader::with('lines')
        ->where('posting_key', 'invoice_adjust:'.$id.':1')
        ->firstOrFail();
    $costCorrection = JournalEntryHeader::with('lines')
        ->where('posting_key', 'invoice_cogs_adjust:'.$id.':1')
        ->firstOrFail();

    // Two more units at 250: 500 more owed, and 80 more of cost.
    expect((float) $correction->total_debit)->toBe(500.0)
        ->and((float) $costCorrection->total_debit)->toBe(80.0);
});

it('credits the books back when the edit makes the sale smaller', function () {
    $id = ($this->invoice)(4);

    ($this->edit)($id, [
        ['product_id' => $this->product->id, 'quantity' => 1, 'unit_price' => 250, 'warehouse_id' => $this->main->id],
    ])->assertOk();

    $correction = JournalEntryHeader::with('lines')
        ->where('posting_key', 'invoice_adjust:'.$id.':1')
        ->firstOrFail();

    // 750 comes off the receivable, so the line that debited it now credits.
    $receivable = $correction->lines->firstWhere(fn ($line) => (float) $line->credit === 750.0);

    expect($receivable)->not->toBeNull()
        ->and((float) $correction->total_credit)->toBe(750.0);
});

it('numbers each correction so a second edit does not overwrite the first', function () {
    $id = ($this->invoice)(4);

    ($this->edit)($id, [
        ['product_id' => $this->product->id, 'quantity' => 5, 'unit_price' => 250, 'warehouse_id' => $this->main->id],
    ])->assertOk();
    ($this->edit)($id, [
        ['product_id' => $this->product->id, 'quantity' => 6, 'unit_price' => 250, 'warehouse_id' => $this->main->id],
    ])->assertOk();

    expect(JournalEntryHeader::where('posting_key', 'invoice_adjust:'.$id.':1')->exists())->toBeTrue()
        ->and(JournalEntryHeader::where('posting_key', 'invoice_adjust:'.$id.':2')->exists())->toBeTrue()
        ->and(($this->onHand)($this->product, $this->main))->toBe(14);
});

it('moves the customer balance by what the edit changed', function () {
    $id = ($this->invoice)(4);

    expect((float) $this->customer->fresh()->balance)->toBe(1000.0);

    ($this->edit)($id, [
        ['product_id' => $this->product->id, 'quantity' => 2, 'unit_price' => 250, 'warehouse_id' => $this->main->id],
    ])->assertOk();

    // Half the sale is gone, so half the debt is too.
    expect((float) $this->customer->fresh()->balance)->toBe(500.0);
});

it('does not reissue against a cancelled invoice', function () {
    $id = ($this->invoice)(4);

    $this->actingAs($this->user)
        ->putJson('/api/v1/invoices/'.$id.'/status', ['status' => 'cancelled'])
        ->assertOk();

    // Cancelling gave the goods back and reversed the entries.
    expect(($this->onHand)($this->product, $this->main))->toBe(20);

    ($this->edit)($id, [
        ['product_id' => $this->product->id, 'quantity' => 2, 'unit_price' => 250, 'warehouse_id' => $this->main->id],
    ])->assertOk();

    // Editing it must not take the stock out again for a sale that is not
    // happening, or post a correction to entries already reversed. The lines
    // are left uncosted, which is what the report should say about goods no
    // issue stands behind.
    expect(($this->onHand)($this->product, $this->main))->toBe(20)
        ->and(InvoiceItem::first()->total_cost)->toBeNull()
        ->and(JournalEntryHeader::where('posting_key', 'invoice_adjust:'.$id.':1')->exists())->toBeFalse();
});

it('reports an edited invoice on the cost the reissue measured', function () {
    $id = ($this->invoice)(4);

    ($this->edit)($id, [
        ['product_id' => $this->product->id, 'quantity' => 4, 'unit_price' => 300, 'warehouse_id' => $this->main->id],
    ])->assertOk();

    $row = $this->actingAs($this->user, 'sanctum')
        ->getJson('/api/v1/admin/reports/invoices')
        ->assertOk()
        ->json('data.invoices.0');

    // 1200 billed against 160 of goods, and nothing about it estimated.
    expect((float) $row['total_cost'])->toBe(160.0)
        ->and((float) $row['gross_profit'])->toBe(1040.0)
        ->and((int) $row['estimated_lines'])->toBe(0);
});
