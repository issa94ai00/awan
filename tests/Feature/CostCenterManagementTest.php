<?php

use App\Models\CostCenter;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\Role;
use App\Models\User;
use App\Models\Warehouse;
use App\Services\Accounting\LedgerPostingService;

/**
 * Defining the parts of the business, and the rules that keep the dimension
 * meaning what it says.
 *
 * Two of them matter more than they look. A warehouse may be claimed by one
 * centre only — two would make the attribution depend on which row was read
 * first. And a centre that has carried figures is deactivated rather than
 * deleted: deleting one detaches it from every line that named it, so a report
 * of a closed month would silently move that activity into "unattributed"
 * without a single ledger figure changing.
 */
beforeEach(function () {
    CostCenter::forgetWarehouseCache();

    $this->admin = User::factory()->create([
        'is_admin' => true,
        'role_id' => Role::firstOrCreate(['name' => 'admin'], ['display_name' => 'admin'])->id,
    ]);

    $this->warehouse = Warehouse::create([
        'name' => 'فرع الشمال',
        'code' => 'WH-N',
        'location' => 'حلب',
        'status' => 'active',
        'is_active' => true,
        'location_type' => Warehouse::TYPE_WAREHOUSE,
    ]);
});

test('a centre can be defined for a division that holds no stock', function () {
    $this->actingAs($this->admin)->postJson('/api/v1/admin/accounting/cost-centers', [
        'code' => 'CC-ADM',
        'name' => 'الإدارة',
    ])->assertCreated();

    $center = CostCenter::where('code', 'CC-ADM')->first();

    // No warehouse: nothing is attributed to it automatically, and it carries
    // only what is posted to it deliberately — which is what an overhead is.
    expect($center->warehouse_id)->toBeNull();
    expect($center->is_active)->toBeTrue();
});

test('two centres cannot claim the same warehouse', function () {
    $this->actingAs($this->admin)->postJson('/api/v1/admin/accounting/cost-centers', [
        'code' => 'CC-N1',
        'name' => 'الشمال',
        'warehouse_id' => $this->warehouse->id,
    ])->assertCreated();

    $this->actingAs($this->admin)->postJson('/api/v1/admin/accounting/cost-centers', [
        'code' => 'CC-N2',
        'name' => 'الشمال مرة أخرى',
        'warehouse_id' => $this->warehouse->id,
    ])->assertStatus(422);

    expect(CostCenter::where('warehouse_id', $this->warehouse->id)->count())->toBe(1);
});

test('only warehouses without a centre are offered', function () {
    $this->actingAs($this->admin)->postJson('/api/v1/admin/accounting/cost-centers', [
        'code' => 'CC-N1',
        'name' => 'الشمال',
        'warehouse_id' => $this->warehouse->id,
    ])->assertCreated();

    $available = $this->actingAs($this->admin)
        ->getJson('/api/v1/admin/accounting/cost-centers')
        ->assertOk()
        ->json('data.available_warehouses');

    expect(collect($available)->pluck('id'))->not->toContain($this->warehouse->id);
});

test('a centre that has carried figures cannot be deleted', function () {
    $center = CostCenter::create([
        'code' => 'CC-N1',
        'name' => 'الشمال',
        'warehouse_id' => $this->warehouse->id,
        'is_active' => true,
    ]);
    CostCenter::forgetWarehouseCache();

    $customer = Customer::create(['name' => 'عميل', 'email' => 'x@example.test', 'status' => 'active']);

    app(LedgerPostingService::class)->postInvoice(Invoice::create([
        'invoice_number' => 'INV-CC',
        'customer_id' => $customer->id,
        'warehouse_id' => $this->warehouse->id,
        'status' => 'sent',
        'subtotal' => 500,
        'tax' => 0,
        'total' => 500,
        'due_amount' => 500,
    ]));

    $this->actingAs($this->admin)
        ->deleteJson('/api/v1/admin/accounting/cost-centers/'.$center->id)
        ->assertStatus(422);

    // Deleting it would move a closed month's activity into "unattributed"
    // without a single ledger figure changing.
    expect(CostCenter::find($center->id))->not->toBeNull();
});

test('an unused centre can be deleted', function () {
    $center = CostCenter::create(['code' => 'CC-TMP', 'name' => 'مؤقت', 'is_active' => true]);

    $this->actingAs($this->admin)
        ->deleteJson('/api/v1/admin/accounting/cost-centers/'.$center->id)
        ->assertOk();

    expect(CostCenter::find($center->id))->toBeNull();
});

test('deactivating a centre stops it claiming new postings', function () {
    $center = CostCenter::create([
        'code' => 'CC-N1',
        'name' => 'الشمال',
        'warehouse_id' => $this->warehouse->id,
        'is_active' => true,
    ]);
    CostCenter::forgetWarehouseCache();

    expect(CostCenter::forWarehouse($this->warehouse->id))->toBe($center->id);

    $this->actingAs($this->admin)->putJson('/api/v1/admin/accounting/cost-centers/'.$center->id, [
        'code' => 'CC-N1',
        'name' => 'الشمال',
        'warehouse_id' => $this->warehouse->id,
        'is_active' => false,
    ])->assertOk();

    expect(CostCenter::forWarehouse($this->warehouse->id))->toBeNull();
});

test('centres are refused to a sales account', function () {
    $sales = User::factory()->create([
        'role_id' => Role::firstOrCreate(['name' => 'sells'], ['display_name' => 'sells'])->id,
    ]);

    $this->actingAs($sales)
        ->getJson('/api/v1/admin/accounting/cost-centers')
        ->assertForbidden();
});
