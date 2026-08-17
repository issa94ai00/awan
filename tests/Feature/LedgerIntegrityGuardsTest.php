<?php

use App\Models\Currency;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\LedgerAccount;
use App\Models\Role;
use App\Models\User;
use App\Services\Accounting\LedgerPostingService;
use App\Services\CurrencyService;

/**
 * What must not be possible to do to the chart of accounts.
 *
 * Deleting a ledger account used to be unconditional. Three different kinds of
 * damage followed, and none announced itself at the moment of the delete:
 *
 *  - an account carrying entries takes its side of every statement already
 *    printed with it (the database now refuses outright, which surfaced as a
 *    driver error rather than an explanation);
 *  - an account holding a posting role does not fail on deletion at all — it
 *    fails the next time somebody records a payment, with a message naming a
 *    role instead of the account someone removed;
 *  - a parent leaves its children pointing at nothing.
 */
beforeEach(function () {
    $this->admin = User::factory()->create([
        'is_admin' => true,
        'role_id' => Role::firstOrCreate(['name' => 'admin'], ['display_name' => 'admin'])->id,
    ]);

    $this->delete = fn (LedgerAccount $account) => $this->actingAs($this->admin)
        ->deleteJson('/api/v1/admin/accounting/ledger-accounts/'.$account->id);
});

test('an account holding a posting role cannot be deleted', function () {
    $cash = LedgerAccount::where('posting_role', 'cash')->first();

    ($this->delete)($cash)->assertStatus(422);

    expect(LedgerAccount::find($cash->id))->not->toBeNull();
    // And the role still resolves, which is the thing that actually mattered.
    expect(LedgerAccount::where('posting_role', 'cash')->exists())->toBeTrue();
});

test('an account carrying entries cannot be deleted', function () {
    $customer = Customer::create(['name' => 'عميل', 'email' => 'x@example.test', 'status' => 'active']);

    app(LedgerPostingService::class)->postInvoice(Invoice::create([
        'invoice_number' => 'INV-GUARD',
        'customer_id' => $customer->id,
        'status' => 'sent',
        'subtotal' => 100,
        'tax' => 0,
        'total' => 100,
        'due_amount' => 100,
    ]));

    $receivable = LedgerAccount::where('posting_role', 'accounts_receivable')->first();

    // Strip the role so the refusal has to come from the entries, not the role.
    $receivable->update(['posting_role' => null, 'is_system' => false]);

    ($this->delete)($receivable->fresh())->assertStatus(422);

    expect(LedgerAccount::find($receivable->id))->not->toBeNull();
});

test('a parent account cannot be deleted while it has children', function () {
    $parent = LedgerAccount::where('code', '1000')->first();
    $parent->update(['is_system' => false]);

    ($this->delete)($parent->fresh())->assertStatus(422);

    expect(LedgerAccount::find($parent->id))->not->toBeNull();
});

test('an unused account with nothing depending on it can still be deleted', function () {
    $account = LedgerAccount::create([
        'code' => '9999',
        'name' => 'حساب مؤقت',
        'type' => 'expense',
        'account_type' => 'expense',
        'balance' => 0,
        'is_active' => true,
    ]);

    ($this->delete)($account)->assertOk();

    expect(LedgerAccount::find($account->id))->toBeNull();
});

/* -------------------------------------------------------------------- *
 * The chart names the currency the books are kept in
 * -------------------------------------------------------------------- */

test('every account is labelled with the base currency', function () {
    $base = base_currency_code();

    expect(LedgerAccount::where('currency', '!=', $base)->count())->toBe(0);
});

test('changing the base currency carries the chart with it', function () {
    $other = Currency::where('is_base', false)->first();

    // Guards the drift this fixed: the chart said one currency while the
    // system was configured for another, and nothing reconciled them.
    app(CurrencyService::class)->setBase($other);

    expect(LedgerAccount::where('currency', '!=', $other->code)->count())->toBe(0);
});

test('an account created by hand takes the books currency', function () {
    $created = $this->actingAs($this->admin)->postJson('/api/v1/admin/accounting/ledger-accounts', [
        'code' => '9998',
        'name' => 'حساب جديد',
        'type' => 'asset',
    ])->assertCreated()->json('data');

    expect($created['currency'])->toBe(base_currency_code());
});

/* -------------------------------------------------------------------- *
 * The integrity command sees what the balance checks cannot
 * -------------------------------------------------------------------- */

test('the check reports an entry left with no lines', function () {
    $customer = Customer::create(['name' => 'عميل', 'email' => 'y@example.test', 'status' => 'active']);

    $entry = app(LedgerPostingService::class)->postInvoice(Invoice::create([
        'invoice_number' => 'INV-EMPTY',
        'customer_id' => $customer->id,
        'status' => 'sent',
        'subtotal' => 100,
        'tax' => 0,
        'total' => 100,
        'due_amount' => 100,
    ]));

    // An entry with no lines balances trivially, so every other check passes it.
    $entry->lines()->delete();

    $this->artisan('accounting:check')->assertFailed();
});

test('a healthy ledger passes the check', function () {
    $this->artisan('accounting:check')->assertSuccessful();
});
