<?php

use App\Models\JournalEntryHeader;
use App\Models\LedgerAccount;
use App\Models\Product;
use App\Models\Role;
use App\Models\Supplier;
use App\Models\SupplierPayment;
use App\Models\User;
use App\Models\Warehouse;
use App\Models\WarehouseInventory;
use App\Services\Accounting\LedgerPostingService;
use App\Services\Inventory\InventoryService;

/**
 * The three places where the operational records and the books used to part
 * company, and now must not.
 *
 *  - **Payables were never settled.** Every purchase receipt credited accounts
 *    payable and nothing ever debited it back, because paying a supplier had
 *    nowhere to be recorded: `payments` carries a customer and settles
 *    receivables. The liability therefore only grew, for the life of the
 *    installation, however much had actually been paid.
 *
 *  - **Stock counts never reached the ledger.** An adjustment moved the
 *    warehouse alone, so the inventory asset kept describing goods that had
 *    been counted away, and the loss appeared as a cost nowhere at all.
 *
 *  - **Posted entries could be rewritten.** The journal endpoint deleted the
 *    lines of a posted entry, unwound their effect on the balances and wrote
 *    new ones under the same entry number — so two trial balances printed
 *    either side of an edit disagreed and nothing could explain why.
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

    $this->supplier = Supplier::create([
        'name' => 'مورّد المضخات',
        'status' => 'active',
        'balance' => 0,
    ]);

    /** Movement on an account within an entry, on its normal side. */
    $this->movementOn = function (JournalEntryHeader $entry, string $role): float {
        $accountId = LedgerAccount::where('posting_role', $role)->value('id');
        $line = $entry->lines->firstWhere('account_id', $accountId);

        return $line ? round((float) $line->debit - (float) $line->credit, 2) : 0.0;
    };

    /** Puts stock on the shelf with a FIFO layer behind it, as a receipt would. */
    $this->stockUp = function (int $quantity, float $unitCost) {
        app(InventoryService::class)->receive(
            $this->product->id,
            $quantity,
            $this->warehouse->id,
            [
                'key' => 'test_opening:'.$this->product->id.':'.uniqid(),
                'unit_cost' => $unitCost,
                'source' => 'test',
            ]
        );
    };
});

/* -------------------------------------------------------------------- *
 * Supplier payments settle the payable
 * -------------------------------------------------------------------- */

test('paying a supplier debits accounts payable and credits the till', function () {
    $payment = SupplierPayment::create([
        'payment_number' => 'SPY-000001',
        'supplier_id' => $this->supplier->id,
        'payment_method' => SupplierPayment::METHOD_CASH,
        'amount' => 750,
        'payment_date' => now()->toDateString(),
    ]);

    $entry = app(LedgerPostingService::class)->postSupplierPayment($payment);

    expect($entry)->not->toBeNull();
    expect(round((float) $entry->total_debit, 2))->toBe(750.0);
    expect(round((float) $entry->total_credit, 2))->toBe(750.0);
    // Debit shrinks the liability, credit takes the money out of the till.
    expect(($this->movementOn)($entry, 'accounts_payable'))->toBe(750.0);
    expect(($this->movementOn)($entry, 'cash'))->toBe(-750.0);
});

test('a bank payment leaves the till alone', function () {
    $payment = SupplierPayment::create([
        'payment_number' => 'SPY-000002',
        'supplier_id' => $this->supplier->id,
        'payment_method' => SupplierPayment::METHOD_BANK_TRANSFER,
        'amount' => 300,
        'payment_date' => now()->toDateString(),
    ]);

    $entry = app(LedgerPostingService::class)->postSupplierPayment($payment);

    expect(($this->movementOn)($entry, 'bank'))->toBe(-300.0);
    expect(($this->movementOn)($entry, 'cash'))->toBe(0.0);
});

test('the same payment cannot be posted twice', function () {
    $payment = SupplierPayment::create([
        'payment_number' => 'SPY-000003',
        'supplier_id' => $this->supplier->id,
        'payment_method' => SupplierPayment::METHOD_CASH,
        'amount' => 100,
        'payment_date' => now()->toDateString(),
    ]);

    $ledger = app(LedgerPostingService::class);
    $first = $ledger->postSupplierPayment($payment);
    $second = $ledger->postSupplierPayment($payment);

    expect($second->id)->toBe($first->id);
    expect(JournalEntryHeader::where('posting_key', $payment->postingKey())->count())->toBe(1);
});

test('receiving goods and then paying for them leaves payables flat', function () {
    $payableId = LedgerAccount::where('posting_role', 'accounts_payable')->value('id');
    $opening = (float) LedgerAccount::find($payableId)->balance;

    $this->actingAs($this->admin)->postJson('/api/v1/purchase-receipts', [
        'supplier_id' => $this->supplier->id,
        'warehouse_id' => $this->warehouse->id,
        'receipt_date' => now()->toDateString(),
        'items' => [
            ['product_id' => $this->product->id, 'quantity' => 10, 'unit_price' => 40],
        ],
    ])->assertCreated();

    expect(round((float) LedgerAccount::find($payableId)->balance - $opening, 2))->toBe(400.0);
    expect(round((float) $this->supplier->fresh()->balance, 2))->toBe(400.0);

    $this->actingAs($this->admin)->postJson('/api/v1/admin/supplier-payments', [
        'supplier_id' => $this->supplier->id,
        'payment_method' => 'bank_transfer',
        'amount' => 400,
    ])->assertCreated();

    // Bought and paid: the supplier is owed nothing, in the books as well as
    // on their record.
    expect(round((float) LedgerAccount::find($payableId)->balance - $opening, 2))->toBe(0.0);
    expect(round((float) $this->supplier->fresh()->balance, 2))->toBe(0.0);
});

test('cancelling a supplier payment reverses its entry and re-opens the debt', function () {
    $this->supplier->updateBalance(500);

    $created = $this->actingAs($this->admin)->postJson('/api/v1/admin/supplier-payments', [
        'supplier_id' => $this->supplier->id,
        'payment_method' => 'cash',
        'amount' => 500,
    ])->assertCreated()->json('data');

    expect(round((float) $this->supplier->fresh()->balance, 2))->toBe(0.0);

    $this->actingAs($this->admin)
        ->deleteJson('/api/v1/admin/supplier-payments/'.$created['id'])
        ->assertOk();

    $original = JournalEntryHeader::where('posting_key', 'supplier_payment:'.$created['id'])->first();

    // The original stays and a mirror entry cancels it: history is kept, the
    // balances go back.
    expect($original->status)->toBe('reversed');
    expect(JournalEntryHeader::where('reversal_of_id', $original->id)->exists())->toBeTrue();
    expect(round((float) $this->supplier->fresh()->balance, 2))->toBe(500.0);
});

test('a payment sent without a date is dated today rather than rejected', function () {
    $created = $this->actingAs($this->admin)->postJson('/api/v1/admin/supplier-payments', [
        'supplier_id' => $this->supplier->id,
        'payment_method' => 'cash',
        'amount' => 120,
        // The date picker sends an explicit null when it is left empty, and
        // the column will not take one.
        'payment_date' => null,
    ])->assertCreated()->json('data');

    expect(SupplierPayment::find($created['id'])->payment_date->toDateString())
        ->toBe(now()->toDateString());
});

test('a supplier payment is refused to a sales account', function () {
    $sales = User::factory()->create([
        'role_id' => Role::firstOrCreate(['name' => 'sells'], ['display_name' => 'sells'])->id,
    ]);

    $this->actingAs($sales)->postJson('/api/v1/admin/supplier-payments', [
        'supplier_id' => $this->supplier->id,
        'payment_method' => 'cash',
        'amount' => 50,
    ])->assertForbidden();

    expect(SupplierPayment::count())->toBe(0);
});

/* -------------------------------------------------------------------- *
 * Stock counts reach the ledger
 * -------------------------------------------------------------------- */

test('a shortage found by a count is booked as a cost against inventory', function () {
    ($this->stockUp)(10, 40);

    $inventoryAccountId = app(LedgerPostingService::class)->inventoryAccountIdFor($this->warehouse->id);
    $shrinkageId = LedgerAccount::where('posting_role', 'inventory_adjustment')->value('id');

    $movement = app(InventoryService::class)->adjust(
        $this->product->id,
        -3,
        $this->warehouse->id,
        ['key' => 'count:1', 'reason' => 'جرد دوري']
    );

    $entry = JournalEntryHeader::with('lines')
        ->where('posting_key', 'stock_adjustment:'.$movement->id)
        ->first();

    expect($entry)->not->toBeNull();

    $shrinkage = $entry->lines->firstWhere('account_id', $shrinkageId);
    $inventory = $entry->lines->firstWhere('account_id', $inventoryAccountId);

    // Three units at the 40 they were bought for: the cost comes off the FIFO
    // layer, not off whatever the product record happens to say today.
    expect(round((float) $shrinkage->debit, 2))->toBe(120.0);
    expect(round((float) $inventory->credit, 2))->toBe(120.0);

    // And the shelf agrees with what was posted.
    expect((int) WarehouseInventory::where('product_id', $this->product->id)
        ->where('warehouse_id', $this->warehouse->id)
        ->value('quantity'))->toBe(7);
});

test('stock found by a count goes back onto the balance sheet', function () {
    ($this->stockUp)(5, 40);

    $inventoryAccountId = app(LedgerPostingService::class)->inventoryAccountIdFor($this->warehouse->id);

    $movement = app(InventoryService::class)->adjust(
        $this->product->id,
        2,
        $this->warehouse->id,
        ['key' => 'count:2', 'reason' => 'جرد دوري', 'unit_cost' => 40]
    );

    $entry = JournalEntryHeader::with('lines')
        ->where('posting_key', 'stock_adjustment:'.$movement->id)
        ->first();

    $inventory = $entry->lines->firstWhere('account_id', $inventoryAccountId);

    expect(round((float) $inventory->debit, 2))->toBe(80.0);
    expect(round((float) $entry->total_debit, 2))->toBe(80.0);
});

test('a repeated adjustment posts its value once', function () {
    ($this->stockUp)(10, 40);

    $inventory = app(InventoryService::class);
    $options = ['key' => 'count:repeat', 'reason' => 'جرد دوري'];

    $first = $inventory->adjust($this->product->id, -2, $this->warehouse->id, $options);
    $second = $inventory->adjust($this->product->id, -2, $this->warehouse->id, $options);

    expect($second->id)->toBe($first->id);
    expect(JournalEntryHeader::where('posting_key', 'stock_adjustment:'.$first->id)->count())->toBe(1);
});

test('an adjustment on stock that cost nothing writes no entry', function () {
    ($this->stockUp)(4, 0);

    $movement = app(InventoryService::class)->adjust(
        $this->product->id,
        -1,
        $this->warehouse->id,
        ['key' => 'count:free', 'reason' => 'جرد دوري']
    );

    // A zero entry moves no balance and only adds noise to the journal.
    expect(JournalEntryHeader::where('posting_key', 'stock_adjustment:'.$movement->id)->exists())->toBeFalse();
});

/* -------------------------------------------------------------------- *
 * A posted entry is not rewritten
 * -------------------------------------------------------------------- */

function manualEntry(array $overrides = []): array
{
    $cash = LedgerAccount::where('posting_role', 'cash')->value('id');
    $capital = LedgerAccount::where('posting_role', 'capital')->value('id');

    return array_merge([
        'entry_date' => now()->toDateString(),
        'description' => 'قيد افتتاحي',
        'lines' => [
            ['ledger_account_id' => $cash, 'debit' => 1000, 'credit' => 0],
            ['ledger_account_id' => $capital, 'debit' => 0, 'credit' => 1000],
        ],
    ], $overrides);
}

test('a posted entry cannot be edited', function () {
    $entry = $this->actingAs($this->admin)
        ->postJson('/api/v1/admin/accounting/journal-entries', manualEntry())
        ->assertCreated()->json('data');

    $this->actingAs($this->admin)
        ->putJson('/api/v1/admin/accounting/journal-entries/'.$entry['id'], manualEntry([
            'description' => 'مبلغ آخر تماماً',
            'lines' => [
                ['ledger_account_id' => $entry['lines'][0]['account_id'], 'debit' => 999999, 'credit' => 0],
                ['ledger_account_id' => $entry['lines'][1]['account_id'], 'debit' => 0, 'credit' => 999999],
            ],
        ]))
        ->assertStatus(422);

    $stored = JournalEntryHeader::with('lines')->find($entry['id']);

    expect(round((float) $stored->total_debit, 2))->toBe(1000.0);
    expect($stored->lines)->toHaveCount(2);
});

test('a posted entry cannot be deleted', function () {
    $entry = $this->actingAs($this->admin)
        ->postJson('/api/v1/admin/accounting/journal-entries', manualEntry())
        ->assertCreated()->json('data');

    $this->actingAs($this->admin)
        ->deleteJson('/api/v1/admin/accounting/journal-entries/'.$entry['id'])
        ->assertStatus(422);

    expect(JournalEntryHeader::find($entry['id']))->not->toBeNull();
});

test('reversing an entry cancels its effect and keeps both sides', function () {
    $cashAccount = LedgerAccount::where('posting_role', 'cash')->first();
    $opening = round((float) $cashAccount->balance, 2);

    $entry = $this->actingAs($this->admin)
        ->postJson('/api/v1/admin/accounting/journal-entries', manualEntry())
        ->assertCreated()->json('data');

    expect(round((float) $cashAccount->fresh()->balance, 2))->toBe($opening + 1000.0);

    $this->actingAs($this->admin)
        ->postJson('/api/v1/admin/accounting/journal-entries/'.$entry['id'].'/reverse')
        ->assertCreated();

    $original = JournalEntryHeader::find($entry['id']);

    expect(round((float) $cashAccount->fresh()->balance, 2))->toBe($opening);
    expect($original->status)->toBe('reversed');
    expect(JournalEntryHeader::where('reversal_of_id', $original->id)->count())->toBe(1);
});

test('an entry is not reversed twice', function () {
    $entry = $this->actingAs($this->admin)
        ->postJson('/api/v1/admin/accounting/journal-entries', manualEntry())
        ->assertCreated()->json('data');

    $this->actingAs($this->admin)
        ->postJson('/api/v1/admin/accounting/journal-entries/'.$entry['id'].'/reverse')
        ->assertCreated();

    $this->actingAs($this->admin)
        ->postJson('/api/v1/admin/accounting/journal-entries/'.$entry['id'].'/reverse')
        ->assertStatus(422);

    expect(JournalEntryHeader::where('reversal_of_id', $entry['id'])->count())->toBe(1);
});
