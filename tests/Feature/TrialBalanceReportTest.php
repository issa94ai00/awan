<?php

use App\Models\LedgerAccount;
use App\Models\Role;
use App\Models\User;
use App\Services\Accounting\LedgerPostingService;

/**
 * The trial balance reports opening, period movement and closing per account,
 * and leaves unposted entries out like every other report.
 */
beforeEach(function () {
    $this->admin = User::factory()->create([
        'is_admin' => true,
        'role_id' => Role::firstOrCreate(['name' => 'admin'], ['display_name' => 'admin'])->id,
    ]);

    $this->cash = LedgerAccount::where('posting_role', 'cash')->value('id');
    $this->capital = LedgerAccount::where('posting_role', 'capital')->value('id');

    $this->post = fn (string $key, string $date, float $amount) => app(LedgerPostingService::class)->post(
        key: $key,
        date: $date,
        description: $key,
        lines: [
            ['account_id' => $this->cash, 'debit' => $amount, 'credit' => 0],
            ['account_id' => $this->capital, 'debit' => 0, 'credit' => $amount],
        ],
        module: 'manual',
    );

    $this->report = fn (string $from, string $to) => collect(
        $this->actingAs($this->admin)
            ->getJson("/api/v1/admin/accounting/trial-balance?date_from={$from}&date_to={$to}")
            ->assertOk()->json('data')
    );
});

it('splits an account into opening, period movement and closing', function () {
    ($this->post)('before', '2026-01-15', 1000);
    ($this->post)('during', '2026-03-10', 250);

    $data = ($this->report)('2026-03-01', '2026-03-31');
    $cash = collect($data['accounts'])->firstWhere('id', $this->cash);
    $capital = collect($data['accounts'])->firstWhere('id', $this->capital);

    expect($cash['opening_debit'])->toEqual(1000)
        ->and($cash['debits'])->toEqual(250)
        ->and($cash['closing_debit'])->toEqual(1250)
        ->and($cash['closing_credit'])->toEqual(0)
        ->and($cash['closing_balance'])->toEqual(1250);

    expect($capital['opening_credit'])->toEqual(1000)
        ->and($capital['closing_credit'])->toEqual(1250)
        ->and($capital['closing_balance'])->toEqual(1250);

    expect($data['totals']['closing_debits'])->toEqual($data['totals']['closing_credits'])
        ->and($data['closing_difference'])->toEqual(0);
});

it('lists an account with an opening balance even when the period is quiet', function () {
    ($this->post)('old', '2026-01-15', 400);

    $data = ($this->report)('2026-05-01', '2026-05-31');

    expect(collect($data['accounts'])->pluck('id'))->toContain($this->cash);
});

it('leaves draft entries out of the trial balance', function () {
    $entry = ($this->post)('draft', '2026-03-10', 900);
    $entry->update(['status' => 'draft']);

    $data = ($this->report)('2026-03-01', '2026-03-31');

    expect(collect($data['accounts'])->pluck('id'))->not->toContain($this->cash)
        ->and($data['totals']['debits'])->toEqual(0);
});
