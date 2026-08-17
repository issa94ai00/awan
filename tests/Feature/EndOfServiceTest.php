<?php

use App\Models\Employee;
use App\Models\JournalEntryHeader;
use App\Models\LedgerAccount;
use App\Models\Payroll;
use App\Models\Role;
use App\Models\User;

/**
 * The wage that is earned every month and paid only at the end.
 *
 * Recognising an end-of-service benefit on the day somebody leaves puts years
 * of cost into one month, and until then the balance sheet says nothing about
 * a debt the business has already run up — so it can look solvent while owing
 * its staff a year of wages.
 *
 * Also covers what a deduction actually is. Every deduction used to sit on one
 * neutral liability because the record gave no way to tell them apart, which
 * left the employee-advances asset standing forever against money that had
 * already come back through the payroll.
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
        'salary' => 1200,
        'hire_date' => '2025-06-01',
        'status' => 'active',
    ]);

    $this->balanceOf = fn (string $role) => round(
        (float) LedgerAccount::where('posting_role', $role)->value('balance'),
        2
    );
});

test('a month of service builds up what will be owed at the end', function () {
    $this->artisan('accounting:accrue-end-of-service --month=2026-01')->assertSuccessful();

    // A twelfth of the salary: one month per year of service.
    expect(($this->balanceOf)('end_of_service_expense'))->toBe(100.0);
    expect(($this->balanceOf)('end_of_service_payable'))->toBe(100.0);
    expect(round((float) $this->employee->fresh()->end_of_service_accrued, 2))->toBe(100.0);
});

test('accruing the same month twice does not double the liability', function () {
    $this->artisan('accounting:accrue-end-of-service --month=2026-01')->assertSuccessful();
    $this->artisan('accounting:accrue-end-of-service --month=2026-01')->assertSuccessful();

    // Like depreciation, this has no document behind it to notice a duplicate.
    expect(($this->balanceOf)('end_of_service_expense'))->toBe(100.0);
    expect(round((float) $this->employee->fresh()->end_of_service_accrued, 2))->toBe(100.0);
});

test('the charge belongs to the month that was worked', function () {
    $this->artisan('accounting:accrue-end-of-service --month=2026-02')->assertSuccessful();

    $entry = JournalEntryHeader::where('posting_key', 'eos_accrual:'.$this->employee->id.':2026-02')->first();

    expect($entry->entry_date->toDateString())->toBe('2026-02-28');
});

test('nothing accrues for a month before the employee was hired', function () {
    $this->artisan('accounting:accrue-end-of-service --month=2025-01')->assertSuccessful();

    expect(($this->balanceOf)('end_of_service_expense'))->toBe(0.0);
});

test('paying the benefit settles the liability without charging the cost again', function () {
    foreach (['2026-01', '2026-02', '2026-03'] as $month) {
        $this->artisan('accounting:accrue-end-of-service --month='.$month)->assertSuccessful();
    }

    expect(($this->balanceOf)('end_of_service_payable'))->toBe(300.0);

    $this->actingAs($this->admin)
        ->postJson('/api/v1/employees/'.$this->employee->id.'/end-of-service', [
            'settlement' => 'cash',
        ])->assertOk();

    expect(($this->balanceOf)('end_of_service_payable'))->toBe(0.0);
    expect(($this->balanceOf)('cash'))->toBe(-300.0);
    // The cost was recognised in the months that earned it, not again here.
    expect(($this->balanceOf)('end_of_service_expense'))->toBe(300.0);
});

test('paying more than was accrued is refused', function () {
    $this->artisan('accounting:accrue-end-of-service --month=2026-01')->assertSuccessful();

    $this->actingAs($this->admin)
        ->postJson('/api/v1/employees/'.$this->employee->id.'/end-of-service', [
            'amount' => 5000,
        ])->assertStatus(422);

    // Debiting a liability that was never raised leaves the account negative
    // and the difference unexplained.
    expect(($this->balanceOf)('end_of_service_payable'))->toBe(100.0);
});

test('an employee with nothing accrued has nothing to settle', function () {
    $this->actingAs($this->admin)
        ->postJson('/api/v1/employees/'.$this->employee->id.'/end-of-service')
        ->assertStatus(422);
});

/* -------------------------------------------------------------------- *
 * What a deduction actually is
 * -------------------------------------------------------------------- */

test('an advance repaid through the payroll reduces the advance', function () {
    $payroll = Payroll::create([
        'payroll_number' => 'PAY-000001',
        'employee_id' => $this->employee->id,
        'pay_period_start' => '2026-01-01',
        'pay_period_end' => '2026-01-31',
        'basic_salary' => 1200,
        'bonuses' => 0,
        'deductions' => 200,
        'deduction_type' => 'advance',
        'status' => Payroll::STATUS_PENDING,
    ]);
    $payroll->calculateNetSalary();
    $payroll->save();

    $this->actingAs($this->admin)->putJson('/api/v1/payrolls/'.$payroll->id, [
        'employee_id' => $this->employee->id,
        'pay_period_start' => '2026-01-01',
        'pay_period_end' => '2026-01-31',
        'basic_salary' => 1200,
        'deductions' => 200,
        'deduction_type' => 'advance',
        'status' => Payroll::STATUS_PROCESSED,
    ])->assertOk();

    // The asset comes down: the money has come back. It used to be held as a
    // liability instead, leaving the advance standing forever.
    expect(($this->balanceOf)('employee_advances'))->toBe(-200.0);
    expect(($this->balanceOf)('payroll_deductions_payable'))->toBe(0.0);
});

test('an unspecified deduction is still held as a liability', function () {
    $payroll = Payroll::create([
        'payroll_number' => 'PAY-000002',
        'employee_id' => $this->employee->id,
        'pay_period_start' => '2026-01-01',
        'pay_period_end' => '2026-01-31',
        'basic_salary' => 1200,
        'deductions' => 150,
        'status' => Payroll::STATUS_PENDING,
    ]);
    $payroll->calculateNetSalary();
    $payroll->save();

    $this->actingAs($this->admin)->putJson('/api/v1/payrolls/'.$payroll->id, [
        'employee_id' => $this->employee->id,
        'pay_period_start' => '2026-01-01',
        'pay_period_end' => '2026-01-31',
        'basic_salary' => 1200,
        'deductions' => 150,
        'status' => Payroll::STATUS_PROCESSED,
    ])->assertOk();

    // Neutral by default, because the record does not say what it is — which
    // is honest rather than a guess.
    expect(($this->balanceOf)('payroll_deductions_payable'))->toBe(150.0);
});
