<?php

use App\Models\CostCenter;
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
});

it('updates and deletes an unused ledger account', function () {
    $a = LedgerAccount::create(['code' => 'T-100', 'name' => 'حساب', 'type' => 'asset', 'balance' => 0, 'is_active' => true]);

    $this->putJson("/api/v1/admin/accounting/ledger-accounts/{$a->id}", ['code' => 'T-100', 'name' => 'اسم جديد', 'type' => 'asset'])
        ->assertOk();
    expect($a->fresh()->name)->toBe('اسم جديد');

    $this->deleteJson("/api/v1/admin/accounting/ledger-accounts/{$a->id}")->assertSuccessful();
    expect(LedgerAccount::find($a->id))->toBeNull();
});

it('refuses a type change or deletion once an account has posted lines', function () {
    $cash = LedgerAccount::where('posting_role', 'cash')->first();
    $capital = LedgerAccount::where('posting_role', 'capital')->value('id');
    $this->postJson('/api/v1/admin/accounting/journal-entries', [
        'entry_date' => now()->toDateString(),
        'lines' => [
            ['ledger_account_id' => $cash->id, 'debit' => 50, 'credit' => 0],
            ['ledger_account_id' => $capital, 'debit' => 0, 'credit' => 50],
        ],
    ])->assertCreated();

    $this->putJson("/api/v1/admin/accounting/ledger-accounts/{$cash->id}", ['code' => $cash->code, 'name' => $cash->name, 'type' => 'expense'])
        ->assertStatus(422);
    $this->deleteJson("/api/v1/admin/accounting/ledger-accounts/{$cash->id}")->assertStatus(422);
    expect(LedgerAccount::find($cash->id))->not->toBeNull();
});

it('updates a cost center and deletes it while unused', function () {
    $c = CostCenter::create(['code' => 'CC1', 'name' => 'مركز', 'is_active' => true]);

    $this->putJson("/api/v1/admin/accounting/cost-centers/{$c->id}", ['code' => 'CC1', 'name' => 'مركز معدل'])->assertOk();
    expect($c->fresh()->name)->toBe('مركز معدل');

    $this->deleteJson("/api/v1/admin/accounting/cost-centers/{$c->id}")->assertSuccessful();
    expect(CostCenter::find($c->id))->toBeNull();
});

it('refuses to delete a cost center that has posted lines', function () {
    $c = CostCenter::create(['code' => 'CC2', 'name' => 'مركز', 'is_active' => true]);
    $cash = LedgerAccount::where('posting_role', 'cash')->value('id');
    $capital = LedgerAccount::where('posting_role', 'capital')->value('id');
    $this->postJson('/api/v1/admin/accounting/journal-entries', [
        'entry_date' => now()->toDateString(),
        'lines' => [
            ['ledger_account_id' => $cash, 'debit' => 10, 'credit' => 0],
            ['ledger_account_id' => $capital, 'debit' => 0, 'credit' => 10],
        ],
    ])->assertCreated();
    JournalEntryLine::query()->limit(1)->update(['cost_center_id' => $c->id]);

    $this->deleteJson("/api/v1/admin/accounting/cost-centers/{$c->id}")->assertStatus(422);
});
