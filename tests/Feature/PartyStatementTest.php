<?php

use App\Models\Customer;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\Product;
use App\Models\Role;
use App\Models\Supplier;
use App\Models\User;
use App\Models\Warehouse;
use App\Services\Accounting\LedgerPostingService;

/**
 * The documents behind one party's balance.
 *
 * The aging report answers who owes what. This answers the question that
 * follows, which is the one an actual conversation needs: why. A customer
 * disputing a figure or a supplier reconciling their month end needs the
 * documents in order with a balance running through them — and the general
 * ledger cannot give it, because a receivables line records that 400 moved and
 * only the invoice behind it records whose 400 it was.
 */
beforeEach(function () {
    $this->admin = User::factory()->create([
        'is_admin' => true,
        'role_id' => Role::firstOrCreate(['name' => 'admin'], ['display_name' => 'admin'])->id,
    ]);

    $this->customer = Customer::create([
        'name' => 'شركة النور',
        'email' => 'noor@example.test',
        'status' => 'active',
    ]);

    $this->ledger = app(LedgerPostingService::class);

    $this->invoice = function (float $total, string $date): Invoice {
        $invoice = Invoice::create([
            'invoice_number' => 'INV-'.str_pad((string) (Invoice::count() + 1), 4, '0', STR_PAD_LEFT),
            'customer_id' => $this->customer->id,
            'status' => 'sent',
            'subtotal' => $total,
            'tax' => 0,
            'total' => $total,
            'due_amount' => $total,
        ]);
        $invoice->forceFill(['created_at' => $date])->save();

        return $invoice->refresh();
    };

    $this->statement = fn (string $type, int $id, array $params = []) => $this->actingAs($this->admin)
        ->getJson('/api/v1/admin/accounting/party-statement?'.http_build_query(
            array_merge(['type' => $type, 'party_id' => $id], $params)
        ));
});

test('a customer statement lists the documents with a running balance', function () {
    ($this->invoice)(500, '2026-03-05');

    Payment::create([
        'payment_number' => 'PAY-0001',
        'customer_id' => $this->customer->id,
        'payment_method' => 'cash',
        'amount' => 200,
        'payment_date' => '2026-03-12',
        'status' => 'completed',
    ]);

    $data = ($this->statement)('customer', $this->customer->id, [
        'date_from' => '2026-03-01', 'date_to' => '2026-03-31',
    ])->assertOk()->json('data');

    expect($data['movements'])->toHaveCount(2);
    expect((float) $data['movements'][0]['balance'])->toBe(500.0);
    expect((float) $data['movements'][1]['balance'])->toBe(300.0);
    expect((float) $data['closing_balance'])->toBe(300.0);
});

test('what happened before the period is one opening figure', function () {
    ($this->invoice)(400, '2026-01-10');
    ($this->invoice)(150, '2026-03-04');

    $data = ($this->statement)('customer', $this->customer->id, [
        'date_from' => '2026-03-01', 'date_to' => '2026-03-31',
    ])->assertOk()->json('data');

    expect((float) $data['opening_balance'])->toBe(400.0);
    expect($data['movements'])->toHaveCount(1);
    expect((float) $data['closing_balance'])->toBe(550.0);
});

test('a refund lands on the other side, not as a negative collection', function () {
    ($this->invoice)(300, '2026-03-01');

    Payment::create([
        'payment_number' => 'PAY-REF',
        'customer_id' => $this->customer->id,
        'payment_method' => 'cash',
        'amount' => -75,
        'payment_date' => '2026-03-15',
        'status' => 'refunded',
    ]);

    $data = ($this->statement)('customer', $this->customer->id, [
        'date_from' => '2026-03-01', 'date_to' => '2026-03-31',
    ])->assertOk()->json('data');

    $refund = collect($data['movements'])->firstWhere('number', 'PAY-REF');

    expect((float) $refund['debit'])->toBe(75.0);
    expect((float) $refund['credit'])->toBe(0.0);
    expect((float) $data['closing_balance'])->toBe(375.0);
});

test('the statement says when it does not agree with the party record', function () {
    // An invoice raised without the customer's balance being moved: the
    // documents say one thing and the record another, which is exactly the
    // drift worth surfacing before somebody argues about it.
    ($this->invoice)(250, '2026-03-01');

    $data = ($this->statement)('customer', $this->customer->id, [
        'date_from' => '2026-01-01', 'date_to' => '2026-12-31',
    ])->assertOk()->json('data');

    expect($data['matches_stored_balance'])->toBeFalse();
    expect((float) $data['closing_balance'])->toBe(250.0);
    expect((float) $data['stored_balance'])->toBe(0.0);
});

test('a supplier statement runs from deliveries to payments to returns', function () {
    $warehouse = Warehouse::create([
        'name' => 'المستودع الرئيسي',
        'code' => 'WH-MAIN',
        'location' => 'الرياض',
        'status' => 'active',
        'is_active' => true,
        'is_primary' => true,
        'location_type' => Warehouse::TYPE_WAREHOUSE,
    ]);

    $supplier = Supplier::create(['name' => 'مورّد المضخات', 'status' => 'active', 'balance' => 0]);

    $product = Product::create([
        'name_ar' => 'مضخة',
        'sku' => 'SKU-1',
        'price' => 100,
        'cost_price' => 40,
    ]);

    $this->actingAs($this->admin)->postJson('/api/v1/purchase-receipts', [
        'supplier_id' => $supplier->id,
        'warehouse_id' => $warehouse->id,
        'receipt_date' => now()->toDateString(),
        'items' => [['product_id' => $product->id, 'quantity' => 10, 'unit_price' => 40]],
    ])->assertCreated();

    $this->actingAs($this->admin)->postJson('/api/v1/admin/supplier-payments', [
        'supplier_id' => $supplier->id,
        'payment_method' => 'cash',
        'amount' => 150,
    ])->assertCreated();

    $this->actingAs($this->admin)->postJson('/api/v1/admin/purchase-returns', [
        'supplier_id' => $supplier->id,
        'warehouse_id' => $warehouse->id,
        'items' => [['product_id' => $product->id, 'quantity' => 2]],
    ])->assertCreated();

    $data = ($this->statement)('supplier', $supplier->id, [
        'date_from' => now()->startOfYear()->toDateString(),
        'date_to' => now()->endOfYear()->toDateString(),
    ])->assertOk()->json('data');

    // 400 delivered, 150 paid, 80 returned: 170 still owed, and the supplier
    // record agrees.
    expect((float) $data['closing_balance'])->toBe(-170.0);
    expect((float) $data['stored_balance'])->toBe(-170.0);
    expect($data['matches_stored_balance'])->toBeTrue();
    expect(collect($data['movements'])->pluck('type')->all())
        ->toContain('receipt', 'payment', 'return');
});

test('an unknown party is answered with a plain not-found', function () {
    ($this->statement)('customer', 999999)->assertStatus(404);
});

test('the statement is refused to a sales account', function () {
    $sales = User::factory()->create([
        'role_id' => Role::firstOrCreate(['name' => 'sells'], ['display_name' => 'sells'])->id,
    ]);

    $this->actingAs($sales)
        ->getJson('/api/v1/admin/accounting/party-statement?type=customer&party_id='.$this->customer->id)
        ->assertForbidden();
});
