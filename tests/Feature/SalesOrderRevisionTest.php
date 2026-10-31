<?php

use App\Models\Customer;
use App\Models\Employee;
use App\Models\Product;
use App\Models\Role;
use App\Models\SalesOrder;
use App\Models\SalesOrderRevision;
use App\Models\User;
use App\Models\Warehouse;
use App\Models\WarehouseInventory;
use App\Services\Sales\SalesOrderWorkflowService;

/**
 * Who may edit a sales order at which stage, and what an edit past the
 * pending stage leaves behind.
 *
 * A manager edits at any stage. A sales rep edits until the order is delivered
 * or cancelled. Either way, once the order is past pending the version as it
 * stood is kept as a revision and the order moves to the next number.
 */
beforeEach(function () {
    $this->warehouse = Warehouse::create([
        'name' => 'المستودع الرئيسي',
        'code' => 'WH-REV',
        'status' => 'active',
        'is_active' => true,
        'is_primary' => true,
        'location_type' => Warehouse::TYPE_WAREHOUSE,
    ]);

    $this->product = Product::create(['name_ar' => 'خلاط مغسلة', 'sku' => 'MIX-1', 'price' => 100, 'cost_price' => 60]);

    WarehouseInventory::create([
        'warehouse_id' => $this->warehouse->id,
        'product_id' => $this->product->id,
        'quantity' => 100,
        'available_quantity' => 100,
        'reserved_quantity' => 0,
        'damaged_quantity' => 0,
        'quarantined_quantity' => 0,
    ]);

    $this->customer = Customer::create(['name' => 'عميل الإصدارات', 'email' => 'rev@example.com', 'phone' => '+963900000099', 'status' => 'نشط']);

    $this->manager = User::factory()->operator()->create();

    $sells = Role::firstOrCreate(['name' => 'sells'], ['display_name' => 'مبيعات', 'guard_name' => 'web', 'is_active' => true]);
    $this->rep = User::factory()->operator()->create(['is_admin' => false, 'role_id' => $sells->id]);
    $this->rep->roles()->syncWithoutDetaching([$sells->id]);
    $this->repEmployee = Employee::create([
        'user_id' => $this->rep->id,
        'warehouse_id' => $this->warehouse->id,
        'first_name' => 'مندوب',
        'last_name' => 'الإصدارات',
        'email' => 'rep-rev@example.com',
        'phone' => '+963900000098',
        'position' => 'مندوب مبيعات',
        'hire_date' => now()->toDateString(),
        'status' => 'نشط',
    ]);
    $this->repEmployee->customers()->syncWithoutDetaching([$this->customer->id]);

    $this->workflow = app(SalesOrderWorkflowService::class);

    // A pending order of 2 × 100, moved on to the given stage.
    $this->orderAt = function (string $stage, ?User $owner = null) {
        $owner ??= $this->manager;
        $order = SalesOrder::create([
            'order_number' => 'SO-REV-'.uniqid(),
            'customer_id' => $this->customer->id,
            'status' => SalesOrder::STATUS_PENDING,
            'order_date' => now()->toDateString(),
            'fulfillment_warehouse_id' => $this->warehouse->id,
            'assigned_employee_id' => $owner->is($this->rep) ? $this->repEmployee->id : null,
            'subtotal' => 200,
            'total' => 200,
            'created_by' => $owner->id,
        ]);
        $order->items()->create(['product_id' => $this->product->id, 'quantity' => 2, 'unit_price' => 100]);

        $this->actingAs($this->manager);
        $path = [
            SalesOrder::STATUS_CONFIRMED => [SalesOrder::STATUS_CONFIRMED],
            SalesOrder::STATUS_SHIPPED => [SalesOrder::STATUS_CONFIRMED, SalesOrder::STATUS_PROCESSING, SalesOrder::STATUS_SHIPPED],
            SalesOrder::STATUS_DELIVERED => [SalesOrder::STATUS_CONFIRMED, SalesOrder::STATUS_PROCESSING, SalesOrder::STATUS_SHIPPED, SalesOrder::STATUS_DELIVERED],
            SalesOrder::STATUS_CANCELLED => [SalesOrder::STATUS_CANCELLED],
        ][$stage] ?? [];
        foreach ($path as $step) {
            $this->workflow->transitionTo($order->refresh(), $step, ['note' => 'اختبار']);
        }

        return $order->refresh();
    };

    $this->edit = fn (User $who, SalesOrder $order, int $quantity, array $extra = []) => $this->actingAs($who, 'sanctum')
        ->putJson("/api/v1/sales-orders/{$order->id}", array_merge([
            'items' => [['product_id' => $this->product->id, 'quantity' => $quantity, 'unit_price' => 100]],
        ], $extra));
});

test('editing a pending order rewrites it in place, without a version', function () {
    $order = ($this->orderAt)(SalesOrder::STATUS_PENDING, $this->rep);

    ($this->edit)($this->rep, $order, 5)->assertSuccessful()->assertJsonPath('revision', null);

    expect((int) $order->refresh()->revision)->toBe(1);
    expect(SalesOrderRevision::count())->toBe(0);
    expect((int) $order->items()->first()->quantity)->toBe(5);
});

test('a manager edit of a confirmed order keeps the previous version', function () {
    $order = ($this->orderAt)(SalesOrder::STATUS_CONFIRMED);

    ($this->edit)($this->manager, $order, 5, ['revision_note' => 'طلب العميل زيادة'])
        ->assertSuccessful()
        ->assertJsonPath('revision.previous', 1)
        ->assertJsonPath('revision.current', 2);

    $order->refresh();
    expect((int) $order->revision)->toBe(2);
    expect((float) $order->total)->toBe(500.0);
    expect((int) $order->items()->first()->quantity)->toBe(5);

    $kept = $order->revisions()->sole();
    expect($kept->revision)->toBe(1);
    expect($kept->status)->toBe(SalesOrder::STATUS_CONFIRMED);
    expect((float) $kept->total)->toBe(200.0);
    expect($kept->note)->toBe('طلب العميل زيادة');
    expect($kept->snapshot['items'][0]['quantity'])->toEqual(2);
    expect($kept->snapshot['items'][0]['product_name'])->toBe('خلاط مغسلة');
    expect((int) $kept->replaced_by)->toBe($this->manager->id);

    // The hold followed the new lines.
    $held = WarehouseInventory::where('product_id', $this->product->id)->value('reserved_quantity');
    expect((int) $held)->toBe(5);
});

test('each further edit adds one more version', function () {
    $order = ($this->orderAt)(SalesOrder::STATUS_CONFIRMED);

    ($this->edit)($this->manager, $order, 3)->assertSuccessful();
    ($this->edit)($this->manager, $order->refresh(), 4)->assertSuccessful();

    expect((int) $order->refresh()->revision)->toBe(3);
    expect($order->revisions()->pluck('revision')->all())->toBe([1, 2]);
    expect($order->revisions()->get()->map(fn ($r) => $r->snapshot['items'][0]['quantity'])->all())->toEqual([2, 3]);
});

test('a manager can edit a delivered order, and the shipped stock follows', function () {
    $order = ($this->orderAt)(SalesOrder::STATUS_DELIVERED);
    $before = (int) WarehouseInventory::where('product_id', $this->product->id)->value('quantity');

    ($this->edit)($this->manager, $order, 3)->assertSuccessful();

    expect((int) $order->refresh()->revision)->toBe(2);
    expect($order->status)->toBe(SalesOrder::STATUS_DELIVERED);
    $after = (int) WarehouseInventory::where('product_id', $this->product->id)->value('quantity');
    expect($before - $after)->toBe(1);
});

test('a manager can edit a cancelled order without holding stock again', function () {
    $order = ($this->orderAt)(SalesOrder::STATUS_CANCELLED);

    ($this->edit)($this->manager, $order, 7)->assertSuccessful();

    expect((int) $order->refresh()->revision)->toBe(2);
    expect((int) $order->items()->first()->quantity)->toBe(7);
    expect((int) WarehouseInventory::where('product_id', $this->product->id)->value('reserved_quantity'))->toBe(0);
});

test('a sales rep edit of a confirmed order is saved as a new version', function () {
    $order = ($this->orderAt)(SalesOrder::STATUS_CONFIRMED, $this->rep);

    ($this->edit)($this->rep, $order, 4)->assertSuccessful()->assertJsonPath('revision.current', 2);

    expect($order->refresh()->revisions()->count())->toBe(1);
    expect((int) $order->items()->first()->quantity)->toBe(4);
});

test('a sales rep cannot edit a delivered or cancelled order', function (string $stage) {
    $order = ($this->orderAt)($stage, $this->rep);

    ($this->edit)($this->rep, $order, 9)->assertStatus(422);

    expect((int) $order->refresh()->revision)->toBe(1);
    expect(SalesOrderRevision::count())->toBe(0);
    expect((int) $order->items()->first()->quantity)->toBe(2);
})->with([SalesOrder::STATUS_DELIVERED, SalesOrder::STATUS_CANCELLED]);

test('a failed amendment leaves no version behind', function () {
    $order = ($this->orderAt)(SalesOrder::STATUS_CONFIRMED);

    // More than the warehouse holds: the amendment is refused.
    ($this->edit)($this->manager, $order, 500)->assertStatus(422);

    expect((int) $order->refresh()->revision)->toBe(1);
    expect(SalesOrderRevision::count())->toBe(0);
});

test('the detail screen lists the versions', function () {
    $order = ($this->orderAt)(SalesOrder::STATUS_CONFIRMED);
    ($this->edit)($this->manager, $order, 3)->assertSuccessful();

    $this->actingAs($this->manager, 'sanctum')
        ->getJson("/api/v1/sales-orders/{$order->id}/detail")
        ->assertSuccessful()
        ->assertJsonPath('data.sales_order.revision', 2)
        ->assertJsonCount(1, 'data.revisions')
        ->assertJsonPath('data.revisions.0.revision', 1);
});
