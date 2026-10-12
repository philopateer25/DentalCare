<?php
require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

// Test 1: dev@clinic.com
$dev = \App\Models\User::where('email', 'dev@clinic.com')->first();
$httpKernel = $app->make(Illuminate\Contracts\Http\Kernel::class);

$req = \Illuminate\Http\Request::create('/admin/1', 'GET');
$req->setUserResolver(fn() => $dev);
auth()->login($dev);

$res = $httpKernel->handle($req);
echo "GET /admin/1 as dev@clinic.com: HTTP Status " . $res->getStatusCode() . "\n";
if ($res->getStatusCode() >= 400) {
    echo "Content (first 400 chars): " . substr($res->getContent(), 0, 400) . "\n";
}

// Test 2: admin@clinic.com
$admin = \App\Models\User::where('email', 'admin@clinic.com')->first();
$req2 = \Illuminate\Http\Request::create('/admin/1', 'GET');
$req2->setUserResolver(fn() => $admin);
auth()->login($admin);
$res2 = $httpKernel->handle($req2);
echo "GET /admin/1 as admin@clinic.com: HTTP Status " . $res2->getStatusCode() . "\n";
if ($res2->getStatusCode() >= 400) {
    echo "Content (first 400 chars): " . substr($res2->getContent(), 0, 400) . "\n";
}
