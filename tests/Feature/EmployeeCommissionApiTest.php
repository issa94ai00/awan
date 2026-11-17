<?php

use App\Models\Employee;
use App\Models\Invoice;
use App\Models\User;
use Laravel\Sanctum\Sanctum;

beforeEach(function () {
    $this->user = User::factory()->admin()->create();
    $this->token = $this->user->createToken('test-token')->plainTextToken;

    $this->employee = Employee::create([
        'name' => 'سامر الخطيب',
        'email' => 'samer@example.com',
        'phone' => '+963900000001',
        'position' => 'مندوب مبيعات',
        'department' => 'المبيعات',
        'hire_date' => now()->subYear()->toDateString(),
        'salary' => 3000,
        'status' => 'نشط',
    ]);
});

function makeInvoice(Employee $employee, float $total, string $status, $createdAt)
{
    $invoice = Invoice::create([
        'invoice_number' => 'INV-' . uniqid(),
        'assigned_employee_id' => $employee->id,
        'subtotal' => $total,
        'total' => $total,
        'status' => $status,
    ]);
    $invoice->created_at = $createdAt;
    $invoice->save();

    return $invoice;
}

test('commission calculate-sales sums only recognised revenue for the given month', function () {
    $month = now()->startOfMonth();

    makeInvoice($this->employee, 100000, Invoice::STATUS_CONFIRMED, $month->copy()->addDays(2));
    makeInvoice($this->employee, 50000, Invoice::STATUS_DELIVERED, $month->copy()->addDays(5));
    makeInvoice($this->employee, 20000, Invoice::STATUS_CANCELLED, $month->copy()->addDays(6));
    makeInvoice($this->employee, 999999, Invoice::STATUS_CONFIRMED, $month->copy()->subMonth());

    $response = $this->withHeader('Authorization', "Bearer {$this->token}")
        ->postJson('/api/v1/admin/employee-commissions/calculate-sales', [
            'employee_id' => $this->employee->id,
            'month' => $month->toDateString(),
        ]);

    $response->assertStatus(200)
        ->assertJsonPath('data.total_sales', 150000)
        ->assertJsonPath('data.invoice_count', 2);
});

test('commission record store computes balance, status and cumulative balance', function () {
    $month = now()->startOfMonth();
    makeInvoice($this->employee, 100000, Invoice::STATUS_CONFIRMED, $month->copy()->addDays(2));
    $auth = ['Authorization' => "Bearer {$this->token}"];

    $response = $this->postJson('/api/v1/admin/employee-commissions', [
        'employee_id' => $this->employee->id,
        'month' => $month->toDateString(),
        'commission_rate' => 5,
        'extra_expenses' => 200,
    ], $auth);

    $commissionId = $response->json('data.id');

    // Withdrawals are recorded in the ledger, in the base currency (USD),
    // and roll up into the record automatically.
    $this->postJson("/api/v1/admin/employee-commissions/{$commissionId}/withdrawals", [
        'withdrawn_at' => now()->toDateTimeString(),
        'currency_code' => 'USD',
        'amount' => 3000,
        'method' => 'cash',
    ], $auth);

    // 100000 * 5% = 5000; 5000 - 200 = 4800; 4800 - 3000 = 1800 (creditor)
    $response->assertStatus(201)
        ->assertJsonPath('data.total_sales', '100000.00000')
        ->assertJsonPath('data.commission_amount', 5000)
        ->assertJsonPath('data.net_due', 4800);

    $index = $this->getJson('/api/v1/admin/employee-commissions?employee_id=' . $this->employee->id, $auth);

    $index->assertStatus(200)
        ->assertJsonPath('data.0.balance', 1800)
        ->assertJsonPath('data.0.balance_status', 'creditor')
        ->assertJsonPath('data.0.cumulative_balance', 1800);
});

test('deleting a commission record hides it from the index and marks who deleted it', function () {
    $month = now()->startOfMonth();
    $auth = ['Authorization' => "Bearer {$this->token}"];

    $created = $this->postJson('/api/v1/admin/employee-commissions', [
        'employee_id' => $this->employee->id,
        'month' => $month->toDateString(),
        'commission_rate' => 5,
    ], $auth)->json('data');

    $this->deleteJson("/api/v1/admin/employee-commissions/{$created['id']}", [], $auth)
        ->assertStatus(200);

    $this->assertSoftDeleted('employee_commissions', ['id' => $created['id'], 'deleted_by' => $this->user->id]);

    $index = $this->getJson('/api/v1/admin/employee-commissions?employee_id=' . $this->employee->id, $auth);
    $index->assertStatus(200)->assertJsonCount(0, 'data');
});

test('deleting a commission record cascades a soft delete to its active withdrawals', function () {
    $month = now()->startOfMonth();
    $auth = ['Authorization' => "Bearer {$this->token}"];

    $created = $this->postJson('/api/v1/admin/employee-commissions', [
        'employee_id' => $this->employee->id,
        'month' => $month->toDateString(),
        'commission_rate' => 5,
    ], $auth)->json('data');

    $withdrawal = $this->postJson("/api/v1/admin/employee-commissions/{$created['id']}/withdrawals", [
        'withdrawn_at' => now()->toDateTimeString(),
        'currency_code' => 'USD',
        'amount' => 100,
        'method' => 'cash',
    ], $auth)->json('data');

    $this->deleteJson("/api/v1/admin/employee-commissions/{$created['id']}", [], $auth)
        ->assertStatus(200);

    $this->assertSoftDeleted('employee_commissions', ['id' => $created['id'], 'deleted_by' => $this->user->id]);
    $this->assertSoftDeleted('employee_commission_withdrawals', ['id' => $withdrawal['id'], 'deleted_by' => $this->user->id]);

    // The pre-delete total stays on the row as a snapshot for the trash view.
    $this->assertDatabaseHas('employee_commissions', ['id' => $created['id'], 'withdrawals' => 100]);
});

test('an admin can list and restore a trashed commission record; a deleted month can be recreated', function () {
    $month = now()->startOfMonth();
    $auth = ['Authorization' => "Bearer {$this->token}"];

    $created = $this->postJson('/api/v1/admin/employee-commissions', [
        'employee_id' => $this->employee->id,
        'month' => $month->toDateString(),
        'commission_rate' => 5,
    ], $auth)->json('data');

    $this->deleteJson("/api/v1/admin/employee-commissions/{$created['id']}", [], $auth);

    // The (employee_id, month) unique DB constraint was dropped for soft
    // delete, so the same month can be re-created while the old row stays
    // in the trash for audit.
    $recreated = $this->postJson('/api/v1/admin/employee-commissions', [
        'employee_id' => $this->employee->id,
        'month' => $month->toDateString(),
        'commission_rate' => 7,
    ], $auth);
    $recreated->assertStatus(201);
    expect($recreated->json('data.id'))->not->toBe($created['id']);

    $trashed = $this->getJson('/api/v1/admin/employee-commissions/trashed', $auth);
    $trashed->assertStatus(200)->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.id', $created['id']);

    $this->putJson("/api/v1/admin/employee-commissions/{$created['id']}/restore", [], $auth)
        ->assertStatus(200);

    $this->assertDatabaseHas('employee_commissions', ['id' => $created['id'], 'deleted_at' => null, 'deleted_by' => null]);
});

test('a non-admin cannot list or restore trashed commission records', function () {
    $month = now()->startOfMonth();
    $auth = ['Authorization' => "Bearer {$this->token}"];

    $created = $this->postJson('/api/v1/admin/employee-commissions', [
        'employee_id' => $this->employee->id,
        'month' => $month->toDateString(),
        'commission_rate' => 5,
    ], $auth)->json('data');

    $this->deleteJson("/api/v1/admin/employee-commissions/{$created['id']}", [], $auth);

    // Sanctum's RequestGuard memoizes the user it resolves from the first
    // Authorization header it sees in a test, so a second request with a
    // different Bearer token would otherwise still authenticate as the
    // first user. Sanctum::actingAs() sets the guard's user directly and
    // sidesteps that cache.
    $staff = User::factory()->create(['is_admin' => false]);
    Sanctum::actingAs($staff);

    $this->getJson('/api/v1/admin/employee-commissions/trashed')->assertStatus(403);
    $this->putJson("/api/v1/admin/employee-commissions/{$created['id']}/restore")->assertStatus(403);
});

test('commission record balance goes negative when withdrawals exceed net due', function () {
    $month = now()->startOfMonth();
    makeInvoice($this->employee, 10000, Invoice::STATUS_CONFIRMED, $month->copy()->addDays(2));
    $auth = ['Authorization' => "Bearer {$this->token}"];

    $response = $this->postJson('/api/v1/admin/employee-commissions', [
        'employee_id' => $this->employee->id,
        'month' => $month->toDateString(),
        'commission_rate' => 5,
    ], $auth);

    $commissionId = $response->json('data.id');

    $this->postJson("/api/v1/admin/employee-commissions/{$commissionId}/withdrawals", [
        'withdrawn_at' => now()->toDateTimeString(),
        'currency_code' => 'USD',
        'amount' => 3000,
        'method' => 'cash',
    ], $auth);

    // 10000 * 5% = 500; withdrawals 3000 => balance -2500 (debtor)
    $index = $this->getJson('/api/v1/admin/employee-commissions?employee_id=' . $this->employee->id, $auth);

    $index->assertStatus(200)
        ->assertJsonPath('data.0.balance', -2500)
        ->assertJsonPath('data.0.balance_status', 'debtor');
});

test('commission calculate-sales deducts returns (credit notes) from gross sales to compute net sales', function () {
    $month = now()->startOfMonth();
    $customer = \App\Models\Customer::create(['name' => 'عميل اختبار', 'phone' => '0911111111']);

    $inv1 = makeInvoice($this->employee, 100000, Invoice::STATUS_CONFIRMED, $month->copy()->addDays(2));
    $inv1->update(['customer_id' => $customer->id]);

    $inv2 = makeInvoice($this->employee, 50000, Invoice::STATUS_DELIVERED, $month->copy()->addDays(5));
    $inv2->update(['customer_id' => $customer->id]);

    // Active credit note on inv1 within the month
    \App\Models\CreditNote::create([
        'credit_note_number' => 'CN-' . uniqid(),
        'customer_id' => $customer->id,
        'invoice_id' => $inv1->id,
        'issue_date' => $month->copy()->addDays(10)->toDateString(),
        'subtotal' => 25000,
        'total' => 25000,
        'status' => \App\Models\CreditNote::STATUS_ISSUED,
    ]);

    // Cancelled credit note - should NOT be deducted
    \App\Models\CreditNote::create([
        'credit_note_number' => 'CN-' . uniqid(),
        'customer_id' => $customer->id,
        'invoice_id' => $inv1->id,
        'issue_date' => $month->copy()->addDays(12)->toDateString(),
        'subtotal' => 10000,
        'total' => 10000,
        'status' => \App\Models\CreditNote::STATUS_CANCELLED,
    ]);

    // Another employee's invoice and return - should NOT be deducted
    $otherRep = Employee::create([
        'name' => 'مندوب آخر',
        'email' => 'other@example.com',
        'phone' => '+963900000002',
        'status' => 'نشط',
    ]);
    $otherInv = makeInvoice($otherRep, 80000, Invoice::STATUS_CONFIRMED, $month->copy()->addDays(3));
    \App\Models\CreditNote::create([
        'credit_note_number' => 'CN-' . uniqid(),
        'customer_id' => $customer->id,
        'invoice_id' => $otherInv->id,
        'issue_date' => $month->copy()->addDays(15)->toDateString(),
        'subtotal' => 30000,
        'total' => 30000,
        'status' => \App\Models\CreditNote::STATUS_ISSUED,
    ]);

    $response = $this->withHeader('Authorization', "Bearer {$this->token}")
        ->postJson('/api/v1/admin/employee-commissions/calculate-sales', [
            'employee_id' => $this->employee->id,
            'month' => $month->toDateString(),
        ]);

    // Gross = 100000 + 50000 = 150000
    // Returns = 25000 (cancelled 10000 & other rep's 30000 ignored)
    // Net = 125000
    $response->assertStatus(200)
        ->assertJsonPath('data.gross_sales', 150000)
        ->assertJsonPath('data.total_returns', 25000)
        ->assertJsonPath('data.net_sales', 125000)
        ->assertJsonPath('data.total_sales', 125000)
        ->assertJsonPath('data.invoice_count', 2)
        ->assertJsonPath('data.return_count', 1);
});

test('storing commission record with returns calculates commission on net sales', function () {
    $month = now()->startOfMonth();
    $customer = \App\Models\Customer::create(['name' => 'عميل اختبار 2', 'phone' => '0922222222']);

    $inv = makeInvoice($this->employee, 100000, Invoice::STATUS_CONFIRMED, $month->copy()->addDays(2));
    $inv->update(['customer_id' => $customer->id]);

    \App\Models\CreditNote::create([
        'credit_note_number' => 'CN-' . uniqid(),
        'customer_id' => $customer->id,
        'invoice_id' => $inv->id,
        'issue_date' => $month->copy()->addDays(5)->toDateString(),
        'subtotal' => 20000,
        'total' => 20000,
        'status' => \App\Models\CreditNote::STATUS_ISSUED,
    ]);

    $auth = ['Authorization' => "Bearer {$this->token}"];

    // Commission rate: 10%
    // Gross: 100000, Returns: 20000, Net: 80000
    // Commission amount should be 80000 * 10% = 8000 (NOT 10000!)
    $response = $this->postJson('/api/v1/admin/employee-commissions', [
        'employee_id' => $this->employee->id,
        'month' => $month->toDateString(),
        'commission_rate' => 10,
        'extra_expenses' => 500,
        'monthly_target' => 100000,
    ], $auth);

    $response->assertStatus(201)
        ->assertJsonPath('data.gross_sales', 100000)
        ->assertJsonPath('data.total_returns', 20000)
        ->assertJsonPath('data.net_sales', 80000)
        ->assertJsonPath('data.commission_amount', 8000)
        ->assertJsonPath('data.net_due', 7500)
        ->assertJsonPath('data.achievement_rate', 80);
});
