<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class ProductSeeder extends Seeder
{
    public function run(): void
    {
        // Excel import functionality has been removed.
        // Products are now seeded via SanitaryProductSeeder.
        $this->command->info('ProductSeeder: Excel import disabled. Use SanitaryProductSeeder for products.');
    }
}
