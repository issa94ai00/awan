<?php
require 'vendor/autoload.php';
$app = require 'bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

echo "Total products: " . App\Models\Product::count() . PHP_EOL;
echo "With stock > 0: " . App\Models\Product::where('stock_quantity', '>', 0)->count() . PHP_EOL;
echo "Total stock: " . App\Models\Product::sum('stock_quantity') . PHP_EOL;
echo "Total value (cost): " . round(App\Models\Product::selectRaw('SUM(stock_quantity * cost_price) as v')->first()->v, 2) . PHP_EOL;

echo "\n=== SAMPLE PRODUCTS ===\n";
$sample = App\Models\Product::with('category')->limit(10)->get();
foreach ($sample as $p) {
    echo sprintf("  [%d] %s | فئة: %s | كمية: %d | تكلفة: %s | سعر: %s\n",
        $p->id, $p->name_ar, $p->category->name_ar, $p->stock_quantity, $p->cost_price, $p->price);
}
