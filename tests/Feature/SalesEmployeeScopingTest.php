<?php

use App\Models\Customer;
use App\Models\Employee;
use App\Models\Feature;
use App\Models\Invoice;
use App\Models\Product;
use App\Models\Role;
use App\Models\User;
use App\Models\Warehouse;
use App\Models\WarehouseInventory;

beforeEach(function () {
    $this->sellsRole = Role::firstOrCreate(
        ['name' => 'sells'],
        ['display_name' => 'مبيعات', 'guard_name' => 'web', 'is_active' => true]
    );

    $salesFeature = Feature::firstOrCreate(['key' => 'sales'], ['name_ar' => 'المبيعات', 'name_en' => 'Sales', 'group' => 'sales', 'is_enabled' => true]);
    $invFeature = Feature::firstOrCreate(['key' => 'inventory'], ['name_ar' => 'المخزون', 'name_en' => 'Inventory', 'group' => 'inventory', 'is_enabled' => true]);
    $this->sellsRole->features()->syncWithoutDetaching([$salesFeature->id, $invFeature->id]);

    // Two warehouses
    $this->wh1 = Warehouse::create([
        'name' => 'مستودع الرياض',
        'code' => 'WH-RUH',
        'is_active' => true,
        'location_type' => Warehouse::TYPE_BRANCH,
    ]);

    $this->wh2 = Warehouse::create([
        'name' => 'مستودع جدة',
        'code' => 'WH-JED',
        'is_active' => true,
        'location_type' => Warehouse::TYPE_BRANCH,
    ]);

    // Product and stock in both warehouses
    $this->product = Product::create([
        'name_ar' => 'صنبور مغسلة فاخر',
        'sku' => 'TAP-001',
        'price' => 150,
        'cost_price' => 100,
    ]);

    WarehouseInventory::create([
        'warehouse_id' => $this->wh1->id,
        'product_id' => $this->product->id,
        'quantity' => 50,
        'reserved_quantity' => 0,
    ]);

    WarehouseInventory::create([
        'warehouse_id' => $this->wh2->id,
        'product_id' => $this->product->id,
        'quantity' => 80,
        'reserved_quantity' => 0,
    ]);

    $this->customer = Customer::create([
        'name' => 'عميل تجريبي',
        'email' => 'client@test.com',
        'phone' => '+966512345678',
        'status' => 'نشط',
    ]);

    // Sales Rep 1 (assigned to WH1)
    $this->rep1User = User::factory()->create([
        'name' => 'مندوب الرياض',
        'is_admin' => false,
        'role_id' => $this->sellsRole->id,
    ]);
    $this->rep1User->roles()->sync([$this->sellsRole->id]);

    $this->rep1Employee = Employee::create([
        'user_id' => $this->rep1User->id,
        'warehouse_id' => $this->wh1->id,
        'first_name' => 'مندوب',
        'last_name' => 'الرياض',
        'email' => 'rep1@test.com',
        'phone' => '+966500000010',
        'position' => 'مندوب مبيعات',
        'hire_date' => now()->toDateString(),
        'status' => 'نشط',
    ]);

    // Sales Rep 2 (assigned to WH2)
    $this->rep2User = User::factory()->create([
        'name' => 'مندوب جدة',
        'is_admin' => false,
        'role_id' => $this->sellsRole->id,
    ]);
    $this->rep2User->roles()->sync([$this->sellsRole->id]);

    $this->rep2Employee = Employee::create([
        'user_id' => $this->rep2User->id,
        'warehouse_id' => $this->wh2->id,
        'first_name' => 'مندوب',
        'last_name' => 'جدة',
        'email' => 'rep2@test.com',
        'phone' => '+966500000020',
        'position' => 'مندوب مبيعات',
        'hire_date' => now()->toDateString(),
        'status' => 'نشط',
    ]);

    // Invoices for each rep
    $this->invoice1 = Invoice::create([
        'invoice_number' => 'INV-TEST-001',
        'customer_id' => $this->customer->id,
        'assigned_employee_id' => $this->rep1Employee->id,
        'created_by' => $this->rep1User->id,
        'warehouse_id' => $this->wh1->id,
        'subtotal' => 300,
        'total' => 300,
        'status' => Invoice::STATUS_CONFIRMED,
    ]);

    $this->invoice2 = Invoice::create([
        'invoice_number' => 'INV-TEST-002',
        'customer_id' => $this->customer->id,
        'assigned_employee_id' => $this->rep2Employee->id,
        'created_by' => $this->rep2User->id,
        'warehouse_id' => $this->wh2->id,
        'subtotal' => 600,
        'total' => 600,
        'status' => Invoice::STATUS_CONFIRMED,
    ]);

    // Super Admin
    $this->adminUser = User::factory()->admin()->create();
});

test('sales rep can only see their own invoices in the invoice list', function () {
    $token1 = $this->rep1User->createToken('token')->plainTextToken;

    $response = $this->withHeader('Authorization', "Bearer {$token1}")
        ->getJson('/api/v1/invoices');

    $response->assertStatus(200);
    $numbers = collect($response->json('data.invoices'))->pluck('invoice_number')->all();

    expect($numbers)->toContain('INV-TEST-001')
        ->not->toContain('INV-TEST-002');
});

test('sales rep cannot view another rep invoice directly', function () {
    $token1 = $this->rep1User->createToken('token')->plainTextToken;

    // Rep 1 views their own invoice
    $this->withHeader('Authorization', "Bearer {$token1}")
        ->getJson("/api/v1/invoices/{$this->invoice1->id}")
        ->assertStatus(200);

    // Rep 1 attempts to view Rep 2's invoice
    $this->withHeader('Authorization', "Bearer {$token1}")
        ->getJson("/api/v1/invoices/{$this->invoice2->id}")
        ->assertStatus(403);
});

test('super admin can see all invoices', function () {
    $adminToken = $this->adminUser->createToken('token')->plainTextToken;

    $response = $this->withHeader('Authorization', "Bearer {$adminToken}")
        ->getJson('/api/v1/invoices');

    $response->assertStatus(200);
    $numbers = collect($response->json('data.invoices'))->pluck('invoice_number')->all();

    expect($numbers)->toContain('INV-TEST-001')
        ->toContain('INV-TEST-002');
});

test('sales rep can only see inventory of their assigned warehouse', function () {
    $token1 = $this->rep1User->createToken('token')->plainTextToken;

    $response = $this->withHeader('Authorization', "Bearer {$token1}")
        ->getJson('/api/v1/admin/inventory/stock');

    $response->assertStatus(200);
    $warehouseIds = collect($response->json('data.stock'))->pluck('warehouse_id')->unique()->all();

    expect($warehouseIds)->toBe([(int) $this->wh1->id]);
});

test('sales rep inventory summary is confined to their warehouse', function () {
    $token1 = $this->rep1User->createToken('token')->plainTextToken;

    $response = $this->withHeader('Authorization', "Bearer {$token1}")
        ->getJson('/api/v1/admin/inventory/summary');

    $response->assertStatus(200);
    $warehouses = $response->json('data.warehouses');

    expect(count($warehouses))->toBe(1);
    expect($warehouses[0]['id'])->toBe($this->wh1->id);
    expect((int) $response->json('data.summary.total_quantity'))->toBe(50);
});

test('super admin sees all warehouses in inventory summary', function () {
    $adminToken = $this->adminUser->createToken('token')->plainTextToken;

    $response = $this->withHeader('Authorization', "Bearer {$adminToken}")
        ->getJson('/api/v1/admin/inventory/summary');

    $response->assertStatus(200);
    $whIds = collect($response->json('data.warehouses'))->pluck('id')->all();

    expect($whIds)->toContain($this->wh1->id)
        ->toContain($this->wh2->id);
});

// ------------------------------------------------------------ sales reports

test('sales rep reports cover only their own invoices, whatever employee they ask for', function () {
    $token1 = $this->rep1User->createToken('token')->plainTextToken;
    $as = fn () => $this->withHeader('Authorization', "Bearer {$token1}");

    $numbers = collect($as()->getJson('/api/v1/admin/reports/invoices')->assertOk()->json('data.invoices'))
        ->pluck('invoice_number')->all();
    expect($numbers)->toBe(['INV-TEST-001']);

    // Asking for the other rep's sales does not widen the scope.
    $asked = $as()->getJson("/api/v1/admin/reports/invoices?employee_id={$this->rep2Employee->id}")->assertOk();
    expect($asked->json('data.invoices'))->toBe([]);

    $performance = $as()->getJson('/api/v1/admin/reports/invoices/performance')->assertOk();
    expect((int) $performance->json('data.summary.total_invoices'))->toBe(1)
        ->and(collect($performance->json('data.employee_summary'))->pluck('employee_id')->all())->toBe([$this->rep1Employee->id]);

    $top = $as()->getJson('/api/v1/admin/reports/invoices/top-performers')->assertOk();
    expect(collect($top->json('data'))->pluck('employee_id')->all())->toBe([$this->rep1Employee->id]);
});

test('admin sales reports still cover every rep', function () {
    $adminToken = $this->adminUser->createToken('token')->plainTextToken;

    $numbers = collect($this->withHeader('Authorization', "Bearer {$adminToken}")
        ->getJson('/api/v1/admin/reports/invoices')->assertOk()->json('data.invoices'))
        ->pluck('invoice_number')->sort()->values()->all();

    expect($numbers)->toBe(['INV-TEST-001', 'INV-TEST-002']);
});

test('the signed-in user payload says whether reports are confined', function () {
    $this->actingAs($this->rep1User)->getJson('/api/v1/auth/user')->assertOk()->assertJsonPath('data.user.is_sales_scoped', true);
    $this->actingAs($this->adminUser)->getJson('/api/v1/auth/user')->assertOk()->assertJsonPath('data.user.is_sales_scoped', false);
});
