<?php
require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$httpKernel = $app->make(Illuminate\Contracts\Http\Kernel::class);

$dev = \App\Models\User::where('email', 'dev@clinic.com')->first();
$req = \Illuminate\Http\Request::create('/admin/1/patients', 'GET');
$req->setUserResolver(fn() => $dev);
auth()->login($dev);

$res = $httpKernel->handle($req);
echo "GET /admin/1/patients as dev@clinic.com: HTTP Status " . $res->getStatusCode() . "\n";

$admin = \App\Models\User::where('email', 'admin@clinic.com')->first();
$req2 = \Illuminate\Http\Request::create('/admin/1/patients', 'GET');
$req2->setUserResolver(fn() => $admin);
auth()->login($admin);
$res2 = $httpKernel->handle($req2);
echo "GET /admin/1/patients as admin@clinic.com: HTTP Status " . $res2->getStatusCode() . "\n";
