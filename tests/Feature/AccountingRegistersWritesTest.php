<?php

use App\Models\BankReconciliation;
use App\Models\Budget;
use App\Models\BudgetLine;
use App\Models\CreditNote;
use App\Models\Customer;
use App\Models\FixedAsset;
use App\Models\JournalEntryHeader;
use App\Models\JournalEntryLine;
use App\Models\LedgerAccount;
use App\Models\Role;
use App\Models\User;

beforeEach(function () {
    $this->admin = User::factory()->create([
        'is_admin' => true,
        'role_id' => Role::firstOrCreate(['name' => 'admin'], ['display_name' => 'admin'])->id,
    ]);
    $this->actingAs($this->admin);

    $this->cash = LedgerAccount::where('posting_role', 'cash')->first();
    $this->capital = LedgerAccount::where('posting_role', 'capital')->value('id');

    // A 100 deposit into cash, dated within the statement window.
    $this->deposit = function (float $amount = 100) {
        $this->postJson('/api/v1/admin/accounting/journal-entries', [
            'entry_date' => now()->toDateString(),
            'lines' => [
                ['ledger_account_id' => $this->cash->id, 'debit' => $amount, 'credit' => 0],
                ['ledger_account_id' => $this->capital, 'debit' => 0, 'credit' => $amount],
            ],
        ])->assertCreated();

        return JournalEntryLine::where('account_id', $this->cash->id)->orderByDesc('id')->first();
    };

    $this->openRec = fn (float $balance = 100) => BankReconciliation::create([
        'reference' => 'BR-T'.(BankReconciliation::count() + 1),
        'account_id' => $this->cash->id,
        'statement_date' => now()->toDateString(),
        'statement_balance' => $balance,
        'status' => 'open',
        'created_by' => $this->admin->id,
    ]);
});

it('ticks lines, completes, refuses edits while completed, reopens and deletes a reconciliation', function () {
    $line = ($this->deposit)(100);
    $rec = ($this->openRec)(100);
    $id = $rec->id;

    $this->postJson("/api/v1/admin/accounting/bank-reconciliations/$id/complete")->assertStatus(422);
    expect($rec->fresh()->status)->toBe('open');

    $this->postJson("/api/v1/admin/accounting/bank-reconciliations/$id/toggle-line", ['line_id' => $line->id])->assertOk();
    expect($rec->clearedLines()->count())->toBe(1);
    $this->postJson("/api/v1/admin/accounting/bank-reconciliations/$id/toggle-line", ['line_id' => $line->id])->assertOk();
    expect($rec->clearedLines()->count())->toBe(0);

    $this->postJson("/api/v1/admin/accounting/bank-reconciliations/$id/lines", ['line_ids' => [$line->id], 'cleared' => true])->assertOk();
    expect($rec->clearedLines()->count())->toBe(1);
    $this->postJson("/api/v1/admin/accounting/bank-reconciliations/$id/lines", ['line_ids' => [$line->id], 'cleared' => false])->assertOk();
    expect($rec->clearedLines()->count())->toBe(0);
    $this->postJson("/api/v1/admin/accounting/bank-reconciliations/$id/lines", ['line_ids' => [999999], 'cleared' => true])->assertStatus(422);

    // Statement balance 100 with nothing cleared: book 100 - outstanding 100 - 100 != 0 -> fix by clearing.
    $this->postJson("/api/v1/admin/accounting/bank-reconciliations/$id/lines", ['line_ids' => [$line->id], 'cleared' => true])->assertOk();
    $this->postJson("/api/v1/admin/accounting/bank-reconciliations/$id/complete")->assertOk();
    $fresh = $rec->fresh();
    expect($fresh->status)->toBe('completed')->and($fresh->completed_by)->toBe($this->admin->id);

    $this->putJson("/api/v1/admin/accounting/bank-reconciliations/$id", ['statement_balance' => 5])->assertStatus(422);
    $this->deleteJson("/api/v1/admin/accounting/bank-reconciliations/$id")->assertStatus(422);
    $this->postJson("/api/v1/admin/accounting/bank-reconciliations/$id/toggle-line", ['line_id' => $line->id])->assertStatus(422);
    $this->postJson("/api/v1/admin/accounting/bank-reconciliations/$id/complete")->assertStatus(422);

    $this->postJson("/api/v1/admin/accounting/bank-reconciliations/$id/reopen")->assertOk();
    expect($rec->fresh()->status)->toBe('open')->and($rec->fresh()->completed_at)->toBeNull();
    $this->postJson("/api/v1/admin/accounting/bank-reconciliations/$id/reopen")->assertStatus(422);

    $this->deleteJson("/api/v1/admin/accounting/bank-reconciliations/$id")->assertOk();
    expect(BankReconciliation::find($id))->toBeNull();
});

it('updates an open reconciliation and drops ticks that fall after a moved-back statement date', function () {
    $line = ($this->deposit)(100);
    $rec = ($this->openRec)(100);
    $id = $rec->id;
    $this->postJson("/api/v1/admin/accounting/bank-reconciliations/$id/toggle-line", ['line_id' => $line->id])->assertOk();

    $this->putJson("/api/v1/admin/accounting/bank-reconciliations/$id", ['statement_balance' => 250, 'notes' => 'تصحيح'])->assertOk();
    expect((float) $rec->fresh()->statement_balance)->toBe(250.0)->and($rec->fresh()->notes)->toBe('تصحيح');
    expect($rec->clearedLines()->count())->toBe(1);

    $this->putJson("/api/v1/admin/accounting/bank-reconciliations/$id", ['statement_date' => now()->subYear()->toDateString()])->assertOk();
    expect($rec->clearedLines()->count())->toBe(0);
});

it('refuses ticking a line from another account and reopening beside another open sheet', function () {
    ($this->deposit)(100);
    $capitalLine = JournalEntryLine::where('account_id', $this->capital)->first();
    $rec = ($this->openRec)(0);
    $id = $rec->id;

    $this->postJson("/api/v1/admin/accounting/bank-reconciliations/$id/toggle-line", ['line_id' => $capitalLine->id])->assertStatus(422);
    expect($rec->clearedLines()->count())->toBe(0);

    $rec->update(['status' => 'completed']);
    ($this->openRec)(0);
    $this->postJson("/api/v1/admin/accounting/bank-reconciliations/$id/reopen")->assertStatus(422);
    expect($rec->fresh()->status)->toBe('completed');
});

it('sets budget lines, approves a budget and refuses deleting an approved one', function () {
    $expense = LedgerAccount::where('type', 'expense')->first();
    $draft = Budget::create(['name' => 'موازنة', 'fiscal_year' => 2026, 'status' => Budget::STATUS_DRAFT, 'created_by' => $this->admin->id]);
    $id = $draft->id;

    $this->postJson("/api/v1/admin/accounting/budgets/$id/lines", ['lines' => [
        ['account_id' => $expense->id, 'month' => 1, 'amount' => 500],
        ['account_id' => $expense->id, 'month' => 2, 'amount' => 700],
    ]])->assertOk();
    expect(BudgetLine::where('budget_id', $id)->count())->toBe(2);

    // Same account+month updates rather than duplicating.
    $this->postJson("/api/v1/admin/accounting/budgets/$id/lines", ['lines' => [
        ['account_id' => $expense->id, 'month' => 1, 'amount' => 900],
    ]])->assertOk();
    expect(BudgetLine::where('budget_id', $id)->count())->toBe(2);
    expect((float) BudgetLine::where('budget_id', $id)->where('month', 1)->value('amount'))->toBe(900.0);

    $this->postJson("/api/v1/admin/accounting/budgets/$id/lines", ['lines' => [
        ['account_id' => $expense->id, 'month' => 13, 'amount' => 1],
    ]])->assertStatus(422);

    $this->postJson("/api/v1/admin/accounting/budgets/$id/approve")->assertOk();
    expect($draft->fresh()->status)->toBe(Budget::STATUS_APPROVED);

    $this->deleteJson("/api/v1/admin/accounting/budgets/$id")->assertStatus(422);
    expect(Budget::find($id))->not->toBeNull();
});

it('deletes a draft budget', function () {
    $draft = Budget::create(['name' => 'مسودة', 'fiscal_year' => 2026, 'status' => Budget::STATUS_DRAFT, 'created_by' => $this->admin->id]);
    $id = $draft->id;

    $this->deleteJson("/api/v1/admin/accounting/budgets/$id")->assertOk();
    expect(Budget::find($id))->toBeNull();
});

it('disposes of a fixed asset, posts the entry and refuses a second disposal', function () {
    $asset = FixedAsset::create([
        'asset_number' => 'FA-T1', 'name' => 'آلة', 'acquired_on' => now()->subMonths(3)->toDateString(),
        'cost' => 1200, 'salvage_value' => 0, 'useful_life_months' => 12,
        'accumulated_depreciation' => 0, 'status' => FixedAsset::STATUS_ACTIVE, 'created_by' => $this->admin->id,
    ]);
    $id = $asset->id;
    $before = JournalEntryHeader::count();

    $this->postJson("/api/v1/admin/accounting/fixed-assets/$id/dispose", ['disposed_on' => now()->addDay()->toDateString()])->assertStatus(422);
    expect($asset->fresh()->status)->toBe(FixedAsset::STATUS_ACTIVE);

    $this->postJson("/api/v1/admin/accounting/fixed-assets/$id/dispose", [
        'disposed_on' => now()->toDateString(), 'proceeds' => 500, 'settlement' => 'cash', 'charge_to_date' => false,
    ])->assertOk();

    $fresh = $asset->fresh();
    expect($fresh->status)->toBe(FixedAsset::STATUS_DISPOSED)
        ->and((float) $fresh->disposal_proceeds)->toBe(500.0)
        ->and($fresh->disposed_on->toDateString())->toBe(now()->toDateString());
    expect(JournalEntryHeader::count())->toBeGreaterThan($before);

    $this->postJson("/api/v1/admin/accounting/fixed-assets/$id/dispose", [])->assertStatus(422);
});

it('cancels an unused credit note and refuses one that was already used', function () {
    $customer = Customer::create(['name' => 'عميل', 'email' => 'cn@example.test', 'status' => 'active']);
    $make = fn (string $n, array $extra = []) => CreditNote::create([
        'credit_note_number' => $n, 'customer_id' => $customer->id, 'issue_date' => now()->toDateString(),
        'subtotal' => 100, 'tax' => 0, 'total' => 100, 'status' => CreditNote::STATUS_ISSUED,
    ] + $extra);

    $unused = $make('CN-T1');
    $id = $unused->id;
    $this->postJson("/api/v1/admin/credit-notes/$id/cancel")->assertOk();
    expect($unused->fresh()->status)->toBe(CreditNote::STATUS_CANCELLED);

    $used = $make('CN-T2', ['applied_to_invoice' => 40, 'status' => CreditNote::STATUS_PARTIALLY_APPLIED]);
    $usedId = $used->id;
    $this->postJson("/api/v1/admin/credit-notes/$usedId/cancel")->assertStatus(422);
    expect($used->fresh()->status)->not->toBe(CreditNote::STATUS_CANCELLED);
});
