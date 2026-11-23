@php
    $url = $scan['url'] ?? null;
    $qr = $url ? \App\Services\PaperRecords\PaperCardScan::qrSvg($url) : null;
    $received = $page->paperCardsReceived();
@endphp

{{-- Only Filament components / Filament's prebuilt classes here: the admin theme has no Tailwind build (docs/decisions.md #13). --}}
<div class="space-y-4 text-sm text-gray-950 dark:text-white">
    @if ($url)
        <p>{{ $qr ? __('Open the camera on a phone connected to the clinic Wi-Fi and point it at this code:') : __('On a phone connected to the clinic Wi-Fi, open this address:') }}</p>

        @if ($qr)
            <div style="display:flex;justify-content:center">
                <div style="background:#fff;padding:8px;border-radius:12px;line-height:0">{!! $qr !!}</div>
            </div>
            <p class="text-center text-gray-500 dark:text-gray-400">{{ __('Or type:') }}</p>
        @endif

        <p class="text-center font-mono font-semibold" style="word-break:break-all;user-select:all">{{ $url }}</p>
        <p class="text-gray-500 dark:text-gray-400">{{ __('Works once, until :time. Take photos of every page, then press "Save" on the phone.', ['time' => $scan['expires']]) }}</p>
    @else
        <x-filament::section>
            <p>{{ __('A phone cannot open "localhost". Open DOXamigo on this computer using its network address (for example http://192.168.1.10), or ask us to set the clinic address (CLINIC_LAN_URL).') }}</p>
            <p class="mt-2 text-gray-500 dark:text-gray-400">{{ __('Address on this computer: :path', ['path' => url($scan['path'] ?? '')]) }}</p>
        </x-filament::section>
    @endif

    <div wire:poll.3s class="flex items-center gap-2">
        @if ($received > 0)
            <x-filament::badge color="success" icon="heroicon-o-check-circle">
                {{ trans_choice(':count photo received|:count photos received', $received, ['count' => $received]) }}
            </x-filament::badge>
        @else
            <x-filament::loading-indicator class="h-4 w-4 text-gray-400" />
            <span class="text-gray-500 dark:text-gray-400">{{ __('Waiting for photos…') }}</span>
        @endif
    </div>
</div>
