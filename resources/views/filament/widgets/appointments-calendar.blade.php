<x-filament-widgets::widget>
    {{-- JS/CSS are injected into <head> by a Dashboard-scoped render hook (AdminPanelProvider). --}}
    <div
        wire:ignore
        class="dc-calendar-host"
        data-appointments-calendar
        data-config='@json($this->getConfig())'
    >
        {{-- Skeleton shown until React mounts --}}
        <div class="dc-calendar-skeleton">
            <div></div><div></div><div></div>
        </div>
    </div>
</x-filament-widgets::widget>
