<?php

use App\Models\Employee;
use App\Models\User;
use Illuminate\Support\Str;

beforeEach(function () {
    $this->user = User::factory()->admin()->create();
    $this->token = $this->user->createToken('test-token')->plainTextToken;
});

test('admin employees API index returns employees list', function () {
    Employee::create([
        'name' => 'محمد الزهيري',
        'email' => 'mohamed@example.com',
        'phone' => '+966500000001',
        'position' => 'محاسب',
        'department' => 'المحاسبة',
        'hire_date' => now()->subYear()->toDateString(),
        'salary' => 3500,
        'status' => 'نشط',
        'notes' => 'موظف قيّم',
        'avatar' => null,
    ]);

    $response = $this->withHeader('Authorization', "Bearer {$this->token}")
        ->getJson('/api/v1/admin/employees');

    $response->assertStatus(200)
        ->assertJson([ 'success' => true ])
        ->assertJsonPath('data.employees.0.email', 'mohamed@example.com');
});

test('admin employees API can create employee using name attribute', function () {
    $payload = [
        'name' => 'ليلى أحمد',
        'email' => 'layla@example.com',
        'phone' => '+966500000002',
        'position' => 'موظف مبيعات',
        'department' => 'المبيعات',
        'hire_date' => now()->subMonths(3)->toDateString(),
        'salary' => 4200,
        'status' => 'نشط',
        'notes' => 'موظف جديد',
        'avatar' => 'https://example.com/avatar.png',
    ];

    $response = $this->withHeader('Authorization', "Bearer {$this->token}")
        ->postJson('/api/v1/admin/employees', $payload);

    $response->assertStatus(201)
        ->assertJson([ 'success' => true ])
        ->assertJsonPath('data.email', 'layla@example.com')
        ->assertJsonPath('data.avatar', 'https://example.com/avatar.png');

    $this->assertDatabaseHas('employees', [
        'email' => 'layla@example.com',
        'phone' => '+966500000002',
        'position' => 'موظف مبيعات',
        'department' => 'المبيعات',
        'avatar' => 'https://example.com/avatar.png',
    ]);
});

test('admin employees API can update existing employee', function () {
    $employee = Employee::create([
        'name' => 'حسن علي',
        'email' => 'hasan@example.com',
        'phone' => '+966500000003',
        'position' => 'مطور',
        'department' => 'تكنولوجيا المعلومات',
        'hire_date' => now()->subMonths(8)->toDateString(),
        'salary' => 5000,
        'status' => 'نشط',
        'notes' => 'مندوب تطوير',
    ]);

    $payload = [
        'name' => 'حسن علي',
        'email' => 'hasan.updated@example.com',
        'phone' => '+966500000004',
        'position' => 'مهندس برمجيات',
        'department' => 'تكنولوجيا المعلومات',
        'hire_date' => now()->subMonths(8)->toDateString(),
        'salary' => 5500,
        'status' => 'نشط',
        'notes' => 'تم تحديث الوظيفة',
        'avatar' => 'https://example.com/avatar-updated.png',
    ];

    $response = $this->withHeader('Authorization', "Bearer {$this->token}")
        ->putJson("/api/v1/admin/employees/{$employee->id}", $payload);

    $response->assertStatus(200)
        ->assertJson([ 'success' => true ])
        ->assertJsonPath('data.email', 'hasan.updated@example.com')
        ->assertJsonPath('data.avatar', 'https://example.com/avatar-updated.png');

    $this->assertDatabaseHas('employees', [
        'id' => $employee->id,
        'email' => 'hasan.updated@example.com',
        'phone' => '+966500000004',
        'position' => 'مهندس برمجيات',
        'avatar' => 'https://example.com/avatar-updated.png',
    ]);
});

test('admin employees API can delete existing employee', function () {
    $employee = Employee::create([
        'name' => 'سعيد محمد',
        'email' => 'saeed@example.com',
        'phone' => '+966500000005',
        'position' => 'مدير',
        'department' => 'الإدارة',
        'hire_date' => now()->subYears(2)->toDateString(),
        'salary' => 8000,
        'status' => 'نشط',
        'notes' => 'مدير عام',
    ]);

    $response = $this->withHeader('Authorization', "Bearer {$this->token}")
        ->deleteJson("/api/v1/admin/employees/{$employee->id}");

    $response->assertStatus(200)
        ->assertJson([ 'success' => true ])
        ->assertJsonPath('data', null);

    $this->assertDatabaseMissing('employees', [
        'id' => $employee->id,
    ]);
});

test('the employee form is offered the departments, positions and titles already in use', function () {
    $base = ['phone' => '+963900000001', 'hire_date' => now()->toDateString(), 'status' => 'نشط'];
    Employee::create($base + ['name' => 'أ', 'email' => 'a@example.com', 'department' => 'المبيعات', 'position' => 'مندوب', 'job_title' => 'مندوب أول']);
    Employee::create($base + ['name' => 'ب', 'email' => 'b@example.com', 'department' => 'المبيعات', 'position' => 'محاسب']);
    Employee::create($base + ['name' => 'ج', 'email' => 'c@example.com', 'department' => '', 'position' => 'مندوب']);

    $this->withHeader('Authorization', "Bearer {$this->token}")
        ->getJson('/api/v1/admin/employees/form-options')
        ->assertOk()
        ->assertJsonPath('data.departments', ['المبيعات'])
        ->assertJsonPath('data.positions', ['محاسب', 'مندوب'])
        ->assertJsonPath('data.job_titles', ['مندوب أول']);
});

test('a contract cannot end before it starts', function () {
    $this->withHeader('Authorization', "Bearer {$this->token}")
        ->postJson('/api/v1/admin/employees', [
            'name' => 'سامر', 'email' => 'samer@example.com', 'phone' => '+963900000002',
            'position' => 'مندوب', 'department' => 'المبيعات', 'hire_date' => now()->toDateString(), 'status' => 'نشط',
            'contract_start' => '2026-10-01', 'contract_end' => '2026-09-01',
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('contract_end');
});

test('a new employee with a password gets a login, reported back as has_login', function () {
    $this->withHeader('Authorization', "Bearer {$this->token}")
        ->postJson('/api/v1/admin/employees', [
            'name' => 'رنا', 'email' => 'rana@example.com', 'phone' => '+963900000003',
            'position' => 'مندوب', 'department' => 'المبيعات', 'hire_date' => now()->toDateString(), 'status' => 'نشط',
            'password' => 'secret-pass-1',
        ])
        ->assertCreated()
        ->assertJsonPath('data.has_login', true);

    expect(User::where('email', 'rana@example.com')->exists())->toBeTrue();
    expect(Employee::where('email', 'rana@example.com')->value('user_id'))->not->toBeNull();
});

describe('the login role picked on the employee form', function () {
    beforeEach(function () {
        $role = fn (string $name) => \App\Models\Role::firstOrCreate(['name' => $name], ['display_name' => $name, 'guard_name' => 'web', 'is_active' => true]);
        $this->employeeRole = $role('employee');
        $this->sells = $role('sells');
        $this->marketer = $role('marketer');
        $this->master = $role('master');

        $this->payload = fn (array $extra = []) => $extra + [
            'name' => 'هادي', 'email' => 'hadi@example.com', 'phone' => '+963900000010',
            'position' => 'مندوب', 'department' => 'المبيعات', 'hire_date' => now()->toDateString(), 'status' => 'نشط',
        ];
        $this->api = fn () => $this->withHeader('Authorization', "Bearer {$this->token}");
    });

    test('a new login takes the picked role, or "employee" when none is picked', function () {
        ($this->api)()->postJson('/api/v1/admin/employees', ($this->payload)(['password' => 'secret-pass-1', 'role_id' => $this->sells->id]))
            ->assertCreated()->assertJsonPath('data.login_role_id', $this->sells->id);
        $user = User::where('email', 'hadi@example.com')->first();
        expect($user->role_id)->toBe($this->sells->id);
        expect($user->roles()->pluck('name')->all())->toBe(['sells']);
        $this->assertDatabaseHas('audit_logs', ['entity_type' => User::class, 'entity_id' => $user->id, 'module' => 'users']);

        ($this->api)()->postJson('/api/v1/admin/employees', ($this->payload)(['email' => 'other@example.com', 'password' => 'secret-pass-1']))
            ->assertCreated()->assertJsonPath('data.login_role_id', $this->employeeRole->id);
    });

    test('a role without a login is ignored', function () {
        ($this->api)()->postJson('/api/v1/admin/employees', ($this->payload)(['role_id' => $this->sells->id]))
            ->assertCreated()->assertJsonPath('data.has_login', false);
        expect(User::where('email', 'hadi@example.com')->exists())->toBeFalse();
    });

    test('changing the role replaces the primary one and keeps the others', function () {
        $id = ($this->api)()->postJson('/api/v1/admin/employees', ($this->payload)(['password' => 'secret-pass-1', 'role_id' => $this->sells->id]))->json('data.id');
        $user = User::where('email', 'hadi@example.com')->first();
        $user->roles()->attach($this->marketer->id);

        ($this->api)()->putJson("/api/v1/admin/employees/{$id}", ($this->payload)(['role_id' => $this->employeeRole->id]))
            ->assertOk()
            ->assertJsonPath('data.login_role_id', $this->employeeRole->id)
            ->assertJsonPath('data.login_other_roles', ['marketer'])
            ->assertJsonMissingPath('data.user');

        expect($user->fresh()->roles()->pluck('name')->sort()->values()->all())->toBe(['employee', 'marketer']);
    });

    test('the master role is not handed out by a non-master admin', function () {
        ($this->api)()->postJson('/api/v1/admin/employees', ($this->payload)(['password' => 'secret-pass-1', 'role_id' => $this->master->id]))
            ->assertForbidden();
        expect(User::where('email', 'hadi@example.com')->exists())->toBeFalse();
        expect(Employee::where('email', 'hadi@example.com')->exists())->toBeFalse();
    });
});
