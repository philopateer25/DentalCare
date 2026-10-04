<?php
require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$apiKey = env('GEMINI_API_KEY');
$url = "https://generativelanguage.googleapis.com/v1beta/models?key={$apiKey}";
$response = file_get_contents($url);
$data = json_decode($response, true);

foreach($data['models'] as $m) {
    if (strpos($m['name'], 'flash') !== false) {
        echo $m['name'] . "\n";
    }
}
