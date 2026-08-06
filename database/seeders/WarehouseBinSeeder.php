<?php

namespace Database\Seeders;

use App\Models\Warehouse;
use App\Models\WarehouseBin;
use Illuminate\Database\Seeder;

class WarehouseBinSeeder extends Seeder
{
    public function run(): void
    {
        // Use existing warehouse or create one with existing schema
        $warehouse = \App\Models\Warehouse::firstOrCreate(
            ['name' => 'Main Warehouse'],
            [
                'code' => 'WH-001',
                'address' => 'Industrial Zone',
                'city' => 'Riyadh',
                'country' => 'Saudi Arabia',
                'location_type' => 'warehouse',
                'capacity' => 10000,
                'manager_id' => 1,
                'is_active' => true,
                'is_primary' => true,
            ]
        );

        // Create zones and bins
        $zones = ['A', 'B', 'C', 'D'];
        $racks = ['R1', 'R2', 'R3', 'R4', 'R5'];
        $shelves = ['S1', 'S2', 'S3', 'S4', 'S5', 'S6', 'S7', 'S8', 'S9', 'S10'];

        foreach ($zones as $zone) {
            foreach ($racks as $rack) {
                foreach ($shelves as $shelf) {
                    $binCode = "{$zone}-{$rack}-{$shelf}";
                    
                    WarehouseBin::firstOrCreate(
                        ['bin_code' => $binCode, 'warehouse_id' => $warehouse->id],
                        [
                            'zone' => $zone,
                            'rack' => $rack,
                            'shelf' => $shelf,
                            'max_weight' => rand(100, 1000),
                        ]
                    );
                }
            }
        }

        $this->command->info('Created warehouse bins for Main Warehouse');
    }
}
