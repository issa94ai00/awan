<?php

use App\Models\Customer;
use App\Models\Expense;
use App\Models\Invoice;
use App\Models\JournalEntryHeader;
use App\Models\LedgerAccount;
use App\Models\Payment;
use App\Models\Role;
use App\Models\User;
use App\Services\Accounting\LedgerPostingService;

/**
 * The weaknesses the diagnosis named, and the behaviour that closes them.
 *
 *  - **Failures were swallowed.** Three paths caught a posting failure, logged
 *    it, and answered success with the reason in a field no screen displays.
 *    Money moved on the customer's record and never reached the books, and the
 *    person who took it had no way to know.
 *  - **Expenses were the last document that could not be reversed.** Editing
 *    one left the ledger describing the old amount; deleting one kept its cost
 *    in the income statement of a period already reported on.
 *  - **The manual entry had its own engine.** A second implementation of
 *    balance checking, numbering and balance updates, which every new rule had
 *    to be remembered in.
 *  - **The stored balance was a second source of truth**, with nothing to
 *    bring it back in line with the journal.
 *  - **Nothing closed a year**, so an income statement in the next year
 *    silently included the last one.
 */
beforeEach(function () {
    $this->admin = User::factory()->create([
        'is_admin' => true,
        'role_id' => Role::firstOrCreate(['name' => 'admin'], ['display_name' => 'admin'])->id,
    ]);

    $this->customer = Customer::create([
        'name' => 'عميل التجربة',
        'email' => 'c@example.test',
        'status' => 'active',
    ]);

    $this->balanceOf = fn (string $role) => round(
        (float) LedgerAccount::where('posting_role', $role)->value('balance'),
        2
    );
});

/* -------------------------------------------------------------------- *
 * Posting failures are refusals, not warnings
 * -------------------------------------------------------------------- */

test('a payment whose entry cannot be posted is refused, and moves nothing', function () {
    // Remove the account the entry needs: the same shape as a chart that was
    // never fully seeded, which is when this used to bite.
    LedgerAccount::where('posting_role', 'accounts_receivable')->update(['posting_role' => null]);

    $this->actingAs($this->admin)->postJson('/api/v1/payments', [
        'customer_id' => $this->customer->id,
        'payment_method' => 'cash',
        'amount' => 500,
    ])->assertStatus(422);

    // The payment is not on file and the customer does not appear to have paid.
    expect(Payment::count())->toBe(0);
    expect(round((float) $this->customer->fresh()->balance, 2))->toBe(0.0);
});

test('an expense whose entry cannot be posted is refused, and is not stored', function () {
    LedgerAccount::where('posting_role', 'other_expense')->update(['posting_role' => null]);

    $this->actingAs($this->admin)->postJson('/api/v1/expenses', [
        'description' => 'إيجار',
        'amount' => 300,
        'expense_date' => now()->toDateString(),
    ])->assertStatus(422);

    expect(Expense::count())->toBe(0);
});

/* -------------------------------------------------------------------- *
 * Expenses behave like every other document
 * -------------------------------------------------------------------- */

test('correcting an expense reverses the old entry and posts the new figure', function () {
    $created = $this->actingAs($this->admin)->postJson('/api/v1/expenses', [
        'description' => 'شحن',
        'amount' => 300,
        'category' => 'shipping',
        'expense_date' => now()->toDateString(),
    ])->assertCreated()->json('data');

    expect(($this->balanceOf)('shipping_expense'))->toBe(300.0);

    $this->actingAs($this->admin)
        ->putJson('/api/v1/expenses/'.$created['id'], ['amount' => 180])
        ->assertOk();

    // Three entries: the original, its reversal, and the restatement — and the
    // account carries only the corrected figure.
    expect(($this->balanceOf)('shipping_expense'))->toBe(180.0);
    expect(JournalEntryHeader::where('source_module', 'expenses')->count())->toBe(3);
});

test('deleting an expense takes its cost out of the income statement', function () {
    $created = $this->actingAs($this->admin)->postJson('/api/v1/expenses', [
        'description' => 'تغليف',
        'amount' => 120,
        'category' => 'packaging',
        'expense_date' => now()->toDateString(),
    ])->assertCreated()->json('data');

    $this->actingAs($this->admin)
        ->deleteJson('/api/v1/expenses/'.$created['id'])
        ->assertOk();

    expect(($this->balanceOf)('packaging_expense'))->toBe(0.0);
    // The original entry stays; a mirror cancels it.
    expect(JournalEntryHeader::where('posting_key', 'expense:'.$created['id'])->first()->status)
        ->toBe('reversed');
});

test('a description-only edit leaves the books alone', function () {
    $created = $this->actingAs($this->admin)->postJson('/api/v1/expenses', [
        'description' => 'شحن',
        'amount' => 300,
        'category' => 'shipping',
        'expense_date' => now()->toDateString(),
    ])->assertCreated()->json('data');

    $this->actingAs($this->admin)
        ->putJson('/api/v1/expenses/'.$created['id'], ['description' => 'شحن — تصحيح إملائي'])
        ->assertOk();

    expect(JournalEntryHeader::where('source_module', 'expenses')->count())->toBe(1);
    expect(($this->balanceOf)('shipping_expense'))->toBe(300.0);
});

/* -------------------------------------------------------------------- *
 * One engine for every entry
 * -------------------------------------------------------------------- */

test('a manual entry is written by the posting engine like any other', function () {
    $cash = LedgerAccount::where('posting_role', 'cash')->value('id');
    $capital = LedgerAccount::where('posting_role', 'capital')->value('id');

    $entry = $this->actingAs($this->admin)->postJson('/api/v1/admin/accounting/journal-entries', [
        'entry_date' => now()->toDateString(),
        'description' => 'قيد يدوي',
        'lines' => [
            ['ledger_account_id' => $cash, 'debit' => 700, 'credit' => 0],
            ['ledger_account_id' => $capital, 'debit' => 0, 'credit' => 700],
        ],
    ])->assertCreated()->json('data');

    // Same numbering, same key discipline, same balance handling as a document.
    expect($entry['entry_number'])->toStartWith('JE-');
    expect($entry['posting_key'])->toStartWith('manual:');
    expect($entry['source_module'])->toBe('manual');
    expect(($this->balanceOf)('cash'))->toBe(700.0);
});

test('the engine refuses an unbalanced manual entry', function () {
    $cash = LedgerAccount::where('posting_role', 'cash')->value('id');
    $capital = LedgerAccount::where('posting_role', 'capital')->value('id');

    $this->actingAs($this->admin)->postJson('/api/v1/admin/accounting/journal-entries', [
        'entry_date' => now()->toDateString(),
        'description' => 'قيد مختل',
        'lines' => [
            ['ledger_account_id' => $cash, 'debit' => 700, 'credit' => 0],
            ['ledger_account_id' => $capital, 'debit' => 0, 'credit' => 500],
        ],
    ])->assertStatus(422);

    expect(JournalEntryHeader::count())->toBe(0);
});

/* -------------------------------------------------------------------- *
 * The stored balance is a cache, and can be rebuilt
 * -------------------------------------------------------------------- */

test('rebuilding restores a balance that drifted from the journal', function () {
    app(LedgerPostingService::class)->postInvoice(Invoice::create([
        'invoice_number' => 'INV-0001',
        'customer_id' => $this->customer->id,
        'status' => 'sent',
        'subtotal' => 900,
        'tax' => 0,
        'total' => 900,
        'due_amount' => 900,
    ]));

    // The kind of damage an import or a hand-run statement leaves behind.
    LedgerAccount::where('posting_role', 'accounts_receivable')->update(['balance' => 12345]);

    $this->artisan('accounting:rebuild-balances')->assertSuccessful();

    expect(($this->balanceOf)('accounts_receivable'))->toBe(900.0);
});

test('a dry run reports the drift and repairs nothing', function () {
    LedgerAccount::where('posting_role', 'cash')->update(['balance' => 999]);

    $this->artisan('accounting:rebuild-balances --dry-run')->assertSuccessful();

    expect(($this->balanceOf)('cash'))->toBe(999.0);
});

/* -------------------------------------------------------------------- *
 * An entry says what currency it is in
 * -------------------------------------------------------------------- */

test('every entry is stamped with the currency the books are kept in', function () {
    app(LedgerPostingService::class)->postInvoice(Invoice::create([
        'invoice_number' => 'INV-CUR',
        'customer_id' => $this->customer->id,
        'status' => 'sent',
        'subtotal' => 100,
        'tax' => 0,
        'total' => 100,
        'due_amount' => 100,
    ]));

    $entry = JournalEntryHeader::latest('id')->first();

    // Not the literal 'SAR' the service used to fall back to regardless of
    // what was configured, and a rate of exactly 1 because nothing converts on
    // the way into the ledger.
    expect($entry->base_currency)->toBe(base_currency_code());
    expect(round((float) $entry->exchange_rate, 4))->toBe(1.0);
});

test('a document labelled in another currency is reported, not silently converted', function () {
    Invoice::create([
        'invoice_number' => 'INV-FOREIGN',
        'customer_id' => $this->customer->id,
        'status' => 'sent',
        'subtotal' => 100,
        'tax' => 0,
        'total' => 100,
        'due_amount' => 100,
        'currency' => base_currency_code() === 'USD' ? 'SYP' : 'USD',
    ]);

    $checks = collect($this->actingAs($this->admin)
        ->getJson('/api/v1/admin/accounting/system-health')
        ->assertOk()
        ->json('data.checks'));

    $check = $checks->firstWhere('code', 'documents_in_other_currency');

    expect($check['count'])->toBe(1);
    expect($check['ok'])->toBeFalse();
});

/* -------------------------------------------------------------------- *
 * Closing the year
 * -------------------------------------------------------------------- */

test('closing a year empties the result accounts into retained earnings', function () {
    $ledger = app(LedgerPostingService::class);

    $invoice = Invoice::create([
        'invoice_number' => 'INV-YEAR',
        'customer_id' => $this->customer->id,
        'status' => 'sent',
        'subtotal' => 1000,
        'tax' => 0,
        'total' => 1000,
        'due_amount' => 1000,
    ]);
    $invoice->forceFill(['created_at' => '2026-03-01'])->save();
    $ledger->postInvoice($invoice->refresh());

    $ledger->postExpense((object) [
        'id' => 991,
        'amount' => 400,
        'status' => 'paid',
        'category' => 'shipping',
        'expense_date' => '2026-04-01',
        'expense_number' => 'EXP-YEAR',
        'description' => 'شحن',
        'currency' => 'SAR',
    ]);

    $this->artisan('accounting:close-year 2026')->assertSuccessful();

    // Revenue and expense are back to zero; the 600 of profit sits in equity.
    expect(($this->balanceOf)('sales_revenue'))->toBe(0.0);
    expect(($this->balanceOf)('shipping_expense'))->toBe(0.0);
    expect(($this->balanceOf)('retained_earnings'))->toBe(600.0);
});

test('closing the same year twice does not double retained earnings', function () {
    $invoice = Invoice::create([
        'invoice_number' => 'INV-TWICE',
        'customer_id' => $this->customer->id,
        'status' => 'sent',
        'subtotal' => 500,
        'tax' => 0,
        'total' => 500,
        'due_amount' => 500,
    ]);
    $invoice->forceFill(['created_at' => '2026-05-01'])->save();
    app(LedgerPostingService::class)->postInvoice($invoice->refresh());

    $this->artisan('accounting:close-year 2026')->assertSuccessful();
    $this->artisan('accounting:close-year 2026')->assertSuccessful();

    expect(($this->balanceOf)('retained_earnings'))->toBe(500.0);
    expect(JournalEntryHeader::where('posting_key', 'year_close:2026')->count())->toBe(1);
});

test('the closing entry balances and lands on the last day of the year', function () {
    $invoice = Invoice::create([
        'invoice_number' => 'INV-DATE',
        'customer_id' => $this->customer->id,
        'status' => 'sent',
        'subtotal' => 250,
        'tax' => 0,
        'total' => 250,
        'due_amount' => 250,
    ]);
    $invoice->forceFill(['created_at' => '2026-02-02'])->save();
    app(LedgerPostingService::class)->postInvoice($invoice->refresh());

    $this->artisan('accounting:close-year 2026')->assertSuccessful();

    $entry = JournalEntryHeader::where('posting_key', 'year_close:2026')->first();

    expect($entry->entry_date->toDateString())->toBe('2026-12-31');
    expect(round((float) $entry->total_debit, 2))->toBe(round((float) $entry->total_credit, 2));
});
