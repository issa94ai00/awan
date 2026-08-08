<?php
require __DIR__ . '/vendor/autoload.php';
$app = require __DIR__ . '/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Illuminate\Support\Facades\DB;
use App\Models\Product;
use App\Models\Category;

DB::statement('SET FOREIGN_KEY_CHECKS=0');
DB::table('products')->delete();
DB::table('categories')->delete();
DB::statement('SET FOREIGN_KEY_CHECKS=1');

echo "Deleted all products and categories.\n";
