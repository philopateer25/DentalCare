@php
    $stages = $this->getStages();
    $badge = [
        'on' => ['success', __('On now')],
        'next' => ['primary', __('Coming next')],
        'later' => ['gray', __('Later')],
    ];
@endphp

{{-- Only Filament components / Filament's prebuilt classes here: the admin theme has no Tailwind build (docs/decisions.md #13). --}}
<x-filament-panels::page>
    <x-filament::section>
        <p class="text-sm text-gray-950 dark:text-white">
            {{ __('We switch the system on a few parts at a time so nobody has to learn everything on day one. When your team is comfortable with what is on now, we turn on the next stage — just tell us.') }}
        </p>
    </x-filament::section>

    <div style="display:grid;gap:1rem;grid-template-columns:repeat(auto-fit,minmax(18rem,1fr))">
        @foreach ($stages as $stage)
            @php [$color, $label] = $badge[$stage['state']]; @endphp
            <x-filament::section>
                <x-slot name="heading">{{ $stage['title'] }}</x-slot>
                <x-slot name="headerEnd"><x-filament::badge :color="$color">{{ $label }}</x-filament::badge></x-slot>

                <ul class="space-y-1 text-sm">
                    @foreach ($stage['modules'] as $module)
                        <li class="flex items-start gap-2 {{ $stage['state'] === 'later' ? 'text-gray-500 dark:text-gray-400' : 'text-gray-950 dark:text-white' }}">
                            <span aria-hidden="true">{{ $stage['state'] === 'on' && $module['in_plan'] ? '✓' : '•' }}</span>
                            <span>
                                {{ $module['name'] }}
                                @unless ($module['in_plan'])
                                    <span class="text-xs text-gray-500 dark:text-gray-400">({{ __('not in your plan') }})</span>
                                @endunless
                            </span>
                        </li>
                    @endforeach
                </ul>
            </x-filament::section>
        @endforeach
    </div>
</x-filament-panels::page>
