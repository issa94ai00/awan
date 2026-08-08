<?php

namespace Database\Seeders;

use App\Models\Warehouse;
use App\Models\WarehouseBin;
use Illuminate\Database\Seeder;

class WarehouseBinSeeder extends Seeder
{
    public function run(): void
    {
        // The main warehouse belongs to WarehouseSeeder. Matching on the English
        // name here used to create a second warehouse the moment anything
        // renamed the first one, so the bins ended up attached to an empty
        // duplicate while all the stock sat in the original.
        $this->call(WarehouseSeeder::class);
        $warehouse = \App\Models\Warehouse::findOrFail(WarehouseSeeder::mainWarehouseId());

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

        $this->command->info("Created warehouse bins for {$warehouse->name}");
    }
}
