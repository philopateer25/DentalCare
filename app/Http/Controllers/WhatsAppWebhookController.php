<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Http;
use App\Services\GeminiAiService;
use App\Jobs\SendWhatsAppMessage;

class WhatsAppWebhookController extends Controller
{
    /**
     * Handle incoming webhook requests from UltraMsg or similar providers.
     */
    public function handle(Request $request, GeminiAiService $geminiAi)
    {
        // 1. Log incoming payload for debugging
        Log::info('Incoming WhatsApp Webhook', $request->all());

        $data = $request->input('data');
        
        // Ensure this is a valid message payload
        if (!$data || !isset($data['from']) || !isset($data['type'])) {
            return response()->json(['status' => 'ignored']);
        }

        // We only care about audio/ptt (Push To Talk / voice notes)
        if ($data['type'] === 'ptt' || $data['type'] === 'audio') {
            // UltraMsg puts the audio link in the 'media' field, not 'body'
            $audioUrl = $data['media'] ?? $data['body'] ?? null;
            $phone = $data['from'];

            if ($audioUrl) {
                // 2. Download the audio file from UltraMsg (disable decoding to prevent cURL error 61 on bad S3 headers)
                try {
                    $audioResponse = Http::withOptions(['decode_content' => false])->get($audioUrl);
                    
                    if ($audioResponse->successful()) {
                        $audioBase64 = base64_encode($audioResponse->body());
                        
                        // 3. Send to Gemini for transcription and JSON extraction
                        $parsedData = $geminiAi->analyzeVoiceNote($audioBase64, 'audio/ogg');

                        if ($parsedData) {
                            // 4. Formulate an automated response based on the AI's JSON output
                            $replyText = "🤖 *AI Assistant Summary*\n\n";
                            $replyText .= "🗣️ *Transcription:* " . ($parsedData['transcription'] ?? 'N/A') . "\n";
                            $replyText .= "🎯 *Intent:* " . ($parsedData['patient_intent'] ?? 'N/A') . "\n";
                            
                            $symptoms = implode(', ', $parsedData['symptoms'] ?? []);
                            $replyText .= "🤕 *Symptoms:* " . ($symptoms ?: 'None detected') . "\n";
                            $replyText .= "📅 *Requested Time:* " . ($parsedData['requested_time'] ?? 'N/A') . "\n\n";
                            $replyText .= "This is an automated reply. We have logged your request and a human will confirm shortly!";

                            // 5. Send the reply back to the patient
                            SendWhatsAppMessage::dispatch($phone, $replyText);
                        } else {
                            Log::error('Gemini AI failed to parse the voice note.');
                            SendWhatsAppMessage::dispatch($phone, "Sorry, I couldn't understand that voice note. Could you please type it?");
                        }
                    } else {
                        Log::error('Failed to download audio from Webhook URL', ['url' => $audioUrl]);
                    }
                } catch (\Exception $e) {
                    Log::error('Exception during audio processing', ['error' => $e->getMessage()]);
                }
            }
        }

        return response()->json(['status' => 'success']);
    }
}
