<?php

use App\Models\Employee;
use App\Models\JournalEntryHeader;
use App\Models\Payroll;
use App\Models\Role;
use App\Models\User;

beforeEach(function () {
    $this->admin = User::factory()->create([
        'is_admin' => true,
        'role_id' => Role::firstOrCreate(['name' => 'admin'], ['display_name' => 'admin'])->id,
    ]);
    $this->actingAs($this->admin);

    $this->employee = Employee::create([
        'first_name' => 'سامر', 'last_name' => 'الأحمد', 'email' => 'samer-w@example.test',
        'position' => 'مندوب', 'salary' => 1000, 'bonus' => 50,
        'hire_date' => now()->subYear()->toDateString(), 'status' => 'نشط',
    ]);

    $this->periodStart = now()->subMonth()->startOfMonth()->toDateString();
    $this->periodEnd = now()->subMonth()->endOfMonth()->toDateString();

    $this->makePayroll = function (array $o = []): Payroll {
        $p = Payroll::create($o + [
            'payroll_number' => 'PAY-W'.(Payroll::count() + 1),
            'employee_id' => $this->employee->id,
            'pay_period_start' => $this->periodStart, 'pay_period_end' => $this->periodEnd,
            'basic_salary' => 1000, 'overtime_hours' => 0, 'overtime_rate' => 0,
            'bonuses' => 200, 'deductions' => 150, 'status' => Payroll::STATUS_PENDING,
        ]);
        $p->calculateNetSalary();
        $p->save();

        return $p;
    };
});

it('auto-generates payrolls for active employees and skips ones already generated', function () {
    Employee::create([
        'first_name' => 'متوقف', 'last_name' => 'س', 'email' => 'inactive-w@example.test',
        'position' => 'x', 'salary' => 500, 'hire_date' => now()->subYear()->toDateString(), 'status' => 'غير نشط',
    ]);
    $payload = ['pay_period_start' => $this->periodStart, 'pay_period_end' => $this->periodEnd, 'payment_date' => $this->periodEnd];

    $this->postJson('/api/v1/payrolls/auto-generate', $payload)->assertCreated()
        ->assertJsonPath('data.created_count', 1)->assertJsonPath('data.skipped_count', 0);

    $p = Payroll::where('employee_id', $this->employee->id)->first();
    expect(Payroll::count())->toBe(1)
        ->and((float) $p->basic_salary)->toBe(1000.0)->and((float) $p->bonuses)->toBe(50.0)
        ->and((float) $p->net_salary)->toBe(1050.0)->and($p->status)->toBe(Payroll::STATUS_PENDING);

    // A row stored with a plain date (as MySQL does) is recognised and skipped.
    // Raw insert: the SQLite date cast would store a datetime string the lookup cannot match.
    $other = Employee::create([
        'first_name' => 'ثان', 'last_name' => 'س', 'email' => 'second-w@example.test',
        'position' => 'x', 'salary' => 700, 'hire_date' => now()->subYear()->toDateString(), 'status' => 'نشط',
    ]);
    \Illuminate\Support\Facades\DB::table('payrolls')->insert([
        'payroll_number' => 'PAY-RAW1', 'employee_id' => $other->id, 'status' => Payroll::STATUS_PENDING,
        'pay_period_start' => $this->periodStart, 'pay_period_end' => $this->periodEnd,
        'basic_salary' => 700, 'net_salary' => 700, 'created_at' => now(), 'updated_at' => now(),
    ]);
    $count = Payroll::count();
    $this->postJson('/api/v1/payrolls/auto-generate', $payload)->assertCreated()
        ->assertJsonPath('data.skipped_count', 1);
    expect(Payroll::where('employee_id', $other->id)->count())->toBe(1)->and(Payroll::count())->toBeGreaterThanOrEqual($count);

    $this->postJson('/api/v1/payrolls/auto-generate', ['pay_period_start' => $this->periodEnd, 'pay_period_end' => $this->periodStart])
        ->assertStatus(422);
});

it('refuses payroll writes to a user without the accountant role', function () {
    $p = ($this->makePayroll)();
    $id = $p->id;
    $role = Role::firstOrCreate(['name' => 'sales'], ['display_name' => 'sales']);
    $this->actingAs(User::factory()->create(['is_admin' => false, 'role_id' => $role->id]));

    $this->postJson('/api/v1/payrolls/auto-generate', ['pay_period_start' => $this->periodStart, 'pay_period_end' => $this->periodEnd])->assertForbidden();
    $this->putJson("/api/v1/payrolls/$id", ['status' => 'paid'])->assertForbidden();
    $this->deleteJson("/api/v1/payrolls/$id")->assertForbidden();
    expect(Payroll::find($id))->not->toBeNull()->and($p->fresh()->status)->toBe(Payroll::STATUS_PENDING);
});

it('updates a pending payroll and recalculates the net salary', function () {
    $p = ($this->makePayroll)();
    $id = $p->id;
    expect((float) $p->net_salary)->toBe(1050.0);

    $this->putJson("/api/v1/payrolls/$id", [
        'employee_id' => $this->employee->id, 'pay_period_start' => $this->periodStart, 'pay_period_end' => $this->periodEnd,
        'basic_salary' => 2000, 'bonuses' => 0, 'deductions' => 100, 'status' => 'pending',
    ])->assertOk();

    $fresh = $p->fresh();
    expect((float) $fresh->basic_salary)->toBe(2000.0)->and((float) $fresh->net_salary)->toBe(1900.0);

    $this->putJson("/api/v1/payrolls/$id", ['status' => 'pending'])->assertStatus(422);
});

it('accrues on processed, locks the payroll against edits-to-pending and deletion, and posts payment when paid', function () {
    $p = ($this->makePayroll)();
    $id = $p->id;
    $entries = JournalEntryHeader::count();
    $body = [
        'employee_id' => $this->employee->id, 'pay_period_start' => $this->periodStart, 'pay_period_end' => $this->periodEnd,
        'basic_salary' => 1000, 'bonuses' => 200, 'deductions' => 150,
    ];

    $this->putJson("/api/v1/payrolls/$id", $body + ['status' => 'processed'])->assertOk();
    expect($p->fresh()->status)->toBe(Payroll::STATUS_PROCESSED)->and($p->fresh()->isAccrued())->toBeTrue();
    expect(JournalEntryHeader::count())->toBe($entries + 1);

    $this->putJson("/api/v1/payrolls/$id", ['status' => 'pending'])->assertStatus(422);
    $this->deleteJson("/api/v1/payrolls/$id")->assertStatus(422);
    expect(Payroll::find($id))->not->toBeNull();

    $this->putJson("/api/v1/payrolls/$id", ['status' => 'paid', 'payment_method' => 'cash'])->assertOk();
    expect($p->fresh()->status)->toBe(Payroll::STATUS_PAID);
    expect(JournalEntryHeader::count())->toBe($entries + 2);
});

it('deletes an un-accrued payroll', function () {
    $p = ($this->makePayroll)();
    $id = $p->id;

    $this->deleteJson("/api/v1/payrolls/$id")->assertOk();
    expect(Payroll::find($id))->toBeNull();
});

it('settles end of service, refusing nothing accrued and amounts above the accrued balance', function () {
    $id = $this->employee->id;

    $this->postJson("/api/v1/employees/$id/end-of-service", [])->assertStatus(422);

    $this->employee->forceFill(['end_of_service_accrued' => 500])->save();
    $entries = JournalEntryHeader::count();

    $this->postJson("/api/v1/employees/$id/end-of-service", ['amount' => 900])->assertStatus(422);
    expect((float) $this->employee->fresh()->end_of_service_accrued)->toBe(500.0)
        ->and(JournalEntryHeader::count())->toBe($entries);

    $this->postJson("/api/v1/employees/$id/end-of-service", ['amount' => 200, 'settlement' => 'cash'])->assertOk();
    expect((float) $this->employee->fresh()->end_of_service_accrued)->toBe(300.0)
        ->and(JournalEntryHeader::count())->toBe($entries + 1);
});
