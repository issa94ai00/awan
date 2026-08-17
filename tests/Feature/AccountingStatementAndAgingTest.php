<?php

use App\Models\Customer;
use App\Models\Invoice;
use App\Models\LedgerAccount;
use App\Models\Role;
use App\Models\Supplier;
use App\Models\User;
use App\Services\Accounting\LedgerPostingService;

/**
 * Two reports the books could not previously produce.
 *
 * **The account statement** was built in the browser: the ledger screen pulled
 * the last 200 journal entries for an account, flattened their lines and ran a
 * total from zero. The period the user had picked was ignored, anything past
 * the 200th entry silently vanished, and a running figure that starts at zero
 * only matches reality for an account nobody had touched before the first row
 * on screen. So the statement regularly disagreed with the balance printed
 * directly above it, and neither number explained the other.
 *
 * **Aging** existed as a single company-wide figure in the analytics service,
 * and it answered a different question than its name: it bucketed whole
 * invoice totals rather than what was still outstanding, and only looked at
 * invoices already overdue — so a large unpaid invoice due next week counted
 * as nothing. Collection is a conversation with one customer, so the answer
 * has to be per customer, and it has to add up to the receivables account or
 * one of the two is wrong.
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

    $this->invoice = function (float $total, string $createdAt, ?string $dueDate = null, float $paid = 0): Invoice {
        $invoice = Invoice::create([
            'invoice_number' => 'INV-'.uniqid(),
            'customer_id' => $this->customer->id,
            'status' => 'sent',
            'subtotal' => $total,
            'tax' => 0,
            'total' => $total,
            'paid_amount' => $paid,
            'due_amount' => $total - $paid,
            'due_date' => $dueDate,
        ]);

        $invoice->forceFill(['created_at' => $createdAt])->save();

        return $invoice->refresh();
    };

    $this->statement = fn (int $accountId, array $params = []) => $this->actingAs($this->admin)
        ->getJson('/api/v1/admin/accounting/account-statement?'.http_build_query(
            array_merge(['account_id' => $accountId], $params)
        ));
});

/* -------------------------------------------------------------------- *
 * Account statement
 * -------------------------------------------------------------------- */

test('the statement opens from everything that happened before the period', function () {
    $ledger = app(LedgerPostingService::class);
    $receivableId = LedgerAccount::where('posting_role', 'accounts_receivable')->value('id');

    $ledger->postInvoice(($this->invoice)(300, '2026-01-10'));
    $ledger->postInvoice(($this->invoice)(200, '2026-03-05'));

    $response = ($this->statement)($receivableId, [
        'date_from' => '2026-02-01',
        'date_to' => '2026-03-31',
    ])->assertOk();

    // January is outside the window, so it belongs in the opening figure and
    // not in the rows — the old screen showed neither.
    expect((float) $response->json('data.opening_balance'))->toBe(300.0);
    expect($response->json('data.movements'))->toHaveCount(1);
    expect((float) $response->json('data.closing_balance'))->toBe(500.0);
});

test('the running balance follows the account down as well as up', function () {
    $ledger = app(LedgerPostingService::class);
    $cashId = LedgerAccount::where('posting_role', 'cash')->value('id');

    $invoice = ($this->invoice)(400, '2026-01-05');
    $ledger->postInvoice($invoice);

    $payment = App\Models\Payment::create([
        'payment_number' => 'PAY-000001',
        'invoice_id' => $invoice->id,
        'customer_id' => $this->customer->id,
        'payment_method' => 'cash',
        'amount' => 400,
        'payment_date' => '2026-01-20',
        'status' => 'completed',
    ]);
    $ledger->postPayment($payment);

    $refund = App\Models\Payment::create([
        'payment_number' => 'PAY-000002',
        'invoice_id' => $invoice->id,
        'customer_id' => $this->customer->id,
        'payment_method' => 'cash',
        'amount' => -150,
        'payment_date' => '2026-01-25',
        'status' => 'refunded',
    ]);
    $ledger->postPayment($refund);

    $response = ($this->statement)($cashId, [
        'date_from' => '2026-01-01',
        'date_to' => '2026-01-31',
    ])->assertOk();

    // Cast because JSON gives back a whole number as an int, and what is being
    // asserted here is the arithmetic, not the encoding.
    $balances = array_map('floatval', array_column($response->json('data.movements'), 'balance'));

    expect($balances)->toBe([400.0, 250.0]);
    expect((float) $response->json('data.closing_balance'))->toBe(250.0);
});

test('the statement says when it does not land on the stored balance', function () {
    $ledger = app(LedgerPostingService::class);
    $receivable = LedgerAccount::where('posting_role', 'accounts_receivable')->first();

    $ledger->postInvoice(($this->invoice)(100, '2026-01-10'));

    $matching = ($this->statement)($receivable->id, [
        'date_from' => '2026-01-01',
        'date_to' => '2026-12-31',
    ])->assertOk();

    expect($matching->json('data.matches_stored_balance'))->toBeTrue();

    // A cached balance nudged out of step with the lines behind it is exactly
    // the corruption this flag exists to surface.
    $receivable->update(['balance' => 999]);

    $drifted = ($this->statement)($receivable->id, [
        'date_from' => '2026-01-01',
        'date_to' => '2026-12-31',
    ])->assertOk();

    expect($drifted->json('data.matches_stored_balance'))->toBeFalse();
    expect((float) $drifted->json('data.closing_balance'))->toBe(100.0);
});

test('the statement is refused to a sales account', function () {
    $sales = User::factory()->create([
        'role_id' => Role::firstOrCreate(['name' => 'sells'], ['display_name' => 'sells'])->id,
    ]);

    $accountId = LedgerAccount::where('posting_role', 'cash')->value('id');

    $this->actingAs($sales)
        ->getJson('/api/v1/admin/accounting/account-statement?account_id='.$accountId)
        ->assertForbidden();
});

/* -------------------------------------------------------------------- *
 * Aging
 * -------------------------------------------------------------------- */

test('what is owed is aged by each invoice own due date', function () {
    $asOf = '2026-06-30';

    ($this->invoice)(100, '2026-06-01', '2026-07-15');  // not yet due
    ($this->invoice)(200, '2026-05-01', '2026-06-10');  // 20 days late
    ($this->invoice)(300, '2026-02-01', '2026-03-01');  // 121 days late

    $buckets = $this->actingAs($this->admin)
        ->getJson('/api/v1/admin/accounting/aging?as_of='.$asOf)
        ->assertOk()
        ->json('data.receivables.buckets');

    expect((float) $buckets['current'])->toBe(100.0);
    expect((float) $buckets['1_30'])->toBe(200.0);
    expect((float) $buckets['over_90'])->toBe(300.0);
});

test('aging counts what is still outstanding, not the whole invoice', function () {
    // Half collected: the old report bucketed the full total and overstated
    // what there was left to chase by the amount already in the bank.
    ($this->invoice)(1000, '2026-05-01', '2026-05-15', paid: 600);

    $receivables = $this->actingAs($this->admin)
        ->getJson('/api/v1/admin/accounting/aging?as_of=2026-06-30')
        ->assertOk()
        ->json('data.receivables');

    expect((float) $receivables['total'])->toBe(400.0);
    expect($receivables['parties'][0]['name'])->toBe('شركة النور');
});

test('a settled invoice drops off the report entirely', function () {
    ($this->invoice)(500, '2026-05-01', '2026-05-15', paid: 500);

    $receivables = $this->actingAs($this->admin)
        ->getJson('/api/v1/admin/accounting/aging?as_of=2026-06-30')
        ->assertOk()
        ->json('data.receivables');

    expect((float) $receivables['total'])->toBe(0.0);
    expect($receivables['parties'])->toBe([]);
});

test('the customer list is reconciled against the receivables account', function () {
    $ledger = app(LedgerPostingService::class);
    $ledger->postInvoice(($this->invoice)(750, '2026-05-01', '2026-05-15'));

    $receivables = $this->actingAs($this->admin)
        ->getJson('/api/v1/admin/accounting/aging?as_of=2026-06-30')
        ->assertOk()
        ->json('data.receivables');

    expect((float) $receivables['total'])->toBe(750.0);
    expect((float) $receivables['control_account']['balance'])->toBe(750.0);
    expect($receivables['reconciled'])->toBeTrue();
});

test('a subsidiary list that does not add up to its control account says so', function () {
    // An invoice that never reached the ledger: the customer owes the money,
    // the receivables account does not know about it. Printing the two side by
    // side is what turns that into something anybody notices.
    ($this->invoice)(750, '2026-05-01', '2026-05-15');

    $receivables = $this->actingAs($this->admin)
        ->getJson('/api/v1/admin/accounting/aging?as_of=2026-06-30')
        ->assertOk()
        ->json('data.receivables');

    expect($receivables['reconciled'])->toBeFalse();
    expect((float) $receivables['difference'])->toBe(750.0);
});

test('suppliers are aged against the payables account', function () {
    $supplier = Supplier::create(['name' => 'مورّد المضخات', 'status' => 'active', 'balance' => 0]);

    App\Models\Warehouse::create([
        'name' => 'المستودع الرئيسي',
        'code' => 'WH-MAIN',
        'location' => 'الرياض',
        'status' => 'active',
        'is_active' => true,
        'is_primary' => true,
        'location_type' => App\Models\Warehouse::TYPE_WAREHOUSE,
    ]);

    $this->actingAs($this->admin)->postJson('/api/v1/purchase-receipts', [
        'supplier_id' => $supplier->id,
        'receipt_date' => '2026-05-01',
        'items' => [
            ['product_id' => App\Models\Product::create([
                'name_ar' => 'مضخة',
                'sku' => 'SKU-1',
                'price' => 100,
                'cost_price' => 40,
            ])->id, 'quantity' => 5, 'unit_price' => 40],
        ],
    ])->assertCreated();

    $payables = $this->actingAs($this->admin)
        ->getJson('/api/v1/admin/accounting/aging?as_of=2026-06-30')
        ->assertOk()
        ->json('data.payables');

    expect((float) $payables['total'])->toBe(200.0);
    expect((float) $payables['control_account']['balance'])->toBe(200.0);
    expect($payables['reconciled'])->toBeTrue();
    // Raised on 1 May and unpaid on 30 June: 60 days late.
    expect((float) $payables['buckets']['31_60'])->toBe(200.0);
});
