<?php

use App\Models\Employee;
use App\Models\JournalEntryHeader;
use App\Models\JournalEntryLine;
use App\Models\LedgerAccount;
use App\Models\User;
use Laravel\Sanctum\Sanctum;

/**
 * A cash advance handed to a sales rep is real money leaving the business,
 * not just a line in the commission sub-ledger. These tests hold the posting
 * service to the same rule every other document follows: what is recorded
 * here must actually move the books.
 */
beforeEach(function () {
    $this->user = User::factory()->admin()->create();
    $this->token = $this->user->createToken('test-token')->plainTextToken;
    $this->auth = ['Authorization' => "Bearer {$this->token}"];

    $this->employee = Employee::create([
        'name' => 'وائل نصار',
        'email' => 'wael@example.com',
        'phone' => '+963900000003',
        'position' => 'مندوب مبيعات',
        'department' => 'المبيعات',
        'hire_date' => now()->subYear()->toDateString(),
        'salary' => 3000,
        'status' => 'نشط',
    ]);

    $this->commission = $this->postJson('/api/v1/admin/employee-commissions', [
        'employee_id' => $this->employee->id,
        'month' => now()->startOfMonth()->toDateString(),
        'commission_rate' => 5,
    ], $this->auth)->json('data');
});

function withdrawalLedgerUrl($id, $suffix = '')
{
    return "/api/v1/admin/employee-commissions/{$id}/withdrawals{$suffix}";
}

test('recording a withdrawal posts a balanced entry against employee advances and cash', function () {
    $response = $this->postJson(withdrawalLedgerUrl($this->commission['id']), [
        'withdrawn_at' => now()->toDateTimeString(),
        'currency_code' => 'USD',
        'amount' => 500,
        'method' => 'cash',
        'reason' => 'سلفة',
    ], $this->auth);

    $response->assertStatus(201);
    $withdrawalId = $response->json('data.id');

    $entry = JournalEntryHeader::where('posting_key', "employee_commission_withdrawal:{$withdrawalId}")->first();
    expect($entry)->not->toBeNull();
    expect((float) $entry->total_debit)->toBe(500.0);
    expect((float) $entry->total_credit)->toBe(500.0);
    expect($entry->status)->toBe('posted');

    $advancesAccount = LedgerAccount::where('posting_role', 'employee_advances')->first();
    $cashAccount = LedgerAccount::where('posting_role', 'cash')->first();

    $debitLine = JournalEntryLine::where('journal_entry_header_id', $entry->id)
        ->where('account_id', $advancesAccount->id)->first();
    $creditLine = JournalEntryLine::where('journal_entry_header_id', $entry->id)
        ->where('account_id', $cashAccount->id)->first();

    expect((float) $debitLine->debit)->toBe(500.0);
    expect((float) $creditLine->credit)->toBe(500.0);
});

test('a bank withdrawal credits the bank account instead of cash', function () {
    $response = $this->postJson(withdrawalLedgerUrl($this->commission['id']), [
        'withdrawn_at' => now()->toDateTimeString(),
        'currency_code' => 'USD',
        'amount' => 200,
        'method' => 'bank',
    ], $this->auth);

    $withdrawalId = $response->json('data.id');
    $entry = JournalEntryHeader::where('posting_key', "employee_commission_withdrawal:{$withdrawalId}")->first();
    $bankAccount = LedgerAccount::where('posting_role', 'bank')->first();

    $creditLine = JournalEntryLine::where('journal_entry_header_id', $entry->id)
        ->where('account_id', $bankAccount->id)->first();

    expect($creditLine)->not->toBeNull();
    expect((float) $creditLine->credit)->toBe(200.0);
});

test('deleting a withdrawal reverses its ledger entry rather than erasing it', function () {
    $created = $this->postJson(withdrawalLedgerUrl($this->commission['id']), [
        'withdrawn_at' => now()->toDateTimeString(),
        'currency_code' => 'USD',
        'amount' => 300,
        'method' => 'cash',
    ], $this->auth)->json('data');

    $this->deleteJson(withdrawalLedgerUrl($this->commission['id'], "/{$created['id']}"), [], $this->auth)
        ->assertStatus(200);

    $original = JournalEntryHeader::where('posting_key', "employee_commission_withdrawal:{$created['id']}")->first();
    expect($original->status)->toBe('reversed');

    $reversal = JournalEntryHeader::where('posting_key', "employee_commission_withdrawal:{$created['id']}:reversal")->first();
    expect($reversal)->not->toBeNull();
    expect((float) $reversal->total_debit)->toBe(300.0);

    $this->assertSoftDeleted('employee_commission_withdrawals', ['id' => $created['id']]);
    $this->assertDatabaseHas('employee_commissions', ['id' => $this->commission['id'], 'withdrawals' => 0]);
});

test('restoring a deleted withdrawal un-hides it without re-posting the reversed entry', function () {
    $created = $this->postJson(withdrawalLedgerUrl($this->commission['id']), [
        'withdrawn_at' => now()->toDateTimeString(),
        'currency_code' => 'USD',
        'amount' => 300,
        'method' => 'cash',
    ], $this->auth)->json('data');

    $this->deleteJson(withdrawalLedgerUrl($this->commission['id'], "/{$created['id']}"), [], $this->auth);

    $restore = $this->putJson(withdrawalLedgerUrl($this->commission['id'], "/{$created['id']}/restore"), [], $this->auth);
    $restore->assertStatus(200);

    $this->assertDatabaseHas('employee_commission_withdrawals', ['id' => $created['id'], 'deleted_at' => null]);

    // The ledger entry stays reversed — restoring only brings the row back
    // into view, it does not undo the reversal.
    $original = JournalEntryHeader::where('posting_key', "employee_commission_withdrawal:{$created['id']}")->first();
    expect($original->status)->toBe('reversed');
});

test('deleting the parent commission record reverses every active withdrawal ledger entry too', function () {
    $created = $this->postJson(withdrawalLedgerUrl($this->commission['id']), [
        'withdrawn_at' => now()->toDateTimeString(),
        'currency_code' => 'USD',
        'amount' => 250,
        'method' => 'bank',
    ], $this->auth)->json('data');

    $this->deleteJson("/api/v1/admin/employee-commissions/{$this->commission['id']}", [], $this->auth)
        ->assertStatus(200);

    $entry = JournalEntryHeader::where('posting_key', "employee_commission_withdrawal:{$created['id']}")->first();
    expect($entry->status)->toBe('reversed');

    $this->assertSoftDeleted('employee_commission_withdrawals', ['id' => $created['id']]);
    $this->assertSoftDeleted('employee_commissions', ['id' => $this->commission['id']]);
});

test('restoring the parent commission record does not resurrect its withdrawals, and settles the total to zero', function () {
    $created = $this->postJson(withdrawalLedgerUrl($this->commission['id']), [
        'withdrawn_at' => now()->toDateTimeString(),
        'currency_code' => 'USD',
        'amount' => 80,
        'method' => 'cash',
    ], $this->auth)->json('data');

    $this->deleteJson("/api/v1/admin/employee-commissions/{$this->commission['id']}", [], $this->auth);
    $restore = $this->putJson("/api/v1/admin/employee-commissions/{$this->commission['id']}/restore", [], $this->auth);
    $restore->assertStatus(200);

    $this->assertDatabaseHas('employee_commissions', ['id' => $this->commission['id'], 'deleted_at' => null]);
    // The withdrawal stays in the trash — restoring the statement only
    // un-hides the statement itself, not the ledger reversal underneath it.
    // Restore it separately if the advance genuinely needs to come back.
    $this->assertSoftDeleted('employee_commission_withdrawals', ['id' => $created['id']]);

    // The financial settlement: `withdrawals` no longer carries the frozen
    // pre-delete snapshot (80) once restored — it is re-summed against the
    // live ledger, which has nothing active, so it settles to zero.
    $restore->assertJsonPath('data.withdrawals', '0.00');
    $this->assertDatabaseHas('employee_commissions', ['id' => $this->commission['id'], 'withdrawals' => 0]);
});

test('restoring the parent commission record settles withdrawals to include one restored independently first', function () {
    $created = $this->postJson(withdrawalLedgerUrl($this->commission['id']), [
        'withdrawn_at' => now()->toDateTimeString(),
        'currency_code' => 'USD',
        'amount' => 80,
        'method' => 'cash',
    ], $this->auth)->json('data');

    $this->deleteJson("/api/v1/admin/employee-commissions/{$this->commission['id']}", [], $this->auth);

    // The withdrawal is un-hidden on its own, before the statement itself
    // is restored.
    $this->putJson(withdrawalLedgerUrl($this->commission['id'], "/{$created['id']}/restore"), [], $this->auth)
        ->assertStatus(200);

    $restore = $this->putJson("/api/v1/admin/employee-commissions/{$this->commission['id']}/restore", [], $this->auth);
    $restore->assertStatus(200);

    $restore->assertJsonPath('data.withdrawals', '80.00');
    $this->assertDatabaseHas('employee_commissions', ['id' => $this->commission['id'], 'withdrawals' => 80]);
});

test('a non-admin cannot list or restore trashed withdrawals', function () {
    $created = $this->postJson(withdrawalLedgerUrl($this->commission['id']), [
        'withdrawn_at' => now()->toDateTimeString(),
        'currency_code' => 'USD',
        'amount' => 150,
        'method' => 'cash',
    ], $this->auth)->json('data');

    $this->deleteJson(withdrawalLedgerUrl($this->commission['id'], "/{$created['id']}"), [], $this->auth);

    // See the equivalent note in EmployeeCommissionApiTest: Sanctum's
    // RequestGuard caches the user resolved from the first Bearer header in
    // a test, so switching identities mid-test needs Sanctum::actingAs().
    $staff = User::factory()->create(['is_admin' => false]);
    Sanctum::actingAs($staff);

    $this->getJson(withdrawalLedgerUrl($this->commission['id'], '/trashed'))->assertStatus(403);
    $this->putJson(withdrawalLedgerUrl($this->commission['id'], "/{$created['id']}/restore"))->assertStatus(403);
});

test('a withdrawal already posted to the ledger cannot have its amount edited', function () {
    $created = $this->postJson(withdrawalLedgerUrl($this->commission['id']), [
        'withdrawn_at' => now()->toDateTimeString(),
        'currency_code' => 'USD',
        'amount' => 400,
        'method' => 'cash',
    ], $this->auth)->json('data');

    $response = $this->putJson(withdrawalLedgerUrl($this->commission['id'], "/{$created['id']}"), [
        'withdrawn_at' => now()->toDateTimeString(),
        'currency_code' => 'USD',
        'amount' => 999,
        'method' => 'cash',
    ], $this->auth);

    $response->assertStatus(422);
    $this->assertDatabaseHas('employee_commission_withdrawals', ['id' => $created['id'], 'amount' => 400]);
});
