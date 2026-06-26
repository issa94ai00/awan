<?php
require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Http\Kernel::class);

$request = \Illuminate\Http\Request::create('/api/v1/special-offers', 'GET');
$request->headers->set('Accept', 'application/json');

$response = $kernel->handle($request);
echo "STATUS: " . $response->getStatusCode() . "\n";
echo "BODY: " . substr($response->getContent(), 0, 500) . "\n";
