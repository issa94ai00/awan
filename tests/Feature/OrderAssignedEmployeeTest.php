<?php

use App\Models\Customer;
use App\Models\Employee;
use App\Models\Product;
use App\Models\SalesOrder;
use App\Models\User;
use App\Models\Warehouse;

/**
 * Who a sales order belongs to.
 *
 * The responsible employee is what puts an order in someone's "my orders", what
 * names a person to chase it, and what the fulfilment warehouse is derived from
 * when none is given. An order without one is nobody's problem.
 */
beforeEach(function () {
    $this->warehouse = Warehouse::create([
        'name' => 'فرع جدة',
        'code' => 'WH-JED',
        'location' => 'جدة',
        'status' => 'active',
        'is_active' => true,
        'location_type' => Warehouse::TYPE_BRANCH,
    ]);

    $this->other = Warehouse::create([
        'name' => 'فرع الرياض',
        'code' => 'WH-RYD',
        'location' => 'الرياض',
        'status' => 'active',
        'is_active' => true,
        'location_type' => Warehouse::TYPE_BRANCH,
    ]);

    $this->makeEmployee = function (string $email, ?Warehouse $warehouse, ?User $user = null) {
        return Employee::create([
            'name' => 'موظف ' . $email,
            'email' => $email,
            'phone' => '+96650' . random_int(1000000, 9999999),
            'position' => 'مندوب مبيعات',
            'department' => 'المبيعات',
            'hire_date' => now()->subYear()->toDateString(),
            'status' => 'نشط',
            'user_id' => $user?->id,
            'warehouse_id' => $warehouse?->id,
        ]);
    };

    $this->user = User::factory()->create();
    $this->token = $this->user->createToken('test-token')->plainTextToken;

    $this->customer = Customer::create([
        'name' => 'عميل',
        'email' => 'customer@example.com',
        'phone' => '+966500000002',
        'status' => 'نشط',
    ]);

    $this->product = Product::create(['name_ar' => 'صنف', 'sku' => 'SKU-1', 'price' => 100]);

    $this->post = fn (array $payload) => $this->withHeader('Authorization', "Bearer {$this->token}")
        ->postJson('/api/v1/sales-orders', array_merge([
            'customer_id' => $this->customer->id,
            'order_date' => now()->toDateString(),
            'items' => [[
                'product_id' => $this->product->id,
                'quantity' => 2,
                'unit_price' => 150,
                'discount' => 0,
                'tax' => 0,
            ]],
        ], $payload));
});

test('the responsible employee is taken from the request', function () {
    $rep = ($this->makeEmployee)('rep@example.com', $this->warehouse);

    ($this->post)(['assigned_employee_id' => $rep->id])
        ->assertStatus(201)
        ->assertJsonPath('data.assigned_employee_id', $rep->id)
        // And the warehouse follows the person the order belongs to.
        ->assertJsonPath('data.fulfillment_warehouse_id', $this->warehouse->id);
});

test('a request that omits it falls back to the signed-in user', function () {
    $mine = ($this->makeEmployee)('mine@example.com', $this->other, $this->user);

    // An order raised without naming anyone still belongs to whoever raised it,
    // rather than being left for nobody to follow up.
    ($this->post)([])
        ->assertStatus(201)
        ->assertJsonPath('data.assigned_employee_id', $mine->id)
        ->assertJsonPath('data.fulfillment_warehouse_id', $this->other->id);
});

test('an explicit employee wins over the signed-in user', function () {
    ($this->makeEmployee)('mine@example.com', $this->other, $this->user);
    $rep = ($this->makeEmployee)('rep@example.com', $this->warehouse);

    // The back office files an order on behalf of the rep who took it.
    ($this->post)(['assigned_employee_id' => $rep->id])
        ->assertStatus(201)
        ->assertJsonPath('data.assigned_employee_id', $rep->id);
});

test('an unknown employee is refused rather than silently dropped', function () {
    ($this->post)(['assigned_employee_id' => 999999])
        ->assertStatus(422)
        ->assertJsonValidationErrors('assigned_employee_id');
});

test('an order can be reassigned, and the warehouse follows', function () {
    $first = ($this->makeEmployee)('first@example.com', $this->warehouse);
    $second = ($this->makeEmployee)('second@example.com', $this->other);

    ($this->post)(['assigned_employee_id' => $first->id])->assertStatus(201);
    $order = SalesOrder::latest('id')->first();

    $this->withHeader('Authorization', "Bearer {$this->token}")
        ->putJson("/api/v1/sales-orders/{$order->id}", [
            'customer_id' => $this->customer->id,
            'assigned_employee_id' => $second->id,
            'items' => [[
                'product_id' => $this->product->id,
                'quantity' => 2,
                'unit_price' => 150,
            ]],
        ])
        ->assertStatus(200);

    $order->refresh();
    expect((int) $order->assigned_employee_id)->toBe($second->id);
    expect((int) $order->fulfillment_warehouse_id)->toBe($this->other->id);
});

test('an update that leaves it out keeps the current owner', function () {
    $rep = ($this->makeEmployee)('rep@example.com', $this->warehouse);

    ($this->post)(['assigned_employee_id' => $rep->id])->assertStatus(201);
    $order = SalesOrder::latest('id')->first();

    // Editing something unrelated must not orphan the order.
    $this->withHeader('Authorization', "Bearer {$this->token}")
        ->putJson("/api/v1/sales-orders/{$order->id}", [
            'customer_id' => $this->customer->id,
            'notes' => 'تعديل ملاحظة فقط',
            'items' => [[
                'product_id' => $this->product->id,
                'quantity' => 2,
                'unit_price' => 150,
            ]],
        ])
        ->assertStatus(200);

    expect((int) $order->refresh()->assigned_employee_id)->toBe($rep->id);
});

test('login tells the app which employee it is', function () {
    $employee = ($this->makeEmployee)('mine@example.com', $this->warehouse, $this->user);

    // Without this the app has no employee id to send, which is why every order
    // it raised arrived unattributed.
    $this->postJson('/api/v1/auth/login', [
        'email' => $this->user->email,
        'password' => 'password',
    ])
        ->assertStatus(200)
        ->assertJsonPath('data.user.employee_id', $employee->id)
        ->assertJsonPath('data.user.employee_warehouse_id', $this->warehouse->id);
});

test('a user with no employee record reports none rather than failing', function () {
    $this->postJson('/api/v1/auth/login', [
        'email' => $this->user->email,
        'password' => 'password',
    ])
        ->assertStatus(200)
        ->assertJsonPath('data.user.employee_id', null);
});
