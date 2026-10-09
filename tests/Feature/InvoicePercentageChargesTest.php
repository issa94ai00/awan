<?php

use App\Models\Customer;
use App\Models\Invoice;
use App\Models\Product;
use App\Models\User;
use App\Models\Warehouse;
use App\Services\Inventory\InventoryService;

/**
 * A discount and a tax are struck as rates, and the invoice carries both.
 *
 * The screen used to ask for two figures. A seller agreeing "ten percent off"
 * worked the number out themselves, typed it, and it went stale the moment a
 * quantity changed — and once saved, an invoice discounted 10% and one
 * discounted 80.00 read the same, so reopening the first offered back a flat
 * figure that no longer tracked its own lines.
 *
 * Rates now decide the figures, on the server's own subtotal rather than on
 * the client's arithmetic, and the rate is stored beside the money it came to.
 * The money is unchanged in kind: it is still what the ledger posts and what
 * every report reads.
 */
beforeEach(function () {
    $this->user = User::factory()->admin()->create();

    $this->warehouse = Warehouse::create(['name' => 'الرئيسي', 'code' => 'WH-PCT-1', 'is_active' => true]);
    $this->product = Product::factory()->create(['price' => 250, 'cost_price' => 100]);
    $this->customer = Customer::create(['name' => 'زبون النسب', 'status' => 'active']);

    app(InventoryService::class)->receive(
        $this->product->id,
        50,
        $this->warehouse->id,
        ['key' => uniqid('recv-', true), 'unit_cost' => 40]
    );

    // Four at 250 — a subtotal of 1,000, so every rate below reads as itself.
    $this->raise = fn (array $charges = []) => $this->actingAs($this->user)->postJson('/api/v1/invoices', [
        'customer_id' => $this->customer->id,
        'items' => [
            ['product_id' => $this->product->id, 'quantity' => 4, 'unit_price' => 250, 'warehouse_id' => $this->warehouse->id],
        ],
    ] + $charges);
});

it('charges a discount as a rate on the goods', function () {
    $id = ($this->raise)(['discount_percent' => 10])->assertCreated()->json('data.id');

    $invoice = Invoice::find($id);

    expect((float) $invoice->subtotal)->toBe(1000.0)
        ->and((float) $invoice->discount)->toBe(100.0)
        ->and((float) $invoice->discount_percent)->toBe(10.0)
        ->and((float) $invoice->total)->toBe(900.0);
});

it('charges tax on what is left after the discount, not on the goods before it', function () {
    $id = ($this->raise)(['discount_percent' => 10, 'tax_percent' => 5])
        ->assertCreated()->json('data.id');

    $invoice = Invoice::find($id);

    // 5% of 900, not 5% of 1,000 — the tax follows the price actually charged.
    expect((float) $invoice->discount)->toBe(100.0)
        ->and((float) $invoice->tax)->toBe(45.0)
        ->and((float) $invoice->tax_percent)->toBe(5.0)
        ->and((float) $invoice->total)->toBe(945.0);
});

it('works the rate off its own subtotal rather than trusting the figure sent with it', function () {
    // A client that sends both, disagreeing. The rate is what the seller chose,
    // so it decides; the stale figure beside it is ignored rather than averaged
    // with it or preferred for being more specific.
    $id = ($this->raise)(['discount_percent' => 10, 'discount' => 999])
        ->assertCreated()->json('data.id');

    expect((float) Invoice::find($id)->discount)->toBe(100.0);
});

it('still takes plain figures from a client that has no rates to send', function () {
    $id = ($this->raise)(['discount' => 80, 'tax' => 20])->assertCreated()->json('data.id');

    $invoice = Invoice::find($id);

    // Null, not zero: this invoice was written in amounts, and saying it was
    // struck at 0% would be a claim nobody made.
    expect((float) $invoice->discount)->toBe(80.0)
        ->and((float) $invoice->tax)->toBe(20.0)
        ->and($invoice->discount_percent)->toBeNull()
        ->and($invoice->tax_percent)->toBeNull()
        ->and((float) $invoice->total)->toBe(940.0);
});

it('re-strikes the rates on the new subtotal when the invoice is edited', function () {
    $id = ($this->raise)(['discount_percent' => 10, 'tax_percent' => 5])
        ->assertCreated()->json('data.id');

    // Half the goods come off the invoice. The rates are unchanged, so both
    // figures halve with the subtotal instead of standing at what they were.
    $this->actingAs($this->user)->putJson('/api/v1/invoices/'.$id, [
        'items' => [
            ['product_id' => $this->product->id, 'quantity' => 2, 'unit_price' => 250, 'warehouse_id' => $this->warehouse->id],
        ],
        'discount_percent' => 10,
        'tax_percent' => 5,
    ])->assertOk();

    $invoice = Invoice::find($id);

    expect((float) $invoice->subtotal)->toBe(500.0)
        ->and((float) $invoice->discount)->toBe(50.0)
        ->and((float) $invoice->tax)->toBe(22.5)
        ->and((float) $invoice->total)->toBe(472.5);
});

it('gives the rates back to the screen that has to offer them again', function () {
    $id = ($this->raise)(['discount_percent' => 12.5, 'tax_percent' => 5])
        ->assertCreated()->json('data.id');

    $this->actingAs($this->user)->getJson('/api/v1/invoices/'.$id)
        ->assertOk()
        // Compared as numbers: JSON gives a whole percentage back as 5, not
        // 5.0, and assertJsonPath compares identically.
        ->assertJsonPath('data.discount_percent', fn ($value) => (float) $value === 12.5)
        ->assertJsonPath('data.tax_percent', fn ($value) => (float) $value === 5.0)
        ->assertJsonPath('data.discount', fn ($value) => (float) $value === 125.0)
        ->assertJsonPath('data.tax', fn ($value) => (float) $value === 43.75);
});

it('refuses a rate that is not one', function () {
    ($this->raise)(['discount_percent' => 140])->assertStatus(422);
    ($this->raise)(['tax_percent' => -1])->assertStatus(422);
});
