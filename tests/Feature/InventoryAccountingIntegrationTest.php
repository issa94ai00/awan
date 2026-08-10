<?php

use App\Models\Customer;
use App\Models\JournalEntryHeader;
use App\Models\LedgerAccount;
use App\Models\Product;
use App\Models\ProductWarehouseAssignment;
use App\Models\PurchaseReceipt;
use App\Models\SalesOrder;
use App\Models\User;
use App\Models\Warehouse;
use App\Models\WarehouseInventory;
use App\Services\Accounting\LedgerPostingService;
use App\Services\Sales\SalesOrderWorkflowService;

/**
 * Where the warehouse and the ledger have to agree.
 *
 * Stock has always been tracked per warehouse — a receipt names the building it
 * arrived at, a sale names the ones it left. These tests hold the general ledger
 * to the same standard, and cover two defects that broke that agreement:
 *
 *  - buying credited the supplier but debited a *pooled* inventory account while
 *    selling credited the per-warehouse ones, so a warehouse's balance could
 *    only ever fall;
 *  - queries filtering on `available_stock` — an accessor, not a column — threw
 *    "Unknown column" instead of answering which warehouse could cover a line.
 */
beforeEach(function () {
    $this->user = User::factory()->create();

    $this->main = Warehouse::create([
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

    // The migration that seeds these walked the warehouses that existed when it
    // ran, so a warehouse created afterwards — as every warehouse in these
    // tests is — has none. Resolving it is what opens it.
    $this->accountFor = fn (Warehouse $warehouse): LedgerAccount => LedgerAccount::findOrFail(
        app(LedgerPostingService::class)->inventoryAccountIdFor($warehouse->id)
    );

    $this->stock = function (Warehouse $warehouse, int $quantity) {
        WarehouseInventory::create([
            'warehouse_id' => $warehouse->id,
            'product_id' => $this->product->id,
            'quantity' => $quantity,
            'available_quantity' => $quantity,
            'reserved_quantity' => 0,
            'damaged_quantity' => 0,
            'quarantined_quantity' => 0,
        ]);
    };

    $this->receipt = function (?Warehouse $warehouse, int $quantity, float $unitPrice): PurchaseReceipt {
        $receipt = PurchaseReceipt::create([
            'receipt_number' => 'PR-' . uniqid(),
            'warehouse_id' => $warehouse?->id,
            'receipt_date' => now(),
            'status' => 'received',
        ]);

        $receipt->items()->create([
            'product_id' => $this->product->id,
            'quantity' => $quantity,
            'unit_price' => $unitPrice,
            'total' => $quantity * $unitPrice,
        ]);

        return $receipt->load('items');
    };
});

/* -------------------------------------------------------------------- *
 * Goods receipt reaches the warehouse's own inventory account
 * -------------------------------------------------------------------- */

test('a receipt debits the inventory account of the warehouse that took the goods', function () {
    $account = ($this->accountFor)($this->main);

    $entry = app(LedgerPostingService::class)
        ->postGoodsReceipt(($this->receipt)($this->main, 5, 40));

    $debit = $entry->lines->firstWhere('debit', '>', 0);

    expect((int) $debit->account_id)->toBe($account->id);
    expect(round((float) $debit->debit, 2))->toBe(200.0);
});

test('a warehouse opened after the chart was seeded still gets its own account', function () {
    $branch = Warehouse::create([
        'name' => 'فرع جديد',
        'code' => 'WH-NEW',
        'location' => 'الدمام',
        'status' => 'active',
        'is_active' => true,
        'location_type' => Warehouse::TYPE_BRANCH,
    ]);

    $ledger = app(LedgerPostingService::class);
    $pooled = (int) LedgerAccount::where('posting_role', 'inventory')->value('id');

    $accountId = $ledger->inventoryAccountIdFor($branch->id);
    $account = LedgerAccount::find($accountId);

    expect($accountId)->not->toBe($pooled);
    expect($account->code)->toBe('1005-' . $branch->id);
    expect((int) $account->warehouse_id)->toBe($branch->id);

    // Resolving again must reuse it rather than open a second one.
    expect($ledger->inventoryAccountIdFor($branch->id))->toBe($accountId);
    expect(LedgerAccount::where('code', '1005-' . $branch->id)->count())->toBe(1);
});

test('a warehouse that does not exist falls back to the shared account', function () {
    $pooled = (int) LedgerAccount::where('posting_role', 'inventory')->value('id');

    expect(app(LedgerPostingService::class)->inventoryAccountIdFor(999999))->toBe($pooled);
});

test('a receipt with no warehouse still posts, to the shared inventory account', function () {
    ($this->accountFor)($this->main);

    $entry = app(LedgerPostingService::class)
        ->postGoodsReceipt(($this->receipt)(null, 5, 40));

    $debit = $entry->lines->firstWhere('debit', '>', 0);
    $pooled = LedgerAccount::where('posting_role', 'inventory')->value('id');

    expect((int) $debit->account_id)->toBe((int) $pooled);
});

test("buying and then selling the same goods leaves the warehouse's inventory account flat", function () {
    $account = ($this->accountFor)($this->main);
    $ledger = app(LedgerPostingService::class);

    // Ten units in at 40 — the asset rises by 400.
    $ledger->postGoodsReceipt(($this->receipt)($this->main, 10, 40));

    // The same ten units sold and shipped out of that warehouse.
    ($this->stock)($this->main, 10);
    $this->actingAs($this->user);

    $customer = Customer::create([
        'name' => 'عميل',
        'email' => 'c@example.com',
        'phone' => '+966500000002',
        'status' => 'نشط',
    ]);

    $order = SalesOrder::create([
        'order_number' => 'SO-FLAT-1',
        'customer_id' => $customer->id,
        'status' => SalesOrder::STATUS_PENDING,
        'order_date' => now()->toDateString(),
        'subtotal' => 1000,
        'total' => 1000,
        'currency' => 'SAR',
        'fulfillment_warehouse_id' => $this->main->id,
        'created_by' => $this->user->id,
    ]);

    $item = $order->items()->create([
        'product_id' => $this->product->id,
        'quantity' => 10,
        'unit_price' => 100,
    ]);
    $item->allocations()->create(['warehouse_id' => $this->main->id, 'quantity' => 10]);

    $workflow = app(SalesOrderWorkflowService::class);
    $workflow->transitionTo($order->refresh(), SalesOrder::STATUS_CONFIRMED);
    $workflow->transitionTo($order->refresh(), SalesOrder::STATUS_PROCESSING);
    $workflow->transitionTo($order->refresh(), SalesOrder::STATUS_SHIPPED);

    // Everything bought has been sold, so the holding is worth nothing — and
    // both sides of that landed on the same account. Before the fix the debit
    // went to the pooled account and this balance read -400.
    $debits = (float) $account->journalEntryLines()->sum('debit');
    $credits = (float) $account->journalEntryLines()->sum('credit');

    expect(round($debits, 2))->toBe(400.0);
    expect(round($debits - $credits, 2))->toBe(0.0);
});

/* -------------------------------------------------------------------- *
 * Filtering on available stock
 * -------------------------------------------------------------------- */

test('assignments can be filtered by available stock without a SQL error', function () {
    ($this->stock)($this->main, 7);

    $assignment = ProductWarehouseAssignment::create([
        'product_id' => $this->product->id,
        'warehouse_id' => $this->main->id,
        'is_active' => true,
        'effective_date' => now()->subDay(),
    ]);

    // The scope has to agree with the accessor it stands in for, at the
    // boundary in both directions.
    expect($assignment->available_stock)->toBe(7);
    expect(ProductWarehouseAssignment::whereAvailableStock('>=', 7)->count())->toBe(1);
    expect(ProductWarehouseAssignment::whereAvailableStock('>=', 8)->count())->toBe(0);
    expect(ProductWarehouseAssignment::whereAvailableStock('>', 6)->count())->toBe(1);
});

test('an assignment whose warehouse holds nothing is excluded rather than erroring', function () {
    ProductWarehouseAssignment::create([
        'product_id' => $this->product->id,
        'warehouse_id' => $this->main->id,
        'is_active' => true,
        'effective_date' => now()->subDay(),
    ]);

    // No warehouse_inventory row at all: the subquery must read zero, not drop
    // the row through a join or fail.
    expect(ProductWarehouseAssignment::whereAvailableStock('>=', 1)->count())->toBe(0);
    expect(ProductWarehouseAssignment::whereAvailableStock('=', 0)->count())->toBe(1);
});

test('the comparison operator cannot be smuggled into the query', function () {
    ProductWarehouseAssignment::query()->whereAvailableStock('; DROP TABLE products --', 1)->count();
})->throws(InvalidArgumentException::class);
