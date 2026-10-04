<?php
require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Http\Kernel::class);
$user = \App\Models\User::first();
\Illuminate\Support\Facades\Auth::login($user);
$request = Illuminate\Http\Request::create('/admin', 'GET');
$request->setLaravelSession(app('session')->driver());
app('auth')->guard()->setUser($user);
$response = $kernel->handle($request);
echo "Status Code: " . $response->getStatusCode() . "\n";
if ($response->getStatusCode() >= 400) {
    echo "Content:\n";
    $content = $response->getContent();
    // try to extract the exception message
    if (preg_match('/<title>.*?Exception.*?<\/title>.*?<div class="exception-message">(.*?)<\/div>/s', $content, $matches)) {
        echo strip_tags($matches[1]);
    } else {
        echo substr($content, 0, 2000);
    }
} else if ($response->isRedirect()) {
    echo "Redirect to: " . $response->getTargetUrl() . "\n";
}
