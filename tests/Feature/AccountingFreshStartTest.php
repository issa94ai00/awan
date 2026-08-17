<?php

use App\Models\Customer;
use App\Models\Invoice;
use App\Models\JournalEntryHeader;
use App\Models\LedgerAccount;
use App\Models\Product;
use App\Models\Supplier;
use App\Models\User;
use App\Models\Warehouse;
use App\Models\WarehouseInventory;
use App\Services\Accounting\LedgerPostingService;
use App\Services\Inventory\InventoryService;

/**
 * Going live: emptying books full of trial data, then opening properly.
 *
 * Two halves of one operation, and each is useless without the other. Wiping
 * the ledger while the warehouse stays full leaves stock on the shelf worth
 * nothing in the books, so the first sale posts a cost against an inventory
 * account holding zero and drives the asset negative. Posting opening balances
 * on top of trial entries carries the mess forward under a new name.
 *
 * What survives the reset matters as much as what does not: a business going
 * live keeps its catalogue, its warehouses and the stock it has counted.
 */
beforeEach(function () {
    $this->user = User::factory()->create(['is_admin' => true]);

    $this->warehouse = Warehouse::create([
        'name' => 'المستودع الرئيسي',
        'code' => 'WH-MAIN',
        'location' => 'الرياض',
        'status' => 'active',
        'is_active' => true,
        'is_primary' => true,
        'location_type' => Warehouse::TYPE_WAREHOUSE,
    ]);

    $this->product = Product::create([
        'name_ar' => 'مضخة مياه',
        'sku' => 'SKU-PUMP',
        'price' => 100,
        'cost_price' => 40,
    ]);

    // Stock on the shelf with a real cost behind it.
    app(InventoryService::class)->receive($this->product->id, 10, $this->warehouse->id, [
        'key' => 'opening-stock',
        'unit_cost' => 40,
    ]);

    // And a ledger full of trial data.
    $customer = Customer::create(['name' => 'عميل تجريبي', 'email' => 't@example.test', 'status' => 'active']);
    $customer->update(['balance' => 5000]);

    Supplier::create(['name' => 'مورد تجريبي', 'status' => 'active', 'balance' => 100099]);

    app(LedgerPostingService::class)->postInvoice(Invoice::create([
        'invoice_number' => 'INV-TRIAL',
        'customer_id' => $customer->id,
        'status' => 'sent',
        'subtotal' => 40,
        'tax' => 0,
        'total' => 40,
        'due_amount' => 40,
    ]));
});

test('the reset empties the books without touching the shelves', function () {
    expect(JournalEntryHeader::count())->toBeGreaterThan(0);

    $this->artisan('accounting:reset-books --force')->assertSuccessful();

    // Gone: entries, documents, and every balance they maintained.
    expect(JournalEntryHeader::count())->toBe(0);
    expect(Invoice::count())->toBe(0);
    expect(LedgerAccount::whereRaw('ABS(balance) > 0.005')->count())->toBe(0);
    expect(round((float) Supplier::where('name', 'مورد تجريبي')->value('balance'), 2))->toBe(0.0);
    expect(round((float) Customer::where('name', 'عميل تجريبي')->value('balance'), 2))->toBe(0.0);

    // Kept: the catalogue and the stock that was counted onto the shelf.
    expect(Product::count())->toBe(1);
    expect(Warehouse::count())->toBe(1);
    expect((int) WarehouseInventory::where('product_id', $this->product->id)->value('quantity'))->toBe(10);
});

test('the reset leaves a general customer and supplier to post against', function () {
    $this->artisan('accounting:reset-books --force')->assertSuccessful();

    expect(Customer::where('name', 'عميل عام')->exists())->toBeTrue();
    expect(Supplier::where('name', 'مورد عام')->exists())->toBeTrue();
});

test('the general parties are not duplicated by a second reset', function () {
    $this->artisan('accounting:reset-books --force')->assertSuccessful();
    $this->artisan('accounting:reset-books --force')->assertSuccessful();

    expect(Customer::where('name', 'عميل عام')->count())->toBe(1);
    expect(Supplier::where('name', 'مورد عام')->count())->toBe(1);
});

test('the opening entry values the stock at what it actually cost', function () {
    $this->artisan('accounting:reset-books --force')->assertSuccessful();
    $this->artisan('accounting:opening-balance --inventory')->assertSuccessful();

    $inventoryId = app(LedgerPostingService::class)->inventoryAccountIdFor($this->warehouse->id);

    // Ten units bought at 40: the figure comes from the FIFO layers, not from
    // whatever the product record says its cost is today.
    expect(round((float) LedgerAccount::find($inventoryId)->balance, 2))->toBe(400.0);
    expect(round((float) LedgerAccount::where('posting_role', 'capital')->value('balance'), 2))->toBe(400.0);
});

test('cash and bank can be opened separately, and later', function () {
    $this->artisan('accounting:reset-books --force')->assertSuccessful();
    $this->artisan('accounting:opening-balance --inventory')->assertSuccessful();

    // Counted the till a day later: its own key, so the inventory entry is
    // untouched and nothing is posted twice.
    $this->artisan('accounting:opening-balance --cash=1500 --bank=25000')->assertSuccessful();

    expect(round((float) LedgerAccount::where('posting_role', 'cash')->value('balance'), 2))->toBe(1500.0);
    expect(round((float) LedgerAccount::where('posting_role', 'bank')->value('balance'), 2))->toBe(25000.0);
    expect(round((float) LedgerAccount::where('posting_role', 'capital')->value('balance'), 2))->toBe(26900.0);
});

test('running the opening twice does not double the capital', function () {
    $this->artisan('accounting:reset-books --force')->assertSuccessful();
    $this->artisan('accounting:opening-balance --inventory --cash=1000')->assertSuccessful();
    $this->artisan('accounting:opening-balance --inventory --cash=1000')->assertSuccessful();

    expect(round((float) LedgerAccount::where('posting_role', 'capital')->value('balance'), 2))->toBe(1400.0);
    expect(JournalEntryHeader::where('source_module', 'opening')->count())->toBe(2);
});

test('a dry run reports without writing anything', function () {
    $this->artisan('accounting:reset-books --force')->assertSuccessful();
    $this->artisan('accounting:opening-balance --inventory --cash=999 --dry-run')->assertSuccessful();

    expect(JournalEntryHeader::count())->toBe(0);
});

test('the opening balance sheet balances', function () {
    $this->artisan('accounting:reset-books --force')->assertSuccessful();
    $this->artisan('accounting:opening-balance --inventory --cash=1500 --bank=25000')->assertSuccessful();

    $data = $this->actingAs($this->user)
        ->getJson('/api/v1/admin/accounting/balance-sheet')
        ->assertOk()
        ->json('data');

    // The whole point of the opening entry: assets equal what the owner put in.
    expect($data['is_balanced'])->toBeTrue();
    expect((float) $data['totals']['assets'])->toBe(26900.0);
    expect((float) $data['equity']['total'])->toBe(26900.0);
});

test('selling after the opening leaves the inventory account describing the shelf', function () {
    $this->artisan('accounting:reset-books --force')->assertSuccessful();
    $this->artisan('accounting:opening-balance --inventory')->assertSuccessful();

    $ledger = app(LedgerPostingService::class);
    $inventoryId = $ledger->inventoryAccountIdFor($this->warehouse->id);

    // Four of the ten units go out the door.
    app(App\Services\Sales\GoodsIssueService::class)->issueAndPostCost(
        lines: [[
            'product_id' => $this->product->id,
            'quantity' => 4,
            'warehouse_id' => $this->warehouse->id,
            'movement_key' => 'sale:1',
        ]],
        postingKey: 'sale_cogs:1',
        label: 'بيع تجريبي',
    );

    // 400 opened, 160 sold: six units at 40 left, in the books and on the shelf.
    expect(round((float) LedgerAccount::find($inventoryId)->balance, 2))->toBe(240.0);
    expect((int) WarehouseInventory::where('product_id', $this->product->id)->value('quantity'))->toBe(6);
});
