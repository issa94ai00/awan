<?php

use App\Models\Customer;
use App\Models\Invoice;
use App\Models\Product;
use App\Models\SalesOrder;
use App\Models\User;
use App\Models\Warehouse;
use App\Models\WarehouseInventory;

/**
 * What an invoice may do once raised: move forward, be cancelled properly —
 * whichever door the cancellation comes through — and only then be deleted.
 * Plus the list's money figures, which describe the search, not the page.
 */
beforeEach(function () {
    $this->user = User::factory()->admin()->create();
    $this->shelf = Warehouse::create(['name' => 'مستودع القواعد', 'code' => 'RULES', 'is_active' => true]);
    $this->product = Product::factory()->create(['price' => 100, 'cost_price' => 60]);
    WarehouseInventory::create([
        'product_id' => $this->product->id,
        'warehouse_id' => $this->shelf->id,
        'quantity' => 20,
        'available_quantity' => 20,
        'reserved_quantity' => 0,
    ]);
    $this->customer = Customer::create(['name' => 'عميل الفواتير', 'phone' => '0999333444', 'balance' => 0]);

    $this->onHand = fn () => (int) WarehouseInventory::where('product_id', $this->product->id)
        ->where('warehouse_id', $this->shelf->id)->value('available_quantity');

    $this->sell = function (int $quantity = 5, float $paid = 0) {
        $this->actingAs($this->user)->postJson('/api/v1/invoices', [
            'customer_id' => $this->customer->id,
            'paid_amount' => $paid,
            'items' => [
                ['product_id' => $this->product->id, 'quantity' => $quantity, 'unit_price' => 100, 'warehouse_id' => $this->shelf->id],
            ],
        ])->assertCreated();

        return Invoice::latest('id')->first();
    };

    $this->move = fn (Invoice $invoice, string $status) => $this->actingAs($this->user)
        ->putJson("/api/v1/invoices/{$invoice->id}/status", ['status' => $status]);
});

test('a cancelled invoice cannot be brought back', function () {
    $invoice = ($this->sell)();
    ($this->move)($invoice, 'cancelled')->assertOk();

    ($this->move)($invoice, 'delivered')->assertUnprocessable();
    ($this->move)($invoice, 'pending')->assertUnprocessable();

    expect($invoice->refresh()->status)->toBe('cancelled')
        ->and(($this->onHand)())->toBe(20);
});

test('stages move forward only', function () {
    $invoice = ($this->sell)();

    ($this->move)($invoice, 'shipped')->assertUnprocessable();
    ($this->move)($invoice, 'confirmed')->assertOk();
    ($this->move)($invoice->refresh(), 'pending')->assertUnprocessable();
});

test('delivered does not mark an unpaid invoice as paid', function () {
    $invoice = ($this->sell)();
    foreach (['confirmed', 'processing', 'shipped', 'delivered'] as $status) {
        ($this->move)($invoice->refresh(), $status)->assertOk();
    }

    expect($invoice->refresh()->paid_at)->toBeNull();

    // Delivered goods come back through a return, not a cancellation.
    ($this->move)($invoice, 'cancelled')->assertUnprocessable();
});

test('cancelling takes the sale off the customer balance', function () {
    $invoice = ($this->sell)(5, 200);
    expect((float) $this->customer->refresh()->balance)->toEqual(300.0);

    ($this->move)($invoice, 'cancelled')->assertOk();

    // The 200 paid stays with the customer, as a credit.
    expect((float) $this->customer->refresh()->balance)->toEqual(-200.0);
});

test('cancelling from the edit form does everything the cancel does', function () {
    $invoice = ($this->sell)(5);
    expect(($this->onHand)())->toBe(15);

    $this->actingAs($this->user)->putJson("/api/v1/invoices/{$invoice->id}", ['status' => 'cancelled'])->assertOk();

    // It used to relabel the invoice and leave the goods off the shelf.
    expect($invoice->refresh()->status)->toBe('cancelled')
        ->and(($this->onHand)())->toBe(15 + 5)
        ->and((float) $this->customer->refresh()->balance)->toEqual(0.0);
});

test('the edit form cannot skip or reverse stages', function () {
    $invoice = ($this->sell)();

    $this->actingAs($this->user)->putJson("/api/v1/invoices/{$invoice->id}", ['status' => 'delivered'])->assertUnprocessable();
    expect($invoice->refresh()->status)->toBe('pending');
});

test('only a cancelled invoice with no payments can be deleted', function () {
    $live = ($this->sell)();
    $this->actingAs($this->user)->deleteJson("/api/v1/invoices/{$live->id}")->assertUnprocessable();

    $paid = ($this->sell)(1, 50);
    ($this->move)($paid, 'cancelled')->assertOk();
    $this->actingAs($this->user)->deleteJson("/api/v1/invoices/{$paid->id}")->assertUnprocessable();

    ($this->move)($live, 'cancelled')->assertOk();
    $this->actingAs($this->user)->deleteJson("/api/v1/invoices/{$live->id}")->assertOk();

    expect(Invoice::find($live->id))->toBeNull()->and(Invoice::find($paid->id))->not->toBeNull();
});

test('an order invoice is moved by its order, not here', function () {
    $order = SalesOrder::create([
        'order_number' => 'SO-RULES-1',
        'customer_id' => $this->customer->id,
        'status' => 'confirmed',
        'order_date' => now()->toDateString(),
        'subtotal' => 100,
        'total' => 100,
    ]);
    $invoice = Invoice::create([
        'invoice_number' => 'INV-RULES-ORDER',
        'customer_id' => $this->customer->id,
        'sales_order_id' => $order->id,
        'status' => 'confirmed',
        'subtotal' => 100,
        'total' => 100,
    ]);

    ($this->move)($invoice, 'cancelled')->assertUnprocessable();
    expect($invoice->refresh()->status)->toBe('confirmed');
});

test('the list summarises money over the search and filters by payment', function () {
    $unpaid = ($this->sell)(2, 0);
    $partial = ($this->sell)(3, 100);
    $overpaid = ($this->sell)(1, 150);
    $cancelled = ($this->sell)(1, 0);
    ($this->move)($cancelled, 'cancelled')->assertOk();

    $data = $this->actingAs($this->user)
        ->getJson('/api/v1/invoices?lean=1&with_summary=1&per_page=1')
        ->assertOk()
        ->json('data');

    expect($data['invoices'])->toHaveCount(1)
        ->and($data['invoices'][0])->toHaveKey('items_count')
        ->and($data['invoices'][0])->not->toHaveKey('items')
        ->and($data['summary']['total'])->toBe(4)
        ->and($data['summary']['by_status']['cancelled'])->toBe(1)
        ->and($data['summary']['billed'])->toEqual(600)
        ->and($data['summary']['outstanding'])->toEqual(400)
        ->and($data['summary']['outstanding_count'])->toBe(2)
        ->and($data['summary']['credit'])->toEqual(50)
        ->and($data['summary']['aging']['0_30'])->toEqual(400);

    $ids = fn (string $payment) => collect($this->actingAs($this->user)
        ->getJson("/api/v1/invoices?lean=1&payment={$payment}")->json('data.invoices'))->pluck('id')->sort()->values()->all();

    expect($ids('unpaid'))->toBe([$unpaid->id])
        ->and($ids('partial'))->toBe([$partial->id])
        ->and($ids('due'))->toBe([$unpaid->id, $partial->id])
        ->and($ids('credit'))->toBe([$overpaid->id]);

    $row = collect($this->actingAs($this->user)->getJson('/api/v1/invoices?lean=1')->json('data.invoices'))
        ->firstWhere('id', $overpaid->id);
    expect($row['payment_state'])->toBe('credit')
        ->and((float) $row['outstanding'])->toEqual(-50.0)
        ->and($row['allowed_statuses'])->toBe(['confirmed', 'cancelled']);
});
