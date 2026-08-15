<?php

namespace Database\Seeders;

use App\Models\Employee;
use App\Models\Role;
use App\Models\User;
use App\Models\Warehouse;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class UserSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $roles = [
            'admin' => Role::where('name', 'admin')->value('id'),
            'manager' => Role::where('name', 'manager')->value('id'),
            'employee' => Role::where('name', 'employee')->value('id'),
            'sells' => Role::where('name', 'sells')->value('id'),
            'accountant' => Role::where('name', 'accountant')->value('id'),
            'marketer' => Role::where('name', 'marketer')->value('id'),
        ];

        $users = [
            [
                'id' => 1,
                'name' => 'Issa',
                'email' => 'admin@awaanaltakadom.sy',
                'avatar' => null,
                'is_pro' => 0,
                'pro_label' => null,
                'notifications_enabled' => 1,
                'is_admin' => 1,
                'email_verified_at' => '2026-03-09 09:07:26',
                'password' => 'admin123',
                'remember_token' => '99YJpZpFWJEwDpET62LvWpYdRMeN6AdDi4ysCBwcqSPR16lLyGSm68n3wjUd',
                'created_at' => '2026-03-09 09:07:26',
                'updated_at' => '2026-03-11 02:14:29',
                'role_id' => $roles['admin'],
            ],
            [
                'id' => 2,
                'name' => 'Test User',
                'email' => 'test@example.com',
                'avatar' => null,
                'is_pro' => 0,
                'pro_label' => null,
                'notifications_enabled' => 1,
                'is_admin' => 0,
                'email_verified_at' => '2026-03-09 09:07:27',
                'password' => '$2y$12$oMRS6Qxh/ezs7IhpizvUau1gSaqrdRpRLiQYVIg490aZv0cS9ioSe',
                'remember_token' => 'cjka7tbR3v',
                'created_at' => '2026-03-09 09:07:27',
                'updated_at' => '2026-03-09 09:07:27',
                'role_id' => $roles['employee'],
            ],
            [
                'name' => 'anas',
                'email' => 'anas@awaanaltakadom.sy',
                'is_admin' => 0,
                'password' => bcrypt('password'),
                'role_id' => $roles['sells'],
            ],
            [
                'name' => 'ahmad',
                'email' => 'ahmad@awaanaltakadom.sy',
                'is_admin' => 0,
                'password' => bcrypt('password'),
                'role_id' => $roles['sells'],
            ],
            [
                'name' => 'ayoub',
                'email' => 'ayoub@awaanaltakadom.sy',
                'is_admin' => 0,
                'password' => bcrypt('password'),
                'role_id' => $roles['sells'],
            ],
            [
                'name' => 'hasan',
                'email' => 'hasan@awaanaltakadom.sy',
                'is_admin' => 0,
                'password' => bcrypt('password'),
                'role_id' => $roles['accountant'],
            ],
            [
                'name' => 'ayad',
                'email' => 'ayad@awaanaltakadom.sy',
                'is_admin' => 0,
                'password' => bcrypt('password'),
                'role_id' => $roles['marketer'],
            ],
        ];

        foreach ($users as $user) {
            $hashedPassword = Hash::make($user['password'] ?? 'password');

            $userModel = User::updateOrCreate(
                ['email' => $user['email']],
                [
                    'name' => $user['name'],
                    'password' => $hashedPassword,
                    'is_admin' => $user['is_admin'],
                    'role_id' => $user['role_id'],
                    'email_verified_at' => now(),
                    'remember_token' => null,
                ]
            );

            if (in_array($user['name'], ['anas', 'ahmad', 'ayoub'], true)) {
                $warehouseName = 'wh-' . strtolower($user['name']);
                $warehouse = Warehouse::updateOrCreate(
                    ['code' => $warehouseName],
                    [
                        'name' => $warehouseName,
                        'code' => $warehouseName,
                        'location_type' => Warehouse::TYPE_WAREHOUSE,
                        'is_active' => true,
                        'is_primary' => false,
                        'manager_id' => $userModel->id,
                    ]
                );

                Employee::updateOrCreate(
                    ['user_id' => $userModel->id],
                    [
                        'warehouse_id' => $warehouse->id,
                        'first_name' => $user['name'],
                        'last_name' => 'Sales',
                        'email' => $userModel->email,
                        'position' => 'sales',
                        'department' => 'sales',
                        'status' => 'active',
                    ]
                );
            }
        }

        $this->command?->info('Created system roles, initial users, and warehouse assignments.');
    }
}
