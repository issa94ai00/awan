<?php

use App\Models\LedgerAccount;
use App\Models\Role;
use App\Models\User;

/**
 * The journal screen searches by entry number or narration and filters by the
 * module that posted the entry; both are answered by the list endpoint.
 */
beforeEach(function () {
    $this->admin = User::factory()->create([
        'is_admin' => true,
        'role_id' => Role::firstOrCreate(['name' => 'admin'], ['display_name' => 'admin'])->id,
    ]);

    $cash = LedgerAccount::where('posting_role', 'cash')->value('id');
    $capital = LedgerAccount::where('posting_role', 'capital')->value('id');

    $this->post = fn (string $description) => $this->actingAs($this->admin)
        ->postJson('/api/v1/admin/accounting/journal-entries', [
            'entry_date' => now()->toDateString(),
            'description' => $description,
            'lines' => [
                ['ledger_account_id' => $cash, 'debit' => 100, 'credit' => 0],
                ['ledger_account_id' => $capital, 'debit' => 0, 'credit' => 100],
            ],
        ])->assertCreated()->json('data');
});

it('searches entries by narration and by entry number', function () {
    ($this->post)('إيداع رأس المال');
    $rent = ($this->post)('Office rent 100%');

    $byText = $this->actingAs($this->admin)
        ->getJson('/api/v1/admin/accounting/journal-entries?search=rent')
        ->assertOk()->json('data.entries');
    expect(collect($byText)->pluck('id')->all())->toBe([$rent['id']]);

    $byNumber = $this->actingAs($this->admin)
        ->getJson('/api/v1/admin/accounting/journal-entries?search='.urlencode($rent['entry_number']))
        ->assertOk()->json('data.entries');
    expect(collect($byNumber)->pluck('id')->all())->toBe([$rent['id']]);

    // LIKE wildcards in the term are matched literally, not as "anything".
    $literal = $this->actingAs($this->admin)
        ->getJson('/api/v1/admin/accounting/journal-entries?search='.urlencode('100%'))
        ->assertOk()->json('data.entries');
    expect(collect($literal)->pluck('id')->all())->toBe([$rent['id']]);
});

it('filters entries by source module', function () {
    ($this->post)('قيد يدوي');

    $this->actingAs($this->admin)
        ->getJson('/api/v1/admin/accounting/journal-entries?source_module=manual')
        ->assertOk()->assertJsonCount(1, 'data.entries');

    $this->actingAs($this->admin)
        ->getJson('/api/v1/admin/accounting/journal-entries?source_module=sales')
        ->assertOk()->assertJsonCount(0, 'data.entries');
});
