@php
    $s = $this->getSummary();
    $names = $this->getFeatureNames();
    $modeStyles = [
        'full' => ['bg-emerald-50 dark:bg-emerald-950/40 text-emerald-700 dark:text-emerald-300', __('Active')],
        'read_only' => ['bg-amber-50 dark:bg-amber-950/40 text-amber-700 dark:text-amber-300', __('Read-only')],
        'locked' => ['bg-red-50 dark:bg-red-950/40 text-red-700 dark:text-red-300', __('Locked')],
    ];
    [$modeClass, $modeLabel] = $modeStyles[$s['mode']] ?? $modeStyles['locked'];
    $fmt = fn ($v) => $v ? \Illuminate\Support\Carbon::parse($v)->timezone(config('app.timezone'))->format('d M Y H:i') : '—';
@endphp

<x-filament-panels::page>
    @unless ($s['enforced'])
        <div class="p-4 rounded-xl border border-gray-200 dark:border-gray-700 bg-gray-50 dark:bg-gray-800 text-sm text-gray-600 dark:text-gray-300">
            {{ __('License checks are switched off on this installation (development mode). Everything is enabled.') }}
        </div>
    @endunless

    <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
        <div class="p-5 rounded-xl border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800 shadow-sm">
            <p class="text-xs font-medium uppercase tracking-wider text-gray-500 dark:text-gray-400">{{ __('Status') }}</p>
            <span class="inline-block mt-2 px-3 py-1 rounded-full text-sm font-semibold {{ $modeClass }}">{{ $modeLabel }}</span>
            @if ($s['reason'])
                <p class="mt-3 text-sm text-gray-700 dark:text-gray-300">{{ $s['reason'] }}</p>
            @endif
        </div>

        <div class="p-5 rounded-xl border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800 shadow-sm">
            <p class="text-xs font-medium uppercase tracking-wider text-gray-500 dark:text-gray-400">{{ __('Plan') }}</p>
            <p class="mt-2 text-lg font-bold text-gray-900 dark:text-white">
                {{ $s['package_tier'] ? (\App\Services\FeatureManager::getPackage($s['package_tier'])['name'] ?? $s['package_tier']) : '—' }}
            </p>
            <p class="mt-1 text-sm text-gray-600 dark:text-gray-400">
                {{ __('Paid until') }}: <span class="font-semibold">{{ $s['paid_until'] ? \Illuminate\Support\Carbon::parse($s['paid_until'])->format('d M Y') : '—' }}</span>
            </p>
            <p class="text-sm text-gray-600 dark:text-gray-400">
                {{ __('Chairs') }}: {{ $s['chair_limit'] ?? '—' }} · {{ __('X-ray') }}: {{ $s['xray_limit'] ?? '—' }} · {{ __('Rollout stage') }}: {{ $s['rollout_stage'] ?? '—' }}
            </p>
        </div>

        <div class="p-5 rounded-xl border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800 shadow-sm">
            <p class="text-xs font-medium uppercase tracking-wider text-gray-500 dark:text-gray-400">{{ __('Connection') }}</p>
            <p class="mt-2 text-sm text-gray-700 dark:text-gray-300">{{ __('Last sync') }}: <span class="font-semibold">{{ $fmt($s['last_sync_at']) }}</span></p>
            <p class="text-sm text-gray-700 dark:text-gray-300">{{ __('Clinic ID') }}: <span class="font-mono">{{ $s['clinic_id'] ?? '—' }}</span></p>
            @if ($s['offline_unlock_until'])
                <p class="text-sm text-amber-700 dark:text-amber-300">{{ __('Offline unlock until') }}: {{ $fmt($s['offline_unlock_until']) }}</p>
            @endif
            @if (($s['state']['status'] ?? null) && in_array($s['state']['status'], ['grace_warning', 'grace_urgent'], true))
                <p class="mt-2 text-sm text-amber-700 dark:text-amber-300">{{ $s['state']['message'] }}</p>
            @endif
        </div>
    </div>

    <div class="p-5 rounded-xl border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800 shadow-sm">
        <p class="text-xs font-medium uppercase tracking-wider text-gray-500 dark:text-gray-400 mb-3">{{ __('Licensed features') }}</p>
        @if (is_array($s['features']))
            <div class="flex flex-wrap gap-2">
                @foreach ($names as $key => $name)
                    @php $on = in_array($key, $s['features'], true); @endphp
                    <span class="px-3 py-1 rounded-full text-xs font-medium {{ $on ? 'bg-teal-50 dark:bg-teal-950/40 text-teal-700 dark:text-teal-300' : 'bg-gray-100 dark:bg-gray-700 text-gray-400 dark:text-gray-500 line-through' }}">
                        {{ $name }}
                    </span>
                @endforeach
            </div>
        @else
            <p class="text-sm text-gray-500 dark:text-gray-400">{{ $s['enforced'] ? __('No verified license yet. Enter your license key or press Sync now.') : __('All features (development mode).') }}</p>
        @endif
    </div>

    @if (\App\Filament\Pages\LicensePage::canManage())
        <p class="text-sm text-gray-500 dark:text-gray-400">
            {{ __('"Export all data" downloads every patient, appointment, invoice and payment of your clinic as Excel-readable CSV files plus patient files, in one ZIP. It always works, even when the system is locked.') }}
        </p>
    @endif
</x-filament-panels::page>
