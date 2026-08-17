<?php

use App\Models\Employee;
use App\Models\JournalEntryHeader;
use App\Models\LedgerAccount;
use App\Models\Payroll;
use App\Models\Role;
use App\Models\User;

/**
 * Wages, and when they belong to the books.
 *
 * Payroll ran entirely outside the ledger: salaries were computed, stored and
 * marked paid without a single journal entry. The chart even carried a
 * salaries expense account that nothing ever posted to. So the largest
 * recurring cost the business has appeared in no income statement, and the
 * cash it consumed left the books with nothing to explain it.
 *
 * The split between accrual and payment is the point of these tests. A cost
 * recognised only when the transfer clears is dated to payday, so a month
 * closed before wages went out reports no salaries at all.
 */
beforeEach(function () {
    $this->admin = User::factory()->create([
        'is_admin' => true,
        'role_id' => Role::firstOrCreate(['name' => 'admin'], ['display_name' => 'admin'])->id,
    ]);

    $this->employee = Employee::create([
        'first_name' => 'سامر',
        'last_name' => 'الأحمد',
        'email' => 'samer@example.test',
        'position' => 'مندوب',
        'salary' => 1000,
        'hire_date' => now()->subYear()->toDateString(),
        'status' => 'active',
    ]);

    $this->payroll = function (array $overrides = []): Payroll {
        $payroll = Payroll::create(array_merge([
            'payroll_number' => 'PAY-'.str_pad((string) (((int) Payroll::max('id')) + 1), 6, '0', STR_PAD_LEFT),
            'employee_id' => $this->employee->id,
            // Last month: a payroll is normally processed and paid after the
            // period it covers has ended, and `payment_date` is validated
            // against that end date.
            'pay_period_start' => now()->subMonth()->startOfMonth()->toDateString(),
            'pay_period_end' => now()->subMonth()->endOfMonth()->toDateString(),
            'basic_salary' => 1000,
            'overtime_hours' => 0,
            'overtime_rate' => 0,
            'bonuses' => 200,
            'deductions' => 150,
            'status' => Payroll::STATUS_PENDING,
        ], $overrides));

        $payroll->calculateNetSalary();
        $payroll->save();

        return $payroll;
    };

    /** Movement on an account within an entry, on its normal side. */
    $this->movementOn = function (JournalEntryHeader $entry, string $role): float {
        $accountId = LedgerAccount::where('posting_role', $role)->value('id');
        $line = $entry->lines->firstWhere('account_id', $accountId);

        return $line ? round((float) $line->debit - (float) $line->credit, 2) : 0.0;
    };

    /** The payload the update endpoint expects for a payroll not yet posted. */
    $this->payloadFor = fn (Payroll $payroll, array $overrides = []) => array_merge([
        'employee_id' => $payroll->employee_id,
        'pay_period_start' => $payroll->pay_period_start->toDateString(),
        'pay_period_end' => $payroll->pay_period_end->toDateString(),
        'basic_salary' => (float) $payroll->basic_salary,
        'bonuses' => (float) $payroll->bonuses,
        'deductions' => (float) $payroll->deductions,
        'status' => Payroll::STATUS_PROCESSED,
    ], $overrides);
});

test('processing a payroll recognises the gross cost and what it owes', function () {
    $payroll = ($this->payroll)();

    $this->actingAs($this->admin)
        ->putJson('/api/v1/payrolls/'.$payroll->id, ($this->payloadFor)($payroll))
        ->assertOk();

    $entry = JournalEntryHeader::with('lines')->where('posting_key', $payroll->accrualKey())->first();

    expect($entry)->not->toBeNull();
    // Gross is the whole cost to the business; the deduction only decides who
    // ends up holding part of it.
    expect(($this->movementOn)($entry, 'salaries_expense'))->toBe(1200.0);
    expect(($this->movementOn)($entry, 'payroll_deductions_payable'))->toBe(-150.0);
    expect(($this->movementOn)($entry, 'salaries_payable'))->toBe(-1050.0);
    expect(round((float) $entry->total_debit, 2))->toBe(round((float) $entry->total_credit, 2));
});

test('the cost is dated to the period worked, not to when it was processed', function () {
    $payroll = ($this->payroll)([
        'pay_period_start' => '2026-05-01',
        'pay_period_end' => '2026-05-31',
    ]);

    $this->actingAs($this->admin)
        ->putJson('/api/v1/payrolls/'.$payroll->id, ($this->payloadFor)($payroll))
        ->assertOk();

    $entry = JournalEntryHeader::where('posting_key', $payroll->accrualKey())->first();

    expect($entry->entry_date->toDateString())->toBe('2026-05-31');
});

test('paying the wage settles the liability without touching the expense again', function () {
    $payroll = ($this->payroll)();

    $this->actingAs($this->admin)
        ->putJson('/api/v1/payrolls/'.$payroll->id, ($this->payloadFor)($payroll))
        ->assertOk();

    $this->actingAs($this->admin)
        ->putJson('/api/v1/payrolls/'.$payroll->id, [
            'status' => Payroll::STATUS_PAID,
            'payment_date' => now()->toDateString(),
            'payment_method' => 'cash',
        ])
        ->assertOk();

    $payment = JournalEntryHeader::with('lines')->where('posting_key', $payroll->paymentKey())->first();

    expect($payment)->not->toBeNull();
    expect(($this->movementOn)($payment, 'salaries_payable'))->toBe(1050.0);
    expect(($this->movementOn)($payment, 'cash'))->toBe(-1050.0);

    // The liability raised by the accrual is now settled, and the expense was
    // recognised once.
    $payableBalance = (float) LedgerAccount::where('posting_role', 'salaries_payable')->value('balance');
    expect(round($payableBalance, 2))->toBe(0.0);
    expect(JournalEntryHeader::where('posting_key', $payroll->accrualKey())->count())->toBe(1);
});

test('a bank payment leaves the till alone', function () {
    $payroll = ($this->payroll)();

    $this->actingAs($this->admin)
        ->putJson('/api/v1/payrolls/'.$payroll->id, ($this->payloadFor)($payroll, [
            'status' => Payroll::STATUS_PAID,
            'payment_date' => now()->toDateString(),
            'payment_method' => 'bank_transfer',
        ]))
        ->assertOk();

    $payment = JournalEntryHeader::with('lines')->where('posting_key', $payroll->paymentKey())->first();

    expect(($this->movementOn)($payment, 'bank'))->toBe(-1050.0);
    expect(($this->movementOn)($payment, 'cash'))->toBe(0.0);
});

test('a payroll paid without passing through processed still recognises its cost', function () {
    $payroll = ($this->payroll)();

    $this->actingAs($this->admin)
        ->putJson('/api/v1/payrolls/'.$payroll->id, ($this->payloadFor)($payroll, [
            'status' => Payroll::STATUS_PAID,
            'payment_date' => now()->toDateString(),
        ]))
        ->assertOk();

    expect(JournalEntryHeader::where('posting_key', $payroll->accrualKey())->exists())->toBeTrue();
    expect(JournalEntryHeader::where('posting_key', $payroll->paymentKey())->exists())->toBeTrue();
});

test('the salary of a posted payroll can no longer be changed', function () {
    $payroll = ($this->payroll)();

    $this->actingAs($this->admin)
        ->putJson('/api/v1/payrolls/'.$payroll->id, ($this->payloadFor)($payroll))
        ->assertOk();

    $this->actingAs($this->admin)
        ->putJson('/api/v1/payrolls/'.$payroll->id, ($this->payloadFor)($payroll, [
            'basic_salary' => 99999,
        ]))
        ->assertOk();

    // The figure is ignored rather than accepted: the ledger already carries
    // the cost, and the two must not describe different amounts.
    expect(round((float) $payroll->fresh()->basic_salary, 2))->toBe(1000.0);
});

test('a posted payroll cannot be sent back to pending', function () {
    $payroll = ($this->payroll)();

    $this->actingAs($this->admin)
        ->putJson('/api/v1/payrolls/'.$payroll->id, ($this->payloadFor)($payroll))
        ->assertOk();

    $this->actingAs($this->admin)
        ->putJson('/api/v1/payrolls/'.$payroll->id, ['status' => Payroll::STATUS_PENDING])
        ->assertStatus(422);

    expect($payroll->fresh()->status)->toBe(Payroll::STATUS_PROCESSED);
});

test('a posted payroll cannot be deleted, an unposted one can', function () {
    $posted = ($this->payroll)();
    $draft = ($this->payroll)();

    $this->actingAs($this->admin)
        ->putJson('/api/v1/payrolls/'.$posted->id, ($this->payloadFor)($posted))
        ->assertOk();

    $this->actingAs($this->admin)->deleteJson('/api/v1/payrolls/'.$posted->id)->assertStatus(422);
    $this->actingAs($this->admin)->deleteJson('/api/v1/payrolls/'.$draft->id)->assertOk();

    expect(Payroll::find($posted->id))->not->toBeNull();
    expect(Payroll::find($draft->id))->toBeNull();
});

test('processing the same payroll twice posts one cost', function () {
    $payroll = ($this->payroll)();

    foreach (range(1, 2) as $ignored) {
        $this->actingAs($this->admin)
            ->putJson('/api/v1/payrolls/'.$payroll->id, ($this->payloadFor)($payroll))
            ->assertOk();
    }

    expect(JournalEntryHeader::where('posting_key', $payroll->accrualKey())->count())->toBe(1);

    $expense = (float) LedgerAccount::where('posting_role', 'salaries_expense')->value('balance');
    expect(round($expense, 2))->toBe(1200.0);
});

test('a payroll number is not handed out twice after an earlier one is deleted', function () {
    $first = ($this->payroll)();
    $second = ($this->payroll)();

    // Counting rows to build the next number is what broke here: with two
    // payrolls on file and the first one removed, the count says one, so the
    // next run claims a number the second payroll already holds — and the
    // unique index rejects the whole payroll run.
    $first->delete();

    $response = $this->actingAs($this->admin)->postJson('/api/v1/payrolls', [
        'employee_id' => $this->employee->id,
        'pay_period_start' => now()->subMonth()->startOfMonth()->toDateString(),
        'pay_period_end' => now()->subMonth()->endOfMonth()->toDateString(),
        'basic_salary' => 800,
    ])->assertCreated();

    expect($response->json('data.payroll_number'))->not->toBe($second->payroll_number);
});
