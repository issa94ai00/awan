<?php

use App\Models\JournalEntryHeader;
use App\Models\Product;
use App\Models\PurchaseReceipt;
use App\Models\Role;
use App\Models\Supplier;
use App\Models\SupplierPayment;
use App\Models\User;
use App\Models\Warehouse;

/**
 * A goods receipt can be paid, in part or in full, as it is booked in — and
 * says what was paid against it and how.
 */
beforeEach(function () {
    $this->admin = User::factory()->create([
        'is_admin' => true,
        'role_id' => Role::firstOrCreate(['name' => 'admin'], ['display_name' => 'admin'])->id,
    ]);

    $this->warehouse = Warehouse::create(['name' => 'الرئيسي', 'code' => 'WH-RP', 'is_active' => true, 'is_primary' => true]);
    $this->product = Product::create(['name_ar' => 'خلاط', 'sku' => 'MIX', 'price' => 60, 'cost_price' => 30]);
    $this->supplier = Supplier::create(['name' => 'مورّد', 'status' => 'active', 'balance' => 0]);

    $this->receipt = fn (array $extra = []) => array_merge([
        'supplier_id' => $this->supplier->id,
        'warehouse_id' => $this->warehouse->id,
        'receipt_date' => now()->toDateString(),
        'tax_amount' => 20,
        'items' => [['product_id' => $this->product->id, 'quantity' => 10, 'unit_price' => 30]],
    ], $extra);
});

test('a receipt paid in part records the payment and leaves the rest owed', function () {
    $data = $this->actingAs($this->admin)
        ->postJson('/api/v1/purchase-receipts', ($this->receipt)(['paid_amount' => 120, 'payment_method' => 'cash', 'payment_reference' => 'R-1']))
        ->assertCreated()
        ->json('data');

    // 10 × 30 + 20 tax = 320, of which 120 paid.
    expect((float) $data['total_amount'])->toBe(320.0)
        ->and((float) $data['paid_amount'])->toBe(120.0)
        ->and((float) $data['due_amount'])->toBe(200.0)
        ->and($data['payment_methods'])->toBe(['cash']);

    $payment = SupplierPayment::firstWhere('purchase_receipt_id', $data['id']);
    expect($payment->payment_method)->toBe('cash')
        ->and($payment->reference)->toBe('R-1')
        ->and(JournalEntryHeader::where('posting_key', $payment->postingKey())->exists())->toBeTrue();

    expect(round((float) $this->supplier->fresh()->balance, 2))->toBe(200.0);

    // The list says the same.
    $row = collect($this->getJson('/api/v1/purchase-receipts')->json('data.receipts'))->firstWhere('id', $data['id']);
    expect((float) $row['paid_amount'])->toBe(120.0)->and((float) $row['due_amount'])->toBe(200.0);
});

test('a receipt with nothing paid is owed in full', function () {
    $data = $this->actingAs($this->admin)
        ->postJson('/api/v1/purchase-receipts', ($this->receipt)())
        ->assertCreated()
        ->json('data');

    expect((float) $data['paid_amount'])->toBe(0.0)
        ->and((float) $data['due_amount'])->toBe(320.0)
        ->and(SupplierPayment::count())->toBe(0);
});

test('a paid amount needs a payment method, and cannot exceed the receipt', function () {
    $this->actingAs($this->admin)
        ->postJson('/api/v1/purchase-receipts', ($this->receipt)(['paid_amount' => 50]))
        ->assertStatus(422)
        ->assertJsonValidationErrors('payment_method');

    $this->postJson('/api/v1/purchase-receipts', ($this->receipt)(['paid_amount' => 400, 'payment_method' => 'cash']))
        ->assertStatus(422);

    expect(PurchaseReceipt::count())->toBe(0);
});

test('only an admin can pay a supplier through a receipt', function () {
    $clerk = User::factory()->create(['is_admin' => false]);

    $this->actingAs($clerk)
        ->postJson('/api/v1/purchase-receipts', ($this->receipt)(['paid_amount' => 50, 'payment_method' => 'cash']))
        ->assertForbidden();

    expect(PurchaseReceipt::count())->toBe(0);
});

test('the rest is paid later against the receipt, and not past it', function () {
    $id = $this->actingAs($this->admin)
        ->postJson('/api/v1/purchase-receipts', ($this->receipt)(['paid_amount' => 120, 'payment_method' => 'cash']))
        ->json('data.id');

    $this->postJson('/api/v1/admin/supplier-payments', [
        'supplier_id' => $this->supplier->id,
        'purchase_receipt_id' => $id,
        'payment_method' => 'bank_transfer',
        'amount' => 250,
    ])->assertStatus(422);

    $this->postJson('/api/v1/admin/supplier-payments', [
        'supplier_id' => $this->supplier->id,
        'purchase_receipt_id' => $id,
        'payment_method' => 'bank_transfer',
        'amount' => 200,
    ])->assertCreated();

    $data = $this->getJson('/api/v1/purchase-receipts/'.$id)->json('data');
    expect((float) $data['due_amount'])->toBe(0.0)
        ->and($data['payment_methods'])->toBe(['cash', 'bank_transfer']);
});

test('a payment cannot be put on another supplier\'s receipt', function () {
    $id = $this->actingAs($this->admin)->postJson('/api/v1/purchase-receipts', ($this->receipt)())->json('data.id');
    $other = Supplier::create(['name' => 'آخر', 'status' => 'active', 'balance' => 0]);

    $this->postJson('/api/v1/admin/supplier-payments', [
        'supplier_id' => $other->id,
        'purchase_receipt_id' => $id,
        'payment_method' => 'cash',
        'amount' => 10,
    ])->assertStatus(422);
});
