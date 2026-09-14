<?php

namespace App\Http\Controllers;

use App\Models\Patient;
use App\Models\Practice;
use App\Models\CommunicationLog;
use App\Models\Appointment;
use App\Services\GeminiService;
use App\Jobs\SendWhatsAppMessageJob;
use Filament\Notifications\Notification;
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

        // Get the text content, list row ID, or audio base64
        $text = '';
        $audioBase64 = null;
        $audioMimeType = null;
        $interactiveRowId = null;

        if (isset($messageData['listResponseMessage']['singleSelectReply']['selectedRowId'])) {
            $interactiveRowId = $messageData['listResponseMessage']['singleSelectReply']['selectedRowId'];
            $text = $messageData['listResponseMessage']['title'] ?? $interactiveRowId;
        } elseif (isset($messageData['buttonsResponseMessage']['selectedButtonId'])) {
            $interactiveRowId = $messageData['buttonsResponseMessage']['selectedButtonId'];
            $text = $messageData['buttonsResponseMessage']['selectedDisplayText'] ?? $interactiveRowId;
        } elseif (isset($messageData['conversation'])) {
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
                
                $this->sendMenu($instanceName, $phoneNumber);
            }
            return response()->json(['status' => 'success']);
        }

        if ($state === 'rescheduling') {
            $requestId = $patient->bot_step;
            $request = \App\Models\WhatsAppAppointmentRequest::find($requestId);
            
            if (!$request || $request->status !== 'pending') {
                $patient->bot_state = 'menu';
                $patient->bot_step = null;
                $patient->save();
                $this->sendMenu($instanceName, $phoneNumber);
                return response()->json(['status' => 'success']);
            }

            $lowerText = strtolower(trim($text));
            if (in_array($lowerText, ['yes', 'ok', 'sure', 'y', 'perfect', 'sounds good']) || str_contains($lowerText, 'yes')) {
                $request->notes .= "\n[Patient accepted proposed time: $text]";
                $request->save();
                $patient->bot_state = 'menu';
                $patient->bot_step = null;
                $patient->save();

                $this->reply($instanceName, $phoneNumber, "Perfect! Your appointment is confirmed. The secretary has been notified.");
                
                $users = \App\Models\User::where('practice_id', $practiceId)->get();
                Notification::make()
                    ->title("Patient Accepted Reschedule")
                    ->body("{$patient->first_name} accepted the new time for their appointment request.")
                    ->success()
                    ->sendToDatabase($users);
            } else {
                $parsed = GeminiService::parseAppointmentRequest($text, $audioBase64, $audioMimeType);
                if ($parsed && isset($parsed['action']) && $parsed['action'] === 'request_appointment') {
                    $date = $parsed['date'] && $parsed['date'] !== 'unknown date' ? $parsed['date'] : $request->requested_date;
                    $time = $parsed['time'] ?? null;
                    
                    $parsedTime = $request->requested_time;
                    if ($time && $time !== 'unknown time') {
                        if (str_contains(strtolower($time), 'morning')) {
                            $parsedTime = '09:00:00';
                        } elseif (str_contains(strtolower($time), 'evening') || str_contains(strtolower($time), 'afternoon')) {
                            $parsedTime = '16:00:00';
                        } else {
                            try {
                                $parsedTime = \Carbon\Carbon::parse($time)->format('H:i:s');
                            } catch (\Exception $e) {
                                // Keep old time
                            }
                        }
                    }
                    
                    $request->update([
                        'requested_date' => $date,
                        'requested_time' => $parsedTime,
                        'notes' => $request->notes . "\n[Patient proposed new time: $text]",
                    ]);
                    
                    $patient->bot_state = 'menu';
                    $patient->bot_step = null;
                    $patient->save();

                    $formattedTime = \Carbon\Carbon::parse($parsedTime)->format('h:i A');
                    $this->reply($instanceName, $phoneNumber, "Got it! Your new proposed time for $date at $formattedTime has been sent to the secretary for review.");
                    
                    $users = \App\Models\User::where('practice_id', $practiceId)->get();
                    Notification::make()
                        ->title("Patient Proposed New Time")
                        ->body("{$patient->first_name} proposed a different time: {$date} at {$formattedTime}.")
                        ->warning()
                        ->sendToDatabase($users);
                } else {
                    $this->reply($instanceName, $phoneNumber, "I couldn't understand the time. Please reply with 'Yes' to accept the proposed time, or clearly state the new time you prefer.");
                }
            }
            return response()->json(['status' => 'success']);
        }

        if ($state === 'menu') {
            $lowerText = strtolower(trim($text));
            if ($interactiveRowId === 'book_appointment' || $lowerText === '1' || str_contains($lowerText, 'book')) {
                $patient->bot_state = 'booking';
                $patient->save();
                $this->reply($instanceName, $phoneNumber, "Great! When would you like to book your appointment? You can type a message or send a voice note with your preferred date and time.");
            } elseif ($interactiveRowId === 'speak_human' || $lowerText === '2' || str_contains($lowerText, 'human')) {
                $patient->bot_state = 'human';
                $patient->save();
                $this->reply($instanceName, $phoneNumber, "I've notified the secretary. They will reply to you here shortly.");
                
                // Notify Secretary
                $users = \App\Models\User::where('practice_id', $practiceId)->get();
                Notification::make()
                    ->title("Patient Requesting Human")
                    ->body("{$patient->first_name} {$patient->last_name} ({$patient->phone}) wants to speak to a secretary via WhatsApp.")
                    ->warning()
                    ->sendToDatabase($users);
            } else {
                // To prevent spamming on unrecognized generic messages like "Thank you", 
                // we only resend the menu if they explicitly use a wake word.
                $wakeWords = ['hi', 'hello', 'menu', 'help', 'start', 'options'];
                
                // If it's a completely empty interaction or explicitly contains a wake word, resend menu.
                if (empty($lowerText) || array_intersect($wakeWords, explode(' ', preg_replace('/[^a-z0-9 ]/', '', $lowerText)))) {
                    $this->sendMenu($instanceName, $phoneNumber);
                }
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
                $date = $parsed['date'] ?? null;
                $time = $parsed['time'] ?? null;
                $notes = $parsed['notes'] ?? '';

                if (!$date || $date === 'unknown date') {
                    $date = now()->addDay()->format('Y-m-d');
                }
                
                $parsedTime = '09:00:00';
                if ($time && $time !== 'unknown time') {
                    if (str_contains(strtolower($time), 'morning')) {
                        $parsedTime = '09:00:00';
                    } elseif (str_contains(strtolower($time), 'evening') || str_contains(strtolower($time), 'afternoon')) {
                        $parsedTime = '16:00:00';
                    } else {
                        try {
                            $parsedTime = \Carbon\Carbon::parse($time)->format('H:i:s');
                        } catch (\Exception $e) {
                            $parsedTime = '09:00:00';
                        }
                    }
                }

                \App\Models\WhatsAppAppointmentRequest::create([
                    'practice_id' => $practiceId,
                    'patient_id' => $patient->id,
                    'requested_date' => $date,
                    'requested_time' => $parsedTime,
                    'notes' => $notes,
                    'status' => 'pending',
                ]);

                $patient->bot_state = 'menu';
                $patient->save();

                $formattedTime = \Carbon\Carbon::parse($parsedTime)->format('h:i A');
                $this->reply($instanceName, $phoneNumber, "Your appointment request for $date at $formattedTime has been submitted! The secretary will review and confirm shortly. Reply 'Menu' for options.");
                
                // Notify Secretary
                $users = \App\Models\User::where('practice_id', $practiceId)->get();
                Notification::make()
                    ->title("New Appointment Request")
                    ->body("{$patient->first_name} {$patient->last_name} requested an appointment on {$date} at {$formattedTime}.")
                    ->info()
                    ->sendToDatabase($users);
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

    private function sendMenu($instanceName, $phone)
    {
        $menuText = "DentalCare Assistant\nHow can we help you today?\n\n1️⃣ Book Appointment\n2️⃣ Speak to Secretary\n\n*(Please reply with 1 or 2)*";
        
        SendWhatsAppMessageJob::dispatch($instanceName, $phone, $menuText);
    }
}
