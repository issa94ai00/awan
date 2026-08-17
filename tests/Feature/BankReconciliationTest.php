<?php

use App\Models\BankReconciliation;
use App\Models\LedgerAccount;
use App\Models\Role;
use App\Models\User;
use App\Services\Accounting\LedgerPostingService;

/**
 * Holding the bank account against the bank's own statement.
 *
 * The bank balance is the only figure in the books with an independent
 * witness, and nothing ever asked it. A difference between the two is usually
 * timing — a cheque written before month end and cashed after it — but with no
 * reconciliation there is no way to tell that apart from a payment entered
 * twice, a transfer that never arrived, or a charge nobody recorded.
 */
beforeEach(function () {
    $this->admin = User::factory()->create([
        'is_admin' => true,
        'role_id' => Role::firstOrCreate(['name' => 'admin'], ['display_name' => 'admin'])->id,
    ]);

    $this->bank = LedgerAccount::where('posting_role', 'bank')->first();
    $this->ledger = app(LedgerPostingService::class);

    /** Money into the bank on a date, against capital so the entry balances. */
    $this->deposit = function (float $amount, string $date, string $key) {
        $this->ledger->post(
            key: $key,
            date: $date,
            description: 'إيداع',
            lines: [
                ['role' => 'bank', 'debit' => $amount, 'description' => 'إيداع'],
                ['role' => 'capital', 'credit' => $amount, 'description' => 'رأس المال'],
            ],
            module: 'manual',
        );
    };

    $this->withdraw = function (float $amount, string $date, string $key) {
        $this->ledger->post(
            key: $key,
            date: $date,
            description: 'شيك',
            lines: [
                ['role' => 'other_expense', 'debit' => $amount, 'description' => 'مصروف'],
                ['role' => 'bank', 'credit' => $amount, 'description' => 'شيك صادر'],
            ],
            module: 'manual',
        );
    };

    $this->open = fn (float $statementBalance, string $date = '2026-03-31') => $this->actingAs($this->admin)
        ->postJson('/api/v1/admin/accounting/bank-reconciliations', [
            'account_id' => $this->bank->id,
            'statement_date' => $date,
            'statement_balance' => $statementBalance,
        ]);

    $this->clear = fn (int $id, int $lineId) => $this->actingAs($this->admin)
        ->postJson('/api/v1/admin/accounting/bank-reconciliations/'.$id.'/toggle-line', ['line_id' => $lineId]);
});

test('a statement that matches the books reconciles with nothing outstanding', function () {
    ($this->deposit)(1000, '2026-03-05', 'dep:1');

    $data = ($this->open)(1000)->assertCreated()->json('data');

    expect($data['movements'])->toHaveCount(1);

    // Clearing the one movement leaves nothing in transit.
    ($this->clear)($data['id'], $data['movements'][0]['id'])->assertOk();

    $summary = $this->actingAs($this->admin)
        ->getJson('/api/v1/admin/accounting/bank-reconciliations/'.$data['id'])
        ->assertOk()->json('data.summary');

    expect((float) $summary['outstanding_total'])->toBe(0.0);
    expect($summary['is_reconciled'])->toBeTrue();
});

test('a cheque not yet cashed explains the difference', function () {
    ($this->deposit)(1000, '2026-03-05', 'dep:1');
    ($this->withdraw)(250, '2026-03-28', 'chq:1');

    // The bank has seen the deposit but not the cheque: it says 1,000 while
    // the books say 750.
    $data = ($this->open)(1000)->assertCreated()->json('data');

    $deposit = collect($data['movements'])->firstWhere('amount', 1000.0);
    ($this->clear)($data['id'], $deposit['id'])->assertOk();

    $summary = $this->actingAs($this->admin)
        ->getJson('/api/v1/admin/accounting/bank-reconciliations/'.$data['id'])
        ->assertOk()->json('data.summary');

    expect((float) $summary['book_balance'])->toBe(750.0);
    expect((float) $summary['outstanding_total'])->toBe(-250.0);
    // 750 − (−250) = 1,000: the whole difference is the uncashed cheque.
    expect($summary['is_reconciled'])->toBeTrue();
});

test('an unexplained difference refuses to be closed', function () {
    ($this->deposit)(1000, '2026-03-05', 'dep:1');

    // The bank says 900 and the books say 1,000, with nothing in transit —
    // that is not timing, it is an error somebody has to find.
    $data = ($this->open)(900)->assertCreated()->json('data');
    ($this->clear)($data['id'], $data['movements'][0]['id'])->assertOk();

    $response = $this->actingAs($this->admin)
        ->postJson('/api/v1/admin/accounting/bank-reconciliations/'.$data['id'].'/complete')
        ->assertStatus(422);

    expect((float) $response->json('data.summary.difference'))->toBe(100.0);
    expect(BankReconciliation::find($data['id'])->status)->toBe('open');
});

test('a reconciliation that closes is recorded as closed', function () {
    ($this->deposit)(500, '2026-03-02', 'dep:1');

    $data = ($this->open)(500)->assertCreated()->json('data');
    ($this->clear)($data['id'], $data['movements'][0]['id'])->assertOk();

    $this->actingAs($this->admin)
        ->postJson('/api/v1/admin/accounting/bank-reconciliations/'.$data['id'].'/complete')
        ->assertOk();

    $reconciliation = BankReconciliation::find($data['id']);

    expect($reconciliation->status)->toBe('completed');
    expect($reconciliation->completed_by)->toBe($this->admin->id);
});

test('movements after the statement date are not held against it', function () {
    ($this->deposit)(400, '2026-03-10', 'dep:1');
    // Next month's trading cannot be on a statement printed before it.
    ($this->deposit)(900, '2026-04-05', 'dep:2');

    $data = ($this->open)(400, '2026-03-31')->assertCreated()->json('data');

    expect($data['movements'])->toHaveCount(1);
    expect((float) $data['summary']['book_balance'])->toBe(400.0);
});

test('a completed reconciliation is frozen until it is reopened', function () {
    ($this->deposit)(300, '2026-03-03', 'dep:1');

    $data = ($this->open)(300)->assertCreated()->json('data');
    $lineId = $data['movements'][0]['id'];

    ($this->clear)($data['id'], $lineId)->assertOk();
    $this->actingAs($this->admin)
        ->postJson('/api/v1/admin/accounting/bank-reconciliations/'.$data['id'].'/complete')->assertOk();

    ($this->clear)($data['id'], $lineId)->assertStatus(422);

    $this->actingAs($this->admin)
        ->postJson('/api/v1/admin/accounting/bank-reconciliations/'.$data['id'].'/reopen')->assertOk();

    ($this->clear)($data['id'], $lineId)->assertOk();
});

test('two open reconciliations on one account are refused', function () {
    ($this->deposit)(100, '2026-03-01', 'dep:1');

    ($this->open)(100, '2026-03-31')->assertCreated();
    // The same movement could otherwise be cleared in both, each proving
    // itself against a different set of outstanding items.
    ($this->open)(100, '2026-04-30')->assertStatus(422);

    expect(BankReconciliation::count())->toBe(1);
});

test('a movement from another account cannot be ticked off here', function () {
    ($this->deposit)(200, '2026-03-01', 'dep:1');

    $data = ($this->open)(200)->assertCreated()->json('data');

    $capitalLine = App\Models\JournalEntryLine::where(
        'account_id',
        LedgerAccount::where('posting_role', 'capital')->value('id')
    )->first();

    ($this->clear)($data['id'], $capitalLine->id)->assertStatus(422);
});

test('a completed reconciliation cannot be deleted', function () {
    ($this->deposit)(150, '2026-03-01', 'dep:1');

    $data = ($this->open)(150)->assertCreated()->json('data');
    ($this->clear)($data['id'], $data['movements'][0]['id'])->assertOk();
    $this->actingAs($this->admin)
        ->postJson('/api/v1/admin/accounting/bank-reconciliations/'.$data['id'].'/complete')->assertOk();

    $this->actingAs($this->admin)
        ->deleteJson('/api/v1/admin/accounting/bank-reconciliations/'.$data['id'])
        ->assertStatus(422);
});

test('reconciliations are refused to a sales account', function () {
    $sales = User::factory()->create([
        'role_id' => Role::firstOrCreate(['name' => 'sells'], ['display_name' => 'sells'])->id,
    ]);

    $this->actingAs($sales)
        ->getJson('/api/v1/admin/accounting/bank-reconciliations')
        ->assertForbidden();
});
