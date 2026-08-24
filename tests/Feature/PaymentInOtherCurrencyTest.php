<?php

use App\Models\Currency;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\User;
use App\Services\CurrencyService;

/**
 * Collecting in a currency other than the base — e.g. a customer paying an
 * invoice in Syrian pounds while the books are kept in dollars.
 *
 * The books must still hold exactly what CurrencyService would compute: the
 * converted amount, never a client-supplied figure. What the payer actually
 * handed over is kept alongside it (currency, rate, tendered amount) purely
 * for the receipt — it never has a say in how much of the invoice was paid.
 */
beforeEach(function () {
    $this->currencies = app(CurrencyService::class);
    $this->currencies->flushCache();

    $this->syp = Currency::where('code', 'SYP')->firstOrFail();
    $this->sar = Currency::where('code', 'SAR')->firstOrFail();

    $this->user = User::factory()->create();

    $this->customer = Customer::create([
        'name' => 'عميل',
        'email' => 'buyer@example.com',
        'phone' => '+966500000003',
        'status' => 'نشط',
        'balance' => 0,
    ]);

    $this->invoice = Invoice::create([
        'invoice_number' => 'INV-FX-1',
        'customer_id' => $this->customer->id,
        'subtotal' => 20,
        'tax' => 0,
        'discount' => 0,
        'additional_charges' => 0,
        'total' => 20,
        'paid_amount' => 0,
        'due_amount' => 20,
        'status' => Invoice::STATUS_CONFIRMED,
        'created_by' => $this->user->id,
        'currency' => 'USD',
    ]);

    $this->customer->updateBalance(20);
});

test('paying an invoice in a foreign currency settles it by the converted base amount', function () {
    $this->currencies->recordRate($this->syp, 13100);

    $response = $this->actingAs($this->user, 'sanctum')
        ->postJson('/api/v1/payments', [
            'invoice_id' => $this->invoice->id,
            'customer_id' => $this->customer->id,
            'payment_method' => 'cash',
            'amount' => 131000,
            'currency' => 'SYP',
        ]);

    $response->assertCreated();

    $payment = Payment::latest('id')->first();
    expect((float) $payment->amount)->toBe(10.0)
        ->and($payment->currency)->toBe('SYP')
        ->and((float) $payment->tendered_amount)->toBe(131000.0)
        ->and((float) $payment->exchange_rate)->toBe(13100.0);

    $this->invoice->refresh();
    expect((float) $this->invoice->paid_amount)->toBe(10.0)
        ->and((float) $this->invoice->due_amount)->toBe(10.0);

    $this->customer->refresh();
    expect((float) $this->customer->balance)->toBe(10.0);
});

test('paying in a currency with no recorded rate is refused rather than guessed', function () {
    $response = $this->actingAs($this->user, 'sanctum')
        ->postJson('/api/v1/payments', [
            'invoice_id' => $this->invoice->id,
            'customer_id' => $this->customer->id,
            'payment_method' => 'cash',
            'amount' => 75,
            'currency' => 'SAR',
        ]);

    $response->assertStatus(422);

    $this->invoice->refresh();
    expect((float) $this->invoice->paid_amount)->toBe(0.0)
        ->and(Payment::count())->toBe(0);
});

test('an on-account payment (no invoice) also converts through the recorded rate', function () {
    $this->currencies->recordRate($this->syp, 13100);

    $response = $this->actingAs($this->user, 'sanctum')
        ->postJson('/api/v1/payments', [
            'customer_id' => $this->customer->id,
            'payment_method' => 'cash',
            'amount' => 26200,
            'currency' => 'SYP',
        ]);

    $response->assertCreated();

    $payment = Payment::latest('id')->first();
    expect((float) $payment->amount)->toBe(2.0)
        ->and($payment->currency)->toBe('SYP')
        ->and((float) $payment->tendered_amount)->toBe(26200.0);

    $this->customer->refresh();
    expect((float) $this->customer->balance)->toBe(18.0);
});

test('omitting the currency still pays in the base currency exactly as before', function () {
    $response = $this->actingAs($this->user, 'sanctum')
        ->postJson('/api/v1/payments', [
            'invoice_id' => $this->invoice->id,
            'customer_id' => $this->customer->id,
            'payment_method' => 'cash',
            'amount' => 20,
        ]);

    $response->assertCreated();

    $payment = Payment::latest('id')->first();
    expect((float) $payment->amount)->toBe(20.0)
        ->and($payment->currency)->toBe('USD')
        ->and($payment->tendered_amount)->toBeNull();
});
