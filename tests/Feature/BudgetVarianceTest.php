<?php

use App\Models\Budget;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\LedgerAccount;
use App\Models\Role;
use App\Models\User;
use App\Services\Accounting\LedgerPostingService;

/**
 * The figure set in advance, and what the year did against it.
 *
 * Every other report answers what happened; none says whether what happened
 * was meant to. An expense account showing 40,000 for a quarter is a fact with
 * no verdict — well under control, or a serious overrun — and only a budget
 * tells the two apart.
 */
beforeEach(function () {
    $this->admin = User::factory()->create([
        'is_admin' => true,
        'role_id' => Role::firstOrCreate(['name' => 'admin'], ['display_name' => 'admin'])->id,
    ]);

    $this->ledger = app(LedgerPostingService::class);
    $this->customer = Customer::create(['name' => 'عميل', 'email' => 'b@example.test', 'status' => 'active']);

    $this->sell = function (float $amount, string $date) {
        $invoice = Invoice::create([
            'invoice_number' => 'INV-'.uniqid(),
            'customer_id' => $this->customer->id,
            'status' => 'sent',
            'subtotal' => $amount,
            'tax' => 0,
            'total' => $amount,
            'due_amount' => $amount,
        ]);
        $invoice->forceFill(['created_at' => $date])->save();
        $this->ledger->postInvoice($invoice->refresh());
    };

    $this->spend = function (float $amount, string $date, int $id) {
        $this->ledger->postExpense((object) [
            'id' => $id,
            'amount' => $amount,
            'status' => 'paid',
            'category' => 'shipping',
            'expense_date' => $date,
            'expense_number' => 'EXP-'.$id,
            'description' => 'شحن',
            'currency' => 'SAR',
        ]);
    };

    $this->budget = function (array $lines, int $year = 2026): Budget {
        $budget = $this->actingAs($this->admin)->postJson('/api/v1/admin/accounting/budgets', [
            'name' => 'الموازنة التشغيلية',
            'fiscal_year' => $year,
        ])->assertCreated()->json('data');

        $this->actingAs($this->admin)
            ->postJson('/api/v1/admin/accounting/budgets/'.$budget['id'].'/lines', ['lines' => $lines])
            ->assertOk();

        return Budget::find($budget['id']);
    };

    $this->roleId = fn (string $role) => (int) LedgerAccount::where('posting_role', $role)->value('id');

    $this->variance = fn (Budget $budget, array $params = []) => $this->actingAs($this->admin)
        ->getJson('/api/v1/admin/accounting/budgets/'.$budget->id.'/variance?'.http_build_query($params))
        ->assertOk()->json('data');
});

test('a budget compares against what the ledger actually recorded', function () {
    $sales = ($this->roleId)('sales_revenue');

    $budget = ($this->budget)([
        ['account_id' => $sales, 'month' => 1, 'amount' => 1000],
        ['account_id' => $sales, 'month' => 2, 'amount' => 1000],
    ]);

    ($this->sell)(1200, '2026-01-15');
    ($this->sell)(600, '2026-02-10');

    $data = ($this->variance)($budget, ['from_month' => 1, 'to_month' => 2]);
    $row = collect($data['rows'])->firstWhere('account_id', $sales);

    expect((float) $row['budget'])->toBe(2000.0);
    expect((float) $row['actual'])->toBe(1800.0);
    expect((float) $row['difference'])->toBe(-200.0);
});

test('earning less than planned is unfavourable, spending less is not', function () {
    $sales = ($this->roleId)('sales_revenue');
    $shipping = ($this->roleId)('shipping_expense');

    $budget = ($this->budget)([
        ['account_id' => $sales, 'month' => 1, 'amount' => 1000],
        ['account_id' => $shipping, 'month' => 1, 'amount' => 500],
    ]);

    ($this->sell)(800, '2026-01-10');
    ($this->spend)(300, '2026-01-12', 501);

    $rows = collect(($this->variance)($budget, ['from_month' => 1, 'to_month' => 1])['rows']);

    // Both are 'under', and a single signed number would call both negative —
    // which is the reason the verdict is computed per side.
    expect($rows->firstWhere('account_id', $sales)['is_favourable'])->toBeFalse();
    expect($rows->firstWhere('account_id', $shipping)['is_favourable'])->toBeTrue();
});

test('only the months asked for are compared', function () {
    $sales = ($this->roleId)('sales_revenue');

    $budget = ($this->budget)([
        ['account_id' => $sales, 'month' => 1, 'amount' => 1000],
        ['account_id' => $sales, 'month' => 6, 'amount' => 1000],
    ]);

    ($this->sell)(400, '2026-01-20');
    ($this->sell)(900, '2026-06-20');

    $row = collect(($this->variance)($budget, ['from_month' => 1, 'to_month' => 1])['rows'])
        ->firstWhere('account_id', $sales);

    expect((float) $row['budget'])->toBe(1000.0);
    expect((float) $row['actual'])->toBe(400.0);
});

test('the report carries the bottom line both ways', function () {
    $sales = ($this->roleId)('sales_revenue');
    $shipping = ($this->roleId)('shipping_expense');

    $budget = ($this->budget)([
        ['account_id' => $sales, 'month' => 3, 'amount' => 2000],
        ['account_id' => $shipping, 'month' => 3, 'amount' => 800],
    ]);

    ($this->sell)(2500, '2026-03-05');
    ($this->spend)(1100, '2026-03-08', 502);

    $totals = ($this->variance)($budget, ['from_month' => 3, 'to_month' => 3])['totals'];

    expect((float) $totals['planned_result'])->toBe(1200.0);
    expect((float) $totals['actual_result'])->toBe(1400.0);
});

test('setting a month again corrects it rather than adding to it', function () {
    $sales = ($this->roleId)('sales_revenue');

    $budget = ($this->budget)([['account_id' => $sales, 'month' => 1, 'amount' => 1000]]);

    $this->actingAs($this->admin)
        ->postJson('/api/v1/admin/accounting/budgets/'.$budget->id.'/lines', [
            'lines' => [['account_id' => $sales, 'month' => 1, 'amount' => 1500]],
        ])->assertOk();

    expect($budget->annualFor($sales))->toBe(1500.0);
    expect($budget->lines()->count())->toBe(1);
});

test('a second budget of the same name and year is refused', function () {
    ($this->budget)([['account_id' => ($this->roleId)('sales_revenue'), 'month' => 1, 'amount' => 100]]);

    // A revision is a new budget, not a silent overwrite of figures that have
    // already been reported against.
    $this->actingAs($this->admin)->postJson('/api/v1/admin/accounting/budgets', [
        'name' => 'الموازنة التشغيلية',
        'fiscal_year' => 2026,
    ])->assertStatus(422);

    expect(Budget::count())->toBe(1);
});

test('an approved budget cannot be deleted', function () {
    $budget = ($this->budget)([['account_id' => ($this->roleId)('sales_revenue'), 'month' => 1, 'amount' => 100]]);

    $this->actingAs($this->admin)
        ->postJson('/api/v1/admin/accounting/budgets/'.$budget->id.'/approve')->assertOk();

    $this->actingAs($this->admin)
        ->deleteJson('/api/v1/admin/accounting/budgets/'.$budget->id)->assertStatus(422);

    expect(Budget::find($budget->id))->not->toBeNull();
});

test('budgets are refused to a sales account', function () {
    $sales = User::factory()->create([
        'role_id' => Role::firstOrCreate(['name' => 'sells'], ['display_name' => 'sells'])->id,
    ]);

    $this->actingAs($sales)
        ->getJson('/api/v1/admin/accounting/budgets')
        ->assertForbidden();
});
