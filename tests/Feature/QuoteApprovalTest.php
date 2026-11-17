<?php

use App\Models\Customer;
use App\Models\Employee;
use App\Models\Feature;
use App\Models\Product;
use App\Models\Quote;
use App\Models\Role;
use App\Models\User;

/**
 * A rep's quote waits for a manager's approval before it may be sent to the
 * customer; editing it puts it back in line. An approver's own quotes are
 * approved as written.
 */
beforeEach(function () {
    $sells = Role::firstOrCreate(['name' => 'sells'], ['display_name' => 'مبيعات', 'guard_name' => 'web', 'is_active' => true]);
    $sales = Feature::firstOrCreate(['key' => 'sales'], ['name_ar' => 'المبيعات', 'name_en' => 'Sales', 'group' => 'sales', 'is_enabled' => true]);
    $sells->features()->syncWithoutDetaching([$sales->id]);

    $this->rep = User::factory()->create(['is_admin' => false, 'role_id' => $sells->id]);
    $this->rep->roles()->sync([$sells->id]);
    $employee = Employee::create([
        'user_id' => $this->rep->id, 'first_name' => 'مندوب', 'last_name' => 'أ',
        'email' => 'rep-approval@test.com', 'phone' => '+966500009999',
        'position' => 'مندوب مبيعات', 'hire_date' => now()->toDateString(), 'status' => 'نشط',
    ]);
    $this->admin = User::factory()->admin()->create();

    $this->customer = Customer::create(['name' => 'عميل', 'phone' => '0999000401', 'employee_id' => $employee->id]);
    $this->product = Product::factory()->create(['price' => 50]);
    $this->payload = fn () => [
        'customer_id' => $this->customer->id,
        'items' => [['product_id' => $this->product->id, 'quantity' => 2, 'unit_price' => 50]],
    ];
});

test('a rep\'s quote is pending and cannot be sent until approved', function () {
    $api = $this->actingAs($this->rep, 'sanctum');
    $quote = $api->postJson('/api/v1/quotes', ($this->payload)())->assertCreated()->json('data');

    expect($quote['approval_status'])->toBe('pending');
    expect($quote['can_share'])->toBeFalse();
    expect($quote['can_approve'])->toBeFalse();

    $api->putJson("/api/v1/quotes/{$quote['id']}/status", ['status' => 'sent'])->assertUnprocessable();
    $api->postJson("/api/v1/quotes/{$quote['id']}/approve")->assertForbidden();

    $approved = $this->actingAs($this->admin, 'sanctum')
        ->postJson("/api/v1/quotes/{$quote['id']}/approve")->assertOk()->json('data');
    expect($approved['approval_status'])->toBe('approved');
    expect($approved['approver']['id'])->toBe($this->admin->id);

    $this->actingAs($this->rep, 'sanctum')
        ->putJson("/api/v1/quotes/{$quote['id']}/status", ['status' => 'sent'])->assertOk();
});

test('rejecting needs a reason and keeps the quote unsendable', function () {
    $id = $this->actingAs($this->rep, 'sanctum')->postJson('/api/v1/quotes', ($this->payload)())->json('data.id');
    $admin = $this->actingAs($this->admin, 'sanctum');

    $admin->postJson("/api/v1/quotes/{$id}/reject-approval")->assertUnprocessable();
    $admin->postJson("/api/v1/quotes/{$id}/reject-approval", ['note' => 'السعر منخفض'])
        ->assertOk()->assertJsonPath('data.approval_status', 'rejected')->assertJsonPath('data.approval_note', 'السعر منخفض');

    $this->actingAs($this->rep, 'sanctum')
        ->putJson("/api/v1/quotes/{$id}/status", ['status' => 'sent'])->assertUnprocessable();
});

test('editing an approved quote sends it back for approval', function () {
    $id = $this->actingAs($this->rep, 'sanctum')->postJson('/api/v1/quotes', ($this->payload)())->json('data.id');
    $this->actingAs($this->admin, 'sanctum')->postJson("/api/v1/quotes/{$id}/approve")->assertOk();

    $this->actingAs($this->rep, 'sanctum')->putJson("/api/v1/quotes/{$id}", ($this->payload)())
        ->assertOk()->assertJsonPath('data.approval_status', 'pending');
});

test('an approver\'s own quote is approved as written', function () {
    $this->actingAs($this->admin, 'sanctum')->postJson('/api/v1/quotes', ($this->payload)())
        ->assertCreated()
        ->assertJsonPath('data.approval_status', 'approved')
        ->assertJsonPath('data.can_share', true);
});

test('approvers can filter quotes awaiting approval', function () {
    $this->actingAs($this->rep, 'sanctum')->postJson('/api/v1/quotes', ($this->payload)());
    $this->actingAs($this->admin, 'sanctum')->postJson('/api/v1/quotes', ($this->payload)());

    $ids = $this->actingAs($this->admin, 'sanctum')
        ->getJson('/api/v1/quotes?approval_status=pending')->assertOk()->json('data.quotes');
    expect($ids)->toHaveCount(1);
    expect(Quote::where('approval_status', 'pending')->count())->toBe(1);
});
