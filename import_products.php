<?php
/**
 * Import products from daily.xlsx (pre-categorized JSON) into the products table.
 * Clears existing products first.
 */
require 'vendor/autoload.php';
$app = require 'bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

$json = file_get_contents('products_import.json');
$products = json_decode($json, true);

if (!$products || !is_array($products)) {
    echo "ERROR: products_import.json not found or invalid\n";
    exit(1);
}

echo "Loaded " . count($products) . " products from JSON\n";

// Confirm
echo "This will DELETE all existing products and insert " . count($products) . " new ones. Continue? (yes/no): ";
$handle = fopen("php://stdin", "r");
$line = trim(fgets($handle));
if (strtolower($line) !== 'yes') {
    echo "Aborted.\n";
    exit(0);
}

// Clear existing products
echo "Deleting existing products...\n";
App\Models\Product::query()->delete();
echo "Deleted.\n";

// Insert new products
$now = now()->toDateTimeString();
$inserted = 0;
$errors = [];

foreach ($products as $p) {
    $name = $p['name'];
    $slug = Illuminate\Support\Str::slug($name) . '-' . substr(md5($name . microtime()), 0, 6);

    try {
        App\Models\Product::create([
            'category_id'    => $p['category_id'],
            'name_ar'        => $name,
            'name_en'        => $name,
            'slug'           => $slug,
            'description_ar' => null,
            'description_en' => null,
            'price'          => $p['price'],
            'cost_price'     => $p['cost'],
            'unit'           => mapUnit($p['unit']),
            'stock_quantity' => $p['qty'],
            'reorder_point'  => 5,
            'min_stock'      => 3,
            'sku'            => null,
            'barcode'        => null,
            'in_stock'       => $p['qty'] > 0,
            'is_active'      => true,
            'show_price'     => true,
            'taxable'        => false,
            'tax_rate'       => 0,
            'is_featured'    => false,
            'views_count'    => 0,
            'sort_order'     => 0,
        ]);
        $inserted++;
    } catch (Exception $e) {
        $errors[] = $name . ': ' . $e->getMessage();
    }
}

echo "\n=== DONE ===\n";
echo "Inserted: $inserted / " . count($products) . "\n";

if ($errors) {
    echo "\nERRORS (" . count($errors) . "):\n";
    foreach ($errors as $err) echo "  - $err\n";
}

// Verify by category
echo "\n=== PRODUCTS BY CATEGORY ===\n";
$cats = App\Models\Category::all(['id', 'name_ar']);
foreach ($cats as $cat) {
    $count = App\Models\Product::where('category_id', $cat->id)->count();
    if ($count > 0) {
        echo $cat->name_ar . ": $count\n";
    }
}

function mapUnit($unit): string
{
    $map = [
        'قطعه'  => 'قطعة',
        'قطعة'  => 'قطعة',
        'حبة'   => 'حبة',
        'كيس'   => 'كيس',
        'جوز'   => 'قطعة',
        'صندوق' => 'صندوق',
    ];
    return $map[$unit] ?? 'قطعة';
}
