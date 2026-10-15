<?php

use App\Models\Employee;
use App\Models\User;

/**
 * A sales rep may look at the stock but not change it. The inventory screen
 * hides its actions for them; these endpoints refuse them as well, so the
 * buttons are not the only thing standing in the way.
 */
beforeEach(function () {
    $this->rep = User::factory()->operator()->create();

    Employee::create([
        'user_id' => $this->rep->id,
        'first_name' => 'مندوب',
        'last_name' => 'المبيعات',
        'email' => 'rep-stock@example.test',
        'position' => 'مندوب مبيعات',
        'department' => 'المبيعات',
        'hire_date' => now()->subYear()->toDateString(),
        'status' => 'نشط',
    ]);
});

it('shows a rep the stock', function () {
    $this->actingAs($this->rep)->getJson('/api/v1/admin/inventory/stock')->assertOk();
    $this->actingAs($this->rep)->getJson('/api/v1/admin/inventory/summary')->assertOk();
});

it('refuses a rep every change to the stock', function (string $method, string $uri) {
    $this->actingAs($this->rep)->json($method, $uri)->assertForbidden();
})->with([
    'movement' => ['POST', '/api/v1/admin/inventory/movements'],
    'transfer' => ['POST', '/api/v1/admin/inventory/transfer'],
    'import' => ['POST', '/api/v1/admin/inventory/import'],
    'export' => ['GET', '/api/v1/admin/inventory/export'],
    'costing method' => ['POST', '/api/v1/admin/inventory/costing-method'],
]);

it('still lets other staff record a movement', function () {
    // Reaches validation rather than the sales-rep gate.
    $this->actingAs(User::factory()->operator()->create())
        ->postJson('/api/v1/admin/inventory/movements')
        ->assertUnprocessable();
});
