<?php

use App\Models\Customer;
use App\Models\Employee;
use App\Models\Product;
use App\Models\SalesOrder;
use App\Models\User;
use App\Models\Warehouse;

function mobileWarehouse(): Warehouse
{
    return Warehouse::create([
        'name' => 'Mobile Warehouse',
        'code' => 'WH-MOB-'.uniqid(),
        'city' => 'Damascus',
        'country' => 'SY',
        'is_active' => true,
        'location_type' => 'warehouse',
    ]);
}

function mobileEmployee(Warehouse $warehouse, string $email, ?User $user = null): Employee
{
    return Employee::create([
        'user_id' => $user?->id,
        'warehouse_id' => $warehouse->id,
        'first_name' => 'Rep',
        'last_name' => explode('@', $email)[0],
        'email' => $email,
        'phone' => '09'.random_int(10000000, 99999999),
        'status' => 'active',
    ]);
}

function mobileProduct(): Product
{
    return Product::create([
        'name_ar' => 'منتج تطبيق',
        'name_en' => 'Mobile Product',
        'sku' => 'SKU-MOB-'.uniqid(),
        'price' => 150,
        'is_active' => true,
    ]);
}

it('attributes an app order to the rep who raised it and makes the customer theirs', function () {
    $warehouse = mobileWarehouse();
    $rep = User::factory()->create();
    $employee = mobileEmployee($warehouse, 'rep.one@example.com', $rep);
    $product = mobileProduct();

    // The app sends the rep's own login token with the order.
    $response = $this->actingAs($rep, 'sanctum')->postJson('/api/v1/purchase-requests', [
        'name' => 'عميل التطبيق',
        'phone' => '966500001111',
        'address' => 'دمشق',
        'employee_id' => $employee->id,
        'items' => [
            ['product_id' => $product->id, 'quantity' => 2],
        ],
    ]);

    $response->assertStatus(201)
        ->assertJsonPath('data.assigned_employee_id', $employee->id);

    $order = SalesOrder::where('order_number', $response->json('data.order_number'))->firstOrFail();
    expect($order->assigned_employee_id)->toBe($employee->id);

    $customer = Customer::where('phone', '966500001111')->firstOrFail();
    expect($customer->employee_id)->toBe($employee->id)
        ->and($customer->primaryEmployee()->first()?->id)->toBe($employee->id);
});

it('leaves an app order unattributed when no rep is sent', function () {
    $product = mobileProduct();

    $response = $this->postJson('/api/v1/purchase-requests', [
        'name' => 'زائر',
        'phone' => '966500002222',
        'address' => 'حلب',
        'items' => [
            ['product_id' => $product->id, 'quantity' => 1],
        ],
    ]);

    $response->assertStatus(201);

    $customer = Customer::where('phone', '966500002222')->firstOrFail();
    expect($customer->employee_id)->toBeNull()
        ->and($customer->customerEmployees()->count())->toBe(0);
});

it('does not demote the primary rep when a second rep serves the same customer', function () {
    $warehouse = mobileWarehouse();
    $firstUser = User::factory()->create();
    $secondUser = User::factory()->create();
    $first = mobileEmployee($warehouse, 'rep.first@example.com', $firstUser);
    $second = mobileEmployee($warehouse, 'rep.second@example.com', $secondUser);
    $product = mobileProduct();

    $payload = [
        'name' => 'عميل مشترك',
        'phone' => '966500003333',
        'address' => 'حمص',
        'items' => [['product_id' => $product->id, 'quantity' => 1]],
    ];

    $this->actingAs($firstUser, 'sanctum')->postJson('/api/v1/purchase-requests', $payload + ['employee_id' => $first->id])
        ->assertStatus(201);
    $this->actingAs($secondUser, 'sanctum')->postJson('/api/v1/purchase-requests', $payload + ['employee_id' => $second->id])
        ->assertStatus(201);

    $customer = Customer::where('phone', '966500003333')->firstOrFail();

    expect($customer->customerEmployees()->pluck('employees.id')->sort()->values()->all())
        ->toBe(collect([$first->id, $second->id])->sort()->values()->all())
        ->and($customer->primaryEmployee()->first()?->id)->toBe($first->id)
        ->and($customer->employee_id)->toBe($first->id);
});

it('rejects an order that names an employee who does not exist', function () {
    $product = mobileProduct();

    $this->postJson('/api/v1/purchase-requests', [
        'name' => 'عميل',
        'phone' => '966500004444',
        'employee_id' => 999999,
        'items' => [['product_id' => $product->id, 'quantity' => 1]],
    ])->assertStatus(422)->assertJsonValidationErrors('employee_id');
});

it('links the customer to the rep on a staff-raised order too', function () {
    $warehouse = mobileWarehouse();
    $user = User::factory()->create(['is_admin' => true]);
    $employee = mobileEmployee($warehouse, 'rep.staff@example.com', $user);
    $product = mobileProduct();

    $this->actingAs($user, 'sanctum')
        ->postJson('/api/v1/admin/purchase-requests', [
            'name' => 'عميل الفريق',
            'phone' => '966500005555',
            'assigned_employee_id' => $employee->id,
            'items' => [['product_id' => $product->id, 'quantity' => 1]],
        ])
        ->assertStatus(201);

    $customer = Customer::where('phone', '966500005555')->firstOrFail();

    expect($customer->primaryEmployee()->first()?->id)->toBe($employee->id)
        ->and($customer->employee_id)->toBe($employee->id);
});

it('lists customers a rep earned through an order, not only those they created', function () {
    $warehouse = mobileWarehouse();
    $user = User::factory()->create();
    $employee = mobileEmployee($warehouse, 'rep.list@example.com', $user);
    $product = mobileProduct();

    $this->actingAs($user, 'sanctum')->postJson('/api/v1/purchase-requests', [
        'name' => 'عميل من الطلبية',
        'phone' => '966500006666',
        'employee_id' => $employee->id,
        'items' => [['product_id' => $product->id, 'quantity' => 1]],
    ])->assertStatus(201);

    // The link alone must be enough: clear the legacy column to prove the
    // listing is not relying on it.
    $customer = Customer::where('phone', '966500006666')->firstOrFail();
    $customer->forceFill(['employee_id' => null])->saveQuietly();

    $this->actingAs($user, 'sanctum')
        ->getJson('/api/v1/staff/customers')
        ->assertOk()
        ->assertJsonPath('data.0.phone', '966500006666');
});

it('ignores a rep named by a visitor who is not signed in as that rep', function () {
    $warehouse = mobileWarehouse();
    $employee = mobileEmployee($warehouse, 'rep.claimed@example.com', User::factory()->create());
    $otherRep = User::factory()->create();
    mobileEmployee($warehouse, 'rep.other@example.com', $otherRep);
    $product = mobileProduct();
    $payload = ['name' => 'زائر', 'items' => [['product_id' => $product->id, 'quantity' => 1]], 'employee_id' => $employee->id];

    // Anonymous, and signed in as a different rep: neither may claim it.
    $this->postJson('/api/v1/purchase-requests', $payload + ['phone' => '966500007777'])->assertStatus(201);
    $this->actingAs($otherRep, 'sanctum')->postJson('/api/v1/purchase-requests', $payload + ['phone' => '966500008888'])->assertStatus(201);

    expect(SalesOrder::whereNotNull('assigned_employee_id')->count())->toBe(0)
        ->and(Customer::whereNotNull('employee_id')->count())->toBe(0);
});

it('does not let a guest rename an existing customer by their phone number', function () {
    $customer = Customer::create(['name' => 'الاسم الأصلي', 'phone' => '966500009999', 'address' => 'العنوان الأصلي', 'status' => 'active']);

    $this->postJson('/api/v1/purchase-requests', [
        'name' => 'اسم مزيف', 'phone' => '966500009999', 'address' => 'عنوان مزيف',
        'items' => [['product_id' => mobileProduct()->id, 'quantity' => 1]],
    ])->assertStatus(201);

    expect($customer->fresh()->name)->toBe('الاسم الأصلي')
        ->and($customer->fresh()->address)->toBe('العنوان الأصلي');
});

it('shows a guest their orders only with phone and one of their order numbers', function () {
    $response = $this->postJson('/api/v1/purchase-requests', [
        'name' => 'ضيف', 'phone' => '966500001234',
        'items' => [['product_id' => mobileProduct()->id, 'quantity' => 1]],
    ])->assertStatus(201);
    $number = $response->json('data.order_number');

    $this->getJson('/api/v1/purchase-requests/orders?phone=966500001234')->assertStatus(422);
    $this->getJson('/api/v1/purchase-requests/orders?phone=966500001234&order_number=SO-999999')->assertStatus(404);
    $this->getJson('/api/v1/purchase-requests/orders?phone=966500001234&order_number='.$number)
        ->assertOk()->assertJsonPath('success', true);
});

it('does not hand out a login for an existing customer through subscribe', function () {
    Customer::create(['name' => 'صاحب الحساب', 'phone' => '966500004321', 'status' => 'active', 'password' => bcrypt('original-secret')]);

    $this->postJson('/api/v1/subscribe', ['name' => 'مهاجم', 'phone' => '966500004321', 'password' => 'attacker-pass'])
        ->assertStatus(409)
        ->assertJsonMissingPath('data.token');

    expect(\Illuminate\Support\Facades\Hash::check('original-secret', Customer::where('phone', '966500004321')->value('password')))->toBeTrue();
});
