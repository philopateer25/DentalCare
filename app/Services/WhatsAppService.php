<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class WhatsAppService
{
    protected string $apiUrl;
    protected string $token;

    public function __construct()
    {
        $this->apiUrl = rtrim(config('whatsapp.api_url'), '/');
        $this->token = config('whatsapp.token');
    }

    /**
     * Send a standard text message.
     */
    public function sendMessage(string $phone, string $message): bool
    {
        if (empty($this->apiUrl) || empty($this->token)) {
            Log::error('WhatsApp API URL or Token is not configured.');
            return false;
        }

        try {
            $response = Http::post("{$this->apiUrl}/messages/chat", [
                'token' => $this->token,
                'to' => $phone,
                'body' => $message,
            ]);

            if ($response->successful()) {
                return true;
            }

            Log::error('WhatsApp message failed', [
                'phone' => $phone,
                'response' => $response->body(),
                'status' => $response->status(),
            ]);

            return false;
        } catch (\Exception $e) {
            Log::error('WhatsApp message exception', [
                'phone' => $phone,
                'error' => $e->getMessage(),
            ]);
            return false;
        }
    }

    /**
     * Send a document (PDF) message.
     */
    public function sendDocument(string $phone, string $documentUrl, string $fileName, string $caption = ''): bool
    {
        if (empty($this->apiUrl) || empty($this->token)) {
            Log::error('WhatsApp API URL or Token is not configured.');
            return false;
        }

        try {
            $response = Http::post("{$this->apiUrl}/messages/document", [
                'token' => $this->token,
                'to' => $phone,
                'document' => $documentUrl,
                'filename' => $fileName,
                'caption' => $caption,
            ]);

            if ($response->successful()) {
                return true;
            }

            Log::error('WhatsApp document failed', [
                'phone' => $phone,
                'response' => $response->body(),
                'status' => $response->status(),
                'url' => "{$this->apiUrl}/messages/document"
            ]);
            
            throw new \Exception("WhatsApp document API failed: " . $response->body());
        } catch (\Exception $e) {
            Log::error('WhatsApp document exception', [
                'phone' => $phone,
                'error' => $e->getMessage(),
            ]);
            return false;
        }
    }
}
