<?php

use App\Models\JournalEntryHeader;
use App\Models\LedgerAccount;
use App\Models\Role;
use App\Models\User;

/** Posted entries are never edited or deleted — only reversed. */
beforeEach(function () {
    $this->admin = User::factory()->create([
        'is_admin' => true,
        'role_id' => Role::firstOrCreate(['name' => 'admin'], ['display_name' => 'admin'])->id,
    ]);

    $this->postEntry = function (): array {
        $cash = LedgerAccount::where('posting_role', 'cash')->value('id');
        $capital = LedgerAccount::where('posting_role', 'capital')->value('id');

        return $this->actingAs($this->admin)->postJson('/api/v1/admin/accounting/journal-entries', [
            'entry_date' => now()->toDateString(),
            'description' => 'قيد يدوي',
            'lines' => [
                ['ledger_account_id' => $cash, 'debit' => 300, 'credit' => 0],
                ['ledger_account_id' => $capital, 'debit' => 0, 'credit' => 300],
            ],
        ])->assertCreated()->json('data');
    };
});

it('refuses to edit a posted entry', function () {
    $id = ($this->postEntry)()['id'];

    $this->actingAs($this->admin)->putJson("/api/v1/admin/accounting/journal-entries/$id", ['description' => 'x'])
        ->assertStatus(422);

    expect(JournalEntryHeader::find($id)->description)->toBe('قيد يدوي');
});

it('refuses to delete a posted entry', function () {
    $id = ($this->postEntry)()['id'];

    $this->actingAs($this->admin)->deleteJson("/api/v1/admin/accounting/journal-entries/$id")
        ->assertStatus(422);

    expect(JournalEntryHeader::find($id))->not->toBeNull();
});

it('reverses an entry once, restoring balances, and refuses a second reversal', function () {
    $before = (float) LedgerAccount::where('posting_role', 'cash')->value('balance');
    $id = ($this->postEntry)()['id'];

    $this->actingAs($this->admin)->postJson("/api/v1/admin/accounting/journal-entries/$id/reverse")
        ->assertCreated();

    expect(JournalEntryHeader::find($id)->status)->toBe('reversed')
        ->and(round((float) LedgerAccount::where('posting_role', 'cash')->value('balance'), 2))->toBe(round($before, 2));

    $this->actingAs($this->admin)->postJson("/api/v1/admin/accounting/journal-entries/$id/reverse")
        ->assertStatus(422);
});
