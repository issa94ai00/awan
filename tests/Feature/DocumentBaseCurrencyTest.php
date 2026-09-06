<?php

use App\Models\Customer;
use App\Models\Expense;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\SalesOrder;

/**
 * Which currency a financial document is denominated in when nobody says.
 *
 * The four financial tables were created with `currency DEFAULT 'SAR'`, a
 * literal from the application's original Saudi context, and several creation
 * paths never set the field. On books based in anything else, every such
 * document was stamped with a currency nobody had configured. Nothing converts
 * at posting time, so the amounts still reached the ledger at face value: the
 * figures were right and only the label was wrong — which is how a system ends
 * up reporting every invoice it holds as foreign.
 *
 * The rule is that a document follows the configured base currency, read at
 * insert time so it tracks configuration rather than the day the table was
 * built. An explicitly supplied currency is always honoured: genuine foreign
 * documents exist, and this is a default, not a rule.
 */
function baseCode(): string
{
    return base_currency_code();
}

it('stamps a new invoice with the books\' own currency', function () {
    $invoice = Invoice::create([
        'invoice_number' => 'INV-CUR-'.uniqid(),
        'subtotal' => 100,
        'total' => 100,
        'status' => 'confirmed',
    ]);

    expect($invoice->fresh()->currency)->toBe(baseCode())
        ->and(baseCode())->not->toBe('SAR');
});

it('stamps payments, sales orders and expenses the same way', function () {
    $customer = Customer::create(['name' => 'عميل', 'status' => 'active']);

    $payment = Payment::create([
        'payment_number' => 'PAY-CUR-'.uniqid(),
        'amount' => 50,
        'payment_date' => now(),
    ]);
    $order = SalesOrder::create([
        'order_number' => 'SO-CUR-'.uniqid(),
        'customer_id' => $customer->id,
        'subtotal' => 50,
        'total' => 50,
        'status' => 'pending',
    ]);
    $expense = Expense::create([
        'expense_number' => 'EXP-CUR-'.uniqid(),
        'description' => 'مصروف',
        'amount' => 10,
        'expense_date' => now(),
        'status' => 'pending',
    ]);

    expect($payment->fresh()->currency)->toBe(baseCode())
        ->and($order->fresh()->currency)->toBe(baseCode())
        ->and($expense->fresh()->currency)->toBe(baseCode());
});

it('leaves a genuinely foreign document alone', function () {
    // A default, not a rule: an amount actually billed in another currency
    // keeps the currency it was billed in, and its own rate.
    $invoice = Invoice::create([
        'invoice_number' => 'INV-CUR-'.uniqid(),
        'subtotal' => 100,
        'total' => 100,
        'status' => 'confirmed',
        'currency' => 'SYP',
        'exchange_rate' => 13000,
    ]);

    expect($invoice->fresh()->currency)->toBe('SYP')
        ->and((float) $invoice->fresh()->exchange_rate)->toBe(13000.0);
});

it('treats a base-currency document as one-to-one', function () {
    $invoice = Invoice::create([
        'invoice_number' => 'INV-CUR-'.uniqid(),
        'subtotal' => 100,
        'total' => 100,
        'status' => 'confirmed',
    ]);

    // Nothing is converted on the way into the ledger, so a document already
    // in the base currency is 1:1 by definition rather than by luck.
    expect((float) $invoice->fresh()->exchange_rate)->toBe(1.0);
});
