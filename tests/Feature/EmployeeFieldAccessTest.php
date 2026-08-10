<?php

use App\Models\Employee;
use App\Models\User;
use App\Models\Warehouse;
use App\Services\Field\FieldScope;
use Illuminate\Support\Facades\Hash;

/**
 * Setting up an employee so they can use the field app.
 *
 * Two things make that account work, and neither is any use alone: a password
 * (which creates the login) and a linked warehouse (which confines them to
 * their own branch). These tests pin the behaviour the admin form's warnings
 * describe, including the case where only one of the two is given.
 */
beforeEach(function () {
    $this->admin = User::factory()->create();
    $this->token = $this->admin->createToken('test-token')->plainTextToken;

    $this->branch = Warehouse::create([
        'name' => 'فرع جدة',
        'code' => 'WH-JED',
        'location' => 'جدة',
        'status' => 'active',
        'is_active' => true,
        'location_type' => Warehouse::TYPE_BRANCH,
    ]);

    $this->post = fn (array $payload) => $this->withHeader('Authorization', "Bearer {$this->token}")
        ->postJson('/api/v1/admin/employees', array_merge([
            'name' => 'بائع الفرع',
            'email' => 'seller@example.com',
            'phone' => '+966500000001',
            'position' => 'مندوب مبيعات',
            'department' => 'المبيعات',
            'hire_date' => now()->subMonths(2)->toDateString(),
            'status' => 'نشط',
        ], $payload));
});

test('a password and a warehouse together produce a working field account', function () {
    ($this->post)([
        'password' => 'secret-password',
        'warehouse_id' => $this->branch->id,
    ])->assertStatus(201);

    $employee = Employee::latest('id')->first();

    expect($employee->warehouse_id)->toBe($this->branch->id);
    expect($employee->user_id)->not->toBeNull();

    // The password is stored hashed, never in the clear.
    expect($employee->user->password)->not->toBe('secret-password');
    expect(Hash::check('secret-password', $employee->user->password))->toBeTrue();

    // And the account is confined to its branch — which is the whole point of
    // the link, and what every field endpoint checks.
    $scope = FieldScope::for($employee->user);
    expect($scope->isConfined())->toBeTrue();
    expect($scope->warehouseIds())->toBe([$this->branch->id]);
});

test('a login with no warehouse is unconfined, as the form warns', function () {
    ($this->post)(['password' => 'secret-password'])->assertStatus(201);

    $employee = Employee::latest('id')->first();
    expect($employee->warehouse_id)->toBeNull();

    // Not a bug — back-office staff need this — but it is why the form warns
    // before creating a seller this way.
    expect(FieldScope::for($employee->user)->isConfined())->toBeFalse();
});

test('a warehouse with no password links but grants no access', function () {
    ($this->post)(['warehouse_id' => $this->branch->id])->assertStatus(201);

    $employee = Employee::latest('id')->first();

    expect($employee->warehouse_id)->toBe($this->branch->id);
    // Nothing to sign in with, so the link cannot take effect yet.
    expect($employee->user_id)->toBeNull();
});

test('an unknown warehouse is refused rather than silently dropped', function () {
    ($this->post)(['warehouse_id' => 999999])
        ->assertStatus(422)
        ->assertJsonValidationErrors('warehouse_id');
});

test('the warehouse can be changed and unlinked on an existing employee', function () {
    ($this->post)([
        'password' => 'secret-password',
        'warehouse_id' => $this->branch->id,
    ])->assertStatus(201);

    $employee = Employee::latest('id')->first();

    $other = Warehouse::create([
        'name' => 'فرع الرياض',
        'code' => 'WH-RYD',
        'location' => 'الرياض',
        'status' => 'active',
        'is_active' => true,
        'location_type' => Warehouse::TYPE_BRANCH,
    ]);

    $update = fn (array $payload) => $this->withHeader('Authorization', "Bearer {$this->token}")
        ->putJson("/api/v1/admin/employees/{$employee->id}", array_merge([
            'name' => 'بائع الفرع',
            'email' => 'seller@example.com',
            'phone' => '+966500000001',
            'position' => 'مندوب مبيعات',
            'department' => 'المبيعات',
            'hire_date' => now()->subMonths(2)->toDateString(),
            'status' => 'نشط',
        ], $payload));

    $update(['warehouse_id' => $other->id])->assertStatus(200);
    expect($employee->fresh()->warehouse_id)->toBe($other->id);

    // Clearing the selector has to actually unlink. Sent as null, which is why
    // the form keeps the key instead of dropping empty values.
    $update(['warehouse_id' => null])->assertStatus(200);
    expect($employee->fresh()->warehouse_id)->toBeNull();
});

test('changing the warehouse does not disturb the existing password', function () {
    ($this->post)([
        'password' => 'secret-password',
        'warehouse_id' => $this->branch->id,
    ])->assertStatus(201);

    $employee = Employee::latest('id')->first();

    $this->withHeader('Authorization', "Bearer {$this->token}")
        ->putJson("/api/v1/admin/employees/{$employee->id}", [
            'name' => 'بائع الفرع',
            'email' => 'seller@example.com',
            'phone' => '+966500000001',
            'position' => 'مندوب مبيعات',
            'department' => 'المبيعات',
            'hire_date' => now()->subMonths(2)->toDateString(),
            'status' => 'نشط',
            'warehouse_id' => null,
        ])->assertStatus(200);

    // An edit that leaves the password blank must keep the old one, or every
    // routine change would lock the employee out.
    expect(Hash::check('secret-password', $employee->fresh()->user->password))->toBeTrue();
});

test('the detail endpoint says whether the employee can sign in', function () {
    ($this->post)([
        'password' => 'secret-password',
        'warehouse_id' => $this->branch->id,
    ])->assertStatus(201);

    $withLogin = Employee::latest('id')->first();

    $this->withHeader('Authorization', "Bearer {$this->token}")
        ->getJson("/api/v1/admin/employees/{$withLogin->id}")
        ->assertStatus(200)
        ->assertJsonPath('data.has_login', true)
        ->assertJsonPath('data.warehouse.name', 'فرع جدة');

    $withoutLogin = Employee::create([
        'name' => 'موظف مكتب',
        'email' => 'office@example.com',
        'phone' => '+966500000009',
        'position' => 'محاسب',
        'department' => 'المحاسبة',
        'hire_date' => now()->toDateString(),
        'status' => 'نشط',
    ]);

    $this->withHeader('Authorization', "Bearer {$this->token}")
        ->getJson("/api/v1/admin/employees/{$withoutLogin->id}")
        ->assertStatus(200)
        ->assertJsonPath('data.has_login', false);
});

test('the list carries each employee warehouse so setup can be checked at a glance', function () {
    ($this->post)([
        'password' => 'secret-password',
        'warehouse_id' => $this->branch->id,
    ])->assertStatus(201);

    $this->withHeader('Authorization', "Bearer {$this->token}")
        ->getJson('/api/v1/admin/employees')
        ->assertStatus(200)
        ->assertJsonPath('data.employees.0.warehouse.name', 'فرع جدة');
});
