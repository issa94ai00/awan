<?php

use App\Models\Role;
use App\Models\User;
use App\Models\Warehouse;

test('initial users have expected roles and dedicated warehouses', function () {
    $this->seed(\Database\Seeders\RoleSeeder::class);
    $this->seed(\Database\Seeders\UserSeeder::class);

    $expected = [
        'anas' => ['role' => 'sells', 'warehouse' => 'wh-anas'],
        'ahmad' => ['role' => 'sells', 'warehouse' => 'wh-ahmad'],
        'ayoub' => ['role' => 'sells', 'warehouse' => 'wh-ayoub'],
        'hasan' => ['role' => 'accountant', 'warehouse' => null],
        'ayad' => ['role' => 'marketer', 'warehouse' => null],
    ];

    foreach ($expected as $name => $meta) {
        $user = User::where('name', $name)->firstOrFail();

        expect($user->hasRole($meta['role']))->toBeTrue();

        if ($meta['warehouse']) {
            $warehouse = Warehouse::where('code', $meta['warehouse'])->firstOrFail();
            expect($warehouse->name)->toBe($meta['warehouse']);
            expect($user->employee?->warehouse_id)->toBe($warehouse->id);
            expect($warehouse->manager_id)->toBe($user->id);
        }
    }

    expect(Role::whereIn('name', ['sells', 'accountant', 'marketer'])->count())->toBe(3);
});
