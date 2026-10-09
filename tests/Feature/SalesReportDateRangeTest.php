<?php

use App\Models\Customer;
use App\Models\Invoice;
use App\Models\SalesOrder;
use App\Models\User;
use Illuminate\Support\Carbon;

/**
 * The sales report's periods cover whole days at both ends.
 *
 * They were whereBetween(column, [from, to]) with bare dates, so on a
 * timestamp the end date meant its midnight: "this month" dropped the
 * invoices of the month's last day, a custom range lost its end day, and the
 * invoices tab ignored a range with only one end set.
 */
beforeEach(function () {
    $this->admin = User::factory()->create();
    $this->customer = Customer::create(['name' => 'زبون الفترات', 'status' => 'active']);

    $this->invoiceAt = function (string $number, string $when) {
        $invoice = Invoice::create([
            'invoice_number' => $number,
            'customer_id' => $this->customer->id,
            'subtotal' => 100,
            'tax' => 0,
            'discount' => 0,
            'total' => 100,
            'status' => 'confirmed',
        ]);
        $invoice->forceFill(['created_at' => Carbon::parse($when)])->saveQuietly();

        return $invoice;
    };

    $this->orderOn = fn (string $number, string $day) => SalesOrder::create([
        'order_number' => $number,
        'customer_id' => $this->customer->id,
        'order_date' => $day,
        'subtotal' => 100,
        'tax' => 0,
        'discount' => 0,
        'total' => 100,
        'status' => SalesOrder::STATUS_CONFIRMED,
        'currency' => 'SYP',
    ]);

    $this->invoiceNumbers = fn (string $query) => collect(
        $this->actingAs($this->admin, 'sanctum')
            ->getJson('/api/v1/admin/reports/invoices'.$query)
            ->assertOk()
            ->json('data.invoices')
    )->pluck('invoice_number')->sort()->values()->all();
});

afterEach(fn () => Carbon::setTestNow());

it('keeps the invoices of the last day of a custom range', function () {
    ($this->invoiceAt)('INV-IN', '2026-09-10 18:30:00');
    ($this->invoiceAt)('INV-AFTER', '2026-09-11 00:00:01');

    expect(($this->invoiceNumbers)('?date_filter_type=custom&start_date=2026-09-01&end_date=2026-09-10'))
        ->toBe(['INV-IN']);
});

it('filters invoices on a range with only one end set', function () {
    ($this->invoiceAt)('INV-OLD', '2026-08-20 10:00:00');
    ($this->invoiceAt)('INV-NEW', '2026-09-05 10:00:00');

    expect(($this->invoiceNumbers)('?date_filter_type=custom&start_date=2026-09-01'))->toBe(['INV-NEW']);
    expect(($this->invoiceNumbers)('?date_filter_type=custom&end_date=2026-08-31'))->toBe(['INV-OLD']);
});

it('counts the evening of the month\'s last day as this month', function () {
    Carbon::setTestNow('2026-09-15 12:00:00');
    ($this->invoiceAt)('INV-LAST-DAY', '2026-09-30 21:00:00');
    ($this->invoiceAt)('INV-NEXT', '2026-10-01 08:00:00');

    expect(($this->invoiceNumbers)('?date_filter_type=this_month'))->toBe(['INV-LAST-DAY']);
});

it('finds last month from the 31st', function () {
    Carbon::setTestNow('2026-03-31 12:00:00');
    ($this->invoiceAt)('INV-FEB', '2026-02-27 09:00:00');
    ($this->invoiceAt)('INV-MAR', '2026-03-03 09:00:00');

    expect(($this->invoiceNumbers)('?date_filter_type=last_month'))->toBe(['INV-FEB']);
});

it('offers this year as a period', function () {
    Carbon::setTestNow('2026-06-01 12:00:00');
    ($this->invoiceAt)('INV-2025', '2025-12-31 23:00:00');
    ($this->invoiceAt)('INV-2026', '2026-12-31 23:00:00');

    expect(($this->invoiceNumbers)('?date_filter_type=this_year'))->toBe(['INV-2026']);
});

it('keeps the end day of a custom range for orders too', function () {
    ($this->orderOn)('SO-IN', '2026-09-10');
    ($this->orderOn)('SO-OUT', '2026-09-11');

    $numbers = collect(
        $this->actingAs($this->admin, 'sanctum')
            ->getJson('/api/v1/admin/reports/sales?date_filter_type=custom&start_date=2026-09-01&end_date=2026-09-10')
            ->assertOk()
            ->json('data.sales_orders')
    )->pluck('order_number')->all();

    expect($numbers)->toBe(['SO-IN']);
});

it('answers every report endpoint for a period', function (string $endpoint) {
    ($this->invoiceAt)('INV-ANY', '2026-09-10 12:00:00');
    ($this->orderOn)('SO-ANY', '2026-09-10');

    // The invoice summary joins lines and products, all with a created_at; an
    // unqualified date column there was ambiguous and failed the whole tab.
    $this->actingAs($this->admin, 'sanctum')
        ->getJson("/api/v1/admin/reports/{$endpoint}?date_filter_type=custom&start_date=2026-09-01&end_date=2026-09-30")
        ->assertOk();
})->with([
    'invoices', 'invoices/dimensions', 'invoices/performance', 'invoices/product-profitability', 'invoices/top-performers',
    'sales', 'sales/dimensions', 'sales/performance', 'sales/product-profitability', 'sales/summary', 'sales/top-performers',
]);
