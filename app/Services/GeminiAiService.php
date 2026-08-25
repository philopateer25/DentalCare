<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class GeminiAiService
{
    protected string $apiKey;
    protected string $apiUrl = 'https://generativelanguage.googleapis.com/v1beta/models/gemini-3.6-flash:generateContent';

    public function __construct()
    {
        $this->apiKey = env('GEMINI_API_KEY', '');
    }

    /**
     * Send an audio base64 string to Gemini 1.5 Flash to extract JSON.
     */
    public function analyzeVoiceNote(string $audioBase64, string $mimeType = 'audio/ogg'): ?array
    {
        if (empty($this->apiKey)) {
            Log::error('Gemini API key is not configured.');
            return null;
        }

        $prompt = "You are a highly capable dental clinic assistant. I will provide you with a voice note from a patient (it will likely be in Arabic).
Your task is to listen to the audio, TRANSLATE it to English, and extract the following information. All your output MUST be in English.
You MUST return ONLY a valid JSON object matching exactly this schema, without any markdown formatting or extra text:
{
  \"transcription\": \"The exact transcribed text translated into English\",
  \"patient_intent\": \"book_appointment, emergency, or general_question\",
  \"symptoms\": [\"symptom 1 in English\", \"symptom 2 in English\"],
  \"requested_time\": \"any mentioned day or time in English, or null if none\"
}";

        $payload = [
            'contents' => [
                [
                    'parts' => [
                        ['text' => $prompt],
                        [
                            'inlineData' => [
                                'mimeType' => $mimeType,
                                'data' => $audioBase64
                            ]
                        ]
                    ]
                ]
            ],
            'generationConfig' => [
                'responseMimeType' => 'application/json',
                'temperature' => 0.2
            ]
        ];

        try {
            $response = Http::post("{$this->apiUrl}?key={$this->apiKey}", $payload);

            if ($response->successful()) {
                $content = $response->json('candidates.0.content.parts.0.text');
                
                if ($content) {
                    $decoded = json_decode($content, true);
                    return $decoded;
                }
            }

            Log::error('Gemini API Failed', [
                'status' => $response->status(),
                'response' => $response->body()
            ]);

            return null;
        } catch (\Exception $e) {
            Log::error('Gemini API Exception', [
                'error' => $e->getMessage()
            ]);
            return null;
        }
    }
}
