<?php

use App\Models\Role;
use App\Models\User;

/**
 * Warehouse administration is for admins; the warehouse *list* is not.
 *
 * Two things made this worth pinning down.
 *
 * The first is the split itself. Six screens outside WMS — the sales, financial,
 * inventory and product reports, plus the warehouse analytics — read the
 * warehouse list to fill a filter, and those screens are open to roles that
 * have no business editing warehouses. Gating the read with the rest would have
 * emptied those filters and made the reports look broken.
 *
 * The second is why the first attempt at this gate did nothing. The WMS routes
 * were declared twice — once in a group under `/admin`, and again in a block
 * labelled "Public Routes" — and Laravel keeps only the last route registered
 * for a given method and URI. So every URI the second block repeated silently
 * replaced the guarded one. `/wms/dashboard`, declared once, correctly returned
 * 403; `/wms/bins`, declared twice, still returned 200. These tests would have
 * caught that immediately.
 */
function wmsUser(?string $roleName, bool $isAdmin = false): User
{
    $roleId = $roleName === null
        ? null
        : Role::firstOrCreate(['name' => $roleName], ['display_name' => $roleName])->id;

    return User::factory()->create(['is_admin' => $isAdmin, 'role_id' => $roleId]);
}

dataset('warehouse administration', [
    'dashboard' => ['GET', '/api/v1/admin/wms/dashboard'],
    'bins' => ['GET', '/api/v1/admin/wms/bins'],
    'picking lists' => ['GET', '/api/v1/admin/wms/picking-lists'],
    'packing lists' => ['GET', '/api/v1/admin/wms/packing-lists'],
    'cycle counts' => ['GET', '/api/v1/admin/wms/cycle-counts'],
    'stats' => ['GET', '/api/v1/admin/wms/stats'],
    'assignments' => ['GET', '/api/v1/admin/wms/assignments'],
]);

it('refuses warehouse administration to a sales account', function (string $method, string $uri) {
    $this->actingAs(wmsUser('sells'))
        ->json($method, $uri)
        ->assertForbidden();
})->with('warehouse administration');

it('allows warehouse administration to an admin', function (string $method, string $uri) {
    $this->actingAs(wmsUser('admin', isAdmin: true))
        ->json($method, $uri)
        ->assertOk();
})->with('warehouse administration');

it('refuses creating or deleting a warehouse to a sales account', function () {
    $sales = wmsUser('sells');

    // 403, not 422: refused before the payload is even considered. A validation
    // error here would mean the request had reached the controller.
    $this->actingAs($sales)
        ->postJson('/api/v1/admin/wms/warehouses', ['name' => 'X', 'code' => 'X'])
        ->assertForbidden();

    $this->actingAs($sales)
        ->deleteJson('/api/v1/admin/wms/warehouses/1')
        ->assertForbidden();
});

/**
 * The deliberate exception. If this ever starts returning 403, six report
 * screens lose their warehouse filter.
 */
it('keeps the warehouse list readable by every signed-in role', function () {
    foreach (['sells', 'accountant', 'marketer'] as $role) {
        $this->actingAs(wmsUser($role))
            ->getJson('/api/v1/admin/wms/warehouses')
            ->assertOk();
    }
});

it('still refuses the warehouse list to an anonymous caller', function () {
    $this->getJson('/api/v1/admin/wms/warehouses')->assertUnauthorized();
});

/**
 * Guards against the duplicate-registration trap that defeated the first
 * attempt: if a URI is declared twice, the later declaration wins and may not
 * carry the gate.
 */
it('registers each WMS route exactly once', function () {
    $seen = [];
    $duplicates = [];

    foreach (Illuminate\Support\Facades\Route::getRoutes() as $route) {
        if (! str_contains($route->uri(), 'admin/wms')) {
            continue;
        }

        foreach ($route->methods() as $method) {
            $key = $method . ' ' . $route->uri();

            if (isset($seen[$key])) {
                $duplicates[] = $key;
            }

            $seen[$key] = true;
        }
    }

    expect($duplicates)->toBe([]);
});
