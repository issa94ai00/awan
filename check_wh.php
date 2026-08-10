<?php
require 'vendor/autoload.php';
$app = require 'bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

echo "Warehouses:\n";
foreach (App\Models\Warehouse::all(['id','name','code','is_active']) as $w) {
    echo "  {$w->id}: {$w->name} ({$w->code})" . ($w->is_active ? '' : ' [INACTIVE]') . "\n";
}
echo "\nExisting warehouse_inventory rows: " . App\Models\WarehouseInventory::count() . "\n";
echo "Existing products: " . App\Models\Product::count() . "\n";
