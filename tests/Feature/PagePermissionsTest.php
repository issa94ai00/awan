<?php

use App\Models\Feature;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use App\Support\PagePermissions;
use Database\Seeders\RoleSeeder;
use Spatie\Permission\PermissionRegistrar;

/**
 * Every admin sidebar page has a permission of its own.
 *
 * The catalogue lives twice — app/Support/PagePermissions.php for the server
 * and resources/js/config/pagePermissions.js for the sidebar and router — so
 * the first tests read the SPA sources and hold the three (sidebar, JS map,
 * PHP catalogue) together. The last ones pin down the rollout grant: nobody
 * loses a page they could open before.
 */
function jsPagePermissions(): array
{
    $source = file_get_contents(resource_path('js/config/pagePermissions.js'));
    preg_match_all("/\\['(\\/admin[^']*)',\\s*(\\[[^\\]]*\\]|'[^']*')\\]/", $source, $matches, PREG_SET_ORDER);

    return collect($matches)->mapWithKeys(function ($m) {
        preg_match_all("/'([^']+)'/", $m[2], $names);

        return [$m[1] => $names[1]];
    })->all();
}

function sidebarPaths(): array
{
    $source = file_get_contents(resource_path('js/components/admin/AdminSidebar.vue'));
    preg_match_all("/path: '(\\/admin[^']*)'/", $source, $matches);

    return array_values(array_unique($matches[1]));
}

it('gives every sidebar page a permission', function () {
    $map = jsPagePermissions();
    // The landing page stays open to every staff account; Features
    // Management is master-only rather than permission-based.
    $open = ['/admin/dashboard', '/admin/features'];

    $missing = collect(sidebarPaths())
        ->reject(fn ($path) => in_array($path, $open, true) || isset($map[$path]))
        ->values()
        ->all();

    expect($missing)->toBe([]);
});

it('keeps the SPA map and the server catalogue in step', function () {
    $fromJs = collect(jsPagePermissions())->map(fn ($names) => $names[0])->sort()->all();
    $fromPhp = collect(PagePermissions::PAGES)->mapWithKeys(fn ($page, $name) => [$page[3] => $name])->sort()->all();

    expect($fromJs)->toBe($fromPhp);
});

it('seeds the page permissions and hands them all to the super roles', function () {
    $this->seed(RoleSeeder::class);

    expect(Permission::whereIn('name', PagePermissions::names())->count())->toBe(count(PagePermissions::PAGES))
        ->and(Role::findByName('admin', 'web')->hasPermissionTo('wms.bins.view'))->toBeTrue();
});

it('grants each role the pages of the modules it already had', function () {
    $this->seed(RoleSeeder::class);
    $accountant = Role::findByName('accountant', 'web');
    $accountant->features()->syncWithoutDetaching(Feature::whereIn('key', ['accounting', 'system'])->pluck('id'));
    $accountant->revokePermissionTo('reports.view');

    (require database_path('migrations/2026_10_16_000001_add_page_permissions.php'))->up();
    app(PermissionRegistrar::class)->forgetCachedPermissions();

    $user = User::factory()->create(['is_admin' => false, 'role_id' => $accountant->id])->fresh();

    expect($user->hasPermission('accounting.journal.view'))->toBeTrue()
        ->and($user->hasPermission('accounting.view'))->toBeTrue()
        ->and($user->hasPermission('currencies.view'))->toBeTrue()
        // Pages of a module the role does not hold stay closed...
        ->and($user->hasPermission('wms.bins.view'))->toBeFalse()
        // ...and so do the access screens, which the API checks as well.
        ->and($user->hasPermission('roles.view'))->toBeFalse()
        ->and($user->hasPermission('users.view'))->toBeFalse();
});
