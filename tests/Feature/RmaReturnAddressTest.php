<?php

use App\Models\Customer;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\Product;
use App\Models\RmaRequest;
use App\Models\User;

/**
 * The collection address on a return request, which is optional in the way the
 * counter actually works.
 *
 * The goods are usually handed back over the counter, so most requests carry no
 * address at all; when one is given it arrives however the customer said it —
 * a city and nothing else, a street dictated over the phone. The three fields
 * used to be mutually required ("started it, finish it"), which meant a
 * half-known address had to be either invented or thrown away, and the form had
 * no way to record the one fact it had been given.
 *
 * The form also posts all four keys on every save, filled in or not, so the
 * other half of "optional" is that an untouched section must not leave four
 * empty strings behind in the JSON column pretending to be an address.
 */
function rmaActor(): User
{
    return User::factory()->admin()->create();
}

function returnableInvoice(): array
{
    $customer = Customer::create([
        'name' => 'عميل الإرجاع',
        'phone' => '0900000000',
        'status' => 'active',
    ]);

    $product = Product::factory()->create(['price' => 100, 'cost_price' => 60]);

    $invoice = Invoice::create([
        'invoice_number' => 'INV-RMA-'.uniqid(),
        'customer_id' => $customer->id,
        'subtotal' => 100,
        'total' => 100,
        'status' => 'confirmed',
    ]);

    $item = InvoiceItem::create([
        'invoice_id' => $invoice->id,
        'product_id' => $product->id,
        'product_name' => $product->name ?? 'منتج',
        'quantity' => 4,
        'unit_price' => 100,
        'total_price' => 400,
    ]);

    return [$customer, $invoice, $item];
}

function rmaPayload(Customer $customer, Invoice $invoice, InvoiceItem $item, mixed $address): array
{
    return [
        'customer_id' => $customer->id,
        'invoice_id' => $invoice->id,
        'reason' => 'defective',
        'type' => 'refund',
        'return_address' => $address,
        'items' => [[
            'invoice_item_id' => $item->id,
            'quantity_requested' => 1,
            'condition' => 'new',
            'resolution' => 'refund',
        ]],
    ];
}

it('accepts a request with no collection address at all', function () {
    [$customer, $invoice, $item] = returnableInvoice();

    $this->actingAs(rmaActor())
        ->postJson('/api/v1/admin/rma', rmaPayload($customer, $invoice, $item, null))
        ->assertCreated();

    expect(RmaRequest::latest('id')->first()->return_address)->toBeNull();
});

it('stores nothing rather than four empty strings when the section is left untouched', function () {
    [$customer, $invoice, $item] = returnableInvoice();

    // Exactly what the form posts when the operator never opens the section.
    $blank = ['address_line1' => '', 'city' => '', 'country' => '', 'postal_code' => ''];

    $this->actingAs(rmaActor())
        ->postJson('/api/v1/admin/rma', rmaPayload($customer, $invoice, $item, $blank))
        ->assertCreated();

    // Not `[]` and not a row of blanks: an address that was never given is
    // absent, so "does this request have one?" has an honest answer.
    expect(RmaRequest::latest('id')->first()->return_address)->toBeNull();
});

it('keeps a half-known address instead of demanding the rest of it', function () {
    [$customer, $invoice, $item] = returnableInvoice();

    // The customer named a city and nothing else. This used to be a 422.
    $partial = ['address_line1' => '', 'city' => 'حلب', 'country' => '', 'postal_code' => ''];

    $this->actingAs(rmaActor())
        ->postJson('/api/v1/admin/rma', rmaPayload($customer, $invoice, $item, $partial))
        ->assertCreated();

    expect(RmaRequest::latest('id')->first()->return_address)->toBe(['city' => 'حلب']);
});

it('trims what it keeps and drops the blanks around it', function () {
    [$customer, $invoice, $item] = returnableInvoice();

    $messy = ['address_line1' => '  شارع بغداد  ', 'city' => ' دمشق ', 'country' => '  ', 'postal_code' => ''];

    $this->actingAs(rmaActor())
        ->postJson('/api/v1/admin/rma', rmaPayload($customer, $invoice, $item, $messy))
        ->assertCreated();

    expect(RmaRequest::latest('id')->first()->return_address)
        ->toBe(['address_line1' => 'شارع بغداد', 'city' => 'دمشق']);
});

it('still records a complete address unchanged', function () {
    [$customer, $invoice, $item] = returnableInvoice();

    $full = [
        'address_line1' => 'شارع بغداد، بناء 12',
        'city' => 'دمشق',
        'country' => 'سوريا',
        'postal_code' => '11000',
    ];

    $this->actingAs(rmaActor())
        ->postJson('/api/v1/admin/rma', rmaPayload($customer, $invoice, $item, $full))
        ->assertCreated();

    expect(RmaRequest::latest('id')->first()->return_address)->toBe($full);
});
