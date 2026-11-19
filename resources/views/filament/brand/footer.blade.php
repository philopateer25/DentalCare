{{-- Brand footer (brand kit): tri-colour ribbon + compact info row. --}}
<footer class="dx-footer">
    <div class="dx-footer-ribbon" aria-hidden="true"><span></span><span></span><span></span></div>
    <div class="dx-footer-row">
        <div class="dx-footer-left">
            <span>&copy; {{ now()->year }} DOXamigo Clinical Systems</span>
            @if ($tenant = \Filament\Facades\Filament::getTenant())
                <span class="dx-footer-dot">&bull;</span>
                <span>{{ $tenant->name }}</span>
            @endif
        </div>
        <div class="dx-footer-right">
            <span class="dx-footer-secure">
                <svg viewBox="0 0 20 20" fill="currentColor" aria-hidden="true"><path fill-rule="evenodd" d="M10 1.9 3 4.6v5.1c0 4.2 3 7.7 7 8.4 4-.7 7-4.2 7-8.4V4.6L10 1.9Zm3.4 6.3a.8.8 0 0 0-1.2-1l-3 3.4-1.4-1.4a.8.8 0 1 0-1.1 1.1l2 2a.8.8 0 0 0 1.1 0l3.6-4.1Z" clip-rule="evenodd"/></svg>
                Encrypted &amp; backed up
            </span>
        </div>
    </div>
</footer>
