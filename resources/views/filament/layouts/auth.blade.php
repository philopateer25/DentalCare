@php
    $livewire ??= null;
@endphp

{{-- Full-width brand layout for auth pages (design/stitch/06-login). --}}
<x-filament-panels::layout.base :livewire="$livewire">
    <div class="dx-auth">
        <header class="dx-auth-header">
            <div class="dx-auth-header-inner">
                @include('filament.brand.logo')
                <div class="dx-auth-header-tools">
                    @include('filament.components.language-switch')
                </div>
            </div>
            <svg class="dx-auth-header-flourish" viewBox="0 0 480 72" preserveAspectRatio="none" aria-hidden="true">
                <polygon fill="#d82c1e" points="40,0 120,0 80,72 0,72"/>
                <polygon fill="#0039a6" points="110,0 480,0 480,48 150,48"/>
                <polygon fill="#009e83" points="180,48 480,48 480,72 140,72"/>
            </svg>
        </header>

        <main class="dx-auth-main">
            {{ $slot }}
        </main>

        <footer class="dx-auth-footer">
            <svg class="dx-auth-footer-flourish" viewBox="0 0 420 54" preserveAspectRatio="none" aria-hidden="true">
                <polygon fill="#d82c1e" points="0,0 80,0 60,54 0,54"/>
                <polygon fill="#009e83" points="75,0 145,0 120,54 50,54"/>
                <polygon fill="#3859a8" points="140,0 230,0 190,54 115,54"/>
            </svg>
            <div class="dx-footer-ribbon" aria-hidden="true"><span></span><span></span><span></span></div>
            <div class="dx-auth-footer-row">
                <span class="dx-auth-footer-copy">&copy; {{ now()->year }} DOXamigo Clinical Systems</span>
                <span class="dx-footer-secure">
                    <svg viewBox="0 0 20 20" fill="currentColor" aria-hidden="true"><path fill-rule="evenodd" d="M10 1.9 3 4.6v5.1c0 4.2 3 7.7 7 8.4 4-.7 7-4.2 7-8.4V4.6L10 1.9Zm3.4 6.3a.8.8 0 0 0-1.2-1l-3 3.4-1.4-1.4a.8.8 0 1 0-1.1 1.1l2 2a.8.8 0 0 0 1.1 0l3.6-4.1Z" clip-rule="evenodd"/></svg>
                    {{ __('Encrypted & backed up') }}
                </span>
            </div>
        </footer>
    </div>
</x-filament-panels::layout.base>
