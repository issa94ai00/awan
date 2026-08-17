<?php

use App\Models\JournalEntryHeader;
use App\Models\LedgerAccount;
use App\Models\Product;
use App\Models\PurchaseReturn;
use App\Models\Role;
use App\Models\Supplier;
use App\Models\User;
use App\Models\Warehouse;
use App\Models\WarehouseInventory;
use App\Services\Accounting\LedgerPostingService;

/**
 * Sending goods back to the supplier.
 *
 * There was no document for this at all. The only way to record a return was a
 * stock adjustment, which books the goods out against the inventory-difference
 * account — so a faulty delivery sent back looked, in the income statement,
 * exactly like stock that had been lost, and the supplier stayed owed in full
 * for goods they had taken back.
 */
beforeEach(function () {
    $this->admin = User::factory()->create([
        'is_admin' => true,
        'role_id' => Role::firstOrCreate(['name' => 'admin'], ['display_name' => 'admin'])->id,
    ]);

    $this->warehouse = Warehouse::create([
        'name' => 'المستودع الرئيسي',
        'code' => 'WH-MAIN',
        'location' => 'الرياض',
        'status' => 'active',
        'is_active' => true,
        'is_primary' => true,
        'location_type' => Warehouse::TYPE_WAREHOUSE,
    ]);

    $this->supplier = Supplier::create(['name' => 'مورّد المضخات', 'status' => 'active', 'balance' => 0]);

    $this->product = Product::create([
        'name_ar' => 'مضخة مياه',
        'sku' => 'SKU-PUMP',
        'price' => 100,
        'cost_price' => 40,
    ]);

    // Ten units bought at 40 with 60 of tax: 400 of stock, 460 owed.
    $this->actingAs($this->admin)->postJson('/api/v1/purchase-receipts', [
        'supplier_id' => $this->supplier->id,
        'warehouse_id' => $this->warehouse->id,
        'receipt_date' => now()->toDateString(),
        'tax_amount' => 60,
        'items' => [
            ['product_id' => $this->product->id, 'quantity' => 10, 'unit_price' => 40],
        ],
    ])->assertCreated();

    $this->balanceOf = fn (string $role) => round(
        (float) LedgerAccount::where('posting_role', $role)->value('balance'),
        2
    );

    $this->inventoryBalance = fn () => round((float) LedgerAccount::find(
        app(LedgerPostingService::class)->inventoryAccountIdFor($this->warehouse->id)
    )->balance, 2);

    $this->returnGoods = fn (array $payload) => $this->actingAs($this->admin)
        ->postJson('/api/v1/admin/purchase-returns', array_merge([
            'supplier_id' => $this->supplier->id,
            'warehouse_id' => $this->warehouse->id,
            'return_date' => now()->toDateString(),
            'reason' => 'بضاعة معيبة',
        ], $payload));
});

test('returning goods takes them off the shelf and out of the books', function () {
    ($this->returnGoods)([
        'items' => [['product_id' => $this->product->id, 'quantity' => 3]],
    ])->assertCreated();

    // Three units at 40 leave: the shelf and the inventory account agree.
    expect((int) WarehouseInventory::where('product_id', $this->product->id)->value('quantity'))->toBe(7);
    expect(($this->inventoryBalance)())->toBe(280.0);
});

test('the supplier is owed less, in the books and on their record', function () {
    ($this->returnGoods)([
        'items' => [['product_id' => $this->product->id, 'quantity' => 5]],
        'tax_amount' => 30,
    ])->assertCreated();

    // 460 owed, less 200 of goods and 30 of tax given back.
    expect(($this->balanceOf)('accounts_payable'))->toBe(230.0);
    expect(round((float) $this->supplier->fresh()->balance, 2))->toBe(230.0);
    // And the tax on the returned portion is no longer reclaimable.
    expect(($this->balanceOf)('input_vat'))->toBe(30.0);
});

test('a return is not booked as shrinkage', function () {
    ($this->returnGoods)([
        'items' => [['product_id' => $this->product->id, 'quantity' => 4]],
    ])->assertCreated();

    // The account a stock adjustment would have used — which is what people
    // had to do before this document existed.
    expect(($this->balanceOf)('inventory_adjustment'))->toBe(0.0);
    expect(($this->balanceOf)('cogs'))->toBe(0.0);
});

test('a restocking fee shows up as a real cost, not a rounded figure', function () {
    // Goods cost 200; the supplier credits only 180.
    ($this->returnGoods)([
        'items' => [['product_id' => $this->product->id, 'quantity' => 5, 'unit_price' => 36]],
    ])->assertCreated();

    $return = PurchaseReturn::latest('id')->first();
    $entry = JournalEntryHeader::where('posting_key', $return->postingKey())->first();

    expect(round((float) $entry->total_debit, 2))->toBe(round((float) $entry->total_credit, 2));
    // The 20 difference between what it cost and what they credited.
    expect(($this->balanceOf)('other_expense'))->toBe(20.0);
    expect(($this->balanceOf)('accounts_payable'))->toBe(280.0);
});

test('returning more than is on the shelf is refused, and moves nothing', function () {
    ($this->returnGoods)([
        'items' => [['product_id' => $this->product->id, 'quantity' => 50]],
    ])->assertStatus(422);

    expect(PurchaseReturn::count())->toBe(0);
    expect((int) WarehouseInventory::where('product_id', $this->product->id)->value('quantity'))->toBe(10);
    expect(($this->inventoryBalance)())->toBe(400.0);
});

test('the cost that leaves is what the units cost, not what they sell for', function () {
    // A second delivery at a higher price: FIFO decides which units go back.
    $this->actingAs($this->admin)->postJson('/api/v1/purchase-receipts', [
        'supplier_id' => $this->supplier->id,
        'warehouse_id' => $this->warehouse->id,
        'receipt_date' => now()->toDateString(),
        'items' => [['product_id' => $this->product->id, 'quantity' => 5, 'unit_price' => 70]],
    ])->assertCreated();

    ($this->returnGoods)([
        'items' => [['product_id' => $this->product->id, 'quantity' => 12]],
    ])->assertCreated();

    $return = PurchaseReturn::with('items')->latest('id')->first();

    // Ten units at 40 and two at 70: 540, oldest first.
    expect($return->totalCost())->toBe(540.0);
});

test('a purchase return cannot be deleted once its goods are gone', function () {
    ($this->returnGoods)([
        'items' => [['product_id' => $this->product->id, 'quantity' => 2]],
    ])->assertCreated();

    $this->actingAs($this->admin)
        ->deleteJson('/api/v1/admin/purchase-returns/'.PurchaseReturn::latest('id')->value('id'))
        ->assertStatus(422);
});
