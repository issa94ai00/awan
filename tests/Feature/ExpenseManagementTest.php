<?php

use App\Models\Customer;
use App\Models\Expense;
use App\Models\Invoice;
use App\Models\JournalEntryHeader;
use App\Models\Role;
use App\Models\User;

beforeEach(function () {
    $this->admin = User::factory()->create([
        'is_admin' => true,
        'role_id' => Role::firstOrCreate(['name' => 'admin'], ['display_name' => 'admin'])->id,
    ]);
});

test('expenses index returns paginated list with summary and category breakdown', function () {
    $c = Customer::create([
        'name' => 'شركة الأمل',
        'email' => 'amal@example.test',
        'status' => 'active',
    ]);
    $inv = Invoice::create([
        'customer_id' => $c->id,
        'invoice_number' => 'INV-TEST-999',
        'invoice_date' => now()->toDateString(),
        'due_date' => now()->addDays(7)->toDateString(),
        'status' => 'issued',
        'total' => 1000,
        'currency' => 'SAR',
    ]);

    $today = now()->toDateString();

    Expense::create([
        'expense_number' => 'EXP-000001',
        'description' => 'شحن بضائع للعميل',
        'amount' => 500,
        'category' => 'shipping',
        'status' => 'paid',
        'expense_date' => $today,
        'customer_id' => $c->id,
        'invoice_id' => $inv->id,
    ]);

    Expense::create([
        'expense_number' => 'EXP-000002',
        'description' => 'تغليف كراتين',
        'amount' => 200,
        'category' => 'packaging',
        'status' => 'pending',
        'expense_date' => $today,
    ]);

    $res = $this->actingAs($this->admin)->getJson('/api/v1/expenses');
    $res->assertOk()
        ->assertJsonStructure([
            'success',
            'data' => [
                'expenses',
                'summary' => [
                    'total',
                    'count',
                    'today',
                    'this_month',
                    'by_category',
                    'by_status',
                ],
                'pagination',
            ],
        ]);

    $data = $res->json('data');
    expect((float) $data['summary']['total'])->toEqual(700);
    expect($data['summary']['count'])->toBe(2);
    expect((float) $data['summary']['by_category']['shipping']['total'])->toEqual(500);
    expect((float) $data['summary']['by_category']['packaging']['total'])->toEqual(200);
    expect((float) $data['summary']['by_status']['paid']['total'])->toEqual(500);
    expect((float) $data['summary']['by_status']['pending']['total'])->toEqual(200);
});

test('expenses index filters by category, status, search term, and date', function () {
    Expense::create([
        'expense_number' => 'EXP-000010',
        'description' => 'شحن خاص',
        'amount' => 300,
        'category' => 'shipping',
        'status' => 'paid',
        'expense_date' => '2026-05-10',
    ]);

    Expense::create([
        'expense_number' => 'EXP-000011',
        'description' => 'تغليف عادي',
        'amount' => 150,
        'category' => 'packaging',
        'status' => 'pending',
        'expense_date' => '2026-05-12',
    ]);

    // Filter category
    $resCat = $this->actingAs($this->admin)->getJson('/api/v1/expenses?category=shipping');
    expect($resCat->json('data.expenses'))->toHaveCount(1);
    expect($resCat->json('data.expenses.0.expense_number'))->toBe('EXP-000010');

    // Filter status
    $resStatus = $this->actingAs($this->admin)->getJson('/api/v1/expenses?status=pending');
    expect($resStatus->json('data.expenses'))->toHaveCount(1);
    expect($resStatus->json('data.expenses.0.expense_number'))->toBe('EXP-000011');

    // Search
    $resSearch = $this->actingAs($this->admin)->getJson('/api/v1/expenses?search=شحن');
    expect($resSearch->json('data.expenses'))->toHaveCount(1);
    expect($resSearch->json('data.expenses.0.description'))->toBe('شحن خاص');

    // Date range
    $resDate = $this->actingAs($this->admin)->getJson('/api/v1/expenses?date_from=2026-05-11&date_to=2026-05-15');
    expect($resDate->json('data.expenses'))->toHaveCount(1);
    expect($resDate->json('data.expenses.0.expense_number'))->toBe('EXP-000011');
});

test('creating expense with status paid credits cash and links invoice/customer', function () {
    $c = Customer::create([
        'name' => 'عميل الشحن',
        'email' => 'ship@example.test',
        'status' => 'active',
    ]);
    $inv = Invoice::create([
        'customer_id' => $c->id,
        'invoice_number' => 'INV-SHIP-123',
        'invoice_date' => now()->toDateString(),
        'due_date' => now()->addDays(7)->toDateString(),
        'status' => 'issued',
        'total' => 500,
        'currency' => 'SAR',
    ]);

    $res = $this->actingAs($this->admin)->postJson('/api/v1/expenses', [
        'description' => 'شحن طلبية للزبون',
        'amount' => 250,
        'category' => 'shipping',
        'expense_date' => now()->toDateString(),
        'status' => 'paid',
        'invoice_id' => $inv->id,
    ]);

    $res->assertCreated();
    $created = $res->json('data');

    expect($created['status'])->toBe('paid');
    expect($created['customer_id'])->toBe($c->id);

    $entry = JournalEntryHeader::with('lines.ledgerAccount')
        ->where('posting_key', 'expense:'.$created['id'])
        ->first();

    expect($entry)->not->toBeNull();
    $creditLine = $entry->lines->firstWhere('credit', '>', 0);
    expect($creditLine->ledgerAccount->posting_role)->toBe('cash');
});

test('marking pending expense as paid reverses liability and credits cash', function () {
    $res = $this->actingAs($this->admin)->postJson('/api/v1/expenses', [
        'description' => 'مصاريف معالجة',
        'amount' => 400,
        'category' => 'handling',
        'expense_date' => now()->toDateString(),
        'status' => 'pending',
    ]);
    $created = $res->json('data');

    $originalEntry = JournalEntryHeader::with('lines.ledgerAccount')
        ->where('posting_key', 'expense:'.$created['id'])
        ->first();
    $origCredit = $originalEntry->lines->firstWhere('credit', '>', 0);
    expect($origCredit->ledgerAccount->posting_role)->toBe('accounts_payable');

    // Update status to paid
    $putRes = $this->actingAs($this->admin)->putJson('/api/v1/expenses/'.$created['id'], [
        'status' => 'paid',
    ]);
    $putRes->assertOk();
    expect($putRes->json('data.status'))->toBe('paid');

    $activeEntry = JournalEntryHeader::with('lines.ledgerAccount')
        ->where('posting_key', 'like', 'expense:'.$created['id'].'%')
        ->where('status', 'posted')
        ->latest('id')
        ->first();

    $newCredit = $activeEntry->lines->firstWhere('credit', '>', 0);
    expect($newCredit->ledgerAccount->posting_role)->toBe('cash');
});
