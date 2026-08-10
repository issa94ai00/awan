<?php
require 'vendor/autoload.php';
$app = require 'bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();
$row = DB::select("SHOW COLUMNS FROM warehouse_inventory WHERE Field='cost_basis'");
print_r($row);
