<?php

namespace App\Jobs;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class SendWhatsAppMessageJob implements ShouldQueue
{
    use Queueable;

    public $instanceName;
    public $phoneNumber;
    public $message;
    public $isManual;

    /**
     * Create a new job instance.
     */
    public function __construct(string $instanceName, string $phoneNumber, string $message, bool $isManual = false)
    {
        $this->instanceName = $instanceName;
        $this->phoneNumber = $phoneNumber;
        $this->message = $message;
        $this->isManual = $isManual;
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

        // Automatically format Egyptian numbers: if it starts with 01 and is 11 digits long, change 0 to 20
        if (str_starts_with($formattedPhone, '01') && strlen($formattedPhone) === 11) {
            $formattedPhone = '20' . substr($formattedPhone, 1);
        }

        try {
            $response = Http::timeout(60)->withHeaders([
                'apikey' => $apiKey,
                'Content-Type' => 'application/json'
            ])->post("{$apiUrl}/message/sendText/{$this->instanceName}", [
                'number' => $formattedPhone,
                'text' => $this->message
            ]);

            if (!$response->successful()) {
                Log::error("Failed to send WhatsApp message to {$this->phoneNumber}", [
                    'status' => $response->status(),
                    'response' => $response->json(),
                ]);
            } else {
                $responseData = $response->json();
                
                // Find patient to log the outbound message
                // This could be passed directly to the job, but for now we look it up
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
                        'message' => $this->message,
                        'status' => 'sent',
                        'message_id' => $responseData['key']['id'] ?? null,
                    ]);

                    // Reset bot state to menu if secretary is replying manually, 
                    // so the bot starts listening again.
                    if ($this->isManual) {
                        $patient->bot_state = 'menu';
                        $patient->save();
                    }
                }
            }
        } catch (\Exception $e) {
            Log::error("Exception in SendWhatsAppMessageJob: " . $e->getMessage());
            // Depending on requirements, we can throw the exception to let Laravel retry the job
            throw $e;
        }
    }
}
