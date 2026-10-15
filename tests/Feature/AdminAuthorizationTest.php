<?php

use App\Models\Feature;
use App\Models\Role;
use App\Models\Setting;
use App\Models\User;

/**
 * Who may reach the sensitive parts of the admin API.
 *
 * Being signed in used to be the entire check. A sales account could read the
 * trial balance, the ledger, every journal entry and the payroll, and could
 * save the system settings — including the base currency the whole ledger is
 * kept in. Verified before the fix: `POST /api/v1/settings` returned 200 from a
 * non-admin and the new value was actually stored.
 *
 * The two directions matter equally here. A gate that only refuses is half a
 * fix; these also pin down that the accountant keeps their books and the admin
 * keeps everything, so the change cannot quietly lock the owner out.
 */
function userWithRole(?string $roleName, bool $isAdmin = false): User
{
    $roleId = null;

    if ($roleName !== null) {
        $role = Role::firstOrCreate(['name' => $roleName], ['display_name' => $roleName]);
        $roleId = $role->id;

        // The modules each role holds on the Features Management screen in
        // production; the module gate runs before the checks tested here.
        $modules = [
            'sells' => ['dashboard', 'content', 'sales', 'inventory', 'pos', 'crm'],
            'accountant' => ['dashboard', 'accounting', 'bi_analytics', 'reports'],
            'marketer' => ['dashboard', 'content', 'crm', 'inquiries', 'reports'],
        ][$roleName] ?? [];
        $role->features()->syncWithoutDetaching(Feature::whereIn('key', $modules)->pluck('id'));
    }

    return User::factory()->create([
        'is_admin' => $isAdmin,
        'role_id' => $roleId,
    ]);
}

dataset('accounting endpoints', [
    'trial balance' => ['/api/v1/admin/accounting/trial-balance'],
    'journal entries' => ['/api/v1/admin/accounting/journal-entries'],
    'ledger accounts' => ['/api/v1/admin/accounting/ledger-accounts'],
    'income statement' => ['/api/v1/admin/accounting/income-statement'],
    'balance sheet' => ['/api/v1/admin/accounting/balance-sheet'],
]);

it('refuses the books to a sales account', function (string $uri) {
    $this->actingAs(userWithRole('sells'))
        ->getJson($uri)
        ->assertForbidden();
})->with('accounting endpoints');

it('opens the books to the accountant', function (string $uri) {
    $this->actingAs(userWithRole('accountant'))
        ->getJson($uri)
        ->assertOk();
})->with('accounting endpoints');

it('opens the books to an admin', function (string $uri) {
    $this->actingAs(userWithRole('admin', isAdmin: true))
        ->getJson($uri)
        ->assertOk();
})->with('accounting endpoints');

it('refuses a settings change from a sales account, and does not store it', function () {
    Setting::set('site_name', 'Original Name');

    $this->actingAs(userWithRole('sells'))
        ->postJson('/api/v1/settings', ['settings' => ['site_name' => 'CHANGED']])
        ->assertForbidden();

    // The regression that mattered: previously this came back 200 and stuck.
    expect(Setting::where('key', 'site_name')->value('value'))->toBe('Original Name');
});

it('refuses a settings change from the accountant too', function () {
    // Deliberately narrower than the books: the base currency lives here.
    $this->actingAs(userWithRole('accountant'))
        ->postJson('/api/v1/settings', ['settings' => ['site_name' => 'CHANGED']])
        ->assertForbidden();
});

it('lets an admin change settings', function () {
    Setting::set('site_name', 'Original Name');

    $this->actingAs(userWithRole('admin', isAdmin: true))
        ->postJson('/api/v1/settings', ['settings' => ['site_name' => 'New Name']])
        ->assertOk();

    expect(Setting::where('key', 'site_name')->value('value'))->toBe('New Name');
});

it('keeps staff records to admins', function () {
    $this->actingAs(userWithRole('sells'))
        ->getJson('/api/v1/admin/employees')
        ->assertForbidden();

    // Salary sits on the employee record, so the accountant is not enough here.
    $this->actingAs(userWithRole('accountant'))
        ->getJson('/api/v1/admin/employees')
        ->assertForbidden();

    $this->actingAs(userWithRole('admin', isAdmin: true))
        ->getJson('/api/v1/admin/employees')
        ->assertOk();
});

it('keeps payroll to the accountant and the admin', function () {
    $this->actingAs(userWithRole('sells'))
        ->getJson('/api/v1/payrolls')
        ->assertForbidden();

    $this->actingAs(userWithRole('accountant'))
        ->getJson('/api/v1/payrolls')
        ->assertOk();
});

it('keeps currency management to the accountant and the admin', function () {
    $this->actingAs(userWithRole('sells'))
        ->getJson('/api/v1/admin/currencies')
        ->assertForbidden();

    $this->actingAs(userWithRole('accountant'))
        ->getJson('/api/v1/admin/currencies')
        ->assertOk();
});

it('keeps the profit and cash-flow analytics to the accountant', function () {
    $this->actingAs(userWithRole('sells'))
        ->getJson('/api/v1/analytics/financial/profit-loss')
        ->assertForbidden();

    $this->actingAs(userWithRole('accountant'))
        ->getJson('/api/v1/analytics/financial/profit-loss')
        ->assertOk();
});

dataset('admin-only groups', [
    'purchase orders' => ['/api/v1/admin/purchase-orders'],
    'suppliers' => ['/api/v1/admin/suppliers'],
    'workflows' => ['/api/v1/workflows'],
    'audit statistics' => ['/api/v1/audit/statistics'],
    'notification templates' => ['/api/v1/notifications/templates'],
]);

it('keeps the admin-only groups to admins', function (string $uri) {
    $this->actingAs(userWithRole('sells'))->getJson($uri)->assertForbidden();
    $this->actingAs(userWithRole('accountant'))->getJson($uri)->assertForbidden();
    $this->actingAs(userWithRole('admin', isAdmin: true))->getJson($uri)->assertOk();
})->with('admin-only groups');

/**
 * Personal notification endpoints stay open. The header polls the unread count
 * on every page for every role, so gating the whole notifications prefix would
 * have broken the chrome of the entire admin.
 */
it('leaves personal notification endpoints open to every role', function () {
    $sales = userWithRole('sells');

    $this->actingAs($sales)->getJson('/api/v1/notifications/unread-count')->assertOk();
    $this->actingAs($sales)->getJson('/api/v1/notifications/preferences')->assertOk();
});

it('leaves operational work open to the staff who do it', function () {
    $sales = userWithRole('sells');

    // The gate is aimed at the books and the payroll, not at everyday work —
    // narrowing these would stop the sales team using the system at all.
    $this->actingAs($sales)->getJson('/api/v1/sales-orders')->assertOk();
    $this->actingAs($sales)->getJson('/api/v1/admin/products')->assertOk();
});

/**
 * Every role lands on a dashboard. A sales rep's is built from their own
 * business (DashboardController::stats), so the company-wide sales trend under
 * `/analytics` is closed to them; other roles still read it.
 */
it('gives every role a dashboard, and keeps company analytics from sales reps', function () {
    $this->actingAs(userWithRole('sells'))
        ->getJson('/api/v1/dashboard/stats')
        ->assertOk();

    $this->actingAs(userWithRole('sells'))
        ->getJson('/api/v1/analytics/sales/trend?days=30&group_by=day')
        ->assertForbidden();

    $this->actingAs(userWithRole('accountant'))
        ->getJson('/api/v1/analytics/sales/trend?days=30&group_by=day')
        ->assertOk();

    $this->actingAs(userWithRole('marketer'))
        ->getJson('/api/v1/dashboard/stats')
        ->assertOk();
});

it('still refuses everything to an anonymous caller', function () {
    $this->getJson('/api/v1/admin/accounting/trial-balance')->assertUnauthorized();
    $this->postJson('/api/v1/settings', ['settings' => []])->assertUnauthorized();
});
