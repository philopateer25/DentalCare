<x-filament-widgets::widget>
    <x-filament::section>
        <div class="flex items-center justify-between">
            <div>
                <h2 class="text-lg font-semibold">Web Push Notifications</h2>
                <p class="text-sm text-gray-500">Test if push notifications are reaching your registered devices.</p>
            </div>
            
            <div>
                {{ $this->testPushAction }}
            </div>
        </div>
    </x-filament::section>
</x-filament-widgets::widget>
