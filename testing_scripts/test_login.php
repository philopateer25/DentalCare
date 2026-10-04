<?php
require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Http\Kernel::class);
$request = Illuminate\Http\Request::create('/admin/login', 'GET');
$response = $kernel->handle($request);
echo "Status Code: " . $response->getStatusCode() . "\n";
if ($response->getStatusCode() === 500) {
    echo "Content:\n";
    $content = $response->getContent();
    // try to extract the exception message
    if (preg_match('/<title>.*?Exception.*?<\/title>.*?<div class="exception-message">(.*?)<\/div>/s', $content, $matches)) {
        echo strip_tags($matches[1]);
    } else {
        echo substr($content, 0, 2000);
    }
}
