<?php
require 'vendor/autoload.php';
$app = require 'bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();
$cols = Illuminate\Support\Facades\Schema::getColumnListing('warehouse_inventory');
foreach (['cost_basis','quantity','available_quantity'] as $c) {
    $t = Illuminate\Support\Facades\Schema::getColumnType('warehouse_inventory', $c);
    echo "$c: $t\n";
}
