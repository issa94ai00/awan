<?php

use App\Models\Customer;
use App\Models\Invoice;
use App\Models\LedgerAccount;
use App\Models\Payment;
use App\Models\Role;
use App\Models\User;
use App\Services\Accounting\LedgerPostingService;

/**
 * Where the money went, as opposed to what was earned.
 *
 * A business can be plainly profitable and still run out of cash: a sale on
 * credit is income the day it is invoiced and money only when it is collected,
 * and stock bought for the shelf is cash gone that no income statement shows.
 * The system could report the profit and never the cash.
 *
 * Built by the direct method — every movement on cash and bank, classified by
 * whatever sat on the other side of its entry. That is only possible because
 * the ledger is complete: each cash line has a counterpart naming the reason
 * the money moved.
 */
beforeEach(function () {
    $this->admin = User::factory()->create([
        'is_admin' => true,
        'role_id' => Role::firstOrCreate(['name' => 'admin'], ['display_name' => 'admin'])->id,
    ]);

    $this->customer = Customer::create([
        'name' => 'عميل التجربة',
        'email' => 'cf@example.test',
        'status' => 'active',
    ]);

    $this->ledger = app(LedgerPostingService::class);

    $this->collect = function (float $amount, string $date) {
        $invoice = Invoice::create([
            'invoice_number' => 'INV-'.uniqid(),
            'customer_id' => $this->customer->id,
            'status' => 'sent',
            'subtotal' => $amount,
            'tax' => 0,
            'total' => $amount,
            'due_amount' => $amount,
        ]);
        $invoice->forceFill(['created_at' => $date])->save();
        $this->ledger->postInvoice($invoice->refresh());

        $payment = Payment::create([
            'payment_number' => 'PAY-'.uniqid(),
            'invoice_id' => $invoice->id,
            'customer_id' => $this->customer->id,
            'payment_method' => 'cash',
            'amount' => $amount,
            'payment_date' => $date,
            'status' => 'completed',
        ]);

        $this->ledger->postPayment($payment);
    };

    $this->report = fn (string $from, string $to) => $this->actingAs($this->admin)
        ->getJson('/api/v1/admin/accounting/cash-flow?date_from='.$from.'&date_to='.$to)
        ->assertOk()
        ->json('data');
});

test('collecting from a customer is an operating inflow', function () {
    ($this->collect)(500, '2026-03-10');

    $data = ($this->report)('2026-03-01', '2026-03-31');

    expect((float) $data['activities']['operating']['total_in'])->toBe(500.0);
    expect((float) $data['net_change'])->toBe(500.0);
    expect($data['activities']['operating']['inflows'][0]['label'])->toContain('ذمم');
});

test('capital put into the business is a financing inflow, not trading', function () {
    $this->ledger->post(
        key: 'opening_balance:cash',
        date: '2026-03-05',
        description: 'رصيد افتتاحي',
        lines: [
            ['role' => 'cash', 'debit' => 10000, 'description' => 'صندوق'],
            ['role' => 'capital', 'credit' => 10000, 'description' => 'رأس المال'],
        ],
        module: 'opening',
    );

    $data = ($this->report)('2026-03-01', '2026-03-31');

    // The distinction the statement exists to make: money from the owner is
    // not money the trading produced.
    expect((float) $data['activities']['financing']['total_in'])->toBe(10000.0);
    expect((float) $data['activities']['operating']['total_in'])->toBe(0.0);
});

test('paying an expense is an operating outflow', function () {
    $this->ledger->postExpense((object) [
        'id' => 771,
        'amount' => 300,
        'status' => 'paid',
        'category' => 'shipping',
        'expense_date' => '2026-03-12',
        'expense_number' => 'EXP-CF',
        'description' => 'شحن',
        'currency' => 'SAR',
    ]);

    $data = ($this->report)('2026-03-01', '2026-03-31');

    expect((float) $data['activities']['operating']['total_out'])->toBe(300.0);
    expect((float) $data['net_change'])->toBe(-300.0);
});

test('the statement opens where the previous period closed', function () {
    ($this->collect)(700, '2026-02-20');
    ($this->collect)(250, '2026-03-15');

    $data = ($this->report)('2026-03-01', '2026-03-31');

    // February is behind the window, so it is the opening figure — a cash flow
    // statement that does not tie the two together is a list, not a statement.
    expect((float) $data['opening_balance'])->toBe(700.0);
    expect((float) $data['net_change'])->toBe(250.0);
    expect((float) $data['closing_balance'])->toBe(950.0);
});

test('the closing figure ties to what the cash accounts actually hold', function () {
    ($this->collect)(400, '2026-03-02');
    ($this->collect)(150, '2026-03-20');

    $data = ($this->report)('2026-01-01', '2026-12-31');

    expect((float) $data['closing_balance'])->toBe((float) $data['stored_balance']);
});

test('an invoice with nothing collected produces no cash flow at all', function () {
    $invoice = Invoice::create([
        'invoice_number' => 'INV-UNPAID',
        'customer_id' => $this->customer->id,
        'status' => 'sent',
        'subtotal' => 900,
        'tax' => 0,
        'total' => 900,
        'due_amount' => 900,
    ]);
    $invoice->forceFill(['created_at' => '2026-03-08'])->save();
    $this->ledger->postInvoice($invoice->refresh());

    $data = ($this->report)('2026-03-01', '2026-03-31');

    // Profitable and penniless: exactly the gap this statement exists to show.
    expect((float) $data['net_change'])->toBe(0.0);
});

test('buying something to keep is an investing outflow', function () {
    $asset = App\Models\FixedAsset::create([
        'asset_number' => 'FA-00001',
        'name' => 'سيارة توصيل',
        'acquired_on' => '2026-03-08',
        'cost' => 9000,
        'salvage_value' => 0,
        'useful_life_months' => 60,
        'status' => 'active',
    ]);

    $this->ledger->postAssetAcquisition($asset, 'cash');

    $data = ($this->report)('2026-03-01', '2026-03-31');

    // The distinction that makes the statement worth reading: money spent on
    // something the business keeps is not money the trading consumed.
    expect((float) $data['activities']['investing']['total_out'])->toBe(9000.0);
    expect((float) $data['activities']['operating']['total_out'])->toBe(0.0);
});

test('the statement is refused to a sales account', function () {
    $sales = User::factory()->create([
        'role_id' => Role::firstOrCreate(['name' => 'sells'], ['display_name' => 'sells'])->id,
    ]);

    $this->actingAs($sales)
        ->getJson('/api/v1/admin/accounting/cash-flow')
        ->assertForbidden();
});
