<?php

use App\Models\Customer;
use App\Models\Invoice;
use App\Models\JournalEntryHeader;
use App\Models\Payment;
use App\Models\User;
use App\Services\Sales\PaymentRecorder;

/**
 * Editing a payment used to be its own, unsynced path: it moved the invoice's
 * paid/due amounts by hand, never touched the customer's balance, never
 * touched the ledger, and marked a fully-paid invoice "delivered" — describing
 * the goods as having arrived because the money had. These hold the corrected
 * `PaymentController::update()` to the same four-records-in-step standard the
 * rest of the payment flow already meets.
 */
beforeEach(function () {
    $this->user = User::factory()->create();

    $this->customer = Customer::create([
        'name' => 'عميل',
        'email' => 'customer@example.com',
        'phone' => '+966500000002',
        'status' => 'نشط',
        'balance' => 0,
    ]);

    $this->invoice = Invoice::create([
        'invoice_number' => 'INV-TEST-1',
        'customer_id' => $this->customer->id,
        'subtotal' => 500,
        'tax' => 0,
        'discount' => 0,
        'additional_charges' => 0,
        'total' => 500,
        'paid_amount' => 0,
        'due_amount' => 500,
        'status' => Invoice::STATUS_CONFIRMED,
        'created_by' => $this->user->id,
        'currency' => 'SAR',
    ]);

    // The invoice itself carries no receivable entry in these tests — only the
    // payment side is under test — so the customer starts owing the invoice
    // total, exactly as SalesOrderWorkflowService::applyConfirmation leaves it.
    $this->customer->updateBalance(500);

    $this->payment = app(PaymentRecorder::class)->record($this->invoice, 200, [
        'method' => 'cash',
        'created_by' => $this->user->id,
    ]);
});

test('raising a payment amount posts the difference instead of the whole new total', function () {
    $this->actingAs($this->user, 'sanctum')
        ->putJson("/api/v1/payments/{$this->payment->id}", [
            'payment_method' => 'cash',
            'amount' => 350,
        ])
        ->assertOk();

    $this->invoice->refresh();
    $this->customer->refresh();

    expect((float) $this->invoice->paid_amount)->toBe(350.0);
    expect((float) $this->invoice->due_amount)->toBe(150.0);
    // Owed 500, paid 200 up front (-200), now paid 350 total: 500 - 350 = 150.
    expect((float) $this->customer->balance)->toBe(150.0);

    $original = JournalEntryHeader::where('posting_key', 'payment:' . $this->payment->id)->first();
    expect($original->status)->toBe('reversed');

    $corrected = JournalEntryHeader::where('posting_key', 'like', 'payment:' . $this->payment->id . ':corrected:%')->first();
    expect($corrected)->not->toBeNull();
    expect((float) $corrected->total_debit)->toBe(350.0);
    expect((float) $corrected->total_credit)->toBe(350.0);
});

test('lowering a payment amount reopens the difference on the customer balance', function () {
    $this->actingAs($this->user, 'sanctum')
        ->putJson("/api/v1/payments/{$this->payment->id}", [
            'payment_method' => 'cash',
            'amount' => 50,
        ])
        ->assertOk();

    $this->invoice->refresh();
    $this->customer->refresh();

    expect((float) $this->invoice->paid_amount)->toBe(50.0);
    expect((float) $this->invoice->due_amount)->toBe(450.0);
    expect((float) $this->customer->balance)->toBe(450.0);
});

test('a corrected amount cannot exceed what the invoice actually has left', function () {
    $this->actingAs($this->user, 'sanctum')
        ->putJson("/api/v1/payments/{$this->payment->id}", [
            'payment_method' => 'cash',
            'amount' => 999,
        ])
        ->assertStatus(422);

    $this->invoice->refresh();
    expect((float) $this->invoice->paid_amount)->toBe(200.0);
});

test('paying off an invoice through an edit does not mark it delivered', function () {
    $this->actingAs($this->user, 'sanctum')
        ->putJson("/api/v1/payments/{$this->payment->id}", [
            'payment_method' => 'cash',
            'amount' => 500,
        ])
        ->assertOk();

    $this->invoice->refresh();
    expect((float) $this->invoice->due_amount)->toBe(0.0);
    expect($this->invoice->status)->toBe(Invoice::STATUS_CONFIRMED);
});

test('reassigning a payment to another invoice or customer is not accepted', function () {
    $otherCustomer = Customer::create([
        'name' => 'عميل آخر',
        'email' => 'other@example.com',
        'phone' => '+966500000003',
        'status' => 'نشط',
    ]);

    $response = $this->actingAs($this->user, 'sanctum')
        ->putJson("/api/v1/payments/{$this->payment->id}", [
            'payment_method' => 'cash',
            'amount' => 200,
            'customer_id' => $otherCustomer->id,
        ])
        ->assertOk();

    $this->payment->refresh();
    expect((int) $this->payment->customer_id)->toBe($this->customer->id);
});
