<?php

use App\Models\Customer;
use App\Models\Employee;
use App\Models\Feature;
use App\Models\Product;
use App\Models\Quote;
use App\Models\Role;
use App\Models\User;

/**
 * A sales rep's quotes screen holds the quotes they wrote and those for their
 * own customers; another rep's clients stay out of reach. Admins see them all.
 */
beforeEach(function () {
    $sells = Role::firstOrCreate(
        ['name' => 'sells'],
        ['display_name' => 'مبيعات', 'guard_name' => 'web', 'is_active' => true]
    );
    $sales = Feature::firstOrCreate(['key' => 'sales'], ['name_ar' => 'المبيعات', 'name_en' => 'Sales', 'group' => 'sales', 'is_enabled' => true]);
    // The quote form picks customers through the POS endpoints.
    $pos = Feature::firstOrCreate(['key' => 'pos'], ['name_ar' => 'نقطة البيع', 'name_en' => 'POS', 'group' => 'sales', 'is_enabled' => true]);
    $sells->features()->syncWithoutDetaching([$sales->id, $pos->id]);

    $rep = function (string $suffix) use ($sells) {
        $user = User::factory()->create(['is_admin' => false, 'role_id' => $sells->id]);
        $user->roles()->sync([$sells->id]);
        $employee = Employee::create([
            'user_id' => $user->id,
            'first_name' => 'مندوب',
            'last_name' => $suffix,
            'email' => "rep-{$suffix}@test.com",
            'phone' => '+96650000'.random_int(1000, 9999),
            'position' => 'مندوب مبيعات',
            'hire_date' => now()->toDateString(),
            'status' => 'نشط',
        ]);

        return [$user, $employee];
    };

    [$this->rep1, $this->emp1] = $rep('one');
    [$this->rep2, $this->emp2] = $rep('two');
    $this->admin = User::factory()->admin()->create();

    // One customer through the legacy column, one through the link table.
    $this->ownByColumn = Customer::create(['name' => 'عميل العمود', 'phone' => '0999000301', 'employee_id' => $this->emp1->id]);
    $this->ownByLink = Customer::create(['name' => 'عميل الربط', 'phone' => '0999000302']);
    $this->ownByLink->customerEmployees()->attach($this->emp1->id, ['is_primary' => true]);
    $this->others = Customer::create(['name' => 'عميل آخر', 'phone' => '0999000303', 'employee_id' => $this->emp2->id]);

    $product = Product::factory()->create(['price' => 50]);
    $this->quoteFor = function (Customer $customer, User $by) use ($product) {
        $quote = Quote::create([
            'quote_number' => Quote::nextNumber(),
            'customer_id' => $customer->id,
            'status' => Quote::STATUS_DRAFT,
            'subtotal' => 100, 'tax' => 0, 'discount' => 0, 'total' => 100,
            'created_by' => $by->id,
        ]);
        $quote->items()->create(['product_id' => $product->id, 'quantity' => 2, 'unit_price' => 50, 'discount' => 0, 'tax' => 0]);

        return $quote;
    };

    $this->q1 = ($this->quoteFor)($this->ownByColumn, $this->admin);
    $this->q2 = ($this->quoteFor)($this->ownByLink, $this->admin);
    $this->q3 = ($this->quoteFor)($this->others, $this->admin);
    // Written by rep 1 for a customer who is not theirs: still theirs to see.
    $this->q4 = ($this->quoteFor)($this->others, $this->rep1);
});

test('a rep lists only their customers\' quotes and the ones they wrote', function () {
    $response = $this->actingAs($this->rep1, 'sanctum')->getJson('/api/v1/quotes')->assertOk();

    expect(collect($response->json('data.quotes'))->pluck('id')->sort()->values()->all())
        ->toBe(collect([$this->q1->id, $this->q2->id, $this->q4->id])->sort()->values()->all());
    expect($response->json('data.summary.total'))->toBe(3);
});

test('a rep cannot open or act on another rep\'s customer quote', function () {
    $api = $this->actingAs($this->rep1, 'sanctum');

    $api->getJson("/api/v1/quotes/{$this->q3->id}")->assertForbidden();
    $api->putJson("/api/v1/quotes/{$this->q3->id}/status", ['status' => 'sent'])->assertForbidden();
    $api->postJson("/api/v1/quotes/{$this->q3->id}/duplicate")->assertForbidden();
    $api->deleteJson("/api/v1/quotes/{$this->q3->id}")->assertForbidden();

    $api->getJson("/api/v1/quotes/{$this->q2->id}")->assertOk();
});

test('the other rep sees only their own side', function () {
    $ids = collect($this->actingAs($this->rep2, 'sanctum')->getJson('/api/v1/quotes')->json('data.quotes'))->pluck('id');

    expect($ids->sort()->values()->all())->toBe(collect([$this->q3->id, $this->q4->id])->sort()->values()->all());
});

test('an admin sees every quote', function () {
    $this->actingAs($this->admin, 'sanctum')->getJson('/api/v1/quotes')
        ->assertOk()->assertJsonPath('data.summary.total', 4);
});

test('a rep quotes their own customers only', function () {
    $api = $this->actingAs($this->rep1, 'sanctum');
    $body = fn (Customer $customer) => [
        'customer_id' => $customer->id,
        'items' => [['product_id' => $this->q1->items()->value('product_id'), 'quantity' => 1, 'unit_price' => 50]],
    ];

    $api->postJson('/api/v1/quotes', $body($this->ownByColumn))->assertCreated();
    $api->postJson('/api/v1/quotes', $body($this->ownByLink))->assertCreated();
    $api->postJson('/api/v1/quotes', $body($this->others))
        ->assertUnprocessable()->assertJsonValidationErrors('customer_id');

    // Moving one of their quotes over to another rep's customer is refused…
    $api->putJson("/api/v1/quotes/{$this->q1->id}", $body($this->others))
        ->assertUnprocessable()->assertJsonValidationErrors('customer_id');
    // …but a quote they already wrote for that customer can still be revised.
    $api->putJson("/api/v1/quotes/{$this->q4->id}", $body($this->others))->assertOk();
    // A copy is a new quote, so it is held to the same rule.
    $api->postJson("/api/v1/quotes/{$this->q4->id}/duplicate")->assertUnprocessable();
    $api->postJson("/api/v1/quotes/{$this->q1->id}/duplicate")->assertCreated();
});

test('an admin quotes any customer', function () {
    $this->actingAs($this->admin, 'sanctum')->postJson('/api/v1/quotes', [
        'customer_id' => $this->others->id,
        'items' => [['product_id' => $this->q1->items()->value('product_id'), 'quantity' => 1, 'unit_price' => 50]],
    ])->assertCreated();
});

test('the quote form picker offers a rep their own customers, and a customer they add is theirs', function () {
    $api = $this->actingAs($this->rep1, 'sanctum');

    $ids = collect($api->getJson('/api/v1/pos/customers?mine=1')->json('data.customers'))->pluck('id');
    expect($ids->sort()->values()->all())->toBe(collect([$this->ownByColumn->id, $this->ownByLink->id])->sort()->values()->all());

    // Without the flag the POS list is unchanged.
    expect(count($api->getJson('/api/v1/pos/customers')->json('data.customers')))->toBe(3);

    $id = $api->postJson('/api/v1/pos/customers', ['name' => 'عميل جديد', 'phone' => '0999000399'])->assertCreated()->json('data.id');
    expect(Customer::servedBy($this->emp1->id)->whereKey($id)->exists())->toBeTrue();
});
