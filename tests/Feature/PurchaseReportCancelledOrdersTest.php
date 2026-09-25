<?php

use App\Models\PurchaseOrder;
use App\Models\Supplier;
use App\Models\User;

/**
 * A cancelled purchase order bought nothing, so the purchases report leaves it
 * out of what was spent — unless it is asked for by status — and still says
 * how many there were.
 */
beforeEach(function () {
    $this->admin = User::factory()->admin()->create();
    $this->supplier = Supplier::create(['name' => 'مورّد', 'status' => 'active', 'balance' => 0]);
    $this->other = Supplier::create(['name' => 'مورّد آخر', 'status' => 'active', 'balance' => 0]);

    $this->order = fn (string $status, float $total, ?Supplier $supplier = null) => PurchaseOrder::create([
        'order_number' => 'PO-RPT-'.uniqid(),
        'supplier_id' => ($supplier ?? $this->supplier)->id,
        'status' => $status,
        'order_date' => now()->toDateString(),
        'subtotal' => $total,
        'total' => $total,
    ]);

    $this->get = fn (string $path, array $query = []) => $this->actingAs($this->admin, 'sanctum')
        ->getJson('/api/v1/admin/reports/purchases'.$path.'?'.http_build_query($query))
        ->assertOk()
        ->json('data');

    ($this->order)('completed', 300);
    ($this->order)('pending', 100);
    ($this->order)('cancelled', 5000);
    ($this->order)('cancelled', 700, $this->other);
});

test('cancelled orders are not spend, but are still counted', function () {
    $data = ($this->get)('');

    expect($data['purchase_orders'])->toHaveCount(2)
        ->and($data['summary']['total_orders'])->toBe(2)
        ->and($data['summary']['total_spend'])->toEqual(400)
        ->and($data['summary']['average_order_value'])->toEqual(200)
        ->and($data['summary']['cancelled_orders'])->toBe(2);
});

test('the cancelled count follows the supplier filter', function () {
    expect(($this->get)('', ['supplier_id' => $this->supplier->id])['summary']['cancelled_orders'])->toBe(1);
});

test('cancelled orders can still be looked at by status', function () {
    $data = ($this->get)('', ['status' => 'cancelled']);

    expect($data['purchase_orders'])->toHaveCount(2)
        ->and($data['summary']['total_spend'])->toEqual(5700);
});

test('suppliers are ranked by what was actually bought', function () {
    $dimensions = ($this->get)('/dimensions');

    expect(collect($dimensions['supplier_summary'])->pluck('supplier_name')->all())->toBe(['مورّد'])
        ->and($dimensions['supplier_summary'][0]['total_spend'])->toEqual(400);

    $top = $this->actingAs($this->admin, 'sanctum')
        ->getJson('/api/v1/admin/reports/purchases/top-suppliers')
        ->assertOk()
        ->json('data');

    expect($top)->toHaveCount(1)->and($top[0]['total_spend'])->toEqual(400);
});
