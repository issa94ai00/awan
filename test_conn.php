<?php
require 'vendor/autoload.php';
$app = require 'bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

try {
    $pdo = DB::connection()->getPdo();
    echo 'CONNECTED: ' . DB::connection()->getDriverName() . PHP_EOL;
    echo 'Products: ' . App\Models\Product::count() . PHP_EOL;
} catch (Exception $e) {
    echo 'FAIL: ' . $e->getMessage() . PHP_EOL;
}
