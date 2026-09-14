<?php

namespace App\Jobs;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class SendWhatsAppListMessageJob implements ShouldQueue
{
    use Queueable;

    public $instanceName;
    public $phoneNumber;
    public $title;
    public $description;
    public $buttonText;
    public $footerText;
    public $sections;

    /**
     * Create a new job instance.
     */
    public function __construct(string $instanceName, string $phoneNumber, string $title, string $description, string $buttonText, string $footerText, array $sections)
    {
        $this->instanceName = $instanceName;
        $this->phoneNumber = $phoneNumber;
        $this->title = $title;
        $this->description = $description;
        $this->buttonText = $buttonText;
        $this->footerText = $footerText;
        $this->sections = $sections;
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        $apiUrl = env('EVOLUTION_API_URL', 'http://evolution_api:8080');
        $apiKey = env('EVOLUTION_API_KEY', '');

        // Format phone number to strictly digits just in case
        $formattedPhone = preg_replace('/[^0-9]/', '', $this->phoneNumber);

        // Automatically format Egyptian numbers
        if (str_starts_with($formattedPhone, '01') && strlen($formattedPhone) === 11) {
            $formattedPhone = '20' . substr($formattedPhone, 1);
        }

        try {
            $response = Http::timeout(60)->withHeaders([
                'apikey' => $apiKey,
                'Content-Type' => 'application/json'
            ])->post("{$apiUrl}/message/sendList/{$this->instanceName}", [
                'number' => $formattedPhone,
                'title' => $this->title,
                'description' => $this->description,
                'buttonText' => $this->buttonText,
                'footerText' => $this->footerText,
                'sections' => $this->sections,
            ]);

            if (!$response->successful()) {
                Log::error("Failed to send WhatsApp list message to {$this->phoneNumber}", [
                    'status' => $response->status(),
                    'response' => $response->json(),
                ]);
            } else {
                $responseData = $response->json();
                
                // Find patient to log the outbound message
                $patient = \App\Models\Patient::where(function ($query) {
                    $query->where('whatsapp_number', $this->phoneNumber)
                          ->orWhere('phone', $this->phoneNumber);
                })->first();

                if ($patient) {
                    \App\Models\CommunicationLog::create([
                        'practice_id' => $patient->practice_id,
                        'patient_id' => $patient->id,
                        'direction' => 'outbound',
                        'type' => 'whatsapp',
                        'message' => "[Interactive List Sent: {$this->title}]",
                        'status' => 'sent',
                        'message_id' => $responseData['key']['id'] ?? null,
                    ]);
                }
            }
        } catch (\Exception $e) {
            Log::error("Exception in SendWhatsAppListMessageJob: " . $e->getMessage());
            throw $e;
        }
    }
}
