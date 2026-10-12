<?php
require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$dev = \App\Models\User::where('email', 'dev@clinic.com')->first();
$httpKernel = $app->make(Illuminate\Contracts\Http\Kernel::class);

$req = \Illuminate\Http\Request::create('/admin/1', 'GET');
$req->setUserResolver(fn() => $dev);
auth()->login($dev);

// Enable full exception tracing in logs or response
app()->singleton(
    \Illuminate\Contracts\Debug\ExceptionHandler::class,
    \App\Exceptions\Handler::class ?? \Illuminate\Foundation\Exceptions\Handler::class
);

$res = $httpKernel->handle($req);
echo "Status: " . $res->getStatusCode() . "\n";

// Let's check Laravel logs!
$logFile = storage_path('logs/laravel.log');
if (file_exists($logFile)) {
    $lines = file($logFile);
    $last20 = array_slice($lines, -30);
    echo "=== RECENT LOGS ===\n";
    echo implode("", $last20);
}
