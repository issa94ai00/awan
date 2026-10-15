<?php

use App\Models\Customer;
use App\Models\Employee;
use App\Models\Feature;
use App\Models\Invoice;
use App\Models\PurchaseOrder;
use App\Models\Role;
use App\Models\SalesOrder;
use App\Models\Supplier;
use App\Models\User;

/**
 * A sales rep's purchase orders screen lists the orders that concern them:
 * the ones they raised, and those buying in for a sale of theirs. Other
 * staff still see every order.
 */
beforeEach(function () {
    $sells = Role::firstOrCreate(['name' => 'sells', 'guard_name' => 'web'], ['display_name' => 'مبيعات', 'is_active' => true]);
    $sells->features()->syncWithoutDetaching(Feature::pluck('id'));

    $this->rep = User::factory()->create();
    $this->rep->roles()->syncWithoutDetaching([$sells->id]);
    $this->repEmployee = Employee::create([
        'user_id' => $this->rep->id,
        'first_name' => 'مندوب',
        'last_name' => 'المشتريات',
        'email' => 'rep-po@example.test',
        'position' => 'مندوب مبيعات',
        'department' => 'المبيعات',
        'hire_date' => now()->subYear()->toDateString(),
        'status' => 'نشط',
    ]);

    $this->colleague = User::factory()->create();
    $this->supplier = Supplier::create(['name' => 'مورّد', 'status' => 'active', 'balance' => 0]);
    $this->customer = Customer::create(['name' => 'عميل', 'phone' => '0999111222']);

    $this->order = fn (array $attributes = []) => PurchaseOrder::create($attributes + [
        'supplier_id' => $this->supplier->id,
        'order_number' => 'PO-' . fake()->unique()->numerify('######'),
        'status' => 'pending',
        'subtotal' => 100,
        'total' => 100,
    ]);

    $this->own = ($this->order)(['created_by' => $this->rep->id]);

    $sale = SalesOrder::create([
        'order_number' => 'SO-REP-1',
        'customer_id' => $this->customer->id,
        'assigned_employee_id' => $this->repEmployee->id,
        'created_by' => $this->colleague->id,
        'subtotal' => 100, 'total' => 100, 'status' => 'pending',
    ]);
    $this->forSale = ($this->order)(['created_by' => $this->colleague->id, 'sales_order_id' => $sale->id]);

    $invoice = Invoice::create([
        'invoice_number' => 'INV-REP-1',
        'customer_id' => $this->customer->id,
        'created_by' => $this->rep->id,
        'status' => 'sent',
        'subtotal' => 100, 'tax' => 0, 'total' => 100, 'due_amount' => 100,
    ]);
    $this->forInvoice = ($this->order)(['created_by' => $this->colleague->id, 'invoice_id' => $invoice->id]);

    $this->other = ($this->order)(['created_by' => $this->colleague->id]);
});

it('lists a rep only the orders that concern them', function () {
    $response = $this->actingAs($this->rep)->getJson('/api/v1/admin/purchase-orders')->assertOk();

    expect(collect($response->json('data.orders'))->pluck('id')->sort()->values()->all())
        ->toBe(collect([$this->own->id, $this->forSale->id, $this->forInvoice->id])->sort()->values()->all())
        ->and($response->json('data.status_counts.all'))->toBe(3);
});

it('refuses a rep someone else\'s order', function () {
    $this->actingAs($this->rep)->getJson("/api/v1/admin/purchase-orders/{$this->other->id}")->assertForbidden();
    $this->actingAs($this->rep)
        ->putJson("/api/v1/admin/purchase-orders/{$this->other->id}/status", ['status' => 'cancelled'])
        ->assertForbidden();

    $this->actingAs($this->rep)->getJson("/api/v1/admin/purchase-orders/{$this->own->id}")->assertOk();
});

it('still lists every order to an admin', function () {
    $admin = User::factory()->admin()->create();

    $this->actingAs($admin)->getJson('/api/v1/admin/purchase-orders')
        ->assertOk()
        ->assertJsonPath('data.status_counts.all', 4);
});
