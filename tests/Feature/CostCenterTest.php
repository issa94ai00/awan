<?php

use App\Models\CostCenter;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\JournalEntryLine;
use App\Models\Product;
use App\Models\Role;
use App\Models\User;
use App\Models\Warehouse;
use App\Services\Accounting\LedgerPostingService;
use App\Services\Inventory\InventoryService;

/**
 * Which part of the business made the money.
 *
 * The income statement says whether the company was profitable; it cannot say
 * which branch was, and that is usually the question worth asking — a company
 * can be comfortably profitable while one location loses money every month,
 * and a combined statement never mentions it.
 *
 * The dimension is attributed from the warehouse a posting already knows
 * about. That is what decides whether a cost dimension survives daily use or
 * ends up blank on every document.
 */
beforeEach(function () {
    CostCenter::forgetWarehouseCache();

    $this->admin = User::factory()->create([
        'is_admin' => true,
        'role_id' => Role::firstOrCreate(['name' => 'admin'], ['display_name' => 'admin'])->id,
    ]);

    $this->makeWarehouse = function (string $name, string $code): Warehouse {
        $warehouse = Warehouse::create([
            'name' => $name,
            'code' => $code,
            'location' => 'سورية',
            'status' => 'active',
            'is_active' => true,
            'location_type' => Warehouse::TYPE_WAREHOUSE,
        ]);

        // The migration creates one per warehouse that existed when it ran;
        // anything opened since gets its own here, the same way the API does.
        CostCenter::create([
            'code' => 'CC-'.str_pad((string) $warehouse->id, 3, '0', STR_PAD_LEFT),
            'name' => $name,
            'warehouse_id' => $warehouse->id,
            'is_active' => true,
        ]);

        CostCenter::forgetWarehouseCache();

        return $warehouse;
    };

    $this->north = ($this->makeWarehouse)('فرع الشمال', 'WH-N');
    $this->south = ($this->makeWarehouse)('فرع الجنوب', 'WH-S');

    $this->product = Product::create([
        'name_ar' => 'مضخة',
        'sku' => 'SKU-1',
        'price' => 100,
        'cost_price' => 40,
    ]);

    $this->customer = Customer::create(['name' => 'عميل', 'email' => 'cc@example.test', 'status' => 'active']);
    $this->ledger = app(LedgerPostingService::class);

    $this->stock = fn (Warehouse $warehouse, int $qty, float $cost) => app(InventoryService::class)->receive(
        $this->product->id,
        $qty,
        $warehouse->id,
        ['key' => 'open:'.$warehouse->id.':'.uniqid(), 'unit_cost' => $cost]
    );

    $this->sellFrom = function (Warehouse $warehouse, float $total, string $date) {
        $invoice = Invoice::create([
            'invoice_number' => 'INV-'.uniqid(),
            'customer_id' => $this->customer->id,
            'warehouse_id' => $warehouse->id,
            'status' => 'sent',
            'subtotal' => $total,
            'tax' => 0,
            'total' => $total,
            'due_amount' => $total,
        ]);
        $invoice->forceFill(['created_at' => $date])->save();
        $this->ledger->postInvoice($invoice->refresh());

        return $invoice;
    };

    $this->statement = fn (array $params = []) => $this->actingAs($this->admin)
        ->getJson('/api/v1/admin/accounting/cost-center-statement?'.http_build_query($params))
        ->assertOk()->json('data');
});

test('a warehouse gets a cost centre without anybody creating one', function () {
    expect(CostCenter::forWarehouse($this->north->id))->not->toBeNull();
});

test('revenue is attributed to the branch that sold it', function () {
    ($this->sellFrom)($this->north, 1000, '2026-03-05');
    ($this->sellFrom)($this->south, 400, '2026-03-06');

    $data = ($this->statement)(['date_from' => '2026-03-01', 'date_to' => '2026-03-31']);

    $north = collect($data['centers'])->firstWhere('name', 'فرع الشمال');
    $south = collect($data['centers'])->firstWhere('name', 'فرع الجنوب');

    expect((float) $north['revenue'])->toBe(1000.0);
    expect((float) $south['revenue'])->toBe(400.0);
});

test('a sale filled from two branches costs each one its own share', function () {
    ($this->stock)($this->north, 10, 40);
    ($this->stock)($this->south, 10, 25);

    // Four units from the north and two from the south: the cost of sale is
    // split the same way the stock was, so each branch carries what it gave up.
    $this->ledger->postCostOfGoodsSoldBySource(
        key: 'so_cogs:1',
        costByWarehouse: [$this->north->id => 160, $this->south->id => 50],
        label: 'بيع مقسّم',
        date: '2026-03-10',
    );

    $data = ($this->statement)(['date_from' => '2026-03-01', 'date_to' => '2026-03-31']);

    $north = collect($data['centers'])->firstWhere('name', 'فرع الشمال');
    $south = collect($data['centers'])->firstWhere('name', 'فرع الجنوب');

    expect((float) $north['cost_of_sales'])->toBe(160.0);
    expect((float) $south['cost_of_sales'])->toBe(50.0);
});

test('the gross margin of a branch is revenue against its own cost of sale', function () {
    ($this->stock)($this->north, 10, 40);
    ($this->sellFrom)($this->north, 1000, '2026-03-05');

    $this->ledger->postCostOfGoodsSoldBySource(
        key: 'so_cogs:2',
        costByWarehouse: [$this->north->id => 600],
        label: 'بيع',
        date: '2026-03-05',
    );

    $north = collect(($this->statement)(['date_from' => '2026-03-01', 'date_to' => '2026-03-31'])['centers'])
        ->firstWhere('name', 'فرع الشمال');

    expect((float) $north['gross_profit'])->toBe(400.0);
    expect((float) $north['margin_percentage'])->toBe(40.0);
});

test('a stock loss is charged to the branch that counted it', function () {
    ($this->stock)($this->south, 10, 25);

    app(InventoryService::class)->adjust(
        $this->product->id,
        -4,
        $this->south->id,
        ['key' => 'count:south', 'reason' => 'جرد']
    );

    $data = ($this->statement)([
        'date_from' => now()->startOfYear()->toDateString(),
        'date_to' => now()->endOfYear()->toDateString(),
    ]);

    $south = collect($data['centers'])->firstWhere('name', 'فرع الجنوب');

    // 100 of shrinkage is the south's loss, not a company-wide one.
    expect((float) $south['operating_expenses'])->toBe(100.0);
});

test('a shared cost stays unattributed rather than being spread', function () {
    $this->ledger->postExpense((object) [
        'id' => 901,
        'amount' => 500,
        'status' => 'paid',
        'category' => 'other',
        'expense_date' => '2026-03-11',
        'expense_number' => 'EXP-SHARED',
        'description' => 'إيجار الإدارة',
        'currency' => 'SAR',
    ]);

    $data = ($this->statement)(['date_from' => '2026-03-01', 'date_to' => '2026-03-31']);
    $unattributed = collect($data['centers'])->firstWhere('id', null);

    // Apportioning it by turnover or headcount would invent a precision the
    // reader would take for a measurement.
    expect((float) $unattributed['operating_expenses'])->toBe(500.0);
    expect($unattributed['name'])->toBe('غير موزّع');
});

test('the report says how much of the period it could not attribute', function () {
    ($this->sellFrom)($this->north, 1000, '2026-03-05');

    $this->ledger->postExpense((object) [
        'id' => 902,
        'amount' => 1000,
        'status' => 'paid',
        'category' => 'other',
        'expense_date' => '2026-03-11',
        'expense_number' => 'EXP-SHARED-2',
        'description' => 'إيجار',
        'currency' => 'SAR',
    ]);

    $data = ($this->statement)(['date_from' => '2026-03-01', 'date_to' => '2026-03-31']);

    // Half the activity belongs to no branch, and a per-branch report that did
    // not say so would read as more complete than it is.
    expect((float) $data['unattributed_share'])->toBe(50.0);
});

test('the totals still add up to the company as a whole', function () {
    ($this->sellFrom)($this->north, 1000, '2026-03-05');
    ($this->sellFrom)($this->south, 500, '2026-03-07');

    $totals = ($this->statement)(['date_from' => '2026-03-01', 'date_to' => '2026-03-31'])['totals'];

    expect((float) $totals['revenue'])->toBe(1500.0);
});

test('entries carry the centre on the line, not on the entry', function () {
    ($this->sellFrom)($this->north, 300, '2026-03-05');

    $center = CostCenter::forWarehouse($this->north->id);

    // The dimension belongs to the line: one entry can touch two branches, and
    // a split sale is exactly that.
    expect(JournalEntryLine::where('cost_center_id', $center)->count())->toBeGreaterThan(0);
});

test('the statement is refused to a sales account', function () {
    $sales = User::factory()->create([
        'role_id' => Role::firstOrCreate(['name' => 'sells'], ['display_name' => 'sells'])->id,
    ]);

    $this->actingAs($sales)
        ->getJson('/api/v1/admin/accounting/cost-center-statement')
        ->assertForbidden();
});
