<?php
require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();
$user = \App\Models\User::first();
\Illuminate\Support\Facades\Auth::login($user);
$httpKernel = $app->make(Illuminate\Contracts\Http\Kernel::class);
$request = Illuminate\Http\Request::create('/admin', 'GET');
$request->setLaravelSession(app('session')->driver());
app('auth')->guard()->setUser($user);
$response = $httpKernel->handle($request);
echo "Status Code: " . $response->getStatusCode() . "\n";
if ($response->isRedirect()) {
    echo "Redirect: " . $response->getTargetUrl() . "\n";
} else if ($response->getStatusCode() >= 400) {
    echo substr(strip_tags($response->getContent()), 0, 500);
}
