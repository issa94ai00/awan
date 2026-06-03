<?php

use App\Models\Employee;
use App\Models\User;
use Illuminate\Support\Str;

beforeEach(function () {
    $this->user = User::factory()->create();
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
