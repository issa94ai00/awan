<?php

use App\Models\Currency;
use App\Models\CurrencyRate;
use App\Models\Employee;
use App\Models\User;

beforeEach(function () {
    $this->user = User::factory()->admin()->create();
    $this->token = $this->user->createToken('test-token')->plainTextToken;

    // The currencies migration itself seeds USD (base), SYP and SAR, so the
    // test only has to make sure they are the way this suite needs them.
    $this->base = Currency::updateOrCreate(['code' => 'USD'], [
        'name_ar' => 'دولار', 'name_en' => 'US Dollar',
        'symbol' => '$', 'decimal_places' => 2, 'is_base' => true, 'is_active' => true,
    ]);
    $this->sar = Currency::updateOrCreate(['code' => 'SAR'], [
        'name_ar' => 'ريال', 'name_en' => 'Saudi Riyal',
        'symbol' => 'SAR', 'decimal_places' => 2, 'is_base' => false, 'is_active' => true,
    ]);
    // 1 USD = 3.75 SAR
    CurrencyRate::create([
        'currency_id' => $this->sar->id, 'base_code' => 'USD', 'rate' => 3.75,
        'effective_at' => now()->subDays(10),
    ]);

    $this->employee = Employee::create([
        'name' => 'ريم سلطان',
        'email' => 'reem@example.com',
        'phone' => '+963900000002',
        'position' => 'مندوبة مبيعات',
        'department' => 'المبيعات',
        'hire_date' => now()->subYear()->toDateString(),
        'salary' => 3000,
        'status' => 'نشط',
    ]);

    $this->commission = $this->postJson('/api/v1/admin/employee-commissions', [
        'employee_id' => $this->employee->id,
        'month' => now()->startOfMonth()->toDateString(),
        'commission_rate' => 5,
    ], ['Authorization' => "Bearer {$this->token}"])->json('data');
});

function withdrawalUrl($id, $suffix = '')
{
    return "/api/v1/admin/employee-commissions/{$id}/withdrawals{$suffix}";
}

test('a withdrawal in the base currency needs no conversion', function () {
    $response = $this->withHeader('Authorization', "Bearer {$this->token}")
        ->postJson(withdrawalUrl($this->commission['id']), [
            'withdrawn_at' => now()->toDateTimeString(),
            'currency_code' => 'USD',
            'amount' => 500,
            'method' => 'cash',
            'reason' => 'سلفة',
        ]);

    $response->assertStatus(201)
        ->assertJsonPath('data.base_amount', '500.00')
        ->assertJsonPath('data.exchange_rate', '1.00000000');

    $this->assertDatabaseHas('employee_commissions', [
        'id' => $this->commission['id'],
        'withdrawals' => 500,
    ]);
});

test('a withdrawal in a foreign currency converts using the recorded rate', function () {
    $response = $this->withHeader('Authorization', "Bearer {$this->token}")
        ->postJson(withdrawalUrl($this->commission['id']), [
            'withdrawn_at' => now()->toDateTimeString(),
            'currency_code' => 'sar',
            'amount' => 375,
            'method' => 'bank',
        ]);

    // 375 SAR / 3.75 = 100 USD
    $response->assertStatus(201)
        ->assertJsonPath('data.currency_code', 'SAR')
        ->assertJsonPath('data.base_amount', '100.00');

    $this->assertDatabaseHas('employee_commissions', [
        'id' => $this->commission['id'],
        'withdrawals' => 100,
    ]);
});

test('an explicit exchange rate overrides the recorded one', function () {
    // Street rate of 4.00 instead of the recorded 3.75
    $response = $this->withHeader('Authorization', "Bearer {$this->token}")
        ->postJson(withdrawalUrl($this->commission['id']), [
            'withdrawn_at' => now()->toDateTimeString(),
            'currency_code' => 'SAR',
            'amount' => 400,
            'exchange_rate' => 4,
            'method' => 'cash',
        ]);

    $response->assertStatus(201)
        ->assertJsonPath('data.base_amount', '100.00');
});

test('a currency with no rate on file is refused rather than guessed', function () {
    Currency::updateOrCreate(['code' => 'EGP'], [
        'name_ar' => 'جنيه', 'name_en' => 'Egyptian Pound',
        'symbol' => 'EGP', 'decimal_places' => 2, 'is_base' => false, 'is_active' => true,
    ]);

    $response = $this->withHeader('Authorization', "Bearer {$this->token}")
        ->postJson(withdrawalUrl($this->commission['id']), [
            'withdrawn_at' => now()->toDateTimeString(),
            'currency_code' => 'EGP',
            'amount' => 100,
            'method' => 'cash',
        ]);

    $response->assertStatus(422);
});

test('the currency breakdown groups transactions and deleting one resyncs the total', function () {
    $auth = ['Authorization' => "Bearer {$this->token}"];

    $usd = $this->postJson(withdrawalUrl($this->commission['id']), [
        'withdrawn_at' => now()->toDateTimeString(), 'currency_code' => 'USD', 'amount' => 200, 'method' => 'cash',
    ], $auth)->json('data');

    $this->postJson(withdrawalUrl($this->commission['id']), [
        'withdrawn_at' => now()->toDateTimeString(), 'currency_code' => 'SAR', 'amount' => 375, 'method' => 'cash',
    ], $auth);

    $index = $this->getJson(withdrawalUrl($this->commission['id']), $auth);
    $index->assertStatus(200)
        ->assertJsonCount(2, 'data.transactions')
        ->assertJsonPath('data.total_base_amount', 300);

    $breakdown = collect($index->json('data.breakdown'))->keyBy('currency_code');
    expect($breakdown['USD']['total_base_amount'])->toBe(200);
    expect($breakdown['SAR']['total_base_amount'])->toBe(100);

    $this->deleteJson(withdrawalUrl($this->commission['id'], "/{$usd['id']}"), [], $auth)
        ->assertStatus(200);

    $this->assertDatabaseHas('employee_commissions', [
        'id' => $this->commission['id'],
        'withdrawals' => 100,
    ]);
});

test('the monthly statement balance reflects the converted withdrawal total', function () {
    // 10000 * 5% = 500 commission; withdraw 375 SAR (=100 USD) => balance 400 creditor
    $this->postJson(withdrawalUrl($this->commission['id']), [
        'withdrawn_at' => now()->toDateTimeString(), 'currency_code' => 'SAR', 'amount' => 375, 'method' => 'cash',
    ], ['Authorization' => "Bearer {$this->token}"]);

    $updated = $this->withHeader('Authorization', "Bearer {$this->token}")
        ->putJson("/api/v1/admin/employee-commissions/{$this->commission['id']}", [
            'commission_rate' => 5,
        ]);

    // total_sales is 0 in this test (no invoices), so commission is 0 and
    // balance is simply the negative of the converted withdrawal.
    $updated->assertStatus(200)
        ->assertJsonPath('data.withdrawals', '100.00')
        ->assertJsonPath('data.balance', -100)
        ->assertJsonPath('data.balance_status', 'debtor');
});
