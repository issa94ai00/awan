<?php
require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Http\Kernel::class);

// Enable exception handling output or catch it
$app->resolving(\Illuminate\Contracts\Debug\ExceptionHandler::class, function ($handler) {
    // Custom exception reporter to dump it
});

try {
    $request1 = \Illuminate\Http\Request::create('/', 'GET');
    // Let's use the router directly to see the exception, or resolve from container
    $response1 = $kernel->handle($request1);
    echo "STATUS: " . $response1->getStatusCode() . "\n";
    if ($response1->getStatusCode() !== 200) {
        // Let's log or check the latest storage/logs/laravel.log or output the response content
        echo "Response: " . substr($response1->getContent(), 0, 2000) . "\n";
    }
} catch (\Exception $e) {
    echo "Caught Exception: " . $e->getMessage() . "\n";
    echo $e->getTraceAsString() . "\n";
}
