<?php

use App\Models\Employee;
use App\Models\SalesOrder;
use App\Models\User;
use App\Models\Warehouse;

it('builds a filtered sales report and exposes a csv export', function () {
    $admin = User::factory()->create();

    $warehouse = Warehouse::create([
        'name' => 'Main Warehouse',
        'code' => 'WH-001',
        'is_active' => true,
    ]);

    $employeeA = Employee::create([
        'first_name' => 'Ali',
        'last_name' => 'Saleh',
        'email' => 'ali@example.com',
        'warehouse_id' => $warehouse->id,
        'status' => 'active',
    ]);

    $employeeB = Employee::create([
        'first_name' => 'Omar',
        'last_name' => 'Nasser',
        'email' => 'omar@example.com',
        'warehouse_id' => $warehouse->id,
        'status' => 'active',
    ]);

    SalesOrder::create([
        'order_number' => 'SO-1001',
        'customer_id' => null,
        'status' => 'confirmed',
        'order_date' => '2026-08-10',
        'subtotal' => 100,
        'tax' => 15,
        'discount' => 5,
        'total' => 110,
        'assigned_employee_id' => $employeeA->id,
    ]);

    SalesOrder::create([
        'order_number' => 'SO-1002',
        'customer_id' => null,
        'status' => 'processing',
        'order_date' => '2026-08-11',
        'subtotal' => 300,
        'tax' => 30,
        'discount' => 10,
        'total' => 320,
        'assigned_employee_id' => $employeeB->id,
    ]);

    $this->actingAs($admin, 'sanctum')
        ->getJson('/api/v1/admin/reports/sales?employee_id=' . $employeeA->id . '&status=confirmed&date=2026-08-10')
        ->assertOk()
        ->assertJsonPath('data.summary.total_orders', 1)
        ->assertJsonPath('data.summary.total_sales', 110)
        ->assertJsonPath('data.pagination.total', 1);

    $this->actingAs($admin, 'sanctum')
        ->get('/api/v1/admin/reports/sales/export?employee_id=' . $employeeA->id . '&status=confirmed&date=2026-08-10')
        ->assertOk()
        ->assertHeader('content-type', 'text/csv; charset=UTF-8');
});
