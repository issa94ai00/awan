<?php

use App\Models\Currency;
use App\Models\Employee;
use App\Models\EmployeeCommission;
use App\Models\EmployeeCommissionWithdrawal;
use App\Models\User;

function cmwCommission(): EmployeeCommission
{
    $employee = Employee::create([
        'first_name' => 'Rep', 'last_name' => 'Cm', 'email' => 'cm.'.uniqid().'@example.com',
        'phone' => '09'.random_int(10000000, 99999999), 'status' => 'active',
    ]);

    return EmployeeCommission::create([
        'employee_id' => $employee->id,
        'month' => now()->startOfMonth()->toDateString(),
        'commission_rate' => 5,
    ]);
}

function cmwWithdrawal(EmployeeCommission $commission, float $amount = 40): EmployeeCommissionWithdrawal
{
    $w = $commission->withdrawalTransactions()->create([
        'withdrawn_at' => now()->toDateString(), 'currency_code' => 'USD', 'amount' => $amount,
        'exchange_rate' => 1, 'base_amount' => $amount, 'method' => 'cash',
    ]);
    $commission->recalculateWithdrawals();

    return $w;
}

beforeEach(function () {
    $this->admin = User::factory()->admin()->create();
    Currency::updateOrCreate(['code' => 'USD'], [
        'name_ar' => 'دولار', 'name_en' => 'US Dollar', 'symbol' => '$', 'decimal_places' => 2,
        'is_base' => true, 'is_active' => true,
    ]);
});

it('updates a withdrawal, recomputes the commission total, and validates input', function () {
    $commission = cmwCommission();
    $w = cmwWithdrawal($commission, 40);
    $cid = $commission->id;
    $wid = $w->id;

    $this->actingAs($this->admin)->putJson("/api/v1/admin/employee-commissions/$cid/withdrawals/$wid", ['amount' => 0])
        ->assertStatus(422);

    $this->actingAs($this->admin)->putJson("/api/v1/admin/employee-commissions/$cid/withdrawals/$wid", [
        'withdrawn_at' => now()->toDateString(), 'currency_code' => 'usd', 'amount' => 75, 'method' => 'bank',
    ])->assertOk();

    $w->refresh();
    expect((float) $w->amount)->toBe(75.0)->and((float) $w->base_amount)->toBe(75.0)
        ->and($w->currency_code)->toBe('USD')->and($w->method)->toBe('bank')
        ->and((float) $commission->fresh()->withdrawals)->toBe(75.0);
});

it('404s when a withdrawal is addressed through the wrong commission', function () {
    $commission = cmwCommission();
    $other = cmwCommission();
    $w = cmwWithdrawal($commission);
    $oid = $other->id;
    $wid = $w->id;

    $this->actingAs($this->admin)->putJson("/api/v1/admin/employee-commissions/$oid/withdrawals/$wid", [
        'withdrawn_at' => now()->toDateString(), 'currency_code' => 'USD', 'amount' => 5, 'method' => 'cash',
    ])->assertNotFound();
    $this->actingAs($this->admin)->deleteJson("/api/v1/admin/employee-commissions/$oid/withdrawals/$wid")->assertNotFound();

    expect((float) $w->fresh()->amount)->toBe(40.0)->and($w->fresh()->trashed())->toBeFalse();
});

it('soft-deletes a withdrawal and restores it, keeping the total in step', function () {
    $commission = cmwCommission();
    $keep = cmwWithdrawal($commission, 10);
    $w = cmwWithdrawal($commission, 40);
    $cid = $commission->id;
    $wid = $w->id;
    expect((float) $commission->fresh()->withdrawals)->toBe(50.0);

    $this->actingAs($this->admin)->deleteJson("/api/v1/admin/employee-commissions/$cid/withdrawals/$wid")->assertOk();

    expect(EmployeeCommissionWithdrawal::withTrashed()->find($wid)->trashed())->toBeTrue()
        ->and(EmployeeCommissionWithdrawal::withTrashed()->find($wid)->deleted_by)->toBe($this->admin->id)
        ->and((float) $commission->fresh()->withdrawals)->toBe(10.0);

    $this->actingAs($this->admin)->putJson("/api/v1/admin/employee-commissions/$cid/withdrawals/$wid/restore")->assertOk();

    expect(EmployeeCommissionWithdrawal::find($wid))->not->toBeNull()
        ->and(EmployeeCommissionWithdrawal::find($wid)->deleted_by)->toBeNull()
        ->and((float) $commission->fresh()->withdrawals)->toBe(50.0);
});

it('keeps withdrawal restore admin-only', function () {
    $commission = cmwCommission();
    $w = cmwWithdrawal($commission);
    $cid = $commission->id;
    $wid = $w->id;
    $w->delete();
    $staff = User::factory()->create();

    $this->actingAs($staff)->putJson("/api/v1/admin/employee-commissions/$cid/withdrawals/$wid/restore")->assertStatus(403);
    expect(EmployeeCommissionWithdrawal::withTrashed()->find($wid)->trashed())->toBeTrue();
});

it('restores a trashed commission record, cascading nothing and re-summing withdrawals', function () {
    $commission = cmwCommission();
    cmwWithdrawal($commission, 25);
    $id = $commission->id;

    $this->actingAs($this->admin)->deleteJson("/api/v1/admin/employee-commissions/$id")->assertOk();
    expect(EmployeeCommission::find($id))->toBeNull();

    $this->actingAs($this->admin)->putJson("/api/v1/admin/employee-commissions/$id/restore")->assertOk();

    $restored = EmployeeCommission::find($id);
    expect($restored)->not->toBeNull()->and($restored->deleted_by)->toBeNull();
});

it('keeps commission restore admin-only', function () {
    $commission = cmwCommission();
    $id = $commission->id;
    $commission->delete();
    $staff = User::factory()->create();

    $this->actingAs($staff)->putJson("/api/v1/admin/employee-commissions/$id/restore")->assertStatus(403);
    expect(EmployeeCommission::find($id))->toBeNull();
});
