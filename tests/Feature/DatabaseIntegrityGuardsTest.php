<?php

use App\Models\CreditNote;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\Product;
use App\Models\ProductUnit;
use App\Models\ProductVariant;
use App\Models\PurchaseOrder;
use App\Models\Supplier;
use App\Models\User;
use App\Models\Warehouse;
use App\Services\Inventory\InventoryService;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Validation\ValidationException;

/**
 * Guards added by the 2026-11-16 database audit: deleting a master record
 * with history, cancelling an invoice twice over, walk-in payments, barcode
 * uniqueness and the customer balance rebuild.
 */
beforeEach(function () {
    $this->user = User::factory()->admin()->create();
    $this->warehouse = Warehouse::create(['name' => 'الرئيسي', 'code' => 'WH-GUARD', 'is_active' => true]);
    $this->product = Product::factory()->create(['price' => 100, 'cost_price' => 40]);

    app(InventoryService::class)->receive($this->product->id, 50, $this->warehouse->id, [
        'key' => uniqid('recv-', true), 'unit_cost' => 40,
    ]);

    $this->sell = fn (array $extra = []) => $this->actingAs($this->user)->postJson('/api/v1/invoices', array_merge([
        'items' => [['product_id' => $this->product->id, 'quantity' => 1, 'unit_price' => 100, 'warehouse_id' => $this->warehouse->id]],
    ], $extra))->assertCreated()->json('data.id');
});

it('refuses to delete a supplier that has purchase orders', function () {
    $supplier = Supplier::create(['name' => 'مورد', 'status' => 'active']);
    PurchaseOrder::create(['supplier_id' => $supplier->id, 'order_number' => 'PO-GUARD-1', 'status' => 'pending', 'total' => 0]);

    $this->actingAs($this->user)->deleteJson("/api/v1/admin/suppliers/{$supplier->id}")
        ->assertStatus(422);

    expect(Supplier::find($supplier->id))->not->toBeNull()
        ->and(PurchaseOrder::where('supplier_id', $supplier->id)->count())->toBe(1);
});

it('still deletes a supplier with no documents', function () {
    $supplier = Supplier::create(['name' => 'مورد فارغ', 'status' => 'active']);

    $this->actingAs($this->user)->deleteJson("/api/v1/admin/suppliers/{$supplier->id}")->assertOk();

    expect(Supplier::find($supplier->id))->toBeNull();
});

it('refuses to delete a customer or warehouse that has history', function () {
    $customer = Customer::create(['name' => 'زبون', 'status' => 'active']);
    ($this->sell)(['customer_id' => $customer->id]);

    expect(fn () => $customer->delete())->toThrow(ValidationException::class)
        ->and(fn () => $this->warehouse->delete())->toThrow(ValidationException::class);
});

it('refuses to cancel an invoice a credit note already reversed', function () {
    $customer = Customer::create(['name' => 'زبون', 'status' => 'active']);
    $invoiceId = ($this->sell)(['customer_id' => $customer->id]);

    CreditNote::create([
        'credit_note_number' => 'CN-GUARD-1', 'customer_id' => $customer->id, 'invoice_id' => $invoiceId,
        'issue_date' => now()->toDateString(), 'subtotal' => 100, 'tax' => 0, 'total' => 100, 'status' => 'applied',
    ]);

    $this->actingAs($this->user)->putJson("/api/v1/invoices/{$invoiceId}/status", ['status' => 'cancelled'])
        ->assertStatus(422);

    expect(Invoice::find($invoiceId)->status)->not->toBe(Invoice::STATUS_CANCELLED);
});

it('clears what is due when an invoice is cancelled', function () {
    $customer = Customer::create(['name' => 'زبون', 'status' => 'active']);
    $invoiceId = ($this->sell)(['customer_id' => $customer->id]);

    $this->actingAs($this->user)->putJson("/api/v1/invoices/{$invoiceId}/status", ['status' => 'cancelled'])
        ->assertOk();

    expect((float) Invoice::find($invoiceId)->due_amount)->toEqual(0.0)
        ->and((float) $customer->fresh()->balance)->toEqual(0.0);

    Artisan::call('customers:rebuild-balance', ['--dry-run' => true]);
    expect(Artisan::output())->not->toContain((string) $customer->id.' ');
});

it('records the payment of a walk-in sale', function () {
    $invoiceId = ($this->sell)(['paid_amount' => 100, 'payment_method' => 'cash']);

    $payment = Payment::where('invoice_id', $invoiceId)->first();

    expect($payment)->not->toBeNull()
        ->and($payment->customer_id)->toBeNull()
        ->and((float) $payment->amount)->toEqual(100.0);
});

it('keeps barcodes unique across products, variants and units', function () {
    $this->product->update(['barcode' => '6291234567890']);

    $other = Product::factory()->create();

    expect(fn () => $other->update(['barcode' => '6291234567890']))->toThrow(ValidationException::class)
        ->and(fn () => ProductVariant::create(['product_id' => $other->id, 'sku' => 'V-GUARD', 'barcode' => '6291234567890']))
        ->toThrow(ValidationException::class)
        ->and(fn () => ProductUnit::create(['product_id' => $other->id, 'name' => 'طرد', 'base_unit_multiplier' => 6, 'barcode' => '6291234567890']))
        ->toThrow(ValidationException::class);

    $other->update(['barcode' => '  ']);
    expect($other->fresh()->barcode)->toBeNull();
});

it('warns when an invoice line sells below cost, and stays quiet otherwise', function () {
    $below = $this->actingAs($this->user)->postJson('/api/v1/invoices', [
        'items' => [['product_id' => $this->product->id, 'quantity' => 2, 'unit_price' => 30, 'warehouse_id' => $this->warehouse->id]],
    ])->assertCreated();

    expect($below->json('warnings'))->toHaveCount(1)
        ->and($below->json('warnings.0'))->toContain('أقل من التكلفة');

    $fine = $this->actingAs($this->user)->postJson('/api/v1/invoices', [
        'items' => [['product_id' => $this->product->id, 'quantity' => 1, 'unit_price' => 100, 'warehouse_id' => $this->warehouse->id]],
    ])->assertCreated();

    expect($fine->json('warnings'))->toBeNull();
});

it('compares a pack price per base unit against cost', function () {
    $lines = [(object) ['product_id' => $this->product->id, 'product_variant_id' => null, 'quantity' => 1, 'unit_price' => 200, 'unit_multiplier' => 6, 'discount' => 0]];

    // 200 for a pack of 6 is 33.33 a piece, under the 40 it cost.
    expect(\App\Support\BelowCostWarnings::forLines($lines))->toHaveCount(1);

    $lines[0]->unit_price = 300;
    expect(\App\Support\BelowCostWarnings::forLines($lines))->toBe([]);
});
