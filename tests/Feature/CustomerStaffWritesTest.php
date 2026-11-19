<?php

use App\Models\Company;
use App\Models\Customer;
use App\Models\Employee;
use App\Models\User;

function cswEmployee(string $tag): Employee
{
    return Employee::create([
        'first_name' => 'Rep', 'last_name' => $tag, 'email' => "rep.$tag@example.com",
        'phone' => '09'.random_int(10000000, 99999999), 'status' => 'active',
    ]);
}

function cswCustomer(string $name = 'عميل'): Customer
{
    return Customer::create(['name' => $name, 'phone' => '09'.random_int(10000000, 99999999), 'status' => 'active']);
}

beforeEach(function () {
    $this->admin = User::factory()->admin()->create();
});

it('sets a customer primary rep and the full rep list', function () {
    $customer = cswCustomer();
    $id = $customer->id;
    $a = cswEmployee('a');
    $b = cswEmployee('b');

    $this->actingAs($this->admin)->putJson("/api/v1/admin/customers/$id/sales-reps", [
        'primary_employee_id' => $a->id,
        'employee_ids' => [$b->id],
    ])->assertOk()->assertJsonPath('data.employee_id', $a->id);

    expect($customer->fresh()->employee_id)->toBe($a->id)
        ->and($customer->customerEmployees()->pluck('employees.id')->sort()->values()->all())
        ->toBe(collect([$a->id, $b->id])->sort()->values()->all());

    // Clearing both leaves the customer unassigned.
    $this->actingAs($this->admin)->putJson("/api/v1/admin/customers/$id/sales-reps", ['primary_employee_id' => null, 'employee_ids' => []])->assertOk();
    expect($customer->fresh()->employee_id)->toBeNull()->and($customer->customerEmployees()->count())->toBe(0);
});

it('requires employee_ids and existing employees on sales-reps update', function () {
    $id = cswCustomer()->id;

    $this->actingAs($this->admin)->putJson("/api/v1/admin/customers/$id/sales-reps", [])->assertStatus(422)->assertJsonValidationErrors('employee_ids');
    $this->actingAs($this->admin)->putJson("/api/v1/admin/customers/$id/sales-reps", ['employee_ids' => [999999]])->assertStatus(422);
});

it('keeps sales-reps and attach/detach admin-only', function () {
    $customer = cswCustomer();
    $id = $customer->id;
    $emp = cswEmployee('x');
    $eid = $emp->id;
    $staff = User::factory()->create();

    $this->actingAs($staff)->putJson("/api/v1/admin/customers/$id/sales-reps", ['employee_ids' => [$emp->id]])->assertStatus(403);
    $this->actingAs($staff)->postJson("/api/v1/admin/employees/$eid/customers/attach", ['customer_ids' => [$id]])->assertStatus(403);
    $this->actingAs($staff)->postJson("/api/v1/admin/employees/$eid/customers/detach", ['customer_ids' => [$id]])->assertStatus(403);

    expect($customer->fresh()->employee_id)->toBeNull()->and($emp->customers()->count())->toBe(0);
});

it('attaches customers to an employee and adopts only unowned ones as primary', function () {
    $emp = cswEmployee('att');
    $other = cswEmployee('oth');
    $free = cswCustomer('حر');
    $owned = cswCustomer('مملوك');
    $owned->setSalesReps($other->id, [$other->id]);
    $eid = $emp->id;

    $this->actingAs($this->admin)->postJson("/api/v1/admin/employees/$eid/customers/attach", ['customer_ids' => []])->assertStatus(422);
    $this->actingAs($this->admin)->postJson("/api/v1/admin/employees/$eid/customers/attach", ['customer_ids' => [999999]])->assertStatus(422);

    $this->actingAs($this->admin)->postJson("/api/v1/admin/employees/$eid/customers/attach", [
        'customer_ids' => [$free->id, $owned->id],
    ])->assertOk();

    expect($emp->customers()->pluck('customers.id')->sort()->values()->all())
        ->toBe(collect([$free->id, $owned->id])->sort()->values()->all())
        ->and($free->fresh()->employee_id)->toBe($emp->id)
        ->and($owned->fresh()->employee_id)->toBe($other->id);
});

it('detaches customers and re-points or clears the primary rep', function () {
    $emp = cswEmployee('det');
    $second = cswEmployee('sec');
    $solo = cswCustomer('منفرد');
    $shared = cswCustomer('مشترك');
    $solo->setSalesReps($emp->id, [$emp->id]);
    $shared->setSalesReps($emp->id, [$emp->id, $second->id]);
    $eid = $emp->id;

    $this->actingAs($this->admin)->postJson("/api/v1/admin/employees/$eid/customers/detach", [
        'customer_ids' => [$solo->id, $shared->id],
    ])->assertOk();

    expect($emp->customers()->count())->toBe(0)
        ->and($solo->fresh()->employee_id)->toBeNull()
        ->and($shared->fresh()->employee_id)->toBe($second->id);
});

it('creates a company and validates the required fields', function () {
    $this->actingAs($this->admin)->postJson('/api/v1/companies', ['name' => 'شركة'])
        ->assertStatus(422)->assertJsonValidationErrors(['contact', 'address']);
    expect(Company::count())->toBe(0);

    $this->actingAs($this->admin)->postJson('/api/v1/companies', [
        'name' => 'شركة النور', 'contact' => '0112223333', 'address' => 'دمشق', 'latitude' => 33.5, 'longitude' => 36.3,
    ])->assertCreated()->assertJsonPath('success', true);

    $company = Company::first();
    expect($company->name)->toBe('شركة النور')->and($company->contact)->toBe('0112223333');
});
