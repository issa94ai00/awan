<?php

use App\Models\Customer;
use App\Models\Invoice;
use App\Models\SalesOrder;
use App\Models\SalesOrderStatusHistory;
use App\Models\User;

/**
 * The sales-orders list: what each row owes, which orders are stuck, and
 * counts and totals that describe the search rather than the page.
 */
beforeEach(function () {
    $this->admin = User::factory()->admin()->create();
    $this->customer = Customer::create(['name' => 'عميل الطلبات', 'phone' => '0999111222']);
    $this->other = Customer::create(['name' => 'شركة البناء', 'phone' => '0999111333']);

    $this->order = function (string $status, float $total, array $extra = []) {
        static $n = 0;
        $n++;

        return SalesOrder::create($extra + [
            'order_number' => 'SO-LIST-'.$n,
            'customer_id' => $this->customer->id,
            'status' => $status,
            'order_date' => now()->toDateString(),
            'subtotal' => $total,
            'total' => $total,
        ]);
    };

    $this->invoice = fn (SalesOrder $order, float $paid, string $number = null) => Invoice::create([
        'invoice_number' => $number ?? 'INV-LIST-'.$order->id,
        'customer_id' => $order->customer_id,
        'sales_order_id' => $order->id,
        'status' => 'sent',
        'subtotal' => $order->total,
        'tax' => 0,
        'total' => $order->total,
        'paid_amount' => $paid,
        'due_amount' => (float) $order->total - $paid,
    ]);

    $this->list = fn (array $query = []) => $this->actingAs($this->admin, 'sanctum')
        ->getJson('/api/v1/sales-orders?'.http_build_query($query))
        ->assertOk()
        ->json('data');
});

test('each row says what its invoice still owes', function () {
    $unpaid = ($this->order)('confirmed', 100);
    $partial = ($this->order)('delivered', 200);
    $paid = ($this->order)('delivered', 50);
    ($this->order)('pending', 70);

    ($this->invoice)($unpaid, 0);
    ($this->invoice)($partial, 80);
    ($this->invoice)($paid, 50);

    $rows = collect(($this->list)()['sales_orders'])->keyBy('id');

    expect($rows[$unpaid->id]['payment']['state'])->toBe('unpaid')
        ->and($rows[$partial->id]['payment']['state'])->toBe('partial')
        ->and((float) $rows[$partial->id]['payment']['due'])->toEqual(120.0)
        ->and($rows[$paid->id]['payment']['state'])->toBe('paid');

    $due = ($this->list)(['payment' => 'due']);
    expect(collect($due['sales_orders'])->pluck('id')->sort()->values()->all())->toBe([$unpaid->id, $partial->id])
        ->and($due['totals']['to_collect'])->toEqual(220)
        ->and($due['totals']['to_collect_count'])->toBe(2)
        ->and($due['totals']['open_value'])->toEqual(170);
});

test('orders stuck in a stage are flagged, counted and filterable', function () {
    $stuck = ($this->order)('pending', 10);
    $stuck->forceFill(['created_at' => now()->subDays(5)])->save();

    $fresh = ($this->order)('pending', 10);

    $movedRecently = ($this->order)('confirmed', 10);
    $movedRecently->forceFill(['created_at' => now()->subDays(10)])->save();
    SalesOrderStatusHistory::create([
        'sales_order_id' => $movedRecently->id,
        'from_status' => 'pending',
        'to_status' => 'confirmed',
        'user_id' => $this->admin->id,
    ]);

    $late = ($this->order)('shipped', 10, ['expected_delivery' => now()->subDays(2)->toDateString()]);

    $data = ($this->list)(['attention' => 1]);

    expect(collect($data['sales_orders'])->pluck('id')->sort()->values()->all())->toBe([$stuck->id, $late->id])
        ->and($data['status_counts']['attention'])->toBe(2)
        ->and($data['status_counts']['overdue'])->toBe(1);

    $row = collect(($this->list)()['sales_orders'])->firstWhere('id', $stuck->id);
    expect($row['follow_up']['is_stalled'])->toBeTrue()
        ->and($row['follow_up']['days_in_stage'])->toBe(5);

    // Working out the age used to reset the order's own timestamp to midnight.
    expect($row['created_at'])->toBe($stuck->refresh()->created_at->toJSON())
        ->and(collect(($this->list)()['sales_orders'])->firstWhere('id', $fresh->id)['follow_up']['needs_attention'])->toBeFalse();
});

test('search reaches invoice numbers and the counts follow it', function () {
    $found = ($this->order)('delivered', 40, ['customer_id' => $this->other->id]);
    ($this->invoice)($found, 0, 'INV-FIND-ME');
    ($this->order)('pending', 10);
    ($this->order)('pending', 10);

    $data = ($this->list)(['search' => 'FIND-ME']);
    expect($data['sales_orders'])->toHaveCount(1)
        ->and($data['status_counts']['all'])->toBe(1)
        ->and($data['status_counts']['pending'])->toBe(0);

    expect(($this->list)(['search' => 'البناء'])['sales_orders'])->toHaveCount(1);
});

test('the in-progress filter and sorting', function () {
    ($this->order)('pending', 10);
    $a = ($this->order)('confirmed', 30);
    $b = ($this->order)('shipped', 20);
    ($this->order)('delivered', 99);

    $rows = ($this->list)(['open' => 1, 'sort' => 'total', 'direction' => 'asc'])['sales_orders'];

    expect(collect($rows)->pluck('id')->all())->toBe([$b->id, $a->id])
        ->and($rows[0])->toHaveKey('items_count')
        ->and($rows[0])->not->toHaveKey('items');
});

test('filter options list the warehouses and reps that hold orders', function () {
    ($this->order)('pending', 10);

    $data = ($this->list)(['with_options' => 1]);

    expect($data['options'])->toHaveKeys(['warehouses', 'employees'])
        ->and(($this->list)()['options'])->toBeNull();
});
