<?php

use App\Models\Employee;
use App\Models\Inquiry;
use App\Models\Payroll;
use App\Models\Product;
use App\Models\ReorderAlert;
use App\Models\Role;
use App\Models\User;
use App\Models\Warehouse;
use App\Models\WarehouseInventory;
use App\Models\Workflow;
use App\Services\Inventory\InventoryService;

/** Endpoints that used to answer 500; each must now behave. */
beforeEach(function () {
    $this->admin = User::factory()->create([
        'is_admin' => true,
        'role_id' => Role::firstOrCreate(['name' => 'admin'], ['display_name' => 'admin'])->id,
    ]);
    $this->wh = Warehouse::create([
        'name' => 'مستودع', 'code' => 'WH-CF', 'location' => 'سورية',
        'status' => 'active', 'is_active' => true, 'location_type' => Warehouse::TYPE_WAREHOUSE,
    ]);
    $this->product = Product::create(['name_ar' => 'منتج', 'sku' => 'SKU-CF', 'price' => 30, 'cost_price' => 10]);
});

it('reorder-points raises an alert with a suggested quantity when stock is below the point', function () {
    app(InventoryService::class)->receive($this->product->id, 1, $this->wh->id, ['key' => 'cf-open', 'unit_cost' => 10]);
    WarehouseInventory::where('product_id', $this->product->id)->update(['safety_stock' => 5, 'lead_time_days' => 3]);

    $this->actingAs($this->admin)->postJson('/api/v1/inventory/update-reorder-points', ['warehouse_id' => $this->wh->id])
        ->assertOk()->assertJsonPath('success', true);

    $alert = ReorderAlert::where('product_id', $this->product->id)->first();
    expect($alert)->not->toBeNull()->and((int) $alert->suggested_order_quantity)->toBeGreaterThan(0);
});

it('stores an inquiry sent without an email, and returns it', function () {
    $this->postJson('/api/v1/inquiries', [
        'name' => 'زائر', 'phone' => '0999999999', 'subject' => 'سؤال', 'message' => 'رسالة اختبار',
    ])->assertSuccessful();

    $inquiry = Inquiry::first();
    expect($inquiry)->not->toBeNull()->and($inquiry->email)->toBe('');
});

it('auto-generates payroll when no payment date is sent, paying at period end', function () {
    Employee::create([
        'first_name' => 'موظف', 'last_name' => 'س', 'email' => 'cf-emp@example.test',
        'position' => 'x', 'salary' => 800, 'hire_date' => now()->subYear()->toDateString(), 'status' => 'نشط',
    ]);
    $start = now()->subMonth()->startOfMonth()->toDateString();
    $end = now()->subMonth()->endOfMonth()->toDateString();

    $this->actingAs($this->admin)->postJson('/api/v1/payrolls/auto-generate', ['pay_period_start' => $start, 'pay_period_end' => $end])
        ->assertCreated();

    expect(Payroll::count())->toBe(1)
        ->and(Payroll::first()->payment_date->toDateString())->toBe($end);
});

it('refuses to execute an inactive or step-less workflow with a 422', function () {
    $workflow = Workflow::create(['name' => 'سير', 'type' => 'custom', 'is_active' => false, 'created_by' => $this->admin->id]);
    $id = $workflow->id;

    $this->actingAs($this->admin)->postJson("/api/v1/workflows/$id/execute")->assertStatus(422);
});
