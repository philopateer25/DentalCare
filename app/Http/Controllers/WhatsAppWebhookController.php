<?php

namespace App\Http\Controllers;

use App\Models\Patient;
use App\Models\Practice;
use App\Models\CommunicationLog;
use App\Models\Appointment;
use App\Services\GeminiService;
use App\Jobs\SendWhatsAppMessageJob;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class WhatsAppWebhookController extends Controller
{
    public function handle(Request $request)
    {
        $payload = $request->all();
        
        Log::info('WhatsApp Webhook Payload: ', $payload);
        
        // Ensure this is a message upsert event
        if (($payload['event'] ?? '') !== 'messages.upsert') {
            return response()->json(['status' => 'ignored', 'reason' => 'Not a message upsert']);
        }

        $data = $payload['data'] ?? [];
        $messageData = $data['message'] ?? [];
        $key = $data['key'] ?? [];

        // Ignore if the message was sent by us (outbound)
        if (isset($key['fromMe']) && $key['fromMe'] === true) {
            return response()->json(['status' => 'ignored', 'reason' => 'Message is from me']);
        }

        // Get sender JID (e.g., 201228277562@s.whatsapp.net)
        $remoteJid = $key['remoteJid'] ?? '';
        $phoneNumber = explode('@', $remoteJid)[0];

        // Ensure it's a real phone number
        if (empty($phoneNumber) || str_contains($remoteJid, '@g.us')) {
            return response()->json(['status' => 'ignored', 'reason' => 'Group message or missing number']);
        }

        // Figure out practice ID from instance name (e.g. clinic_1)
        $instanceName = $payload['instance'] ?? '';
        $practiceId = 1; // Default
        if (preg_match('/clinic_(\d+)/', $instanceName, $matches)) {
            $practiceId = $matches[1];
        }

        // Get the text content or audio base64
        $text = '';
        $audioBase64 = null;
        $audioMimeType = null;

        if (isset($messageData['conversation'])) {
            $text = $messageData['conversation'];
        } elseif (isset($messageData['extendedTextMessage']['text'])) {
            $text = $messageData['extendedTextMessage']['text'];
        } elseif (isset($messageData['audioMessage']) || isset($messageData['pttMessage'])) {
            // Evolution API provides base64 in the webhook if base64: true is set.
            // If base64 is false, it might provide a mediaUrl. 
            // We'll extract text if they sent text. If audio, we rely on Gemini.
            $audioMsg = $messageData['audioMessage'] ?? $messageData['pttMessage'] ?? [];
            if (isset($data['base64'])) {
                $audioBase64 = preg_replace('/^data:audio\/[a-zA-Z0-9]+;base64,/', '', $data['base64']);
                $audioMimeType = $audioMsg['mimetype'] ?? 'audio/ogg';
            } elseif (!empty($audioMsg)) {
                // If base64 wasn't in the webhook, manually fetch it from Evolution API
                $apiUrl = env('EVOLUTION_API_URL', 'http://evolution_api:8080');
                $apiKey = env('EVOLUTION_API_KEY', '');
                
                try {
                    $mediaResponse = Http::timeout(30)->withHeaders([
                        'apikey' => $apiKey,
                        'Content-Type' => 'application/json'
                    ])->post("{$apiUrl}/chat/getBase64FromMediaMessage/{$instanceName}", [
                        'message' => $messageData
                    ]);

                    if ($mediaResponse->successful()) {
                        $mediaData = $mediaResponse->json();
                        $base64Str = $mediaData['base64'] ?? '';
                        if ($base64Str) {
                            $audioBase64 = preg_replace('/^data:audio\/[a-zA-Z0-9]+;base64,/', '', $base64Str);
                            $audioMimeType = $audioMsg['mimetype'] ?? 'audio/ogg';
                        }
                    } else {
                        Log::error("Failed to fetch base64 from Evolution API", ['response' => $mediaResponse->body()]);
                    }
                } catch (\Exception $e) {
                    Log::error("Exception fetching base64: " . $e->getMessage());
                }
            }
        }

        if (empty($text) && !$audioBase64) {
            return response()->json(['status' => 'ignored', 'reason' => 'Message is empty or unhandled type']);
        }

        // Find patient
        $localPhone = $phoneNumber;
        if (str_starts_with($phoneNumber, '20') && strlen($phoneNumber) === 12) {
            $localPhone = '0' . substr($phoneNumber, 2);
        }

        $patient = Patient::where('practice_id', $practiceId)
            ->where(function ($query) use ($phoneNumber, $localPhone) {
                $query->where('whatsapp_number', $phoneNumber)
                      ->orWhere('whatsapp_number', $localPhone)
                      ->orWhere('phone', $phoneNumber)
                      ->orWhere('phone', $localPhone);
            })->first();

        // If patient not found, start registration flow
        if (!$patient) {
            $patient = Patient::create([
                'practice_id' => $practiceId,
                'phone' => $phoneNumber,
                'whatsapp_number' => $phoneNumber,
                'first_name' => 'Unknown',
                'last_name' => 'Patient',
                'status' => 'active',
                'bot_state' => 'registering',
                'bot_step' => 'ask_name'
            ]);

            $this->reply($instanceName, $phoneNumber, "Welcome to our Clinic! To get started, please tell us your full name.");
            return response()->json(['status' => 'success', 'reason' => 'started_registration']);
        }

        // Save incoming message to DB
        CommunicationLog::create([
            'practice_id' => $practiceId,
            'patient_id' => $patient->id,
            'direction' => 'inbound',
            'type' => 'whatsapp',
            'message' => $audioBase64 ? '[Voice Note Received]' : $text,
            'status' => 'received',
            'message_id' => $key['id'] ?? null,
        ]);

        // -------------------------
        // STATE MACHINE
        // -------------------------
        $state = $patient->bot_state ?? 'menu';

        if ($state === 'registering') {
            $step = $patient->bot_step;
            $data = $patient->bot_data ?? [];

            if ($step === 'ask_name') {
                $patient->first_name = $text;
                $patient->last_name = ''; // Splitting name can be done here if needed
                $patient->bot_step = 'ask_age';
                $patient->save();
                $this->reply($instanceName, $phoneNumber, "Nice to meet you, $text! What is your age?");
            } elseif ($step === 'ask_age') {
                // Approximate DOB from age
                $age = (int) preg_replace('/[^0-9]/', '', $text);
                if ($age > 0) {
                    $patient->dob = now()->subYears($age)->format('Y-m-d');
                }
                $patient->bot_state = 'menu';
                $patient->bot_step = null;
                $patient->status = 'active'; // Strictly lowercase for SQLite CHECK constraint
                $patient->save();
                
                $this->reply($instanceName, $phoneNumber, "Thank you! You are now registered. How can we help you today?\n\n1️⃣ Book an Appointment\n2️⃣ Speak to the Secretary");
            }
            return response()->json(['status' => 'success']);
        }

        if ($state === 'menu') {
            if (trim($text) === '1') {
                $patient->bot_state = 'booking';
                $patient->save();
                $this->reply($instanceName, $phoneNumber, "Great! When would you like to book your appointment? You can type a message or send a voice note with your preferred date and time.");
            } elseif (trim($text) === '2') {
                $patient->bot_state = 'human';
                $patient->save();
                $this->reply($instanceName, $phoneNumber, "I've notified the secretary. They will reply to you here shortly.");
            } else {
                $this->reply($instanceName, $phoneNumber, "Please reply with a number:\n\n1️⃣ Book an Appointment\n2️⃣ Speak to the Secretary");
            }
            return response()->json(['status' => 'success']);
        }

        if ($state === 'human') {
            // Do nothing, wait for secretary to reply via Filament.
            // When secretary replies manually, the SendWhatsAppMessageJob should ideally reset state to 'menu'.
            return response()->json(['status' => 'success', 'reason' => 'in_human_handoff']);
        }

        if ($state === 'booking') {
            $parsed = GeminiService::parseAppointmentRequest($text, $audioBase64, $audioMimeType);

            if ($parsed && isset($parsed['action']) && $parsed['action'] === 'request_appointment') {
                $date = $parsed['date'] ?? 'unknown date';
                $time = $parsed['time'] ?? 'unknown time';
                $notes = $parsed['notes'] ?? '';

                  // Create appointment request
                  $branchId = \App\Models\Branch::where('practice_id', $practiceId)->first()->id ?? 1;
                  
                  Appointment::create([
                      'practice_id' => $practiceId,
                      'branch_id' => $branchId,
                      'patient_id' => $patient->id,
                      'status' => 'booked', // ENUM limits us, so we use 'booked'
                      'start_time' => $date !== 'unknown date' ? $date . ' 00:00:00' : now(),
                      'chief_complaint' => "PATIENT REQUESTED via WhatsApp. Date: $date, Time: $time. Notes: $notes",
                      'procedure_name' => 'General Checkup',
                  ]);

                $patient->bot_state = 'menu';
                $patient->save();

                $this->reply($instanceName, $phoneNumber, "Your appointment request for $date ($time) has been submitted! The secretary will review and confirm shortly. Reply 'Menu' for options.");
            } elseif ($parsed && isset($parsed['action']) && $parsed['action'] === 'api_error') {
                $this->reply($instanceName, $phoneNumber, "Sorry, I'm receiving too many requests right now and hit my rate limit. Please wait about 30 seconds and try sending your request again!");
            } else {
                $this->reply($instanceName, $phoneNumber, "I couldn't quite understand the date and time. Could you please specify when you'd like to come in?");
            }
            return response()->json(['status' => 'success']);
        }

        return response()->json(['status' => 'success']);
    }

    private function reply($instanceName, $phone, $message)
    {
        SendWhatsAppMessageJob::dispatch($instanceName, $phone, $message);
    }
}
