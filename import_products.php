<?php
/**
 * Corrected inventory import from daily.xlsx (products_import.json).
 *
 * Logic:
 *   - Excel "الكمية" = units that are immediately AVAILABLE (not a separate total).
 *   - Each product gets a warehouse_inventory row on the main warehouse with
 *     quantity = available, so the inventory dashboard (which reads
 *     warehouse_inventory) and the product catalog agree.
 *   - Fills the key fields the business cares about: available, cost, price.
 */
require 'vendor/autoload.php';
$app = require 'bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use Illuminate\Support\Facades\DB;

$products = json_decode(file_get_contents('products_import.json'), true);
if (!$products) { echo "ERROR: products_import.json invalid\n"; exit(1); }

echo "Loaded " . count($products) . " products\n";
echo "This clears products + warehouse_inventory and re-imports. Continue? (yes/no): ";
if (trim(fgets(STDIN)) !== 'yes') { echo "Aborted.\n"; exit(0); }

$mainWarehouse = App\Models\Warehouse::where('is_active', true)->orderBy('id')->first();
if (!$mainWarehouse) { echo "ERROR: no active warehouse\n"; exit(1); }
echo "Using warehouse: {$mainWarehouse->name}\n";

DB::transaction(function () use ($products, $mainWarehouse) {
    // Temporarily disable FK checks so we can clear stock + products
    DB::statement('SET FOREIGN_KEY_CHECKS=0');
    App\Models\WarehouseInventory::query()->delete();
    App\Models\Product::query()->delete();
    DB::statement('SET FOREIGN_KEY_CHECKS=1');

    $inserted = 0;
    foreach ($products as $p) {
        $slug = Illuminate\Support\Str::slug($p['name']) . '-' . substr(md5($p['name'] . $inserted), 0, 6);

        $product = App\Models\Product::create([
            'category_id'    => $p['category_id'],
            'name_ar'        => $p['name'],
            'name_en'        => $p['name'],
            'slug'           => $slug,
            'price'          => $p['price'],     // سعر البيع
            'cost_price'     => $p['cost'],      // التكلفة
            'unit'           => mapUnit($p['unit']),
            'stock_quantity' => $p['qty'],       // المتاح
            'reorder_point'  => 5,
            'min_stock'      => 3,
            'in_stock'       => $p['qty'] > 0,
            'is_active'      => true,
            'show_price'     => true,
            'taxable'        => false,
            'tax_rate'       => 0,
        ]);

        // Mirror into warehouse_inventory: quantity = available stock.
        if ($p['qty'] > 0) {
            App\Models\WarehouseInventory::create([
                'warehouse_id'      => $mainWarehouse->id,
                'product_id'        => $product->id,
                'quantity'          => $p['qty'],        // = available on import
                'reserved_quantity' => 0,
                'available_quantity'=> $p['qty'],
                'damaged_quantity'  => 0,
                'quarantined_quantity' => 0,
                'reorder_point'     => 5,
            ]);
        }
        $inserted++;
    }
    echo "Inserted: $inserted products\n";
});

echo "\n=== VERIFY ===\n";
echo "Products: " . App\Models\Product::count() . "\n";
echo "Warehouse inventory rows: " . App\Models\WarehouseInventory::count() . "\n";
echo "Total available stock: " . App\Models\WarehouseInventory::sum('quantity') . "\n";
echo "Total cost value: " . round(App\Models\WarehouseInventory::sum(DB::raw('quantity * cost_basis')), 2) . "\n";

echo "\n=== BY CATEGORY ===\n";
foreach (App\Models\Category::all(['id','name_ar']) as $c) {
    $n = App\Models\Product::where('category_id', $c->id)->count();
    if ($n) echo "  {$c->name_ar}: $n\n";
}

function mapUnit($u) {
    return ['قطعه'=>'قطعة','قطعة'=>'قطعة','حبة'=>'حبة','كيس'=>'كيس','جوز'=>'قطعة','صندوق'=>'صندوق'][$u] ?? 'قطعة';
}
