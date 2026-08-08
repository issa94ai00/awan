<?php
require 'vendor/autoload.php';
$app = require 'bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

echo "=== CATEGORIES ===\n";
foreach (App\Models\Category::all(['id','name_ar','name_en']) as $c) {
    echo $c->id . ': ' . $c->name_ar . ' (' . $c->name_en . ")\n";
}

echo "\n=== PRODUCTS COUNT ===\n";
echo "Products: " . App\Models\Product::count() . "\n";
echo "Categories: " . App\Models\Category::count() . "\n";
