<?php
require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$payload = [
    'event' => 'messages.upsert',
    'instance' => 'clinic_1',
    'data' => [
        'key' => [
            'remoteJid' => '201228277562@s.whatsapp.net',
            'fromMe' => false,
            'id' => 'MSG123'
        ],
        'message' => [
            'listResponseMessage' => [
                'title' => 'Book Appointment',
                'singleSelectReply' => [
                    'selectedRowId' => 'book_appointment'
                ]
            ]
        ]
    ]
];

$request = Illuminate\Http\Request::create('/api/whatsapp/webhook', 'POST', $payload);
$httpKernel = $app->make(Illuminate\Contracts\Http\Kernel::class);
$response = $httpKernel->handle($request);
echo "Status Code: " . $response->getStatusCode() . "\n";
echo "Response: " . $response->getContent() . "\n";
