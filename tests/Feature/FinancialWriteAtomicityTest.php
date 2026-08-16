<?php

use App\Models\Customer;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\Product;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderItem;
use App\Models\Quote;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * A financial document and its lines have to land together.
 *
 * These were written as separate statements with no transaction around them.
 * The header stored a subtotal and a total computed from the full set of lines,
 * so a failure part-way through the loop produced an invoice that disagreed
 * with itself — and a receivable posted for an amount nothing on the document
 * added up to.
 *
 * The edit paths were worse than inconsistent, they were destructive: they
 * clear every line and write them again, so a failure between the delete and
 * the last insert removed lines the document had a moment earlier, with nothing
 * to restore them from.
 *
 * Rather than simulating a mid-loop crash, these drive the real endpoints and
 * then assert the two halves agree — which is the property the transaction
 * exists to preserve, and which fails loudly if someone unwraps it.
 */

function atomicityUser(): User
{
    return User::factory()->admin()->create();
}

function atomicityProduct(float $price = 100): Product
{
    return Product::factory()->create(['price' => $price, 'cost_price' => 60]);
}

it('writes an invoice and its lines together', function () {
    $user = atomicityUser();
    $a = atomicityProduct(100);
    $b = atomicityProduct(250);

    $this->actingAs($user)->postJson('/api/v1/invoices', [
        'items' => [
            ['product_id' => $a->id, 'quantity' => 2, 'unit_price' => 100],
            ['product_id' => $b->id, 'quantity' => 1, 'unit_price' => 250],
        ],
    ])->assertCreated();

    $invoice = Invoice::latest('id')->first();

    expect($invoice->items()->count())->toBe(2)
        // The header's own figure must equal what its lines actually say.
        ->and((float) $invoice->subtotal)->toEqual(450.0);
});

it('leaves no invoice behind when the lines cannot be written', function () {
    $user = atomicityUser();

    $before = Invoice::count();

    // A product id that does not exist: validation refuses it, and nothing at
    // all should have been created on the way to that refusal.
    $this->actingAs($user)->postJson('/api/v1/invoices', [
        'items' => [
            ['product_id' => 999999, 'quantity' => 1, 'unit_price' => 100],
        ],
    ])->assertStatus(422);

    expect(Invoice::count())->toBe($before)
        ->and(InvoiceItem::count())->toBe(0);
});

it('does not strand a purchase order without its lines', function () {
    $user = atomicityUser();
    $product = atomicityProduct();

    $supplierId = DB::table('suppliers')->insertGetId([
        'name' => 'مورد', 'created_at' => now(), 'updated_at' => now(),
    ]);

    $this->actingAs($user)->postJson('/api/v1/admin/purchase-orders', [
        'supplier_id' => $supplierId,
        'items' => [
            ['product_id' => $product->id, 'quantity' => 5, 'unit_price' => 60],
        ],
    ])->assertCreated();

    $order = PurchaseOrder::latest('id')->first();

    expect($order->items()->count())->toBe(1)
        ->and((float) $order->subtotal)->toEqual(300.0);
});

/**
 * The dangerous one: editing clears the lines before rewriting them, so an
 * interrupted edit used to destroy data rather than merely skew a total.
 */
it('keeps a purchase order whole across an edit', function () {
    $user = atomicityUser();
    $product = atomicityProduct();

    $supplierId = DB::table('suppliers')->insertGetId([
        'name' => 'مورد', 'created_at' => now(), 'updated_at' => now(),
    ]);

    $this->actingAs($user)->postJson('/api/v1/admin/purchase-orders', [
        'supplier_id' => $supplierId,
        'items' => [['product_id' => $product->id, 'quantity' => 5, 'unit_price' => 60]],
    ])->assertCreated();

    $order = PurchaseOrder::latest('id')->first();

    $this->actingAs($user)->putJson("/api/v1/admin/purchase-orders/{$order->id}", [
        'supplier_id' => $supplierId,
        // The update endpoint requires the status explicitly; it rewrites the
        // whole order rather than patching it.
        'status' => 'pending',
        'items' => [
            ['product_id' => $product->id, 'quantity' => 8, 'unit_price' => 55],
        ],
    ])->assertOk();

    $order->refresh();

    // Rewritten, not merely emptied.
    expect($order->items()->count())->toBe(1)
        ->and((int) $order->items()->first()->quantity)->toBe(8)
        ->and((float) $order->subtotal)->toEqual(440.0);
});

it('writes a quote and its lines together', function () {
    $user = atomicityUser();
    $product = atomicityProduct();
    $customer = Customer::create(['name' => 'عميل', 'phone' => '0999000111']);

    $this->actingAs($user)->postJson('/api/v1/quotes', [
        'customer_id' => $customer->id,
        'quote_date' => now()->toDateString(),
        'valid_until' => now()->addDays(14)->toDateString(),
        'items' => [
            ['product_id' => $product->id, 'quantity' => 3, 'unit_price' => 100],
        ],
    ])->assertCreated();

    $quote = Quote::latest('id')->first();

    expect($quote->items()->count())->toBe(1)
        ->and((float) $quote->subtotal)->toEqual(300.0);
});

/**
 * Guards the wrapping itself. If someone removes a `DB::transaction` from these
 * paths, the header and its lines stop being written together — and no
 * behavioural test would notice until data was already lost.
 */
it('wraps every document-with-lines write in a transaction', function () {
    $controllers = [
        'InvoiceController' => 2,
        'PurchaseOrderController' => 2,
        'QuoteController' => 3,
    ];

    foreach ($controllers as $name => $expected) {
        $source = file_get_contents(app_path("Http/Controllers/Api/{$name}.php"));

        expect(substr_count($source, 'DB::transaction'))->toBeGreaterThanOrEqual(
            $expected,
            "{$name} lost a transaction around a write that creates a document and its lines."
        );
    }
});
