<?php

namespace App\Filament\Pages;

use Filament\Pages\Page;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\Http;
use Filament\Facades\Filament;

class WhatsAppSettings extends Page
{
    protected static ?string $navigationIcon = 'heroicon-o-chat-bubble-left-right';
    protected static ?string $navigationGroup = 'Settings';
    protected static string $view = 'filament.pages.whats-app-settings';
    protected static ?string $title = 'WhatsApp Integration';

    public ?string $qrCodeBase64 = null;
    public bool $isConnected = false;

    public function resetConnection()
    {
        $practice = Filament::getTenant();
        $instanceName = $practice ? 'clinic_' . $practice->id : 'default_clinic';
        $apiUrl = env('EVOLUTION_API_URL', 'http://evolution_api:8080');
        $apiKey = env('EVOLUTION_API_KEY', '');

        try {
            Http::withHeaders(['apikey' => $apiKey])->delete("{$apiUrl}/instance/delete/{$instanceName}");
            
            $this->isConnected = false;
            $this->qrCodeBase64 = null;
            
            Notification::make()->title('Connection reset successfully. You can now reconnect.')->success()->send();
        } catch (\Exception $e) {
            Notification::make()->title('Failed to reset')->danger()->send();
        }
    }

    public function registerWebhook()
    {
        $practice = Filament::getTenant();
        $instanceName = $practice ? 'clinic_' . $practice->id : 'default_clinic';
        $apiUrl = env('EVOLUTION_API_URL', 'http://evolution_api:8080');
        $apiKey = env('EVOLUTION_API_KEY', '');

        // Using the WSL vEthernet IP to reach the Windows host running `php artisan serve`
        $webhookUrl = 'http://172.31.176.1:8000/api/whatsapp/webhook';

        try {
            $response = Http::withHeaders([
                'apikey' => $apiKey,
                'Content-Type' => 'application/json'
            ])->post("{$apiUrl}/webhook/set/{$instanceName}", [
                'webhook' => [
                    'enabled' => true,
                    'url' => $webhookUrl,
                    'byEvents' => false,
                    'base64' => false,
                    'events' => [
                        'MESSAGES_UPSERT'
                    ]
                ]
            ]);

            if ($response->successful()) {
                Notification::make()->title('Webhook registered successfully!')->success()->send();
            } else {
                Notification::make()->title('Failed to register webhook')->body($response->body())->danger()->send();
            }
        } catch (\Exception $e) {
            Notification::make()->title('Error')->body($e->getMessage())->danger()->send();
        }
    }

    public function connectWhatsApp()
    {
        $practice = Filament::getTenant();
        // Fallback name if no tenant context
        $instanceName = $practice ? 'clinic_' . $practice->id : 'default_clinic';
        
        $apiUrl = env('EVOLUTION_API_URL', 'http://evolution_api:8080');
        $apiKey = env('EVOLUTION_API_KEY', ''); // Add your global API key if needed

        try {
            // First, let's check the current connection state of this instance
            $stateResponse = Http::withHeaders(['apikey' => $apiKey])
                ->get("{$apiUrl}/instance/connectionState/{$instanceName}");

            if ($stateResponse->successful()) {
                $stateData = $stateResponse->json();
                
                // If it exists and is already connected, we don't need a QR code
                if (isset($stateData['instance']['state']) && $stateData['instance']['state'] === 'open') {
                    $this->isConnected = true;
                    Notification::make()->title('WhatsApp is already connected!')->success()->send();
                    return;
                }

                // If it exists but is NOT connected, ask for a new QR code
                $connectResponse = Http::withHeaders(['apikey' => $apiKey])
                    ->get("{$apiUrl}/instance/connect/{$instanceName}");
                    
                if ($connectResponse->successful()) {
                    $data = $connectResponse->json();
                    if (isset($data['base64'])) {
                        $this->qrCodeBase64 = $data['base64'];
                        Notification::make()->title('QR Code refreshed. Please scan.')->success()->send();
                    } else {
                        Notification::make()->title('No base64 in response')->body(json_encode($data))->warning()->send();
                    }
                    return;
                } else {
                    Notification::make()->title('Failed to get QR code')->body($connectResponse->body())->danger()->send();
                    return;
                }
            }

            // If the instance doesn't exist at all, we create it
            $response = Http::withHeaders([
                'apikey' => $apiKey
            ])->post("{$apiUrl}/instance/create", [
                'instanceName' => $instanceName,
                'qrcode' => true,
                'integration' => 'WHATSAPP-BAILEYS',
            ]);

            if ($response->successful()) {
                $data = $response->json();
                
                if (isset($data['qrcode']['base64'])) {
                    $this->qrCodeBase64 = $data['qrcode']['base64'];
                    Notification::make()->title('Instance created. Please scan the QR code.')->success()->send();
                } else {
                    Notification::make()->title('Could not fetch QR Code from create. Try again.')->body(json_encode($data))->warning()->send();
                }
            } else {
                Notification::make()
                    ->title('Error connecting to Evolution API')
                    ->body($response->body())
                    ->danger()
                    ->send();
            }
        } catch (\Exception $e) {
            Notification::make()
                ->title('Connection Failed')
                ->body($e->getMessage())
                ->danger()
                ->send();
        }
    }
}
