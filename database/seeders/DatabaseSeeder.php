<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Artisan;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $connection = config('database.default');
        $driver = config("database.connections.{$connection}.driver");

        if ($driver !== 'mysql') {
            // For non-MySQL databases (like SQLite in testing), run original seeders
            // Create admin user
            User::factory()->create([
                'name' => 'Admin',
                'email' => 'admin@admin.com',
                'password' => bcrypt('admin123'),
                'is_admin' => true,
            ]);

            // Create test user
            User::factory()->create([
                'name' => 'Test User',
                'email' => 'test@example.com',
            ]);

            $this->call([
                SettingSeeder::class,
                RoleSeeder::class,
                UserSeeder::class,
                CategorySeeder::class,
                ProductSeeder::class,
                WarehouseBinSeeder::class,
                CustomerSeeder::class,
                NotificationTemplateSeeder::class,
                WorkflowSeeder::class,
            ]);
            return;
        }

        // For MySQL, run the attakadom.sql dump if it exists
        $path = base_path('attakadom.sql');
        if (File::exists($path)) {
            $pdo = DB::connection()->getPdo();
            \Illuminate\Support\Facades\Schema::dropAllTables();
            $pdo->exec('SET FOREIGN_KEY_CHECKS=0;');

            // Execute SQL file statement-by-statement to avoid "max_allowed_packet" errors
            $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
            $query = '';
            foreach ($lines as $line) {
                $trimmed = trim($line);
                if (str_starts_with($trimmed, '--') || str_starts_with($trimmed, '/*') || $trimmed === '') {
                    continue;
                }

                $query .= $line . "\n";

                if (str_ends_with($trimmed, ';')) {
                    $pdo->exec($query);
                    $query = '';
                }
            }
            if (trim($query) !== '') {
                $pdo->exec($query);
            }

            $pdo->exec('SET FOREIGN_KEY_CHECKS=1;');

            // Re-run any migrations added after the backup was taken
            Artisan::call('migrate', ['--force' => true]);

            // Re-seed settings to insert any newly added default configurations
            $this->call([
                SettingSeeder::class,
                RmaSeeder::class,
            ]);
        } else {
            // Fallback if backup file is missing
            User::factory()->create([
                'name' => 'Admin',
                'email' => 'admin@admin.com',
                'password' => bcrypt('admin123'),
                'is_admin' => true,
            ]);

            User::factory()->create([
                'name' => 'Test User',
                'email' => 'test@example.com',
            ]);

            $this->call([
                SettingSeeder::class,
                RoleSeeder::class,
                UserSeeder::class,
                CategorySeeder::class,
                ProductSeeder::class,
                WarehouseBinSeeder::class,
                CustomerSeeder::class,
                NotificationTemplateSeeder::class,
                WorkflowSeeder::class,
                RmaSeeder::class,
            ]);
        }
    }
}
