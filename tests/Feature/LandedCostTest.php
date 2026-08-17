<?php

use App\Models\JournalEntryHeader;
use App\Models\LedgerAccount;
use App\Models\Product;
use App\Models\PurchaseReceipt;
use App\Models\Role;
use App\Models\Supplier;
use App\Models\User;
use App\Models\Warehouse;
use App\Services\Accounting\LedgerPostingService;
use App\Services\ErpUpgradeService;
use App\Services\Sales\GoodsIssueService;
use Illuminate\Support\Facades\DB;

/**
 * Freight, customs and insurance — part of what the goods cost.
 *
 * The allocation used to rewrite `unit_price` on the receipt's item rows and
 * stop there. Three records were left disagreeing and none of them said so:
 * the receipt no longer matched the journal entry posted from it, the cost
 * layers still held the original price so no sale ever carried the freight,
 * and the ledger never heard of the charge at all — inventory did not gain it,
 * no expense recorded it, and nobody was recorded as owed it.
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

    $this->supplier = Supplier::create(['name' => 'مورّد', 'status' => 'active', 'balance' => 0]);
    $this->carrier = Supplier::create(['name' => 'شركة الشحن', 'status' => 'active', 'balance' => 0]);

    $this->product = Product::create([
        'name_ar' => 'مضخة مياه',
        'sku' => 'SKU-PUMP',
        'price' => 100,
        'cost_price' => 40,
    ]);

    // Ten units received at 40: 400 of goods on the shelf, and the layer that
    // any sale will be costed against.
    $this->receive = fn (int $quantity = 10, float $price = 40) => $this->actingAs($this->admin)
        ->postJson('/api/v1/purchase-receipts', [
            'supplier_id' => $this->supplier->id,
            'warehouse_id' => $this->warehouse->id,
            'receipt_date' => now()->toDateString(),
            'items' => [
                ['product_id' => $this->product->id, 'quantity' => $quantity, 'unit_price' => $price],
            ],
        ])->assertCreated();

    $this->balanceOf = fn (string $role) => round(
        (float) LedgerAccount::where('posting_role', $role)->value('balance'),
        2
    );

    $this->inventoryBalance = fn () => round((float) LedgerAccount::find(
        app(LedgerPostingService::class)->inventoryAccountIdFor($this->warehouse->id)
    )->balance, 2);

    $this->layerValue = fn () => round((float) DB::table('inventory_cost_layers')
        ->selectRaw('SUM(remaining_quantity * unit_cost) v')->value('v'), 2);
});

test('freight on stock still held raises the value of the stock', function () {
    ($this->receive)();

    expect(($this->inventoryBalance)())->toBe(400.0);

    app(ErpUpgradeService::class)->allocateLandedCost(
        purchaseReceiptId: PurchaseReceipt::latest('id')->value('id'),
        shipping: 50, customs: 30, insurance: 0, other: 0,
        method: 'value', settlement: 'credit', supplierId: $this->carrier->id,
    );

    // 80 of charges on ten units still on the shelf.
    expect(($this->inventoryBalance)())->toBe(480.0);
    expect(($this->balanceOf)('accounts_payable'))->toBe(480.0);
    expect(round((float) $this->carrier->fresh()->balance, 2))->toBe(80.0);
});

test('the cost layers carry the charge, so the next sale costs what it really cost', function () {
    ($this->receive)();

    app(ErpUpgradeService::class)->allocateLandedCost(
        purchaseReceiptId: PurchaseReceipt::latest('id')->value('id'),
        shipping: 80, customs: 0, insurance: 0, other: 0,
        method: 'value', settlement: 'cash',
    );

    // This is the record a sale is actually costed from — the old version left
    // it untouched, so the freight reached no cost of sale ever.
    expect(($this->layerValue)())->toBe(480.0);

    app(GoodsIssueService::class)->issueAndPostCost(
        lines: [[
            'product_id' => $this->product->id,
            'quantity' => 5,
            'warehouse_id' => $this->warehouse->id,
            'movement_key' => 'sale:1',
        ]],
        postingKey: 'sale_cogs:1',
        label: 'بيع',
    );

    // Five units at 48, not at 40.
    expect(($this->balanceOf)('cogs'))->toBe(240.0);
    expect(($this->inventoryBalance)())->toBe(240.0);
});

test('the share belonging to units already sold goes to cost of sales', function () {
    ($this->receive)();

    // Four of the ten are gone before the freight bill arrives.
    app(GoodsIssueService::class)->issueAndPostCost(
        lines: [[
            'product_id' => $this->product->id,
            'quantity' => 4,
            'warehouse_id' => $this->warehouse->id,
            'movement_key' => 'sale:early',
        ]],
        postingKey: 'sale_cogs:early',
        label: 'بيع مبكر',
    );

    expect(($this->balanceOf)('cogs'))->toBe(160.0);

    app(ErpUpgradeService::class)->allocateLandedCost(
        purchaseReceiptId: PurchaseReceipt::latest('id')->value('id'),
        shipping: 100, customs: 0, insurance: 0, other: 0,
        method: 'value', settlement: 'cash',
    );

    // 10 per unit: six on the shelf, four already sold. The sold share belongs
    // to the period that sold them at a cost now known to be too low.
    expect(($this->balanceOf)('cogs'))->toBe(200.0);
    expect(($this->inventoryBalance)())->toBe(300.0);
});

test('the receipt keeps the price the supplier charged', function () {
    ($this->receive)();
    $receiptId = PurchaseReceipt::latest('id')->value('id');

    app(ErpUpgradeService::class)->allocateLandedCost(
        purchaseReceiptId: $receiptId,
        shipping: 90, customs: 0, insurance: 0, other: 0,
        method: 'value', settlement: 'cash',
    );

    // Rewriting this is what made the receipt disagree with the entry posted
    // from it when the goods arrived.
    $item = DB::table('purchase_receipt_items')->where('purchase_receipt_id', $receiptId)->first();

    expect(round((float) $item->unit_price, 2))->toBe(40.0);

    $receiptEntry = JournalEntryHeader::where('posting_key', 'goods_receipt:'.$receiptId)->first();
    expect(round((float) $receiptEntry->total_debit, 2))->toBe(400.0);
});

test('the entry balances however the charge splits', function () {
    ($this->receive)(7, 33.33);

    app(GoodsIssueService::class)->issueAndPostCost(
        lines: [[
            'product_id' => $this->product->id,
            'quantity' => 3,
            'warehouse_id' => $this->warehouse->id,
            'movement_key' => 'sale:odd',
        ]],
        postingKey: 'sale_cogs:odd',
        label: 'بيع',
    );

    $landed = app(ErpUpgradeService::class)->allocateLandedCost(
        purchaseReceiptId: PurchaseReceipt::latest('id')->value('id'),
        shipping: 77.77, customs: 11.11, insurance: 0, other: 0,
        method: 'quantity', settlement: 'bank',
    );

    $entry = JournalEntryHeader::where('posting_key', 'landed_cost:'.$landed->id)->first();

    expect(round((float) $entry->total_debit, 2))->toBe(88.88);
    expect(round((float) $entry->total_credit, 2))->toBe(88.88);
});

test('a charge on account must name who it is owed to', function () {
    ($this->receive)();

    expect(fn () => app(ErpUpgradeService::class)->allocateLandedCost(
        purchaseReceiptId: PurchaseReceipt::latest('id')->value('id'),
        shipping: 50, customs: 0, insurance: 0, other: 0,
        method: 'value', settlement: 'credit',
    ))->toThrow(RuntimeException::class);

    // Nothing was written: no payable without a party, which would have left
    // the aging report unable to reconcile.
    expect(($this->balanceOf)('accounts_payable'))->toBe(400.0);
});

test('the same allocation is not posted twice', function () {
    ($this->receive)();

    $landed = app(ErpUpgradeService::class)->allocateLandedCost(
        purchaseReceiptId: PurchaseReceipt::latest('id')->value('id'),
        shipping: 40, customs: 0, insurance: 0, other: 0,
        method: 'value', settlement: 'cash',
    );

    expect(JournalEntryHeader::where('posting_key', 'landed_cost:'.$landed->id)->count())->toBe(1);
});
