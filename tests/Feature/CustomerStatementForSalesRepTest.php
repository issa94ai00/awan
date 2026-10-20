<?php

use App\Models\Customer;
use App\Models\Employee;
use App\Models\Feature;
use App\Models\Role;
use App\Models\User;

/**
 * A sales rep follows their own clients' account statements through the
 * customers module, without being given the accounting one.
 */
beforeEach(function () {
    // The rep's modules: everything but accounting.
    $sells = Role::firstOrCreate(['name' => 'sells', 'guard_name' => 'web'], ['display_name' => 'مبيعات', 'is_active' => true]);
    $sells->features()->syncWithoutDetaching(Feature::where('key', '!=', 'accounting')->pluck('id'));

    $this->rep = User::factory()->create();
    $this->rep->roles()->syncWithoutDetaching([$sells->id]);
    $employee = Employee::create([
        'user_id' => $this->rep->id,
        'first_name' => 'مندوب',
        'last_name' => 'الكشوف',
        'email' => 'rep-statement@example.test',
        'position' => 'مندوب مبيعات',
        'department' => 'المبيعات',
        'hire_date' => now()->subYear()->toDateString(),
        'status' => 'نشط',
    ]);

    $this->mine = Customer::create(['name' => 'عميل المندوب', 'phone' => '0999000111']);
    $this->theirs = Customer::create(['name' => 'عميل آخر', 'phone' => '0999000222']);
    $employee->customers()->attach($this->mine->id);
});

it('shows a rep the statement of a client they serve', function () {
    $this->actingAs($this->rep)
        ->getJson("/api/v1/admin/customers/{$this->mine->id}/statement")
        ->assertOk()
        ->assertJsonPath('data.party.id', $this->mine->id);
});

it('refuses a rep the statement of a client they do not serve', function () {
    $this->actingAs($this->rep)
        ->getJson("/api/v1/admin/customers/{$this->theirs->id}/statement")
        ->assertForbidden();
});

it('keeps the accounting party statement closed to a rep', function () {
    $this->actingAs($this->rep)
        ->getJson("/api/v1/admin/accounting/party-statement?type=customer&party_id={$this->mine->id}")
        ->assertForbidden();
});

it('shows an admin any customer\'s statement', function () {
    $this->actingAs(User::factory()->admin()->create())
        ->getJson("/api/v1/admin/customers/{$this->theirs->id}/statement")
        ->assertOk()
        ->assertJsonPath('data.party.id', $this->theirs->id);
});
