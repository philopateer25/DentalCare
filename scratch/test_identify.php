<?php
require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$dev = \App\Models\User::where('email', 'dev@clinic.com')->first();
auth()->login($dev);

$panel = \Filament\Facades\Filament::getPanel('admin');
\Filament\Facades\Filament::setCurrentPanel($panel);

$router = app('router');
$request = \Illuminate\Http\Request::create('/admin/1', 'GET');
$route = $router->getRoutes()->match($request);
$request->setRouteResolver(fn() => $route);
$request->setUserResolver(fn() => $dev);

try {
    $mw = new \Filament\Http\Middleware\IdentifyTenant();
    $res = $mw->handle($request, function($req) {
        return response('PASSED IDENTIFY TENANT');
    });
    echo "IdentifyTenant Response: " . $res->getContent() . "\n";
} catch (\Symfony\Component\HttpKernel\Exception\HttpException $e) {
    echo "HTTP Exception in IdentifyTenant: " . $e->getStatusCode() . " - " . $e->getMessage() . "\n";
} catch (\Throwable $e) {
    echo "Throwable: " . $e->getMessage() . " in " . $e->getFile() . ":" . $e->getLine() . "\n";
}
