<?php
require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Http\Kernel::class);
$request = Illuminate\Http\Request::create('/admin/login', 'GET');
$request->setLaravelSession(app('session')->driver());
$response = $kernel->handle($request);
echo "Status Code: " . $response->getStatusCode() . "\n";
