<?php

use App\Models\Product;
use App\Models\Role;
use App\Models\Supplier;
use App\Models\User;
use App\Models\Warehouse;

/**
 * The supplier payments screen: searching and filtering on the server, totals
 * by method, and a debt figure that advances don't shrink.
 */
beforeEach(function () {
    $this->admin = User::factory()->create([
        'is_admin' => true,
        'role_id' => Role::firstOrCreate(['name' => 'admin'], ['display_name' => 'admin'])->id,
    ]);

    $this->warehouse = Warehouse::create(['name' => 'الرئيسي', 'code' => 'WH-SPL', 'is_active' => true, 'is_primary' => true]);
    $this->product = Product::create(['name_ar' => 'خلاط', 'sku' => 'MIX-SPL', 'price' => 60, 'cost_price' => 30]);
    $this->pumps = Supplier::create(['name' => 'مورّد المضخات', 'status' => 'active', 'balance' => 0]);
    $this->pipes = Supplier::create(['name' => 'مورّد الأنابيب', 'status' => 'active', 'balance' => 0]);

    // 300 owed to each.
    foreach ([$this->pumps, $this->pipes] as $supplier) {
        $this->actingAs($this->admin)->postJson('/api/v1/purchase-receipts', [
            'supplier_id' => $supplier->id,
            'warehouse_id' => $this->warehouse->id,
            'receipt_date' => now()->toDateString(),
            'items' => [['product_id' => $this->product->id, 'quantity' => 10, 'unit_price' => 30]],
        ])->assertCreated();
    }

    $this->pay = fn (Supplier $supplier, float $amount, string $method, array $extra = []) => $this->actingAs($this->admin)
        ->postJson('/api/v1/admin/supplier-payments', array_merge([
            'supplier_id' => $supplier->id,
            'amount' => $amount,
            'payment_method' => $method,
        ], $extra))
        ->assertCreated();

    $this->list = fn (array $query = []) => $this->actingAs($this->admin)
        ->getJson('/api/v1/admin/supplier-payments?'.http_build_query($query))
        ->assertOk()
        ->json('data');
});

test('payments are searched on the server, beyond the page being shown', function () {
    ($this->pay)($this->pumps, 100, 'cash', ['reference' => 'CHQ-7781']);
    ($this->pay)($this->pipes, 50, 'bank_transfer');

    $found = ($this->list)(['search' => '7781', 'per_page' => 1]);
    expect($found['payments'])->toHaveCount(1)
        ->and($found['payments'][0]['reference'])->toBe('CHQ-7781');

    expect(($this->list)(['search' => 'الأنابيب'])['payments'])->toHaveCount(1);
});

test('the list filters by method and totals each method', function () {
    ($this->pay)($this->pumps, 100, 'cash');
    ($this->pay)($this->pumps, 40, 'cash');
    ($this->pay)($this->pipes, 60, 'bank_transfer');

    $all = ($this->list)();
    expect($all['total_paid'])->toEqual(200)
        ->and($all['by_method']['cash'])->toEqual(['count' => 2, 'total' => 140])
        ->and($all['by_method']['bank_transfer'])->toEqual(['count' => 1, 'total' => 60]);

    $cash = ($this->list)(['payment_method' => 'cash']);
    expect($cash['payments'])->toHaveCount(2)
        ->and($cash['total_paid'])->toEqual(140);
});

test('an advance to one supplier does not shrink what is owed to another', function () {
    // 300 owed to the pipes supplier; 500 paid to the pumps supplier leaves a
    // 200 advance with them.
    ($this->pay)($this->pumps, 500, 'bank_transfer');

    $data = $this->actingAs($this->admin)
        ->getJson('/api/v1/admin/supplier-payments/outstanding')
        ->assertOk()
        ->json('data');

    expect($data['total_outstanding'])->toEqual(300)
        ->and($data['owed_count'])->toBe(1)
        ->and($data['total_advances'])->toEqual(200)
        ->and($data['advances_count'])->toBe(1);
});
