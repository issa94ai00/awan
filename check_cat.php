<?php
require 'vendor/autoload.php';
$app = require 'bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();
echo "Categories: " . App\Models\Category::count() . "\n";
foreach (App\Models\Category::all(['id','name_ar']) as $c) {
    echo "  {$c->id}: {$c->name_ar}\n";
}
