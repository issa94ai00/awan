<?php

use App\Models\Customer;
use App\Models\Employee;
use App\Models\Invoice;
use App\Models\JournalEntryHeader;
use App\Models\Product;
use App\Models\StockMovement;
use App\Models\User;
use App\Models\Warehouse;
use App\Models\WarehouseInventory;

/**
 * Selling straight from the dashboard: the goods, the books and the rep.
 *
 * Raising an invoice here used to do three wrong things at once, and each one
 * was invisible from the screen:
 *
 *   1. Stock was issued with no warehouse named, so it fell through to
 *      "whichever warehouse has the lowest id" and drew every sale from there
 *      regardless of where the goods were. One warehouse drifted negative while
 *      the real one stayed full.
 *   2. `allow_negative` let the sale proceed anyway. A negative shelf is not a
 *      smaller number — it is a record that has stopped describing anything.
 *   3. No cost-of-sales entry was posted at all. Revenue and the receivable
 *      went to the ledger; the cost of the goods never did. Every direct sale
 *      therefore overstated gross profit by the whole cost of what it sold.
 *
 * And cancelling one did nothing but change a label: the goods stayed off the
 * shelf and the books went on carrying the revenue.
 */
function sale(string $code): Warehouse
{
    return Warehouse::create(['name' => 'مستودع '.$code, 'code' => $code, 'is_active' => true]);
}

function stocked(Warehouse $warehouse, int $quantity, float $cost = 60, float $price = 100): Product
{
    $product = Product::factory()->create(['price' => $price, 'cost_price' => $cost]);

    WarehouseInventory::create([
        'product_id' => $product->id,
        'warehouse_id' => $warehouse->id,
        'quantity' => $quantity,
        'available_quantity' => $quantity,
        'reserved_quantity' => 0,
    ]);

    return $product;
}

function onHand(Product $product, Warehouse $warehouse): int
{
    return (int) WarehouseInventory::where('product_id', $product->id)
        ->where('warehouse_id', $warehouse->id)
        ->value('available_quantity');
}

beforeEach(function () {
    $this->user = User::factory()->admin()->create();
});

it('takes the goods from the warehouse the line names, not the lowest id', function () {
    // Deliberately created first, so it holds the lowest id — the warehouse the
    // old code would have drawn from no matter what the line said.
    $main = sale('MAIN');
    $branch = sale('BRANCH');

    $product = stocked($branch, 20);
    WarehouseInventory::create([
        'product_id' => $product->id,
        'warehouse_id' => $main->id,
        'quantity' => 100,
        'available_quantity' => 100,
        'reserved_quantity' => 0,
    ]);

    $this->actingAs($this->user)->postJson('/api/v1/invoices', [
        'items' => [
            ['product_id' => $product->id, 'quantity' => 5, 'unit_price' => 100, 'warehouse_id' => $branch->id],
        ],
    ])->assertCreated();

    expect(onHand($product, $branch))->toBe(15)
        ->and(onHand($product, $main))->toBe(100);
});

it('refuses a sale the named warehouse cannot cover, and writes nothing', function () {
    $branch = sale('BRANCH2');
    $product = stocked($branch, 3);

    $before = Invoice::count();

    $response = $this->actingAs($this->user)->postJson('/api/v1/invoices', [
        'items' => [
            ['product_id' => $product->id, 'quantity' => 10, 'unit_price' => 100, 'warehouse_id' => $branch->id],
        ],
    ])->assertStatus(422);

    $response->assertJsonPath('data.shortages.0.required', 10)
        ->assertJsonPath('data.shortages.0.available', 3)
        ->assertJsonPath('data.shortages.0.shortfall', 7)
        ->assertJsonPath('data.shortages.0.warehouse_name', 'مستودع BRANCH2');

    // Refused before anything was written, and the shelf is untouched.
    expect(Invoice::count())->toBe($before)
        ->and(onHand($product, $branch))->toBe(3);
});

it('never drives a shelf negative', function () {
    $branch = sale('BRANCH3');
    $product = stocked($branch, 2);

    $this->actingAs($this->user)->postJson('/api/v1/invoices', [
        'items' => [
            ['product_id' => $product->id, 'quantity' => 5, 'unit_price' => 100, 'warehouse_id' => $branch->id],
        ],
    ])->assertStatus(422);

    expect(onHand($product, $branch))->toBeGreaterThanOrEqual(0)->toBe(2);
});

it('pools two lines of the same product before deciding it fits', function () {
    $branch = sale('BRANCH4');
    $product = stocked($branch, 6);

    // 4 + 4 against 6 in stock: each line looks fine alone, together they do not.
    $this->actingAs($this->user)->postJson('/api/v1/invoices', [
        'items' => [
            ['product_id' => $product->id, 'quantity' => 4, 'unit_price' => 100, 'warehouse_id' => $branch->id],
            ['product_id' => $product->id, 'quantity' => 4, 'unit_price' => 100, 'warehouse_id' => $branch->id],
        ],
    ])
        ->assertStatus(422)
        ->assertJsonPath('data.shortages.0.required', 8)
        ->assertJsonPath('data.shortages.0.shortfall', 2);
});

it('posts cost of sales so the margin is not overstated', function () {
    $branch = sale('BRANCH5');
    $product = stocked($branch, 20, cost: 60, price: 100);

    $this->actingAs($this->user)->postJson('/api/v1/invoices', [
        'items' => [
            ['product_id' => $product->id, 'quantity' => 5, 'unit_price' => 100, 'warehouse_id' => $branch->id],
        ],
    ])->assertCreated();

    $invoice = Invoice::latest('id')->first();

    // The entry exists and is keyed so it can be reversed later.
    $cogs = JournalEntryHeader::where('posting_key', 'invoice_cogs:'.$invoice->id)->first();

    expect($cogs)->not->toBeNull();

    // 5 units at a cost of 60 — not the 500 they sold for.
    expect((float) $cogs->lines()->sum('debit'))->toEqual(300.0);
});

it('draws the cost from the warehouse the goods actually left', function () {
    $a = sale('A');
    $b = sale('B');

    $product = stocked($a, 10, cost: 50);
    WarehouseInventory::create([
        'product_id' => $product->id,
        'warehouse_id' => $b->id,
        'quantity' => 10,
        'available_quantity' => 10,
        'reserved_quantity' => 0,
    ]);

    $this->actingAs($this->user)->postJson('/api/v1/invoices', [
        'items' => [
            ['product_id' => $product->id, 'quantity' => 2, 'unit_price' => 100, 'warehouse_id' => $a->id],
            ['product_id' => $product->id, 'quantity' => 3, 'unit_price' => 100, 'warehouse_id' => $b->id],
        ],
    ])->assertCreated();

    expect(onHand($product, $a))->toBe(8)
        ->and(onHand($product, $b))->toBe(7);

    // One movement per source, so the warehouse ledger can tell them apart.
    $invoice = Invoice::latest('id')->first();
    expect(StockMovement::where('reference', 'invoice')->where('source', (string) $invoice->id)->count())->toBe(2);
});

it('credits the sale to a rep when one is named', function () {
    $branch = sale('BRANCH6');
    $product = stocked($branch, 20);

    $employee = Employee::create([
        'first_name' => 'أحمد',
        'last_name' => 'المندوب',
        'email' => 'rep@example.test',
        'position' => 'مندوب مبيعات',
        'department' => 'المبيعات',
        'hire_date' => now()->subYear()->toDateString(),
        'status' => 'نشط',
    ]);

    $this->actingAs($this->user)->postJson('/api/v1/invoices', [
        'assigned_employee_id' => $employee->id,
        'items' => [
            ['product_id' => $product->id, 'quantity' => 1, 'unit_price' => 100, 'warehouse_id' => $branch->id],
        ],
    ])->assertCreated();

    expect((int) Invoice::latest('id')->first()->assigned_employee_id)->toBe($employee->id);
});

it('sells without a rep, because a counter sale has none', function () {
    $branch = sale('BRANCH7');
    $product = stocked($branch, 20);

    $this->actingAs($this->user)->postJson('/api/v1/invoices', [
        'items' => [
            ['product_id' => $product->id, 'quantity' => 1, 'unit_price' => 100, 'warehouse_id' => $branch->id],
        ],
    ])->assertCreated();

    expect(Invoice::latest('id')->first()->assigned_employee_id)->toBeNull();
});

it('names the customer on the sale', function () {
    $branch = sale('BRANCH8');
    $product = stocked($branch, 20);
    $customer = Customer::create(['name' => 'عميل', 'phone' => '0999888777']);

    $this->actingAs($this->user)->postJson('/api/v1/invoices', [
        'customer_id' => $customer->id,
        'items' => [
            ['product_id' => $product->id, 'quantity' => 1, 'unit_price' => 100, 'warehouse_id' => $branch->id],
        ],
    ])->assertCreated();

    expect((int) Invoice::latest('id')->first()->customer_id)->toBe($customer->id);
});

/* ------------------------------------------------------------------ *
 * Cancelling
 * ------------------------------------------------------------------ */

it('returns the goods to the warehouse they left when the sale is cancelled', function () {
    $branch = sale('BRANCH9');
    $product = stocked($branch, 20);

    $this->actingAs($this->user)->postJson('/api/v1/invoices', [
        'items' => [
            ['product_id' => $product->id, 'quantity' => 5, 'unit_price' => 100, 'warehouse_id' => $branch->id],
        ],
    ])->assertCreated();

    expect(onHand($product, $branch))->toBe(15);

    $invoice = Invoice::latest('id')->first();

    $this->actingAs($this->user)
        ->putJson("/api/v1/invoices/{$invoice->id}/status", ['status' => 'cancelled'])
        ->assertOk();

    expect(onHand($product, $branch))->toBe(20);
});

it('reverses the cost entry when the sale is cancelled', function () {
    $branch = sale('BRANCH10');
    $product = stocked($branch, 20, cost: 60);

    $this->actingAs($this->user)->postJson('/api/v1/invoices', [
        'items' => [
            ['product_id' => $product->id, 'quantity' => 5, 'unit_price' => 100, 'warehouse_id' => $branch->id],
        ],
    ])->assertCreated();

    $invoice = Invoice::latest('id')->first();

    $this->actingAs($this->user)
        ->putJson("/api/v1/invoices/{$invoice->id}/status", ['status' => 'cancelled'])
        ->assertOk();

    // Cost and its reversal net to nothing across the cogs postings.
    $net = JournalEntryHeader::where('posting_key', 'like', '%invoice_cogs:'.$invoice->id.'%')
        ->get()
        ->sum(fn ($header) => (float) $header->lines()->sum('debit') - (float) $header->lines()->sum('credit'));

    expect(round($net, 2))->toEqual(0.0);
});

it('does not return the goods twice when cancelled twice', function () {
    $branch = sale('BRANCH11');
    $product = stocked($branch, 20);

    $this->actingAs($this->user)->postJson('/api/v1/invoices', [
        'items' => [
            ['product_id' => $product->id, 'quantity' => 5, 'unit_price' => 100, 'warehouse_id' => $branch->id],
        ],
    ])->assertCreated();

    $invoice = Invoice::latest('id')->first();

    foreach ([1, 2] as $ignored) {
        $this->actingAs($this->user)
            ->putJson("/api/v1/invoices/{$invoice->id}/status", ['status' => 'cancelled'])
            ->assertOk();
    }

    // Back to 20, not 25.
    expect(onHand($product, $branch))->toBe(20);
});
