<?php

use App\Models\Customer;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\User;
use App\Services\Sales\PaymentRecorder;
use Illuminate\Support\Facades\DB;

/**
 * The payments screen: found on the server, summed over the search, and
 * corrected without leaving the invoice, the balance or the books behind.
 */
beforeEach(function () {
    $this->user = User::factory()->admin()->create();
    $this->customer = Customer::create(['name' => 'عميل الدفعات', 'phone' => '0999555666', 'balance' => 0]);
    $this->other = Customer::create(['name' => 'مؤسسة الري', 'phone' => '0999555777', 'balance' => 0]);

    $this->invoice = function (float $total, array $extra = []) {
        static $n = 0;
        $n++;

        return Invoice::create($extra + [
            'invoice_number' => 'INV-PAY-'.$n,
            'customer_id' => $this->customer->id,
            'subtotal' => $total,
            'tax' => 0,
            'total' => $total,
            'paid_amount' => 0,
            'due_amount' => $total,
            'status' => Invoice::STATUS_CONFIRMED,
        ]);
    };

    $this->collect = fn (Invoice $invoice, float $amount, string $method = 'cash') => app(PaymentRecorder::class)
        ->record($invoice, $amount, ['method' => $method, 'created_by' => $this->user->id]);

    $this->api = fn () => $this->actingAs($this->user, 'sanctum');
});

test('the list searches on the server and sums the search', function () {
    ($this->collect)(($this->invoice)(300), 100, 'cash');
    ($this->collect)(($this->invoice)(300), 200, 'bank_transfer');
    $theirs = ($this->invoice)(500, ['customer_id' => $this->other->id]);
    ($this->collect)($theirs, 50, 'cash');

    Payment::create([
        'payment_number' => 'REF-TEST-1',
        'customer_id' => $this->customer->id,
        'payment_method' => 'cash',
        'status' => Payment::STATUS_REFUNDED,
        'amount' => -30,
        'payment_date' => now()->toDateString(),
    ]);

    $data = ($this->api)()->getJson('/api/v1/payments?with_summary=1&per_page=1')->assertOk()->json('data');

    expect($data['payments'])->toHaveCount(1)
        ->and($data['pagination']['total'])->toBe(4)
        ->and($data['summary']['collected'])->toEqual(350)
        ->and($data['summary']['by_method']['bank_transfer']['total'])->toEqual(200)
        ->and($data['summary']['refunded'])->toEqual(30)
        ->and($data['summary']['net'])->toEqual(320)
        ->and($data['summary']['today'])->toEqual(350);

    $found = ($this->api)()->getJson('/api/v1/payments?with_summary=1&search=الري')->json('data');
    expect($found['payments'])->toHaveCount(1)
        ->and($found['summary']['collected'])->toEqual(50);

    $refunds = ($this->api)()->getJson('/api/v1/payments?kind=refund')->json('data.payments');
    expect($refunds)->toHaveCount(1)
        ->and($refunds[0]['is_refund'])->toBeTrue()
        ->and($refunds[0]['can_change'])->toBeFalse();

    expect(($this->api)()->getJson('/api/v1/payments?search='.$theirs->invoice_number)->json('data.payments'))->toHaveCount(1);
});

test('a refund from a return cannot be reversed or edited here', function () {
    $refund = Payment::create([
        'payment_number' => 'REF-TEST-2',
        'customer_id' => $this->customer->id,
        'payment_method' => 'cash',
        'status' => Payment::STATUS_REFUNDED,
        'amount' => -40,
    ]);

    ($this->api)()->deleteJson("/api/v1/payments/{$refund->id}")->assertUnprocessable();
    ($this->api)()->putJson("/api/v1/payments/{$refund->id}", ['payment_method' => 'cash', 'amount' => 10])->assertUnprocessable();

    expect(Payment::find($refund->id))->not->toBeNull();
});

test('reversing the payment that settled an invoice takes its paid stamp off', function () {
    $invoice = ($this->invoice)(100);
    $payment = ($this->collect)($invoice, 100);
    expect($invoice->refresh()->paid_at)->not->toBeNull();

    ($this->api)()->deleteJson("/api/v1/payments/{$payment->id}")->assertOk();

    $invoice->refresh();
    expect((float) $invoice->paid_amount)->toEqual(0.0)
        ->and((float) $invoice->due_amount)->toEqual(100.0)
        ->and($invoice->paid_at)->toBeNull();
});

test('correcting an on-account payment moves the balance and the entry', function () {
    $payment = ($this->api)()->postJson('/api/v1/payments', [
        'customer_id' => $this->customer->id,
        'payment_method' => 'cash',
        'amount' => 100,
    ])->assertCreated()->json('data');

    expect((float) $this->customer->refresh()->balance)->toEqual(-100.0);

    ($this->api)()->putJson("/api/v1/payments/{$payment['id']}", ['payment_method' => 'cash', 'amount' => 60])->assertOk();

    expect((float) $this->customer->refresh()->balance)->toEqual(-60.0);

    // The original entry reversed and the corrected one posted: the cash
    // account, across all of them, holds the corrected amount.
    $lines = DB::table('journal_entry_lines')
        ->join('journal_entry_headers', 'journal_entry_headers.id', '=', 'journal_entry_lines.journal_entry_header_id')
        ->where('journal_entry_headers.posting_key', 'like', 'payment:'.$payment['id'].'%');
    $cashAccount = (clone $lines)
        ->where('journal_entry_headers.posting_key', 'like', 'payment:'.$payment['id'].':corrected:%')
        ->where('journal_entry_lines.debit', '>', 0)
        ->value('journal_entry_lines.account_id');
    $cashNet = (clone $lines)->where('journal_entry_lines.account_id', $cashAccount)
        ->selectRaw('SUM(journal_entry_lines.debit) - SUM(journal_entry_lines.credit) as net')
        ->value('net');

    expect($cashAccount)->not->toBeNull()
        ->and(round((float) $cashNet, 2))->toEqual(60.0);
});

test('nothing is collected against a cancelled invoice', function () {
    $invoice = ($this->invoice)(100, ['status' => Invoice::STATUS_CANCELLED]);

    ($this->api)()->postJson('/api/v1/payments', [
        'customer_id' => $this->customer->id,
        'invoice_id' => $invoice->id,
        'payment_method' => 'cash',
        'amount' => 50,
    ])->assertUnprocessable();

    expect(Payment::where('invoice_id', $invoice->id)->exists())->toBeFalse();
});

test('what a credit note already settled cannot be paid again', function () {
    $invoice = ($this->invoice)(100);
    DB::table('credit_notes')->insert([
        'credit_note_number' => 'CN-PAY-1',
        'invoice_id' => $invoice->id,
        'customer_id' => $this->customer->id,
        'total' => 40,
        'status' => 'issued',
        'issue_date' => now()->toDateString(),
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    ($this->api)()->postJson('/api/v1/payments', [
        'customer_id' => $this->customer->id,
        'invoice_id' => $invoice->id,
        'payment_method' => 'cash',
        'amount' => 80,
    ])->assertUnprocessable();

    ($this->api)()->postJson('/api/v1/payments', [
        'customer_id' => $this->customer->id,
        'invoice_id' => $invoice->id,
        'payment_method' => 'cash',
        'amount' => 60,
    ])->assertCreated();
});
