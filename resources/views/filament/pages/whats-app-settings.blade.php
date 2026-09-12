<x-filament-panels::page>
    <x-filament::section>
        <x-slot name="heading">
            WhatsApp Connection
        </x-slot>

        <x-slot name="description">
            Connect your clinic's WhatsApp account to automatically send updates to patients.
        </x-slot>

        <div class="flex flex-col items-center justify-center space-y-6 py-6">
            @if($isConnected)
                <div class="text-green-600 flex items-center space-x-2">
                    <x-heroicon-o-check-circle class="w-8 h-8" />
                    <span class="text-lg font-medium">WhatsApp is connected successfully!</span>
                </div>
            @elseif($qrCodeBase64)
                <div class="flex flex-col items-center space-y-4">
                    <p class="text-sm text-gray-500">Scan this QR code with your WhatsApp app to connect.</p>
                    <div class="p-4 bg-white border rounded-lg shadow-sm">
                        <img src="{{ $qrCodeBase64 }}" alt="WhatsApp QR Code" class="w-64 h-64" />
                    </div>
                    <p class="text-xs text-gray-400">The QR code will expire shortly. Refresh or reconnect if it times out.</p>
                    <x-filament::button wire:click="connectWhatsApp" color="gray">
                        Refresh QR Code
                    </x-filament::button>
                </div>
            @else
                <x-filament::button wire:click="connectWhatsApp" size="lg" icon="heroicon-o-qr-code">
                    Connect WhatsApp
                </x-filament::button>
            @endif

            <div class="pt-8 border-t border-gray-200 mt-8 w-full flex flex-col items-center gap-4">
                <x-filament::button wire:click="registerWebhook" color="success" size="sm">
                    Register Webhook (For Incoming Messages)
                </x-filament::button>
                <x-filament::button wire:click="resetConnection" color="danger" variant="outlined" size="sm">
                    Reset / Delete Connection
                </x-filament::button>
            </div>
        </div>
    </x-filament::section>
</x-filament-panels::page>
