<?php

use App\Models\Customer;
use App\Models\Product;
use App\Models\SalesOrder;
use App\Models\User;
use App\Models\Warehouse;

/**
 * Profit per sales order in the report.
 *
 * The order tab knew what the pipeline was worth and what each product earned,
 * but nothing about the order in between — the same gap the invoice tab had.
 *
 * Cost is the harder half, and it differs from an invoice in one way that
 * matters: an order is a commitment, and nothing has cost anything until it
 * ships. So the report reads a shipped line's recorded cost as a measurement
 * and an unshipped one as a projection at catalogue price, and says which is
 * which rather than presenting a forecast as a fact.
 */
beforeEach(function () {
    $this->admin = User::factory()->create();

    $this->warehouse = Warehouse::create([
        'name' => 'Main Warehouse',
        'code' => 'WH-SO-PROFIT',
        'is_active' => true,
    ]);

    $this->customer = Customer::create(['name' => 'زبون الطلبات', 'status' => 'active']);

    // 100 sold, 60 on the catalogue.
    $this->costed = Product::create([
        'name_ar' => 'خلاط',
        'sku' => 'SKU-SO-MIXER',
        'price' => 100,
        'cost_price' => 60,
    ]);

    // Never given a cost.
    $this->uncosted = Product::create([
        'name_ar' => 'وصلة',
        'sku' => 'SKU-SO-JOINT',
        'price' => 40,
        'cost_price' => null,
    ]);

    $this->makeOrder = function (string $number, array $lines, float $tax = 0) {
        $subtotal = array_sum(array_map(fn ($l) => $l['unit_price'] * $l['quantity'], $lines));

        $order = SalesOrder::create([
            'order_number' => $number,
            'customer_id' => $this->customer->id,
            'fulfillment_warehouse_id' => $this->warehouse->id,
            'order_date' => now(),
            'subtotal' => $subtotal,
            'tax' => $tax,
            'discount' => 0,
            'total' => $subtotal + $tax,
            'status' => SalesOrder::STATUS_CONFIRMED,
            'currency' => 'SYP',
        ]);

        foreach ($lines as $line) {
            $item = $order->items()->create([
                'product_id' => $line['product']->id,
                'quantity' => $line['quantity'],
                'unit_price' => $line['unit_price'],
                'discount' => 0,
                'tax' => 0,
                'total' => $line['unit_price'] * $line['quantity'],
            ]);

            // What the shipment would have stamped on the line. Absent means
            // the goods have not left, which is not the same as costing zero.
            if (array_key_exists('cost', $line)) {
                $item->forceFill([
                    'total_cost' => $line['cost'],
                    'unit_cost' => $line['cost'] / $line['quantity'],
                ])->save();
            }
        }

        return $order;
    };

    $this->report = fn (string $query = '') => $this->actingAs($this->admin, 'sanctum')
        ->getJson('/api/v1/admin/reports/sales'.$query)
        ->assertOk();
});

it('reports cost, profit and margin for each order', function () {
    ($this->makeOrder)('SO-P1', [
        ['product' => $this->costed, 'quantity' => 2, 'unit_price' => 100, 'cost' => 110],
    ]);

    $row = ($this->report)()->json('data.sales_orders.0');

    expect((float) $row['total_cost'])->toBe(110.0)
        ->and((float) $row['gross_profit'])->toBe(90.0)
        ->and((float) $row['gross_margin'])->toBe(45.0)
        ->and((int) $row['line_count'])->toBe(1)
        ->and((int) $row['estimated_lines'])->toBe(0);
});

it('measures profit against revenue net of tax', function () {
    // Tax is collected for the authority and owed straight back, so it is not
    // revenue: counting it credited the order with profit it never made.
    ($this->makeOrder)('SO-TAX', [
        ['product' => $this->costed, 'quantity' => 1, 'unit_price' => 100, 'cost' => 60],
    ], tax: 15);

    $row = ($this->report)()->json('data.sales_orders.0');

    expect((float) $row['total'])->toBe(115.0)
        ->and((float) $row['net_revenue'])->toBe(100.0)
        ->and((float) $row['gross_profit'])->toBe(40.0)
        ->and((float) $row['gross_margin'])->toBe(40.0);
});

it('projects an unshipped order at catalogue cost and says that is what it did', function () {
    ($this->makeOrder)('SO-PENDING', [
        ['product' => $this->costed, 'quantity' => 1, 'unit_price' => 100],
        ['product' => $this->uncosted, 'quantity' => 1, 'unit_price' => 40],
    ]);

    $row = ($this->report)()->json('data.sales_orders.0');

    // Only the mixer's catalogue 60 is known; the joint is counted as free.
    expect((float) $row['total_cost'])->toBe(60.0)
        ->and((int) $row['line_count'])->toBe(2)
        // Neither line has shipped, so the whole figure is a forecast.
        ->and((int) $row['estimated_lines'])->toBe(2)
        // And one of the two could not even be forecast.
        ->and((int) $row['uncosted_lines'])->toBe(1);
});

it('does not let a later price change rewrite the margin of a shipped order', function () {
    ($this->makeOrder)('SO-SHIPPED', [
        ['product' => $this->costed, 'quantity' => 1, 'unit_price' => 100, 'cost' => 60],
    ]);

    // The supplier raises their price. Costing off the catalogue would turn an
    // order that made 40 into one that lost 20, retroactively and silently.
    $this->costed->update(['cost_price' => 120]);

    $row = ($this->report)()->json('data.sales_orders.0');

    expect((float) $row['total_cost'])->toBe(60.0)
        ->and((float) $row['gross_profit'])->toBe(40.0);
});

it('sorts by profit and by margin so the loss-makers surface first', function () {
    // Shipped at 60, sold for 40.
    ($this->makeOrder)('SO-LOSS', [
        ['product' => $this->costed, 'quantity' => 1, 'unit_price' => 40, 'cost' => 60],
    ]);
    // Small absolute profit, wide margin — nothing costed against it.
    ($this->makeOrder)('SO-THIN', [
        ['product' => $this->uncosted, 'quantity' => 1, 'unit_price' => 10],
    ]);
    // Large absolute profit, ordinary margin.
    ($this->makeOrder)('SO-FAT', [
        ['product' => $this->costed, 'quantity' => 10, 'unit_price' => 100, 'cost' => 600],
    ]);

    $numbers = fn (string $sort) => array_column(
        ($this->report)('?sort='.$sort)->json('data.sales_orders'),
        'order_number'
    );

    expect($numbers('profit_asc')[0])->toBe('SO-LOSS')
        ->and($numbers('profit_desc')[0])->toBe('SO-FAT')
        // Margin ranks differently from profit: the thin order is 100% margin
        // on ten units of money, the fat one 40% on a thousand.
        ->and($numbers('margin_desc')[0])->toBe('SO-THIN')
        ->and($numbers('margin_asc')[0])->toBe('SO-LOSS');
});

it('reconciles the page rows against the totals for the whole filtered set', function () {
    ($this->makeOrder)('SO-A', [['product' => $this->costed, 'quantity' => 1, 'unit_price' => 100, 'cost' => 50]]);
    ($this->makeOrder)('SO-B', [['product' => $this->costed, 'quantity' => 3, 'unit_price' => 100, 'cost' => 150]]);

    // One row on the page; the summary still describes both orders — a profit
    // line that only added up the page would contradict the count beside it.
    $response = ($this->report)('?per_page=1');

    expect($response->json('data.sales_orders'))->toHaveCount(1);
    expect((float) $response->json('data.summary.total_cost'))->toBe(200.0)
        ->and((float) $response->json('data.summary.gross_profit'))->toBe(200.0)
        ->and((float) $response->json('data.summary.gross_margin'))->toBe(50.0)
        ->and((int) $response->json('data.summary.estimated_lines'))->toBe(0);
});

it('keeps the summary intact when the rows are sorted by profit', function () {
    // Sorting joins the line costs in; a join that leaked into the summary
    // would multiply each order's total by its line count.
    ($this->makeOrder)('SO-ONE-LINE', [['product' => $this->costed, 'quantity' => 1, 'unit_price' => 100, 'cost' => 60]]);
    ($this->makeOrder)('SO-THREE-LINES', [
        ['product' => $this->costed, 'quantity' => 1, 'unit_price' => 100, 'cost' => 60],
        ['product' => $this->costed, 'quantity' => 1, 'unit_price' => 100, 'cost' => 60],
        ['product' => $this->uncosted, 'quantity' => 1, 'unit_price' => 40],
    ]);

    $sorted = ($this->report)('?sort=profit_desc');

    expect((float) $sorted->json('data.summary.total_sales'))->toBe(340.0)
        ->and($sorted->json('data.summary.total_orders'))->toBe(2)
        ->and($sorted->json('data.pagination.total'))->toBe(2);
});

it('exports the profit columns alongside the order', function () {
    ($this->makeOrder)('SO-EXPORT', [
        ['product' => $this->costed, 'quantity' => 1, 'unit_price' => 100, 'cost' => 60],
    ]);

    $csv = $this->actingAs($this->admin, 'sanctum')
        ->get('/api/v1/admin/reports/sales/export')
        ->assertOk()
        ->assertHeader('content-type', 'text/csv; charset=UTF-8')
        ->getContent();

    expect($csv)->toContain('gross_profit')
        ->and($csv)->toContain('estimated_lines')
        ->and($csv)->toContain('SO-EXPORT');
});
