<x-filament-panels::page class="dxw-page">
    {{-- React Chart workspace (resources/js/filament/chart-workspace.jsx). --}}
    <div wire:ignore data-chart-workspace data-config='@json($this->getConfig())' class="dxw-host">
        <div class="dxw-skeleton">{{ __('Loading dental chart…') }}</div>
    </div>
</x-filament-panels::page>
