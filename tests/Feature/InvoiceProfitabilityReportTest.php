<?php

use App\Models\Customer;
use App\Models\Employee;
use App\Models\Invoice;
use App\Models\Product;
use App\Models\User;
use App\Models\Warehouse;

/**
 * Profit per invoice in the sales report.
 *
 * The report carried profit in aggregate, and per product, but never for the
 * document in between — so "which sale actually made money" could only be
 * answered by rebuilding it in a spreadsheet, and an invoice sold below cost
 * was invisible.
 */
beforeEach(function () {
    $this->admin = User::factory()->create();

    $this->warehouse = Warehouse::create([
        'name' => 'Main Warehouse',
        'code' => 'WH-PROFIT',
        'is_active' => true,
    ]);

    $this->customer = Customer::create(['name' => 'زبون التقارير', 'status' => 'active']);

    // 100 sold, 60 cost.
    $this->costed = Product::create([
        'name_ar' => 'خلاط',
        'sku' => 'SKU-MIXER',
        'price' => 100,
        'cost_price' => 60,
    ]);

    // Never given a cost — 1038 of 1805 products currently have none.
    $this->uncosted = Product::create([
        'name_ar' => 'وصلة',
        'sku' => 'SKU-JOINT',
        'price' => 40,
        'cost_price' => null,
    ]);

    $this->makeInvoice = function (string $number, array $lines, float $tax = 0) {
        $subtotal = array_sum(array_map(fn ($l) => $l['unit_price'] * $l['quantity'], $lines));

        $invoice = Invoice::create([
            'invoice_number' => $number,
            'customer_id' => $this->customer->id,
            'warehouse_id' => $this->warehouse->id,
            'subtotal' => $subtotal,
            'tax' => $tax,
            'discount' => 0,
            'total' => $subtotal + $tax,
            'status' => 'confirmed',
        ]);

        foreach ($lines as $line) {
            $item = $invoice->items()->create([
                'product_id' => $line['product']->id,
                'product_name' => $line['product']->name_ar,
                'quantity' => $line['quantity'],
                'unit_price' => $line['unit_price'],
                'total_price' => $line['unit_price'] * $line['quantity'],
            ]);

            // What the goods issue would have stamped on the line. Absent
            // means the sale never costed it, which is a different claim from
            // costing it at zero.
            if (array_key_exists('cost', $line)) {
                $item->forceFill([
                    'total_cost' => $line['cost'],
                    'unit_cost' => $line['cost'] / $line['quantity'],
                ])->save();
            }
        }

        return $invoice;
    };

    $this->report = fn (string $query = '') => $this->actingAs($this->admin, 'sanctum')
        ->getJson('/api/v1/admin/reports/invoices'.$query)
        ->assertOk();
});

it('reports cost, profit and margin for each invoice', function () {
    // Two units at 100, costing 60 each: 200 revenue, 120 cost, 80 profit.
    ($this->makeInvoice)('INV-P1', [['product' => $this->costed, 'quantity' => 2, 'unit_price' => 100]]);

    $row = ($this->report)()->json('data.invoices.0');

    expect((float) $row['total_cost'])->toBe(120.0)
        ->and((float) $row['gross_profit'])->toBe(80.0)
        ->and((float) $row['gross_margin'])->toBe(40.0)
        ->and((int) $row['line_count'])->toBe(1)
        ->and((int) $row['uncosted_lines'])->toBe(0);
});

it('measures profit against revenue net of tax', function () {
    // Tax is collected for the authority and owed straight back, so it is not
    // revenue: counting it credited the sale with profit it never made.
    ($this->makeInvoice)('INV-TAX', [['product' => $this->costed, 'quantity' => 1, 'unit_price' => 100]], tax: 15);

    $row = ($this->report)()->json('data.invoices.0');

    expect((float) $row['total'])->toBe(115.0)
        ->and((float) $row['net_revenue'])->toBe(100.0)
        ->and((float) $row['gross_profit'])->toBe(40.0)
        ->and((float) $row['gross_margin'])->toBe(40.0);
});

it('flags an invoice whose margin rests on products with no cost on file', function () {
    ($this->makeInvoice)('INV-UNCOSTED', [
        ['product' => $this->costed, 'quantity' => 1, 'unit_price' => 100],
        ['product' => $this->uncosted, 'quantity' => 1, 'unit_price' => 40],
    ]);

    $row = ($this->report)()->json('data.invoices.0');

    // 140 billed, only the mixer's 60 known — an 57% margin that is really
    // "we do not know", which is what the flag is for.
    expect((float) $row['total_cost'])->toBe(60.0)
        ->and((int) $row['uncosted_lines'])->toBe(1)
        ->and((int) $row['line_count'])->toBe(2);
});

it('sorts by profit and by margin so the loss-makers surface first', function () {
    // Sold below cost: 40 in, 60 out.
    ($this->makeInvoice)('INV-LOSS', [['product' => $this->costed, 'quantity' => 1, 'unit_price' => 40]]);
    // Small absolute profit, wide margin.
    ($this->makeInvoice)('INV-THIN', [['product' => $this->uncosted, 'quantity' => 1, 'unit_price' => 10]]);
    // Large absolute profit, ordinary margin.
    ($this->makeInvoice)('INV-FAT', [['product' => $this->costed, 'quantity' => 10, 'unit_price' => 100]]);

    $numbers = fn (string $sort) => array_column(
        ($this->report)('?sort='.$sort)->json('data.invoices'),
        'invoice_number'
    );

    expect($numbers('profit_asc')[0])->toBe('INV-LOSS')
        ->and($numbers('profit_desc')[0])->toBe('INV-FAT')
        // Margin ranks differently from profit: the thin invoice is 100% margin
        // on ten units of money, the fat one 40% on a thousand.
        ->and($numbers('margin_desc')[0])->toBe('INV-THIN')
        ->and($numbers('margin_asc')[0])->toBe('INV-LOSS');
});

it('reconciles the page rows against the totals for the whole filtered set', function () {
    ($this->makeInvoice)('INV-A', [['product' => $this->costed, 'quantity' => 1, 'unit_price' => 100]]);
    ($this->makeInvoice)('INV-B', [['product' => $this->costed, 'quantity' => 3, 'unit_price' => 100]]);

    // One row on the page; the summary still describes both invoices — a
    // profit line that only added up the page would contradict the count
    // printed beside it.
    $response = ($this->report)('?per_page=1');

    expect($response->json('data.invoices'))->toHaveCount(1);
    expect((float) $response->json('data.summary.total_cost'))->toBe(240.0)
        ->and((float) $response->json('data.summary.gross_profit'))->toBe(160.0)
        ->and((float) $response->json('data.summary.gross_margin'))->toBe(40.0);
});

it('keeps the summary intact when the rows are sorted by profit', function () {
    // Sorting joins the line costs in; a join that leaked into the summary
    // would multiply each invoice's total by its line count.
    ($this->makeInvoice)('INV-ONE-LINE', [['product' => $this->costed, 'quantity' => 1, 'unit_price' => 100]]);
    ($this->makeInvoice)('INV-THREE-LINES', [
        ['product' => $this->costed, 'quantity' => 1, 'unit_price' => 100],
        ['product' => $this->costed, 'quantity' => 1, 'unit_price' => 100],
        ['product' => $this->uncosted, 'quantity' => 1, 'unit_price' => 40],
    ]);

    $sorted = ($this->report)('?sort=profit_desc');

    expect((float) $sorted->json('data.summary.total_invoiced'))->toBe(340.0)
        ->and($sorted->json('data.summary.total_invoices'))->toBe(2)
        ->and($sorted->json('data.pagination.total'))->toBe(2);
});

it('exports the profit columns, honouring the employee filter it used to drop', function () {
    $employee = Employee::create([
        'first_name' => 'Ali',
        'last_name' => 'Saleh',
        'email' => 'ali@example.com',
        'warehouse_id' => $this->warehouse->id,
        'status' => 'active',
    ]);

    ($this->makeInvoice)('INV-MINE', [['product' => $this->costed, 'quantity' => 1, 'unit_price' => 100]])
        ->update(['assigned_employee_id' => $employee->id]);
    ($this->makeInvoice)('INV-THEIRS', [['product' => $this->costed, 'quantity' => 1, 'unit_price' => 100]]);

    $csv = $this->actingAs($this->admin, 'sanctum')
        ->get('/api/v1/admin/reports/invoices/export?employee_id='.$employee->id)
        ->assertOk()
        ->assertHeader('content-type', 'text/csv; charset=UTF-8')
        ->getContent();

    expect($csv)->toContain('gross_profit')
        ->and($csv)->toContain('INV-MINE')
        // employee_id was validated and then never applied, so the export
        // handed back the whole team while the screen showed one rep.
        ->and($csv)->not->toContain('INV-THEIRS');
});

it('costs a sale by what the goods actually cost, not by the catalogue today', function () {
    // Bought at 40, sold at 100, and the line says so.
    ($this->makeInvoice)('INV-FIFO', [
        ['product' => $this->costed, 'quantity' => 2, 'unit_price' => 100, 'cost' => 80],
    ]);

    $row = ($this->report)()->json('data.invoices.0');

    // The product's catalogue cost is 60 — using it would have reported 120.
    expect((float) $row['total_cost'])->toBe(80.0)
        ->and((float) $row['gross_profit'])->toBe(120.0)
        ->and((int) $row['estimated_lines'])->toBe(0);
});

it('does not let a later price change rewrite the profit of a closed sale', function () {
    ($this->makeInvoice)('INV-CLOSED', [
        ['product' => $this->costed, 'quantity' => 1, 'unit_price' => 100, 'cost' => 60],
    ]);

    // The supplier raises their price. Costing off the catalogue would turn a
    // sale that made 40 into one that lost 20 — retroactively, and silently.
    $this->costed->update(['cost_price' => 120]);

    $row = ($this->report)()->json('data.invoices.0');

    expect((float) $row['total_cost'])->toBe(60.0)
        ->and((float) $row['gross_profit'])->toBe(40.0);
});

it('says how much of a cost figure it had to estimate', function () {
    ($this->makeInvoice)('INV-MIXED', [
        // Costed at the till.
        ['product' => $this->costed, 'quantity' => 1, 'unit_price' => 100, 'cost' => 55],
        // Never costed: valued at the catalogue's 60.
        ['product' => $this->costed, 'quantity' => 1, 'unit_price' => 100],
        // Never costed and no catalogue price either — counted as free.
        ['product' => $this->uncosted, 'quantity' => 1, 'unit_price' => 40],
    ]);

    $row = ($this->report)()->json('data.invoices.0');

    expect((float) $row['total_cost'])->toBe(115.0)
        ->and((int) $row['line_count'])->toBe(3)
        ->and((int) $row['estimated_lines'])->toBe(2)
        // Uncosted is the subset of estimated that could not even be valued.
        ->and((int) $row['uncosted_lines'])->toBe(1);
});

it('qualifies the summary with how much of it was measured', function () {
    ($this->makeInvoice)('INV-REAL', [
        ['product' => $this->costed, 'quantity' => 1, 'unit_price' => 100, 'cost' => 70],
    ]);
    ($this->makeInvoice)('INV-GUESSED', [
        ['product' => $this->costed, 'quantity' => 1, 'unit_price' => 100],
    ]);

    $summary = ($this->report)()->json('data.summary');

    expect((float) $summary['total_cost'])->toBe(130.0)
        ->and((float) $summary['gross_profit'])->toBe(70.0)
        ->and((int) $summary['line_count'])->toBe(2)
        ->and((int) $summary['estimated_lines'])->toBe(1)
        ->and((int) $summary['uncosted_lines'])->toBe(0);
});
