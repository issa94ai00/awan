<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class UserSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $users = [
            [
                'id' => 1,
                'name' => 'Issa',
                'email' => 'admin@admin.com',
                'avatar' => null,
                'is_pro' => 0,
                'pro_label' => null,
                'notifications_enabled' => 1,
                'is_admin' => 1,
                'email_verified_at' => '2026-03-09 09:07:26',
                'password' => '$2y$12$VEaW0OUHVKfkLlU8lMBjwuZg7j8/2nXceR8P82dSCWZU0vFKAw99K',
                'remember_token' => '99YJpZpFWJEwDpET62LvWpYdRMeN6AdDi4ysCBwcqSPR16lLyGSm68n3wjUd',
                'created_at' => '2026-03-09 09:07:26',
                'updated_at' => '2026-03-11 02:14:29',
                'role_id' => null,
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
                'role_id' => null,
            ],
        ];

        DB::table('users')->insertOrIgnore($users);
        
        $this->command->info('Created sample users from database.sql');
    }
}
