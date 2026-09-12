<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class GeminiService
{
    public static function parseAppointmentRequest(string $text, ?string $audioBase64 = null, ?string $audioMimeType = null): ?array
    {
        $apiKey = env('GEMINI_API_KEY');
        if (!$apiKey) {
            Log::error('Gemini API key missing');
            return null;
        }

        $url = "https://generativelanguage.googleapis.com/v1beta/models/gemini-3.6-flash:generateContent?key={$apiKey}";

        $systemPrompt = "You are an AI assistant for a dental clinic. A patient wants to request an appointment. "
            . "Extract the desired date, time, and any notes from their message. "
            . "If they sent a voice note, parse the audio transcription. "
            . "Always return the response in strict JSON format with exactly these keys: "
            . "`action` (always 'request_appointment'), `date` (YYYY-MM-DD if possible to determine, or null), `time` (HH:MM if possible, or 'morning'/'evening'/null), `notes` (string or null). "
            . "Current date is " . now()->format('Y-m-d (l)') . ". Do NOT wrap the JSON in markdown code blocks, just return raw JSON.";

        $contents = [];

        if ($audioBase64 && $audioMimeType) {
            $contents[] = [
                'parts' => [
                    ['text' => $systemPrompt],
                    ['text' => "Here is the user's voice note audio:"],
                    [
                        'inlineData' => [
                            'mimeType' => $audioMimeType,
                            'data' => $audioBase64
                        ]
                    ]
                ]
            ];
        } else {
            $contents[] = [
                'parts' => [
                    ['text' => $systemPrompt],
                    ['text' => "User message: " . $text]
                ]
            ];
        }

        $payload = [
            'contents' => $contents,
            'generationConfig' => [
                'responseMimeType' => 'application/json'
            ]
        ];

        try {
            $response = Http::post($url, $payload);

            if ($response->successful()) {
                $data = $response->json();
                $responseText = $data['candidates'][0]['content']['parts'][0]['text'] ?? null;
                
                if ($responseText) {
                    $json = json_decode($responseText, true);
                    return $json;
                }
            } elseif ($response->status() === 429) {
                Log::error('Gemini API Rate Limit Exceeded', $response->json());
                return ['action' => 'api_error', 'message' => 'Rate limit exceeded'];
            } else {
                Log::error('Gemini API Error', $response->json());
                return ['action' => 'api_error', 'message' => 'API Error'];
            }
        } catch (\Exception $e) {
            Log::error('Gemini Service Exception', ['error' => $e->getMessage()]);
            return ['action' => 'api_error', 'message' => $e->getMessage()];
        }

        return null;
    }
}
