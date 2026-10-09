<?php

use App\Models\Customer;
use App\Models\Product;
use App\Models\Quote;
use App\Models\SalesOrder;
use App\Models\User;

/**
 * A quote moves draft → sent → accepted → one sales order, and nothing along
 * the way can fork it: no second order, no reused number, no edits after the
 * customer answered.
 */
beforeEach(function () {
    $this->admin = User::factory()->admin()->create();
    $this->customer = Customer::create(['name' => 'عميل العروض', 'phone' => '0999000222']);
    $this->product = Product::factory()->create(['price' => 50, 'name_ar' => 'خلاط']);

    $this->api = fn () => $this->actingAs($this->admin, 'sanctum');

    $this->make = fn (array $overrides = []) => ($this->api)()->postJson('/api/v1/quotes', $overrides + [
        'customer_id' => $this->customer->id,
        'valid_until' => now()->addDays(10)->toDateString(),
        'items' => [['product_id' => $this->product->id, 'quantity' => 2, 'unit_price' => 50]],
    ]);

    $this->move = fn (Quote $quote, string $status) => ($this->api)()
        ->putJson("/api/v1/quotes/{$quote->id}/status", ['status' => $status]);
});

test('numbers are not reused after a quote is deleted', function () {
    $first = Quote::find(($this->make)()->assertCreated()->json('data.id'));
    $second = Quote::find(($this->make)()->assertCreated()->json('data.id'));

    ($this->api)()->deleteJson("/api/v1/quotes/{$first->id}")->assertOk();

    // Counting rows would hand out the second quote's number again.
    ($this->make)()->assertCreated()->assertJsonPath('data.quote_number', fn ($n) => $n !== $second->quote_number);
});

test('lines take the product name and a discount above the line is refused', function () {
    ($this->make)()->assertCreated()->assertJsonPath('data.items.0.description', 'خلاط');

    ($this->make)([
        'items' => [['product_id' => $this->product->id, 'quantity' => 1, 'unit_price' => 50, 'discount' => 80]],
    ])->assertUnprocessable()->assertJsonValidationErrors('items.0.discount');

    ($this->make)(['discount' => 500])->assertUnprocessable()->assertJsonValidationErrors('discount');
});

test('a quote can be valid for today only', function () {
    ($this->make)(['valid_until' => now()->toDateString()])->assertCreated();
});

test('status moves follow the workflow', function () {
    $quote = Quote::find(($this->make)()->json('data.id'));

    ($this->move)($quote, 'expired')->assertUnprocessable();
    ($this->move)($quote, 'sent')->assertOk()->assertJsonPath('data.status', 'sent');
    ($this->move)($quote->refresh(), 'rejected')->assertOk();
    ($this->move)($quote->refresh(), 'accepted')->assertUnprocessable();
    ($this->move)($quote->refresh(), 'draft')->assertOk();
});

test('a lapsed quote cannot be accepted until its validity is extended', function () {
    $quote = Quote::find(($this->make)()->json('data.id'));
    $quote->update(['status' => 'sent', 'valid_until' => now()->subDay()->toDateString()]);

    ($this->move)($quote, 'accepted')->assertUnprocessable();
    expect($quote->refresh()->status)->toBe('sent');
});

test('the full save cannot change status and is refused once answered', function () {
    $quote = Quote::find(($this->make)()->json('data.id'));
    $payload = [
        'customer_id' => $this->customer->id,
        'status' => 'accepted',
        'items' => [['product_id' => $this->product->id, 'quantity' => 3, 'unit_price' => 40]],
    ];

    ($this->api)()->putJson("/api/v1/quotes/{$quote->id}", $payload)->assertOk();
    expect($quote->refresh()->status)->toBe('draft')->and((float) $quote->total)->toEqual(120.0);

    ($this->move)($quote, 'rejected')->assertOk();
    ($this->api)()->putJson("/api/v1/quotes/{$quote->id}", $payload)->assertUnprocessable();
});

test('an accepted quote becomes one sales order, and is then locked', function () {
    $quote = Quote::find(($this->make)()->json('data.id'));
    ($this->move)($quote, 'accepted')->assertOk();

    $order = ($this->api)()->postJson("/api/v1/quotes/{$quote->id}/convert-to-sales-order")
        ->assertCreated()
        ->json('data');

    expect((float) $order['total'])->toEqual(100.0)
        ->and(SalesOrder::find($order['id'])->statusHistory()->count())->toBe(1);

    ($this->api)()->postJson("/api/v1/quotes/{$quote->id}/convert-to-sales-order")
        ->assertStatus(409)
        ->assertJsonPath('data.order_number', $order['order_number']);

    expect(SalesOrder::where('quote_id', $quote->id)->count())->toBe(1);

    ($this->move)($quote->refresh(), 'sent')->assertUnprocessable();
    ($this->api)()->deleteJson("/api/v1/quotes/{$quote->id}")->assertUnprocessable();
    expect(Quote::find($quote->id))->not->toBeNull();
});

test('duplicating makes a fresh draft with the same lines', function () {
    $quote = Quote::find(($this->make)()->json('data.id'));
    ($this->move)($quote, 'rejected')->assertOk();

    $copy = ($this->api)()->postJson("/api/v1/quotes/{$quote->id}/duplicate")->assertCreated()->json('data');

    expect($copy['status'])->toBe('draft')
        ->and($copy['quote_number'])->not->toBe($quote->quote_number)
        ->and($copy['items'])->toHaveCount(1)
        ->and((float) $copy['total'])->toEqual(100.0);
});

test('the list searches on the server and summarises every quote', function () {
    $other = Customer::create(['name' => 'شركة المياه', 'phone' => '0999000333']);
    ($this->make)()->assertCreated();
    ($this->make)(['customer_id' => $other->id])->assertCreated();
    $lapsed = Quote::find(($this->make)()->json('data.id'));
    $lapsed->update(['status' => 'sent', 'valid_until' => now()->subDays(2)->toDateString()]);

    $data = ($this->api)()->getJson('/api/v1/quotes?per_page=1')->assertOk()->json('data');
    expect($data['quotes'])->toHaveCount(1)
        ->and($data['summary']['total'])->toBe(3)
        ->and($data['summary']['by_status']['draft']['count'])->toBe(2)
        ->and($data['summary']['lapsed'])->toBe(1)
        ->and($data['summary']['open_value'])->toEqual(300);

    $found = ($this->api)()->getJson('/api/v1/quotes?search=المياه')->json('data');
    expect($found['quotes'])->toHaveCount(1)->and($found['summary']['total'])->toBe(1);

    $late = ($this->api)()->getJson('/api/v1/quotes?validity=lapsed')->json('data.quotes');
    expect($late)->toHaveCount(1)->and($late[0]['is_past_validity'])->toBeTrue();

    expect(($this->api)()->getJson('/api/v1/quotes?open=1')->json('data.quotes'))->toHaveCount(3);
});

test('accepted quotes not yet ordered can be listed', function () {
    $ordered = Quote::find(($this->make)()->json('data.id'));
    $waiting = Quote::find(($this->make)()->json('data.id'));
    ($this->move)($ordered, 'accepted')->assertOk();
    ($this->move)($waiting, 'accepted')->assertOk();
    ($this->api)()->postJson("/api/v1/quotes/{$ordered->id}/convert-to-sales-order")->assertCreated();

    $data = ($this->api)()->getJson('/api/v1/quotes?status=accepted&converted=0')->json('data');

    expect(collect($data['quotes'])->pluck('id')->all())->toBe([$waiting->id])
        ->and($data['summary']['awaiting_conversion'])->toBe(1)
        ->and($data['summary']['converted'])->toBe(1)
        ->and($data['quotes'][0]['allowed_statuses'])->toBe(['sent']);
});
