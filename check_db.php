<?php
require 'vendor/autoload.php';
$app = require 'bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

echo 'DB default: ' . config('database.default') . PHP_EOL;
echo 'SQLite path: ' . (config('database.connections.sqlite.database') ?? 'N/A') . PHP_EOL;
echo 'DB_DATABASE env: ' . (env('DB_DATABASE') ?? 'N/A') . PHP_EOL;

// Check products table columns we care about
$cols = Illuminate\Support\Facades\Schema::getColumnListing('products');
echo 'Product fillable cols: ' . implode(', ', array_intersect($cols, [
    'name_ar','name_en','slug','price','cost_price','unit',
    'stock_quantity','min_stock','reorder_point','sku','barcode',
    'category_id','is_active','in_stock','description_ar'
])) . PHP_EOL;
