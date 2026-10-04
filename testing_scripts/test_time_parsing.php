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
            'id' => 'MSG1234'
        ],
        'message' => [
            'conversation' => 'book appointment tomorrow at 6pm'
        ]
    ]
];

// Force patient 1 into booking state for this test
$patient = \App\Models\Patient::find(1);
if ($patient) {
    $patient->bot_state = 'booking';
    $patient->save();
}

// Mock GeminiService to simulate successful booking extraction
app()->bind('App\Services\GeminiService', function () {
    return new class {
        public static function parseAppointmentRequest($text, $audioBase64, $audioMimeType) {
            return [
                'action' => 'request_appointment',
                'date' => now()->addDay()->format('Y-m-d'),
                'time' => '18:00',
                'notes' => 'Patient wants 6pm'
            ];
        }
    };
});

$request = Illuminate\Http\Request::create('/api/whatsapp/webhook', 'POST', $payload);
$httpKernel = $app->make(Illuminate\Contracts\Http\Kernel::class);
$response = $httpKernel->handle($request);

echo "Status Code: " . $response->getStatusCode() . "\n";
echo "Response: " . $response->getContent() . "\n";

// Verify request
$req = \App\Models\WhatsAppAppointmentRequest::latest()->first();
echo "Created Request:\n";
echo "Date: " . $req->requested_date . "\n";
echo "Time: " . $req->requested_time . "\n";
echo "Status: " . $req->status . "\n";

