<?php

use App\Models\Employee;
use App\Models\EmployeeCommission;
use App\Models\EmployeeCommissionWithdrawal;
use App\Models\User;
use Illuminate\Support\Carbon;

beforeEach(function () {
    $this->employee = Employee::create([
        'name' => 'ليلى حمدان',
        'email' => 'laila@example.com',
        'phone' => '+963900000004',
        'position' => 'مندوبة مبيعات',
        'department' => 'المبيعات',
        'hire_date' => now()->subYear()->toDateString(),
        'salary' => 3000,
        'status' => 'نشط',
    ]);
    $this->admin = User::factory()->admin()->create();
});

test('purge removes only trashed records older than the retention window', function () {
    $old = EmployeeCommission::create([
        'employee_id' => $this->employee->id,
        'month' => now()->subMonths(2)->startOfMonth(),
        'commission_rate' => 5,
    ]);
    $old->delete();
    $old->forceFill(['deleted_at' => Carbon::now()->subDays(100)])->save();

    $recent = EmployeeCommission::create([
        'employee_id' => $this->employee->id,
        'month' => now()->subMonth()->startOfMonth(),
        'commission_rate' => 5,
    ]);
    $recent->delete();
    $recent->forceFill(['deleted_at' => Carbon::now()->subDays(10)])->save();

    $withdrawal = EmployeeCommissionWithdrawal::create([
        'employee_commission_id' => $recent->id,
        'withdrawn_at' => now(),
        'currency_code' => 'USD',
        'amount' => 50,
        'base_amount' => 50,
        'method' => 'cash',
    ]);
    $withdrawal->delete();
    $withdrawal->forceFill(['deleted_at' => Carbon::now()->subDays(100)])->save();

    $this->artisan('commissions:purge-trashed')->assertExitCode(0);

    $this->assertDatabaseMissing('employee_commissions', ['id' => $old->id]);
    $this->assertSoftDeleted('employee_commissions', ['id' => $recent->id]);
    $this->assertDatabaseMissing('employee_commission_withdrawals', ['id' => $withdrawal->id]);
});

test('purge dry-run reports what would be removed without deleting anything', function () {
    $old = EmployeeCommission::create([
        'employee_id' => $this->employee->id,
        'month' => now()->subMonths(2)->startOfMonth(),
        'commission_rate' => 5,
    ]);
    $old->delete();
    $old->forceFill(['deleted_at' => Carbon::now()->subDays(100)])->save();

    $this->artisan('commissions:purge-trashed', ['--dry-run' => true])->assertExitCode(0);

    $this->assertSoftDeleted('employee_commissions', ['id' => $old->id]);
});
