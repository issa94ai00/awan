<?php
require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$bladeCompiler = app('blade.compiler');
$viewPath = 'resources/views/vue.blade.php';
$content = file_get_contents($viewPath);
$compiled = $bladeCompiler->compileString($content);

$outputPath = 'scratch/compiled_vue.php';
file_put_contents($outputPath, $compiled);

exec("php -l " . escapeshellarg($outputPath), $output, $returnVar);
echo implode("\n", $output) . "\n";
echo "Exit code: " . $returnVar . "\n";
