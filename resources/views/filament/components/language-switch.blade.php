@php
    $current = \App\Services\LanguageHelper::currentLocale();
    $locales = \App\Services\LanguageHelper::LOCALES;
    $activeInfo = $locales[$current] ?? $locales['en'];
@endphp

<div class="relative flex items-center" x-data="{ open: false }" @click.outside="open = false" @close.stop="open = false">
    <button
        type="button"
        @click="open = !open"
        class="fi-topbar-item inline-flex items-center gap-1.5 px-2.5 py-1.5 rounded-lg text-xs font-semibold bg-gray-100 dark:bg-gray-800 hover:bg-gray-200 dark:hover:bg-gray-700 text-gray-700 dark:text-gray-200 transition-colors border border-gray-200 dark:border-gray-700 shadow-sm focus:outline-none focus:ring-2 focus:ring-teal-500"
        title="{{ __('Language') }}"
    >
        <span class="text-base leading-none">{{ $activeInfo['flag'] }}</span>
        <span class="uppercase font-bold tracking-wider">{{ $current }}</span>
        <svg class="w-3.5 h-3.5 text-gray-500 transition-transform duration-200" :class="{ 'rotate-180': open }" fill="none" viewBox="0 0 24 24" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
        </svg>
    </button>

    <div
        x-show="open"
        x-transition:enter="transition ease-out duration-100"
        x-transition:enter-start="transform opacity-0 scale-95"
        x-transition:enter-end="transform opacity-100 scale-100"
        x-transition:leave="transition ease-in duration-75"
        x-transition:leave-start="transform opacity-100 scale-100"
        x-transition:leave-end="transform opacity-0 scale-95"
        class="absolute z-50 top-full mt-2 end-0 w-44 rounded-xl bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-800 shadow-xl py-1 overflow-hidden"
        style="display: none;"
    >
        <div class="px-3 py-1.5 text-[11px] font-bold uppercase tracking-wider text-gray-400 border-b border-gray-100 dark:border-gray-800">
            {{ __('Select Language') }}
        </div>

        @foreach($locales as $code => $info)
            <a
                href="{{ route('locale.switch', $code) }}"
                class="flex items-center justify-between px-3 py-2 text-xs font-medium transition-colors hover:bg-gray-50 dark:hover:bg-gray-800 {{ $current === $code ? 'bg-teal-50 dark:bg-teal-950/40 text-teal-600 dark:text-teal-400 font-bold' : 'text-gray-700 dark:text-gray-300' }}"
            >
                <div class="flex items-center gap-2">
                    <span class="text-base leading-none">{{ $info['flag'] }}</span>
                    <span>{{ $info['native'] }}</span>
                </div>
                @if($current === $code)
                    <svg class="w-4 h-4 text-teal-500" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7" />
                    </svg>
                @endif
            </a>
        @endforeach
    </div>
</div>
