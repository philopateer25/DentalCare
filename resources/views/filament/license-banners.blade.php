@php
    $banners = app(\App\Services\LicenseManager::class)->getBanners();
    $tones = [
        'danger' => 'bg-red-50 dark:bg-red-950/50 border-red-200 dark:border-red-800 text-red-800 dark:text-red-200',
        'warning' => 'bg-amber-50 dark:bg-amber-950/50 border-amber-200 dark:border-amber-800 text-amber-800 dark:text-amber-200',
        'success' => 'bg-emerald-50 dark:bg-emerald-950/50 border-emerald-200 dark:border-emerald-800 text-emerald-800 dark:text-emerald-200',
        'info' => 'bg-sky-50 dark:bg-sky-950/50 border-sky-200 dark:border-sky-800 text-sky-800 dark:text-sky-200',
    ];
@endphp

@if (! empty($banners))
    <div class="space-y-2 mb-4" role="status">
        @foreach ($banners as $banner)
            <div class="px-4 py-3 rounded-lg border text-sm font-medium {{ $tones[$banner['level']] ?? $tones['info'] }}">
                {{ $banner['message'] }}
            </div>
        @endforeach
    </div>
@endif
