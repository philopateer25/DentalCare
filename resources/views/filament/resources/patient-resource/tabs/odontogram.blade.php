@php
    $patientId = $getRecord()->id;
    $embedUrl = route('patient.odontogram', $patientId);
    $detailsUrl = route('patient.odontogram.details', $patientId);
@endphp

{{--
    3D mouth chart tab. The chart page is embedded in an iframe; the "Open clinical details"
    action lives here (outside the iframe) so it is always visible, including on phones.
    The iframe only loads once the tab is visible: loading it inside a hidden tab gave the
    WebGL canvas a 0×0 size and the model stayed blank.
--}}
<div class="dx-3d"
     x-data="{ isDark: document.documentElement.classList.contains('dark'), load: false }"
     x-init="const t = setInterval(() => { if ($el.offsetWidth > 0) { load = true; clearInterval(t); } }, 250)"
     @theme-changed.window="isDark = document.documentElement.classList.contains('dark')">

    <div class="dx-3d-bar">
        <div class="dx-3d-title">
            <x-filament::icon icon="heroicon-o-cube-transparent" class="h-5 w-5" />
            <div>
                <strong>{{ __('3D mouth chart') }}</strong>
                <span>{{ __('Rotate, zoom and tap a tooth. Open clinical details to chart findings and plan treatment.') }}</span>
            </div>
        </div>
        <div class="dx-3d-actions">
            <a href="{{ $embedUrl }}" target="_blank" rel="noopener" class="dx-btn dx-btn-tint">
                <x-filament::icon icon="heroicon-o-arrows-pointing-out" class="h-4 w-4" />
                {{ __('Full screen') }}
            </a>
            <a href="{{ $detailsUrl }}" target="_blank" rel="noopener" class="dx-btn dx-btn-primary">
                <x-filament::icon icon="heroicon-o-pencil-square" class="h-4 w-4" />
                {{ __('Open clinical details') }}
            </a>
        </div>
    </div>

    <div class="dx-3d-frame">
        <template x-if="load">
            <iframe
                x-bind:src="`{{ $embedUrl }}?embed=1&dark=` + (isDark ? '1' : '0')"
                title="{{ __('3D mouth chart') }}"
                allowfullscreen
                loading="lazy"></iframe>
        </template>
        <div class="dx-3d-loading" x-show="!load">{{ __('Loading 3D chart…') }}</div>
    </div>
</div>
