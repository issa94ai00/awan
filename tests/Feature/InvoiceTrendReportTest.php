<?php

use App\Models\Customer;
use App\Models\Invoice;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

function trendInvoice(array $attributes, string $createdAt): Invoice
{
    static $n = 0;
    $n++;

    $invoice = Invoice::create(array_merge([
        'invoice_number' => "INV-TREND-{$n}",
        'subtotal' => $attributes['total'],
        'tax' => 0,
        'discount' => 0,
        'currency' => 'SYP',
        'created_by' => User::first()->id,
    ], $attributes));

    // Not fillable: stamped directly, the way the day an invoice was raised is.
    DB::table('invoices')->where('id', $invoice->id)->update(['created_at' => $createdAt]);

    return $invoice;
}

beforeEach(function () {
    Carbon::setTestNow('2026-09-16 12:00:00'); // a Wednesday
    $this->actingAs(User::factory()->create(), 'sanctum');
    $this->customer = Customer::create(['name' => 'Trend Customer', 'email' => 'trend@example.test', 'phone' => '0999000111']);

    trendInvoice(['customer_id' => $this->customer->id, 'total' => 100, 'paid_amount' => 100, 'due_amount' => 0, 'status' => Invoice::STATUS_DELIVERED], '2026-09-01 10:00:00');
    trendInvoice(['total' => 50, 'paid_amount' => 0, 'due_amount' => 50, 'status' => Invoice::STATUS_PENDING], '2026-09-01 15:00:00');
    trendInvoice(['total' => 70, 'paid_amount' => 20, 'due_amount' => 50, 'status' => Invoice::STATUS_CONFIRMED], '2026-09-10 09:00:00');
    trendInvoice(['total' => 999, 'paid_amount' => 0, 'due_amount' => 0, 'status' => Invoice::STATUS_CANCELLED], '2026-09-10 11:00:00');
});

afterEach(fn () => Carbon::setTestNow());

it('gives a day per row for the period, zeros included, and stops at today', function () {
    $trend = $this->getJson('/api/v1/admin/reports/invoices/trend?date_filter_type=this_month&group_by=day')
        ->assertOk()
        ->json('data.trend');

    // 1–16 September: the rest of the month has not happened yet.
    expect($trend)->toHaveCount(16)
        ->and($trend[0])->toMatchArray(['period' => '2026-09-01', 'total_invoices' => 2, 'total_invoiced' => 150.0, 'paid_amount' => 100.0])
        ->and($trend[1])->toMatchArray(['period' => '2026-09-02', 'total_invoices' => 0, 'total_invoiced' => 0.0])
        // The cancelled invoice raised that day is not billing.
        ->and($trend[9])->toMatchArray(['period' => '2026-09-10', 'total_invoices' => 1, 'total_invoiced' => 70.0]);
});

it('folds days into weeks and months', function () {
    $weeks = $this->getJson('/api/v1/admin/reports/invoices/trend?date_filter_type=this_month&group_by=week')->json('data.trend');
    // Weeks start where the app's locale starts them (Saturday), as the
    // "this week" filter does.
    expect(array_column($weeks, 'period'))->toBe(['2026-08-29', '2026-09-05', '2026-09-12'])
        ->and($weeks[1]['total_invoiced'])->toEqual(70);

    $months = $this->getJson('/api/v1/admin/reports/invoices/trend?group_by=month')->json('data.trend');
    expect($months)->toHaveCount(1)
        ->and($months[0])->toMatchArray(['period' => '2026-09', 'total_invoices' => 3, 'total_invoiced' => 220.0, 'due_amount' => 100.0]);
});

it('follows the filters, and shows cancelled invoices only when asked for them', function () {
    $mine = $this->getJson('/api/v1/admin/reports/invoices/trend?group_by=month&customer_id='.$this->customer->id)->json('data.trend');
    expect($mine[0]['total_invoiced'])->toEqual(100);

    $cancelled = $this->getJson('/api/v1/admin/reports/invoices/trend?group_by=month&status=cancelled')->json('data.trend');
    expect($cancelled[0]['total_invoiced'])->toEqual(999);
});

it('breaks the invoice dimensions down by status', function () {
    $statuses = collect($this->getJson('/api/v1/admin/reports/invoices/dimensions')->assertOk()->json('data.status_summary'))
        ->keyBy('status');

    expect($statuses)->toHaveCount(4)
        ->and($statuses['cancelled']['total_invoiced'])->toEqual(999)
        ->and($statuses['pending']['due_amount'])->toEqual(50);
});

it('can leave cancelled invoices out of the breakdowns while still counting them by status', function () {
    $data = $this->getJson('/api/v1/admin/reports/invoices/dimensions?exclude_cancelled=1')->assertOk()->json('data');

    expect($data['overall']['total_invoiced'])->toEqual(220)
        ->and(collect($data['status_summary'])->firstWhere('status', 'cancelled')['total_invoiced'])->toEqual(999);

    // Asked for by name, cancelled is what is shown.
    $this->getJson('/api/v1/admin/reports/invoices/dimensions?exclude_cancelled=1&status=cancelled')
        ->assertJsonPath('data.overall.total_invoiced', 999);

    $this->getJson('/api/v1/admin/reports/invoices/performance?exclude_cancelled=1')
        ->assertOk()
        ->assertJsonPath('data.summary.total_invoices', 3);
});
