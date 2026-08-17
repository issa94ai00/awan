<?php

use App\Models\AccountingPeriod;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\JournalEntryHeader;
use App\Models\LedgerAccount;
use App\Models\Role;
use App\Models\User;
use App\Services\Accounting\LedgerPostingService;

/**
 * What stops a reported month from moving.
 *
 * Nothing used to distinguish a date that had been reported on from any other.
 * An invoice backdated by a typo, a stock count entered late, a journal entry
 * typed by hand — any of them could land in last quarter, and every statement
 * already printed and sent for that quarter quietly stopped matching the books
 * it came from. The trial balance still balanced, so there was no way to
 * notice: a backdated entry is a perfectly valid entry, just not one anybody
 * is still allowed to make.
 *
 * The lock is deliberately inert until used. A system with years of history
 * and no periods defined behaves exactly as it did before.
 */
beforeEach(function () {
    $this->admin = User::factory()->create([
        'is_admin' => true,
        'role_id' => Role::firstOrCreate(['name' => 'admin'], ['display_name' => 'admin'])->id,
    ]);

    $this->customer = Customer::create([
        'name' => 'عميل التجربة',
        'email' => 'client@example.test',
        'status' => 'active',
    ]);

    $this->closedPeriod = fn (string $start, string $end): AccountingPeriod => AccountingPeriod::create([
        'name' => 'فترة '.$start,
        'start_date' => $start,
        'end_date' => $end,
        'status' => AccountingPeriod::STATUS_CLOSED,
        'closed_at' => now(),
    ]);

    // An invoice's entry is dated by `created_at`, which Eloquent stamps
    // itself, so the date is written afterwards rather than passed to create().
    $this->invoiceDated = function (string $date): Invoice {
        $invoice = Invoice::create([
            'invoice_number' => 'INV-'.uniqid(),
            'customer_id' => $this->customer->id,
            'status' => 'sent',
            'subtotal' => 100,
            'tax' => 0,
            'total' => 100,
            'due_amount' => 100,
        ]);

        $invoice->forceFill(['created_at' => $date])->save();

        return $invoice->refresh();
    };

    $this->manualEntry = fn (string $date): array => [
        'entry_date' => $date,
        'description' => 'قيد يدوي',
        'lines' => [
            ['ledger_account_id' => LedgerAccount::where('posting_role', 'cash')->value('id'), 'debit' => 50, 'credit' => 0],
            ['ledger_account_id' => LedgerAccount::where('posting_role', 'capital')->value('id'), 'debit' => 0, 'credit' => 50],
        ],
    ];
});

test('nothing is refused while no period has been closed', function () {
    $entry = app(LedgerPostingService::class)->postInvoice(($this->invoiceDated)('2026-01-15'));

    expect($entry)->not->toBeNull();
});

test('a document dated into a closed period is refused', function () {
    ($this->closedPeriod)('2026-01-01', '2026-01-31');

    expect(fn () => app(LedgerPostingService::class)->postInvoice(($this->invoiceDated)('2026-01-15')))
        ->toThrow(App\Exceptions\ClosedPeriodException::class);

    expect(JournalEntryHeader::count())->toBe(0);
});

test('the same document posts fine one day outside the closed period', function () {
    ($this->closedPeriod)('2026-01-01', '2026-01-31');

    $entry = app(LedgerPostingService::class)->postInvoice(($this->invoiceDated)('2026-02-01'));

    expect($entry)->not->toBeNull();
    expect($entry->entry_date->toDateString())->toBe('2026-02-01');
});

test('a hand-typed entry cannot slip into a closed period either', function () {
    ($this->closedPeriod)('2026-01-01', '2026-01-31');

    $this->actingAs($this->admin)
        ->postJson('/api/v1/admin/accounting/journal-entries', ($this->manualEntry)('2026-01-20'))
        ->assertStatus(422);

    expect(JournalEntryHeader::count())->toBe(0);
});

test('re-firing an event whose entry already exists stays a no-op after closing', function () {
    $invoice = ($this->invoiceDated)('2026-01-15');
    $ledger = app(LedgerPostingService::class);

    $first = $ledger->postInvoice($invoice);
    ($this->closedPeriod)('2026-01-01', '2026-01-31');

    // Posting is idempotent, and closing the period must not turn a harmless
    // replay — a re-saved document, a repeated webhook — into a failure on
    // history that is already correct.
    $again = $ledger->postInvoice($invoice);

    expect($again->id)->toBe($first->id);
});

test('reopening a period lets the entry through again', function () {
    $period = ($this->closedPeriod)('2026-01-01', '2026-01-31');

    $this->actingAs($this->admin)
        ->postJson('/api/v1/admin/accounting/journal-entries', ($this->manualEntry)('2026-01-20'))
        ->assertStatus(422);

    $this->actingAs($this->admin)
        ->postJson('/api/v1/admin/accounting/periods/'.$period->id.'/reopen')
        ->assertOk();

    $this->actingAs($this->admin)
        ->postJson('/api/v1/admin/accounting/journal-entries', ($this->manualEntry)('2026-01-20'))
        ->assertCreated();

    // Who reopened it is recorded: the trail is the point of allowing it.
    expect($period->fresh()->reopened_by)->toBe($this->admin->id);
});

test('periods may not overlap', function () {
    $this->actingAs($this->admin)->postJson('/api/v1/admin/accounting/periods', [
        'name' => 'يناير',
        'start_date' => '2026-01-01',
        'end_date' => '2026-01-31',
    ])->assertCreated();

    $this->actingAs($this->admin)->postJson('/api/v1/admin/accounting/periods', [
        'name' => 'متداخلة',
        'start_date' => '2026-01-15',
        'end_date' => '2026-02-15',
    ])->assertStatus(422);

    expect(AccountingPeriod::count())->toBe(1);
});

test('a period holding an unbalanced entry cannot be closed', function () {
    $period = AccountingPeriod::create([
        'name' => 'يناير',
        'start_date' => '2026-01-01',
        'end_date' => '2026-01-31',
        'status' => AccountingPeriod::STATUS_OPEN,
    ]);

    // Written straight to the tables: the posting service refuses to produce
    // one of these, which is exactly why a period holding a corrupt entry from
    // some earlier era must not be frozen over it.
    $header = JournalEntryHeader::create([
        'entry_number' => 'JE-BAD',
        'entry_date' => '2026-01-10',
        'description' => 'قيد تالف',
        'total_debit' => 100,
        'total_credit' => 40,
        'status' => 'posted',
    ]);

    $header->lines()->create([
        'account_id' => LedgerAccount::where('posting_role', 'cash')->value('id'),
        'debit' => 100,
        'credit' => 0,
    ]);
    $header->lines()->create([
        'account_id' => LedgerAccount::where('posting_role', 'capital')->value('id'),
        'debit' => 0,
        'credit' => 40,
    ]);

    $this->actingAs($this->admin)
        ->postJson('/api/v1/admin/accounting/periods/'.$period->id.'/close')
        ->assertStatus(422);

    expect($period->fresh()->status)->toBe(AccountingPeriod::STATUS_OPEN);
});

test('closing a clean period records who closed it', function () {
    $period = AccountingPeriod::create([
        'name' => 'يناير',
        'start_date' => '2026-01-01',
        'end_date' => '2026-01-31',
        'status' => AccountingPeriod::STATUS_OPEN,
    ]);

    $this->actingAs($this->admin)
        ->postJson('/api/v1/admin/accounting/periods/'.$period->id.'/close')
        ->assertOk();

    $period->refresh();

    expect($period->status)->toBe(AccountingPeriod::STATUS_CLOSED);
    expect($period->closed_by)->toBe($this->admin->id);
    expect(AccountingPeriod::isClosed('2026-01-10'))->toBeTrue();
    expect(AccountingPeriod::isClosed('2026-02-10'))->toBeFalse();
});

test('a closed period cannot be deleted out from under the lock', function () {
    $period = ($this->closedPeriod)('2026-01-01', '2026-01-31');

    $this->actingAs($this->admin)
        ->deleteJson('/api/v1/admin/accounting/periods/'.$period->id)
        ->assertStatus(422);

    expect(AccountingPeriod::find($period->id))->not->toBeNull();
});

test('a stock adjustment inside a closed period is refused, not crashed', function () {
    $warehouse = App\Models\Warehouse::create([
        'name' => 'المستودع الرئيسي',
        'code' => 'WH-MAIN',
        'location' => 'الرياض',
        'status' => 'active',
        'is_active' => true,
        'is_primary' => true,
        'location_type' => App\Models\Warehouse::TYPE_WAREHOUSE,
    ]);

    $product = App\Models\Product::create([
        'name_ar' => 'مضخة مياه',
        'sku' => 'SKU-PUMP',
        'price' => 100,
        'cost_price' => 40,
        'stock_quantity' => 0,
    ]);

    app(App\Services\Inventory\InventoryService::class)->receive(
        $product->id,
        10,
        $warehouse->id,
        ['key' => 'opening:1', 'unit_cost' => 40]
    );

    // Adjustments are dated today, so closing the period today falls in is
    // what puts one out of reach.
    ($this->closedPeriod)(now()->startOfMonth()->toDateString(), now()->endOfMonth()->toDateString());

    $response = $this->actingAs($this->admin)->putJson('/api/v1/admin/products/'.$product->id, [
        'name_ar' => $product->name_ar,
        'price' => 100,
        'stock_quantity' => 3,
    ]);

    // A refusal that explains itself, rather than a 500 from an exception
    // nobody along that path was expecting.
    $response->assertStatus(422);
    expect($response->json('closed_period.name'))->not->toBeNull();

    // And the stock did not move either: the movement and its entry are one
    // transaction, so refusing the entry rolls the count back.
    expect((int) App\Models\WarehouseInventory::where('product_id', $product->id)->value('quantity'))->toBe(10);
});

test('the books are not opened to a sales account', function () {
    $sales = User::factory()->create([
        'role_id' => Role::firstOrCreate(['name' => 'sells'], ['display_name' => 'sells'])->id,
    ]);

    $this->actingAs($sales)
        ->getJson('/api/v1/admin/accounting/periods')
        ->assertForbidden();
});
