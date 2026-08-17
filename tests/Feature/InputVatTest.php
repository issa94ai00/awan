<?php

use App\Models\Invoice;
use App\Models\Customer;
use App\Models\JournalEntryHeader;
use App\Models\LedgerAccount;
use App\Models\Product;
use App\Models\PurchaseReceipt;
use App\Models\Role;
use App\Models\Supplier;
use App\Models\User;
use App\Models\Warehouse;
use App\Services\Accounting\LedgerPostingService;

/**
 * The purchase side of value-added tax.
 *
 * Sales collected tax from the start: an invoice credits it to a liability,
 * because it is money held for the state rather than earned. Purchases had no
 * equivalent — a receipt booked the whole amount paid into inventory. That is
 * wrong twice: the goods are carried at more than they cost, so every margin
 * computed from them is understated, and the tax paid to the supplier is
 * recoverable but appears nowhere, so the business hands the state money it
 * was entitled to deduct.
 */
beforeEach(function () {
    $this->admin = User::factory()->create([
        'is_admin' => true,
        'role_id' => Role::firstOrCreate(['name' => 'admin'], ['display_name' => 'admin'])->id,
    ]);

    $this->warehouse = Warehouse::create([
        'name' => 'المستودع الرئيسي',
        'code' => 'WH-MAIN',
        'location' => 'الرياض',
        'status' => 'active',
        'is_active' => true,
        'is_primary' => true,
        'location_type' => Warehouse::TYPE_WAREHOUSE,
    ]);

    $this->product = Product::create([
        'name_ar' => 'مضخة مياه',
        'sku' => 'SKU-PUMP',
        'price' => 100,
        'cost_price' => 40,
    ]);

    $this->supplier = Supplier::create(['name' => 'مورّد المضخات', 'status' => 'active', 'balance' => 0]);

    $this->receive = fn (float $unitPrice, int $quantity, float $tax) => $this->actingAs($this->admin)
        ->postJson('/api/v1/purchase-receipts', [
            'supplier_id' => $this->supplier->id,
            'warehouse_id' => $this->warehouse->id,
            'receipt_date' => now()->toDateString(),
            'tax_amount' => $tax,
            'items' => [
                ['product_id' => $this->product->id, 'quantity' => $quantity, 'unit_price' => $unitPrice],
            ],
        ]);

    $this->balanceOf = fn (string $role) => round(
        (float) LedgerAccount::where('posting_role', $role)->value('balance'),
        2
    );
});

test('tax on a purchase is claimed back, not buried in the cost of the stock', function () {
    ($this->receive)(40, 10, 60)->assertCreated();

    $receipt = PurchaseReceipt::latest('id')->first();
    $entry = JournalEntryHeader::with('lines')->where('posting_key', 'goods_receipt:'.$receipt->id)->first();

    $inventoryId = app(LedgerPostingService::class)->inventoryAccountIdFor($this->warehouse->id);
    $inventoryLine = $entry->lines->firstWhere('account_id', $inventoryId);

    // The goods cost 400; the 60 of tax is a claim on the authority.
    expect(round((float) $inventoryLine->debit, 2))->toBe(400.0);
    expect(($this->balanceOf)('input_vat'))->toBe(60.0);
    // And the supplier is owed the whole document.
    expect(($this->balanceOf)('accounts_payable'))->toBe(460.0);
    expect(round((float) $this->supplier->fresh()->balance, 2))->toBe(460.0);
});

test('a purchase with no tax posts exactly as it always did', function () {
    ($this->receive)(40, 10, 0)->assertCreated();

    $receipt = PurchaseReceipt::latest('id')->first();
    $entry = JournalEntryHeader::with('lines')->where('posting_key', 'goods_receipt:'.$receipt->id)->first();

    // Two lines, no tax line: nothing already in the ledger is contradicted by
    // adding the account.
    expect($entry->lines)->toHaveCount(2);
    expect(($this->balanceOf)('input_vat'))->toBe(0.0);
    expect(($this->balanceOf)('accounts_payable'))->toBe(400.0);
});

test('the entry balances with the tax on it', function () {
    ($this->receive)(25, 4, 15)->assertCreated();

    $receipt = PurchaseReceipt::latest('id')->first();
    $entry = JournalEntryHeader::where('posting_key', 'goods_receipt:'.$receipt->id)->first();

    expect(round((float) $entry->total_debit, 2))->toBe(115.0);
    expect(round((float) $entry->total_credit, 2))->toBe(115.0);
});

/* -------------------------------------------------------------------- *
 * The return
 * -------------------------------------------------------------------- */

test('the return nets what was collected against what was paid', function () {
    $customer = Customer::create(['name' => 'عميل', 'email' => 'c@example.test', 'status' => 'active']);

    $invoice = Invoice::create([
        'invoice_number' => 'INV-0001',
        'customer_id' => $customer->id,
        'status' => 'sent',
        'subtotal' => 1000,
        'tax' => 150,
        'total' => 1150,
        'due_amount' => 1150,
    ]);
    app(LedgerPostingService::class)->postInvoice($invoice);

    ($this->receive)(40, 10, 60)->assertCreated();

    $data = $this->actingAs($this->admin)
        ->getJson('/api/v1/admin/accounting/vat-return?date_from='.now()->startOfMonth()->toDateString()
            .'&date_to='.now()->endOfMonth()->toDateString())
        ->assertOk()
        ->json('data');

    expect((float) $data['output_tax']['amount'])->toBe(150.0);
    expect((float) $data['input_tax']['amount'])->toBe(60.0);
    expect((float) $data['net'])->toBe(90.0);
    expect($data['direction'])->toBe('payable');
});

test('more tax paid than collected is reported as recoverable', function () {
    ($this->receive)(100, 10, 150)->assertCreated();

    $data = $this->actingAs($this->admin)
        ->getJson('/api/v1/admin/accounting/vat-return?date_from='.now()->startOfMonth()->toDateString()
            .'&date_to='.now()->endOfMonth()->toDateString())
        ->assertOk()
        ->json('data');

    expect((float) $data['net'])->toBe(-150.0);
    expect($data['direction'])->toBe('refundable');
});

test('the return says when the documents disagree with the accounts', function () {
    $customer = Customer::create(['name' => 'عميل', 'email' => 'c2@example.test', 'status' => 'active']);

    // Charged on the invoice, never posted: the state is owed money the ledger
    // has no record of, which is exactly what a return must not hide.
    Invoice::create([
        'invoice_number' => 'INV-0002',
        'customer_id' => $customer->id,
        'status' => 'sent',
        'subtotal' => 1000,
        'tax' => 150,
        'total' => 1150,
        'due_amount' => 1150,
    ]);

    $data = $this->actingAs($this->admin)
        ->getJson('/api/v1/admin/accounting/vat-return?date_from='.now()->startOfMonth()->toDateString()
            .'&date_to='.now()->endOfMonth()->toDateString())
        ->assertOk()
        ->json('data');

    expect($data['reconciliation']['output_matches'])->toBeFalse();
    expect((float) $data['reconciliation']['output_difference'])->toBe(150.0);
});

test('the return is refused to a sales account', function () {
    $sales = User::factory()->create([
        'role_id' => Role::firstOrCreate(['name' => 'sells'], ['display_name' => 'sells'])->id,
    ]);

    $this->actingAs($sales)
        ->getJson('/api/v1/admin/accounting/vat-return')
        ->assertForbidden();
});
